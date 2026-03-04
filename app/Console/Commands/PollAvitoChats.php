<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Integrations\AvitoApiClient;
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
                        'chat' => $chat,
                        'last_message' => $last,
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
