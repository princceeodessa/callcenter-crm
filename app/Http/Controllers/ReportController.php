<?php

namespace App\Http\Controllers;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\Measurement;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function monthly(Request $request)
    {
        $user = Auth::user();
        $month = $request->string('month')->toString();
        $callSourceOptions = Deal::incomingPhoneSourceOptions();
        $requestedMonth = $month;

        if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
            $month = $this->resolveDefaultMonth($user);
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
            $dataPeriodHint = $this->measurementDataPeriodHint($requestedMonth, $month, $measurementSummary['total'], $user);

            return view('reports.monthly', [
                'month' => $month,
                'mode' => 'measurer',
                'isManager' => $isManager,
                'callSourceOptions' => $callSourceOptions,
                'dataPeriodHint' => $dataPeriodHint,
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
            $dataPeriodHint = $this->operatorDataPeriodHint($requestedMonth, $month, $operatorSummary, $user);

            return view('reports.monthly', [
                'month' => $month,
                'mode' => 'manager',
                'isManager' => true,
                'callSourceOptions' => $callSourceOptions,
                'dataPeriodHint' => $dataPeriodHint,
                'operatorSummary' => $operatorSummary,
                'operatorRows' => $operatorRows,
                'measurementSummary' => $measurementSummary,
                'measurementRows' => $measurementRows,
            ]);
        }

        $operatorRows = $this->operatorRows($operatorUsers->where('id', $user->id), $from, $to);
        $operatorSummary = $this->summarizeOperatorRows($operatorRows);
        $dataPeriodHint = $this->operatorDataPeriodHint($requestedMonth, $month, $operatorSummary, $user);

        return view('reports.monthly', [
            'month' => $month,
            'mode' => 'operator',
            'isManager' => false,
            'callSourceOptions' => $callSourceOptions,
            'dataPeriodHint' => $dataPeriodHint,
            'operatorSummary' => $operatorSummary,
            'operatorRows' => $operatorRows,
            'measurementSummary' => null,
            'measurementRows' => collect(),
        ]);
    }

    private function resolveDefaultMonth(User $user): string
    {
        $latest = $this->latestRelevantMomentForUser($user);

        return ($latest ?? now())->format('Y-m');
    }

    private function measurementCompletionRows(Collection $users, $from, $to): Collection
    {
        return $users->values()->map(function (User $u) use ($createdDeals, $closedDeals, $stageHistoryRows, $callActivities, $dealResponsibleMap) {
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
        $accountId = (int) $users->first()?->account_id;

        if ($accountId === 0) {
            return collect();
        }

        $createdDeals = Deal::query()
            ->where('account_id', $accountId)
            ->whereBetween('created_at', [$from, $to])
            ->get(['id', 'responsible_user_id']);

        $closedDeals = Deal::query()
            ->where('account_id', $accountId)
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$from, $to])
            ->get(['id', 'responsible_user_id', 'closed_by_user_id', 'closed_result']);

        $stageHistoryRows = DealStageHistory::query()
            ->where('account_id', $accountId)
            ->whereBetween('changed_at', [$from, $to])
            ->get(['deal_id', 'changed_by_user_id']);

        $callActivities = DealActivity::query()
            ->where('account_id', $accountId)
            ->where('type', 'call')
            ->whereBetween('created_at', [$from, $to])
            ->get(['deal_id', 'payload']);

        $dealResponsibleMap = Deal::query()
            ->where('account_id', $accountId)
            ->pluck('responsible_user_id', 'id')
            ->map(fn ($value) => $value !== null ? (int) $value : null)
            ->all();

        return $users->values()->map(function (User $u) use ($from, $to) {
            $createdDealIds = $createdDeals
                ->filter(fn (Deal $deal) => (int) $deal->responsible_user_id === (int) $u->id)
                ->pluck('id');

            $stageChangedDealIds = $stageHistoryRows
                ->filter(fn (DealStageHistory $row) => (int) $row->changed_by_user_id === (int) $u->id)
                ->pluck('deal_id');

            $closedByUser = $closedDeals->filter(function (Deal $deal) use ($u) {
                if ((int) ($deal->closed_by_user_id ?? 0) === (int) $u->id) {
                    return true;
                }

                return $deal->closed_by_user_id === null
                    && (int) ($deal->responsible_user_id ?? 0) === (int) $u->id;
            });

            $userCallActivities = $callActivities->filter(function (DealActivity $activity) use ($u, $dealResponsibleMap) {
                return $this->callActivityBelongsToUser($activity, $u, $dealResponsibleMap);
            })->values();

            $processedDealIds = $createdDealIds
                ->merge($stageChangedDealIds)
                ->merge($closedByUser->pluck('id'))
                ->merge($userCallActivities->pluck('deal_id'))
                ->filter()
                ->unique()
                ->values();

            $closedWon = $closedByUser->where('closed_result', 'won')->count();
            $closedLost = $closedByUser->where('closed_result', 'lost')->count();
            $callSourceStats = $this->callSourceStats($userCallActivities);

            $winBase = $closedWon + $closedLost;

            return [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role,
                'created' => $processedDealIds->count(),
                'closedWon' => $closedWon,
                'closedLost' => $closedLost,
                'callActivities' => $userCallActivities->count(),
                'callSourceCounts' => $callSourceStats['counts'],
                'uncategorizedCallActivities' => $callSourceStats['uncategorized'],
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
        $callSourceCounts = Deal::emptyIncomingPhoneSourceCounts();
        foreach ($rows as $row) {
            foreach ($callSourceCounts as $key => $value) {
                $callSourceCounts[$key] += (int) ($row['callSourceCounts'][$key] ?? 0);
            }
        }

        $uncategorizedCallActivities = (int) $rows->sum('uncategorizedCallActivities');
        $winBase = $closedWon + $closedLost;

        return [
            'created' => $created,
            'closedWon' => $closedWon,
            'closedLost' => $closedLost,
            'callActivities' => $callActivities,
            'callSourceCounts' => $callSourceCounts,
            'uncategorizedCallActivities' => $uncategorizedCallActivities,
            'winRate' => $winBase > 0 ? round(($closedWon / $winBase) * 100, 1) : null,
        ];
    }

    private function callSourceStats(Collection $activities): array
    {
        $counts = Deal::emptyIncomingPhoneSourceCounts();
        $uncategorized = 0;

        foreach ($activities as $activity) {
            $key = Deal::resolveIncomingPhoneSourceFilterKeyFromPayload(
                is_array($activity->payload ?? null) ? $activity->payload : []
            );

            if ($key !== null && array_key_exists($key, $counts)) {
                $counts[$key]++;
                continue;
            }

            $uncategorized++;
        }

        return [
            'counts' => $counts,
            'uncategorized' => $uncategorized,
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

    private function operatorDataPeriodHint(string $requestedMonth, string $resolvedMonth, array $summary, User $user): ?string
    {
        $hasData = ((int) ($summary['created'] ?? 0))
            + ((int) ($summary['closedWon'] ?? 0))
            + ((int) ($summary['closedLost'] ?? 0))
            + ((int) ($summary['callActivities'] ?? 0)) > 0;

        if ($hasData) {
            return null;
        }

        $latest = $this->latestRelevantMomentForUser($user);
        if ($latest === null) {
            if (!in_array((string) $user->role, ['admin', 'main_operator'], true)) {
                $accountLatest = $this->latestAccountMoment((int) $user->account_id);
                if ($accountLatest !== null) {
                    return 'В аккаунте есть данные за '.$accountLatest->translatedFormat('F Y').', но личный отчёт оператора считает только сделки и звонки, где вы указаны ответственным.';
                }
            }

            return null;
        }

        $latestMonth = $latest->format('Y-m');
        if ($requestedMonth === '' && $latestMonth === $resolvedMonth) {
            return null;
        }

        if ($latestMonth === $resolvedMonth) {
            return null;
        }

        return 'За '.Carbon::createFromFormat('Y-m', $resolvedMonth)->translatedFormat('F Y').' данных нет. Последние данные есть за '.Carbon::createFromFormat('Y-m', $latestMonth)->translatedFormat('F Y').'.';
    }

    private function measurementDataPeriodHint(string $requestedMonth, string $resolvedMonth, int $total, User $user): ?string
    {
        if ($total > 0) {
            return null;
        }

        $latest = $this->latestRelevantMomentForUser($user);
        if ($latest === null) {
            return null;
        }

        $latestMonth = $latest->format('Y-m');
        if ($requestedMonth === '' && $latestMonth === $resolvedMonth) {
            return null;
        }

        if ($latestMonth === $resolvedMonth) {
            return null;
        }

        return 'За '.Carbon::createFromFormat('Y-m', $resolvedMonth)->translatedFormat('F Y').' данных нет. Последние данные есть за '.Carbon::createFromFormat('Y-m', $latestMonth)->translatedFormat('F Y').'.';
    }

    private function latestRelevantMomentForUser(User $user): ?Carbon
    {
        $role = (string) $user->role;

        if ($role === 'measurer') {
            return $this->maxCarbonValue([
                Measurement::query()
                    ->where('account_id', $user->account_id)
                    ->where('assigned_user_id', $user->id)
                    ->max('scheduled_at'),
            ]);
        }

        if (in_array($role, ['admin', 'main_operator'], true)) {
            return $this->latestAccountMoment((int) $user->account_id);
        }

        return $this->maxCarbonValue([
            Deal::query()
                ->where('account_id', $user->account_id)
                ->where('responsible_user_id', $user->id)
                ->max('created_at'),
            Deal::query()
                ->where('account_id', $user->account_id)
                ->where('responsible_user_id', $user->id)
                ->max('closed_at'),
            DealActivity::query()
                ->where('account_id', $user->account_id)
                ->where('type', 'call')
                ->whereHas('deal', function ($query) use ($user) {
                    $query->where('responsible_user_id', $user->id);
                })
                ->max('created_at'),
        ]);
    }

    private function maxCarbonValue(array $values): ?Carbon
    {
        $moments = collect($values)
            ->filter(fn ($value) => filled($value))
            ->map(function ($value) {
                try {
                    return Carbon::parse($value);
                } catch (\Throwable) {
                    return null;
                }
            })
            ->filter();

        if ($moments->isEmpty()) {
            return null;
        }

        return $moments->sortByDesc(fn (Carbon $moment) => $moment->getTimestamp())->first();
    }

    private function latestAccountMoment(int $accountId): ?Carbon
    {
        return $this->maxCarbonValue([
            Deal::query()->where('account_id', $accountId)->max('created_at'),
            Deal::query()->where('account_id', $accountId)->max('closed_at'),
            DealActivity::query()
                ->where('account_id', $accountId)
                ->where('type', 'call')
                ->max('created_at'),
            Measurement::query()->where('account_id', $accountId)->max('scheduled_at'),
        ]);
    }

    private function callActivityBelongsToUser(DealActivity $activity, User $user, array $dealResponsibleMap): bool
    {
        $payload = is_array($activity->payload ?? null) ? $activity->payload : [];
        $employee = Deal::resolveCallEmployeeFromPayload($payload);

        if ($employee !== null && $this->operatorLabelMatchesUser($employee, $user)) {
            return true;
        }

        return (int) ($dealResponsibleMap[(int) $activity->deal_id] ?? 0) === (int) $user->id;
    }

    private function operatorLabelMatchesUser(string $label, User $user): bool
    {
        $labelTokens = $this->normalizeHumanTokens($label);
        if ($labelTokens === []) {
            return false;
        }

        $candidates = [$user->name];
        $login = trim((string) $user->email);
        if ($login !== '') {
            $candidates[] = Str::before($login, '@');
            $candidates[] = $login;
        }

        foreach ($candidates as $candidate) {
            if ($this->humanTokensMatch($labelTokens, $this->normalizeHumanTokens((string) $candidate))) {
                return true;
            }
        }

        return false;
    }

    private function normalizeHumanTokens(string $value): array
    {
        $value = Str::lower(Str::ascii($value));
        $value = str_replace(['_', '.', '-', '@'], ' ', $value);
        $value = preg_replace('/[^a-z0-9\s]+/', ' ', $value) ?? '';

        return collect(preg_split('/\s+/', trim($value)) ?: [])
            ->filter(fn ($token) => $token !== '' && !ctype_digit($token))
            ->unique()
            ->values()
            ->all();
    }

    private function humanTokensMatch(array $left, array $right): bool
    {
        if ($left === [] || $right === []) {
            return false;
        }

        $matched = 0;
        foreach ($right as $candidateToken) {
            foreach ($left as $labelToken) {
                if ($this->humanTokenMatches($labelToken, $candidateToken)) {
                    $matched++;
                    break;
                }
            }
        }

        if (count($right) === 1) {
            return $matched >= 1;
        }

        return $matched >= min(2, count($right));
    }

    private function humanTokenMatches(string $left, string $right): bool
    {
        if ($left === $right) {
            return true;
        }

        $minLength = min(strlen($left), strlen($right));
        if ($minLength >= 4 && (str_starts_with($left, $right) || str_starts_with($right, $left))) {
            return true;
        }

        return $minLength >= 5 && levenshtein($left, $right) <= 2;
    }
}
