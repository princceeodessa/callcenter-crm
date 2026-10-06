<?php

namespace App\Services\Warehouse;

use App\Models\Deal;
use App\Models\Purchase;
use App\Models\StockMark;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WarehouseConsignment;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Складской учёт кроссовочного пространства.
 *
 * Остаток по позиции «бренд+модель+размер» (warehouse_items): quantity (на руках),
 * reserved (зарезервировано открытыми сделками), avg_cost (средневзвешенная себестоимость).
 * Закупки приходуют (стадия is_stock_in), сделки резервируют (is_reserve) и списывают (is_final).
 * Всё идемпотентно и логируется в stock_movements.
 */
class WarehouseService
{
    // ===================== Закупки → склад =====================

    public function syncPurchaseStock(Purchase $purchase): void
    {
        $purchase->load('stage');
        $qty = (int) $purchase->quantity;
        // Приход зависит только от стадии — закрытие (архив) закупки остаток НЕ снимает.
        $shouldStock = $purchase->stage && $purchase->stage->is_stock_in && $qty > 0;
        $isStocked = $purchase->stocked_at !== null;

        if ($shouldStock && ! $isStocked) {
            DB::transaction(function () use ($purchase, $qty) {
                $item = $this->resolveItemForPurchase($purchase);
                $this->receive($item, $qty, $this->unitCost($purchase), $purchase->expected_sale_price, "Приход с закупки #{$purchase->id}", 'purchase', $purchase->id);
                $purchase->forceFill(['stocked_at' => now(), 'stocked_quantity' => $qty, 'warehouse_item_id' => $item->id])->save();
            });
        } elseif ($shouldStock && $isStocked && (int) $purchase->stocked_quantity !== $qty) {
            // Кол-во изменили у уже оприходованной закупки — досинхронизировать дельту.
            DB::transaction(function () use ($purchase, $qty) {
                $item = $purchase->warehouse_item_id ? WarehouseItem::find($purchase->warehouse_item_id) : $this->resolveItemForPurchase($purchase);
                if ($item) {
                    $delta = $qty - (int) $purchase->stocked_quantity;
                    if ($delta > 0) {
                        $this->receive($item, $delta, $this->unitCost($purchase), null, "Докуплено по закупке #{$purchase->id}", 'purchase', $purchase->id);
                    } else {
                        $this->changeQty($item, $delta, 'in_adjust', "Уменьшение по закупке #{$purchase->id}", 'purchase', $purchase->id);
                    }
                    $purchase->forceFill(['stocked_quantity' => $qty])->save();
                }
            });
        } elseif (! $shouldStock && $isStocked) {
            DB::transaction(function () use ($purchase) {
                $item = $purchase->warehouse_item_id ? WarehouseItem::find($purchase->warehouse_item_id) : $this->resolveItemForPurchase($purchase);
                if ($item) {
                    $this->changeQty($item, -(int) $purchase->stocked_quantity, 'in_reversal', "Откат прихода закупки #{$purchase->id}", 'purchase', $purchase->id);
                }
                $purchase->forceFill(['stocked_at' => null, 'stocked_quantity' => null])->save();
            });
        }
    }

    // ===================== Сделка: резерв / списание =====================

