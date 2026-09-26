<?php

namespace App\Http\Controllers;

use App\Models\StockMark;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use App\Support\Warehouse\Code128;
use App\Support\Marking\MarkCode;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Печать на термопринтер этикеток (Xprinter XP-365B и подобные, 203 dpi):
 * ценники, товарные этикетки со штрихкодом и этикетки с кодом «Честного знака».
 *
 * Каждая этикетка — отдельная страница ровно под размер наклейки (@page),
 * поэтому печатается из браузера без полей и без смещений, если в драйвере
 * заведён тот же размер бумаги.
 */
class LabelPrintController extends Controller
{
    /** Размеры наклеек (мм). Первый — по умолчанию: рулон, который стоит в магазине (60 × 40). */
    public const FORMATS = [
        '60x40' => ['w' => 60, 'h' => 40, 'label' => '60 × 40 мм'],
        '58x40' => ['w' => 58, 'h' => 40, 'label' => '58 × 40 мм'],
        '58x30' => ['w' => 58, 'h' => 30, 'label' => '58 × 30 мм'],
        '58x60' => ['w' => 58, 'h' => 60, 'label' => '58 × 60 мм'],
        '43x25' => ['w' => 43, 'h' => 25, 'label' => '43 × 25 мм'],
        '40x30' => ['w' => 40, 'h' => 30, 'label' => '40 × 30 мм'],
    ];

    public const TYPES = [
        'price' => 'Ценник',
        'product' => 'Этикетка со штрихкодом',
        'mark' => 'Честный знак (DataMatrix)',
    ];

    /** Не даём одним кликом отправить на принтер рулон целиком. */
    private const MAX_LABELS = 500;

    /**
     * Драйвер XP-365B (официальный установщик Seagull с xprinters.ru, 40 МБ) — лежит на сервере
     * вне git, отдаётся только сотрудникам кроссовок, чтобы поставить принтер на любой компьютер.
     */
    public const DRIVER_FILE = 'drivers/xprinter/For_365B_237B_370B.zip';

    public static function driverPath(): string
    {
        return storage_path('app/'.self::DRIVER_FILE);
    }

    public function driver()
    {
        abort_unless(is_file(self::driverPath()), 404, 'Файл драйвера не загружен на сервер.');

        return response()->download(self::driverPath(), 'Xprinter_XP-365B_driver.zip');
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;

        $type = array_key_exists($request->input('type'), self::TYPES) ? $request->input('type') : 'price';
        $formatKey = array_key_exists($request->input('format'), self::FORMATS) ? $request->input('format') : array_key_first(self::FORMATS);
        $format = self::FORMATS[$formatKey];
        $showPrice = $request->input('show_price', '1') === '1';

        $productIds = collect(explode(',', (string) $request->input('products', '')))
            ->merge((array) $request->input('product_ids', []))
            ->map(fn ($v) => (int) $v)->filter()->unique()->values();
        if ($request->filled('product')) {
            $productIds->push((int) $request->input('product'));
        }

        $products = WarehouseProduct::where('account_id', $accId)->whereIn('id', $productIds->unique())->get();
        $items = $this->itemsOf($accId, $products);

        // Сколько копий на каждый размер: из формы (c[id]=n), иначе 1.
        $copiesInput = (array) $request->input('c', []);
        $copies = $items->mapWithKeys(fn (WarehouseItem $i) => [
            $i->id => array_key_exists($i->id, $copiesInput) ? max(0, min(99, (int) $copiesInput[$i->id])) : 1,
        ]);

        $labels = collect();
        $marks = collect();
        $useAllMarks = true;
        $selectedMarks = collect();
        $pasted = [];
        $pastedInvalid = 0;

        if ($type === 'mark') {
            // Коды со склада (по выбранным товарам) + вставленные вручную / из файла ЧЗ.
            $selectedMarks = collect((array) $request->input('marks', []))->map(fn ($v) => (int) $v);
            $marks = $items->isEmpty() ? collect() : StockMark::where('account_id', $accId)
                ->whereIn('warehouse_item_id', $items->pluck('id'))
                ->where('status', 'in_stock')
                ->orderBy('warehouse_item_id')->orderBy('id')
                ->get();
            $useAllMarks = ! $request->has('marks_sent');

            foreach ($marks as $mark) {
                if (! $useAllMarks && ! $selectedMarks->contains($mark->id)) {
                    continue;
                }
                $item = $items->firstWhere('id', $mark->warehouse_item_id);
                $labels->push($this->markLabel(MarkCode::normalize($mark->code), $item, $products));
            }

            foreach (preg_split('~\R~u', (string) $request->input('codes', '')) as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $code = MarkCode::normalize($line);
                if (! MarkCode::looksLike($code)) {
                    $pastedInvalid++;

                    continue;
                }
                $pasted[] = $code;
                $labels->push($this->markLabel($code, null, $products));
            }
        } else {
            foreach ($items as $item) {
                $product = $this->productFor($item, $products);
                $label = [
                    'name' => $product?->display_name ?: trim($item->brand.' '.$item->model),
                    'brand' => (string) $item->brand,
                    'size' => (string) $item->size,
                    'price' => $item->sale_price !== null ? (float) $item->sale_price : null,
                    'article' => (string) ($product?->article ?? ''),
                    'barcode' => $product && $product->article ? Code128::svg($product->article, 60, 2) : null,
                ];
                $label['barcode_mm'] = $label['barcode'] ? $this->barcodeWidthMm($label['barcode'], $format['w'] - 4) : null;
                for ($n = 0; $n < $copies[$item->id]; $n++) {
                    $labels->push($label);
                }
            }
        }

        $markRows = $marks->map(function (StockMark $mark) use ($items, $useAllMarks, $selectedMarks) {
            $item = $items->firstWhere('id', $mark->warehouse_item_id);

            return [
                'id' => $mark->id,
                'item' => $item ? trim($item->brand.' '.$item->model).' · р. '.$item->size : '—',
                'hr' => MarkCode::humanReadable(MarkCode::normalize($mark->code)),
                'checked' => $useAllMarks || $selectedMarks->contains($mark->id),
            ];
        });

        $truncated = $labels->count() > self::MAX_LABELS;
        $labels = $labels->take(self::MAX_LABELS)->values();

        return view('print.labels', [
            'type' => $type,
            'types' => self::TYPES,
            'formatKey' => $formatKey,
            'format' => $format,
            'formats' => self::FORMATS,
            'showPrice' => $showPrice,
            'products' => $products,
            'items' => $items,
            'copies' => $copies,
            'marks' => $marks,
            'markRows' => $markRows,
            'labels' => $labels,
            'truncated' => $truncated,
            'maxLabels' => self::MAX_LABELS,
            'pastedCodes' => implode("\n", $pasted),
            'pastedInvalid' => $pastedInvalid,
            'productIdsCsv' => $products->pluck('id')->implode(','),
            'driverMb' => is_file(self::driverPath()) ? max(1, (int) round(filesize(self::driverPath()) / 1048576)) : null,
        ]);
    }

