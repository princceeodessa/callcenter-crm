<?php

namespace App\Console\Commands;

use App\Models\IntegrationConnection;
use App\Services\Integrations\HandsLeadSync;
use Illuminate\Console\Command;

/** Новые заказы «Руки» (hands.ru) → лиды потолков; раз в 5 минут из планировщика. */
class PollHandsOrders extends Command
{
    protected $signature = 'integrations:hands-poll';

    protected $description = 'Заказы «Руки» (hands.ru) → лиды в «Поступил лид»';

    public function handle(HandsLeadSync $sync): int
    {
        $conns = IntegrationConnection::withoutGlobalScopes()->where('provider', HandsLeadSync::PROVIDER)->where('status', 'active')->get();
        foreach ($conns as $conn) {
            try {
                $r = $sync->poll($conn);
                $this->info("[account {$conn->account_id}] заказов {$r['orders']}, новых лидов {$r['created']}, договорились (→ «Замер назначен») {$r['moved']}");
            } catch (\Throwable $e) {
                // сеть или ключ — ошибка видна в «Интеграциях», планировщик не падает
                $conn->forceFill(['last_error' => 'Руки: '.mb_substr($e->getMessage(), 0, 300)])->save();
                $this->warn("[account {$conn->account_id}] ".$e->getMessage());
            }
        }

        return self::SUCCESS;
    }
}