    public function syncDealStock(Deal $deal): void
    {
        if (! $deal->warehouse_item_id || ! (int) $deal->sold_quantity || $deal->returned_at) {
            return;
        }
        $deal->load('stage');
        $qty = (int) $deal->sold_quantity;
        $wonish = is_null($deal->closed_result) || $deal->closed_result === 'won';
        $desiredDeducted = $wonish && $deal->stage && $deal->stage->is_final;
        $desiredReserved = $wonish && ! $desiredDeducted && $deal->stage && $deal->stage->is_reserve;

        DB::transaction(function () use ($deal, $qty, $desiredDeducted, $desiredReserved) {
            $item = WarehouseItem::find($deal->warehouse_item_id);
            if (! $item) {
                return;
            }

            // --- Списание ---
            if ($desiredDeducted && ! $deal->stock_deducted_at) {
                // Белая ли пара (в 1С уходят только белые). Продавец мог указать сам — тогда не трогаем.
                if ($deal->stock_white === null) {
                    $deal->forceFill(['stock_white' => $this->guessWhite($item, $qty)])->save();
                }
                if ($deal->stock_reserved_at) {
                    $this->changeReserved($item, -$qty, 'reserve_release', "Резерв снят (продажа) · сделка #{$deal->id}", $deal->id);
                    $deal->forceFill(['stock_reserved_at' => null])->save();
                }
                $deal->forceFill(['sold_unit_cost' => $item->avg_cost])->save();
                $this->changeQty($item, -$qty, 'out', "Продажа · сделка #{$deal->id}", 'deal', $deal->id);
                $deal->forceFill(['stock_deducted_at' => now()])->save();
                // Пометить коды маркировки этой сделки как sold («Честный знак» — вывод из оборота).
                StockMark::where('account_id', $deal->account_id)
                    ->where('deal_id', $deal->id)
                    ->where('status', 'in_stock')
                    ->update(['status' => 'sold', 'sold_at' => now()]);
                $this->notifySale($deal, $item);
            } elseif (! $desiredDeducted && $deal->stock_deducted_at) {
                $this->changeQty($item, $qty, 'out_reversal', "Возврат на склад · сделка #{$deal->id}", 'deal', $deal->id);
                $deal->forceFill(['stock_deducted_at' => null, 'sold_unit_cost' => null])->save();
                // Коды маркировки: вернуть в оборот.
                StockMark::where('account_id', $deal->account_id)
                    ->where('deal_id', $deal->id)
                    ->where('status', 'sold')
                    ->update(['status' => 'in_stock', 'sold_at' => null]);
            }

            // --- Резерв (только пока не списано) ---
            if (! $deal->stock_deducted_at) {
                $reservedNow = $deal->stock_reserved_at !== null;
                if ($desiredReserved && ! $reservedNow) {
                    $this->changeReserved($item, $qty, 'reserve', "Резерв (бронь) · сделка #{$deal->id}", $deal->id);
                    $deal->forceFill(['stock_reserved_at' => now()])->save();
                } elseif (! $desiredReserved && $reservedNow) {
                    $this->changeReserved($item, -$qty, 'reserve_release', "Снятие резерва · сделка #{$deal->id}", $deal->id);
                    $deal->forceFill(['stock_reserved_at' => null])->save();
                }
            }
        });
    }

    /**
     * Продажа уже отмечена «списано» (stock_deducted_at), но к складу не привязана — так была
     * загружена история продаж 07.07.2026. Привязываем пару и списываем её по-настоящему.
     * Себестоимость продажи (sold_unit_cost) из загрузки сохраняем: это цена именно проданной пары.
     */
    public function deductLegacySale(Deal $deal, WarehouseItem $item, ?int $qty = null): void
    {
        DB::transaction(function () use ($deal, $item, $qty) {
            $deal = Deal::whereKey($deal->id)->lockForUpdate()->firstOrFail();
            $item = WarehouseItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($deal->warehouse_item_id) {
                return;   // уже привязана — повторный клик ничего не списывает
            }
            $qty = max(1, (int) ($qty ?? $deal->sold_quantity ?? 1));

            $deal->forceFill([
                'warehouse_item_id' => $item->id,
                'sold_quantity' => $qty,
                'sold_unit_cost' => $deal->sold_unit_cost ?? $item->avg_cost,
                'stock_deducted_at' => $deal->stock_deducted_at ?? now(),
                'stock_linked_at' => now(),
                'stock_link_skipped_at' => null,
            ])->save();

            $this->changeQty($item, -$qty, 'out', "Списание продажи · сделка #{$deal->id} (продажа была внесена без склада)", 'deal', $deal->id);
        });
    }

