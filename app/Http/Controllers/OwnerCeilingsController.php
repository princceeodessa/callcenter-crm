<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnerPeriod;
use App\Services\Owner\CeilingsKpi;
use App\Services\Owner\ContractProfit;
use App\Services\Owner\HeadWork;
use App\Services\Owner\Marketing\MarketingStats;
use App\Services\Owner\NonClosureSummary;
use App\Support\Export\ContactsExport;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Сводка владельца — вкладка «Потолки». Только для владельца всех бизнесов (users.all_businesses):
 * остальные пользователи пространства не объединяют.
 */
class OwnerCeilingsController extends Controller
{
    use OwnerPeriod;

    public function index(Request $request)
    {
        $user = $request->user();
        abort_unless($user && $user->role === 'sneaker_owner' && $user->all_businesses, 403);

        [$period, $from, $to] = $this->period($request);
        if ($request->query('export') === 'contacts') {
            return ContactsExport::download((int) config('owner.ceilings_account_id'), $from, $to);
        }
        // блоки CRM БлагоДар по одному (страница догружает их параллельно); blagodar — оба, для уже открытых страниц
        switch ($request->query('part')) {
            case 'profit':
                return view('owner._contract_profit', ['profit' => ContractProfit::forPeriod($from, $to)]);
            case 'nonclosures':
                return view('owner._nonclosures', ['nonclosures' => NonClosureSummary::forPeriod($from, $to)]);
            case 'headwork':
                return view('owner._head_work', ['headwork' => HeadWork::forPeriod($from, $to)]);
            case 'blagodar':
                return view('owner._blagodar_blocks', ['profit' => ContractProfit::forPeriod($from, $to), 'nonclosures' => NonClosureSummary::forPeriod($from, $to)]);
        }
        // CRM БлагоДар отвечает по 3–5 с: нет ответа в кэше — страница открывается без него, блоки догружаются следом
        $profit = ContractProfit::cached($from, $to);
        $nonclosures = NonClosureSummary::cached($from, $to);
        $headwork = HeadWork::cached($from, $to);
        $kpi = (new CeilingsKpi((int) config('owner.ceilings_account_id')))->compute($from, $to);

        return view('owner.ceilings', [
            'kpi' => $kpi,
            'ads' => MarketingStats::forPeriod($from, $to),
            // число замеров — по таблице замеров (решение владельца 09.10.2026)
            'measures' => MarketingStats::measurements($from, $to),
            'nonclosures' => $nonclosures,
            // работа главного по замерщикам — готовые итоги CRM БлагоДар (под незаключёнными)
            'headwork' => $headwork,
            // прибыль договоров — готовые итоги CRM БлагоДар (страница и так только владельца всех бизнесов)
            'profit' => $profit,
            'period' => $period,
            'periods' => self::PERIODS,
            'fromValue' => $from->format('Y-m-d'),
            'toValue' => $to->copy()->subDay()->format('Y-m-d'),
            // весь календарный месяц («Отчёт по месяцам») — «отчёт за сентябрь 2026»
            'monthReport' => $from->day === 1 && $to->equalTo($from->copy()->addMonthNoOverflow()) ? $from->translatedFormat('F Y') : null,
        ]);
    }
}
