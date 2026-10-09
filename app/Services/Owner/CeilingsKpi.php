<?php

namespace App\Services\Owner;

use App\Models\Deal;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * KPI колл-центра потолков для сводки владельца. Читает пространство потолков напрямую по account_id
 * (запросами DB, без глобального скоупа: владелец сидит в другом пространстве).
 *
 * - Лид — сделка, созданная в периоде.
 * - Замер назначен — сделка впервые попала на этап «Замер назначен» или закрыта «Успешно» (что раньше).
 *   «Замеров за период» — такие события в периоде; «конверсия» — доля лидов периода, дошедших до замера.
 * - Нецелевой — закрыт «Доп.работы / Не целевой» или стоит на этапе «Нецелевое»; отказ — закрыт «Отказ».
 * - Источник лида — самый ранний сигнал: чат, форма с сайта, импорт или звонок на рекламный номер.
 * - Звонки — события Мегафона: входящий (INCOMING), принят (ACCEPTED), исходящий (OUTGOING), по callid.
 */
class CeilingsKpi
{
    /** Группы каналов — под них ляжут расходы на рекламу. */
    public const GROUPS = [
        'direct' => 'Яндекс Директ / сайт',
        'avito' => 'Авито',
        'vk' => 'ВКонтакте',
        'radio' => 'Радио',
        'tv' => 'ТВ',
        'other' => 'Прочее',
    ];

    private ?array $stageIds = null;

    public function __construct(private readonly int $accountId)
    {
    }

    /** @return array<string, mixed> период [$from, $to) */
    public function compute(Carbon $from, Carbon $to): array
    {
        $leads = DB::table('deals')
            ->where('account_id', $this->accountId)
            ->where('created_at', '>=', $from)
            ->where('created_at', '<', $to)
            ->orderBy('id')
            ->get(['id', 'created_at', 'stage_id', 'closed_at', 'closed_result', 'responsible_user_id']);
        $leadIds = $leads->pluck('id')->all();
        $bookedAt = $this->bookedAtFor($leadIds);
        $channels = $this->channelsFor($leadIds);
        $bookings = $this->bookingsBetween($from, $to);
        $calls = $this->callsBetween($from, $to);

        $outcome = [];
        foreach ($leads as $d) {
            $outcome[$d->id] = $this->outcome($d, isset($bookedAt[$d->id]));
        }
        $count = fn (string $o) => count(array_filter($outcome, fn ($x) => $x === $o));
        $total = count($leads);
        $booked = $count('booked');
        $nonTarget = $count('non_target');
        $targeted = $total - $nonTarget;

        // прошлый период той же длины — для сравнения
        $prevFrom = $from->copy()->subSeconds($to->getTimestamp() - $from->getTimestamp());
        $prevLeads = DB::table('deals')->where('account_id', $this->accountId)
            ->where('created_at', '>=', $prevFrom)->where('created_at', '<', $from)->pluck('id')->all();
        $prevBooked = count($this->bookedAtFor($prevLeads));
        $prevCalls = $this->callsBetween($prevFrom, $from);

        return [
            'from' => $from,
            'to' => $to,
            'leads' => $total,
            'booked_cohort' => $booked,
            'non_target' => $nonTarget,
            'lost' => $count('lost'),
            'open' => $count('open'),
            'conversion' => $total > 0 ? $booked / $total * 100 : null,
            'conversion_targeted' => $targeted > 0 ? $booked / $targeted * 100 : null,
            'bookings' => count($bookings),
            'calls' => $calls['totals'],
            'prev' => [
                'leads' => count($prevLeads),
                'bookings' => count($this->bookingsBetween($prevFrom, $from)),
                'conversion' => count($prevLeads) > 0 ? $prevBooked / count($prevLeads) * 100 : null,
                'incoming' => $prevCalls['totals']['incoming'],
            ],
            'channels' => $this->channelTable($leads, $outcome, $channels),
            'operators' => $this->operatorTable($from, $to, $bookings),
            'series' => $this->series($from, $to, $leads, $bookings, $calls['incoming_days']),
        ];
    }

    /** @return array{measure: ?int, non_target: ?int} */
    private function stages(): array
    {
        if ($this->stageIds === null) {
            $rows = DB::table('pipeline_stages')->where('account_id', $this->accountId)->get(['id', 'name']);
            $find = fn (string $needle) => $rows->first(fn ($s) => mb_stripos((string) $s->name, $needle) !== false)?->id;
            $this->stageIds = ['measure' => $find('Замер назначен'), 'non_target' => $find('Нецелев')];
        }

        return $this->stageIds;
    }

