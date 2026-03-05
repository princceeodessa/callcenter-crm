<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Task;
use App\Models\DealActivity;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TaskController extends Controller
{
    public function store(Request $request, Deal $deal)
    {
        $data = $request->validate([
            'title' => ['required','string','max:255'],
            'description' => ['nullable','string'],
            'due_at' => ['required','date'],
            'assigned_user_id' => ['required','integer','exists:users,id'],
        ]);

        $user = Auth::user();
        abort_unless($deal->account_id === $user->account_id, 403);

        $assigneeId = (int)$data['assigned_user_id'];
        $assigneeOk = \App\Models\User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', 1)
            ->where('id', $assigneeId)
            ->exists();

        if (!$assigneeOk) {
            return back()->withErrors([
                'assigned_user_id' => 'Нельзя назначить дело этому пользователю',
            ]);
        }

        $task = Task::create([
            'account_id' => $user->account_id,
            'deal_id' => $deal->id,
            'assigned_user_id' => $assigneeId,
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'status' => 'open',
            'due_at' => $data['due_at'],
        ]);

        // If the task is already due, create notification immediately.
        if ($task->due_at && $task->due_at->lte(now())) {
            DB::transaction(function () use ($task) {
                $fresh = Task::query()->lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->notified_at) return;

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
            'body' => 'Создано дело: '.$task->title,
            'payload' => ['task_id' => $task->id],
        ]);

        return back();
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
}