    /** Отменить привязку, сделанную при сверке: пара возвращается на склад, продажа снова «без списания». */
    public function unlinkLegacySale(Deal $deal): void
    {
        DB::transaction(function () use ($deal) {
            $deal = Deal::whereKey($deal->id)->lockForUpdate()->firstOrFail();
            if (! $deal->warehouse_item_id || ! $deal->stock_linked_at) {
                return;
            }
            $item = WarehouseItem::whereKey($deal->warehouse_item_id)->lockForUpdate()->first();
            if ($item) {
                $qty = max(1, (int) $deal->sold_quantity);
                $this->changeQty($item, $qty, 'out_reversal', "Отмена списания при сверке · сделка #{$deal->id}", 'deal', $deal->id);
            }
            $deal->forceFill(['warehouse_item_id' => null, 'stock_linked_at' => null])->save();
        });
    }

    /** Обнулить отрицательный остаток (ошибка учёта: пар меньше нуля не бывает). */
    public function zeroNegative(WarehouseItem $item, string $note): void
    {
        DB::transaction(function () use ($item, $note) {
            $item = WarehouseItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ((int) $item->quantity >= 0) {
                return;
            }
            $this->changeQty($item, -(int) $item->quantity, 'adjust', $note, 'manual', null);
        });
    }

    // ===================== Белые / серые пары =====================

    /**
     * Сколько пар позиции белые (пришли закупками «в белую») и сколько остальные.
     * Белые = принятые белые закупки − проданные белые пары (возврат снимает списание — пара снова белая на складе).
     *
     * @param  iterable<WarehouseItem>  $items
     * @return array<int, array{white:int, other:int}>
     */
    public function whiteSplits(iterable $items): array
    {
        $items = collect($items);
        if ($items->isEmpty()) {
            return [];
        }
        $ids = $items->pluck('id')->all();
        $in = Purchase::whereIn('warehouse_item_id', $ids)->where('is_white', true)->whereNotNull('stocked_at')
            ->groupBy('warehouse_item_id')->selectRaw('warehouse_item_id, SUM(stocked_quantity) q')->pluck('q', 'warehouse_item_id');
        $out = Deal::whereIn('warehouse_item_id', $ids)->where('stock_white', true)->whereNotNull('stock_deducted_at')
            ->groupBy('warehouse_item_id')->selectRaw('warehouse_item_id, SUM(sold_quantity) q')->pluck('q', 'warehouse_item_id');

        $res = [];
        foreach ($items as $item) {
            $qty = max(0, (int) $item->quantity);
            $white = max(0, min($qty, (int) ($in[$item->id] ?? 0) - (int) ($out[$item->id] ?? 0)));
            $res[$item->id] = ['white' => $white, 'other' => $qty - $white];
        }

        return $res;
    }

    /** @return array{white:int, other:int} */
    public function whiteSplit(WarehouseItem $item): array
    {
        return $this->whiteSplits([$item])[$item->id];
    }

    /** true — только белые, false — белых нет, null — есть и те и другие (решает продавец). */
    public function guessWhite(WarehouseItem $item, int $qty = 1): ?bool
    {
        $s = $this->whiteSplit($item);
        if ($s['white'] <= 0) {
            return false;
        }

        return $s['other'] <= 0 && $s['white'] >= $qty ? true : null;
    }

    /** Полный откат резерва и списания сделки (для переназначения товара). */
    public function reverseDealDeduction(Deal $deal): void
    {
        if (! $deal->warehouse_item_id) {
            return;
        }
        DB::transaction(function () use ($deal) {
            $item = WarehouseItem::find($deal->warehouse_item_id);
            $qty = (int) $deal->sold_quantity;
            if ($item && $qty > 0) {
                if ($deal->stock_deducted_at) {
                    $this->changeQty($item, $qty, 'out_reversal', "Возврат (переназначение) · сделка #{$deal->id}", 'deal', $deal->id);
                }
                if ($deal->stock_reserved_at) {
                    $this->changeReserved($item, -$qty, 'reserve_release', "Снятие резерва (переназначение) · сделка #{$deal->id}", $deal->id);
                }
            }
            $deal->forceFill(['stock_deducted_at' => null, 'stock_reserved_at' => null, 'sold_unit_cost' => null])->save();
            // Отвязать коды маркировки от сделки при переназначении.
            StockMark::where('account_id', $deal->account_id)
                ->where('deal_id', $deal->id)
                ->update(['deal_id' => null, 'status' => 'in_stock', 'sold_at' => null]);
        });
    }