    /** @return Collection<int, WarehouseItem> все размеры выбранных товаров */
    private function itemsOf(int $accId, Collection $products): Collection
    {
        if ($products->isEmpty()) {
            return collect();
        }

        return WarehouseItem::where('account_id', $accId)
            ->where(function ($q) use ($products) {
                foreach ($products as $p) {
                    $q->orWhere(fn ($w) => $w->where('brand', $p->brand)->where('model', $p->model));
                }
            })
            ->get()
            ->sort(fn ($a, $b) => [mb_strtolower($a->brand), mb_strtolower($a->model)] <=> [mb_strtolower($b->brand), mb_strtolower($b->model)]
                ?: strnatcmp((string) $a->size, (string) $b->size))
            ->values();
    }

    private function productFor(?WarehouseItem $item, Collection $products): ?WarehouseProduct
    {
        if (! $item) {
            return null;
        }
        $key = mb_strtolower(trim($item->brand.'|'.$item->model));

        return $products->first(fn ($p) => mb_strtolower(trim($p->brand.'|'.$p->model)) === $key);
    }

    /**
     * Ширина штрихкода в мм так, чтобы один модуль = 2 точки принтера 203 dpi (0,25 мм):
     * растянутый «как получится» Code128 на термопечати даёт неровные штрихи и плохо читается.
     * Если на наклейку не помещается — ужимаем до ширины наклейки.
     */
    private function barcodeWidthMm(string $svg, float $maxMm): float
    {
        if (! preg_match('~viewBox="0 0 ([\d.]+) ~', $svg, $m)) {
            return $maxMm;
        }
        $modules = (float) $m[1] / 2;

        return round(min($modules * 0.25, $maxMm), 2);
    }

    private function markLabel(string $code, ?WarehouseItem $item, Collection $products): array
    {
        $product = $this->productFor($item, $products);
        $parsed = MarkCode::parse($code);

        return [
            'code' => $code,
            'dm' => MarkCode::forDataMatrix($code),
            'gtin' => $parsed['gtin'] ?? '',
            'serial' => $parsed['serial'] ?? '',
            'has_crypto' => $parsed['has_crypto'] ?? false,
            'name' => $item ? ($product?->display_name ?: trim($item->brand.' '.$item->model)) : '',
            'size' => $item ? (string) $item->size : '',
        ];
    }
}
