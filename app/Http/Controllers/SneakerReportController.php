<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\WarehouseItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SneakerReportController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;

        $monthStr = $request->string('month')->toString();
        try {
            $month = $monthStr !== '' ? Carbon::createFromFormat('Y-m', $monthStr)->startOfMonth() : Carbon::now()->startOfMonth();
        } catch (\Throwable) {
            $month = Carbon::now()->startOfMonth();
        }
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();
        $prevStart = $month->copy()->subMonthNoOverflow()->startOfMonth();
        $prevEnd = $month->copy()->subMonthNoOverflow()->endOfMonth();

        // Проданные со склада сделки за диапазон.
        $soldIn = fn ($s, $e) => Deal::query()
            ->where('account_id', $accId)
            ->whereNotNull('stock_deducted_at')
            ->whereBetween('stock_deducted_at', [$s, $e])
            ->with(['warehouseItem:id,brand,model,avg_cost', 'responsible:id,name'])
            ->get();

        $metrics = function ($deals) {
            $revenue = (float) $deals->sum(fn ($d) => (float) ($d->amount ?? 0));
            $cogs = (float) $deals->sum(fn ($d) => (float) ($d->sold_unit_cost ?? 0) * (int) $d->sold_quantity);

            return [
                'revenue' => $revenue,
                'cogs' => $cogs,
                'profit' => $revenue - $cogs,
                'units' => (int) $deals->sum(fn ($d) => (int) $d->sold_quantity),
                'count' => $deals->count(),
                'avg' => $deals->count() ? $revenue / $deals->count() : 0.0,
                'margin' => $revenue > 0 ? ($revenue - $cogs) / $revenue * 100 : 0.0,
            ];
        };

        $current = $soldIn($start, $end);
        $cur = $metrics($current);
        $prev = $metrics($soldIn($prevStart, $prevEnd));

        $pct = fn ($c, $p) => $p > 0 ? (int) round(($c - $p) / $p * 100) : ($c > 0 ? 100 : 0);
        $delta = [
            'revenue' => $pct($cur['revenue'], $prev['revenue']),
            'profit' => $pct($cur['profit'], $prev['profit']),
            'units' => $pct($cur['units'], $prev['units']),
            'avg' => $pct($cur['avg'], $prev['avg']),
        ];

        // Динамика за 6 месяцев.
        $dynamics = collect();
        for ($k = 5; $k >= 0; $k--) {
            $m = $month->copy()->subMonthsNoOverflow($k);
            $mm = $metrics($soldIn($m->copy()->startOfMonth(), $m->copy()->endOfMonth()));
            $dynamics->push(['label' => $m->format('m.y'), 'revenue' => $mm['revenue'], 'profit' => $mm['profit']]);
        }
        $dynMax = max(1, (float) $dynamics->max('revenue'));

        // Воронка продаж (активные сделки по стадиям) + конверсия за период.
        $pipeline = Pipeline::where('account_id', $accId)->orderByDesc('is_default')->first();
        $stages = $pipeline
            ? PipelineStage::where('account_id', $accId)->where('pipeline_id', $pipeline->id)->orderBy('sort')->get()
            : collect();
        $activeAgg = Deal::query()
            ->where('account_id', $accId)
            ->whereNull('closed_at')
            ->selectRaw('stage_id, COUNT(*) c, COALESCE(SUM(amount),0) s')
            ->groupBy('stage_id')->get()->keyBy('stage_id');
        $funnel = $stages->map(fn ($st) => [
            'name' => $st->name,
            'count' => (int) ($activeAgg[$st->id]->c ?? 0),
            'sum' => (float) ($activeAgg[$st->id]->s ?? 0),
            'final' => (bool) $st->is_final,
        ]);
        $pipelineValue = (float) $funnel->sum('sum');
        $pipelineCount = (int) $funnel->sum('count');

        $lostCount = Deal::query()
            ->where('account_id', $accId)
            ->whereIn('closed_result', ['lost', 'extra_non_target'])
            ->whereBetween('closed_at', [$start, $end])->count();
        $wonCount = $cur['count'];
        $conversion = ($wonCount + $lostCount) > 0 ? round($wonCount / ($wonCount + $lostCount) * 100) : null;

        // Закупки и склад.
        $purchaseSpend = (float) Purchase::query()
            ->where('account_id', $accId)
            ->whereNotNull('stocked_at')
            ->whereBetween('stocked_at', [$start, $end])
            ->get()->sum(fn ($p) => (float) ($p->cost ?? 0) * (int) ($p->stocked_quantity ?? $p->quantity));

        $items = WarehouseItem::where('account_id', $accId)->get();
        $stockUnits = (int) $items->sum('quantity');
        $reservedUnits = (int) $items->sum('reserved');
        $stockCostValue = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) ($i->avg_cost ?? 0));
        $stockSaleValue = (float) $items->sum(fn ($i) => (int) $i->quantity * (float) ($i->sale_price ?? 0));
        $openPurchases = Purchase::where('account_id', $accId)->whereNull('closed_at')->count();

        // Топ моделей за период.
        $topModels = $current
            ->groupBy(fn ($d) => $d->warehouseItem ? trim(($d->warehouseItem->brand ?? '').' '.($d->warehouseItem->model ?? '')) : 'Без товара')
            ->map(fn ($g, $name) => [
                'name' => $name !== '' ? $name : 'Без товара',
                'units' => (int) $g->sum(fn ($d) => (int) $d->sold_quantity),
                'revenue' => (float) $g->sum(fn ($d) => (float) ($d->amount ?? 0)),
                'profit' => (float) $g->sum(fn ($d) => (float) ($d->amount ?? 0) - (float) ($d->sold_unit_cost ?? 0) * (int) $d->sold_quantity),
            ])
            ->sortByDesc('units')->take(8)->values();

        // Залежавшийся товар: есть остаток, но нет продаж за 30 дней.
        $lastOut = StockMovement::query()
            ->where('account_id', $accId)->where('type', 'out')
            ->selectRaw('warehouse_item_id, MAX(created_at) last_out')
            ->groupBy('warehouse_item_id')->pluck('last_out', 'warehouse_item_id');
        $deadline = now()->subDays(30);
        $deadStock = $items
            ->filter(fn ($i) => (int) $i->quantity > 0 && (! isset($lastOut[$i->id]) || Carbon::parse($lastOut[$i->id])->lt($deadline)))
            ->map(fn ($i) => [
                'name' => $i->display_name,
                'qty' => (int) $i->quantity,
                'last' => isset($lastOut[$i->id]) ? Carbon::parse($lastOut[$i->id])->format('d.m.Y') : null,
                'value' => (int) $i->quantity * (float) ($i->avg_cost ?? 0),
            ])
            ->sortByDesc('value')->take(10)->values();

        // Дозаказать (низкий остаток).
        $reorder = $items
            ->filter(fn ($i) => $i->is_low)
            ->map(fn ($i) => [
                'name' => $i->display_name,
                'available' => (int) $i->available,
                'threshold' => (int) $i->low_stock_threshold,
                'suggest' => max((int) $i->low_stock_threshold * 2 - (int) $i->available, 1),
            ])
            ->sortBy('available')->values();

        // По сотрудникам за период.
        $byOperator = $current
            ->groupBy('responsible_user_id')
            ->map(fn ($g) => [
                'name' => optional($g->first()->responsible)->name ?? 'Не назначен',
                'count' => $g->count(),
                'units' => (int) $g->sum(fn ($d) => (int) $d->sold_quantity),
                'revenue' => (float) $g->sum(fn ($d) => (float) ($d->amount ?? 0)),
                'profit' => (float) $g->sum(fn ($d) => (float) ($d->amount ?? 0) - (float) ($d->sold_unit_cost ?? 0) * (int) $d->sold_quantity),
            ])
            ->sortByDesc('revenue')->values();

        $returnsCount = Deal::query()->where('account_id', $accId)
            ->whereNotNull('returned_at')->whereBetween('returned_at', [$start, $end])->count();

        $bySource = $current
            ->groupBy(fn ($d) => $d->manual_source ?: 'Не указан')
            ->map(fn ($g, $name) => ['name' => $name, 'count' => $g->count(), 'revenue' => (float) $g->sum(fn ($d) => (float) ($d->amount ?? 0))])
            ->sortByDesc('revenue')->values();

        $soldDeals = $current->sortByDesc('stock_deducted_at')->values();
        $monthValue = $month->format('Y-m');

        return view('reports.sneaker', compact(
            'cur', 'prev', 'delta', 'dynamics', 'dynMax',
            'funnel', 'pipelineValue', 'pipelineCount', 'conversion', 'wonCount', 'lostCount',
            'purchaseSpend', 'stockUnits', 'reservedUnits', 'stockCostValue', 'stockSaleValue', 'openPurchases',
            'topModels', 'deadStock', 'reorder', 'byOperator', 'bySource', 'returnsCount', 'soldDeals', 'monthValue'
        ));
    }

    public function export(Request $request)
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

        $sold = Deal::where('account_id', $user->account_id)
            ->whereNotNull('stock_deducted_at')->whereBetween('stock_deducted_at', [$start, $end])
            ->with('warehouseItem:id,brand,model,avg_cost')->orderBy('stock_deducted_at')->get();

        $rows = [['Дата', 'Сделка', 'Товар', 'Источник', 'Пар', 'Сумма', 'Себест./пара', 'Прибыль', 'Маржа %']];
        foreach ($sold as $d) {
            $cost = $d->unit_cost_basis;
            $profit = $d->sale_profit;
            $margin = $d->sale_margin_percent;
            $rows[] = [
                optional($d->stock_deducted_at)->format('Y-m-d H:i'), $d->title, $d->warehouseItem?->display_name,
                $d->manual_source, $d->sold_quantity, $d->amount,
                $cost !== null ? $cost : '', $profit !== null ? $profit : '', $margin !== null ? $margin : '',
            ];
        }

        return response()->streamDownload(function () use ($rows) {
            echo "\xEF\xBB\xBF";
            $out = fopen('php://output', 'w');
            foreach ($rows as $r) {
                fputcsv($out, $r, ';');
            }
            fclose($out);
        }, 'prodazhi-krossovki-'.$month->format('Y-m').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
