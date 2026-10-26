<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\UserNotification;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(Request $request, TaskWorkflowService $taskWorkflow)
    {
        $user = Auth::user();
        $this->ensureDueTasksNotifiedForUser($user->account_id, $user->id, $taskWorkflow);

        if ($request->boolean('list')) {
            return $this->popoverList($user);
        }

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

    public function poll(Request $request, TaskWorkflowService $taskWorkflow)
    {
        $user = Auth::user();
        $this->ensureDueTasksNotifiedForUser($user->account_id, $user->id, $taskWorkflow);

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
                $url = $this->notificationUrl($notification) ?? route('notifications.index');

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

    public function markRead(Request $request, UserNotification $notification)
    {
        $user = Auth::user();
        abort_unless($notification->account_id === $user->account_id && $notification->user_id === $user->id, 403);

        if ($request->boolean('all')) {
            // окно уведомлений (Nordika): «Прочитать все»
            UserNotification::query()->where('account_id', $user->account_id)->where('user_id', $user->id)
                ->where('is_read', 0)->update(['is_read' => 1]);
        } else {
            $notification->is_read = 1;
            $notification->save();
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back();
    }

    /** Окно уведомлений у колокольчика (редизайн Nordika): последние 30 и число непрочитанных — JSON. */
    private function popoverList($user)
    {
        $items = UserNotification::query()
            ->where('account_id', $user->account_id)
            ->where('user_id', $user->id)
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json([
            'unread_count' => UserNotification::query()->where('account_id', $user->account_id)->where('user_id', $user->id)->where('is_read', 0)->count(),
            'items' => $items->map(fn (UserNotification $n) => [
                'id' => $n->id,
                'title' => $n->title,
                'body' => $n->body,
                'is_read' => (bool) $n->is_read,
                'url' => $this->notificationUrl($n),
                'time' => $n->created_at ? ($n->created_at->isToday() ? 'сегодня '.$n->created_at->format('H:i') : $n->created_at->format('d.m H:i')) : '',
                'read_url' => route('notifications.read', $n),
            ])->values(),
        ]);
    }

    /** Куда ведёт уведомление: сделка или страница из payload. */
    private function notificationUrl(UserNotification $notification): ?string
    {
        $payload = is_array($notification->payload ?? null) ? $notification->payload : [];
        $dealId = $payload['deal_id'] ?? null;
        $contextUrl = trim((string) ($payload['context_url'] ?? ''));

        return $dealId ? route('deals.show', ['deal' => $dealId]) : ($contextUrl !== '' ? $contextUrl : null);
    }

    private function ensureDueTasksNotifiedForUser(int $accountId, int $userId, TaskWorkflowService $taskWorkflow): void
    {
        $now = now();

        $tasks = Task::query()
            ->where('account_id', $accountId)
            ->where('status', 'open')
            ->whereNotNull('due_at')
            ->where('due_at', '<=', $now)
            ->where(function ($query) use ($userId) {
                $query
                    ->where('assigned_user_id', $userId)
                    ->orWhereNull('assigned_user_id');
            })
            ->where(function ($query) use ($userId) {
                $query
                    ->whereNull('notified_at')
                    ->orWhereDoesntHave('dueNotifications', function ($notificationQuery) use ($userId) {
                        $notificationQuery->where('user_id', $userId);
                    });
            })
            ->limit(100)
            ->get();

        foreach ($tasks as $task) {
            $recipientIds = $taskWorkflow->resolveNotificationRecipientIds($task);
            if (! in_array($userId, $recipientIds, true)) {
                continue;
            }

            $taskWorkflow->syncDueNotificationState($task);
        }
    }
}