    private function outcome(object $deal, bool $booked): string
    {
        if ($booked) {
            return 'booked';
        }
        if ($deal->closed_result === 'extra_non_target'
            || ($deal->closed_at === null && $this->stages()['non_target'] !== null && (int) $deal->stage_id === (int) $this->stages()['non_target'])) {
            return 'non_target';
        }
        if ($deal->closed_result === 'lost') {
            return 'lost';
        }

        return 'open';
    }

    /**
     * Когда и кем сделка доведена до замера: раньшее из «первый раз на этапе «Замер назначен»» (кто перевёл) и
     * «закрыта «Успешно»» (кто закрыл). @return array<int, object{at: Carbon, by: ?int}>
     */
    private function bookedAtFor(array $dealIds): array
    {
        $out = [];
        $measure = $this->stages()['measure'];
        foreach (array_chunk($dealIds, 1000) as $chunk) {
            if ($measure !== null) {
                $rows = DB::table('deal_stage_history')
                    ->where('account_id', $this->accountId)->where('to_stage_id', $measure)->whereIn('deal_id', $chunk)
                    ->orderBy('changed_at')->orderBy('id')->get(['deal_id', 'changed_at', 'changed_by_user_id']);
                foreach ($rows as $r) {
                    $out[(int) $r->deal_id] ??= (object) ['at' => Carbon::parse($r->changed_at), 'by' => $r->changed_by_user_id !== null ? (int) $r->changed_by_user_id : null];
                }
            }
            $won = DB::table('deals')->whereIn('id', $chunk)->where('closed_result', 'won')->whereNotNull('closed_at')->get(['id', 'closed_at', 'closed_by_user_id']);
            foreach ($won as $r) {
                $at = Carbon::parse($r->closed_at);
                if (! isset($out[(int) $r->id]) || $at->lt($out[(int) $r->id]->at)) {
                    $out[(int) $r->id] = (object) ['at' => $at, 'by' => $r->closed_by_user_id !== null ? (int) $r->closed_by_user_id : null];
                }
            }
        }

        return $out;
    }

    /** Замеры, назначенные в периоде (по сделкам любого возраста). @return Collection<int, object{id:int, by:?int, at:Carbon}> */
    private function bookingsBetween(Carbon $from, Carbon $to): Collection
    {
        $measure = $this->stages()['measure'];
        $ids = collect();
        if ($measure !== null) {
            $ids = DB::table('deal_stage_history')
                ->where('account_id', $this->accountId)->where('to_stage_id', $measure)
                ->where('changed_at', '>=', $from)->where('changed_at', '<', $to)
                ->distinct()->pluck('deal_id');
        }
        $ids = $ids->merge(DB::table('deals')->where('account_id', $this->accountId)->where('closed_result', 'won')
            ->where('closed_at', '>=', $from)->where('closed_at', '<', $to)->pluck('id'))
            ->map(fn ($id) => (int) $id)->unique()->values();
        $ev = $this->bookedAtFor($ids->all());

        return $ids
            ->filter(fn ($id) => isset($ev[$id]) && $ev[$id]->at->gte($from) && $ev[$id]->at->lt($to))
            ->map(fn ($id) => (object) ['id' => $id, 'by' => $ev[$id]->by, 'at' => $ev[$id]->at])
            ->values();
    }

