<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\IntegrationConnection;
use App\Models\Message;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use Carbon\Carbon;

class ChatIngestService
{
    public function ingestFromTelegram(IntegrationConnection $connection, array $payload): ?Message
    {
        $msg = $payload['message'] ?? null;
        if (!is_array($msg)) {
            return null;
        }

        $chatId = $msg['chat']['id'] ?? null;
        if (!is_scalar($chatId)) {
            return null;
        }

        $messageId = $msg['message_id'] ?? ($payload['update_id'] ?? null);
        $from = is_array($msg['from'] ?? null) ? $msg['from'] : [];
        $author = trim((string)($from['first_name'] ?? '').' '.(string)($from['last_name'] ?? ''));
        $author = $author !== '' ? $author : (is_scalar($from['id'] ?? null) ? 'tg:'.$from['id'] : 'tg');
        $text = $msg['text'] ?? null;
        if (!is_string($text)) {
            $text = $msg['caption'] ?? null;
        }
        $text = is_string($text) ? $text : '';
        $sentAt = isset($msg['date']) ? Carbon::createFromTimestamp((int)$msg['date']) : now();

        return $this->ingestGeneric(
            accountId: $connection->account_id,
            provider: 'telegram',
            externalConversationId: (string)$chatId,
            externalMessageId: is_scalar($messageId) ? (string)$messageId : null,
            author: $author,
            body: $text,
            payload: $payload,
            sentAt: $sentAt,
        );
    }

    public function ingestFromVk(IntegrationConnection $connection, array $payload): ?Message
    {
        if (($payload['type'] ?? null) !== 'message_new') {
            return null;
        }
        $msg = $payload['object']['message'] ?? null;
        if (!is_array($msg)) {
            return null;
        }

        $peerId = $msg['peer_id'] ?? null;
        if (!is_scalar($peerId)) {
            return null;
        }
        $externalMessageId = $msg['conversation_message_id'] ?? ($msg['id'] ?? null);
        $author = is_scalar($msg['from_id'] ?? null) ? 'vk:'.$msg['from_id'] : 'vk';
        $text = is_string($msg['text'] ?? null) ? $msg['text'] : '';
        $sentAt = isset($msg['date']) ? Carbon::createFromTimestamp((int)$msg['date']) : now();

        return $this->ingestGeneric(
            accountId: $connection->account_id,
            provider: 'vk',
            externalConversationId: (string)$peerId,
            externalMessageId: is_scalar($externalMessageId) ? (string)$externalMessageId : null,
            author: $author,
            body: $text,
            payload: $payload,
            sentAt: $sentAt,
        );
    }

    public function ingestFromAvito(IntegrationConnection $connection, array $payload): ?Message
    {
        // Avito payload shape varies by product; best-effort.
        $chatId = $payload['chat_id'] ?? ($payload['chatId'] ?? data_get($payload, 'chat.id'));
        if (!is_scalar($chatId)) {
            return null;
        }

        $text = $payload['text'] ?? data_get($payload, 'message.text') ?? data_get($payload, 'content.text');
        $text = is_string($text) ? $text : '';
        $externalMessageId = $payload['id'] ?? ($payload['message_id'] ?? data_get($payload, 'message.id'));
        $authorId = $payload['author_id'] ?? ($payload['user_id'] ?? data_get($payload, 'author.id'));
        $author = is_scalar($authorId) ? 'avito:'.$authorId : 'avito';

        return $this->ingestGeneric(
            accountId: $connection->account_id,
            provider: 'avito',
            externalConversationId: (string)$chatId,
            externalMessageId: is_scalar($externalMessageId) ? (string)$externalMessageId : null,
            author: $author,
            body: $text,
            payload: $payload,
            sentAt: now(),
        );
    }

    private function ingestGeneric(
        int $accountId,
        string $provider,
        string $externalConversationId,
        ?string $externalMessageId,
        string $author,
        string $body,
        array $payload,
        Carbon $sentAt,
    ): ?Message {
        $conversation = Conversation::query()
            ->where('account_id', $accountId)
            ->where('channel', $provider)
            ->where('external_id', $externalConversationId)
            ->first();

        if (!$conversation) {
            [$pipeline, $stage] = $this->getDefaultPipelineAndStage($accountId);

            $title = $this->makeDealTitle($provider, $externalConversationId, $author, $body);
            $deal = Deal::create([
                'account_id' => $accountId,
                'pipeline_id' => $pipeline->id,
                'stage_id' => $stage->id,
                'title' => $title,
                'is_unread' => true,
            ]);

            $conversation = Conversation::create([
                'account_id' => $accountId,
                'deal_id' => $deal->id,
                'channel' => $provider,
                'external_id' => $externalConversationId,
                'status' => 'open',
                'unread_count' => 0,
                'last_message_at' => null,
                'meta' => [
                    'created_by' => 'webhook',
                ],
            ]);
        }

        // Idempotency: avoid duplicates if external message id repeats.
        if ($externalMessageId) {
            $exists = Message::query()
                ->where('conversation_id', $conversation->id)
                ->where('external_id', $externalMessageId)
                ->where('direction', 'in')
                ->exists();
            if ($exists) {
                return null;
            }
        }

        $message = Message::create([
            'account_id' => $accountId,
            'conversation_id' => $conversation->id,
            'direction' => 'in',
            'author' => $author,
            'body' => $body,
            'external_id' => $externalMessageId,
            'payload' => $payload,
            'status' => 'ok',
        ]);

        $conversation->update([
            'unread_count' => (int)$conversation->unread_count + 1,
            'last_message_at' => $sentAt,
        ]);

        $conversation->deal()->update(['is_unread' => true]);

        DealActivity::create([
            'account_id' => $accountId,
            'deal_id' => $conversation->deal_id,
            'author_user_id' => null,
            'type' => 'message_in',
            'body' => $body,
            'payload' => [
                'provider' => $provider,
                'author' => $author,
                'external_conversation_id' => $externalConversationId,
                'external_message_id' => $externalMessageId,
            ],
        ]);

        return $message;
    }

    private function getDefaultPipelineAndStage(int $accountId): array
    {
        $pipeline = Pipeline::query()
            ->where('account_id', $accountId)
            ->where('is_default', 1)
            ->first();

        if (!$pipeline) {
            $pipeline = Pipeline::query()
                ->where('account_id', $accountId)
                ->orderBy('id')
                ->firstOrFail();
        }

        $stage = PipelineStage::query()
            ->where('account_id', $accountId)
            ->where('pipeline_id', $pipeline->id)
            ->orderBy('sort')
            ->orderBy('id')
            ->firstOrFail();

        return [$pipeline, $stage];
    }

    private function makeDealTitle(string $provider, string $externalConversationId, string $author, string $body): string
    {
        $prov = match ($provider) {
            'vk' => 'VK',
            'telegram' => 'Telegram',
            'avito' => 'Avito',
            default => strtoupper($provider),
        };
        $preview = trim(preg_replace('/\s+/', ' ', (string)$body));
        if (mb_strlen($preview) > 40) {
            $preview = mb_substr($preview, 0, 40).'…';
        }
        return "Чат {$prov}: {$externalConversationId}".($preview !== '' ? " — {$preview}" : "");
    }
}
