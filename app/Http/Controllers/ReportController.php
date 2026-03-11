<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Measurement;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReportController extends Controller
{
    public function monthly(Request $request)
    {
        $user = Auth::user();
        $month = $request->string('month')->toString();

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = now()->format('Y-m');
        }

        [$y, $m] = explode('-', $month);
        $from = now()->setDate((int)$y, (int)$m, 1)->startOfDay();
        $to = (clone $from)->addMonth()->startOfDay();

        $role = (string)$user->role;
        $isManager = in_array($role, ['admin', 'main_operator'], true);
        $isMeasurer = $role === 'measurer';

        $operatorRoles = ['admin', 'main_operator', 'operator'];

        $operatorUsers = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', true)
            ->whereIn('role', $operatorRoles)
            ->orderByRaw("FIELD(role,'operator','main_operator','admin')")
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        $measurerUsers = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', true)
            ->where('role', 'measurer')
            ->orderBy('name')
            ->get(['id', 'name', 'role']);

        if ($isMeasurer) {
            $measurementRows = $this->measurementCompletionRows($measurerUsers->where('id', $user->id), $from, $to);
            $measurementSummary = $this->summarizeMeasurementCompletionRows($measurementRows);

            return view('reports.monthly', [
                'month' => $month,
                'mode' => 'measurer',
                'isManager' => $isManager,
                'operatorSummary' => null,
                'operatorRows' => collect(),
                'measurementSummary' => $measurementSummary,
                'measurementRows' => $measurementRows,
            ]);
        }

        if ($isManager) {
            $operatorRows = $this->operatorRows($operatorUsers, $from, $to);
            $operatorSummary = $this->summarizeOperatorRows($operatorRows);
            $measurementRows = $this->measurementRows($measurerUsers, $from, $to);
            $measurementSummary = $this->summarizeMeasurementRows($measurementRows);

            return view('reports.monthly', [
                'month' => $month,
                'mode' => 'manager',
                'isManager' => true,
                'operatorSummary' => $operatorSummary,
                'operatorRows' => $operatorRows,
                'measurementSummary' => $measurementSummary,
                'measurementRows' => $measurementRows,
            ]);
        }

        $operatorRows = $this->operatorRows($operatorUsers->where('id', $user->id), $from, $to);
        $operatorSummary = $this->summarizeOperatorRows($operatorRows);

        return view('reports.monthly', [
            'month' => $month,
            'mode' => 'operator',
            'isManager' => false,
            'operatorSummary' => $operatorSummary,
            'operatorRows' => $operatorRows,
            'measurementSummary' => null,
            'measurementRows' => collect(),
        ]);
    }

    private function measurementCompletionRows(Collection $users, $from, $to): Collection
    {
        return $users->values()->map(function (User $u) use ($from, $to) {
            $base = Measurement::query()
                ->where('account_id', $u->account_id)
                ->where('assigned_user_id', $u->id)
                ->whereBetween('scheduled_at', [$from, $to]);

            $completed = (clone $base)->whereIn('status', [
                'concluded',
                'not_concluded',
                'done',
                'refused_after_measurement',
            ])->count();

            $notCompleted = (clone $base)->whereIn('status', [
                'planned',
                'accepted',
                'confirmed',
                'cancelled',
            ])->count();

            $total = $completed + $notCompleted;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'completed' => $completed,
                'notCompleted' => $notCompleted,
                'total' => $total,
                'completionRate' => $total > 0 ? round(($completed / $total) * 100, 1) : null,
            ];
        });
    }

    private function summarizeMeasurementCompletionRows(Collection $rows): array
    {
        $completed = (int) $rows->sum('completed');
        $notCompleted = (int) $rows->sum('notCompleted');
        $total = $completed + $notCompleted;

        return [
            'completed' => $completed,
            'notCompleted' => $notCompleted,
            'total' => $total,
            'completionRate' => $total > 0 ? round(($completed / $total) * 100, 1) : null,
        ];
    }

    private function operatorRows(Collection $users, $from, $to): Collection
    {
        return $users->values()->map(function (User $u) use ($from, $to) {
            $created = Deal::query()
                ->where('account_id', $u->account_id)
                ->where('responsible_user_id', $u->id)
                ->whereBetween('created_at', [$from, $to])
                ->count();

            $closedWon = Deal::query()
                ->where('account_id', $u->account_id)
                ->where('responsible_user_id', $u->id)
                ->where('closed_result', 'won')
                ->whereNotNull('closed_at')
                ->whereBetween('closed_at', [$from, $to])
                ->count();

            $closedLost = Deal::query()
                ->where('account_id', $u->account_id)
                ->where('responsible_user_id', $u->id)
                ->where('closed_result', 'lost')
                ->whereNotNull('closed_at')
                ->whereBetween('closed_at', [$from, $to])
                ->count();

            $callActivities = DealActivity::query()
                ->where('account_id', $u->account_id)
                ->where('type', 'call')
                ->whereBetween('created_at', [$from, $to])
                ->whereHas('deal', function ($q) use ($u) {
                    $q->where('responsible_user_id', $u->id);
                })
                ->count();

            $winBase = $closedWon + $closedLost;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role,
                'created' => $created,
                'closedWon' => $closedWon,
                'closedLost' => $closedLost,
                'callActivities' => $callActivities,
                'winRate' => $winBase > 0 ? round(($closedWon / $winBase) * 100, 1) : null,
            ];
        });
    }

    private function summarizeOperatorRows(Collection $rows): array
    {
        $created = (int)$rows->sum('created');
        $closedWon = (int)$rows->sum('closedWon');
        $closedLost = (int)$rows->sum('closedLost');
        $callActivities = (int)$rows->sum('callActivities');
        $winBase = $closedWon + $closedLost;

        return [
            'created' => $created,
            'closedWon' => $closedWon,
            'closedLost' => $closedLost,
            'callActivities' => $callActivities,
            'winRate' => $winBase > 0 ? round(($closedWon / $winBase) * 100, 1) : null,
        ];
    }

    private function measurementRows(Collection $users, $from, $to): Collection
    {
        return $users->values()->map(function (User $u) use ($from, $to) {
            $base = Measurement::query()
                ->where('account_id', $u->account_id)
                ->where('assigned_user_id', $u->id)
                ->whereBetween('scheduled_at', [$from, $to]);

            $successful = (clone $base)->whereIn('status', ['concluded', 'done'])->count();
            $refused = (clone $base)->whereIn('status', ['not_concluded', 'refused_after_measurement'])->count();
            $cancelled = (clone $base)->where('status', 'cancelled')->count();
            $planned = (clone $base)->whereIn('status', ['planned', 'accepted', 'confirmed'])->count();
            $baseForResult = $successful + $refused;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'successful' => $successful,
                'refused' => $refused,
                'cancelled' => $cancelled,
                'planned' => $planned,
                'resultBase' => $baseForResult,
                'successRate' => $baseForResult > 0 ? round(($successful / $baseForResult) * 100, 1) : null,
            ];
        });
    }

    private function summarizeMeasurementRows(Collection $rows): array
    {
        $successful = (int)$rows->sum('successful');
        $refused = (int)$rows->sum('refused');
        $cancelled = (int)$rows->sum('cancelled');
        $planned = (int)$rows->sum('planned');
        $baseForResult = $successful + $refused;

        return [
            'successful' => $successful,
            'refused' => $refused,
            'cancelled' => $cancelled,
            'planned' => $planned,
            'resultBase' => $baseForResult,
            'successRate' => $baseForResult > 0 ? round(($successful / $baseForResult) * 100, 1) : null,
        ];
    }
}
