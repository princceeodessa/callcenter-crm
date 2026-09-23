<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Продажи кроссовок за один день: что продали, кто, откуда клиент, по часам,
 * плюс сравнение со вчера и с тем же днём прошлой недели.
 *
 * Продажа = сделка, списанная со склада (`stock_deducted_at`), — тот же критерий,
 * что в отчёте и в «Быстрой продаже». Возврат обнуляет `stock_deducted_at`,
 * поэтому возвращённая пара из продаж дня пропадает сама.
 */
class SneakerDailySalesController extends Controller
{
    /** Сколько дней показывать в полосе «последние дни». */
    private const STRIP_DAYS = 14;

    public function index(Request $request)
    {
        $user = Auth::user();
        $accId = $user->account_id;
        $isHead = $user->role === 'sneaker_head';

        $today = Carbon::today();
        try {
            $raw = $request->string('date')->toString();
            $day = $raw !== '' ? Carbon::createFromFormat('Y-m-d', $raw)->startOfDay() : $today->copy();
        } catch (\Throwable) {
            $day = $today->copy();
        }
        if ($day->gt($today)) {
            $day = $today->copy();
        }

        $sales = $this->salesBetween($accId, $day->copy()->startOfDay(), $day->copy()->endOfDay());
        $cur = $this->metrics($sales);

        $yesterday = $day->copy()->subDay();
        $weekAgo = $day->copy()->subWeek();
        $vsYesterday = $this->metrics($this->salesBetween($accId, $yesterday->copy()->startOfDay(), $yesterday->copy()->endOfDay()));
        $vsWeekAgo = $this->metrics($this->salesBetween($accId, $weekAgo->copy()->startOfDay(), $weekAgo->copy()->endOfDay()));

        // Возвраты, оформленные в этот день (сами продажи могли быть раньше).
        $returns = Deal::query()
            ->where('account_id', $accId)
            ->whereNotNull('returned_at')
            ->whereBetween('returned_at', [$day->copy()->startOfDay(), $day->copy()->endOfDay()])
            ->with('warehouseItem:id,brand,model,size')
            ->orderByDesc('returned_at')
            ->get();

        // По часам: диапазон рабочего дня, расширяется, если продажи были раньше/позже.
        $byHourRaw = $sales->groupBy(fn (Deal $d) => (int) $d->stock_deducted_at->format('G'));
        $fromHour = min(10, $byHourRaw->keys()->min() ?? 10);
        $toHour = max(21, $byHourRaw->keys()->max() ?? 21);
        $byHour = collect(range($fromHour, $toHour))->map(fn (int $h) => [
            'hour' => $h,
            'units' => (int) ($byHourRaw[$h] ?? collect())->sum('sold_quantity'),
            'revenue' => (float) ($byHourRaw[$h] ?? collect())->sum(fn (Deal $d) => (float) ($d->amount ?? 0)),
        ]);
        $hourMax = max(1, (int) $byHour->max('units'));

        $bySeller = $sales
            ->groupBy('responsible_user_id')
            ->map(fn (Collection $g) => [
                'name' => optional($g->first()->responsible)->name ?? 'Не назначен',
            ] + $this->metrics($g))
            ->sortByDesc('revenue')->values();

        $bySource = $sales
            ->groupBy(fn (Deal $d) => $d->manual_source ?: 'Не указан')
            ->map(fn (Collection $g, string $name) => ['name' => $name] + $this->metrics($g))
            ->sortByDesc('revenue')->values();

        // Полоса последних дней — одним запросом, для перехода по дням кликом.
        $stripStart = $day->copy()->subDays(self::STRIP_DAYS - 1)->startOfDay();
        $stripAgg = Deal::query()
            ->where('account_id', $accId)
            ->whereNotNull('stock_deducted_at')
            ->whereBetween('stock_deducted_at', [$stripStart, $day->copy()->endOfDay()])
            ->selectRaw('DATE(stock_deducted_at) d, COALESCE(SUM(sold_quantity),0) units, COALESCE(SUM(amount),0) revenue')
            ->groupBy('d')
            ->get()->keyBy('d');
        $strip = collect(range(0, self::STRIP_DAYS - 1))->map(function (int $i) use ($stripStart, $stripAgg, $day) {
            $d = $stripStart->copy()->addDays($i);
            $row = $stripAgg[$d->toDateString()] ?? null;

            return [
                'date' => $d->toDateString(),
                'label' => $d->format('d.m'),
                'weekday' => $d->locale('ru')->isoFormat('dd'),
                'units' => (int) ($row->units ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
                'active' => $d->isSameDay($day),
                'weekend' => $d->isWeekend(),
            ];
        });
        $stripMax = max(1, (float) $strip->max('revenue'));

        return view('sale.day', [
            'day' => $day,
            'isToday' => $day->isSameDay($today),
            'prevDate' => $day->copy()->subDay()->toDateString(),
            'nextDate' => $day->lt($today) ? $day->copy()->addDay()->toDateString() : null,
            'sales' => $sales->sortByDesc('stock_deducted_at')->values(),
            'cur' => $cur,
            'vsYesterday' => $vsYesterday,
            'vsWeekAgo' => $vsWeekAgo,
            'returns' => $returns,
            'byHour' => $byHour,
            'hourMax' => $hourMax,
            'bySeller' => $bySeller,
            'bySource' => $bySource,
            'strip' => $strip,
            'stripMax' => $stripMax,
            'isHead' => $isHead,
        ]);
    }

    /** @return Collection<int, Deal> */
    private function salesBetween(int $accId, Carbon $from, Carbon $to): Collection
    {
        return Deal::query()
            ->where('account_id', $accId)
            ->whereNotNull('stock_deducted_at')
            ->whereBetween('stock_deducted_at', [$from, $to])
            ->with(['warehouseItem:id,brand,model,size,avg_cost', 'responsible:id,name', 'contact:id,name,phone'])
            ->get();
    }

    /**
     * Сводка по набору продаж. Прибыль считается только там, где известна
     * себестоимость пары, — иначе продажа без закупочной цены дала бы «100% маржи».
     *
     * @param  Collection<int, Deal>  $deals
     * @return array{revenue: float, units: int, count: int, avg: float, profit: float, margin: ?float, no_cost: int}
     */
    private function metrics(Collection $deals): array
    {
        $revenue = (float) $deals->sum(fn (Deal $d) => (float) ($d->amount ?? 0));
        $withProfit = $deals->filter(fn (Deal $d) => $d->sale_profit !== null);
        $profit = (float) $withProfit->sum(fn (Deal $d) => $d->sale_profit);
        $profitRevenue = (float) $withProfit->sum(fn (Deal $d) => (float) $d->amount);

        return [
            'revenue' => $revenue,
            'units' => (int) $deals->sum(fn (Deal $d) => (int) $d->sold_quantity),
            'count' => $deals->count(),
            'avg' => $deals->count() ? $revenue / $deals->count() : 0.0,
            'profit' => $profit,
            'margin' => $profitRevenue > 0 ? round($profit / $profitRevenue * 100, 1) : null,
            'no_cost' => $deals->count() - $withProfit->count(),
        ];
    }
}
