<?php

namespace App\Services\Tasks;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\NonClosureWorkbookSheet;
use App\Models\Task;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Integrations\BitrixTaskSyncService;
use App\Support\Users\AssignmentScope;
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
        $assignment = $this->resolveAssignment($user, $data['assigned_user_id'] ?? null);

        $task = Task::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'assigned_user_id' => $assignment['assignee_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'open',
            'due_at' => $data['due_at'],
            'external_payload' => $this->applyAssignmentScopeToPayload(null, $assignment['scope']),
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
        $assignment = $this->resolveAssignment($user, $data['assigned_user_id'] ?? null);
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
            'assigned_user_id' => $assignment['assignee_id'],
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'open',
            'due_at' => $data['due_at'],
            'external_payload' => $this->applyAssignmentScopeToPayload($payload, $assignment['scope']),
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

            $recipientIds = $this->resolveNotificationRecipientIds($fresh);
            if ($recipientIds === []) {
                $notificationQuery()->delete();

                if ($fresh->notified_at !== null) {
                    $fresh->notified_at = null;
                    $fresh->save();
                }

                return;
            }

            $notificationQuery()
                ->whereNotIn('user_id', $recipientIds)
                ->delete();

            $locationLabel = $fresh->deal
                ? ($fresh->deal?->title ?? ('Сделка #'.$fresh->deal_id))
                : ($fresh->context_label ?? 'задача без привязки');

            foreach ($recipientIds as $recipientId) {
                UserNotification::query()->updateOrCreate(
                    [
                        'user_id' => $recipientId,
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
            }

            $fresh->notified_at = now();
            $fresh->save();
        });
    }

    public function resolveNotificationRecipientIds(Task $task): array
    {
        if ((int) ($task->assigned_user_id ?? 0) > 0) {
            return [(int) $task->assigned_user_id];
        }

        $group = $this->assignmentScopeFromTask($task) ?: AssignmentScope::GROUP_CALL_CENTER;

        static $groupUsersCache = [];
        $cacheKey = (int) $task->account_id.':'.$group;
        if (! array_key_exists($cacheKey, $groupUsersCache)) {
            $groupUsersCache[$cacheKey] = AssignmentScope::groupUserIds((int) $task->account_id, $group);
        }

        return $groupUsersCache[$cacheKey];
    }

    public function resolveAssigneeId(User $actor, mixed $value, string $errorField = 'assigned_user_id'): ?int
    {
        return $this->resolveAssignment($actor, $value, $errorField)['assignee_id'];
    }

    /**
     * @return array{assignee_id: ?int, scope: ?string}
     */
    public function resolveAssignment(User $actor, mixed $value, string $errorField = 'assigned_user_id'): array
    {
        if ($value === null || $value === '' || (string) $value === '0') {
            if (! AssignmentScope::canAssignToAll($actor)) {
                throw ValidationException::withMessages([
                    $errorField => 'Назначение "Всем" доступно только сотрудникам колл-центра и администратору.',
                ]);
            }

            return [
                'assignee_id' => null,
                'scope' => AssignmentScope::groupForAll($actor),
            ];
        }

        $assigneeId = (int) $value;
        $assigneeOk = AssignmentScope::query($actor)
            ->where('id', $assigneeId)
            ->exists();

        if (! $assigneeOk) {
            throw ValidationException::withMessages([
                $errorField => 'Нельзя назначить дело этому пользователю.',
            ]);
        }

        return [
            'assignee_id' => $assigneeId,
            'scope' => null,
        ];
    }

    public function applyAssignmentScopeToPayload(?array $payload, ?string $scope): ?array
    {
        $payload = is_array($payload) ? $payload : [];

        if ($scope === null || trim((string) $scope) === '') {
            unset($payload['assignment_scope']);
        } else {
            $payload['assignment_scope'] = trim((string) $scope);
        }

        return $payload === [] ? null : $payload;
    }

    private function assignmentScopeFromTask(Task $task): ?string
    {
        $payload = is_array($task->external_payload ?? null) ? $task->external_payload : [];
        $scope = trim((string) ($payload['assignment_scope'] ?? ''));

        return $scope !== '' ? $scope : null;
    }
}
