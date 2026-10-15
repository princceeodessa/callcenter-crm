<?php

namespace App\Http\Controllers;

use App\Services\Owner\SmmReport;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Сводка владельца — «SMM-контент»: реклама клипов VK по бизнесу (потолки — БлагоДар, кроссовки — «Шаговый»),
 * с оценкой каждого клипа и рекомендациями, как в отчёте SMM-специалиста. Потолки — только владельцу всех бизнесов
 * (как вкладка «Потолки»), кроссовки — тем, кому открыта сводка кроссовок.
 */
class OwnerSmmController extends Controller
{
    public const PERIODS = [7 => '7 дней', 30 => '30 дней', 60 => '60 дней'];

    public function show(Request $request, string $business)
    {
        abort_unless(isset(SmmReport::BUSINESSES[$business]), 404);
        $user = Auth::user();
        if ($business === 'ceilings') {
            abort_unless($user && $user->role === 'sneaker_owner' && $user->all_businesses, 403);
        }
        $days = (int) $request->query('days', 60);
        if (! isset(self::PERIODS[$days])) {
            $days = 60;
        }
        $today = Carbon::today();
        $from = $today->copy()->subDays($days - 1);
        $toExclusive = $today->copy()->addDay();
        // свои даты из календаря (from/to) — вместо 7/30/60 дней
        $custom = false;
        try {
            $f = Carbon::createFromFormat('!Y-m-d', (string) $request->query('from'));
            $t = Carbon::createFromFormat('!Y-m-d', (string) $request->query('to'))->addDay();
            if ($t->gt($f) && $f->diffInDays($t) <= 400) {
                [$from, $toExclusive, $custom] = [$f, $t, true];
                $days = (int) $f->diffInDays($t);
            }
        } catch (\Throwable) {
        }
        $report = [
            'generated' => now()->format('Y-m-d H:i'),
            'date_from' => $from->toDateString(),
            'date_to' => $toExclusive->copy()->subDay()->toDateString(),
            'period_days' => $days,
            'cabinets' => [SmmReport::cabinet($business, $from, $toExclusive)],
        ];

        return view('owner.smm', [
            'business' => $business,
            'days' => $custom ? null : $days,
            'periods' => self::PERIODS,
            'report' => $report,
        ]);
    }
}
