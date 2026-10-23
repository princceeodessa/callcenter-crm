<?php

namespace App\Http\Controllers;

use App\Services\Owner\SmmReport;
use App\Services\Owner\InstagramStats;
use App\Services\Owner\SmmResults;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Сводка владельца — «SMM-контент»: реклама клипов VK по бизнесу (потолки — БлагоДар, кроссовки — «Шаговый»),
 * с оценкой каждого клипа и рекомендациями, как в отчёте SMM-специалиста. Потолки — только владельцу всех бизнесов
 * (как вкладка «Потолки»), кроссовки — тем, кому открыта сводка кроссовок. SMM-специалист (роль smm) видит только
 * эти страницы своих сообществ (User::smmBusinesses) и «Результат для бизнеса» — что продвижение дало в CRM.
 */
class OwnerSmmController extends Controller
{
    public const PERIODS = [7 => '7 дней', 30 => '30 дней', 60 => '60 дней'];

    public function show(Request $request, string $business)
    {
        abort_unless(isset(SmmReport::BUSINESSES[$business]), 404);
        $user = Auth::user();
        // потолки — владельцу всех бизнесов, кроссовки — тем, кому открыта сводка кроссовок; SMM-специалисту — свои сообщества
        abort_unless($user && in_array($business, $user->smmBusinesses(), true), 403);
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
        $cabinet = SmmReport::cabinet($business, $from, $toExclusive);
        $report = [
            'generated' => now()->format('Y-m-d H:i'),
            'date_from' => $from->toDateString(),
            'date_to' => $toExclusive->copy()->subDay()->toDateString(),
            'period_days' => $days,
            'cabinets' => [$cabinet],
        ];

        return view('owner.smm', [
            'business' => $business,
            'days' => $custom ? null : $days,
            'periods' => self::PERIODS,
            'report' => $report,
            // что продвижение дало в CRM за тот же период (клиенты из ВК, замеры / продажи) — без имён и телефонов
            'results' => SmmResults::forBusiness($business, $from, $toExclusive, SmmResults::spend($cabinet)),
            // Instagram сообщества бизнеса: подписчики и публикации периода (InstagramSource собирает раз в 2 часа)
            'instagram' => InstagramStats::forBusiness($business, $from, $toExclusive),
            'isSmm' => $user->role === 'smm',
            'smmBusinesses' => $user->smmBusinesses(),
        ]);
    }
}
