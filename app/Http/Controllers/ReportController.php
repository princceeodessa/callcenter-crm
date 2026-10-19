<?php

namespace App\Http\Controllers;

use App\Services\Owner\CeilingsKpi;
use App\Support\Deals\NonTargetCategory;
use App\Support\Export\ContactsExport;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\DealStageHistory;
use App\Models\CallRecording;
use App\Models\IntegrationEvent;
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
        // свои даты из календаря (from/to) — вместо месяца
        $customRange = false;
        try {
            $rangeFrom = Carbon::createFromFormat('!Y-m-d', $request->string('from')->toString());
            $rangeTo = Carbon::createFromFormat('!Y-m-d', $request->string('to')->toString())->addDay();
            if ($rangeTo->gt($rangeFrom) && $rangeFrom->diffInDays($rangeTo) <= 400) {
                [$from, $to, $customRange] = [$rangeFrom, $rangeTo, true];
                $month = $from->format('Y-m');
            }
        } catch (\Throwable) {
        }
        $period = [
            'from' => $from->toDateString(),
            'to' => $to->copy()->subDay()->toDateString(),
            'custom' => $customRange,
        ];

        $role = (string)$user->role;
        $showLeads = NonTargetCategory::appliesTo((int) $user->account_id);
        $isManager = in_array($role, ['admin', 'main_operator'], true);
        $isMeasurer = $role === 'measurer';

        $operatorRoles = ['admin', 'main_operator', 'operator'];

        $operatorUsers = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', true)
            ->whereIn('role', $operatorRoles)
            ->orderByRaw("FIELD(role,'operator','main_operator','admin')")
            ->orderBy('name')
            ->get(['id', 'account_id', 'name', 'role', 'hide_in_reports']);
        // «Новые лиды» — по одному источнику (?src=): звонки, Авито, ВК отдельно
        $leadSource = $showLeads && isset(CeilingsKpi::LEAD_SOURCES[$request->query('src')]) ? (string) $request->query('src') : null;

        $measurerUsers = User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', true)
            ->where('role', 'measurer')
            ->orderBy('name')
            ->get(['id', 'account_id', 'name', 'role']);

        if ($isMeasurer) {
            $measurementRows = $this->measurementCompletionRows($measurerUsers->where('id', $user->id), $from, $to);
            $measurementSummary = $this->summarizeMeasurementCompletionRows($measurementRows);
            $dataPeriodHint = $customRange ? null : $this->measurementDataPeriodHint($requestedMonth, $month, $measurementSummary['total'], $user);

            return view('reports.monthly', [
                'month' => $month,
                'period' => $period,
                'mode' => 'measurer',
                'isManager' => $isManager,
                'showLeads' => false,
                'callSourceOptions' => $callSourceOptions,
                'dataPeriodHint' => $dataPeriodHint,
                'operatorSummary' => null,
                'operatorRows' => collect(),
                'measurementSummary' => $measurementSummary,
                'measurementRows' => $measurementRows,
            ]);
        }

        // потолки: обращения месяца в Excel — номер, менеджер (кто первым взял лид), записался ли; без нецелевых
        if ($isManager && $showLeads && $request->query('export') === 'contacts') {
            return ContactsExport::download((int) $user->account_id, $from->copy(), $to->copy());
        }

        if ($isManager) {
            // сотрудники с отметкой «не показывать в отчётах» (Admin, руководитель) — не в таблицах и не в итогах
            $operatorRows = $this->operatorRows($operatorUsers->reject(fn (User $u) => (bool) $u->hide_in_reports)->values(), $from, $to, $leadSource);
            if ($request->filled('drill')) {
                return $this->drill($request, $operatorRows, $period, $leadSource);
            }
            $operatorSummary = $this->summarizeOperatorRows($operatorRows);
            $measurementRows = $this->measurementRows($measurerUsers, $from, $to);
            $measurementSummary = $this->summarizeMeasurementRows($measurementRows);
            $dataPeriodHint = $customRange ? null : $this->operatorDataPeriodHint($requestedMonth, $month, $operatorSummary, $user);

            return view('reports.monthly', [
                'month' => $month,
                'period' => $period,
                'mode' => 'manager',
                'isManager' => true,
                'showLeads' => $showLeads,
                'leadSource' => $leadSource,
                'leadSourceTotals' => $this->leadSourceTotals,
                'callSourceOptions' => $callSourceOptions,
                'dataPeriodHint' => $dataPeriodHint,
                'operatorSummary' => $operatorSummary,
                'operatorRows' => $operatorRows,
                'measurementSummary' => $measurementSummary,
                'measurementRows' => $measurementRows,
            ]);
        }

        $operatorRows = $this->operatorRows($operatorUsers->where('id', $user->id), $from, $to, $leadSource);
        if ($request->filled('drill')) {
            return $this->drill($request, $operatorRows, $period, $leadSource);
        }
        $operatorSummary = $this->summarizeOperatorRows($operatorRows);
        $dataPeriodHint = $customRange ? null : $this->operatorDataPeriodHint($requestedMonth, $month, $operatorSummary, $user);

        return view('reports.monthly', [
            'month' => $month,
            'period' => $period,
            'mode' => 'operator',
            'isManager' => false,
            'showLeads' => $showLeads,
            'leadSource' => $leadSource,
            'leadSourceTotals' => $this->leadSourceTotals,
            'callSourceOptions' => $callSourceOptions,
            'dataPeriodHint' => $dataPeriodHint,
            'operatorSummary' => $operatorSummary,
            'operatorRows' => $operatorRows,
            'measurementSummary' => null,
            'measurementRows' => collect(),
        ]);
    }

    /** Числа таблицы «Операторы», в которые можно провалиться: ключ ?drill= → заголовок. */
    public const DRILL = [
        'leads.taken' => 'Взял новых лидов',
        'leads.booked_self' => 'Записал сам',
        'leads.rescued' => 'Записали другие',
        'leads.not_booked' => 'Не записал',
        'leads.in_work' => 'Незавершённые',
        'leads.non_target' => 'Нецелевые',
        'leads.booked_others' => 'Дожал чужих',
        'created' => 'Обработано сделок',
        'closedWon' => 'Успешно',
        'closedLost' => 'Отказ',
        'closedExtraNonTarget' => 'Доп.Работы / Не целевой',
        'callActivities' => 'Звонки',
    ];

    /**
     * Сделки за числом таблицы «Операторы» (?drill=метрика&user=id, 0 — «Не распределено»): окно на странице отчёта
     * (?partial=1) или отдельная страница. Берутся те же сделки, что посчитаны в числе; сотрудник видит только свою строку.
     */
    private function drill(Request $request, Collection $operatorRows, array $period, ?string $leadSource = null)
    {
        $metric = (string) $request->query('drill');
        abort_unless(isset(self::DRILL[$metric]), 404);
        $row = $operatorRows->first(fn ($r) => (int) $r['id'] === (int) $request->query('user'));
        abort_if($row === null, 404);
        $lead = str_starts_with($metric, 'leads.');
        $ids = $lead ? (array) ($row['leads']['ids'][substr($metric, 6)] ?? []) : (array) ($row['ids'][$metric] ?? []);
        $deals = Deal::query()->whereIn('id', $ids)
            ->with(['contact:id,name,phone', 'stage:id,name', 'responsible:id,name'])
            ->orderByDesc('created_at')->orderByDesc('id')->get();

        $source = $lead && $leadSource !== null ? CeilingsKpi::LEAD_SOURCES[$leadSource] : null;

        return view($request->boolean('partial') ? 'reports._drill' : 'reports.drill', [
            'deals' => $deals,
            'title' => self::DRILL[$metric].($source !== null ? ' · '.$source : ''),
            'who' => $row['name'],
            'period' => $period,
            'backUrl' => route('reports.monthly', ($period['custom'] ? ['from' => $period['from'], 'to' => $period['to']] : ['month' => substr($period['from'], 0, 7)])
                + ($leadSource !== null ? ['src' => $leadSource] : [])),
        ]);
    }

    private function resolveDefaultMonth(User $user): string
    {
        return now()->format('Y-m');
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

    /** Сколько новых лидов взяли сотрудники таблицы — всего и по каждому источнику (для переключателя). */
    private array $leadSourceTotals = [];

    private function operatorRows(Collection $users, $from, $to, ?string $leadSource = null): Collection
    {
        $accountId = (int) $users->first()?->account_id;

        if ($accountId === 0) {
            return collect();
        }

        // потолки: новый лид — за тем, кто первым его перетащил (как в сводке владельца)
        $leadHandlers = NonTargetCategory::appliesTo($accountId)
            ? (new CeilingsKpi($accountId))->handlerStats($from->copy(), $to->copy())
            : null;
        if ($leadHandlers !== null) {
            $leadHandlers = array_intersect_key($leadHandlers, array_flip($users->pluck('id')->map(fn ($id) => (int) $id)->all()));
            $sourceOf = (new CeilingsKpi($accountId))->leadSourcesFor(collect($leadHandlers)
                ->flatMap(fn ($s) => array_merge($s['ids']['taken'], $s['ids']['booked_others']))->all());
            $this->leadSourceTotals = ['all' => (int) collect($leadHandlers)->sum('taken')];
            foreach (array_keys(CeilingsKpi::LEAD_SOURCES) as $src) {
                $this->leadSourceTotals[$src] = (int) collect($leadHandlers)->sum(fn ($s) => count(array_filter($s['ids']['taken'], fn ($id) => ($sourceOf[(int) $id] ?? 'other') === $src)));
            }
            if ($leadSource !== null) {
                $leadHandlers = array_map(fn ($s) => CeilingsKpi::handlerRowForSource($s, $sourceOf, $leadSource), $leadHandlers);
            }
        }

        $reportUserIdSet = array_fill_keys(
            $users
                ->pluck('id')
                ->map(static fn ($id) => (int) $id)
                ->filter(static fn ($id) => $id > 0)
                ->values()
                ->all(),
            true
        );

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
            ->orderBy('changed_at')
            ->orderBy('id')
            ->get(['id', 'deal_id', 'changed_by_user_id', 'changed_at']);

        $callActivities = DealActivity::query()
            ->where('account_id', $accountId)
            ->where('type', 'call')
            ->whereBetween('created_at', [$from, $to])
            ->get(['deal_id', 'payload']);

        $sourceDealIds = $createdDeals
            ->pluck('id')
            ->merge($closedDeals->pluck('id'))
            ->merge($stageHistoryRows->pluck('deal_id'))
            ->merge($callActivities->pluck('deal_id'))
            ->filter()
            ->unique()
            ->values();

        $sourceCallActivitiesByDealId = $sourceDealIds->isEmpty()
            ? collect()
            : DealActivity::query()
                ->where('account_id', $accountId)
                ->where('type', 'call')
                ->whereIn('deal_id', $sourceDealIds->all())
                ->orderBy('created_at')
                ->orderBy('id')
                ->get(['deal_id', 'payload'])
                ->groupBy('deal_id');

        $callRecordingRowsByDealId = $sourceDealIds->isEmpty()
            ? collect()
            : CallRecording::query()
                ->where('account_id', $accountId)
                ->whereIn('deal_id', $sourceDealIds->all())
                ->get(['deal_id', 'callid'])
                ->groupBy('deal_id');

        $recordingCallIds = $callRecordingRowsByDealId
            ->flatten(1)
            ->pluck('callid')
            ->filter()
            ->map(static fn ($callId) => (string) $callId)
            ->unique()
            ->values();

        $integrationEventPayloadsByCallId = $recordingCallIds->isEmpty()
            ? collect()
            : IntegrationEvent::query()
                ->where('account_id', $accountId)
                ->where('provider', 'megafon_vats')
                ->whereIn('external_id', $recordingCallIds->all())
                ->orderBy('received_at')
                ->get(['external_id', 'payload'])
                ->groupBy('external_id')
                ->map(static fn (Collection $events) => $events
                    ->map(static fn (IntegrationEvent $event) => is_array($event->payload ?? null) ? $event->payload : [])
                    ->filter(static fn (array $payload) => $payload !== [])
                    ->values());

        $closedActivityUserByDealId = DealActivity::query()
            ->where('account_id', $accountId)
            ->where('type', 'deal_closed')
            ->whereBetween('created_at', [$from, $to])
            ->orderBy('created_at')
            ->orderBy('id')
            ->get(['deal_id', 'author_user_id', 'created_at'])
            ->groupBy('deal_id')
            ->map(static fn (Collection $rows) => (int) ($rows->last()?->author_user_id ?? 0))
            ->filter(static fn ($userId) => $userId > 0)
            ->all();

        $stageChangedUserByDealId = $stageHistoryRows
            ->groupBy('deal_id')
            ->map(static fn (Collection $rows) => (int) ($rows->last()?->changed_by_user_id ?? 0))
            ->filter(static fn ($userId) => $userId > 0)
            ->all();

        // потолки: отказ — тому, кто первым взял лид (изначально обрабатывал), а не тому, кто закрыл
        $lostHandlerOf = $leadHandlers === null ? [] : (new CeilingsKpi($accountId))->handlersOf(
            $closedDeals->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === 'lost')->pluck('id')->map(fn ($id) => (int) $id)->all()
        );
        $closedDealsByUser = $this->closedDealsByReportUser(
            $closedDeals,
            $reportUserIdSet,
            $closedActivityUserByDealId,
            $stageChangedUserByDealId,
            $lostHandlerOf
        );

        $dealResponsibleMap = Deal::query()
            ->where('account_id', $accountId)
            ->pluck('responsible_user_id', 'id')
            ->map(fn ($value) => $value !== null ? (int) $value : null)
            ->all();

        $rows = $users->values()->map(function (User $u) use ($createdDeals, $closedDealsByUser, $stageHistoryRows, $callActivities, $dealResponsibleMap, $sourceCallActivitiesByDealId, $callRecordingRowsByDealId, $integrationEventPayloadsByCallId, $leadHandlers) {
            $createdDealIds = $createdDeals
                ->filter(fn (Deal $deal) => (int) $deal->responsible_user_id === (int) $u->id)
                ->pluck('id');

            $stageChangedDealIds = $stageHistoryRows
                ->filter(fn (DealStageHistory $row) => (int) $row->changed_by_user_id === (int) $u->id)
                ->pluck('deal_id');

            $closedByUser = $closedDealsByUser[(int) $u->id] ?? collect();

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

            $closedWon = $closedByUser->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === 'won')->count();
            $closedLost = $closedByUser->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === 'lost')->count();
            $closedExtraNonTarget = $closedByUser->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === 'extra_non_target')->count();
            $callSourceStats = $this->dealSourceStats(
                $processedDealIds,
                $sourceCallActivitiesByDealId,
                $callRecordingRowsByDealId,
                $integrationEventPayloadsByCallId
            );

            $winBase = $closedWon + $closedLost;
            $sourceTotal = $this->callSourceStatsTotal($callSourceStats);
            $closedIds = fn (string $result) => $closedByUser->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === $result)->pluck('id')->values()->all();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'role' => $u->role,
                'created' => $processedDealIds->count(),
                'closedWon' => $closedWon,
                'closedLost' => $closedLost,
                'closedExtraNonTarget' => $closedExtraNonTarget,
                'callActivities' => $sourceTotal,
                'callSourceCounts' => $callSourceStats['counts'],
                'uncategorizedCallActivities' => $callSourceStats['uncategorized'],
                'winRate' => $winBase > 0 ? round(($closedWon / $winBase) * 100, 1) : null,
                'leads' => $leadHandlers === null ? null
                    : ($leadHandlers[(int) $u->id] ?? CeilingsKpi::emptyHandlerRow() + ['ids' => []]),
                // сделки за каждым числом — для списка по клику (?drill=…&user=…)
                'ids' => [
                    'created' => $processedDealIds->all(),
                    'closedWon' => $closedIds('won'),
                    'closedLost' => $closedIds('lost'),
                    'closedExtraNonTarget' => $closedIds('extra_non_target'),
                    'callActivities' => $callSourceStats['deal_ids'],
                ],
            ];
        });

        $unassignedClosedDeals = $closedDealsByUser[0] ?? collect();
        if ($users->count() > 1 && $unassignedClosedDeals->isNotEmpty()) {
            $closedWon = $unassignedClosedDeals->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === 'won')->count();
            $closedLost = $unassignedClosedDeals->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === 'lost')->count();
            $closedExtraNonTarget = $unassignedClosedDeals->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === 'extra_non_target')->count();
            $winBase = $closedWon + $closedLost;
            $callSourceStats = $this->dealSourceStats(
                $unassignedClosedDeals->pluck('id'),
                $sourceCallActivitiesByDealId,
                $callRecordingRowsByDealId,
                $integrationEventPayloadsByCallId
            );
            $sourceTotal = $this->callSourceStatsTotal($callSourceStats);
            $closedIds = fn (string $result) => $unassignedClosedDeals->filter(fn (Deal $deal) => $this->normalizeClosedResult($deal->closed_result) === $result)->pluck('id')->values()->all();

            $rows->push([
                'id' => 0,
                'name' => "\u{041D}\u{0435} \u{0440}\u{0430}\u{0441}\u{043F}\u{0440}\u{0435}\u{0434}\u{0435}\u{043B}\u{0435}\u{043D}\u{043E}",
                'role' => 'unassigned',
                'created' => $unassignedClosedDeals->count(),
                'closedWon' => $closedWon,
                'closedLost' => $closedLost,
                'closedExtraNonTarget' => $closedExtraNonTarget,
                'callActivities' => $sourceTotal,
                'callSourceCounts' => $callSourceStats['counts'],
                'uncategorizedCallActivities' => $callSourceStats['uncategorized'],
                'winRate' => $winBase > 0 ? round(($closedWon / $winBase) * 100, 1) : null,
                'leads' => null,
                'ids' => [
                    'created' => $unassignedClosedDeals->pluck('id')->values()->all(),
                    'closedWon' => $closedIds('won'),
                    'closedLost' => $closedIds('lost'),
                    'closedExtraNonTarget' => $closedIds('extra_non_target'),
                    'callActivities' => $callSourceStats['deal_ids'],
                ],
            ]);
        }

        return $rows;
    }

    private function closedDealsByReportUser(Collection $closedDeals, array $reportUserIdSet, array $closedActivityUserByDealId, array $stageChangedUserByDealId, array $lostHandlerOf = []): array
    {
        $byUser = [];

        foreach ($closedDeals as $deal) {
            $handler = (int) ($lostHandlerOf[(int) $deal->id] ?? 0);
            $userId = $handler > 0 && isset($reportUserIdSet[$handler]) ? $handler : $this->resolveClosedDealReportUserId(
                $deal,
                $reportUserIdSet,
                $closedActivityUserByDealId,
                $stageChangedUserByDealId
            );

            $userId ??= 0;

            $byUser[$userId] ??= collect();
            $byUser[$userId]->push($deal);
        }

        return $byUser;
    }

    private function resolveClosedDealReportUserId(Deal $deal, array $reportUserIdSet, array $closedActivityUserByDealId, array $stageChangedUserByDealId): ?int
    {
        $dealId = (int) $deal->id;

        foreach ([
            $deal->closed_by_user_id ?? null,
            $closedActivityUserByDealId[$dealId] ?? null,
            $stageChangedUserByDealId[$dealId] ?? null,
            $deal->responsible_user_id ?? null,
        ] as $candidateId) {
            $candidateId = (int) $candidateId;
            if ($candidateId > 0 && isset($reportUserIdSet[$candidateId])) {
                return $candidateId;
            }
        }

        return null;
    }

    private function normalizeClosedResult(?string $result): ?string
    {
        $value = Str::lower(trim((string) $result));

        return match ($value) {
            'won', 'win', 'success', 'successful', 'ok', 'done', "\u{0443}\u{0441}\u{043F}\u{0435}\u{0448}\u{043D}\u{043E}", "\u{0437}\u{0430}\u{043A}\u{043B}\u{044E}\u{0447}\u{0435}\u{043D}\u{043E}" => 'won',
            'lost', 'loss', 'fail', 'failed', 'refused', 'reject', 'rejected', 'cancelled', 'canceled', "\u{043E}\u{0442}\u{043A}\u{0430}\u{0437}", "\u{043D}\u{0435} \u{0437}\u{0430}\u{043A}\u{043B}\u{044E}\u{0447}\u{0435}\u{043D}\u{043E}" => 'lost',
            'extra_non_target', 'extra', 'additional_work', 'additional_works', 'non_target', 'non-target', "\u{0434}\u{043E}\u{043F}.\u{0440}\u{0430}\u{0431}\u{043E}\u{0442}\u{044B}", "\u{0434}\u{043E}\u{043F} \u{0440}\u{0430}\u{0431}\u{043E}\u{0442}\u{044B}", "\u{0434}\u{043E}\u{043F}\u{0440}\u{0430}\u{0431}\u{043E}\u{0442}\u{044B}", "\u{043D}\u{0435} \u{0446}\u{0435}\u{043B}\u{0435}\u{0432}\u{043E}\u{0439}", "\u{043D}\u{0435}\u{0446}\u{0435}\u{043B}\u{0435}\u{0432}\u{043E}\u{0439}", "\u{0434}\u{043E}\u{043F}.\u{0440}\u{0430}\u{0431}\u{043E}\u{0442}\u{044B} / \u{043D}\u{0435} \u{0446}\u{0435}\u{043B}\u{0435}\u{0432}\u{043E}\u{0439}" => 'extra_non_target',
            default => null,
        };
    }

    private function summarizeOperatorRows(Collection $rows): array
    {
        $created = (int)$rows->sum('created');
        $closedWon = (int)$rows->sum('closedWon');
        $closedLost = (int)$rows->sum('closedLost');
        $closedExtraNonTarget = (int)$rows->sum('closedExtraNonTarget');
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
            'closedExtraNonTarget' => $closedExtraNonTarget,
            'callActivities' => $callActivities,
            'callSourceCounts' => $callSourceCounts,
            'uncategorizedCallActivities' => $uncategorizedCallActivities,
            'winRate' => $winBase > 0 ? round(($closedWon / $winBase) * 100, 1) : null,
        ];
    }

    private function dealSourceStats(Collection $dealIds, Collection $sourceCallActivitiesByDealId, Collection $callRecordingRowsByDealId, Collection $integrationEventPayloadsByCallId): array
    {
        $counts = Deal::emptyIncomingPhoneSourceCounts();
        $uncategorized = 0;
        $counted = [];

        foreach ($dealIds->map(static fn ($dealId) => (int) $dealId)->filter()->unique() as $dealId) {
            $activities = $sourceCallActivitiesByDealId->get($dealId, collect());
            $matched = false;

            foreach ($activities as $activity) {
                $key = Deal::resolveIncomingPhoneSourceFilterKeyFromPayload(
                    is_array($activity->payload ?? null) ? $activity->payload : []
                );

                if ($key !== null && array_key_exists($key, $counts)) {
                    $counts[$key]++;
                    $counted[] = $dealId;
                    $matched = true;
                    break;
                }
            }

            $recordings = $callRecordingRowsByDealId->get($dealId, collect());

            if (! $matched) {
                foreach ($recordings as $recording) {
                    $callId = (string) ($recording->callid ?? '');
                    if ($callId === '') {
                        continue;
                    }

                    foreach ($integrationEventPayloadsByCallId->get($callId, collect()) as $payload) {
                        $key = Deal::resolveIncomingPhoneSourceFilterKeyFromPayload(
                            is_array($payload) ? $payload : []
                        );

                        if ($key !== null && array_key_exists($key, $counts)) {
                            $counts[$key]++;
                            $counted[] = $dealId;
                            $matched = true;
                            break 2;
                        }
                    }
                }
            }

            if (! $matched && ($activities->isNotEmpty() || $recordings->isNotEmpty())) {
                $uncategorized++;
                $counted[] = $dealId;
            }
        }

        return [
            'counts' => $counts,
            'uncategorized' => $uncategorized,
            'deal_ids' => $counted,
        ];
    }

    private function callSourceStatsTotal(array $stats): int
    {
        return (int) collect($stats['counts'] ?? [])->sum() + (int) ($stats['uncategorized'] ?? 0);
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
            + ((int) ($summary['closedExtraNonTarget'] ?? 0))
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

    /** Сотрудник из звонка (по объекту записи) и «подпись ↔ пользователь» — считаются один раз на запрос. */
    private array $callEmployee = [];
    private array $labelMatchesUser = [];

    private function callActivityBelongsToUser(DealActivity $activity, User $user, array $dealResponsibleMap): bool
    {
        $key = spl_object_id($activity);
        if (! array_key_exists($key, $this->callEmployee)) {
            $payload = is_array($activity->payload ?? null) ? $activity->payload : [];
            $this->callEmployee[$key] = Deal::resolveCallEmployeeFromPayload($payload);
        }
        $employee = $this->callEmployee[$key];

        if ($employee !== null && ($this->labelMatchesUser[$employee.'|'.$user->id] ??= $this->operatorLabelMatchesUser($employee, $user))) {
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
