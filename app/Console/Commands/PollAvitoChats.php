<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Chat\ChatIngestService;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\AvitoOAuthService;
use Illuminate\Console\Command;

class PollAvitoChats extends Command
{
    protected $signature = 'integrations:avito-poll {--account_id=} {--limit=100}';
    protected $description = 'Poll Avito chats (Messenger API), store events and ingest incoming messages into CRM.';

    public function handle(): int
    {
        $q = IntegrationConnection::query()
            ->where('provider', 'avito')
            ->where('status', 'active');

        if ($this->option('account_id')) {
            $q->where('account_id', (int)$this->option('account_id'));
        }

        $connections = $q->get();
        $limit = max(1, min(100, (int)$this->option('limit')));

        if ($connections->isEmpty()) {
            $this->info('No active Avito connections.');
            return self::SUCCESS;
        }

        foreach ($connections as $conn) {
            $settings = $conn->settings ?? [];
            $userId = (string)($settings['user_id'] ?? '');
            $token = trim((string)($settings['access_token'] ?? ''));

            if ($token === '' && !empty($settings['client_id']) && !empty($settings['client_secret'])) {
                try {
                    $oauth = app(AvitoOAuthService::class);
                    $resp = $oauth->clientCredentials((string)$settings['client_id'], (string)$settings['client_secret']);
                    $freshToken = trim((string)($resp['access_token'] ?? ''));
                    if ($freshToken !== '') {
                        $settings['access_token'] = $freshToken;
                        $token = $freshToken;
                        if (!empty($resp['expires_in'])) {
                            $settings['token_expires_at'] = now()->addSeconds((int)$resp['expires_in'])->toDateTimeString();
                        }
                        if (!empty($resp['refresh_token'])) {
                            $settings['refresh_token'] = (string)$resp['refresh_token'];
                        }
                        unset($settings['last_setup_error']);
                        $conn->update([
                            'settings' => $settings,
                            'status' => 'active',
                            'last_error' => null,
                        ]);
                    } else {
                        $msg = 'Avito token error: '.json_encode($resp, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                        $settings['last_setup_error'] = $msg;
                        $conn->update([
                            'settings' => $settings,
                            'status' => trim((string)($settings['user_id'] ?? '')) !== '' ? 'active' : 'error',
                            'last_error' => $msg,
                        ]);
                    }
                } catch (\Throwable $e) {
                    $msg = 'Avito token exception: '.$e->getMessage();
                    $settings['last_setup_error'] = $msg;
                    $conn->update([
                        'settings' => $settings,
                        'status' => trim((string)($settings['user_id'] ?? '')) !== '' ? 'active' : 'error',
                        'last_error' => $msg,
                    ]);
                }
            }

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
                            $token = trim((string)$settings['access_token']);
                            $conn->update(['settings' => $settings]);
                        }
                    }
                }
            } catch (\Throwable) {
                // ignore token refresh errors
            }

            if ($userId === '' || $token === '') {
                $this->warn("[account {$conn->account_id}] skipped: missing user_id/access_token");
                continue;
            }

            $client = new AvitoApiClient($token);
            $seen = $settings['last_seen_message_id_by_chat'] ?? [];
            if (!is_array($seen)) {
                $seen = [];
            }

            $newCount = 0;
            $ingestedCount = 0;
            $scannedChats = 0;
            $offset = 0;

            while (true) {
                $chats = $client->listChats($userId, $limit, $offset);
                if (empty($chats)) {
                    break;
                }

                foreach ($chats as $chat) {
                    $scannedChats++;

                    $chatId = (string)($chat['id'] ?? $chat['chat_id'] ?? '');
                    if ($chatId === '') {
                        continue;
                    }

                    $last = $chat['last_message'] ?? $chat['lastMessage'] ?? $chat['last_message_info'] ?? null;
                    $lastMessageId = is_array($last) ? (string)($last['id'] ?? $last['message_id'] ?? '') : '';
                    $previousSeen = (string)($seen[$chatId] ?? '');

                    if ($lastMessageId !== '' && $previousSeen === $lastMessageId) {
                        continue;
                    }

                    $chatFull = [];
                    try {
                        $chatFull = $client->getChat($userId, $chatId);
                    } catch (\Throwable) {
                        $chatFull = [];
                    }

                    $historyMessages = [];
                    try {
                        $historyMessages = $client->listMessages($userId, $chatId, 50, 0);
                    } catch (\Throwable) {
                        $historyMessages = [];
                    }

                    $historyMessages = array_values(array_filter($historyMessages, fn($item) => is_array($item)));

                    if (!empty($historyMessages)) {
                        usort($historyMessages, function (array $a, array $b) {
                            $ta = $this->messageTimestamp($a);
                            $tb = $this->messageTimestamp($b);
                            if ($ta === $tb) {
                                return strcmp((string)($a['id'] ?? ''), (string)($b['id'] ?? ''));
                            }
                            return $ta <=> $tb;
                        });

                        $ids = array_map(fn($item) => (string)($item['id'] ?? $item['message_id'] ?? ''), $historyMessages);
                        $startAt = 0;
                        if ($previousSeen !== '') {
                            $foundIndex = array_search($previousSeen, $ids, true);
                            if ($foundIndex !== false) {
                                $startAt = $foundIndex + 1;
                            }
                        }

                        for ($i = $startAt; $i < count($historyMessages); $i++) {
                            $historyMessage = $historyMessages[$i];
                            $historyMessageId = (string)($historyMessage['id'] ?? $historyMessage['message_id'] ?? '');
                            if ($historyMessageId === '') {
                                continue;
                            }

                            if (!$this->isIncomingMessage($historyMessage, (string)$userId)) {
                                continue;
                            }

                            $newCount++;
                            $eventPayload = [
                                'chat_id' => $chatId,
                                'account_id' => (string)$userId,
                                'chat' => $chat,
                                'chat_full' => $chatFull,
                                'message' => $historyMessage,
                            ];

                            IntegrationEvent::create([
                                'account_id' => $conn->account_id,
                                'provider' => 'avito',
                                'direction' => 'in',
                                'event_type' => 'message',
                                'external_id' => $historyMessageId,
                                'payload' => $eventPayload,
                                'received_at' => now(),
                            ]);

                            try {
                                $message = app(ChatIngestService::class)->ingestFromAvito($conn, $eventPayload);
                                if ($message) {
                                    $ingestedCount++;
                                }
                            } catch (\Throwable $e) {
                                $conn->update(['last_error' => 'Avito ingest error: '.$e->getMessage()]);
                            }
                        }

                        $lastHistoryId = '';
                        for ($i = count($ids) - 1; $i >= 0; $i--) {
                            if ($ids[$i] !== '') {
                                $lastHistoryId = $ids[$i];
                                break;
                            }
                        }
                        if ($lastHistoryId !== '') {
                            $seen[$chatId] = $lastHistoryId;
                            continue;
                        }
                    }

                    if (!is_array($last)) {
                        continue;
                    }

                    if ($lastMessageId === '') {
                        continue;
                    }

                    if (!$this->isIncomingMessage($last, (string)$userId)) {
                        $seen[$chatId] = $lastMessageId;
                        continue;
                    }

                    if ($previousSeen === $lastMessageId) {
                        continue;
                    }

                    $seen[$chatId] = $lastMessageId;
                    $newCount++;

                    $eventPayload = [
                        'chat_id' => $chatId,
                        'account_id' => (string)$userId,
                        'chat' => $chat,
                        'chat_full' => $chatFull,
                        'message' => $last,
                    ];

                    IntegrationEvent::create([
                        'account_id' => $conn->account_id,
                        'provider' => 'avito',
                        'direction' => 'in',
                        'event_type' => 'last_message',
                        'external_id' => $lastMessageId,
                        'payload' => $eventPayload,
                        'received_at' => now(),
                    ]);

                    try {
                        $message = app(ChatIngestService::class)->ingestFromAvito($conn, $eventPayload);
                        if ($message) {
                            $ingestedCount++;
                        }
                    } catch (\Throwable $e) {
                        $conn->update(['last_error' => 'Avito ingest error: '.$e->getMessage()]);
                    }
                }

                if (count($chats) < $limit) {
                    break;
                }

                $offset += $limit;
            }

            if (count($seen) > 1000) {
                $seen = array_slice($seen, -1000, null, true);
            }

            $settings['last_seen_message_id_by_chat'] = $seen;
            $conn->update([
                'settings' => $settings,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->info("[account {$conn->account_id}] chats={$scannedChats} new={$newCount} ingested={$ingestedCount}");
        }

        return self::SUCCESS;
    }

    private function isIncomingMessage(array $message, string $ownerUserId): bool
    {
        $direction = strtolower(trim((string)($message['direction'] ?? '')));
        $authorId = (string)($message['author_id'] ?? $message['authorId'] ?? '');

        return match ($direction) {
            'in', 'incoming' => true,
            'out', 'outgoing' => false,
            default => ($authorId === '' || $authorId !== $ownerUserId),
        };
    }

    private function messageTimestamp(array $message): int
    {
        foreach (['created', 'created_at', 'timestamp', 'time', 'date'] as $key) {
            $value = $message[$key] ?? null;
            if ($value === null || $value === '') {
                continue;
            }

            try {
                if (is_numeric($value)) {
                    $raw = (int)$value;
                    if ($raw > 9999999999) {
                        $raw = (int) floor($raw / 1000);
                    }
                    return $raw;
                }

                if (is_string($value)) {
                    return strtotime($value) ?: 0;
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return 0;
    }
}