    /** Оформить возврат: вернуть пары на склад, пометить сделку возвращённой (терминально). */
    public function returnDealStock(Deal $deal): void
    {
        if (! $deal->warehouse_item_id || ! (int) $deal->sold_quantity || $deal->returned_at) {
            return;
        }
        DB::transaction(function () use ($deal) {
            $item = WarehouseItem::find($deal->warehouse_item_id);
            $qty = (int) $deal->sold_quantity;
            if ($item) {
                if ($deal->stock_deducted_at) {
                    $this->changeQty($item, $qty, 'return', "Возврат · сделка #{$deal->id}", 'deal', $deal->id);
                }
                if ($deal->stock_reserved_at) {
                    $this->changeReserved($item, -$qty, 'reserve_release', "Снятие резерва (возврат) · сделка #{$deal->id}", $deal->id);
                }
            }
            $deal->forceFill(['returned_at' => now(), 'stock_deducted_at' => null, 'stock_reserved_at' => null, 'sold_unit_cost' => null])->save();
        });
    }

    // ===================== Ручные операции =====================

    public function replenish(WarehouseItem $item, int $qty, ?string $note = null): void
    {
        if ($qty === 0) {
            return;
        }
        DB::transaction(fn () => $this->changeQty($item, $qty, $qty > 0 ? 'replenish' : 'adjust', $note ?? 'Ручное пополнение', 'manual', null));
    }

    public function setQuantity(WarehouseItem $item, int $newQty, ?string $note = null): void
    {
        $delta = $newQty - (int) $item->quantity;
        if ($delta === 0) {
            return;
        }
        DB::transaction(fn () => $this->changeQty($item, $delta, 'adjust', $note ?? 'Ручная корректировка остатка', 'manual', null));
    }

    // ===================== Реализация (посредники) =====================

    /** Передать N пар посреднику под реализацию: снимает с доступного остатка, не трогая quantity. */
    public function giveForConsignment(WarehouseItem $item, int $qty, string $consignee, ?string $note = null): WarehouseConsignment
    {
        return DB::transaction(function () use ($item, $qty, $consignee, $note) {
            $consignment = WarehouseConsignment::create([
                'account_id' => $item->account_id,
                'warehouse_item_id' => $item->id,
                'consignee' => $consignee,
                'quantity' => $qty,
                'unit_cost' => $item->avg_cost,
                'status' => 'given',
                'note' => $note,
                'user_id' => Auth::id(),
                'given_at' => now(),
            ]);
            $this->changeConsigned($item, $qty, 'consign_out', "Передано под реализацию ({$consignee}) · #{$consignment->id}", $consignment->id);

            return $consignment;
        });
    }

    /** Посредник продал пары — списываем со склада насовсем. */
    public function resolveConsignmentSold(WarehouseConsignment $consignment): void
    {
        if ($consignment->status !== 'given') {
            return;
        }
        DB::transaction(function () use ($consignment) {
            $item = WarehouseItem::find($consignment->warehouse_item_id);
            if ($item) {
                $this->changeQty($item, -$consignment->quantity, 'consign_sold', "Продано посредником ({$consignment->consignee}) · #{$consignment->id}", 'consignment', $consignment->id);
                $this->changeConsigned($item, -$consignment->quantity, 'consign_resolve', "Снятие с реализации (продано) · #{$consignment->id}", $consignment->id);
            }
            $consignment->forceFill(['status' => 'sold', 'resolved_at' => now()])->save();
        });
    }

    /** Посредник вернул пары — они снова доступны на своём складе. */
    public function resolveConsignmentReturned(WarehouseConsignment $consignment): void
    {
        if ($consignment->status !== 'given') {
            return;
        }
        DB::transaction(function () use ($consignment) {
            $item = WarehouseItem::find($consignment->warehouse_item_id);
            if ($item) {
                $this->changeConsigned($item, -$consignment->quantity, 'consign_resolve', "Возврат с реализации · #{$consignment->id}", $consignment->id);
            }
            $consignment->forceFill(['status' => 'returned', 'resolved_at' => now()])->save();
        });
    }

    // ===================== Низкоуровневое =====================

