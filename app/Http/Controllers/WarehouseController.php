<?php

namespace App\Http\Controllers;

use App\Models\StockMovement;
use App\Models\WarehouseItem;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return view('warehouse.index', compact('items', 'movements', 'q', 'totalUnits'));
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

        $item->sale_price = $data['sale_price'] ?? null;
        $item->low_stock_threshold = (int) ($data['low_stock_threshold'] ?? 0);
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

        $item->delete();

        return redirect()->route('warehouse.index')->with('status', 'Позиция удалена.');
    }
}
