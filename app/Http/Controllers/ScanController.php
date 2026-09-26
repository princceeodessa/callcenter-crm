<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\StockMark;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use App\Support\Marking\MarkCode;
use App\Support\Warehouse\ArticleIdentity;
use App\Support\Warehouse\ProductClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Скан штрихкода сканером в любом месте CRM → карточка товара: размеры, остатки, цены,
 * «Продать» и «Печать». Понимает артикул со штрихкода ценника, код «Честного знака»
 * (находит конкретную пару) и GTIN; исправляет ввод в русской раскладке.
 */
class ScanController extends Controller
{
    public function show(Request $request)
    {
        $accId = Auth::user()->account_id;
        $raw = trim((string) $request->query('code', ''));
        $code = MarkCode::fromRussianLayout($raw);

        $mark = null;
        $products = collect();
        $highlightItemId = null;

        if ($code !== '') {
            // 1. Код «Честного знака» → конкретная пара.
            $markCode = MarkCode::normalize($raw);
            if (MarkCode::looksLike($markCode)) {
                $mark = $this->findMark($accId, $markCode);
                if ($mark?->item) {
                    $highlightItemId = $mark->item->id;
                    $products = $this->productsForItem($accId, $mark->item);
                }
            }

            // 2. GTIN / EAN (13–14 цифр) → код ЧЗ с таким GTIN.
            if ($products->isEmpty() && ! $mark && preg_match('~^\d{13,14}$~', $code)) {
                $gtin = str_pad($code, 14, '0', STR_PAD_LEFT);
                $byGtin = StockMark::where('account_id', $accId)->where('code', 'like', '01'.$gtin.'21%')->with('item')->first();
                if ($byGtin?->item) {
                    $products = $this->productsForItem($accId, $byGtin->item);
                }
            }

            // 3. Артикул (штрихкод на наших ценниках).
            if ($products->isEmpty() && ! $mark) {
                $article = ArticleIdentity::normalizeArticle($code);
                $products = WarehouseProduct::where('account_id', $accId)
                    ->where(fn ($q) => $q->where('article', $article)->orWhere('article', $code))
                    ->get();
            }

            // 4. Похожие: артикул/модель содержат введённое.
            if ($products->isEmpty() && ! $mark && mb_strlen($code) >= 3) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $code).'%';
                $products = WarehouseProduct::where('account_id', $accId)
                    ->where(fn ($q) => $q->where('article', 'like', $like)->orWhere('model', 'like', $like)->orWhere('custom_name', 'like', $like))
                    ->orderBy('brand')->orderBy('model')->limit(20)->get();
            }
        }

        $cards = $products->map(function (WarehouseProduct $p) use ($accId) {
            $items = WarehouseItem::where('account_id', $accId)->where('brand', $p->brand)->where('model', $p->model)->get()
                ->sort(fn ($a, $b) => strnatcmp((string) $a->size, (string) $b->size))->values();
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

        return view('scan.show', [
            'raw' => $raw,
            'code' => $code,
            'mark' => $mark,
            'markHr' => $mark ? MarkCode::humanReadable(MarkCode::normalize($mark->code)) : (MarkCode::looksLike(MarkCode::normalize($raw)) ? MarkCode::humanReadable(MarkCode::normalize($raw)) : null),
            'cards' => $cards,
            'highlightItemId' => $highlightItemId,
            'isHead' => Auth::user()->role === 'sneaker_head',
            'categoryOptions' => ProductClassifier::categoryOptions(),
            'genderOptions' => ProductClassifier::genderOptions(),
            'seasonOptions' => ProductClassifier::seasonOptions(),
        ]);
    }

    /** Точное совпадение кода, иначе — по GTIN+серийному (сканер мог потерять криптохвост). */
    private function findMark(int $accId, string $code): ?StockMark
    {
        $mark = StockMark::where('account_id', $accId)->where('code', $code)->with('item', 'deal')->first();
        if ($mark) {
            return $mark;
        }
        $p = MarkCode::parse($code);
        if (! $p) {
            return null;
        }

        return StockMark::where('account_id', $accId)
            ->where('code', 'like', '01'.$p['gtin'].'21'.str_replace(['%', '_'], ['\%', '\_'], $p['serial']).'%')
            ->with('item', 'deal')->first();
    }

    /** @return Collection<int, WarehouseProduct> */
    private function productsForItem(int $accId, WarehouseItem $item): Collection
    {
        $product = WarehouseProduct::where('account_id', $accId)->where('brand', $item->brand)->where('model', $item->model)->first()
            // карточки модели ещё нет — показываем размеры без неё (ничего не создаём на GET)
            ?? new WarehouseProduct(['account_id' => $accId, 'brand' => $item->brand, 'model' => $item->model]);

        return collect([$product]);
    }
}
