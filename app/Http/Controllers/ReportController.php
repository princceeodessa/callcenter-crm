<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function monthly(Request $request)
    {
        $user = Auth::user();
        $month = $request->string('month')->toString();

        // format YYYY-MM, default current
        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        [$y, $m] = explode('-', $month);
        $from = now()->setDate((int)$y, (int)$m, 1)->startOfDay();
        $to = (clone $from)->addMonth()->startOfDay();

        $created = Deal::query()
            ->where('account_id', $user->account_id)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $closedWon = Deal::query()
            ->where('account_id', $user->account_id)
            ->whereNotNull('closed_at')
            ->where('closed_result', 'won')
            ->whereBetween('closed_at', [$from, $to])
            ->count();

        $closedLost = Deal::query()
            ->where('account_id', $user->account_id)
            ->whereNotNull('closed_at')
            ->where('closed_result', 'lost')
            ->whereBetween('closed_at', [$from, $to])
            ->count();

        $callActivities = DealActivity::query()
            ->where('account_id', $user->account_id)
            ->where('type', 'call')
            ->whereBetween('created_at', [$from, $to])
            ->count();

        return view('reports.monthly', [
            'month' => $month,
            'created' => $created,
            'closedWon' => $closedWon,
            'closedLost' => $closedLost,
            'callActivities' => $callActivities,
        ]);
    }
}
