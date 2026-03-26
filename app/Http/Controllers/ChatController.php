<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Deal;
use App\Models\Message;
use App\Services\Chat\ChatHistorySyncService;
use App\Services\Chat\ChatSendService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $accountId = $request->user()->account_id;
        $activeId = (int) ($request->query('c') ?? 0);

        $conversations = Conversation::query()
            ->where('account_id', $accountId)
            ->with(['deal', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30);

        $activeConversation = null;
        $messages = collect();

        if ($activeId > 0) {
            $activeConversation = Conversation::query()
                ->where('account_id', $accountId)
                ->with(['deal'])
                ->find($activeId);
        }

        if (!$activeConversation && $conversations->count() > 0) {
            $activeConversation = $conversations->getCollection()->first();
            if ($activeConversation) {
                $activeConversation->load(['deal']);
            }
        }

        if ($activeConversation) {
            app(ChatHistorySyncService::class)->syncFull($activeConversation, $accountId);

            $activeConversation->update(['unread_count' => 0]);
            $activeConversation->deal()->update(['is_unread' => false]);
            $conversations->getCollection()->transform(function (Conversation $conversation) use ($activeConversation) {
                if ((int) $conversation->id === (int) $activeConversation->id) {
                    $conversation->forceFill(['unread_count' => 0]);
                }

                return $conversation;
            });

            $messages = $activeConversation->messages()
                ->orderBy('id')
                ->get();
        }

        $unreadConversationCount = Conversation::query()
            ->where('account_id', $accountId)
            ->where('unread_count', '>', 0)
            ->count();

        $unreadMessageCount = (int) Conversation::query()
            ->where('account_id', $accountId)
            ->sum('unread_count');

        return view('chats.messenger', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $messages,
            'unreadConversationCount' => $unreadConversationCount,
            'unreadMessageCount' => $unreadMessageCount,
        ]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        return redirect()->route('chats.index', ['c' => $conversation->id]);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $afterId = (int) ($request->query('after_id') ?? 0);
        app(ChatHistorySyncService::class)->syncRecent($conversation, $request->user()->account_id);

        $messages = Message::query()
            ->where('conversation_id', $conversation->id)
            ->when($afterId > 0, fn ($query) => $query->where('id', '>', $afterId))
            ->orderBy('id')
            ->take(100)
            ->get();

        $out = $messages->map(fn ($message) => $this->presentMessage($conversation, $message))->values();

        return response()->json([
            'ok' => true,
            'messages' => $out,
        ]);
    }

    public function send(Request $request, Conversation $conversation, ChatSendService $sender)
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'text' => ['nullable', 'string', 'max:4000'],
            'media.*' => ['file', 'max:51200'],
        ]);

        $text = trim((string) ($data['text'] ?? ''));
        $rawFiles = $request->file('media');
        $files = [];

        if ($rawFiles instanceof \Illuminate\Http\UploadedFile) {
            $files = [$rawFiles];
        } elseif (is_array($rawFiles)) {
            foreach ($rawFiles as $item) {
                if ($item instanceof \Illuminate\Http\UploadedFile) {
                    $files[] = $item;
                }
            }
        }

        if ($text === '' && count($files) === 0) {
            return $request->expectsJson()
                ? response()->json(['ok' => false, 'error' => 'empty_message'], 422)
                : back()->withErrors(['text' => 'Пустое сообщение']);
        }

        try {
            $presentedMessages = [];

            if (count($files) > 0) {
                $caption = $text !== '' ? $text : null;
                foreach ($files as $index => $file) {
                    $message = $sender->sendMedia($conversation, $request->user()->id, $file, $index === 0 ? $caption : null);
                    $presentedMessages[] = $this->presentMessage($conversation, $message);
                }
            } else {
                $message = $sender->sendText($conversation, $request->user()->id, $text);
                $presentedMessages[] = $this->presentMessage($conversation, $message);
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'message' => end($presentedMessages) ?: null,
                    'messages' => $presentedMessages,
                ]);
            }

            $statusText = count($presentedMessages) > 1
                ? 'Сообщения отправлены: '.count($presentedMessages)
                : 'Сообщение отправлено';

            return redirect()->route('chats.index', ['c' => $conversation->id])->with('status', $statusText);
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }

            return redirect()->route('chats.index', ['c' => $conversation->id])->withErrors([$e->getMessage()]);
        }
    }

    public function markRead(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);
        $conversation->update(['unread_count' => 0]);
        $conversation->deal()->update(['is_unread' => false]);

        return response()->json(['ok' => true]);
    }

    public function markAllRead(Request $request)
    {
        $accountId = $request->user()->account_id;
        $activeId = (int) ($request->input('c') ?? 0);

        $unreadQuery = Conversation::query()
            ->where('account_id', $accountId)
            ->where('unread_count', '>', 0);

        $dealIds = (clone $unreadQuery)
            ->whereNotNull('deal_id')
            ->distinct()
            ->pluck('deal_id')
            ->map(fn ($dealId) => (int) $dealId)
            ->filter(fn (int $dealId) => $dealId > 0)
            ->values();

        $updatedCount = $unreadQuery->update(['unread_count' => 0]);

        if ($dealIds->isNotEmpty()) {
            Deal::query()
                ->where('account_id', $accountId)
                ->whereIn('id', $dealIds->all())
                ->update(['is_unread' => false]);
        }

        $routeParams = [];
        if ($activeId > 0) {
            $routeParams['c'] = $activeId;
        }

        $statusMessage = $updatedCount > 0
            ? "\u{0412}\u{0441}\u{0435} \u{0447}\u{0430}\u{0442}\u{044B} \u{043E}\u{0442}\u{043C}\u{0435}\u{0447}\u{0435}\u{043D}\u{044B} \u{043F}\u{0440}\u{043E}\u{0447}\u{0438}\u{0442}\u{0430}\u{043D}\u{043D}\u{044B}\u{043C}\u{0438}."
            : "\u{041D}\u{0435}\u{043F}\u{0440}\u{043E}\u{0447}\u{0438}\u{0442}\u{0430}\u{043D}\u{043D}\u{044B}\u{0445} \u{0447}\u{0430}\u{0442}\u{043E}\u{0432} \u{043D}\u{0435} \u{0431}\u{044B}\u{043B}\u{043E}.";

        return redirect()
            ->route('chats.index', $routeParams)
            ->with('status', $statusMessage);
    }

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        if ($conversation->account_id !== $request->user()->account_id) {
            abort(404);
        }
    }

    private function presentMessage(Conversation $conversation, Message $message): array
    {
        $payload = is_array($message->payload) ? $message->payload : [];
        $media = [];
        $rawMedia = Arr::wrap($payload['media'] ?? []);

        foreach ($rawMedia as $item) {
            if (!is_array($item)) {
                continue;
            }

            $type = (string) ($item['type'] ?? 'file');
            $url = $item['url'] ?? null;
            $fileId = $item['file_id'] ?? null;

            if ($conversation->channel === 'telegram' && is_string($fileId) && $fileId !== '') {
                $url = route('media.telegram', ['conversation' => $conversation->id, 'fileId' => $fileId]);
            }

            if (is_string($url) && $url !== '') {
                $media[] = [
                    'type' => $type,
                    'url' => $url,
                    'file_name' => $item['file_name'] ?? null,
                ];
            }
        }

        $author = trim((string) $message->author);
        if ($message->direction !== 'out') {
            $lower = mb_strtolower($author);
            foreach (['vk:', 'tg:', 'avito:', 'telegram:', 'user ', 'id '] as $prefix) {
                if (str_starts_with($lower, $prefix)) {
                    $author = $conversation->lead_name ?? 'Клиент';
                    break;
                }
            }
        }

        return [
            'id' => $message->id,
            'direction' => $message->direction,
            'author' => $author,
            'body' => $message->body,
            'created_at' => optional($message->created_at)->toDateTimeString(),
            'status' => $message->status,
            'media' => $media,
        ];
    }
}
