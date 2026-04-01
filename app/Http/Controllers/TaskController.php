<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\NonClosureSheetRowActivity;
use App\Models\NonClosureWorkbookSheet;
use App\Models\Task;
use App\Models\User;
use App\Services\Integrations\BitrixTaskSyncService;
use App\Services\Tasks\TaskWorkflowService;
use App\Support\Deals\InteractsWithDealBroadcasts;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class TaskController extends Controller
{
    use InteractsWithDealBroadcasts;

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

        $productCategoryOptions = Deal::productCategoryOptions();
        $broadcastTemplates = [];
        $broadcastRecipients = array_fill_keys(array_keys($productCategoryOptions), []);
        $todayBroadcastCounts = array_fill_keys(array_keys($productCategoryOptions), 0);
        $broadcastTargetModeOptions = [
            'primary' => 'Один чат на сделку',
            'all' => 'Все чаты сделки',
        ];
        $broadcastPreviewError = null;

        try {
            $broadcastTemplates = $this->broadcastTemplates();
            $broadcastRecipients = $this->broadcastRecipientsByCategory($user->account_id);
            foreach ($productCategoryOptions as $categoryKey => $categoryLabel) {
                $todayBroadcastCounts[$categoryKey] = count($broadcastRecipients[$categoryKey] ?? []);
            }
            $broadcastTargetModeOptions = $this->broadcastTargetModeOptions();
        } catch (\Throwable $e) {
            report($e);
            $broadcastPreviewError = 'Не удалось построить список адресатов для рассылки. Проверьте лог Laravel.';
        }

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
            'productCategoryOptions' => $productCategoryOptions,
            'broadcastTemplates' => $broadcastTemplates,
            'broadcastRecipients' => $broadcastRecipients,
            'todayBroadcastCounts' => $todayBroadcastCounts,
            'broadcastTargetModeOptions' => $broadcastTargetModeOptions,
            'broadcastPreviewError' => $broadcastPreviewError,
        ]);
    }

    public function store(Request $request, Deal $deal, TaskWorkflowService $taskWorkflow)
    {
        $data = $this->validateTaskData($request);

        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $taskWorkflow->createTask($deal, $data, $user);

        return back();
    }

    public function storeFromPage(Request $request, TaskWorkflowService $taskWorkflow)
    {
        $data = $this->validateTaskData($request, true);

        $user = Auth::user();
        $deal = Deal::query()
            ->where('account_id', $user->account_id)
            ->findOrFail((int) $data['deal_id']);

        $taskWorkflow->createTask($deal, $data, $user);

        return redirect()->route('tasks.index')->with('status', 'Дело добавлено');
    }

    public function storeDocumentTask(Request $request, NonClosureWorkbookSheet $sheet, TaskWorkflowService $taskWorkflow)
    {
        $user = Auth::user();
        $sheet = $this->accessibleWorkbookSheet($sheet, $user);

        $data = $request->validate([
            'row_index' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $rows = collect($sheet->rows ?? [])->values();
        $rowIndex = (int) $data['row_index'];
        $row = $rows->get($rowIndex - 1);

        abort_if(!is_array($row), 422, 'Строка таблицы не найдена.');

        $rowPreview = collect($row)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->take(5)
            ->implode(' | ');

        $subject = trim((string) ($row[1] ?? $row[0] ?? ''));
        if ($subject === '') {
            $subject = 'строка '.$rowIndex;
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'Связаться: '.Str::limit($subject, 120, '');
        }

        $description = trim((string) ($data['description'] ?? ''));
        $contextLabel = implode(' -> ', array_filter([
            $sheet->workbook?->title,
            $sheet->name,
            'строка '.$rowIndex,
        ]));
        $contextUrl = route('nonclosures.sheets.show', [
            'sheet' => $sheet->id,
            'workbook' => $sheet->workbook_id,
        ]).'#row-'.$rowIndex;

        if ($description === '') {
            $description = implode("\n", array_filter([
                $contextLabel,
                $rowPreview !== '' ? 'Контекст: '.$rowPreview : null,
            ]));
        }

        $task = $taskWorkflow->createDocumentTask($sheet, [
            'title' => $title,
            'description' => $description,
            'due_at' => $data['due_at'],
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
        ], $user, [
            'context_label' => $contextLabel,
            'context_url' => $contextUrl,
            'row_index' => $rowIndex,
            'row_preview' => $rowPreview,
            'sheet_slug' => $sheet->slug,
        ]);

        NonClosureSheetRowActivity::create([
            'account_id' => $user->account_id,
            'workbook_sheet_id' => $sheet->id,
            'row_index' => $rowIndex,
            'actor_user_id' => $user->id,
            'type' => NonClosureSheetRowActivity::TYPE_TASK_CREATED,
            'body' => 'Создана задача: '.$task->title,
            'payload' => [
                'task_id' => $task->id,
                'task_title' => $task->title,
                'due_at' => optional($task->due_at)->toISOString(),
                'assigned_user_id' => $task->assigned_user_id,
            ],
        ]);

        return redirect()->to(route('nonclosures.sheets.show', array_filter([
            'sheet' => $sheet->id,
            'scope' => $data['scope'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'workbook' => $data['workbook'] ?? $sheet->workbook_id,
        ])).'#row-'.$rowIndex)->with('status', 'Напоминание по строке создано.');
    }

    private function storeDocumentTaskLegacy(Request $request, NonClosureWorkbookSheet $sheet, TaskWorkflowService $taskWorkflow)
    {
        $user = Auth::user();
        $sheet = $this->accessibleWorkbookSheet($sheet, $user);

        $data = $request->validate([
            'row_index' => ['required', 'integer', 'min:1'],
            'title' => ['nullable', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'due_at' => ['required', 'date'],
            'assigned_user_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $rows = collect($sheet->rows ?? [])->values();
        $rowIndex = (int) $data['row_index'];
        $row = $rows->get($rowIndex - 1);

        abort_if(!is_array($row), 422, 'Строка таблицы не найдена.');

        $rowPreview = collect($row)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->take(5)
            ->implode(' | ');

        $subject = trim((string) ($row[1] ?? $row[0] ?? ''));
        if ($subject === '') {
            $subject = 'строка '.$rowIndex;
        }

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'Связаться: '.Str::limit($subject, 120, '');
        }

        $description = trim((string) ($data['description'] ?? ''));
        $contextLabel = implode(' -> ', array_filter([
            $sheet->workbook?->title,
            $sheet->name,
            'строка '.$rowIndex,
        ]));
        $contextUrl = route('nonclosures.sheets.show', [
            'sheet' => $sheet->id,
            'workbook' => $sheet->workbook_id,
        ]).'#row-'.$rowIndex;

        if ($description === '') {
            $description = implode("\n", array_filter([
                $contextLabel,
                $rowPreview !== '' ? 'Контекст: '.$rowPreview : null,
            ]));
        }

        $taskWorkflow->createDocumentTask($sheet, [
            'title' => $title,
            'description' => $description,
            'due_at' => $data['due_at'],
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
        ], $user, [
            'context_label' => $contextLabel,
            'context_url' => $contextUrl,
            'row_index' => $rowIndex,
            'row_preview' => $rowPreview,
            'sheet_slug' => $sheet->slug,
        ]);

        return redirect()->route('nonclosures.sheets.show', array_filter([
            'sheet' => $sheet->id,
            'scope' => $data['scope'] ?? null,
            'owner_id' => $data['owner_id'] ?? null,
            'workbook' => $data['workbook'] ?? $sheet->workbook_id,
        ]))->with('status', 'Напоминание по строке создано.');
    }

    public function update(Request $request, Task $task, BitrixTaskSyncService $bitrixSync, TaskWorkflowService $taskWorkflow)
    {
        $user = Auth::user();
        abort_unless($task->account_id === $user->account_id, 403);

        $data = $this->validateTaskUpdateData($request);

        $task->assigned_user_id = $taskWorkflow->resolveAssigneeId(
            $user->account_id,
            $data['assigned_user_id'] ?? null,
            'edit_assigned_user_id'
        );
        $task->title = $data['title'];
        $task->description = $data['description'] ?? null;
        $task->due_at = $data['due_at'];
        $task->save();

        $task->load(['deal', 'assignedTo']);
        $taskWorkflow->syncDueNotificationState($task);

        if ($task->deal_id) {
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
        }

        return back()->with('status', 'Дело обновлено');
    }

    public function complete(Request $request, Task $task, BitrixTaskSyncService $bitrixSync, TaskWorkflowService $taskWorkflow)
    {
        $user = Auth::user();
        abort_unless($task->account_id === $user->account_id, 403);

        $task->status = 'done';
        $task->completed_at = now();
        $task->save();

        $taskWorkflow->syncDueNotificationState($task);

        if ($task->deal_id) {
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
        }

        return back();
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

    private function accessibleWorkbookSheet(NonClosureWorkbookSheet $sheet, User $user): NonClosureWorkbookSheet
    {
        $query = NonClosureWorkbookSheet::query()
            ->where('account_id', $user->account_id)
            ->whereKey($sheet->id)
            ->with('workbook:id,title');

        if (!in_array($user->role, ['admin', 'main_operator'], true)) {
            $query->accessibleFor($user);
        }

        return $query->firstOrFail();
    }
}
