<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnerPeriod;
use App\Models\Deal;
use App\Models\Purchase;
use App\Models\WarehouseItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Единственная страница роли «Владелец» (sneaker_owner): сводка склада в деньгах
 * + все продажи с прибылью/маржой по каждому заказу за выбранный период (как у сводки потолков: «Сегодня» …
 * «Прошлый месяц» или свои даты в календаре; старые ссылки ?month= тоже работают).
 */
class OwnerDashboardController extends Controller
{
    use OwnerPeriod;

    public function index(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;

        // ---- Склад в деньгах ----
        $items = WarehouseItem::where('account_id', $accId)->get();
        $productsCount = $items
            ->groupBy(fn ($i) => mb_strtolower(trim(($i->brand ?? '').'|'.($i->model ?? ''))))
            ->count();
        $totalUnits = (int) $items->sum('quantity');
        $availableSum = (int) $items->sum(fn ($i) => (int) $i->available);
        $stockCostValue = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) ($i->avg_cost ?? 0));
        $stockSaleValue = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) ($i->sale_price ?? 0));
        $potentialProfit = $stockSaleValue - $stockCostValue;

        // ---- В доставке (заказано/оплачено/в пути — деньги потрачены, на склад ещё не заведено) ----
        $inDelivery = Purchase::where('account_id', $accId)
            ->whereNull('closed_at')
            ->whereNull('stocked_at')
            ->get();
        $inDeliveryUnits = (int) $inDelivery->sum('quantity');
        $inDeliveryValue = (float) $inDelivery->sum(fn ($p) => (int) $p->quantity * (float) ($p->cost ?? 0));

        // ---- Период ----
        [$period, $start, $endExclusive] = $this->period($request);
        $end = $endExclusive->copy()->subSecond();

        $sold = Deal::where('account_id', $accId)
            ->whereNotNull('stock_deducted_at')
            ->whereBetween('stock_deducted_at', [$start, $end])
            ->with(['warehouseItem:id,brand,model,avg_cost', 'responsible:id,name'])
            ->orderByDesc('stock_deducted_at')
            ->get();

        $revenue = (float) $sold->sum(fn ($d) => (float) ($d->amount ?? 0));
        $profit = (float) $sold->sum(fn ($d) => (float) ($d->sale_profit ?? 0));
        $units = (int) $sold->sum(fn ($d) => (int) $d->sold_quantity);
        $count = $sold->count();
        $avg = $count > 0 ? $revenue / $count : 0.0;
        $margin = $revenue > 0 ? $profit / $revenue * 100 : 0.0;

        // Сколько заказов без указанного закупа (прибыль не посчитана).
        $noCostCount = $sold->filter(fn ($d) => $d->sale_profit === null)->count();

        // ---- Топ моделей по прибыли ----
        $topModels = $sold
            ->groupBy(fn ($d) => $d->warehouseItem ? trim(($d->warehouseItem->brand ?? '').' '.($d->warehouseItem->model ?? '')) : ($d->title ?: 'Без товара'))
            ->map(fn ($g, $name) => [
                'name' => $name !== '' ? $name : 'Без товара',
                'units' => (int) $g->sum(fn ($d) => (int) $d->sold_quantity),
                'revenue' => (float) $g->sum(fn ($d) => (float) ($d->amount ?? 0)),
                'profit' => (float) $g->sum(fn ($d) => (float) ($d->sale_profit ?? 0)),
            ])
            ->sortByDesc('profit')->take(8)->values();

        // ---- По источникам ----
        $bySource = $sold
            ->groupBy(fn ($d) => $d->manual_source ?: 'Не указан')
            ->map(fn ($g, $name) => [
                'name' => $name,
                'count' => $g->count(),
                'revenue' => (float) $g->sum(fn ($d) => (float) ($d->amount ?? 0)),
                'profit' => (float) $g->sum(fn ($d) => (float) ($d->sale_profit ?? 0)),
            ])
            ->sortByDesc('revenue')->values();

        // ---- Ряд для графиков: по дням (до сегодня), а для периодов длиннее 62 дней — по месяцам ----
        $byMonth = $start->diffInDays($endExclusive) > 62;
        $last = Carbon::today()->lt($end) ? Carbon::today() : $end->copy()->startOfDay();
        $series = [];
        for ($d = $start->copy(); $d->lte($last); $byMonth ? $d->addMonthNoOverflow()->startOfMonth() : $d->addDay()) {
            $key = $d->format($byMonth ? 'Y-m' : 'Y-m-d');
            $series[$key] = ['key' => $key, 'revenue' => 0.0, 'profit' => 0.0, 'units' => 0, 'orders' => 0];
        }
        foreach ($sold as $d) {
            $key = $d->stock_deducted_at?->format($byMonth ? 'Y-m' : 'Y-m-d');
            if ($key === null || ! isset($series[$key])) {
                continue;
            }
            $series[$key]['revenue'] += (float) ($d->amount ?? 0);
            $series[$key]['profit'] += (float) ($d->sale_profit ?? 0);
            $series[$key]['units'] += (int) $d->sold_quantity;
            $series[$key]['orders']++;
        }

        return view('owner.dashboard', [
            'productsCount' => $productsCount, 'totalUnits' => $totalUnits, 'availableSum' => $availableSum,
            'stockCostValue' => $stockCostValue, 'stockSaleValue' => $stockSaleValue, 'potentialProfit' => $potentialProfit,
            'inDeliveryUnits' => $inDeliveryUnits, 'inDeliveryValue' => $inDeliveryValue,
            'sold' => $sold, 'revenue' => $revenue, 'profit' => $profit, 'units' => $units, 'count' => $count, 'avg' => $avg,
            'margin' => $margin, 'noCostCount' => $noCostCount, 'topModels' => $topModels, 'bySource' => $bySource,
            'period' => $period, 'periods' => self::PERIODS,
            'fromValue' => $start->toDateString(), 'toValue' => $end->toDateString(),
            'series' => ['by' => $byMonth ? 'month' : 'day', 'rows' => array_values($series)],
        ]);
    }
}
