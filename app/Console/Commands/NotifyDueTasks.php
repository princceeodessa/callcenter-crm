<?php

namespace App\Console\Commands;

use App\Models\Task;
use App\Models\UserNotification;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class NotifyDueTasks extends Command
{
    protected $signature = 'tasks:notify-due';
    protected $description = 'Create notifications for due tasks (best with cron + schedule:run)';

    public function handle(TaskWorkflowService $taskWorkflow): int
    {
        $now = now();

        $tasks = Task::query()
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->whereNull('notified_at')
            ->with(['deal:id,title'])
            ->limit(500)
            ->get();

        $created = 0;

        foreach ($tasks as $task) {
            DB::transaction(function () use ($task, $now, &$created, $taskWorkflow) {
                $fresh = Task::query()
                    ->with('deal:id,title')
                    ->lockForUpdate()
                    ->find($task->id);

                if (! $fresh || $fresh->notified_at || $fresh->status !== 'open' || ! $fresh->due_at || $fresh->due_at->gt($now)) {
                    return;
                }

                $recipientIds = $taskWorkflow->resolveNotificationRecipientIds($fresh);
                if ($recipientIds === []) {
                    return;
                }

                $locationLabel = $fresh->deal
                    ? ($fresh->deal?->title ?? ('Сделка #'.$fresh->deal_id))
                    : ($fresh->context_label ?? 'задача без привязки');

                UserNotification::query()
                    ->where('account_id', $fresh->account_id)
                    ->where('type', 'task_due')
                    ->where('source_type', 'task')
                    ->where('source_id', $fresh->id)
                    ->whereNotIn('user_id', $recipientIds)
                    ->delete();

                foreach ($recipientIds as $recipientId) {
                    $notification = UserNotification::query()->updateOrCreate(
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

                    if ($notification->wasRecentlyCreated) {
                        $created++;
                    }
                }

                $fresh->notified_at = $now;
                $fresh->save();
            });
        }

        $this->info("Created notifications: {$created}");

        return 0;
    }
}
