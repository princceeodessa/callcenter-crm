<?php

namespace App\Services\Tasks;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\NonClosureWorkbookSheet;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Integrations\BitrixTaskSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TaskWorkflowService
{
    public function __construct(
        private readonly BitrixTaskSyncService $bitrixSync,
    ) {
    }

    public function createTask(Deal $deal, array $data, User $user): Task
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

        $task->load('assignedTo');
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

        $this->bitrixSync->syncCreatedTask($task, $deal, $user);

        return $task;
    }

    public function createDocumentTask(NonClosureWorkbookSheet $sheet, array $data, User $user, array $context = []): Task
    {
        $assigneeId = $this->resolveAssigneeId($user->account_id, $data['assigned_user_id'] ?? null);
        $payload = array_merge($context, [
            'context_type' => 'document_sheet_row',
            'sheet_id' => $sheet->id,
            'sheet_name' => $sheet->name,
            'workbook_id' => $sheet->workbook_id,
            'workbook_title' => $sheet->workbook?->title,
        ]);

        $task = Task::create([
            'account_id' => $user->account_id,
            'deal_id' => null,
            'assigned_user_id' => $assigneeId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'open',
            'due_at' => $data['due_at'],
            'external_payload' => $payload,
        ]);

        $task->load('assignedTo');
        $this->syncDueNotificationState($task);

        return $task;
    }

    public function syncDueNotificationState(Task $task): void
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

            $locationLabel = $fresh->deal
                ? ($fresh->deal?->title ?? ('Сделка #'.$fresh->deal_id))
                : ($fresh->context_label ?? 'задача без привязки');

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
                    'body' => "{$fresh->title} ({$locationLabel})",
                    'payload' => [
                        'task_id' => $fresh->id,
                        'deal_id' => $fresh->deal_id,
                        'context_label' => $fresh->context_label,
                        'context_url' => $fresh->context_url,
                        'due_at' => optional($fresh->due_at)->toISOString(),
                    ],
                    'is_read' => 0,
                ]
            );

            $fresh->notified_at = now();
            $fresh->save();
        });
    }

    public function resolveAssigneeId(int $accountId, mixed $value, string $errorField = 'assigned_user_id'): ?int
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
