<?php

namespace App\Http\Controllers\Concerns;

use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Период сводок владельца: «Сегодня», «Вчера», 7/30 дней, этот/прошлый месяц или свои даты (period=custom&from&to,
 * их выбирают в календаре). Сводка кроссовок понимает и старые ссылки ?month=YYYY-MM.
 */
trait OwnerPeriod
{
    public const PERIODS = [
        'today' => 'Сегодня',
        'yesterday' => 'Вчера',
        '7d' => '7 дней',
        '30d' => '30 дней',
        'month' => 'Этот месяц',
        'prev_month' => 'Прошлый месяц',
    ];

    /** @return array{0: string, 1: Carbon, 2: Carbon} период [from, to) */
    protected function period(Request $request): array
    {
        $period = (string) $request->query('period', 'month');
        $today = Carbon::today();

        $month = (string) $request->query('month', '');
        if ($month !== '' && ! $request->has('period')) {
            try {
                $from = Carbon::createFromFormat('!Y-m', $month)->startOfMonth();

                return ['custom', $from, $from->copy()->addMonth()];
            } catch (\Throwable) {
            }
        }

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
