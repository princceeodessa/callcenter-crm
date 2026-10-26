<?php

namespace App\Services\Owner;

use Carbon\Carbon;

/**
 * Общие расходы потолков за период — то, что не входит в расходы договоров CRM БлагоДар (монтаж, материалы, отделы,
 * налоги): кассиры, маркетинг, радио, газеты, 2ГИС и реклама из сборщиков CRM. «Заработаем» минус они — «Чистыми останется».
 * Суммы — config('owner.overhead'): кассиры за день × дни периода, остальное в месяц — за неполный месяц по дням.
 * Будущие дни периода («этот месяц» до сегодня включительно) не считаются: расход ещё не случился.
 */
class Overhead
{
    /**
     * @param  float  $adSpend  реклама из CRM за тот же период (MarketingStats::forPeriod()['spend_total'])
     * @return array{days: int, items: list<array{key: string, label: string, sum: float, note: string}>, total: float}
     */
    public static function forPeriod(Carbon $from, Carbon $toExclusive, float $adSpend): array
    {
        $cfg = (array) config('owner.overhead');
        $end = $toExclusive->copy()->min(Carbon::tomorrow());
        $days = $end->gt($from) ? (int) $from->diffInDays($end) : 0;
        $rub = fn (float $v) => number_format($v, 0, ',', ' ').' ₽';

        $items = [];
        $perDay = (float) ($cfg['cashiers_per_day'] ?? 0);
        if ($perDay > 0) {
            $items[] = ['key' => 'cashiers', 'label' => 'Кассиры', 'sum' => $perDay * $days, 'note' => $rub($perDay).' × '.$days.' '.self::dayWord($days)];
        }

        // месячные суммы — по дням каждого месяца периода
        [$share, $partial] = self::monthShare($from, $end);
        foreach ((array) ($cfg['monthly'] ?? []) as $key => [$label, $perMonth]) {
            $items[] = ['key' => (string) $key, 'label' => (string) $label, 'sum' => round((float) $perMonth * $share, 2),
                'note' => $rub((float) $perMonth).' в месяц'.($partial ? ' · за '.$days.' '.self::dayWord($days) : '')];
        }

        $items[] = ['key' => 'ads', 'label' => 'Реклама', 'sum' => $adSpend, 'note' => 'Директ, VK, Авито — из CRM'];

        return ['days' => $days, 'items' => $items, 'total' => round(array_sum(array_column($items, 'sum')), 2)];
    }

    /** Доля месяцев в [from, end): целый месяц = 1; @return array{0: float, 1: bool} [доля, есть ли неполный месяц] */
    private static function monthShare(Carbon $from, Carbon $end): array
    {
        $share = 0.0;
        $partial = false;
        for ($m = $from->copy()->startOfMonth(); $m->lt($end); $m->addMonthNoOverflow()) {
            $next = $m->copy()->addMonthNoOverflow();
            $a = $from->max($m);
            $b = $end->copy()->min($next);
            if ($b->lte($a)) {
                continue;
            }
            $covered = (int) $a->diffInDays($b);
            $share += $covered / $m->daysInMonth;
            $partial = $partial || $covered < $m->daysInMonth;
        }

        return [$share, $partial];
    }

    private static function dayWord(int $n): string
    {
        $k = $n % 100;
        $k1 = $n % 10;

        return $k > 10 && $k < 20 ? 'дней' : ($k1 === 1 ? 'день' : ($k1 > 1 && $k1 < 5 ? 'дня' : 'дней'));
    }
}
