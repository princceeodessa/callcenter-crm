<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $search = trim((string) $request->query('q', ''));
        $assignedUserId = (int) ($request->query('assigned_user_id') ?? 0);

        $taskQuery = Task::query()
            ->with([
                'assignedTo:id,name',
                'deal:id,title,title_is_custom,contact_id,stage_id',
                'deal.contact:id,name,phone',
                'deal.stage:id,name',
            ])
            ->where('account_id', $user->account_id)
            ->where('status', 'open')
            ->when($assignedUserId > 0, fn ($query) => $query->where('assigned_user_id', $assignedUserId))
            ->when($assignedUserId === -1, fn ($query) => $query->whereNull('assigned_user_id'))
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('title', 'like', "%{$search}%")
                        ->orWhere('description', 'like', "%{$search}%")
                        ->orWhereHas('deal', fn ($deal) => $deal->where('title', 'like', "%{$search}%"))
                        ->orWhereHas('deal.contact', fn ($contact) => $contact
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('phone', 'like', "%{$search}%"))
                        ->orWhereHas('assignedTo', fn ($assignee) => $assignee->where('name', 'like', "%{$search}%"));
                });
            });

        $todayStart = now()->startOfDay();
        $tomorrowStart = (clone $todayStart)->addDay();

        $todayTasks = (clone $taskQuery)
            ->whereBetween('due_at', [$todayStart, $tomorrowStart])
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $overdueTasks = (clone $taskQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $todayStart)
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $upcomingTasks = (clone $taskQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', $tomorrowStart)
            ->orderBy('due_at')
            ->orderBy('id')
            ->limit(100)
            ->get();

        $users = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', 1)
            ->orderBy('name')
            ->get(['id', 'name']);

        $deals = Deal::query()
            ->with(['contact:id,name,phone'])
            ->where('account_id', $user->account_id)
            ->whereNull('closed_at')
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get(['id', 'title', 'title_is_custom', 'contact_id', 'updated_at']);

        return view('tasks.index', [
            'todayTasks' => $todayTasks,
            'overdueTasks' => $overdueTasks,
            'upcomingTasks' => $upcomingTasks,
            'users' => $users,
            'deals' => $deals,
            'search' => $search,
            'assignedUserId' => $assignedUserId,
            'todayLabel' => $todayStart->format('d.m.Y'),
        ]);
    }

    public function store(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $this->createTask($deal, $data, $user);

        return back();
    }

    public function storeFromPage(Request $request)
    {
        $data = $request->validate([
            'deal_id' => ['required', 'integer', 'exists:deals,id'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
        ]);

        $user = Auth::user();
        $deal = Deal::query()
            ->where('account_id', $user->account_id)
            ->findOrFail((int) $data['deal_id']);

        $this->createTask($deal, $data, $user);

        return redirect()->route('tasks.index')->with('status', 'Дело добавлено');
    }

    public function complete(Request $request, Task $task)
    {
        $user = Auth::user();
        abort_unless($task->account_id === $user->account_id, 403);

        $task->status = 'done';
        $task->completed_at = now();
        $task->save();

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $task->deal_id,
            'author_user_id' => $user->id,
            'type' => 'task_done',
            'body' => 'Дело выполнено: '.$task->title,
            'payload' => ['task_id' => $task->id],
        ]);

        return back();
    }

    private function createTask(Deal $deal, array $data, $user): Task
    {
        $assigneeId = $this->resolveAssigneeId($user->account_id, $data['assigned_user_id'] ?? null);

        $task = Task::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'assigned_user_id' => $assigneeId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'open',
            'due_at' => $data['due_at'],
        ]);

        if ($task->assigned_user_id && $task->due_at && $task->due_at->lte(now())) {
            DB::transaction(function () use ($task) {
                $fresh = Task::query()->lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->notified_at || !$fresh->assigned_user_id) {
                    return;
                }

                $dealTitle = $fresh->deal?->title ?? ('Сделка #'.$fresh->deal_id);

                UserNotification::query()->firstOrCreate(
                    [
                        'user_id' => $fresh->assigned_user_id,
                        'type' => 'task_due',
                        'source_type' => 'task',
                        'source_id' => $fresh->id,
                    ],
                    [
                        'account_id' => $fresh->account_id,
                        'title' => 'Пора выполнить дело',
                        'body' => "{$fresh->title} (сделка: {$dealTitle})",
                        'payload' => [
                            'task_id' => $fresh->id,
                            'deal_id' => $fresh->deal_id,
                            'due_at' => optional($fresh->due_at)->toISOString(),
                        ],
                        'is_read' => 0,
                    ]
                );

                $fresh->notified_at = now();
                $fresh->save();
            });
        }

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $user->id,
            'type' => 'task_created',
            'body' => 'Создано дело: '.$task->title.' • Назначено: '.$task->assignee_label,
            'payload' => [
                'task_id' => $task->id,
                'assigned_user_id' => $task->assigned_user_id,
                'assigned_label' => $task->assignee_label,
            ],
        ]);

        return $task;
    }

    private function resolveAssigneeId(int $accountId, mixed $value): ?int
    {
        if ($value === null || $value === '' || (string) $value === '0') {
            return null;
        }

        $assigneeId = (int) $value;
        $assigneeOk = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', 1)
            ->where('id', $assigneeId)
            ->exists();

        if (!$assigneeOk) {
            throw ValidationException::withMessages([
                'assigned_user_id' => 'Нельзя назначить дело этому пользователю',
            ]);
        }

        return $assigneeId;
    }
}