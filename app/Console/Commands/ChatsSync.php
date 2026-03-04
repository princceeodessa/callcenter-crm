<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Chat\ChatIngestService;
use Illuminate\Console\Command;

class ChatsSync extends Command
{
    protected $signature = 'chats:sync {--since-id=0 : Process integration_events with id > since-id} {--limit=2000 : Max events to process}';
    protected $description = 'Build messenger conversations/messages from existing integration_events (best-effort)';

    public function handle(ChatIngestService $ingest): int
    {
        $sinceId = (int)$this->option('since-id');
        $limit = (int)$this->option('limit');
        $limit = $limit > 0 ? min($limit, 10000) : 2000;

        $events = IntegrationEvent::query()
            ->where('direction', 'in')
            ->whereIn('provider', ['vk', 'telegram', 'avito'])
            ->when($sinceId > 0, fn($q) => $q->where('id', '>', $sinceId))
            ->orderBy('id')
            ->take($limit)
            ->get();

        $processed = 0;
        foreach ($events as $e) {
            if (!$e->account_id) {
                continue;
            }

            $conn = IntegrationConnection::query()
                ->where('account_id', $e->account_id)
                ->where('provider', $e->provider)
                ->first();
            if (!$conn) {
                continue;
            }

            try {
                $payload = is_array($e->payload) ? $e->payload : [];
                match ($e->provider) {
                    'vk' => $ingest->ingestFromVk($conn, $payload),
                    'telegram' => $ingest->ingestFromTelegram($conn, $payload),
                    'avito' => $ingest->ingestFromAvito($conn, $payload),
                    default => null,
                };
                $processed++;
            } catch (\Throwable $ex) {
                $this->warn("Skip event #{$e->id}: {$ex->getMessage()}");
            }
        }

        $this->info("Processed {$processed} events");
        return Command::SUCCESS;
    }
}
