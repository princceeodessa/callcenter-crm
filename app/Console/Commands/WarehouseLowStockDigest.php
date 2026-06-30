<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Models\UserNotification;
use App\Models\WarehouseItem;
use Illuminate\Console\Command;

class WarehouseLowStockDigest extends Command
{
    protected $signature = 'warehouse:low-stock-digest';
    protected $description = 'Ежедневно уведомлять руководителей кроссовочного отдела о заканчивающихся позициях.';

    public function handle(): int
    {
        $byAccount = User::where('role', 'sneaker_head')->where('is_active', true)->get()->groupBy('account_id');

        foreach ($byAccount as $accId => $heads) {
            $low = WarehouseItem::where('account_id', $accId)
                ->where('low_stock_threshold', '>', 0)
                ->whereRaw('(quantity - reserved) <= low_stock_threshold')
                ->orderByRaw('(quantity - reserved) asc')
                ->get();

            if ($low->isEmpty()) {
                continue;
            }

            $names = $low->take(8)->map(fn ($i) => $i->display_name.' ('.$i->available.')')->implode(', ');
            $body = 'Заканчивается '.$low->count().' поз.: '.$names.($low->count() > 8 ? '…' : '');

            foreach ($heads as $h) {
                UserNotification::create([
                    'account_id' => (int) $accId,
                    'user_id' => $h->id,
                    'type' => 'low_stock',
                    'title' => 'Низкий остаток на складе',
                    'body' => $body,
                    'payload' => ['context_url' => route('warehouse.index')],
                    'is_read' => false,
                ]);
            }

            $this->info("[account {$accId}] low={$low->count()} heads=".count($heads));
        }

        return self::SUCCESS;
    }
}
