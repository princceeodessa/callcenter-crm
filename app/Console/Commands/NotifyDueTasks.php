<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use App\Models\Task;
use App\Models\UserNotification;

class NotifyDueTasks extends Command
{
    protected $signature = 'tasks:notify-due';
    protected $description = 'Create notifications for due tasks (best with cron + schedule:run)';

    public function handle(): int
    {
        $now = now();

        // Select tasks that are due and haven't been notified yet.
        $tasks = Task::query()
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->whereNull('notified_at')
            ->whereNotNull('assigned_user_id')
            ->with(['deal'])
            ->limit(500)
            ->get();

        $created = 0;

        foreach ($tasks as $task) {
            DB::transaction(function () use ($task, $now, &$created) {
                // Double-check inside the transaction.
                $fresh = Task::query()->lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->notified_at || $fresh->status !== 'open' || !$fresh->due_at || $fresh->due_at->gt($now)) {
                    return;
                }

                $dealTitle = $task->deal?->title ?? ('Сделка #'.$task->deal_id);

                // Unique key: (user_id, type, source_type, source_id)
                $notif = UserNotification::query()->firstOrCreate(
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

                if ($notif->wasRecentlyCreated) {
                    $created++;
                }

                $fresh->notified_at = $now;
                $fresh->save();
            });
        }

        $this->info("Created notifications: {$created}");
        return 0;
    }
}