    /** Источник каждого лида — ключ канала. @return array<int, string> */
    private function channelsFor(array $dealIds): array
    {
        $signals = []; // deal_id => [time, key]
        $note = function (int $dealId, $at, string $key) use (&$signals) {
            $t = $at ? Carbon::parse($at)->getTimestamp() : PHP_INT_MAX;
            if (! isset($signals[$dealId]) || $t < $signals[$dealId][0]) {
                $signals[$dealId] = [$t, $key];
            }
        };

        foreach (array_chunk($dealIds, 500) as $chunk) {
            foreach (DB::table('conversations')->whereIn('deal_id', $chunk)->get(['deal_id', 'channel', 'created_at']) as $c) {
                $note((int) $c->deal_id, $c->created_at, 'chat_'.mb_strtolower((string) $c->channel));
            }
            $forms = DB::table('deal_activities')->whereIn('deal_id', $chunk)->whereIn('type', ['lead_form', 'import'])
                ->get(['deal_id', 'type', 'created_at', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.provider')) as provider")]);
            foreach ($forms as $f) {
                $provider = mb_strtolower((string) ($f->provider ?? ''));
                $note((int) $f->deal_id, $f->created_at, ($f->type === 'import' ? 'import_' : 'form_').($provider !== '' ? $provider : 'other'));
            }
            $calls = DB::table('deal_activities')->whereIn('deal_id', $chunk)->where('type', 'call')
                ->orderBy('id')->get(['deal_id', 'payload', 'created_at'])->groupBy('deal_id');
            foreach ($calls as $dealId => $rows) {
                $key = 'phone_other';
                foreach ($rows as $r) {
                    $payload = json_decode((string) $r->payload, true);
                    $found = is_array($payload) ? Deal::resolveIncomingPhoneSourceFilterKeyFromPayload($payload) : null;
                    if ($found !== null) {
                        $key = $found;
                        break;
                    }
                }
                $note((int) $dealId, $rows->first()->created_at, $key);
            }
        }

        $out = [];
        foreach ($dealIds as $id) {
            $out[(int) $id] = $signals[(int) $id][1] ?? 'crm';
        }

        return $out;
    }

    public static function channelLabel(string $key): string
    {
        $phones = Deal::incomingPhoneSourceOptions();
        if (isset($phones[$key])) {
            // у двух номеров может быть одно название («вк») — хвост номера их различает
            return 'Звонок · '.$phones[$key].' (…'.substr(preg_replace('/\D/', '', $key), -4).')';
        }

        return match (true) {
            $key === 'phone_other' => 'Звонок · другой номер',
            $key === 'crm' => 'Заведено в CRM вручную',
            $key === 'chat_avito' => 'Авито · чат',
            $key === 'chat_vk' => 'ВК · сообщения',
            $key === 'chat_telegram' => 'Telegram',
            $key === 'form_tilda' || $key === 'chat_tilda' => 'Сайт · форма (Tilda)',
            $key === 'form_vk' => 'ВК · лид-форма',
            $key === 'import_bitrix' => 'Импорт из Битрикса',
            str_starts_with($key, 'chat_') => 'Чат · '.substr($key, 5),
            str_starts_with($key, 'form_') => 'Форма · '.substr($key, 5),
            default => $key,
        };
    }

    public static function channelGroup(string $key): string
    {
        $phones = Deal::incomingPhoneSourceOptions();
        $label = mb_strtolower($phones[$key] ?? '');
        if ($label !== '') {
            return match (true) {
                str_contains($label, 'директ') || str_contains($label, 'сайт') => 'direct',
                str_contains($label, 'авито') => 'avito',
                str_contains($label, 'вк') => 'vk',
                str_contains($label, 'радио') => 'radio',
                $label === 'тв' => 'tv',
                default => 'other',
            };
        }

        return match ($key) {
            'form_tilda', 'chat_tilda' => 'direct',
            'chat_avito' => 'avito',
            'chat_vk', 'form_vk' => 'vk',
            default => 'other',
        };
    }

    private function channelTable(Collection $leads, array $outcome, array $channels): array
    {
        $groups = [];
        foreach (self::GROUPS as $g => $label) {
            $groups[$g] = ['key' => $g, 'label' => $label, 'leads' => 0, 'booked' => 0, 'non_target' => 0, 'sources' => []];
        }
        foreach ($leads as $d) {
            $key = $channels[$d->id] ?? 'crm';
            $g = self::channelGroup($key);
            $booked = $outcome[$d->id] === 'booked' ? 1 : 0;
            $nonTarget = $outcome[$d->id] === 'non_target' ? 1 : 0;
            $groups[$g]['sources'][$key] ??= ['key' => $key, 'label' => self::channelLabel($key), 'leads' => 0, 'booked' => 0, 'non_target' => 0];
            $groups[$g]['leads']++;
            $groups[$g]['booked'] += $booked;
            $groups[$g]['non_target'] += $nonTarget;
            $groups[$g]['sources'][$key]['leads']++;
            $groups[$g]['sources'][$key]['booked'] += $booked;
            $groups[$g]['sources'][$key]['non_target'] += $nonTarget;
        }
        foreach ($groups as &$g) {
            $g['conversion'] = $g['leads'] > 0 ? $g['booked'] / $g['leads'] * 100 : null;
            $g['sources'] = collect($g['sources'])->sortByDesc('leads')->values()
                ->map(fn ($s) => $s + ['conversion' => $s['leads'] > 0 ? $s['booked'] / $s['leads'] * 100 : null])->all();
        }
        unset($g);

        // все группы: какие показывать, решает страница (у группы могут быть замеры по таблице без лидов в CRM)
        return array_values($groups);
    }

    /**
     * Операторы — по их действиям в периоде (ответственный у сделок почти всегда «Admin», по нему не видно, кто работал):
     * обработано сделок — двигал по этапам или закрыл; замеров назначено — перевёл на «Замер назначен» / закрыл «Успешно»
     * первым; нецелевых и отказов — закрыл с таким итогом.
     */
    private function operatorTable(Carbon $from, Carbon $to, Collection $bookings): array
    {
        $rows = [];
        $touch = function (?int $uid) use (&$rows) {
            $k = $uid ?? 0;
            $rows[$k] ??= ['user_id' => $uid, 'handled' => [], 'bookings' => 0, 'non_target' => 0, 'lost' => 0];

            return $k;
        };
        $moves = DB::table('deal_stage_history')->where('account_id', $this->accountId)
            ->where('changed_at', '>=', $from)->where('changed_at', '<', $to)
            ->distinct()->get(['changed_by_user_id', 'deal_id']);
        foreach ($moves as $m) {
            $rows[$touch($m->changed_by_user_id !== null ? (int) $m->changed_by_user_id : null)]['handled'][(int) $m->deal_id] = true;
        }
        $closed = DB::table('deals')->where('account_id', $this->accountId)
            ->where('closed_at', '>=', $from)->where('closed_at', '<', $to)
            ->get(['id', 'closed_by_user_id', 'closed_result']);
        foreach ($closed as $c) {
            $k = $touch($c->closed_by_user_id !== null ? (int) $c->closed_by_user_id : null);
            $rows[$k]['handled'][(int) $c->id] = true;
            $rows[$k]['non_target'] += $c->closed_result === 'extra_non_target' ? 1 : 0;
            $rows[$k]['lost'] += $c->closed_result === 'lost' ? 1 : 0;
        }
        foreach ($bookings as $b) {
            $rows[$touch($b->by)]['bookings']++;
        }
        $names = DB::table('users')->whereIn('id', array_filter(array_keys($rows)))->pluck('name', 'id');

        return collect($rows)
            ->map(function ($r) use ($names) {
                $handled = count($r['handled']);

                return [
                    'user_id' => $r['user_id'],
                    'name' => $r['user_id'] ? ($names[$r['user_id']] ?? 'Пользователь #'.$r['user_id']) : 'Система',
                    'handled' => $handled,
                    'bookings' => $r['bookings'],
                    'non_target' => $r['non_target'],
                    'lost' => $r['lost'],
                    'conversion' => $handled > 0 ? $r['bookings'] / $handled * 100 : null,
                ];
            })
            ->filter(fn ($r) => $r['handled'] > 0 || $r['bookings'] > 0)
            ->sortByDesc(fn ($r) => $r['bookings'] * 1000000 + $r['handled'])
            ->values()->all();
    }

    /** Входящие / принятые / пропущенные / исходящие звонки по callid. */
    private function callsBetween(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('deal_activities')
            ->where('account_id', $this->accountId)->where('type', 'call')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) IN ('INCOMING', 'ACCEPTED', 'OUTGOING')")
            ->groupBy('t', 'c')
            ->get([
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) as t"),
                DB::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.callid')) as c"),
                DB::raw('MIN(created_at) as at'),
            ]);
        $by = ['INCOMING' => [], 'ACCEPTED' => [], 'OUTGOING' => []];
        foreach ($rows as $r) {
            if ($r->c !== null && $r->c !== '') {
                $by[$r->t][$r->c] = $r->at;
            }
        }
        $days = [];
        foreach ($by['INCOMING'] as $at) {
            $d = substr((string) $at, 0, 10);
            $days[$d] = ($days[$d] ?? 0) + 1;
        }
        $incoming = count($by['INCOMING']);
        $answered = count(array_intersect_key($by['INCOMING'], $by['ACCEPTED']));

        return [
            'totals' => [
                'incoming' => $incoming,
                'answered' => $answered,
                'missed' => $incoming - $answered,
                'outgoing' => count($by['OUTGOING']),
            ],
            'incoming_days' => $days,
        ];
    }

