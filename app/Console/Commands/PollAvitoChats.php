<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\AvitoOAuthService;
use Illuminate\Console\Command;

class PollAvitoChats extends Command
{
    protected $signature = 'integrations:avito-poll {--account_id=} {--limit=100}';
    protected $description = 'Poll Avito chats (Messenger API) and store last_message into integration_events.';

    public function handle(): int
    {
        $q = IntegrationConnection::query()
            ->where('provider', 'avito')
            ->where('status', 'active');

        if ($this->option('account_id')) {
            $q->where('account_id', (int)$this->option('account_id'));
        }

        $connections = $q->get();
        $limit = (int)$this->option('limit');

        if ($connections->isEmpty()) {
            $this->info('No active Avito connections.');
            return self::SUCCESS;
        }

        foreach ($connections as $conn) {
            $settings = $conn->settings ?? [];
            $userId = $settings['user_id'] ?? null;
            $token = $settings['access_token'] ?? null;

            // Refresh token if it is close to expiration (best-effort)
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
                            $token = $settings['access_token'];
                            $conn->update(['settings' => $settings]);
                        }
                    }
                }
            } catch (\Throwable $e) {
                // ignore token refresh errors
            }

            if (!$userId || !$token) {
                $this->warn("[account {$conn->account_id}] skipped: missing user_id/access_token");
                continue;
            }

            $client = new AvitoApiClient($token);
            $chats = $client->listChats($userId, $limit);

            $seen = $settings['last_seen_message_id_by_chat'] ?? [];
            if (!is_array($seen)) {
                $seen = [];
            }

            $newCount = 0;

            foreach ($chats as $chat) {
                $chatId = (string)($chat['id'] ?? $chat['chat_id'] ?? '');
                if ($chatId === '') {
                    continue;
                }

                $last = $chat['last_message'] ?? null;
                if (!is_array($last)) {
                    continue;
                }

                $messageId = (string)($last['id'] ?? $last['message_id'] ?? '');
                if ($messageId === '') {
                    continue;
                }

                if (($seen[$chatId] ?? null) === $messageId) {
                    continue;
                }

                $seen[$chatId] = $messageId;
                $newCount++;

                IntegrationEvent::create([
                    'account_id' => $conn->account_id,
                    'provider' => 'avito',
                    'direction' => 'in',
                    'event_type' => 'last_message',
                    'external_id' => $messageId,
                    'payload' => [
                        // normalized for ChatIngestService
                        'chat_id' => $chatId,
                        'account_id' => (string) $userId,
                        'chat' => $chat,
                        'message' => $last,
                    ],
                    'received_at' => now(),
                ]);
            }

            // keep map reasonably small
            if (count($seen) > 500) {
                $seen = array_slice($seen, -500, null, true);
            }

            $settings['last_seen_message_id_by_chat'] = $seen;
            $conn->update([
                'settings' => $settings,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->info("[account {$conn->account_id}] chats=".count($chats)." new={$newCount}");
        }

        return self::SUCCESS;
    }
}
