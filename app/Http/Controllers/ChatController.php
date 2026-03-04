<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Models\Message;
use App\Services\Chat\ChatSendService;
use Illuminate\Http\Request;

class ChatController extends Controller
{
    public function index(Request $request)
    {
        $accountId = $request->user()->account_id;

        $conversations = Conversation::query()
            ->where('account_id', $accountId)
            ->with(['deal', 'lastMessage'])
            ->orderByDesc('last_message_at')
            ->orderByDesc('id')
            ->paginate(30);

        return view('chats.index', compact('conversations'));
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->authorizeConversation($request, $conversation);

        // Mark read
        $conversation->update(['unread_count' => 0]);
        $conversation->deal()->update(['is_unread' => false]);

        $messages = $conversation->messages()
            ->orderBy('id')
            ->take(200)
            ->get();

        $conversation->load(['deal']);

        return view('chats.show', compact('conversation', 'messages'));
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
            ->get(['id', 'direction', 'author', 'body', 'created_at', 'status']);

        return response()->json([
            'ok' => true,
            'messages' => $msgs,
        ]);
    }

    public function send(Request $request, Conversation $conversation, ChatSendService $sender)
    {
        $this->authorizeConversation($request, $conversation);

        $data = $request->validate([
            'text' => ['required', 'string', 'max:4000'],
        ]);

        try {
            $msg = $sender->sendText($conversation, $request->user()->id, $data['text']);

            if ($request->expectsJson()) {
                return response()->json(['ok' => true, 'message' => $msg]);
            }

            return redirect()->route('chats.show', $conversation)->with('status', 'Сообщение отправлено');
        } catch (\Throwable $e) {
            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
            }
            return redirect()->route('chats.show', $conversation)->withErrors([$e->getMessage()]);
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
}
