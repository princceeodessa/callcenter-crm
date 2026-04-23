<?php

namespace App\Console\Commands;

use App\Http\Controllers\ReportController;
use App\Models\CallRecording;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\IntegrationEvent;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;

class DebugMonthlyReport extends Command
{
    protected $signature = 'reports:debug {month? : Month in YYYY-MM format} {--account= : Account id}';

    protected $description = 'Show raw data used by the monthly report for quick production diagnostics.';

    public function handle(): int
    {
        $month = (string) ($this->argument('month') ?: now()->format('Y-m'));
        if (! preg_match('/^\d{4}-\d{2}$/', $month)) {
            $this->error('Month must be in YYYY-MM format.');

            return self::FAILURE;
        }

        [$year, $monthNumber] = explode('-', $month);
        $from = now()->setDate((int) $year, (int) $monthNumber, 1)->startOfDay();
        $to = (clone $from)->addMonth()->startOfDay();
        $accountId = $this->resolveAccountId($from, $to);

        if ($accountId === null) {
            $this->warn('No account found.');

            return self::SUCCESS;
        }

        $this->info("Monthly report debug: account={$accountId}, month={$month}, from={$from->toDateTimeString()}, to={$to->toDateTimeString()}");
        $this->newLine();

        $operatorUsers = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->whereIn('role', ['admin', 'main_operator', 'operator'])
            ->orderByRaw("FIELD(role,'operator','main_operator','admin')")
            ->orderBy('name')
            ->get(['id', 'account_id', 'name', 'role']);

        $this->table(
            ['id', 'name', 'role'],
            $operatorUsers->map(fn (User $user) => [$user->id, $user->name, $user->role])->all()
        );

        $closedDeals = Deal::query()
            ->where('account_id', $accountId)
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$from, $to])
            ->with(['responsible:id,name', 'closedBy:id,name'])
            ->orderByDesc('closed_at')
            ->get(['id', 'account_id', 'title', 'responsible_user_id', 'closed_by_user_id', 'closed_at', 'closed_result']);

        $this->info('Closed deals by result');
        $this->table(
            ['closed_result', 'count'],
            $closedDeals
                ->groupBy(fn (Deal $deal) => (string) ($deal->closed_result ?: '<empty>'))
                ->map(fn (Collection $deals, string $result) => [$result, $deals->count()])
                ->values()
                ->all()
        );

        $this->info('Closed deals attribution');
        $this->table(
            ['type', 'user_id', 'user', 'count'],
            $this->closedAttributionRows($closedDeals)->all()
        );

        $closedDealIds = $closedDeals->pluck('id')->values();
        $callActivities = $closedDealIds->isEmpty()
            ? collect()
            : DealActivity::query()
                ->where('account_id', $accountId)
                ->where('type', 'call')
                ->whereIn('deal_id', $closedDealIds->all())
                ->get(['id', 'deal_id', 'payload', 'created_at']);

        $recordings = $closedDealIds->isEmpty()
            ? collect()
            : CallRecording::query()
                ->where('account_id', $accountId)
                ->whereIn('deal_id', $closedDealIds->all())
                ->get(['id', 'deal_id', 'callid', 'created_at']);

        $callIds = $recordings
            ->pluck('callid')
            ->filter()
            ->map(static fn ($callId) => (string) $callId)
            ->unique()
            ->values();

        $integrationEvents = $callIds->isEmpty()
            ? collect()
            : IntegrationEvent::query()
                ->where('account_id', $accountId)
                ->where('provider', 'megafon_vats')
                ->whereIn('external_id', $callIds->all())
                ->get(['id', 'external_id', 'payload', 'received_at']);

        $this->info('Closed deal call-source raw coverage');
        $this->table([
            'closed_deals',
            'call_activities',
            'call_recordings',
            'recording_callids',
            'integration_events_for_callids',
        ], [[
            $closedDeals->count(),
            $callActivities->count(),
            $recordings->count(),
            $callIds->count(),
            $integrationEvents->count(),
        ]]);

        $sourceOptions = Deal::incomingPhoneSourceOptions();
        $sourceCounts = Deal::emptyIncomingPhoneSourceCounts();
        $uncategorized = 0;

        foreach ($callActivities as $activity) {
            $key = Deal::resolveIncomingPhoneSourceFilterKeyFromPayload(is_array($activity->payload ?? null) ? $activity->payload : []);
            if ($key !== null && array_key_exists($key, $sourceCounts)) {
                $sourceCounts[$key]++;
            } else {
                $uncategorized++;
            }
        }

        foreach ($integrationEvents as $event) {
            $key = Deal::resolveIncomingPhoneSourceFilterKeyFromPayload(is_array($event->payload ?? null) ? $event->payload : []);
            if ($key !== null && array_key_exists($key, $sourceCounts)) {
                $sourceCounts[$key]++;
            }
        }

        $this->info('Raw source matches from call activities + integration events');
        $this->table(
            ['source_key', 'label', 'count'],
            collect($sourceCounts)
                ->map(fn (int $count, string $key) => [$key, $sourceOptions[$key] ?? $key, $count])
                ->push(['uncategorized_activity_payloads', 'Uncategorized activity payloads', $uncategorized])
                ->values()
                ->all()
        );

        $this->info('Report rows produced by current code');
        $this->table(
            ['id', 'name', 'role', 'processed', 'won', 'lost', 'calls', 'uncategorized'],
            $this->currentReportRows($operatorUsers, $from, $to)->map(fn (array $row) => [
                $row['id'],
                $row['name'],
                $row['role'],
                $row['created'],
                $row['closedWon'],
                $row['closedLost'],
                $row['callActivities'],
                $row['uncategorizedCallActivities'],
            ])->all()
        );

        $this->info('Closed deal samples');
        $this->table(
            ['id', 'closed_at', 'result', 'responsible', 'closed_by', 'calls', 'recordings', 'title'],
            $closedDeals->take(20)->map(function (Deal $deal) use ($callActivities, $recordings) {
                return [
                    $deal->id,
                    optional($deal->closed_at)->format('Y-m-d H:i:s'),
                    $deal->closed_result,
                    $deal->responsible?->name ?: $deal->responsible_user_id,
                    $deal->closedBy?->name ?: $deal->closed_by_user_id,
                    $callActivities->where('deal_id', $deal->id)->count(),
                    $recordings->where('deal_id', $deal->id)->count(),
                    mb_strimwidth((string) $deal->title, 0, 60, '...'),
                ];
            })->all()
        );

        return self::SUCCESS;
    }

    private function resolveAccountId(Carbon $from, Carbon $to): ?int
    {
        $option = $this->option('account');
        if ($option !== null && $option !== '') {
            return (int) $option;
        }

        $accountId = Deal::query()
            ->whereNotNull('closed_at')
            ->whereBetween('closed_at', [$from, $to])
            ->value('account_id');

        if ($accountId !== null) {
            return (int) $accountId;
        }

        $accountId = User::query()->value('account_id');

        return $accountId !== null ? (int) $accountId : null;
    }

    private function closedAttributionRows(Collection $closedDeals): Collection
    {
        $responsible = $closedDeals
            ->groupBy(fn (Deal $deal) => (int) ($deal->responsible_user_id ?? 0))
            ->map(fn (Collection $deals, int $userId) => ['responsible', $userId ?: '-', $deals->first()?->responsible?->name ?: '-', $deals->count()])
            ->values();

        $closedBy = $closedDeals
            ->groupBy(fn (Deal $deal) => (int) ($deal->closed_by_user_id ?? 0))
            ->map(fn (Collection $deals, int $userId) => ['closed_by', $userId ?: '-', $deals->first()?->closedBy?->name ?: '-', $deals->count()])
            ->values();

        return $responsible->merge($closedBy);
    }

    private function currentReportRows(Collection $operatorUsers, Carbon $from, Carbon $to): Collection
    {
        $method = new ReflectionMethod(ReportController::class, 'operatorRows');
        $method->setAccessible(true);

        return $method->invoke(new ReportController(), $operatorUsers, $from, $to);
    }
}
