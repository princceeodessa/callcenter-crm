<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\StockMark;
use App\Models\StockMovement;
use App\Models\WarehouseConsignment;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use App\Models\WarehouseProductPhoto;
use App\Services\Warehouse\WarehouseService;
use App\Support\Warehouse\ArticleIdentity;
use App\Support\Warehouse\ProductClassifier;
use App\Support\Marking\MarkCode;
use App\Support\Ui\Sneakers;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    /**
     * Склад — каркас «Нордика» (прототип PAGES.warehouse, снимки 05, 06): блоки «Остатки» и «Склад в деньгах», карточки
     * товаров с размерами. Все товары уходят на страницу одним JSON — поиск, фильтры, итоги и правки размеров работают
     * без перезагрузки (действия отвечают JSON с обновлённым товаром). Закупка в данные попадает только руководителю.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;
        $seesCost = Sneakers::seesCost($user);

        $items = WarehouseItem::query()
            ->with(['consignments' => fn ($cq) => $cq->where('status', 'given')])
            ->where('account_id', $accId)
            ->orderBy('brand')->orderBy('model')->orderBy('size')
            ->get();

        // Все продукты одним запросом (фото сразу) — без N+1 на каждую карточку.
        $prodMap = [];
        foreach (WarehouseProduct::with('photos')->where('account_id', $accId)->get() as $p) {
            $prodMap[self::productKey($p->brand, $p->model)] = $p;
        }

        // Размеры — под одним товаром (бренд + модель); товара в warehouse_products ещё нет — заводим (артикул, фото).
        $products = $items
            ->groupBy(fn ($i) => self::productKey($i->brand, $i->model))
            ->map(function ($group, $key) use ($accId, &$prodMap, $seesCost) {
                $first = $group->first();
                $product = $prodMap[$key] ?? null;
                if (! $product) {
                    $product = WarehouseProduct::create(['account_id' => $accId, 'brand' => (string) ($first->brand ?? ''), 'model' => (string) ($first->model ?? '')]);
                    $product->setRelation('photos', collect());
                    $prodMap[$key] = $product;
                }

                return $this->payload($group, $product, $seesCost);
            })
            ->sortBy(fn ($p) => mb_strtoupper($p['name']))
            ->values();

        $moveLabels = [
            'in' => 'Приход', 'in_adjust' => 'Корректировка прихода', 'in_reversal' => 'Откат прихода', 'out' => 'Продажа',
            'out_reversal' => 'Возврат на склад', 'return' => 'Возврат продажи', 'reserve' => 'Резерв', 'reserve_release' => 'Снятие резерва',
            'replenish' => 'Пополнение', 'adjust' => 'Корректировка', 'consign_out' => 'Передано посреднику', 'consign_resolve' => 'Снято с реализации',
            'consign_sold' => 'Продано посредником',
        ];
        $movements = StockMovement::query()
            ->with(['item:id,brand,model,size', 'user:id,name'])
            ->where('account_id', $accId)
            ->orderByDesc('id')
            ->limit(40)
            ->get()
            ->map(fn (StockMovement $m) => [
                'at' => optional($m->created_at)->format('d.m H:i'),
                'name' => $m->item?->display_name ?? '—',
                'type' => $moveLabels[$m->type] ?? $m->type,
                'q' => (int) $m->quantity,
                'note' => $m->note,
                'user' => $m->user?->name,
            ]);

        return view('warehouse.sneakers', [
            'products' => $products,
            'movements' => $movements,
            'seesCost' => $seesCost,
            'isHead' => $this->isHead(),
            'categoryOptions' => ProductClassifier::categoryOptions(),
            'genderOptions' => ProductClassifier::genderOptions(),
            'seasonOptions' => ProductClassifier::seasonOptions(),
            'initial' => [
                'q' => trim($request->string('q')->toString()),
                'low' => $request->query('low') == '1',
                'cat' => trim((string) $request->query('cat')),
                'sex' => trim((string) $request->query('sex')),
                'sea' => trim((string) $request->query('sea')),
            ],
            // Сколько пар закуплено, но ещё не заведено на склад — счётчик на кнопке «В пути».
            'inTransitPairs' => Sneakers::inTransitPairs($user),
            // Сверка: продажи без списания со склада и отрицательные остатки — оранжевая кнопка над блоками.
            'unlinkedSalesCount' => Deal::where('account_id', $accId)->soldWithoutStock()->count(),
            'negativeCount' => WarehouseItem::where('account_id', $accId)->where('quantity', '<', 0)->count(),
        ]);
    }

    /** Ключ товара — бренд + модель без учёта регистра и пробелов по краям (так размеры собираются под одной карточкой) */
    private static function productKey(?string $brand, ?string $model): string
    {
        return mb_strtolower(trim(($brand ?? '').'|'.($model ?? '')));
    }

    /**
     * Товар для страницы склада: название, артикул, фото, разметка и размеры (остаток, доступно, резерв, у посредников,
     * цена, мин. остаток, передачи посредникам). Закупка «c» — только тем, кто видит себестоимость (решение 28.10.2026).
     */
    private function payload(Collection $group, WarehouseProduct $product, bool $seesCost): array
    {
        $first = $group->first();
        $brand = (string) ($first->brand ?? '');
        $model = (string) ($first->model ?? '');
        $autoName = trim($brand.' '.$model) ?: 'Без названия';
        $name = $product->custom_name ?: $autoName;
        $gallery = $product->gallery;

        return [
            'id' => $product->id,
            'name' => $name,
            'auto' => $autoName,
            'art' => (string) $product->article,
            'brand' => Sneakers::brandOf($name),
            'b' => $brand,
            'm' => $model,
            'img' => $gallery[0]['url'] ?? null,
            'gal' => $gallery,
            'cat' => $product->category,
            'sex' => $product->gender,
            'sea' => $product->season,
            'sz' => $group->sortBy('size', SORT_NATURAL)->values()->map(fn (WarehouseItem $i) => [
                'id' => $i->id,
                's' => (string) $i->size,
                'q' => (int) $i->quantity,
                'a' => (int) $i->available,
                'r' => (int) $i->reserved,
                'k' => (int) $i->consigned,
                'p' => $i->sale_price !== null ? (float) $i->sale_price : null,
                'min' => (int) $i->low_stock_threshold,
                'cons' => $i->consignments->where('status', 'given')->map(fn ($c) => ['id' => $c->id, 'who' => $c->consignee, 'q' => (int) $c->quantity])->values()->all(),
            ] + ($seesCost ? ['c' => $i->avg_cost !== null ? (float) $i->avg_cost : null] : []))->all(),
        ];
    }

    /** Размеры одного товара (бренд + модель); пустой бренд/модель в старых строках бывает и NULL, и '' */
    private function productItems(int $accId, string $brand, string $model): Collection
    {
        $same = fn ($q, string $col, string $v) => $v === '' ? $q->where(fn ($w) => $w->whereNull($col)->orWhere($col, '')) : $q->where($col, $v);

        $query = WarehouseItem::query()->with(['consignments' => fn ($cq) => $cq->where('status', 'given')])->where('account_id', $accId);
        $same($query, 'brand', $brand);
        $same($query, 'model', $model);

        return $query->get();
    }

    /** Ответ действия склада для страницы: обновлённый товар (или product = null — размеров не осталось) */
    private function productResponse(int $accId, string $brand, string $model, string $msg, ?int $productId = null)
    {
        $items = $this->productItems($accId, $brand, $model);
        $product = WarehouseProduct::with('photos')->where('account_id', $accId)
            ->when($productId, fn ($q) => $q->whereKey($productId), fn ($q) => $q->where('brand', $brand)->where('model', $model))
            ->first();
        if ($items->isNotEmpty() && ! $product) {
            $product = WarehouseProduct::create(['account_id' => $accId, 'brand' => $brand, 'model' => $model]);
            $product->setRelation('photos', collect());
        }

        return response()->json([
            'ok' => true,
            'msg' => $msg,
            'id' => $product?->id ?? $productId,
            'product' => $items->isNotEmpty() ? $this->payload($items, $product, Sneakers::seesCost()) : null,
        ]);
    }

    public function store(Request $request, WarehouseService $warehouse)
    {
        // «+ Товар» на складе: название, артикул, бренд и сразу несколько размеров «41:1, 42:2»
        if ($request->filled('name')) {
            return $this->storeProduct($request, $warehouse);
        }

        $user = Auth::user();
        $data = $request->validate([
            'brand' => ['nullable', 'string', 'max:255'],
            'model' => ['nullable', 'string', 'max:255'],
            'size' => ['nullable', 'string', 'max:50'],
            'quantity' => ['required', 'integer', 'min:0', 'max:1000000'],
            'sale_price' => ['nullable', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
        ]);
        $brand = (string) ($data['brand'] ?? '');
        $model = (string) ($data['model'] ?? '');

        $item = WarehouseItem::firstOrNew([
            'account_id' => $user->account_id,
            'brand' => $brand,
            'model' => $model,
            'size' => (string) ($data['size'] ?? ''),
        ]);
        $existed = $item->exists;
        if (array_key_exists('sale_price', $data)) {
            $item->sale_price = $data['sale_price'];
        }
        // «+ размер» у товара: цену и закупку берём у соседнего размера — иначе новый размер продавался бы «без цены»
        if (! $existed && $item->sale_price === null) {
            $sibling = $this->productItems($user->account_id, $brand, $model)->whereNotNull('sale_price')->sortByDesc('id')->first();
            if ($sibling) {
                $item->sale_price = $sibling->sale_price;
                $item->avg_cost ??= $sibling->avg_cost;
            }
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

        $msg = ($item->size !== '' ? 'Размер '.$item->size : 'Позиция').($existed ? ' пополнен' : ' добавлен').($qty > 0 ? ': +'.$qty : '');
        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, $brand, $model, $msg);
        }

        return redirect()->route('warehouse.index')->with('status', $existed ? 'Позиция пополнена.' : 'Позиция добавлена.');
    }

    /** Новый товар с несколькими размерами (окно «Новый товар», прототип openNewProduct) */
    private function storeProduct(Request $request, WarehouseService $warehouse)
    {
        $user = Auth::user();
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'article' => ['nullable', 'string', 'max:64'],
            'sizes' => ['required', 'string', 'max:1000'],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'price' => ['nullable', 'numeric', 'min:0'],
        ], [
            'sizes.required' => 'Укажите размеры и пары: 41:1, 42:2.',
        ]);

        $sizes = self::parseNewSizes($data['sizes'], $bad);
        if ($bad !== null || ! $sizes) {
            throw ValidationException::withMessages(['sizes' => $bad !== null
                ? 'Не понял размер «'.$bad.'». Пишите так: 41:1, 42.5:2 (размер : пар).'
                : 'Укажите размеры и пары: 41:1, 42:2.']);
        }
        [$brand, $model] = self::splitName($data['name'], (string) ($data['brand'] ?? ''));
        $article = trim((string) ($data['article'] ?? ''));
        if ($article !== '') {
            $taken = WarehouseProduct::where('account_id', $user->account_id)->where('article', $article)->first();
            if ($taken && self::productKey($taken->brand, $taken->model) !== self::productKey($brand, $model)) {
                throw ValidationException::withMessages(['article' => 'Артикул «'.$article.'» уже у товара «'.$taken->display_name.'».']);
            }
        }

        $pairs = 0;
        foreach ($sizes as $size => $qty) {
            $item = WarehouseItem::firstOrNew(['account_id' => $user->account_id, 'brand' => $brand, 'model' => $model, 'size' => (string) $size]);
            $existed = $item->exists;
            if (! $existed) {
                $item->quantity = 0;
                $item->low_stock_threshold = 0;
            }
            if (($data['price'] ?? null) !== null) {
                $item->sale_price = (float) $data['price'];
            }
            if ($this->isHead() && ($data['cost'] ?? null) !== null) {
                $item->avg_cost = (float) $data['cost'];
            }
            $item->save();
            if ($qty > 0) {
                $warehouse->replenish($item, $qty, $existed ? 'Добавление к позиции (вручную)' : 'Начальный остаток (вручную)');
                $pairs += $qty;
            }
        }

        $product = WarehouseProduct::firstOrCreate(['account_id' => $user->account_id, 'brand' => $brand, 'model' => $model]);
        if ($article !== '' && $product->article !== $article) {
            $product->article = $article;
            $product->save();
        }

        $msg = 'Товар «'.$product->display_name.'» создан: '.count($sizes).' разм., '.$pairs.' пар';
        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, $brand, $model, $msg, $product->id);
        }

        return redirect()->route('warehouse.index')->with('status', $msg.'.');
    }

    /**
     * Название нового товара → бренд + модель, как их хранит склад («NIKE» + «p-6000 prm»): бренд указан и название с него
     * начинается — бренд берётся из названия; не начинается — бренд отдельно, модель = название; бренда нет — первое слово
     * (New Balance — два).
     *
     * @return array{0: string, 1: string}
     */
    private static function splitName(string $name, string $brand): array
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $brand = trim(preg_replace('/\s+/u', ' ', $brand));
        if ($brand !== '') {
            $len = mb_strlen($brand);
            if (mb_strtolower(mb_substr($name, 0, $len)) === mb_strtolower($brand) && (mb_strlen($name) === $len || mb_substr($name, $len, 1) === ' ')) {
                return [mb_substr($name, 0, $len), trim(mb_substr($name, $len))];
            }

            return [$brand, $name];
        }
        if (preg_match('/^(new\s+balance|\S+)\s*(.*)$/iu', $name, $m)) {
            return [$m[1], trim($m[2])];
        }

        return ['', $name];
    }

    /**
     * «41:1, 42.5:2, 43» → [41 => 1, 42.5 => 2, 43 => 1]; пары можно и в скобках — «42(2)». Размер — число (половинки через
     * точку или запятую); непонятный кусок — в $bad.
     *
     * @return array<string, int>
     */
    private static function parseNewSizes(string $raw, ?string &$bad = null): array
    {
        $bad = null;
        $out = [];
        $raw = preg_replace(['/\s*([:x×*])\s*/u', '/\s*\(\s*/u', '/\s*\)/u'], ['$1', '(', ')'], $raw);
        foreach (preg_split('/[;\s]+|,(?=\s)|,(?=\d{2})/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $part = trim($part, " ,\t");
            if ($part === '') {
                continue;
            }
            if (! preg_match('/^(\d{1,2}(?:[.,]5)?)\s*(?:[:x×*]\s*(\d{1,4})|\((\d{1,4})\))?$/u', $part, $m)) {
                $bad = $part;

                return [];
            }
            $size = str_replace(',', '.', $m[1]);
            $qty = (int) (($m[2] ?? '') !== '' ? $m[2] : (($m[3] ?? '') !== '' ? $m[3] : 1));
            $out[$size] = ($out[$size] ?? 0) + $qty;
        }

        return $out;
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

        // Цены (закуп/продажа) и порог меняет только руководитель отдела. Меняются только присланные поля: окно размера
        // продавца цен не шлёт, а заметку позиции не показывает — раньше сохранение стирало её.
        if ($this->isHead()) {
            if ($request->has('sale_price')) {
                $item->sale_price = $data['sale_price'] ?? null;
            }
            if ($request->has('avg_cost')) {
                $item->avg_cost = $data['avg_cost'] ?? null;
            }
            if ($request->has('low_stock_threshold')) {
                $item->low_stock_threshold = (int) ($data['low_stock_threshold'] ?? 0);
            }
        }
        if ($request->has('notes')) {
            $item->notes = $data['notes'] ?? null;
        }
        $item->save();

        $warehouse->setQuantity($item, (int) $data['quantity'], 'Ручная корректировка остатка');

        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, (string) $item->brand, (string) $item->model, 'Размер '.($item->size !== '' ? $item->size : '—').' сохранён');
        }

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

        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, (string) $item->brand, (string) $item->model, 'Остаток изменён');
        }

        return redirect()->route('warehouse.index')->with('status', 'Остаток изменён.');
    }

    public function destroy(Request $request, WarehouseItem $item)
    {
        $user = Auth::user();
        abort_unless($item->account_id === $user->account_id, 403);
        abort_unless($this->isHead(), 403);

        $productId = WarehouseProduct::where('account_id', $user->account_id)->where('brand', (string) $item->brand)->where('model', (string) $item->model)->value('id');
        $item->delete();

        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, (string) $item->brand, (string) $item->model, 'Размер '.($item->size !== '' ? $item->size : '—').' удалён', $productId);
        }

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

        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, (string) $product->brand, (string) $product->model, 'Товар сохранён', $product->id);
        }

        return back()->with('status', 'Товар обновлён.');
    }

    /** Загрузить фото товара — новое фото становится обложкой (главным), остальные уходят в галерею. */
    public function uploadProductPhoto(Request $request, WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $path = $data['photo']->store('warehouse_products/'.$product->account_id, 'public');
        // Наименьший sort → фото становится первым в галерее (обложкой). Так «сменить фото» реально меняет главную картинку.
        // sort — unsignedInteger, поэтому вместо min-1 (может уйти в минус) сдвигаем остальные фото вверх и ставим новое на 0.
        $product->photos()->increment('sort');
        $product->photos()->create(['path' => $path, 'sort' => 0]);
        $product->image_path = $path;
        $product->save();

        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, (string) $product->brand, (string) $product->model, 'Фото обновлено', $product->id);
        }

        return back()->with('status', 'Фото обновлено.');
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

        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, (string) $product->brand, (string) $product->model, 'Фото удалено', $product->id);
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
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['cost' => 'Укажите закуп и/или цену продажи.']);
            }

            return back()->withErrors(['cost' => 'Укажите закуп и/или цену продажи.']);
        }

        $n = WarehouseItem::where('account_id', $user->account_id)
            ->where('brand', $product->brand)
            ->where('model', $product->model)
            ->update($upd);

        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, (string) $product->brand, (string) $product->model, 'Цена применена ко всем размерам ('.$n.')', $product->id);
        }

        return back()->with('status', 'Цены применены к '.$n.' размер(ам) «'.$product->display_name.'».');
    }

    /** Старая ссылка «🖨️ Этикетка» — ведёт на общую страницу печати. */
    public function label(WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        // Печать переехала на общую страницу под термопринтер (размер наклейки, копии по размерам, ЧЗ).
        return redirect()->route('print.labels', ['product' => $product->id, 'type' => 'product']);
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
        $mode = $data['mode'] ?? 'article';
        // Код ЧЗ: вернуть потерянные сканером GS и латиницу из русской раскладки.
        $code = $mode === 'mark' ? MarkCode::normalize($data['code']) : trim($data['code']);
        $qty = (int) ($data['quantity'] ?? 1);
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
        $codes = array_values(array_filter(array_map([MarkCode::class, 'normalize'], preg_split('/\r?\n/', $data['codes']))));
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

    // ==================== РЕАЛИЗАЦИЯ (ПОСРЕДНИКИ) ====================

    /** Передать N пар посреднику под реализацию — снимает их с доступного остатка. */
    public function giveConsignment(Request $request, WarehouseItem $item, WarehouseService $warehouse)
    {
        $user = Auth::user();
        abort_unless($item->account_id === $user->account_id, 403);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:1'],
            'consignee' => ['required', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        if ((int) $data['quantity'] > (int) $item->available) {
            if ($request->expectsJson()) {
                throw ValidationException::withMessages(['quantity' => 'Недостаточно доступного остатка (доступно: '.$item->available.').']);
            }

            return back()->withErrors(['quantity' => 'Недостаточно доступного остатка (доступно: '.$item->available.').']);
        }

        $warehouse->giveForConsignment($item, (int) $data['quantity'], trim($data['consignee']), $data['note'] ?? null);

        if ($request->expectsJson()) {
            return $this->productResponse($user->account_id, (string) $item->brand, (string) $item->model, 'Передано «'.trim($data['consignee']).'»: '.$data['quantity'].' пар');
        }

        return back()->with('status', 'Передано под реализацию: '.$data['quantity'].' пар.');
    }

    /** Закрыть реализацию: посредник продал или вернул пары. */
    public function resolveConsignment(Request $request, WarehouseConsignment $consignment, WarehouseService $warehouse)
    {
        $user = Auth::user();
        abort_unless($consignment->account_id === $user->account_id, 403);

        $data = $request->validate([
            'result' => ['required', 'in:sold,returned'],
        ]);

        if ($data['result'] === 'sold') {
            $warehouse->resolveConsignmentSold($consignment);
            $status = 'Отмечено как продано у посредника.';
        } else {
            $warehouse->resolveConsignmentReturned($consignment);
            $status = 'Пары возвращены на склад.';
        }

        if ($request->expectsJson()) {
            $item = $consignment->item()->first();

            return $this->productResponse($user->account_id, (string) $item?->brand, (string) $item?->model, $status);
        }

        return back()->with('status', $status);
    }

    /** Сводка по реализации: кто сколько держит сейчас + история закрытых. */
    public function consignmentsIndex(Request $request)
    {
        $user = Auth::user();
        $isHead = $this->isHead();

        $active = WarehouseConsignment::with('item:id,brand,model,size,sale_price')
            ->where('account_id', $user->account_id)
            ->where('status', 'given')
            ->orderByDesc('id')
            ->get();

        $byConsignee = $active->groupBy('consignee')->map(function ($group, $name) {
            return [
                'name' => $name,
                'items' => $group,
                'qty' => (int) $group->sum('quantity'),
                'cost_value' => (float) $group->sum(fn ($c) => (float) ($c->unit_cost ?? 0) * (int) $c->quantity),
                'sale_value' => (float) $group->sum(fn ($c) => (float) ($c->item->sale_price ?? 0) * (int) $c->quantity),
            ];
        })->sortByDesc('qty')->values();

        $totalQty = (int) $active->sum('quantity');
        $totalCostValue = (float) $active->sum(fn ($c) => (float) ($c->unit_cost ?? 0) * (int) $c->quantity);

        $history = WarehouseConsignment::with('item:id,brand,model,size')
            ->where('account_id', $user->account_id)
            ->whereIn('status', ['sold', 'returned'])
            ->orderByDesc('resolved_at')
            ->limit(50)
            ->get();

        return view('warehouse.consignments', compact('byConsignee', 'totalQty', 'totalCostValue', 'history', 'isHead'));
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

        if ($request->expectsJson()) {
            $seesCost = Sneakers::seesCost($user);
            $payloads = $products->map(function (WarehouseProduct $p) use ($user, $seesCost) {
                $items = $this->productItems($user->account_id, (string) $p->brand, (string) $p->model);
                $p->load('photos');

                return $items->isNotEmpty() ? $this->payload($items, $p, $seesCost) : null;
            })->filter()->values();

            return response()->json(['ok' => true, 'msg' => 'Обновлено товаров: '.$count, 'products' => $payloads]);
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
            [$brand, $model] = ArticleIdentity::splitBrandModel($name);
            $sizeStr = rtrim(rtrim(number_format((float) $sm[0], 2, '.', ''), '0'), '.');
            $qty = $qm[0];
            $articleNorm = ArticleIdentity::normalizeArticle((string) $article);
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

        // Артикул = идентичность товара: все размеры с одним артикулом должны попасть
        // в один и тот же (бренд+модель), даже если в источнике модель написана по-разному.
        $canonicalByArticle = ArticleIdentity::canonicalizeByArticle($user->account_id, $rows);

        // Агрегируем дубликаты строк в файле (один и тот же размер несколько раз)
        $agg = [];
        foreach ($rows as $r) {
            [$brand, $model] = ArticleIdentity::resolve($canonicalByArticle, $r);
            $art = $r['article'];
            $key = mb_strtolower($brand.'|'.$model.'|'.$r['size']);
            if (! isset($agg[$key])) {
                $agg[$key] = ['brand' => $brand, 'model' => $model, 'size' => $r['size'], 'qty' => 0, 'article' => $art];
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
                ArticleIdentity::ensureProduct($user->account_id, $r['brand'], $r['model'], $r['article']);
            } catch (\Throwable $e) {
                $errors[] = $r['brand'].' | '.$r['model'].' | '.$r['size'].' — '.$e->getMessage();
            }
        }

        return [$positionsCreated, $pairsTotal, $errors];
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
                    [$brand, $model] = ArticleIdentity::splitBrandModel($name);
                    $article = isset($block[2]) ? ArticleIdentity::normalizeArticle($block[2]) : '';
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
                [$brand, $model] = ArticleIdentity::splitBrandModel($name);
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

        // Себестоимость — только тем, кто видит закупку (решение 28.10.2026): продавцу колонки нет
        $cost = Sneakers::seesCost($user);
        $rows = [array_values(array_filter(['Бренд', 'Модель', 'Размер', 'Остаток', 'Резерв', 'Доступно', $cost ? 'Себестоимость' : null, 'Цена продажи', 'Стоимость (прод.)'], fn ($v) => $v !== null))];
        foreach ($items as $i) {
            $row = [$i->brand, $i->model, $i->size, $i->quantity, $i->reserved, $i->available];
            if ($cost) {
                $row[] = $i->avg_cost;
            }
            $rows[] = array_merge($row, [$i->sale_price, (int) $i->quantity * (float) ($i->sale_price ?? 0)]);
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
