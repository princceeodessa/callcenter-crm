<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\DealActivity;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\Message;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\TelegramApiClient;
use App\Services\Integrations\VkApiClient;

class ChatSendService
{
    public function sendText(Conversation $conversation, int $userId, string $text): Message
    {
        $accountId = $conversation->account_id;
        $provider = $conversation->channel;

        $conn = IntegrationConnection::query()
            ->where('account_id', $accountId)
            ->where('provider', $provider)
            ->whereIn('status', ['active', 'error'])
            ->first();

        if (!$conn) {
            throw new \RuntimeException("Интеграция {$provider} не подключена");
        }

        $message = Message::create([
            'account_id' => $accountId,
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'author' => 'user:'.$userId,
            'body' => $text,
            'external_id' => null,
            'payload' => [
                'provider' => $provider,
                'request' => [
                    'text' => $text,
                    'external_conversation_id' => $conversation->external_id,
                ],
            ],
            'status' => 'pending',
            'error' => null,
        ]);

        try {
            $resp = $this->dispatchSend($conn, $conversation->external_id, $text);

            $externalId = $resp['external_message_id'] ?? null;
            $message->update([
                'status' => ($resp['ok'] ?? false) ? 'sent' : 'error',
                'external_id' => is_scalar($externalId) ? (string)$externalId : null,
                'payload' => array_merge($message->payload ?? [], ['response' => $resp]),
                'error' => ($resp['ok'] ?? false) ? null : (string)($resp['error'] ?? 'send_failed'),
            ]);

            $conversation->update(['last_message_at' => now()]);

            IntegrationEvent::create([
                'account_id' => $accountId,
                'provider' => $provider,
                'direction' => 'out',
                'event_type' => 'send_message',
                'external_id' => is_scalar($externalId) ? (string)$externalId : null,
                'payload' => [
                    'request' => [
                        'text' => $text,
                        'external_conversation_id' => $conversation->external_id,
                    ],
                    'response' => $resp,
                ],
                'received_at' => now(),
            ]);

            DealActivity::create([
                'account_id' => $accountId,
                'deal_id' => $conversation->deal_id,
                'author_user_id' => $userId,
                'type' => 'message_out',
                'body' => $text,
                'payload' => [
                    'provider' => $provider,
                    'external_conversation_id' => $conversation->external_id,
                    'external_message_id' => is_scalar($externalId) ? (string)$externalId : null,
                ],
            ]);

            return $message->fresh();
        } catch (\Throwable $e) {
            $message->update([
                'status' => 'error',
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    private function dispatchSend(IntegrationConnection $conn, string $externalConversationId, string $text): array
    {
        return match ($conn->provider) {
            'telegram' => $this->sendTelegram($conn, $externalConversationId, $text),
            'vk' => $this->sendVk($conn, $externalConversationId, $text),
            'avito' => $this->sendAvito($conn, $externalConversationId, $text),
            default => ['ok' => false, 'error' => 'unsupported_provider'],
        };
    }

    private function sendTelegram(IntegrationConnection $conn, string $chatId, string $text): array
    {
        $token = (string)($conn->settings['bot_token'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'bot_token_missing'];
        }

        $client = new TelegramApiClient($token);
        $r = $client->sendMessage($chatId, $text);

        $ok = (bool)($r['ok'] ?? false);
        $mid = data_get($r, 'result.message_id');
        return [
            'ok' => $ok,
            'external_message_id' => is_scalar($mid) ? (string)$mid : null,
            'raw' => $r,
            'error' => $ok ? null : (string)($r['description'] ?? 'telegram_send_failed'),
        ];
    }

    private function sendVk(IntegrationConnection $conn, string $peerId, string $text): array
    {
        $token = (string)($conn->settings['access_token'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'access_token_missing'];
        }
        $client = new VkApiClient($token);
        $r = $client->sendMessage($peerId, $text);

        $mid = $r['response'] ?? null;
        $ok = isset($r['response']) && !isset($r['error']);
        return [
            'ok' => $ok,
            'external_message_id' => is_scalar($mid) ? (string)$mid : null,
            'raw' => $r,
            'error' => $ok ? null : json_encode($r['error'] ?? $r, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function sendAvito(IntegrationConnection $conn, string $chatId, string $text): array
    {
        $token = (string)($conn->settings['access_token'] ?? '');
        $userId = (string)($conn->settings['user_id'] ?? '');
        if ($token === '') {
            return ['ok' => false, 'error' => 'access_token_missing'];
        }
        if ($userId === '') {
            return ['ok' => false, 'error' => 'user_id_missing'];
        }

        $client = new AvitoApiClient($token);
        $r = $client->sendText($userId, $chatId, $text);
        $ok = (bool)($r['ok'] ?? true);
        $mid = $r['id'] ?? ($r['message_id'] ?? null);
        return [
            'ok' => $ok,
            'external_message_id' => is_scalar($mid) ? (string)$mid : null,
            'raw' => $r,
            'error' => $ok ? null : json_encode($r, JSON_UNESCAPED_UNICODE),
        ];
    }
}
