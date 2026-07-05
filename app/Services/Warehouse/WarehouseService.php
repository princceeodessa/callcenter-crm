<?php

namespace App\Services\Warehouse;

use App\Models\Deal;
use App\Models\Purchase;
use App\Models\StockMark;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserNotification;
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
            UserNotification::create([
                'account_id' => $deal->account_id,
                'user_id' => $uid,
                'type' => 'sneaker_sale',
                'title' => 'Новая продажа кроссовок',
                'body' => $body,
                'source_type' => 'deal',
                'source_id' => $deal->id,
                'payload' => ['deal_id' => $deal->id],
                'is_read' => false,
            ]);
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
