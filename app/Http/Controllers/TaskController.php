<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Integrations\BitrixTaskSyncService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
        $focusDate = $this->resolveFocusDate((string) $request->query('focus_date', ''));
        $dayStart = $focusDate->copy()->startOfDay();
        $dayEnd = $focusDate->copy()->addDay()->startOfDay();

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

        $selectedTasks = (clone $taskQuery)
            ->where('due_at', '>=', $dayStart)
            ->where('due_at', '<', $dayEnd)
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $previousTasks = (clone $taskQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '<', $dayStart)
            ->orderBy('due_at')
            ->orderBy('id')
            ->get();

        $nextTasks = (clone $taskQuery)
            ->whereNotNull('due_at')
            ->where('due_at', '>=', $dayEnd)
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
            'selectedTasks' => $selectedTasks,
            'previousTasks' => $previousTasks,
            'nextTasks' => $nextTasks,
            'users' => $users,
            'deals' => $deals,
            'search' => $search,
            'assignedUserId' => $assignedUserId,
            'focusDate' => $dayStart->toDateString(),
            'focusDateLabel' => $dayStart->format('d.m.Y'),
            'isTodayFocusDate' => $dayStart->isSameDay(now()),
            'editingTaskId' => max(0, (int) $request->query('edit_task', 0)),
        ]);
    }

    public function store(Request $request, Deal $deal)
    {
        $data = $this->validateTaskData($request);

        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $this->createTask($deal, $data, $user);

        return back();
    }

    public function storeFromPage(Request $request)
    {
        $data = $this->validateTaskData($request, true);

        $user = Auth::user();
        $deal = Deal::query()
            ->where('account_id', $user->account_id)
            ->findOrFail((int) $data['deal_id']);

        $this->createTask($deal, $data, $user);

        return redirect()->route('tasks.index')->with('status', 'Дело добавлено');
    }

    public function update(Request $request, Task $task, BitrixTaskSyncService $bitrixSync)
    {
        $user = Auth::user();
        abort_unless($task->account_id === $user->account_id, 403);

        $data = $this->validateTaskUpdateData($request);

        $task->assigned_user_id = $this->resolveAssigneeId(
            $user->account_id,
            $data['assigned_user_id'] ?? null,
            'edit_assigned_user_id'
        );
        $task->title = $data['title'];
        $task->description = $data['description'] ?? null;
        $task->due_at = $data['due_at'];
        $task->save();

        $task->load(['deal', 'assignedTo']);
        $this->syncDueNotificationState($task);

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $task->deal_id,
            'author_user_id' => $user->id,
            'type' => 'task_updated',
            'body' => 'Дело обновлено: '.$task->title,
            'payload' => [
                'task_id' => $task->id,
                'assigned_user_id' => $task->assigned_user_id,
                'assigned_label' => $task->assignee_label,
                'due_at' => optional($task->due_at)->toISOString(),
            ],
        ]);

        $bitrixSync->syncUpdatedTask($task, $user);

        return back()->with('status', 'Дело обновлено');
    }

    public function complete(Request $request, Task $task, BitrixTaskSyncService $bitrixSync)
    {
        $user = Auth::user();
        abort_unless($task->account_id === $user->account_id, 403);

        $task->status = 'done';
        $task->completed_at = now();
        $task->save();

        $this->syncDueNotificationState($task);

        DealActivity::create([
            'account_id' => $user->account_id,
            'deal_id' => $task->deal_id,
            'author_user_id' => $user->id,
            'type' => 'task_done',
            'body' => 'Дело выполнено: '.$task->title,
            'payload' => ['task_id' => $task->id],
        ]);

        $task->loadMissing('deal');
        $bitrixSync->syncCompletedTask($task, $user);

        return back();
    }

    private function createTask(Deal $deal, array $data, User $user): Task
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

        $this->syncDueNotificationState($task);

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

        app(BitrixTaskSyncService::class)->syncCreatedTask($task, $deal, $user);

        return $task;
    }

    private function validateTaskData(Request $request, bool $includeDealId = false): array
    {
        $rules = [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
        ];

        if ($includeDealId) {
            $rules = [
                'deal_id' => ['required', 'integer', 'exists:deals,id'],
                ...$rules,
            ];
        }

        return $request->validate($rules);
    }

    private function validateTaskUpdateData(Request $request): array
    {
        $data = $request->validate([
            'edit_title' => ['required', 'string', 'max:255'],
            'edit_description' => ['nullable', 'string'],
            'edit_due_at' => ['required', 'date'],
            'edit_assigned_user_id' => ['nullable', 'integer'],
        ]);

        return [
            'title' => $data['edit_title'],
            'description' => $data['edit_description'] ?? null,
            'due_at' => $data['edit_due_at'],
            'assigned_user_id' => $data['edit_assigned_user_id'] ?? null,
        ];
    }

    private function resolveFocusDate(string $value): Carbon
    {
        $value = trim($value);
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return now()->startOfDay();
        }

        try {
            $date = Carbon::createFromFormat('Y-m-d', $value)->startOfDay();

            return $date->format('Y-m-d') === $value
                ? $date
                : now()->startOfDay();
        } catch (\Throwable) {
            return now()->startOfDay();
        }
    }

    private function syncDueNotificationState(Task $task): void
    {
        $notificationQuery = fn () => UserNotification::query()
            ->where('account_id', $task->account_id)
            ->where('type', 'task_due')
            ->where('source_type', 'task')
            ->where('source_id', $task->id);

        if (
            $task->status !== 'open'
            || ! $task->assigned_user_id
            || ! $task->due_at
            || $task->due_at->gt(now())
        ) {
            $notificationQuery()->delete();

            if ($task->notified_at !== null) {
                $task->forceFill(['notified_at' => null])->save();
            }

            return;
        }

        DB::transaction(function () use ($task, $notificationQuery) {
            $fresh = Task::query()
                ->with('deal:id,title')
                ->lockForUpdate()
                ->find($task->id);

            if (! $fresh) {
                return;
            }

            if (
                $fresh->status !== 'open'
                || ! $fresh->assigned_user_id
                || ! $fresh->due_at
                || $fresh->due_at->gt(now())
            ) {
                $notificationQuery()->delete();

                if ($fresh->notified_at !== null) {
                    $fresh->notified_at = null;
                    $fresh->save();
                }

                return;
            }

            $notificationQuery()
                ->where('user_id', '!=', $fresh->assigned_user_id)
                ->delete();

            $dealTitle = $fresh->deal?->title ?? ('Сделка #'.$fresh->deal_id);

            UserNotification::query()->updateOrCreate(
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

    private function resolveAssigneeId(int $accountId, mixed $value, string $errorField = 'assigned_user_id'): ?int
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

        if (! $assigneeOk) {
            throw ValidationException::withMessages([
                $errorField => 'Нельзя назначить дело этому пользователю',
            ]);
        }

        return $assigneeId;
    }
}
