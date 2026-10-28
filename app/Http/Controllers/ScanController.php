<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\StockMark;
use App\Models\WarehouseProduct;
use App\Support\Marking\MarkCode;
use App\Support\Ui\Sneakers;
use App\Support\Warehouse\CodeLookup;
use App\Support\Warehouse\ProductClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Скан штрихкода сканером в любом месте CRM → карточка товара: размеры, остатки, цены,
 * «Продать» и «Печать». Понимает артикул со штрихкода ценника, код «Честного знака»
 * (находит конкретную пару) и GTIN; исправляет ввод в русской раскладке (поиск — CodeLookup).
 */
class ScanController extends Controller
{
    public function show(Request $request)
    {
        $accId = Auth::user()->account_id;
        $found = CodeLookup::find($accId, (string) $request->query('code', ''));
        ['raw' => $raw, 'code' => $code, 'mark' => $mark, 'products' => $products, 'highlight' => $highlightItemId] = $found;

        $cards = $products->map(function (WarehouseProduct $p) use ($accId) {
            $items = CodeLookup::items($accId, $p);
            $ids = $items->pluck('id');

            return [
                'product' => $p,
                'items' => $items,
                // коды «Честного знака» на складе по размерам
                'marks' => $ids->isEmpty() ? collect() : StockMark::where('account_id', $accId)->whereIn('warehouse_item_id', $ids)
                    ->where('status', 'in_stock')->selectRaw('warehouse_item_id, COUNT(*) c')->groupBy('warehouse_item_id')
                    ->pluck('c', 'warehouse_item_id'),
                // последние продажи этой модели
                'sales' => $ids->isEmpty() ? collect() : Deal::where('account_id', $accId)->whereIn('warehouse_item_id', $ids)
                    ->whereNotNull('stock_deducted_at')->with('warehouseItem:id,size', 'responsible:id,name')
                    ->orderByDesc('stock_deducted_at')->limit(8)->get(),
                'soldTotal' => $ids->isEmpty() ? 0 : (int) Deal::where('account_id', $accId)->whereIn('warehouse_item_id', $ids)
                    ->whereNotNull('stock_deducted_at')->sum('sold_quantity'),
            ];
        });

        // Нашли одну модель — сразу в «Быструю продажу» этой модели (скан ценника = «продать эти кроссовки»).
        // Размер выбираем сами, если он однозначен: пара из кода ЧЗ или единственный размер в наличии.
        // ?info=1 — показать карточку с остатками вместо продажи.
        if ($cards->count() === 1 && ! $request->boolean('info')) {
            $card = $cards->first();
            $inStock = $card['items']->filter(fn ($i) => (int) $i->available > 0)->values();
            $itemId = $highlightItemId && $inStock->contains('id', $highlightItemId)
                ? $highlightItemId
                : ($inStock->count() === 1 ? $inStock->first()->id : null);
            $p = $card['product'];

            return redirect()->route('sale.quick', array_filter([
                'q' => $p->article ?: trim($p->brand.' '.$p->model),
                'item' => $itemId,
                'scan' => $raw,
            ]));
        }

        return view('scan.sneakers', [
            'raw' => $raw,
            'code' => $code,
            'mark' => $mark,
            'markHr' => $mark ? MarkCode::humanReadable(MarkCode::normalize($mark->code)) : (MarkCode::looksLike(MarkCode::normalize($raw)) ? MarkCode::humanReadable(MarkCode::normalize($raw)) : null),
            'cards' => $cards,
            'similar' => $found['similar'],
            'highlightItemId' => $highlightItemId,
            'seesCost' => Sneakers::seesCost(),
            'categoryOptions' => ProductClassifier::categoryOptions(),
            'genderOptions' => ProductClassifier::genderOptions(),
            'seasonOptions' => ProductClassifier::seasonOptions(),
        ]);
    }
}
