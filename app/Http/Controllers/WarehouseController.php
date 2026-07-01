<?php

namespace App\Http\Controllers;

use App\Models\StockMark;
use App\Models\StockMovement;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use App\Models\WarehouseProductPhoto;
use App\Services\Warehouse\WarehouseService;
use App\Support\Warehouse\Code128;
use App\Support\Warehouse\ProductClassifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class WarehouseController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $q = trim($request->string('q')->toString());

        $items = WarehouseItem::query()
            ->where('account_id', $user->account_id)
            ->when($q !== '', fn ($query) => $query->where(fn ($qq) => $qq
                ->where('brand', 'like', "%{$q}%")
                ->orWhere('model', 'like', "%{$q}%")
                ->orWhere('size', 'like', "%{$q}%")))
            ->orderBy('brand')->orderBy('model')->orderBy('size')
            ->get();

        $movements = StockMovement::query()
            ->with(['item:id,brand,model,size', 'user:id,name'])
            ->where('account_id', $user->account_id)
            ->orderByDesc('id')
            ->limit(40)
            ->get();

        $totalUnits = (int) $items->sum('quantity');
        $stockValue = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) ($i->sale_price ?? 0));
        $isHead = $this->isHead();

        // Опции таксономии + текущие фильтры
        $categoryOptions = ProductClassifier::categoryOptions();
        $genderOptions = ProductClassifier::genderOptions();
        $seasonOptions = ProductClassifier::seasonOptions();
        $filterCategory = trim((string) $request->query('cat'));
        $filterGender = trim((string) $request->query('sex'));
        $filterSeason = trim((string) $request->query('sea'));

        // Группируем размеры под одним товаром (бренд + модель).
        // Заодно подмешиваем warehouse_products (кастомное имя, фото).
        $accId = $user->account_id;
        $products = $items
            ->groupBy(fn ($i) => mb_strtolower(trim(($i->brand ?? '').'|'.($i->model ?? ''))))
            ->map(function ($group) use ($accId) {
                $first = $group->first();
                $brand = (string) ($first->brand ?? '');
                $model = (string) ($first->model ?? '');
                $product = WarehouseProduct::with('photos')->firstOrCreate(
                    ['account_id' => $accId, 'brand' => $brand, 'model' => $model],
                    []
                );
                $autoName = trim($brand.' '.$model) ?: 'Без названия';
                $gallery = $product->gallery;
                $article = (string) $product->article;

                return [
                    'entity' => $product,
                    'name' => $product->custom_name ?: $autoName,
                    'auto_name' => $autoName,
                    'custom_name' => $product->custom_name,
                    'image_url' => $gallery[0]['url'] ?? null,
                    'gallery' => $gallery,
                    'article' => $article,
                    'barcode_svg' => $article !== '' ? Code128::svg($article, 30, 1) : '',
                    'category' => $product->category,
                    'gender' => $product->gender,
                    'season' => $product->season,
                    'brand' => $brand,
                    'model' => $model,
                    'sizes' => $group->sortBy('size', SORT_NATURAL)->values(),
                    'total' => (int) $group->sum('quantity'),
                    'available' => (int) $group->sum(fn ($i) => $i->available),
                    'reserved' => (int) $group->sum('reserved'),
                    'value' => (float) $group->sum(fn ($i) => (int) $i->quantity * (float) ($i->sale_price ?? 0)),
                    'low' => $group->contains(fn ($i) => $i->is_low),
                ];
            })
            ->sortBy('name')
            ->values();

        if ($filterCategory !== '' || $filterGender !== '' || $filterSeason !== '') {
            $products = $products->filter(function ($p) use ($filterCategory, $filterGender, $filterSeason) {
                if ($filterCategory !== '' && (string) $p['category'] !== $filterCategory) return false;
                if ($filterGender !== '' && (string) $p['gender'] !== $filterGender) return false;
                if ($filterSeason !== '' && (string) $p['season'] !== $filterSeason) return false;
                return true;
            })->values();
        }

        return view('warehouse.index', compact(
            'products', 'movements', 'q', 'totalUnits', 'stockValue', 'isHead',
            'categoryOptions', 'genderOptions', 'seasonOptions',
            'filterCategory', 'filterGender', 'filterSeason'
        ));
    }

    public function store(Request $request, WarehouseService $warehouse)
    {
        $user = Auth::user();
        $data = $request->validate([
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);

        $item = WarehouseItem::firstOrNew([
            'account_id' => $user->account_id,
            'brand' => (string) ($data['brand'] ?? ''),
            'model' => (string) ($data['model'] ?? ''),
            'size' => (string) ($data['size'] ?? ''),
        ]);
        $existed = $item->exists;
        if (array_key_exists('sale_price', $data)) {
            $item->sale_price = $data['sale_price'];
        }
        $item->low_stock_threshold = (int) ($data['low_stock_threshold'] ?? $item->low_stock_threshold ?? 0);
        if (! $existed) {
            $item->quantity = 0;
        }
        $item->save();

        $qty = (int) $data['quantity'];
        if ($qty > 0) {
            $warehouse->replenish($item, $qty, $existed ? 'Добавление к позиции (вручную)' : 'Начальный остаток (вручную)');
        }

        return redirect()->route('warehouse.index')->with('status', $existed ? 'Позиция пополнена.' : 'Позиция добавлена.');
    }

    public function update(Request $request, WarehouseItem $item, WarehouseService $warehouse)
    {
        $user = Auth::user();
        abort_unless($item->account_id === $user->account_id, 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Цену продажи и порог меняет только руководитель отдела.
        if ($this->isHead()) {
            $item->sale_price = $data['sale_price'] ?? null;
            $item->low_stock_threshold = (int) ($data['low_stock_threshold'] ?? 0);
        }
        $item->notes = $data['notes'] ?? null;
        $item->save();

        $warehouse->setQuantity($item, (int) $data['quantity'], 'Ручная корректировка остатка');

        return redirect()->route('warehouse.index')->with('status', 'Позиция обновлена.');
    }

    public function replenish(Request $request, WarehouseItem $item, WarehouseService $warehouse)
    {
        $user = Auth::user();
        abort_unless($item->account_id === $user->account_id, 403);

        $data = $request->validate([
            'delta' => ['required', 'integer', 'not_in:0', 'min:-1000000', 'max:1000000'],
        ]);

        $warehouse->replenish($item, (int) $data['delta'], $data['delta'] > 0 ? 'Пополнение (вручную)' : 'Списание (вручную)');

        return redirect()->route('warehouse.index')->with('status', 'Остаток изменён.');
    }

    public function destroy(WarehouseItem $item)
    {
        $user = Auth::user();
        abort_unless($item->account_id === $user->account_id, 403);
        abort_unless($this->isHead(), 403);

        $item->delete();

        return redirect()->route('warehouse.index')->with('status', 'Позиция удалена.');
    }

    /** Изменить отображаемое имя и/или артикул товара. */
    public function updateProduct(Request $request, WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        $data = $request->validate([
            'custom_name' => ['nullable', 'string', 'max:255'],
            'article' => ['nullable', 'string', 'max:64'],
            'category' => ['nullable', 'in:sport,casual,premium,winter,kids'],
            'gender' => ['nullable', 'in:male,female,unisex,kids'],
            'season' => ['nullable', 'in:summer,winter,demi'],
        ]);

        if ($request->has('custom_name')) {
            $name = trim((string) ($data['custom_name'] ?? ''));
            $product->custom_name = $name !== '' ? $name : null;
        }
        if ($request->has('article')) {
            $article = trim((string) ($data['article'] ?? ''));
            $product->article = $article !== '' ? $article : WarehouseProduct::nextArticle($product->account_id);
        }
        foreach (['category', 'gender', 'season'] as $tax) {
            if ($request->has($tax)) {
                $v = trim((string) ($data[$tax] ?? ''));
                $product->{$tax} = $v !== '' ? $v : null;
            }
        }
        $product->save();

        return back()->with('status', 'Товар обновлён.');
    }

    /** Загрузить фото товара — добавляется в галерею (не заменяет). */
    public function uploadProductPhoto(Request $request, WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $data['photo']->store('warehouse_products/'.$product->account_id, 'public');
        $maxSort = (int) $product->photos()->max('sort');
        $product->photos()->create(['path' => $path, 'sort' => $maxSort + 1]);

        // Если legacy image_path пустой — заполним первым фото для обратной совместимости.
        if (empty($product->image_path)) {
            $product->image_path = $path;
            $product->save();
        }

        return back()->with('status', 'Фото добавлено.');
    }

    /** Удалить одно фото из галереи (или legacy image_path, если id пустой). */
    public function deleteProductPhoto(Request $request, WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        $photoId = (int) $request->input('photo_id');
        if ($photoId > 0) {
            $photo = $product->photos()->whereKey($photoId)->first();
            if ($photo) {
                Storage::disk('public')->delete($photo->path);
                // Если удаляем именно то, что дублировано в image_path — очистим и его.
                if ($product->image_path === $photo->path) {
                    $product->image_path = optional($product->photos()->where('id', '!=', $photoId)->first())->path;
                    $product->save();
                }
                $photo->delete();
            }
        } elseif ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
            $product->image_path = null;
            $product->save();
        }

        return back()->with('status', 'Фото удалено.');
    }

    /** Печатная этикетка (артикул + Code128). */
    public function label(WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        return view('warehouse.label', [
            'product' => $product,
            'barcode_svg' => Code128::svg($product->article ?: '', 60, 2),
            'display_name' => $product->display_name,
        ]);
    }

    // ==================== ПРИЁМКА (ТСД / сканер) ====================

    /** Страница приёмки со сканером-input. */
    public function receivingForm(Request $request)
    {
        $user = Auth::user();
        $recent = StockMovement::with('item:id,brand,model,size')
            ->where('account_id', $user->account_id)
            ->whereIn('type', ['in', 'replenish'])
            ->orderByDesc('id')->limit(15)->get();
        $lastCode = $request->session()->get('receive_last', '');
        $lastResult = $request->session()->get('receive_result', '');

        return view('warehouse.receiving', compact('recent', 'lastCode', 'lastResult'));
    }

    /** Приём кода со сканера: находим товар по артикулу (K-XXXXX), +qty в остаток; либо принимаем как код маркировки. */
    public function receivingScan(Request $request, WarehouseService $warehouse)
    {
        $user = Auth::user();
        $data = $request->validate([
            'code' => ['required', 'string', 'max:512'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'mode' => ['nullable', 'in:article,mark'],
        ]);
        $code = trim($data['code']);
        $qty = (int) ($data['quantity'] ?? 1);
        $mode = $data['mode'] ?? 'article';
        $result = '';

        if ($mode === 'mark') {
            // Приём кода маркировки — привязываем к последней добавленной позиции (или показываем ошибку)
            $lastItemId = $request->session()->get('receive_target_item_id');
            if (! $lastItemId) {
                $result = 'MARK_NO_TARGET';
            } else {
                $item = WarehouseItem::where('account_id', $user->account_id)->find($lastItemId);
                if (! $item) {
                    $result = 'MARK_NO_TARGET';
                } else {
                    $exists = StockMark::where('account_id', $user->account_id)->where('code', $code)->exists();
                    if ($exists) {
                        $result = 'MARK_DUP';
                    } else {
                        StockMark::create([
                            'account_id' => $user->account_id,
                            'warehouse_item_id' => $item->id,
                            'code' => $code,
                            'status' => 'in_stock',
                        ]);
                        $result = 'MARK_OK:'.$item->display_name;
                    }
                }
            }
        } else {
            // Поиск по артикулу — если формат K-XXXXX, ищем продукт и берём первую позицию.
            $product = WarehouseProduct::where('account_id', $user->account_id)->where('article', $code)->first();
            if (! $product) {
                // Может это сам штрих-код товара как есть — пробуем по customName / brand model
                $result = 'NOT_FOUND';
            } else {
                $items = WarehouseItem::where('account_id', $user->account_id)
                    ->where('brand', $product->brand)->where('model', $product->model)
                    ->orderBy('size')->get();
                if ($items->isEmpty()) {
                    $result = 'NO_SIZES:'.$product->display_name;
                } else {
                    // Приход в первую позицию (обычно у товара один размер, либо оператор потом уточнит).
                    $item = $items->first();
                    $warehouse->replenish($item, $qty, 'Приёмка ТСД ('.date('Y-m-d H:i').')');
                    $request->session()->put('receive_target_item_id', $item->id);
                    $result = 'RECEIVED:'.$product->display_name.' · р. '.$item->size.' × +'.$qty;
                }
            }
        }

        $request->session()->flash('receive_last', $code);
        $request->session()->flash('receive_result', $result);

        return back();
    }

    // ==================== КОДЫ МАРКИРОВКИ («Честный знак») ====================

    /** Добавить коды маркировки к позиции склада (по одному коду в строке). */
    public function addItemMarks(Request $request, WarehouseItem $item)
    {
        $user = Auth::user();
        abort_unless($item->account_id === $user->account_id, 403);

        $data = $request->validate([
            'codes' => ['required', 'string', 'max:100000'],
        ]);
        $codes = array_values(array_filter(array_map('trim', preg_split('/\r?\n/', $data['codes']))));
        $added = 0;
        $dup = 0;
        foreach ($codes as $code) {
            if ($code === '') {
                continue;
            }
            if (StockMark::where('account_id', $user->account_id)->where('code', $code)->exists()) {
                $dup++;
                continue;
            }
            StockMark::create([
                'account_id' => $user->account_id,
                'warehouse_item_id' => $item->id,
                'code' => $code,
                'status' => 'in_stock',
            ]);
            $added++;
        }
        return back()->with('status', "Добавлено кодов: {$added}, дубликатов пропущено: {$dup}.");
    }

    /** Удалить один код маркировки. */
    public function deleteMark(StockMark $mark)
    {
        $user = Auth::user();
        abort_unless($mark->account_id === $user->account_id, 403);
        $mark->delete();
        return back()->with('status', 'Код удалён.');
    }

    // ==================== МАССОВЫЙ ИМПОРТ ====================

    /** Массовый импорт: показать форму. */
    public function importForm()
    {
        return view('warehouse.import');
    }

    /**
     * Массовый импорт: разобрать текст и залить позиции на склад.
     * Формат строки: "БРЕНД МОДЕЛЬ ... - размер1,размер2,..."  или  "БРЕНД МОДЕЛЬ ... -размер"
     */
    public function importRun(Request $request, WarehouseService $warehouse)
    {
        $user = Auth::user();
        $data = $request->validate([
            'raw' => ['required', 'string', 'max:200000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $qty = (int) ($data['quantity'] ?? 1);

        $rows = $this->parseImportText($data['raw']);
        $positionsCreated = 0;
        $pairsAdded = 0;
        $errors = [];
        $note = 'Импорт списка ('.date('Y-m-d H:i').')';

        foreach ($rows as $row) {
            foreach ($row['sizes'] as $size) {
                try {
                    $item = WarehouseItem::firstOrNew([
                        'account_id' => $user->account_id,
                        'brand' => $row['brand'],
                        'model' => $row['model'],
                        'size' => (string) $size,
                    ]);
                    if (! $item->exists) {
                        $item->quantity = 0;
                        $item->save();
                        $positionsCreated++;
                    }
                    $warehouse->replenish($item, $qty, $note);
                    $pairsAdded += $qty;
                } catch (\Throwable $e) {
                    $errors[] = $row['brand'].' | '.$row['model'].' | '.$size.' — '.$e->getMessage();
                }
            }
        }

        return redirect()->route('warehouse.index')->with('status',
            'Импорт: создано позиций '.$positionsCreated.', оприходовано пар '.$pairsAdded.
            (count($errors) ? ' · ошибок '.count($errors) : ''));
    }

    /** Парсер текста импорта: возвращает массив ['brand','model','sizes'=>[]]. */
    private function parseImportText(string $raw): array
    {
        $multiWordBrands = ['NEW BALANCE'];
        $lines = preg_split('/\r?\n/', $raw);
        $rows = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            // Разделитель "-" перед размерами (может быть с пробелами вокруг). Ищем последний "-" со стороны цифр.
            if (! preg_match('/^(.*?)[\s]*[-—]\s*(?:рр\s*)?([\d.,\s]+)$/u', $line, $m)) {
                continue;
            }
            $name = trim(preg_replace('/\s+/u', ' ', $m[1]));
            $sizesRaw = trim($m[2]);
            $sizes = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', $sizesRaw))));
            if (empty($sizes) || $name === '') {
                continue;
            }
            $upper = mb_strtoupper($name);
            $brand = '';
            $model = $name;
            foreach ($multiWordBrands as $mb) {
                if (str_starts_with($upper, $mb.' ')) {
                    $brand = $mb;
                    $model = trim(mb_substr($name, mb_strlen($mb) + 1));
                    break;
                }
            }
            if ($brand === '') {
                $parts = explode(' ', $name, 2);
                $brand = mb_strtoupper($parts[0] ?? '');
                $model = trim($parts[1] ?? '');
            }
            $rows[] = ['brand' => $brand, 'model' => $model, 'sizes' => $sizes];
        }
        return $rows;
    }

    public function export(Request $request)
    {
        $user = Auth::user();
        $items = WarehouseItem::where('account_id', $user->account_id)
            ->orderBy('brand')->orderBy('model')->orderBy('size')->get();

        $rows = [['Бренд', 'Модель', 'Размер', 'Остаток', 'Резерв', 'Доступно', 'Себестоимость', 'Цена продажи', 'Стоимость (прод.)']];
        foreach ($items as $i) {
            $rows[] = [$i->brand, $i->model, $i->size, $i->quantity, $i->reserved, $i->available, $i->avg_cost, $i->sale_price, (int) $i->quantity * (float) ($i->sale_price ?? 0)];
        }

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF"; // UTF-8 BOM для корректной кириллицы в Excel
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, 'sklad-krossovki-'.date('Y-m-d').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function isHead(): bool
    {
        return Auth::user()?->role === 'sneaker_head';
    }
}
