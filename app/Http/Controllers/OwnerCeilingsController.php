<?php

namespace App\Http\Controllers;

use App\Services\Owner\CeilingsKpi;
use App\Services\Owner\Marketing\MarketingStats;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Сводка владельца — вкладка «Потолки». Только для владельца всех бизнесов (users.all_businesses):
 * остальные пользователи пространства не объединяют.
 */
class OwnerCeilingsController extends Controller
{
    public const PERIODS = [
        'today' => 'Сегодня',
        'yesterday' => 'Вчера',
        '7d' => '7 дней',
        '30d' => '30 дней',
        'month' => 'Этот месяц',
        'prev_month' => 'Прошлый месяц',
    ];

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'sneaker_owner' && $user->all_businesses, 403);

        [$period, $from, $to] = $this->period($request);
        $kpi = (new CeilingsKpi((int) config('owner.ceilings_account_id')))->compute($from, $to);

        return view('owner.ceilings', [
            'kpi' => $kpi,
            'ads' => MarketingStats::forPeriod($from, $to),
            'period' => $period,
            'periods' => self::PERIODS,
            'fromValue' => $from->format('Y-m-d'),
            'toValue' => $to->copy()->subDay()->format('Y-m-d'),
        ]);
    }

    /** @return array{0: string, 1: Carbon, 2: Carbon} период [from, to) */
    private function period(Request $request): array
    {
        $period = (string) $request->query('period', 'month');
        $today = Carbon::today();

        if ($period === 'custom') {
            try {
                $from = Carbon::createFromFormat('Y-m-d', (string) $request->query('from'))->startOfDay();
                $to = Carbon::createFromFormat('Y-m-d', (string) $request->query('to'))->startOfDay()->addDay();
                if ($to->gt($from) && $from->diffInDays($to) <= 400) {
                    return ['custom', $from, $to];
                }
            } catch (\Throwable) {
            }
            $period = 'month';
        }

        return match ($period) {
            'today' => ['today', $today->copy(), $today->copy()->addDay()],
            'yesterday' => ['yesterday', $today->copy()->subDay(), $today->copy()],
            '7d' => ['7d', $today->copy()->subDays(6), $today->copy()->addDay()],
            '30d' => ['30d', $today->copy()->subDays(29), $today->copy()->addDay()],
            'prev_month' => ['prev_month', $today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->startOfMonth()],
            default => ['month', $today->copy()->startOfMonth(), $today->copy()->addDay()],
        };
    }
}
