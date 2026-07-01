<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\Purchase;
use App\Models\StockMovement;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use App\Support\Warehouse\ProductClassifier;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WarehouseAnalyticsController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;
        $now = Carbon::now();

        // --- Продажи со склада за последние 12 месяцев (для сезонности + ABC) ---
        $soldWindowStart = $now->copy()->subMonthsNoOverflow(11)->startOfMonth();
        $sold = Deal::where('account_id', $accId)
            ->whereNotNull('stock_deducted_at')
            ->whereBetween('stock_deducted_at', [$soldWindowStart, $now->copy()->endOfMonth()])
            ->with('warehouseItem:id,brand,model,size')
            ->get();

        // --- Сезонность: по месяцам ---
        $seasonality = collect(range(0, 11))->map(function ($k) use ($now) {
            $m = $now->copy()->subMonthsNoOverflow(11 - $k);
            return [
                'label' => $m->format('m.y'),
                'month' => $m->format('Y-m'),
                'units' => 0,
                'revenue' => 0.0,
                'profit' => 0.0,
                'by_brand' => [],  // brand => units
            ];
        })->keyBy('month');

        $brandsTop = []; // total units per brand across window
        foreach ($sold as $d) {
            $key = optional($d->stock_deducted_at)->format('Y-m');
            if (! isset($seasonality[$key])) continue;
            $units = (int) $d->sold_quantity;
            $revenue = (float) ($d->amount ?? 0);
            $profit = $revenue - (float) ($d->sold_unit_cost ?? 0) * $units;
            $brand = mb_strtoupper($d->warehouseItem?->brand ?: 'Прочее');

            $row = $seasonality[$key];
            $row['units'] += $units;
            $row['revenue'] += $revenue;
            $row['profit'] += $profit;
            $row['by_brand'][$brand] = ($row['by_brand'][$brand] ?? 0) + $units;
            $seasonality[$key] = $row;

            $brandsTop[$brand] = ($brandsTop[$brand] ?? 0) + $units;
        }
        $seasonality = $seasonality->values();
        $seasMax = max(1, (int) $seasonality->max('units'));
        arsort($brandsTop);
        $topBrandsForLegend = array_slice($brandsTop, 0, 5, true);

        // --- Закупки vs продажи (по месяцам, за окно) ---
        $purchasedInWindow = Purchase::where('account_id', $accId)
            ->whereNotNull('stocked_at')
            ->whereBetween('stocked_at', [$soldWindowStart, $now])
            ->get();
        $flowByMonth = $seasonality->map(fn ($m) => ['label' => $m['label'], 'purchased' => 0, 'sold' => (int) $m['units']])->keyBy(fn ($m, $k) => $seasonality[$k]['month']);
        foreach ($purchasedInWindow as $p) {
            $key = optional($p->stocked_at)->format('Y-m');
            if (! isset($flowByMonth[$key])) continue;
            $row = $flowByMonth[$key];
            $row['purchased'] += (int) ($p->stocked_quantity ?: $p->quantity);
            $flowByMonth[$key] = $row;
        }
        $flow = array_values($flowByMonth->all());
        $flowMax = max(1, max(array_column($flow, 'purchased') ?: [0]), max(array_column($flow, 'sold') ?: [0]));

        // --- ABC-анализ по моделям (за окно) по прибыли ---
        $byModel = [];
        foreach ($sold as $d) {
            $key = $d->warehouseItem
                ? mb_strtoupper(trim($d->warehouseItem->brand.' '.$d->warehouseItem->model))
                : 'Прочее';
            if (! isset($byModel[$key])) {
                $byModel[$key] = ['name' => $key, 'units' => 0, 'revenue' => 0.0, 'profit' => 0.0];
            }
            $qty = (int) $d->sold_quantity;
            $rev = (float) ($d->amount ?? 0);
            $byModel[$key]['units'] += $qty;
            $byModel[$key]['revenue'] += $rev;
            $byModel[$key]['profit'] += $rev - (float) ($d->sold_unit_cost ?? 0) * $qty;
        }
        usort($byModel, fn ($a, $b) => $b['profit'] <=> $a['profit']);
        $totalProfit = array_sum(array_column($byModel, 'profit'));
        $abc = ['A' => [], 'B' => [], 'C' => []];
        $cum = 0.0;
        foreach ($byModel as $m) {
            $share = $totalProfit > 0 ? $m['profit'] / $totalProfit : 0.0;
            $cum += $share;
            $klass = $cum <= 0.8 ? 'A' : ($cum <= 0.95 ? 'B' : 'C');
            $m['share'] = $share;
            $m['cum'] = $cum;
            $abc[$klass][] = $m;
        }

        // --- Оборачиваемость и прогноз остатка (по моделям) ---
        // База: продано за 90 дней; средний остаток на день = quantity сейчас.
        $days = 90;
        $recentStart = $now->copy()->subDays($days);
        $recentSold = $sold->filter(fn ($d) => $d->stock_deducted_at && $d->stock_deducted_at->gte($recentStart));
        $soldByModel = [];
        foreach ($recentSold as $d) {
            $key = $d->warehouseItem
                ? mb_strtolower(trim(($d->warehouseItem->brand ?? '').'|'.($d->warehouseItem->model ?? '')))
                : '';
            if ($key === '') continue;
            $soldByModel[$key] = ($soldByModel[$key] ?? 0) + (int) $d->sold_quantity;
        }
        $items = WarehouseItem::where('account_id', $accId)->get();
        $modelsStock = $items->groupBy(fn ($i) => mb_strtolower(trim(($i->brand ?? '').'|'.($i->model ?? ''))));
        $turnover = [];
        foreach ($modelsStock as $key => $group) {
            $first = $group->first();
            $name = trim(($first->brand ?? '').' '.($first->model ?? '')) ?: 'Без названия';
            $stock = (int) $group->sum('quantity');
            $soldQty = (int) ($soldByModel[$key] ?? 0);
            $perDay = $soldQty / $days;
            $daysLeft = $perDay > 0 ? (int) round($stock / $perDay) : null;
            $turnover[] = [
                'name' => $name,
                'stock' => $stock,
                'sold_90d' => $soldQty,
                'per_day' => $perDay,
                'days_left' => $daysLeft,
            ];
        }
        // Быстро оборачиваются (по units/день, ISO по остатку > 0)
        usort($turnover, fn ($a, $b) => $b['per_day'] <=> $a['per_day']);
        $fastMovers = array_slice(array_filter($turnover, fn ($t) => $t['per_day'] > 0), 0, 8);
        // Медленно/мёртвые (нет продаж, но есть остаток)
        $slowMovers = array_slice(array_filter($turnover, fn ($t) => $t['sold_90d'] === 0 && $t['stock'] > 0), 0, 8);
        // Скоро закончатся (days_left != null && <= 21 и в stock > 0)
        $endingSoon = array_values(array_filter($turnover, fn ($t) => $t['days_left'] !== null && $t['days_left'] <= 21 && $t['stock'] > 0));
        usort($endingSoon, fn ($a, $b) => ($a['days_left'] ?? 999) <=> ($b['days_left'] ?? 999));
        $endingSoon = array_slice($endingSoon, 0, 8);

        // --- Топ размеров: продажи vs остаток ---
        $sizeSold = [];
        foreach ($sold as $d) {
            $sz = trim((string) ($d->warehouseItem?->size ?? ''));
            if ($sz === '') continue;
            $sizeSold[$sz] = ($sizeSold[$sz] ?? 0) + (int) $d->sold_quantity;
        }
        $sizeStock = [];
        foreach ($items as $i) {
            $sz = trim((string) ($i->size ?? ''));
            if ($sz === '') continue;
            $sizeStock[$sz] = ($sizeStock[$sz] ?? 0) + (int) $i->quantity;
        }
        $allSizes = array_unique(array_merge(array_keys($sizeSold), array_keys($sizeStock)));
        usort($allSizes, fn ($a, $b) => (float) $a <=> (float) $b);
        $sizesData = array_map(fn ($sz) => [
            'size' => $sz,
            'sold' => $sizeSold[$sz] ?? 0,
            'stock' => $sizeStock[$sz] ?? 0,
        ], $allSizes);
        $sizeMax = max(1, max(array_column($sizesData, 'sold') ?: [0]), max(array_column($sizesData, 'stock') ?: [0]));

        // --- Топ брендов: сколько на складе (пар и денег) ---
        $brandStock = [];
        foreach ($items as $i) {
            $b = mb_strtoupper((string) ($i->brand ?? '') ?: 'Прочее');
            if (! isset($brandStock[$b])) {
                $brandStock[$b] = ['brand' => $b, 'stock_units' => 0, 'stock_value' => 0.0, 'sold_units_12m' => 0];
            }
            $brandStock[$b]['stock_units'] += (int) $i->quantity;
            $brandStock[$b]['stock_value'] += (int) $i->quantity * (float) ($i->sale_price ?? 0);
        }
        foreach ($brandsTop as $brand => $u) {
            if (! isset($brandStock[$brand])) {
                $brandStock[$brand] = ['brand' => $brand, 'stock_units' => 0, 'stock_value' => 0.0, 'sold_units_12m' => 0];
            }
            $brandStock[$brand]['sold_units_12m'] = $u;
        }
        usort($brandStock, fn ($a, $b) => $b['stock_units'] <=> $a['stock_units']);

        // --- Разбивка склада по категориям / полу / сезону ---
        $products = WarehouseProduct::where('account_id', $accId)->get();
        $productMap = [];
        foreach ($products as $p) {
            $key = mb_strtolower(trim(($p->brand ?? '').'|'.($p->model ?? '')));
            $productMap[$key] = $p;
        }
        $taxonomy = [
            'category' => [
                'options' => ProductClassifier::categoryOptions(),
                'stock' => [], 'sold' => [], 'unset' => 0,
            ],
            'gender' => [
                'options' => ProductClassifier::genderOptions(),
                'stock' => [], 'sold' => [], 'unset' => 0,
            ],
            'season' => [
                'options' => ProductClassifier::seasonOptions(),
                'stock' => [], 'sold' => [], 'unset' => 0,
            ],
        ];
        foreach ($items as $i) {
            $key = mb_strtolower(trim(($i->brand ?? '').'|'.($i->model ?? '')));
            $p = $productMap[$key] ?? null;
            foreach (['category', 'gender', 'season'] as $tax) {
                $v = $p?->{$tax};
                if ($v) {
                    $taxonomy[$tax]['stock'][$v] = ($taxonomy[$tax]['stock'][$v] ?? 0) + (int) $i->quantity;
                } else {
                    $taxonomy[$tax]['unset'] += (int) $i->quantity;
                }
            }
        }
        foreach ($sold as $d) {
            $wi = $d->warehouseItem;
            if (! $wi) continue;
            $key = mb_strtolower(trim(($wi->brand ?? '').'|'.($wi->model ?? '')));
            $p = $productMap[$key] ?? null;
            $qty = (int) $d->sold_quantity;
            foreach (['category', 'gender', 'season'] as $tax) {
                $v = $p?->{$tax};
                if ($v) $taxonomy[$tax]['sold'][$v] = ($taxonomy[$tax]['sold'][$v] ?? 0) + $qty;
            }
        }

        return view('warehouse.analytics', compact(
            'seasonality', 'seasMax', 'topBrandsForLegend',
            'flow', 'flowMax',
            'abc', 'totalProfit',
            'fastMovers', 'slowMovers', 'endingSoon',
            'sizesData', 'sizeMax',
            'brandStock', 'taxonomy'
        ));
    }
}
