<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Models\DealActivity;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\Message;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\AvitoOAuthService;
use App\Services\Integrations\TelegramApiClient;
use App\Services\Integrations\VkApiClient;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    public function sendMedia(Conversation $conversation, int $userId, UploadedFile $file, ?string $caption = null): Message
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

        // Store uploaded file locally (so we can render it back in CRM)
        $path = $file->store('chat_uploads/'.$accountId, 'public');
        $publicUrl = Storage::disk('public')->url($path);

        $body = trim((string)$caption);
        if ($body === '') {
            $body = $file->getClientOriginalName() ?: 'Вложение';
        }

        $message = Message::create([
            'account_id' => $accountId,
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'author' => 'user:'.$userId,
            'body' => $body,
            'external_id' => null,
            'payload' => [
                'provider' => $provider,
                'request' => [
                    'caption' => $caption,
                    'external_conversation_id' => $conversation->external_id,
                    'file' => [
                        'name' => $file->getClientOriginalName(),
                        'mime' => $file->getClientMimeType(),
                        'size' => $file->getSize(),
                        'storage_path' => $path,
                        'public_url' => $publicUrl,
                    ],
                ],
                'media' => [[
                    'type' => str_starts_with((string)$file->getClientMimeType(), 'image/') ? 'photo'
                        : (str_starts_with((string)$file->getClientMimeType(), 'video/') ? 'video' : 'document'),
                    'url' => $publicUrl,
                    'file_name' => $file->getClientOriginalName(),
                    'mime' => $file->getClientMimeType(),
                    'size' => $file->getSize(),
                ]],
            ],
            'status' => 'pending',
            'error' => null,
        ]);

        try {
            $resp = $this->dispatchSendMedia($conn, $conversation->external_id, $file, $caption, $publicUrl);

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
                'event_type' => 'send_media',
                'external_id' => is_scalar($externalId) ? (string)$externalId : null,
                'payload' => [
                    'request' => [
                        'caption' => $caption,
                        'external_conversation_id' => $conversation->external_id,
                        'public_url' => $publicUrl,
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
                'body' => $body,
                'payload' => [
                    'provider' => $provider,
                    'external_conversation_id' => $conversation->external_id,
                    'external_message_id' => is_scalar($externalId) ? (string)$externalId : null,
                    'media_url' => $publicUrl,
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

    private function dispatchSendMedia(IntegrationConnection $conn, string $externalConversationId, UploadedFile $file, ?string $caption, string $publicUrl): array
    {
        return match ($conn->provider) {
            'telegram' => $this->sendTelegramMedia($conn, $externalConversationId, $file, $caption),
            'vk' => $this->sendVkMedia($conn, $externalConversationId, $file, $caption),
            // Avito Messenger attachments are product-specific; fallback to sending a link.
            'avito' => $this->sendAvito($conn, $externalConversationId, trim((string)$caption).'\n'.$publicUrl),
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

    private function sendTelegramMedia(IntegrationConnection $conn, string $chatId, UploadedFile $file, ?string $caption): array
    {
        $token = (string)($conn->settings['bot_token'] ?? '');
        if (trim($token) === '') {
            return ['ok' => false, 'error' => 'bot_token_missing'];
        }

        $client = new TelegramApiClient($token);
        $mime = (string)($file->getClientMimeType() ?? '');
        $resp = null;

        if (str_starts_with($mime, 'image/')) {
            $resp = $client->sendPhoto($chatId, $file, $caption);
        } elseif (str_starts_with($mime, 'video/')) {
            $resp = $client->sendVideo($chatId, $file, $caption);
        } else {
            $resp = $client->sendDocument($chatId, $file, $caption);
        }

        $ok = (bool)($resp['ok'] ?? false);
        $mid = data_get($resp, 'result.message_id');
        return [
            'ok' => $ok,
            'external_message_id' => is_scalar($mid) ? (string)$mid : null,
            'raw' => $resp,
            'error' => $ok ? null : (string)($resp['description'] ?? 'telegram_send_failed'),
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

    private function sendVkMedia(IntegrationConnection $conn, string $peerId, UploadedFile $file, ?string $caption): array
    {
        $token = (string)($conn->settings['access_token'] ?? '');
        if (trim($token) === '') {
            return ['ok' => false, 'error' => 'access_token_missing'];
        }

        $client = new VkApiClient($token);
        $mime = (string)($file->getClientMimeType() ?? '');

        $upload = null;
        if (str_starts_with($mime, 'image/')) {
            $upload = $client->uploadMessagePhoto($peerId, $file);
        } else {
            $upload = $client->uploadMessageDoc($peerId, $file);
        }

        if (!($upload['ok'] ?? false) || empty($upload['attachment'])) {
            return ['ok' => false, 'error' => $upload['error'] ?? 'vk_upload_failed', 'raw' => $upload];
        }

        $text = trim((string)$caption);
        $r = $client->sendMessageWithAttachment($peerId, $text, (string)$upload['attachment']);
        $mid = $r['response'] ?? null;
        $ok = isset($r['response']) && !isset($r['error']);
        return [
            'ok' => $ok,
            'external_message_id' => is_scalar($mid) ? (string)$mid : null,
            'raw' => ['upload' => $upload, 'send' => $r],
            'error' => $ok ? null : json_encode($r['error'] ?? $r, JSON_UNESCAPED_UNICODE),
        ];
    }

    private function sendAvito(IntegrationConnection $conn, string $chatId, string $text): array
    {
        $settings = $conn->settings ?? [];

        // Refresh Avito token if it is close to expiration (best-effort)
        try {
            $exp = $settings['token_expires_at'] ?? null;
            $refreshToken = $settings['refresh_token'] ?? null;
            $clientId = $settings['client_id'] ?? null;
            $clientSecret = $settings['client_secret'] ?? null;
            if ($exp && $refreshToken && $clientId && $clientSecret) {
                $expAt = \Carbon\Carbon::parse($exp);
                if ($expAt->lessThanOrEqualTo(now()->addSeconds(60))) {
                    $oauth = app(AvitoOAuthService::class);
                    $resp = $oauth->refreshToken((string)$clientId, (string)$clientSecret, (string)$refreshToken);
                    if (!empty($resp['access_token'])) {
                        $settings['access_token'] = (string)$resp['access_token'];
                        if (!empty($resp['refresh_token'])) {
                            $settings['refresh_token'] = (string)$resp['refresh_token'];
                        }
                        if (!empty($resp['expires_in'])) {
                            $settings['token_expires_at'] = now()->addSeconds((int)$resp['expires_in'])->toDateTimeString();
                        }
                        $conn->update(['settings' => $settings]);
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $token = (string)($settings['access_token'] ?? '');
        $userId = (string)($settings['user_id'] ?? '');
        if ($token === '' && !empty($settings['client_id']) && !empty($settings['client_secret'])) {
            try {
                $oauth = app(AvitoOAuthService::class);
                $resp = $oauth->clientCredentials((string) $settings['client_id'], (string) $settings['client_secret']);
                $token = trim((string) ($resp['access_token'] ?? ''));
                if ($token !== '') {
                    $settings['access_token'] = $token;
                    if (!empty($resp['refresh_token'])) {
                        $settings['refresh_token'] = (string) $resp['refresh_token'];
                    }
                    if (!empty($resp['expires_in'])) {
                        $settings['token_expires_at'] = now()->addSeconds((int) $resp['expires_in'])->toDateTimeString();
                    }
                    unset($settings['last_setup_error']);
                    $conn->update(['settings' => $settings, 'status' => 'active', 'last_error' => null]);
                }
            } catch (\Throwable $e) {
                $settings['last_setup_error'] = 'Avito token exception: '.$e->getMessage();
                $conn->update(['settings' => $settings, 'last_error' => $settings['last_setup_error']]);
            }
        }
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
