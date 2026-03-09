<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Chat\ChatSendService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $accountId = $request->user()->account_id;

        $activeId = (int)($request->query('c') ?? 0);

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
            // Mark read
            $activeConversation->update(['unread_count' => 0]);
            $activeConversation->deal()->update(['is_unread' => false]);

            $messages = $activeConversation->messages()
                ->orderBy('id')
                ->take(200)
                ->get();
        }

        return view('chats.messenger', [
            'conversations' => $conversations,
            'activeConversation' => $activeConversation,
            'messages' => $messages,
        ]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        // Keep backward-compatible URL, but render Telegram-like messenger.
        $this->authorizeConversation($request, $conversation);
        return redirect()->route('chats.index', ['c' => $conversation->id]);
    }

    public function poll(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        $afterId = (int)($request->query('after_id') ?? 0);

        $msgs = Message::query()
            ->where('conversation_id', $conversation->id)
            ->when($afterId > 0, fn($q) => $q->where('id', '>', $afterId))
            ->orderBy('id')
            ->take(100)
            ->get();

        $out = $msgs->map(fn($m) => $this->presentMessage($conversation, $m))->values();

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
            'media.*' => ['file', 'max:51200'], // 50MB per file
        ]);

        $text = trim((string)($data['text'] ?? ''));
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
                    $msg = $sender->sendMedia($conversation, $request->user()->id, $file, $index === 0 ? $caption : null);
                    $presentedMessages[] = $this->presentMessage($conversation, $msg);
                }
            } else {
                $msg = $sender->sendText($conversation, $request->user()->id, $text);
                $presentedMessages[] = $this->presentMessage($conversation, $msg);
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

    private function authorizeConversation(Request $request, Conversation $conversation): void
    {
        if ($conversation->account_id !== $request->user()->account_id) {
            abort(404);
        }
    }

    private function presentMessage(Conversation $conversation, Message $m): array
    {
        $payload = is_array($m->payload) ? $m->payload : [];
        $media = [];
        $rawMedia = Arr::wrap($payload['media'] ?? []);
        foreach ($rawMedia as $it) {
            if (!is_array($it)) continue;
            $type = (string)($it['type'] ?? 'file');
            $url = $it['url'] ?? null;
            $fileId = $it['file_id'] ?? null;

            if ($conversation->channel === 'telegram' && is_string($fileId) && $fileId !== '') {
                $url = route('media.telegram', ['conversation' => $conversation->id, 'fileId' => $fileId]);
            }

            if (is_string($url) && $url !== '') {
                $media[] = [
                    'type' => $type,
                    'url' => $url,
                    'file_name' => $it['file_name'] ?? null,
                ];
            }
        }

        $author = trim((string) $m->author);
        if ($m->direction !== 'out') {
            $lower = mb_strtolower($author);
            foreach (['vk:', 'tg:', 'avito:', 'telegram:', 'user ', 'id '] as $prefix) {
                if (str_starts_with($lower, $prefix)) {
                    $author = $conversation->lead_name ?? 'Клиент';
                    break;
                }
            }
        }

        return [
            'id' => $m->id,
            'direction' => $m->direction,
            'author' => $author,
            'body' => $m->body,
            'created_at' => optional($m->created_at)->toDateTimeString(),
            'status' => $m->status,
            'media' => $media,
        ];
    }
}
