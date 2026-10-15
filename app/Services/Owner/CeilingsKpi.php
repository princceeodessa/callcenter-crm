<?php

namespace App\Services\Owner;

use App\Models\Deal;
use App\Support\Deals\NonTargetCategory;
use App\Support\Leads\LeadDeduplicator;
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
 * - Обратились — клиенты, которые сами позвонили, написали или оставили контакты в форме в периоде (contactsBetween).
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

    /** Как клиент обратился впервые за период — для разбивки источника. */
    public const CONTACT_KINDS = [
        'call' => 'позвонили',
        'chat_vk' => 'написали в сообщения',
        'chat_avito' => 'написали в чат',
        'chat_telegram' => 'написали в Telegram',
        'form_tilda' => 'форма на сайте',
        'form_vk' => 'лид-форма',
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
        $contacts = $this->contactsBetween($from, $to);

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
            'contacts' => $contacts,
            'prev' => [
                'leads' => count($prevLeads),
                'bookings' => count($this->bookingsBetween($prevFrom, $from)),
                'conversion' => count($prevLeads) > 0 ? $prevBooked / count($prevLeads) * 100 : null,
                'incoming' => $prevCalls['totals']['incoming'],
                'contacts' => $this->contactsBetween($prevFrom, $from)['total'],
            ],
            'channels' => $this->channelTable($leads, $outcome, $channels),
            'operators' => $this->operatorTable($from, $to, $bookings),
            'series' => $this->series($from, $to, $leads, $bookings, $calls['incoming_days'], $contacts['by_day']),
        ];
    }

    /** Короткие итоги дня — для уведомлений владельца: обратились, записаны на замер, звонки. */
    public function daySummary(Carbon $from, Carbon $to): array
    {
        return [
            'contacts' => $this->contactsBetween($from, $to)['total'],
            'bookings' => count($this->bookingsBetween($from, $to)),
            'calls' => $this->callsBetween($from, $to)['totals'],
        ];
    }

    /** @return array{measure: ?int, non_target: ?int} */
    private function stages(): array
    {
        if ($this->stageIds === null) {
            $rows = DB::table('pipeline_stages')->where('account_id', $this->accountId)->get(['id', 'name']);
            // «Нецелевое дозвон» (исходящие дозвоны) — не «Нецелевое»
            $find = fn (string $needle) => $rows->first(fn ($s) => mb_stripos((string) $s->name, $needle) !== false && mb_stripos((string) $s->name, 'дозвон') === false)?->id;
            $this->stageIds = ['measure' => $find('Замер назначен'), 'non_target' => $find('Нецелев')];
        }

        return $this->stageIds;
    }

    private function outcome(object $deal, bool $booked): string
    {
        // оператор отметил категорию нецелевого — это его решение, даже если сделка когда-то была на замере
        if (NonTargetCategory::label($deal->non_target_category ?? null) !== null) {
            return 'non_target';
        }
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
    public function bookedAtFor(array $dealIds): array
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
    public function bookingsBetween(Carbon $from, Carbon $to): Collection
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
    public function channelsFor(array $dealIds): array
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

    public static function contactKindLabel(string $kind): string
    {
        return self::CONTACT_KINDS[$kind] ?? match (true) {
            str_starts_with($kind, 'chat_') => 'написали ('.substr($kind, 5).')',
            str_starts_with($kind, 'form_') => 'форма ('.substr($kind, 5).')',
            default => $kind,
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
     * Кто первым взял новый лид — тот за него и отвечает (решение руководства 15.10.2026). «Взял» — первым из людей
     * перетащил сделку с первого этапа («Поступил лид») или закрыл, не двигая. По лидам, взятым в периоде: записал сам;
     * не записал — в том числе если потом записал другой (rescued); нецелевые. «Дожал чужих» — замеры в периоде по
     * лидам, которые первым брал другой. Сделки из исходящих звонков (дозвоны, «Нецелевое дозвон») — не лиды.
     *
     * @return array<int, array{taken:int, booked_self:int, not_booked:int, rescued:int, non_target:int, booked_others:int}>
     */
    public function handlerStats(Carbon $from, Carbon $to, ?Collection $bookings = null): array
    {
        $bookings ??= $this->bookingsBetween($from, $to);
        $candidates = DB::table('deal_stage_history')->where('account_id', $this->accountId)
            ->whereNotNull('changed_by_user_id')->where('changed_at', '>=', $from)->where('changed_at', '<', $to)
            ->distinct()->pluck('deal_id')
            ->merge(DB::table('deals')->where('account_id', $this->accountId)->whereNotNull('closed_by_user_id')
                ->where('closed_at', '>=', $from)->where('closed_at', '<', $to)->pluck('id'))
            ->merge($bookings->pluck('id'))
            ->map(fn ($id) => (int) $id)->unique()->values()->all();
        if ($candidates === []) {
            return [];
        }
        [$handler, $deals] = $this->leadHandlers($candidates);

        $booked = $this->bookedAtFor(array_keys($handler));
        $stats = [];
        $row = function (int $user) use (&$stats) {
            $stats[$user] ??= ['taken' => 0, 'booked_self' => 0, 'not_booked' => 0, 'rescued' => 0, 'non_target' => 0, 'booked_others' => 0];
        };
        foreach ($handler as $id => $h) {
            if ($h['at']->lt($from) || $h['at']->gte($to)) {
                continue;
            }
            $user = $h['user'];
            $row($user);
            $stats[$user]['taken']++;
            $b = $booked[$id] ?? null;
            if ($b !== null && $b->by === $user) {
                $stats[$user]['booked_self']++;
            } elseif ($this->outcome($deals[$id], $b !== null) === 'non_target') {
                $stats[$user]['non_target']++;
            } else {
                $stats[$user]['not_booked']++;
                $stats[$user]['rescued'] += $b !== null ? 1 : 0;
            }
        }
        foreach ($bookings as $b) {
            $h = $handler[(int) $b->id] ?? null;
            if ($b->by !== null && $h !== null && $h['user'] !== $b->by) {
                $row($b->by);
                $stats[$b->by]['booked_others']++;
            }
        }

        return $stats;
    }

    /**
     * Кто первым взял каждую из сделок: первым из людей перетащил её с первого этапа («Поступил лид») или закрыл, не
     * двигая. Сделки, заведённые исходящим звонком (первый звонок — исходящий), без $withOutbound не берутся — это дозвоны.
     *
     * @return array{0: array<int, array{user:int, at:Carbon}>, 1: array<int, object>} [сделка => кто и когда, сделки]
     */
    private function leadHandlers(array $dealIds, bool $withOutbound = false): array
    {
        // первый этап каждой воронки — «Поступил лид»
        $leadStages = DB::table('pipeline_stages')->where('account_id', $this->accountId)->orderBy('sort')->orderBy('id')
            ->get(['id', 'pipeline_id'])->groupBy('pipeline_id')->map(fn ($s) => (int) $s->first()->id)->flip()->all();
        $deals = [];
        $handler = [];
        foreach (array_chunk(array_values(array_unique($dealIds)), 1000) as $chunk) {
            foreach (DB::table('deals')->whereIn('id', $chunk)->get(['id', 'stage_id', 'closed_at', 'closed_result', 'closed_by_user_id', 'non_target_category']) as $d) {
                $deals[(int) $d->id] = $d;
            }
            $firstMove = [];
            foreach (DB::table('deal_stage_history')->whereIn('deal_id', $chunk)->whereNotNull('changed_by_user_id')
                ->orderBy('changed_at')->orderBy('id')->get(['deal_id', 'from_stage_id', 'changed_by_user_id', 'changed_at']) as $h) {
                $firstMove[(int) $h->deal_id] ??= $h;
            }
            // заведена исходящим звонком (первый звонок — исходящий) — дозвон, не лид
            $firstCallIds = DB::table('deal_activities')->whereIn('deal_id', $chunk)->where('type', 'call')
                ->groupBy('deal_id')->pluck(DB::raw('MIN(id)'))->all();
            $outbound = DB::table('deal_activities')->whereIn('id', $firstCallIds)
                ->whereRaw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type'))) IN ('out', 'outgoing')")
                ->pluck('deal_id')->map(fn ($id) => (int) $id)->flip()->all();
            foreach ($chunk as $id) {
                if ((! $withOutbound && isset($outbound[$id])) || ! isset($deals[$id])) {
                    continue;
                }
                $d = $deals[$id];
                $move = $firstMove[$id] ?? null;
                $closeAt = $d->closed_at !== null && $d->closed_by_user_id !== null ? Carbon::parse($d->closed_at) : null;
                if ($move !== null && ($closeAt === null || Carbon::parse($move->changed_at)->lte($closeAt))) {
                    if (isset($leadStages[(int) $move->from_stage_id])) {
                        $handler[$id] = ['user' => (int) $move->changed_by_user_id, 'at' => Carbon::parse($move->changed_at)];
                    }
                } elseif ($closeAt !== null) {
                    $handler[$id] = ['user' => (int) $d->closed_by_user_id, 'at' => $closeAt];
                }
            }
        }

        return [$handler, $deals];
    }

    /**
     * Список обратившихся за период для выгрузки в таблицу: номер, менеджер — кто первым взял лид, записался ли.
     * Нецелевые не попадают (их не надо потом вычищать руками); исходящие дозвоны — не обращения и так.
     *
     * @return list<array{phone: string, manager: string, booked: bool, at: string}>
     */
    public function contactList(Carbon $from, Carbon $to): array
    {
        [$clients] = $this->contactClients($from, $to);
        $ids = [];
        foreach ($clients as $c) {
            if ($c['outcome'] !== 'non_target') {
                array_push($ids, ...array_keys($c['deals']));
            }
        }
        [$handler] = $this->leadHandlers($ids, true);
        $names = DB::table('users')->whereIn('id', array_unique(array_column($handler, 'user')))->pluck('name', 'id');
        $channel = fn (string $kind) => ['chat_avito' => 'чат Авито', 'chat_vk' => 'ВК', 'chat_telegram' => 'Telegram', 'form_tilda' => 'форма на сайте', 'form_vk' => 'лид-форма ВК'][$kind] ?? $kind;

        $rows = [];
        foreach ($clients as $key => $c) {
            if ($c['outcome'] === 'non_target') {
                continue;
            }
            $first = null;
            foreach (array_keys($c['deals']) as $id) {
                if (isset($handler[$id]) && ($first === null || $handler[$id]['at']->lt($first['at']))) {
                    $first = $handler[$id];
                }
            }
            $digits = str_starts_with($key, 'p') ? substr($key, 1) : null;
            $rows[] = [
                'phone' => $digits !== null
                    ? (strlen($digits) === 11 ? '+'.$digits[0].' ('.substr($digits, 1, 3).') '.substr($digits, 4, 3).'-'.substr($digits, 7, 2).'-'.substr($digits, 9, 2) : '+'.$digits)
                    : 'без номера — '.$channel($c['kind']).($c['name'] !== '' ? ' ('.$c['name'].')' : ''),
                'manager' => $first !== null ? (string) ($names[$first['user']] ?? 'Пользователь #'.$first['user']) : 'не взят в работу',
                'booked' => $c['outcome'] === 'booked',
                'at' => $c['at'],
            ];
        }

        return $rows;
    }

    /**
     * Операторы — по их действиям в периоде (ответственный у сделок почти всегда «Admin», по нему не видно, кто работал):
     * обработано сделок — двигал по этапам или закрыл; замеров назначено — перевёл на «Замер назначен» / закрыл «Успешно»
     * первым; нецелевых и отказов — закрыл с таким итогом. Плюс учёт по тому, кто первым взял лид (handlerStats).
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
        $leads = $this->handlerStats($from, $to, $bookings);
        foreach (array_keys($leads) as $uid) {
            $touch($uid);
        }
        $names = DB::table('users')->whereIn('id', array_filter(array_keys($rows)))->pluck('name', 'id');

        return collect($rows)
            ->map(function ($r) use ($names, $leads) {
                $handled = count($r['handled']);
                $l = $leads[(int) $r['user_id']] ?? ['taken' => 0, 'booked_self' => 0, 'not_booked' => 0, 'rescued' => 0, 'non_target' => 0, 'booked_others' => 0];

                return [
                    'user_id' => $r['user_id'],
                    'name' => $r['user_id'] ? ($names[$r['user_id']] ?? 'Пользователь #'.$r['user_id']) : 'Система',
                    'handled' => $handled,
                    'bookings' => $r['bookings'],
                    'non_target' => $r['non_target'],
                    'lost' => $r['lost'],
                    'conversion' => $handled > 0 ? $r['bookings'] / $handled * 100 : null,
                    'taken' => $l['taken'],
                    'booked_self' => $l['booked_self'],
                    'not_booked' => $l['not_booked'],
                    'rescued' => $l['rescued'],
                    'non_target_taken' => $l['non_target'],
                    'booked_others' => $l['booked_others'],
                ];
            })
            ->filter(fn ($r) => $r['handled'] > 0 || $r['bookings'] > 0 || $r['taken'] > 0)
            ->sortByDesc(fn ($r) => $r['taken'] * 1000000 + $r['booked_self'] * 1000 + $r['handled'])
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

    /**
     * Обратившиеся клиенты: сами позвонили (входящий звонок), написали (входящее сообщение в чате) или оставили контакты
     * (форма) в периоде. Исходящие звонки операторов не в счёт — на новый номер CRM заводит сделку, но это не обращение.
     * Клиент — номер телефона контакта (без номера — контакт, без контакта — сделка): позвонил трижды и написал — одно
     * обращение. Источник клиента — его первое обращение в периоде. Итог — по его сделкам с обращениями: с начала периода
     * сделка дошла до «Замер назначен» или закрыта «Успешно» — «записался» (запись, сделанная раньше периода, — старая, не
     * в счёт); все нецелевые — «нецелевой»; иначе есть открытая — «в работе», нет — «отказ».
     */
    private function contactsBetween(Carbon $from, Carbon $to): array
    {
        [$clients, $deals, $bookedAt] = $this->contactClients($from, $to);

        $out = ['total' => 0, 'booked' => 0, 'non_target' => 0, 'lost' => 0, 'open' => 0, 'booked_earlier' => 0, 'cats' => []];
        $groups = [];
        foreach (self::GROUPS as $g => $label) {
            $groups[$g] = ['key' => $g, 'label' => $label, 'total' => 0, 'booked' => 0, 'non_target' => 0, 'cats' => [], 'kinds' => []];
        }
        $byDay = [];
        foreach ($clients as $c) {
            $o = $c['outcome'];
            // колонка статистики: записан / отказ / в работе или категория нецелевого (none — без категории, старые)
            $cat = $o;
            if ($o === 'non_target') {
                $cat = 'none';
                foreach (array_reverse(array_keys($c['deals'])) as $id) {
                    if (NonTargetCategory::label($deals[$id]->non_target_category ?? null) !== null) {
                        $cat = $deals[$id]->non_target_category;
                        break;
                    }
                }
            }
            $out['total']++;
            $out[$o]++;
            $out['cats'][$cat] = ($out['cats'][$cat] ?? 0) + 1;
            if ($o !== 'booked' && array_intersect_key($bookedAt, $c['deals'])) {
                $out['booked_earlier']++;   // записан ещё до периода — для проверки, в сводке не показывается
            }
            $g = self::channelGroup($c['key']);
            $groups[$g]['total']++;
            $groups[$g]['booked'] += $o === 'booked' ? 1 : 0;
            $groups[$g]['non_target'] += $o === 'non_target' ? 1 : 0;
            $groups[$g]['cats'][$cat] = ($groups[$g]['cats'][$cat] ?? 0) + 1;
            $k = &$groups[$g]['kinds'][$c['kind']];
            $k ??= ['total' => 0, 'booked' => 0, 'non_target' => 0, 'cats' => []];
            $k['total']++;
            $k['booked'] += $o === 'booked' ? 1 : 0;
            $k['non_target'] += $o === 'non_target' ? 1 : 0;
            $k['cats'][$cat] = ($k['cats'][$cat] ?? 0) + 1;
            unset($k);
            $day = substr($c['at'], 0, 10);
            $byDay[$day] = ($byDay[$day] ?? 0) + 1;
        }
        foreach ($groups as &$g) {
            uasort($g['kinds'], fn ($a, $b) => $b['total'] <=> $a['total']);
        }
        unset($g);

        return $out + ['groups' => array_values($groups), 'by_day' => $byDay];
    }

    /**
     * Обратившиеся клиенты периода (см. contactsBetween) — с их сделками и итогом.
     *
     * @return array{0: array<string, array{at:string, kind:string, key:string, name:string, deals:array<int,bool>, outcome:string}>, 1: array<int, object>, 2: array<int, object>}
     */
    private function contactClients(Carbon $from, Carbon $to): array
    {
        $touches = []; // [сделка, когда, как обратился, ключ канала]
        $calls = DB::table('deal_activities')
            ->where('account_id', $this->accountId)->where('type', 'call')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) IN ('INCOMING', 'in')")
            ->get(['deal_id', 'payload', 'created_at']);
        foreach ($calls as $c) {
            $payload = json_decode((string) $c->payload, true);
            $key = (is_array($payload) ? Deal::resolveIncomingPhoneSourceFilterKeyFromPayload($payload) : null) ?? 'phone_other';
            $touches[] = [(int) $c->deal_id, (string) $c->created_at, 'call', $key];
        }
        $chats = DB::table('messages')->join('conversations', 'conversations.id', '=', 'messages.conversation_id')
            ->where('messages.account_id', $this->accountId)->where('messages.direction', 'in')
            ->where('messages.created_at', '>=', $from)->where('messages.created_at', '<', $to)
            ->groupBy('conversations.deal_id', 'conversations.channel')
            ->get(['conversations.deal_id', 'conversations.channel', DB::raw('MIN(messages.created_at) as at')]);
        foreach ($chats as $c) {
            $kind = 'chat_'.mb_strtolower((string) $c->channel);
            $touches[] = [(int) $c->deal_id, (string) $c->at, $kind, $kind];
        }
        $forms = DB::table('deal_activities')
            ->where('account_id', $this->accountId)->where('type', 'lead_form')
            ->where('created_at', '>=', $from)->where('created_at', '<', $to)
            ->get(['deal_id', 'created_at', DB::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.provider')) as provider")]);
        foreach ($forms as $f) {
            $provider = mb_strtolower((string) ($f->provider ?? ''));
            $kind = 'form_'.($provider !== '' ? $provider : 'other');
            $touches[] = [(int) $f->deal_id, (string) $f->created_at, $kind, $kind];
        }

        $dealIds = array_values(array_unique(array_column($touches, 0)));
        $deals = [];
        foreach (array_chunk($dealIds, 1000) as $chunk) {
            $rows = DB::table('deals')->leftJoin('contacts', 'contacts.id', '=', 'deals.contact_id')->whereIn('deals.id', $chunk)
                ->get(['deals.id', 'deals.contact_id', 'deals.stage_id', 'deals.closed_at', 'deals.closed_result', 'deals.non_target_category', 'contacts.phone', 'contacts.name']);
            foreach ($rows as $d) {
                $deals[(int) $d->id] = $d;
            }
        }
        $bookedAt = $this->bookedAtFor($dealIds);
        $booked = array_filter($bookedAt, fn ($b) => $b->at->gte($from));

        usort($touches, fn ($a, $b) => strcmp($a[1], $b[1]));
        $clients = [];
        foreach ($touches as [$dealId, $at, $kind, $key]) {
            $d = $deals[$dealId] ?? null;
            $phone = LeadDeduplicator::normalizePhone($d?->phone);
            $client = $phone !== null ? 'p'.$phone : ($d?->contact_id ? 'c'.$d->contact_id : 'd'.$dealId);
            $clients[$client] ??= ['at' => $at, 'kind' => $kind, 'key' => $key, 'name' => trim((string) ($d?->name ?? '')), 'deals' => []];
            $clients[$client]['deals'][$dealId] = true;
        }
        foreach ($clients as &$c) {
            $outcomes = [];
            foreach (array_keys($c['deals']) as $id) {
                $outcomes[] = isset($deals[$id]) ? $this->outcome($deals[$id], isset($booked[$id])) : 'open';
            }
            $c['outcome'] = match (true) {
                in_array('booked', $outcomes, true) => 'booked',
                array_values(array_unique($outcomes)) === ['non_target'] => 'non_target',
                in_array('open', $outcomes, true) => 'open',
                default => 'lost',
            };
        }
        unset($c);

        return [$clients, $deals, $bookedAt];
    }

    /** Ряд по дням (до 62 дней) или по месяцам: лиды, обратившиеся, замеры, входящие звонки. */
    private function series(Carbon $from, Carbon $to, Collection $leads, Collection $bookings, array $callDays, array $contactDays): array
    {
        $byMonth = $from->diffInDays($to) > 62;
        $fmt = $byMonth ? 'Y-m' : 'Y-m-d';
        $buckets = [];
        $period = CarbonPeriod::create($from->copy()->startOfDay(), $byMonth ? '1 month' : '1 day', $to->copy()->subSecond());
        foreach ($period as $p) {
            $buckets[$p->format($fmt)] = ['key' => $p->format($fmt), 'leads' => 0, 'contacts' => 0, 'bookings' => 0, 'calls' => 0];
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
        foreach ($contactDays as $day => $n) {
            $k = $byMonth ? substr($day, 0, 7) : $day;
            if (isset($buckets[$k])) {
                $buckets[$k]['contacts'] += $n;
            }
        }

        return ['by' => $byMonth ? 'month' : 'day', 'rows' => array_values($buckets)];
    }
}
