<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Chat\ChatIngestService;
use App\Services\Integrations\AvitoApiClient;
use App\Services\Integrations\AvitoTokenManager;
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
            $tokenManager = app(AvitoTokenManager::class);
            $token = $tokenManager->getValidToken($conn);
            $settings = is_array($conn->fresh()->settings) ? $conn->fresh()->settings : [];
            $userId = trim((string) ($settings['user_id'] ?? ''));

            if ($userId === '' || $token === '') {
                $this->warn("[account {$conn->account_id}] skipped: missing user_id/access_token");
                continue;
            }

            $client = new AvitoApiClient($token, timeoutSeconds: 8.0);
            $seen = $settings['last_seen_message_id_by_chat'] ?? [];
            if (!is_array($seen)) {
                $seen = [];
            }

            $newCount = 0;
            $ingestedCount = 0;
            $scannedChats = 0;
            $offset = 0;
            $scannedIds = [];
            $pages = 0;

            while (true) {
                try {
                    $chats = $client->listChats($userId, $limit, $offset);
                } catch (\Throwable $e) {
                    // Сеть/таймаут Avito не должны валить крон и засорять лог фаталами.
                    $conn->update(['last_error' => 'Avito poll error: '.$e->getMessage()]);
                    $this->warn("[account {$conn->account_id}] avito poll skipped: ".$e->getMessage());
                    break;
                }
                if (empty($chats)) {
                    break;
                }
                // Авито (v2 без параметров) отдаёт первую страницу, не глядя на offset: страница без новых чатов — конец
                // списка. Раньше опрос листал её по кругу, пока Авито не начинал отказывать, — 4–16 минут на запуск.
                $fresh = self::freshChatIds($chats, $scannedIds);
                if ($fresh === []) {
                    break;
                }
                $scannedIds += array_fill_keys($fresh, true);

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

                if (count($chats) < $limit || ++$pages >= self::MAX_PAGES) {
                    break;
                }

                $offset += $limit;
            }

            $settings['last_seen_message_id_by_chat'] = self::rememberSeen($seen);
            $conn->update([
                'settings' => $settings,
                'last_synced_at' => now(),
                'last_error' => null,
            ]);

            $this->info("[account {$conn->account_id}] chats={$scannedChats} new={$newCount} ingested={$ingestedCount}");
        }

        return self::SUCCESS;
    }

    /** Сколько чатов помнить (с запасом; было 1000 — забытый чат выкачивается заново). */
    public const SEEN_LIMIT = 20000;

    /** Предохранитель: не больше стольких страниц списка чатов за запуск. */
    public const MAX_PAGES = 50;

    /**
     * Чаты страницы, которых в этом запуске ещё не было. Пусто — Авито вернул ту же страницу (offset он не учитывает):
     * дальше листать нечего. 23.10.2026 опрос листал одну и ту же страницу по 4 раза в секунду, пока Авито не начинал
     * отказывать, — запуск шёл 4–16 минут и держал весь планировщик: сбор рекламы «в :20» пропускался с полуночи.
     *
     * @param  array<int, array<string, mixed>>  $chats
     * @param  array<string, true>  $scanned
     * @return list<string>
     */
    public static function freshChatIds(array $chats, array $scanned): array
    {
        $ids = [];
        foreach ($chats as $chat) {
            $id = (string) ($chat['id'] ?? $chat['chat_id'] ?? '');
            if ($id !== '' && ! isset($scanned[$id])) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }

    /**
     * Последнее увиденное сообщение по каждому чату; лишнее отрезается со старых.
     *
     * @param  array<string, string>  $seen
     * @return array<string, string>
     */
    public static function rememberSeen(array $seen): array
    {
        return count($seen) > self::SEEN_LIMIT ? array_slice($seen, -self::SEEN_LIMIT, null, true) : $seen;
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
