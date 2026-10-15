<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\OwnerPeriod;
use App\Services\Owner\CeilingsKpi;
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
        $kpi = (new CeilingsKpi((int) config('owner.ceilings_account_id')))->compute($from, $to);

        return view('owner.ceilings', [
            'kpi' => $kpi,
            'ads' => MarketingStats::forPeriod($from, $to),
            // число замеров — по таблице замеров (решение владельца 09.10.2026)
            'measures' => MarketingStats::measurements($from, $to),
            'nonclosures' => NonClosureSummary::forPeriod($from, $to),
            'period' => $period,
            'periods' => self::PERIODS,
            'fromValue' => $from->format('Y-m-d'),
            'toValue' => $to->copy()->subDay()->format('Y-m-d'),
        ]);
    }
}
