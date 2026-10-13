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
        $report = [
            'generated' => now()->format('Y-m-d H:i'),
            'date_from' => $from->toDateString(),
            'date_to' => $today->toDateString(),
            'period_days' => $days,
            'cabinets' => [SmmReport::cabinet($business, $from, $today->copy()->addDay())],
        ];

        return view('owner.smm', [
            'business' => $business,
            'days' => $days,
            'periods' => self::PERIODS,
            'report' => $report,
            'canBoth' => $user && $user->role === 'sneaker_owner' && $user->all_businesses,
        ]);
    }
}
