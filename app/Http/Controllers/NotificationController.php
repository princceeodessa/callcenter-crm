<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\UserNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        $notifications = UserNotification::query()
            ->where('account_id', $user->account_id)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->paginate(50);

        $unreadCount = UserNotification::query()
            ->where('account_id', $user->account_id)
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->count();

        return view('notifications.index', compact('notifications', 'unreadCount'));
    }

    public function poll(Request $request)
    {
        $user = Auth::user();
        $this->ensureDueTasksNotifiedForUser($user->account_id, $user->id);

        $afterId = (int) $request->query('after_id', 0);

        $maxId = (int) UserNotification::query()
            ->where('account_id', $user->account_id)
            ->where('user_id', $user->id)
            ->max('id');

        if ($afterId > $maxId) {
            $afterId = 0;
        }

        $new = UserNotification::query()
            ->where('account_id', $user->account_id)
            ->where('user_id', $user->id)
            ->where('id', '>', $afterId)
            ->orderBy('id')
            ->limit(50)
            ->get();

        $unreadCount = UserNotification::query()
            ->where('account_id', $user->account_id)
            ->where('user_id', $user->id)
            ->where('is_read', 0)
            ->count();

        return response()->json([
            'notifications' => $new->map(function (UserNotification $notification) {
                $payload = is_array($notification->payload ?? null) ? $notification->payload : [];
                $dealId = $payload['deal_id'] ?? null;
                $contextUrl = trim((string) ($payload['context_url'] ?? ''));
                $url = $dealId
                    ? route('deals.show', ['deal' => $dealId])
                    : ($contextUrl !== '' ? $contextUrl : route('notifications.index'));

                return [
                    'id' => $notification->id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'body' => $notification->body,
                    'url' => $url,
                    'created_at' => optional($notification->created_at)->toISOString(),
                ];
            }),
            'unread_count' => $unreadCount,
            'max_id' => $maxId,
        ]);
    }

    public function markRead(UserNotification $notification)
    {
        $user = Auth::user();
        abort_unless($notification->account_id === $user->account_id && $notification->user_id === $user->id, 403);

        $notification->is_read = 1;
        $notification->save();

        return back();
    }

    private function ensureDueTasksNotifiedForUser(int $accountId, int $userId): void
    {
        $now = now();

        $tasks = Task::query()
            ->where('account_id', $accountId)
            ->where('assigned_user_id', $userId)
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->whereNull('notified_at')
            ->with(['deal'])
            ->limit(50)
            ->get();

        foreach ($tasks as $task) {
            DB::transaction(function () use ($task, $now, $accountId, $userId) {
                $fresh = Task::query()->where('account_id', $accountId)->lockForUpdate()->find($task->id);
                if (!$fresh || $fresh->notified_at || $fresh->status !== 'open' || !$fresh->due_at || $fresh->due_at->gt($now)) {
                    return;
                }

                $locationLabel = $fresh->deal
                    ? ($task->deal?->title ?? ('Сделка #'.$fresh->deal_id))
                    : ($fresh->context_label ?? 'задача без привязки');

                UserNotification::query()->firstOrCreate(
                    [
                        'user_id' => $userId,
                        'type' => 'task_due',
                        'source_type' => 'task',
                        'source_id' => $fresh->id,
                    ],
                    [
                        'account_id' => $accountId,
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

                $fresh->notified_at = $now;
                $fresh->save();
            });
        }
    }
}
