<?php

namespace App\Services\Warehouse;

use App\Models\Deal;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\WarehouseItem;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Складской учёт кроссовочного пространства.
 *
 * Остаток ведётся по позиции «бренд+модель+размер» (warehouse_items).
 * Канбан закупок приходует на склад (при стадии is_stock_in), продажи — списывают.
 * Все операции идемпотентны и логируются в stock_movements.
 */
class WarehouseService
{
    /** Приход с закупки при попадании в стадию склада; реверс при уходе. Идемпотентно (purchases.stocked_at). */
    public function syncPurchaseStock(Purchase $purchase): void
    {
        $purchase->load('stage');
        $shouldStock = $purchase->stage
            && $purchase->stage->is_stock_in
            && ! $purchase->closed_at
            && (int) $purchase->quantity > 0;
        $isStocked = $purchase->stocked_at !== null;

        if ($shouldStock && ! $isStocked) {
            DB::transaction(function () use ($purchase) {
                $item = $this->resolveItemForPurchase($purchase);
                $this->applyDelta($item, (int) $purchase->quantity, 'in', 'purchase', $purchase->id, "Приход с закупки #{$purchase->id}");
                $purchase->forceFill(['stocked_at' => now(), 'warehouse_item_id' => $item->id])->save();
            });
        } elseif (! $shouldStock && $isStocked) {
            DB::transaction(function () use ($purchase) {
                $item = $purchase->warehouse_item_id
                    ? WarehouseItem::find($purchase->warehouse_item_id)
                    : $this->resolveItemForPurchase($purchase);
                if ($item) {
                    $this->applyDelta($item, -(int) $purchase->quantity, 'in_reversal', 'purchase', $purchase->id, "Откат прихода закупки #{$purchase->id}");
                }
                $purchase->forceFill(['stocked_at' => null])->save();
            });
        }
    }

    /** Списание по сделке при финальной стадии «Продано»; реверс при reopen/закрытии не в плюс. Идемпотентно. */
    public function syncDealStock(Deal $deal): void
    {
        if (! $deal->warehouse_item_id || ! (int) $deal->sold_quantity) {
            return;
        }

        $deal->load('stage');
        $wonish = is_null($deal->closed_result) || $deal->closed_result === 'won';
        $shouldDeduct = $deal->stage && $deal->stage->is_final && (int) $deal->sold_quantity > 0 && $wonish;
        $isDeducted = $deal->stock_deducted_at !== null;

        if ($shouldDeduct && ! $isDeducted) {
            DB::transaction(function () use ($deal) {
                $item = WarehouseItem::find($deal->warehouse_item_id);
                if ($item) {
                    $this->applyDelta($item, -(int) $deal->sold_quantity, 'out', 'deal', $deal->id, "Продажа · сделка #{$deal->id}");
                }
                $deal->forceFill(['stock_deducted_at' => now()])->save();
            });
        } elseif (! $shouldDeduct && $isDeducted) {
            DB::transaction(function () use ($deal) {
                $item = WarehouseItem::find($deal->warehouse_item_id);
                if ($item) {
                    $this->applyDelta($item, (int) $deal->sold_quantity, 'out_reversal', 'deal', $deal->id, "Возврат на склад · сделка #{$deal->id}");
                }
                $deal->forceFill(['stock_deducted_at' => null])->save();
            });
        }
    }

    /** Вернуть на склад прежнее списание сделки (используется при переназначении товара). */
    public function reverseDealDeduction(Deal $deal): void
    {
        if (! $deal->stock_deducted_at || ! $deal->warehouse_item_id || ! (int) $deal->sold_quantity) {
            return;
        }
        DB::transaction(function () use ($deal) {
            $item = WarehouseItem::find($deal->warehouse_item_id);
            if ($item) {
                $this->applyDelta($item, (int) $deal->sold_quantity, 'out_reversal', 'deal', $deal->id, "Возврат (переназначение) · сделка #{$deal->id}");
            }
            $deal->forceFill(['stock_deducted_at' => null])->save();
        });
    }

    /** Ручное пополнение (+/- qty) с записью движения. */
    public function replenish(WarehouseItem $item, int $qty, ?string $note = null): void
    {
        if ($qty === 0) {
            return;
        }
        DB::transaction(fn () => $this->applyDelta($item, $qty, $qty > 0 ? 'replenish' : 'adjust', 'manual', null, $note ?? 'Ручное пополнение'));
    }

    /** Ручная установка точного остатка. */
    public function setQuantity(WarehouseItem $item, int $newQty, ?string $note = null): void
    {
        $delta = $newQty - (int) $item->quantity;
        if ($delta === 0) {
            return;
        }
        DB::transaction(fn () => $this->applyDelta($item, $delta, 'adjust', 'manual', null, $note ?? 'Ручная корректировка остатка'));
    }

    private function applyDelta(WarehouseItem $item, int $delta, string $type, string $sourceType, ?int $sourceId, string $note): void
    {
        $item->quantity = max(0, (int) $item->quantity + $delta);
        $item->save();

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

    private function resolveItemForPurchase(Purchase $purchase): WarehouseItem
    {
        return WarehouseItem::firstOrCreate(
            [
                'account_id' => $purchase->account_id,
                'brand' => (string) ($purchase->brand ?? ''),
                'model' => (string) ($purchase->model ?? ''),
                'size' => (string) ($purchase->size ?? ''),
            ],
            [
                'sale_price' => $purchase->expected_sale_price,
            ]
        );
    }
}
