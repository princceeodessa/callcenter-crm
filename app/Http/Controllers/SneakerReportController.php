<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Purchase;
use App\Models\WarehouseItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SneakerReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();

        $monthStr = $request->string('month')->toString();
        try {
            $month = $monthStr !== '' ? Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            $month = Carbon::now()->startOfMonth();
        }
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        // Продажи со склада за период (сделки, списавшие товар).
        $soldDeals = Deal::query()
            ->where('account_id', $user->account_id)
            ->whereNotNull('stock_deducted_at')
            ->whereBetween('stock_deducted_at', [$start, $end])
            ->with('warehouseItem:id,brand,model,size')
            ->orderByDesc('stock_deducted_at')
            ->get();

        $revenue = (float) $soldDeals->sum(fn ($d) => (float) ($d->amount ?? 0));
        $unitsSold = (int) $soldDeals->sum(fn ($d) => (int) $d->sold_quantity);
        $cogs = (float) $soldDeals->sum(fn ($d) => (float) ($d->sold_unit_cost ?? 0) * (int) $d->sold_quantity);
        $profit = $revenue - $cogs;

        // Состояние склада (на текущий момент).
        $items = WarehouseItem::where('account_id', $user->account_id)->get();
        $stockUnits = (int) $items->sum('quantity');
        $reservedUnits = (int) $items->sum('reserved');
        $stockCostValue = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) ($i->avg_cost ?? 0));
        $stockSaleValue = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) ($i->sale_price ?? 0));
        $lowItems = $items->filter(fn ($i) => $i->is_low)->sortBy('available')->values();

        $openPurchases = Purchase::where('account_id', $user->account_id)->whereNull('closed_at')->count();

        $monthValue = $month->format('Y-m');

        return view('reports.sneaker', compact(
            'soldDeals', 'revenue', 'unitsSold', 'cogs', 'profit',
            'stockUnits', 'reservedUnits', 'stockCostValue', 'stockSaleValue', 'lowItems',
            'openPurchases', 'monthValue'
        ));
    }
}
