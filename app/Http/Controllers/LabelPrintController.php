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

        // Цена продажи правится прямо здесь (как на складе — только руководитель) и сохраняется
        // в позицию склада: ценник, «Быстрая продажа» и склад показывают одну и ту же цену.
        $isHead = $user->role === 'sneaker_head';
        $pricesSaved = $isHead && $request->isMethod('post') ? $this->savePrices($request, $items) : 0;

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
        $marksSaved = 0;
        $attachItem = null;
        // Текст слева на этикетке ЧЗ (пусто — собираем из карточки товара), копии, «сразу печатать» после фото.
        $labelText = trim((string) $request->input('label_text', ''));
        $markCopies = max(1, min(50, (int) $request->input('mark_copies', 1)));

        if ($type === 'mark') {
            // Коды со склада (по выбранным товарам) + вставленные вручную / из файла ЧЗ.
            $selectedMarks = collect((array) $request->input('marks', []))->map(fn ($v) => (int) $v);
            $marks = $items->isEmpty() ? collect() : StockMark::where('account_id', $accId)
                ->whereIn('warehouse_item_id', $items->pluck('id'))
                ->where('status', 'in_stock')
                ->orderBy('warehouse_item_id')->orderBy('id')
                ->get();
            $useAllMarks = ! $request->has('marks_sent');

            // Размер, к которому относятся вставленные / распознанные с фото коды.
            $attachItem = $items->firstWhere('id', (int) $request->input('attach_item'));

            // Коды со вставки/фото: разбираем, при желании сохраняем к размеру (до вывода списка склада,
            // чтобы сохранённые сразу попали в него и не напечатались дважды).
            $pastedCodes = [];
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
                $pastedCodes[$code] = true;   // один и тот же код — одна этикетка
            }
            $pastedCodes = array_keys($pastedCodes);

            // Что CRM уже знает об этих кодах: продан / числится за другой парой.
            $known = $pastedCodes === [] ? collect() : StockMark::where('account_id', $accId)
                ->whereIn('code', $pastedCodes)->with('item:id,brand,model,size')->get()->keyBy('code');

            if ($attachItem && $request->isMethod('post') && $request->boolean('attach_save')) {
                foreach ($pastedCodes as $code) {
                    if ($known->has($code)) {
                        continue;
                    }
                    $known[$code] = StockMark::create([
                        'account_id' => $accId,
                        'warehouse_item_id' => $attachItem->id,
                        'code' => $code,
                        'status' => 'in_stock',
                    ]);
                    $marksSaved++;
                }
                if ($marksSaved > 0) {
                    $marks = StockMark::where('account_id', $accId)
                        ->whereIn('warehouse_item_id', $items->pluck('id'))
                        ->where('status', 'in_stock')
                        ->orderBy('warehouse_item_id')->orderBy('id')
                        ->get();
                }
            }

            $printed = [];
            foreach ($marks as $mark) {
                if (! $useAllMarks && ! $selectedMarks->contains($mark->id) && ! in_array(MarkCode::normalize($mark->code), $pastedCodes, true)) {
                    continue;
                }
                $code = MarkCode::normalize($mark->code);
                $item = $items->firstWhere('id', $mark->warehouse_item_id);
                $labels->push($this->markLabel($code, $item, $products, $labelText));
                $printed[$code] = true;
            }

            foreach ($pastedCodes as $code) {
                $pasted[] = $code;
                if (isset($printed[$code])) {
                    continue;
                }
                $mark = $known->get($code);
                $item = $attachItem ?? ($mark?->item ? ($items->firstWhere('id', $mark->warehouse_item_id) ?? $mark->item) : null);
                $labels->push($this->markLabel($code, $item, $products, $labelText));
                $printed[$code] = true;
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
            // GS в поле показываем как <GS>: невидимый символ в textarea легко теряется при копировании.
            'pastedCodes' => str_replace(MarkCode::GS, '<GS>', implode("\n", $pasted)),
            'marksSaved' => $marksSaved,
            'attachItemId' => $attachItem?->id,
            'labelText' => $labelText,
            'markCopies' => $markCopies,
            'pastedInvalid' => $pastedInvalid,
            'productIdsCsv' => $products->pluck('id')->implode(','),
            'isHead' => $isHead,
            'pricesSaved' => $pricesSaved,
            'driverMb' => is_file(self::driverPath()) ? max(1, (int) round(filesize(self::driverPath()) / 1048576)) : null,
        ]);
    }

    /**
     * «Цена на все размеры» (p_all) перекрывает цены по строкам (p[id]). Пустое поле — без изменений.
     * Сохраняем только реально изменившиеся цены.
     */
    private function savePrices(Request $request, Collection $items): int
    {
        $all = $this->parsePrice($request->input('p_all'));
        $perItem = (array) $request->input('p', []);
        $saved = 0;

        foreach ($items as $item) {
            $new = $all ?? $this->parsePrice($perItem[$item->id] ?? null);
            if ($new === null) {
                continue;
            }
            if ($item->sale_price === null || round((float) $item->sale_price, 2) !== $new) {
                $item->sale_price = $new;
                $item->save();
                $saved++;
            }
        }

        return $saved;
    }

    /** «12 990», «12990,50», «12 990 ₽» → 12990.5; мусор и отрицательные — null. */
    private function parsePrice(mixed $raw): ?float
    {
        $text = str_replace([' ', "\u{00A0}", '₽', ','], ['', '', '', '.'], trim((string) $raw));
        if ($text === '' || ! is_numeric($text)) {
            return null;
        }
        $value = round((float) $text, 2);

        return $value >= 0 && $value <= 10_000_000 ? $value : null;
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

    private function markLabel(string $code, ?WarehouseItem $item, Collection $products, string $text = ''): array
    {
        $product = $this->productFor($item, $products);
        $parsed = MarkCode::parse($code);

        // «Кроссовки NIKE Cortez Textile, арт. DZ2795-702, размер 42» — как на этикетке «Честного знака».
        if (! $item && ! $product && $products->count() === 1) {
            $product = $products->first();   // страница открыта для одной модели — описание берём из неё
        }
        $desc = $text;
        if ($desc === '' && ($item || $product)) {
            $name = $product?->display_name ?: trim($item->brand.' '.$item->model);
            $desc = 'Кроссовки '.$name
                .($product && $product->article ? ', арт. '.$product->article : '')
                .($item && (string) $item->size !== '' ? ', размер '.$item->size : '');
        }

        return [
            'desc' => $desc,
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
