<?php

namespace App\Http\Controllers;

use App\Models\Task;
use App\Models\UserNotification;
use App\Services\Tasks\TaskWorkflowService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class NotificationController extends Controller
{
    public function index(TaskWorkflowService $taskWorkflow)
    {
        $user = Auth::user();
        $this->ensureDueTasksNotifiedForUser($user->account_id, $user->id, $taskWorkflow);

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
