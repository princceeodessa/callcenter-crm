<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use App\Services\Warehouse\WarehouseService;
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

        // Группируем размеры под одним товаром (бренд + модель).
        // Заодно подмешиваем warehouse_products (кастомное имя, фото).
        $accId = $user->account_id;
        $products = $items
            ->groupBy(fn ($i) => mb_strtolower(trim(($i->brand ?? '').'|'.($i->model ?? ''))))
            ->map(function ($group) use ($accId) {
                $first = $group->first();
                $brand = (string) ($first->brand ?? '');
                $model = (string) ($first->model ?? '');
                $product = WarehouseProduct::firstOrCreate(
                    ['account_id' => $accId, 'brand' => $brand, 'model' => $model],
                    []
                );
                $autoName = trim($brand.' '.$model) ?: 'Без названия';

                return [
                    'entity' => $product,
                    'name' => $product->custom_name ?: $autoName,
                    'auto_name' => $autoName,
                    'custom_name' => $product->custom_name,
                    'image_url' => $product->image_url,
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

        return view('warehouse.index', compact('products', 'movements', 'q', 'totalUnits', 'stockValue', 'isHead'));
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

    /** Изменить отображаемое имя товара (кастомное). */
    public function updateProduct(Request $request, WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        $data = $request->validate([
            'custom_name' => ['nullable', 'string', 'max:255'],
        ]);

        $name = trim((string) ($data['custom_name'] ?? ''));
        $product->custom_name = $name !== '' ? $name : null;
        $product->save();

        return back()->with('status', 'Название товара обновлено.');
    }

    /** Загрузить/заменить фото товара. */
    public function uploadProductPhoto(Request $request, WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        $data = $request->validate([
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        // Удалить старое фото
        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        $path = $data['photo']->store('warehouse_products/'.$product->account_id, 'public');
        $product->image_path = $path;
        $product->save();

        return back()->with('status', 'Фото обновлено.');
    }

    /** Удалить фото товара. */
    public function deleteProductPhoto(WarehouseProduct $product)
    {
        $user = Auth::user();
        abort_unless($product->account_id === $user->account_id, 403);

        if ($product->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }
        $product->image_path = null;
        $product->save();

        return back()->with('status', 'Фото удалено.');
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
