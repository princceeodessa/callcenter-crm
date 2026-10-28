<?php

namespace App\Support\Warehouse;

use App\Models\StockMark;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use App\Support\Marking\MarkCode;
use Illuminate\Support\Collection;

/**
 * Код со сканера → товары склада (общий поиск «Скана» и «Приёмки»): код «Честного знака» (конкретная пара), GTIN из кода ЧЗ,
 * артикул со штрихкода ценника, затем похожие (артикул / модель / название содержат введённое). Ввод в русской раскладке
 * исправляется. Ничего не создаёт: у пары без карточки модели — несохранённая карточка.
 */
class CodeLookup
{
    /**
     * @return array{raw: string, code: string, mark: ?StockMark, products: Collection<int, WarehouseProduct>, highlight: ?int, similar: bool}
     */
    public static function find(int $accId, string $raw, bool $similar = true): array
    {
        $raw = trim($raw);
        $code = MarkCode::fromRussianLayout($raw);
        $mark = null;
        $products = collect();
        $highlight = null;
        $bySimilar = false;

        if ($code !== '') {
            // 1. Код «Честного знака» → конкретная пара.
            $markCode = MarkCode::normalize($raw);
            if (MarkCode::looksLike($markCode)) {
                $mark = self::findMark($accId, $markCode);
                if ($mark?->item) {
                    $highlight = $mark->item->id;
                    $products = self::productsForItem($accId, $mark->item);
                }
            }

            // 2. GTIN / EAN (13–14 цифр) → код ЧЗ с таким GTIN.
            if ($products->isEmpty() && ! $mark && preg_match('~^\d{13,14}$~', $code)) {
                $gtin = str_pad($code, 14, '0', STR_PAD_LEFT);
                $byGtin = StockMark::where('account_id', $accId)->where('code', 'like', '01'.$gtin.'21%')->with('item')->first();
                if ($byGtin?->item) {
                    $products = self::productsForItem($accId, $byGtin->item);
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
            if ($similar && $products->isEmpty() && ! $mark && mb_strlen($code) >= 3) {
                $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $code).'%';
                $products = WarehouseProduct::where('account_id', $accId)
                    ->where(fn ($q) => $q->where('article', 'like', $like)->orWhere('model', 'like', $like)->orWhere('custom_name', 'like', $like))
                    ->orderBy('brand')->orderBy('model')->limit(20)->get();
                $bySimilar = $products->isNotEmpty();
            }
        }

        return ['raw' => $raw, 'code' => $code, 'mark' => $mark, 'products' => $products, 'highlight' => $highlight, 'similar' => $bySimilar];
    }

    /** Размеры модели по возрастанию (42 < 42.5 < 43) */
    public static function items(int $accId, WarehouseProduct $p): Collection
    {
        return WarehouseItem::where('account_id', $accId)->where('brand', $p->brand)->where('model', $p->model)->get()
            ->sort(fn ($a, $b) => strnatcmp((string) $a->size, (string) $b->size))->values();
    }

    /** Точное совпадение кода, иначе — по GTIN+серийному (сканер мог потерять криптохвост). */
    private static function findMark(int $accId, string $code): ?StockMark
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
    private static function productsForItem(int $accId, WarehouseItem $item): Collection
    {
        $product = WarehouseProduct::where('account_id', $accId)->where('brand', $item->brand)->where('model', $item->model)->first()
            // карточки модели ещё нет — показываем размеры без неё (ничего не создаём на GET)
            ?? new WarehouseProduct(['account_id' => $accId, 'brand' => $item->brand, 'model' => $item->model]);

        return collect([$product]);
    }
}