    /** Ряд по дням (до 62 дней) или по месяцам: лиды, замеры, входящие звонки. */
    private function series(Carbon $from, Carbon $to, Collection $leads, Collection $bookings, array $callDays): array
    {
        $byMonth = $from->diffInDays($to) > 62;
        $fmt = $byMonth ? 'Y-m' : 'Y-m-d';
        $buckets = [];
        $period = CarbonPeriod::create($from->copy()->startOfDay(), $byMonth ? '1 month' : '1 day', $to->copy()->subSecond());
        foreach ($period as $p) {
            $buckets[$p->format($fmt)] = ['key' => $p->format($fmt), 'leads' => 0, 'bookings' => 0, 'calls' => 0];
        }
        foreach ($leads as $d) {
            $k = Carbon::parse($d->created_at)->format($fmt);
            if (isset($buckets[$k])) {
                $buckets[$k]['leads']++;
            }
        }
        foreach ($bookings as $b) {
            $k = $b->at->format($fmt);
            if (isset($buckets[$k])) {
                $buckets[$k]['bookings']++;
            }
        }
        foreach ($callDays as $day => $n) {
            $k = $byMonth ? substr($day, 0, 7) : $day;
            if (isset($buckets[$k])) {
                $buckets[$k]['calls'] += $n;
            }
        }

        return ['by' => $byMonth ? 'month' : 'day', 'rows' => array_values($buckets)];
    }
}