    /** Приход с обновлением средневзвешенной себестоимости. */
    private function receive(WarehouseItem $item, int $qty, ?float $unitCost, $defaultSalePrice, string $note, string $sourceType, ?int $sourceId): void
    {
        if ($unitCost !== null) {
            $oldQ = max(0, (int) $item->quantity);
            $oldC = (float) ($item->avg_cost ?? 0);
            $newQ = $oldQ + $qty;
            $item->avg_cost = $newQ > 0 ? round((($oldQ * $oldC) + ($qty * $unitCost)) / $newQ, 2) : $unitCost;
        }
        if ($item->sale_price === null && $defaultSalePrice !== null) {
            $item->sale_price = $defaultSalePrice;
        }
        $this->changeQty($item, $qty, 'in', $note, $sourceType, $sourceId);
    }

    private function changeQty(WarehouseItem $item, int $delta, string $type, string $note, string $sourceType, ?int $sourceId): void
    {
        $item->quantity = (int) $item->quantity + $delta;
        $item->save();
        $this->logMovement($item, $type, $delta, $sourceType, $sourceId, $note);
    }

    private function changeReserved(WarehouseItem $item, int $delta, string $type, string $note, ?int $dealId): void
    {
        $item->reserved = max(0, (int) $item->reserved + $delta);
        $item->save();
        $this->logMovement($item, $type, $delta, 'deal', $dealId, $note);
    }

    private function changeConsigned(WarehouseItem $item, int $delta, string $type, string $note, ?int $consignmentId): void
    {
        $item->consigned = max(0, (int) $item->consigned + $delta);
        $item->save();
        $this->logMovement($item, $type, $delta, 'consignment', $consignmentId, $note);
    }

    private function logMovement(WarehouseItem $item, string $type, int $delta, string $sourceType, ?int $sourceId, string $note): void
    {
        StockMovement::create([
            'account_id' => $item->account_id,
            'warehouse_item_id' => $item->id,
            'type' => $type,
            'quantity' => $delta,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'user_id' => Auth::id(),
            'note' => $note,
        ]);
    }

    /** Уведомить руководителей отдела о новой продаже (тост/колокольчик). */
    private function notifySale(Deal $deal, WarehouseItem $item): void
    {
        $heads = User::where('account_id', $deal->account_id)
            ->where('role', 'sneaker_head')->where('is_active', true)->pluck('id');
        if ($heads->isEmpty()) {
            return;
        }
        $amount = $deal->amount ? number_format((float) $deal->amount, 0, '', ' ') : '—';
        $body = 'Сделка #'.$deal->id.': '.$item->display_name.' × '.(int) $deal->sold_quantity.' — '.$amount.' ₽';
        $profit = $deal->sale_profit;
        if ($profit !== null) {
            $margin = $deal->sale_margin_percent;
            $body .= ' · прибыль '.number_format($profit, 0, '', ' ').' ₽'.($margin !== null ? ' ('.$margin.'%)' : '');
        }
        foreach ($heads as $uid) {
            // По сделке одно уведомление (уникальный ключ user+type+source): при смене товара у проданной
            // сделки — обновить его, а не создавать второе (create падал 500 посреди списания).
            UserNotification::updateOrCreate(
                ['user_id' => $uid, 'type' => 'sneaker_sale', 'source_type' => 'deal', 'source_id' => $deal->id],
                [
                    'account_id' => $deal->account_id,
                    'title' => 'Новая продажа кроссовок',
                    'body' => $body,
                    'payload' => ['deal_id' => $deal->id],
                    'is_read' => false,
                ]
            );
        }
    }

    private function unitCost(Purchase $purchase): ?float
    {
        return $purchase->cost !== null ? (float) $purchase->cost : null;
    }

    private function resolveItemForPurchase(Purchase $purchase): WarehouseItem
    {
        return WarehouseItem::firstOrCreate(
            [
                'account_id' => $purchase->account_id,
                'brand' => (string) ($purchase->brand ?? ''),
                'model' => (string) ($purchase->model ?? ''),
                'size' => (string) ($purchase->size ?? ''),
            ],
            ['sale_price' => $purchase->expected_sale_price]
        );
    }
}
