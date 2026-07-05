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
use Illuminate\Pagination\LengthAwarePaginator;
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
        $stockCostValue = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) ($i->avg_cost ?? 0));
        $isHead = $this->isHead();

        // Опции таксономии + текущие фильтры
        $categoryOptions = ProductClassifier::categoryOptions();
        $genderOptions = ProductClassifier::genderOptions();
        $seasonOptions = ProductClassifier::seasonOptions();
        $filterCategory = trim((string) $request->query('cat'));
        $filterGender = trim((string) $request->query('sex'));
        $filterSeason = trim((string) $request->query('sea'));
        $filterTag = trim((string) $request->query('tag'));
        $lowFilter = $request->query('low') == '1';

        // Все продукты одним запросом (фото сразу) — без N+1 на каждую карточку.
        $productRows = WarehouseProduct::with('photos')->where('account_id', $user->account_id)->get();
        $prodMap = [];
        $allTags = collect();
        foreach ($productRows as $p) {
            $prodMap[mb_strtolower(trim(($p->brand ?? '').'|'.($p->model ?? '')))] = $p;
            if (is_array($p->tags)) {
                foreach ($p->tags as $t) {
                    $allTags->push($t);
                }
            }
        }
        $allTags = $allTags->unique()->sort()->values();

        // Группируем размеры под одним товаром (бренд + модель).
        // Заодно подмешиваем warehouse_products (кастомное имя, фото).
        $accId = $user->account_id;
        $products = $items
            ->groupBy(fn ($i) => mb_strtolower(trim(($i->brand ?? '').'|'.($i->model ?? ''))))
            ->map(function ($group) use ($accId, &$prodMap) {
                $first = $group->first();
                $brand = (string) ($first->brand ?? '');
                $model = (string) ($first->model ?? '');
                $key = mb_strtolower(trim($brand.'|'.$model));
                $product = $prodMap[$key] ?? null;
                if (! $product) {
                    $product = WarehouseProduct::create(['account_id' => $accId, 'brand' => $brand, 'model' => $model]);
                    $product->setRelation('photos', collect());
                    $prodMap[$key] = $product;
                }
                $autoName = trim($brand.' '.$model) ?: 'Без названия';
                $gallery = $product->gallery;
                $article = (string) $product->article;

                // Общие цены по расцветке (если одинаковы у всех размеров)
                $costVals = $group->pluck('avg_cost')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->unique()->values();
                $priceVals = $group->pluck('sale_price')->filter(fn ($v) => $v !== null)->map(fn ($v) => (float) $v)->unique()->values();
                $costCommon = $costVals->count() === 1 ? $costVals[0] : null;
                $priceCommon = $priceVals->count() === 1 ? $priceVals[0] : null;
                $margin = ($costCommon && $priceCommon && $costCommon > 0)
                    ? (int) round(($priceCommon - $costCommon) / $costCommon * 100)
                    : null;

                return [
                    'entity' => $product,
                    'name' => $product->custom_name ?: $autoName,
                    'auto_name' => $autoName,
                    'custom_name' => $product->custom_name,
                    'image_url' => $gallery[0]['url'] ?? null,
                    'gallery' => $gallery,
                    'article' => $article,
                    'category' => $product->category,
                    'gender' => $product->gender,
                    'season' => $product->season,
                    'tags' => is_array($product->tags) ? $product->tags : [],
                    'cost_common' => $costCommon,
                    'price_common' => $priceCommon,
                    'margin' => $margin,
                    'mixed_prices' => $costVals->count() > 1 || $priceVals->count() > 1,
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

        // Сводка считается по всему складу (до фильтров)
        $productsCount = $products->count();
        $availableSum = (int) $products->sum('available');
        $lowCount = $products->filter(fn ($p) => $p['low'])->count();

        $filtered = $products;
        if ($filterCategory !== '' || $filterGender !== '' || $filterSeason !== '' || $filterTag !== '') {
            $filtered = $filtered->filter(function ($p) use ($filterCategory, $filterGender, $filterSeason, $filterTag) {
                if ($filterCategory !== '' && (string) $p['category'] !== $filterCategory) return false;
                if ($filterGender !== '' && (string) $p['gender'] !== $filterGender) return false;
                if ($filterSeason !== '' && (string) $p['season'] !== $filterSeason) return false;
                if ($filterTag !== '' && ! in_array($filterTag, $p['tags'] ?? [], true)) return false;
                return true;
            })->values();
        }
        if ($lowFilter) {
            $filtered = $filtered->filter(fn ($p) => $p['low'])->values();
        }

        // Пагинация: браузер не тянет сотни карточек разом.
        $perPage = 24;
        $page = LengthAwarePaginator::resolveCurrentPage();
        $productsPage = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage)->values(),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()]
        );

        return view('warehouse.index', compact(
            'productsPage', 'productsCount', 'availableSum', 'lowCount', 'lowFilter',
            'movements', 'q', 'totalUnits', 'stockValue', 'stockCostValue', 'isHead',
            'categoryOptions', 'genderOptions', 'seasonOptions',
            'filterCategory', 'filterGender', 'filterSeason', 'filterTag', 'allTags'
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
            'avg_cost' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        // Цены (закуп/продажа) и порог меняет только руководитель отдела.
        if ($this->isHead()) {
            $item->sale_price = $data['sale_price'] ?? null;
            if ($request->has('avg_cost')) {
                $item->avg_cost = $data['avg_cost'] ?? null;
            }
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

    /** Задать закуп/продажу сразу для всех размеров расцветки (только руководитель). */
    public function updateProductPrices(Request $request, WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);
        abort_unless($this->isHead(), 403);

        $data = $request->validate([
            'cost' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ]);

        $upd = [];
        if ($request->filled('cost')) {
            $upd['avg_cost'] = (float) $data['cost'];
        }
        if ($request->filled('price')) {
            $upd['sale_price'] = (float) $data['price'];
        }
        if (empty($upd)) {
            return back()->withErrors(['cost' => 'Укажите закуп и/или цену продажи.']);
        }

        $n = WarehouseItem::where('account_id', $user->account_id)
            ->where('brand', $product->brand)
            ->where('model', $product->model)
            ->update($upd);

        return back()->with('status', 'Цены применены к '.$n.' размер(ам) «'.$product->display_name.'».');
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

    // ==================== BULK-ДЕЙСТВИЯ + ТЕГИ ====================

    /** Массовое действие над выбранными товарами. */
    public function bulkAction(Request $request)
    {
        $user = Auth::user();
        $data = $request->validate([
            'product_ids' => ['required', 'array', 'min:1'],
            'product_ids.*' => ['integer'],
            'action' => ['required', 'in:category,gender,season,tag_add,tag_remove'],
            'value' => ['nullable', 'string', 'max:64'],
        ]);
        $ids = array_map('intval', $data['product_ids']);
        $products = WarehouseProduct::where('account_id', $user->account_id)->whereIn('id', $ids)->get();
        $count = 0;
        foreach ($products as $p) {
            switch ($data['action']) {
                case 'category':
                    if (in_array($data['value'], ['sport', 'casual', 'premium', 'winter', 'kids', ''], true)) {
                        $p->category = $data['value'] === '' ? null : $data['value'];
                    }
                    break;
                case 'gender':
                    if (in_array($data['value'], ['male', 'female', 'unisex', 'kids', ''], true)) {
                        $p->gender = $data['value'] === '' ? null : $data['value'];
                    }
                    break;
                case 'season':
                    if (in_array($data['value'], ['summer', 'winter', 'demi', ''], true)) {
                        $p->season = $data['value'] === '' ? null : $data['value'];
                    }
                    break;
                case 'tag_add':
                    $tag = trim((string) $data['value']);
                    if ($tag !== '') {
                        $tags = is_array($p->tags) ? $p->tags : [];
                        if (! in_array($tag, $tags, true)) {
                            $tags[] = $tag;
                            $p->tags = $tags;
                        }
                    }
                    break;
                case 'tag_remove':
                    $tag = trim((string) $data['value']);
                    if ($tag !== '' && is_array($p->tags)) {
                        $p->tags = array_values(array_filter($p->tags, fn ($t) => $t !== $tag));
                    }
                    break;
            }
            $p->save();
            $count++;
        }

        return back()->with('status', "Обновлено товаров: {$count}");
    }

    // ==================== УМНЫЕ ПОДСКАЗКИ ЗАКУПОК ====================

    public function reorderSuggestions(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;
        $days = (int) $request->query('days', 30); // горизонт прогноза
        if (! in_array($days, [7, 14, 30, 60, 90], true)) $days = 30;

        // Продажи за 90 дней по модели+размеру
        $windowStart = now()->subDays(90);
        $soldRows = \App\Models\Deal::where('account_id', $accId)
            ->whereNotNull('stock_deducted_at')->where('stock_deducted_at', '>=', $windowStart)
            ->with('warehouseItem:id,brand,model,size')->get();
        $soldBySku = [];
        foreach ($soldRows as $d) {
            $wi = $d->warehouseItem;
            if (! $wi) continue;
            $sku = $wi->id;
            $soldBySku[$sku] = ($soldBySku[$sku] ?? 0) + (int) $d->sold_quantity;
        }

        $items = WarehouseItem::where('account_id', $accId)->get();
        $suggestions = [];
        foreach ($items as $i) {
            $sold90 = (int) ($soldBySku[$i->id] ?? 0);
            $perDay = $sold90 / 90;
            if ($perDay <= 0) continue;
            $available = (int) $i->available;
            $forecastNeed = (int) ceil($perDay * $days);
            $safetyBuffer = max(1, (int) round($perDay * 7)); // недельный запас
            $suggested = max(0, $forecastNeed + $safetyBuffer - $available);
            if ($suggested <= 0) continue;
            $daysLeft = $perDay > 0 ? (int) floor($available / $perDay) : null;
            $priority = $daysLeft !== null && $daysLeft < 7 ? 'high' : ($daysLeft !== null && $daysLeft < 21 ? 'mid' : 'low');
            $suggestions[] = [
                'name' => $i->display_name,
                'available' => $available,
                'sold_90d' => $sold90,
                'per_day' => $perDay,
                'days_left' => $daysLeft,
                'suggested' => $suggested,
                'priority' => $priority,
                'brand' => $i->brand,
                'model' => $i->model,
                'size' => $i->size,
            ];
        }
        usort($suggestions, fn ($a, $b) => ($a['days_left'] ?? 999) <=> ($b['days_left'] ?? 999));

        return view('warehouse.reorder', compact('suggestions', 'days'));
    }

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
        $data = $request->validate([
            'raw' => ['nullable', 'string', 'max:200000'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'xlsx' => ['nullable', 'file', 'mimes:xlsx', 'max:10240'],
            'mode' => ['nullable', 'in:add,set'],
        ]);

        if ($request->hasFile('xlsx')) {
            // Excel-файл: Название | Размер | Кол-во | Артикул
            $rows = $this->parseImportXlsx($request->file('xlsx')->getRealPath());
            $mode = $data['mode'] ?? 'set';
            $note = 'Импорт из Excel ('.date('Y-m-d H:i').')';
        } else {
            $raw = trim((string) ($data['raw'] ?? ''));
            if ($raw === '') {
                return back()->withErrors(['raw' => 'Вставьте список или выберите файл .xlsx.']);
            }
            $qty = (int) ($data['quantity'] ?? 1);
            $rows = $this->parseImportText($raw, $qty);
            $mode = 'add';
            $note = 'Импорт списка ('.date('Y-m-d H:i').')';
        }

        [$positionsCreated, $pairsTotal, $errors] = $this->applyImportRows($rows, $mode, $note, $warehouse);

        $verb = $mode === 'set' ? 'остатки установлены по файлу, всего пар' : 'оприходовано пар';
        return redirect()->route('warehouse.index')->with('status',
            'Импорт: строк '.count($rows).', создано позиций '.$positionsCreated.', '.$verb.' '.$pairsTotal.
            (count($errors) ? ' · ошибок '.count($errors) : ''));
    }

    /**
     * Разбор .xlsx формата «Название | Размер | Кол-во | Артикул».
     * Заголовки и мусорные строки отсеиваются по нечисловому размеру/кол-ву.
     *
     * @return array<int, array{brand:string,model:string,size:string,qty:int,article:string}>
     */
    private function parseImportXlsx(string $path): array
    {
        $rows = [];
        foreach (\App\Support\Warehouse\SimpleXlsxReader::rows($path, 4) as $r) {
            [$name, $size, $qty, $article] = [$r[0] ?? null, $r[1] ?? null, $r[2] ?? null, $r[3] ?? null];
            $name = str_replace(['İ', 'ı'], ['I', 'i'], preg_replace('/\s+/u', ' ', trim((string) $name)));
            $article = str_replace(['İ', 'ı'], ['I', 'i'], (string) $article);
            // Размер может быть текстом вида «40,5 RU» — вытаскиваем число.
            $sizeClean = str_replace(',', '.', trim((string) $size));
            $qtyClean = str_replace(',', '.', trim((string) $qty));
            if ($name === ''
                || ! preg_match('/\d+(?:\.\d+)?/', $sizeClean, $sm)
                || ! preg_match('/\d+(?:\.\d+)?/', $qtyClean, $qm)) {
                continue; // заголовок или пустая строка
            }
            [$brand, $model] = $this->splitBrandModel($name);
            $sizeStr = rtrim(rtrim(number_format((float) $sm[0], 2, '.', ''), '0'), '.');
            $qty = $qm[0];
            $articleNorm = $this->normalizeArticle((string) $article);
            $rows[] = [
                'brand' => $brand,
                'model' => $model,
                'size' => $sizeStr,
                'qty' => max(0, (int) round((float) $qty)),
                'article' => $articleNorm,
            ];
        }
        return $rows;
    }

    /**
     * Применить нормализованные строки импорта.
     * Расцветка = отдельный товар: артикул добавляется к названию модели.
     * mode=add — прибавить к остаткам, mode=set — установить остаток как в файле.
     *
     * @param array<int, array{brand:string,model:string,size:string,qty:int,article:string}> $rows
     * @return array{0:int,1:int,2:array<int,string>}
     */
    private function applyImportRows(array $rows, string $mode, string $note, WarehouseService $warehouse): array
    {
        $user = Auth::user();

        // Агрегируем дубликаты строк в файле (один и тот же размер несколько раз)
        $agg = [];
        foreach ($rows as $r) {
            $modelDb = trim($r['model'].($r['article'] !== '' ? ' '.$r['article'] : ''));
            $key = mb_strtolower($r['brand'].'|'.$modelDb.'|'.$r['size']);
            if (! isset($agg[$key])) {
                $agg[$key] = ['brand' => $r['brand'], 'model' => $modelDb, 'size' => $r['size'], 'qty' => 0, 'article' => $r['article']];
            }
            $agg[$key]['qty'] += $r['qty'];
        }

        $positionsCreated = 0;
        $pairsTotal = 0;
        $errors = [];

        foreach ($agg as $r) {
            try {
                $item = WarehouseItem::firstOrNew([
                    'account_id' => $user->account_id,
                    'brand' => $r['brand'],
                    'model' => $r['model'],
                    'size' => $r['size'],
                ]);
                if (! $item->exists) {
                    $item->quantity = 0;
                    $item->save();
                    $positionsCreated++;
                }
                if ($mode === 'set') {
                    $warehouse->setQuantity($item, $r['qty'], $note);
                } else {
                    $warehouse->replenish($item, $r['qty'], $note);
                }
                $pairsTotal += $r['qty'];

                // Товар + вендорский артикул (если указан и свободен)
                $product = WarehouseProduct::firstOrCreate(
                    ['account_id' => $user->account_id, 'brand' => $r['brand'], 'model' => $r['model']], []
                );
                if ($r['article'] !== '' && $product->article !== $r['article']) {
                    $taken = WarehouseProduct::where('account_id', $user->account_id)
                        ->where('article', $r['article'])->where('id', '!=', $product->id)->exists();
                    if (! $taken) {
                        $product->article = $r['article'];
                        $product->save();
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = $r['brand'].' | '.$r['model'].' | '.$r['size'].' — '.$e->getMessage();
            }
        }

        return [$positionsCreated, $pairsTotal, $errors];
    }

    /**
     * Нормализация артикула: пробелы → дефис; Nike-коды (XX9999999 / XX9999 999)
     * приводятся к каноничному виду XX9999-999, чтобы расцветка не двоилась.
     */
    private function normalizeArticle(string $article): string
    {
        $a = preg_replace('/\s+/u', '-', trim($article));
        if (preg_match('/^([A-Za-z]{2}\d{4})-?(\d{3})$/', $a, $m)) {
            return strtoupper($m[1]).'-'.$m[2];
        }
        return $a;
    }

    /** Первое слово — бренд (NEW BALANCE — двусоставный), остальное — модель. */
    private function splitBrandModel(string $name): array
    {
        $multiWordBrands = ['NEW BALANCE'];
        $upper = mb_strtoupper($name);
        foreach ($multiWordBrands as $mb) {
            if (str_starts_with($upper, $mb.' ')) {
                return [$mb, trim(mb_substr($name, mb_strlen($mb) + 1))];
            }
        }
        $parts = explode(' ', $name, 2);
        return [mb_strtoupper($parts[0] ?? ''), trim($parts[1] ?? '')];
    }

    /**
     * Парсер текста импорта. Поддерживает два формата:
     *
     * 1) Строчный: «БРЕНД МОДЕЛЬ - 41,42.5,43» (кол-во каждой пары = $defaultQty).
     * 2) Блочный (как шлют поставщики), блоки разделены пустой строкой:
     *      Название модели
     *      38,38.5(2),39      ← размер, в скобках кол-во пар (без скобок = 1)
     *      DD1873112          ← артикул (расцветка)
     *    Допускаются склейки без запятой: «38(2)37.5», «37(4)40.5,43(2)».
     *
     * @return array<int, array{brand:string,model:string,size:string,qty:int,article:string}>
     */
    private function parseImportText(string $raw, int $defaultQty = 1): array
    {
        // Турецкие İ/ı в артикулах и названиях → латинские I/i.
        $raw = str_replace(['İ', 'ı'], ['I', 'i'], $raw);

        $rows = [];
        $block = [];

        $flushBlock = function () use (&$block, &$rows, $defaultQty) {
            if (empty($block)) {
                return;
            }
            // Блочный формат: [название, строка размеров, артикул?]
            if (count($block) >= 2 && count($block) <= 3 && $this->looksLikeSizesLine($block[1])) {
                $sizes = $this->parseSizesWithQty($block[1]);
                $name = $this->normalizeLeadingW($block[0]);
                if (! empty($sizes) && $name !== '') {
                    [$brand, $model] = $this->splitBrandModel($name);
                    $article = isset($block[2]) ? $this->normalizeArticle($block[2]) : '';
                    foreach ($sizes as [$size, $q]) {
                        $rows[] = ['brand' => $brand, 'model' => $model, 'size' => $size, 'qty' => $q, 'article' => $article];
                    }
                    $block = [];
                    return;
                }
            }
            // Иначе — каждая строка блока в строчном формате «... - размеры».
            foreach ($block as $line) {
                if (! preg_match('/^(.*?)[\s]*[-—]\s*(?:рр\s*)?([\d.,\s]+)$/u', $line, $m)) {
                    continue;
                }
                $name = trim($m[1]);
                $sizes = array_values(array_filter(array_map('trim', preg_split('/[,\s]+/', trim($m[2])))));
                if (empty($sizes) || $name === '') {
                    continue;
                }
                [$brand, $model] = $this->splitBrandModel($name);
                foreach ($sizes as $size) {
                    $rows[] = ['brand' => $brand, 'model' => $model, 'size' => (string) $size, 'qty' => $defaultQty, 'article' => ''];
                }
            }
            $block = [];
        };

        foreach (preg_split('/\r?\n/', $raw) as $line) {
            $line = trim(preg_replace('/\s+/u', ' ', $line));
            if ($line === '') {
                $flushBlock();
                continue;
            }
            $block[] = $line;
        }
        $flushBlock();

        return $rows;
    }

    /** Строка состоит только из цифр, точек, запятых, скобок и пробелов? */
    private function looksLikeSizesLine(string $line): bool
    {
        return $line !== ''
            && preg_match('/\d/', $line) === 1
            && preg_match('/^[\d.,()\s]+$/', $line) === 1;
    }

    /**
     * «38,38.5(2)39» → [['38',1], ['38.5',2], ['39',1]].
     *
     * @return array<int, array{0:string,1:int}>
     */
    private function parseSizesWithQty(string $line): array
    {
        $out = [];
        // Запятая здесь — разделитель списка, десятичные только через точку.
        if (! preg_match_all('/(\d+(?:\.\d+)?)(?:\s*\((\d+)\))?/', $line, $mm, PREG_SET_ORDER)) {
            return $out;
        }
        foreach ($mm as $m) {
            $size = rtrim(rtrim(number_format((float) $m[1], 2, '.', ''), '0'), '.');
            $qty = isset($m[2]) && $m[2] !== '' ? (int) $m[2] : 1;
            $out[] = [$size, max(1, $qty)];
        }
        return $out;
    }

    /** «W Nike dunk low» / «Wmns Nike …» → «Nike W dunk low» (бренд должен идти первым). */
    private function normalizeLeadingW(string $name): string
    {
        if (! preg_match('/^(WMNS|W)\s+(\S+)\s*(.*)$/iu', $name, $m)) {
            return $name;
        }
        $knownBrands = ['NIKE', 'ADIDAS', 'PUMA', 'VANS', 'LACOSTE', 'KAPPA', 'SAUCONY', 'GUESS', 'CALVIN', 'LI-NING', 'NEW'];
        if (! in_array(mb_strtoupper($m[2]), $knownBrands, true)) {
            return $name;
        }
        return trim($m[2].' '.$m[1].' '.$m[3]);
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
