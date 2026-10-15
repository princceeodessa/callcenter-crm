<?php

namespace App\Services\Owner;

use App\Models\Deal;
use App\Models\User;
use App\Services\Owner\Marketing\MarketingStats;
use App\Support\Deals\NonTargetCategory;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Шапка страниц владельца: поиск по сделкам и клиентам, карточка сделки или клиента (только просмотр — в сделки CRM
 * у владельца доступа нет) и уведомления о событиях последних дней. Бизнесы — те, что видит пользователь: кроссовки
 * (его пространство) и потолки (только владелец всех бизнесов, users.all_businesses).
 */
class OwnerFeed
{
    /** Уведомления — за столько последних суток. */
    public const DAYS = 3;

    /** Телефон без пробелов, скобок, дефисов и «+» — для поиска по номеру. */
    private const PHONE_SQL = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(contacts.phone, ' ', ''), '-', ''), '(', ''), ')', ''), '+', '')";

    /** @var array<int, string> account_id => бизнес */
    private array $accounts = [];

    private int $ceilings;

    private ?array $stageNames = null;

    public function __construct(User $user)
    {
        $this->ceilings = (int) config('owner.ceilings_account_id');
        if ($user->role === 'sneaker_owner' && $user->all_businesses && $this->ceilings > 0) {
            $this->accounts[$this->ceilings] = 'Потолки';
        }
        if ((int) $user->account_id !== $this->ceilings) {
            $this->accounts[(int) $user->account_id] = 'Кроссовки';
        }
    }

    /** @return array{deals: list<array>, clients: list<array>} */
    public function search(string $q): array
    {
        $q = trim(mb_substr($q, 0, 80));
        if (mb_strlen($q) < 2 || ! $this->accounts) {
            return ['deals' => [], 'clients' => []];
        }
        $digits = preg_replace('/\D+/', '', $q);
        $isPhone = strlen($digits) >= 4 && preg_match('/^[\d\s()+\-]+$/', $q) === 1;
        // номер ищем без кода страны: «8 912…», «+7 912…» и «912…» — один и тот же клиент
        $needles = [strlen($digits) > 10 ? substr($digits, -10) : $digits];
        if (strlen($digits) >= 5 && strlen($digits) <= 10 && preg_match('/^\s*(\+\s*7|7|8)/', $q)) {
            $needles[] = substr($digits, 1);
        }
        $like = '%'.addcslashes($q, '%_\\').'%';

        $contacts = DB::table('contacts')->whereIn('account_id', array_keys($this->accounts))
            ->when($isPhone,
                fn ($w) => $w->where(function ($w) use ($needles) {
                    foreach ($needles as $n) {
                        $w->orWhereRaw(self::PHONE_SQL.' LIKE ?', ['%'.$n.'%']);
                    }
                }),
                fn ($w) => $w->where('name', 'like', $like))
            ->orderByDesc('updated_at')->orderByDesc('id')->limit(40)
            ->get(['id', 'account_id', 'name', 'phone']);

        $deals = DB::table('deals')->whereIn('account_id', array_keys($this->accounts))
            ->where(function ($w) use ($isPhone, $needles, $like, $contacts) {
                foreach ($isPhone ? $needles : [] as $n) {
                    $w->orWhere('title', 'like', '%'.$n.'%');
                }
                if (! $isPhone) {
                    $w->where('title', 'like', $like);
                }
                if ($contacts->isNotEmpty()) {
                    $w->orWhereIn('contact_id', $contacts->pluck('id'));
                }
            })
            ->orderByDesc('updated_at')->orderByDesc('id')->limit(6)
            ->get();

        $dealCounts = $contacts->isEmpty() ? collect() : DB::table('deals')->whereIn('contact_id', $contacts->pluck('id')->take(5))
            ->groupBy('contact_id')->selectRaw('contact_id, COUNT(*) as c')->pluck('c', 'contact_id');

        return [
            'deals' => $this->dealRows($deals),
            'clients' => $contacts->take(5)->map(fn ($c) => [
                'id' => (int) $c->id,
                'name' => trim((string) $c->name) !== '' ? trim((string) $c->name) : 'Без имени',
                'phone' => self::maskPhone($c->phone),
                'deals' => (int) ($dealCounts[$c->id] ?? 0),
                'biz' => $this->bizLabel((int) $c->account_id),
            ])->values()->all(),
        ];
    }

    /** Карточка сделки: факты и лента событий. */
    public function deal(int $id): ?array
    {
        $d = DB::table('deals')->where('id', $id)->whereIn('account_id', array_keys($this->accounts))->first();
        if (! $d) {
            return null;
        }
        $isCeilings = (int) $d->account_id === $this->ceilings;
        $contact = $d->contact_id ? DB::table('contacts')->where('id', $d->contact_id)->first(['id', 'name', 'phone']) : null;
        $userIds = array_filter([$d->responsible_user_id, $d->closed_by_user_id]);
        $booked = null;
        $facts = [['Статус', $this->status($d)]];
        if ($isCeilings) {
            $kpi = new CeilingsKpi($this->ceilings);
            $facts[] = ['Источник', self::shortChannel($kpi->channelsFor([(int) $d->id])[(int) $d->id] ?? 'crm', false)];
            $booked = $kpi->bookedAtFor([(int) $d->id])[(int) $d->id] ?? null;
            if ($booked?->by) {
                $userIds[] = $booked->by;
            }
        }
        $names = DB::table('users')->whereIn('id', $userIds)->pluck('name', 'id');
        if ($booked) {
            $facts[] = ['Записан на замер', $booked->at->format('d.m.Y H:i').($booked->by && isset($names[$booked->by]) ? ' · '.$names[$booked->by] : '')];
        }
        if ($d->warehouse_item_id) {
            $item = DB::table('warehouse_items')->where('id', $d->warehouse_item_id)->first(['brand', 'model', 'size']);
            if ($item) {
                $facts[] = ['Модель', trim($item->brand.' '.$item->model).($item->size ? ' · р. '.$item->size : '')];
            }
        }
        if ((float) $d->amount > 0) {
            $facts[] = ['Сумма', self::money((float) $d->amount)];
        }
        $facts[] = ['Создана', Carbon::parse($d->created_at)->format('d.m.Y H:i')];
        if ($d->responsible_user_id && isset($names[$d->responsible_user_id])) {
            $facts[] = ['Ответственный', $names[$d->responsible_user_id]];
        }
        if ($d->closed_at) {
            $facts[] = ['Завершена', Carbon::parse($d->closed_at)->format('d.m.Y H:i')
                .($d->closed_by_user_id && isset($names[$d->closed_by_user_id]) ? ' · '.$names[$d->closed_by_user_id] : '')];
            if (trim((string) $d->closed_reason) !== '') {
                $facts[] = ['Комментарий', mb_strimwidth(trim((string) $d->closed_reason), 0, 300, '…')];
            }
        }

        return [
            'id' => (int) $d->id,
            'title' => $this->title($d, $contact),
            'biz' => $this->accounts[(int) $d->account_id],
            'contact' => $contact ? [
                'id' => (int) $contact->id,
                'name' => trim((string) $contact->name),
                'phone' => self::formatPhone($contact->phone),
                'tel' => self::telLink($contact->phone),
            ] : null,
            'facts' => array_map(fn ($f) => ['label' => $f[0], 'value' => $f[1]], $facts),
            'events' => $this->timeline($d),
        ];
    }

    /** Карточка клиента: контакты и все его сделки. */
    public function client(int $id): ?array
    {
        $c = DB::table('contacts')->where('id', $id)->whereIn('account_id', array_keys($this->accounts))->first(['id', 'account_id', 'name', 'phone']);
        if (! $c) {
            return null;
        }
        $deals = DB::table('deals')->where('contact_id', $c->id)->orderByDesc('created_at')->orderByDesc('id')->limit(30)->get();

        return [
            'id' => (int) $c->id,
            'name' => trim((string) $c->name) !== '' ? trim((string) $c->name) : 'Без имени',
            'biz' => $this->accounts[(int) $c->account_id],
            'phone' => self::formatPhone($c->phone),
            'tel' => self::telLink($c->phone),
            'deals' => $this->dealRows($deals),
        ];
    }

    /**
     * Уведомления за последние сутки (DAYS): потолки — записи на замер, пропущенные звонки (по часам), формы с сайта
     * и ВК, итоги дня; кроссовки — продажи, возвраты, итоги дня. Новые сверху; что прочитано — помнит браузер.
     *
     * @return list<array{id: string, icon: string, tone: string, text: string, sub: string, at: string, deal: ?int, url: ?string}>
     */
    public function notifications(Carbon $now): array
    {
        $since = $now->copy()->subDays(self::DAYS);
        $items = [];
        $add = function (string $id, string $icon, string $tone, string $text, string $sub, Carbon $at, ?int $deal = null, ?string $url = null) use (&$items) {
            $items[] = ['id' => $id, 'icon' => $icon, 'tone' => $tone, 'text' => $text, 'sub' => $sub, 'at' => $at->toIso8601String(), 'deal' => $deal, 'url' => $url];
        };

        if (isset($this->accounts[$this->ceilings])) {
            $this->ceilingsNotifications($since, $now, $add);
        }
        foreach (array_keys($this->accounts) as $acc) {
            if ($acc !== $this->ceilings) {
                $this->sneakersNotifications($acc, $since, $now, $add);
            }
        }
        usort($items, fn ($a, $b) => strcmp($b['at'], $a['at']) ?: strcmp($a['id'], $b['id']));

        return array_slice($items, 0, 40);
    }

    private function ceilingsNotifications(Carbon $since, Carbon $now, callable $add): void
    {
        $acc = $this->ceilings;
        $kpi = new CeilingsKpi($acc);

        // записали на замер
        $bookings = $kpi->bookingsBetween($since, $now);
        $channels = $kpi->channelsFor($bookings->pluck('id')->all());
        $info = $this->dealInfo($bookings->pluck('id')->all());
        $names = DB::table('users')->whereIn('id', $bookings->pluck('by')->filter()->unique())->pluck('name', 'id');
        foreach ($bookings as $b) {
            $add('book:'.$b->id, 'measure', 'green', 'Новый замер записан — '.self::shortChannel($channels[$b->id] ?? 'crm', true),
                implode(' · ', array_filter([$b->by ? ($names[$b->by] ?? null) : null, $info[$b->id]['client'] ?? null])), $b->at, $b->id);
        }

        // пропущенные входящие (звонок без «принят»); ещё звонящие — не в счёт. По часам: «3 пропущенных звонка».
        $calls = [];
        $rows = DB::table('deal_activities')->where('account_id', $acc)->where('type', 'call')
            ->where('created_at', '>=', $since)->where('created_at', '<=', $now)
            ->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.type')) IN ('INCOMING', 'ACCEPTED')")
            ->orderBy('id')->get(['deal_id', 'payload', 'created_at']);
        foreach ($rows as $r) {
            $p = json_decode((string) $r->payload, true) ?: [];
            $cid = (string) ($p['callid'] ?? '');
            if ($cid === '') {
                continue;
            }
            $calls[$cid] ??= ['at' => null, 'accepted' => false, 'deal' => (int) $r->deal_id, 'key' => null];
            if (($p['type'] ?? '') === 'ACCEPTED') {
                $calls[$cid]['accepted'] = true;
            } elseif ($calls[$cid]['at'] === null) {
                $calls[$cid]['at'] = Carbon::parse($r->created_at);
                $calls[$cid]['key'] = Deal::resolveIncomingPhoneSourceFilterKeyFromPayload($p) ?? 'phone_other';
            }
        }
        $missed = collect($calls)->filter(fn ($c) => ! $c['accepted'] && $c['at'] !== null && $c['at']->lte($now->copy()->subMinutes(3)))
            ->groupBy(fn ($c) => $c['at']->format('Y-m-d H'));
        foreach ($missed as $hour => $group) {
            $last = $group->sortBy(fn ($c) => $c['at']->getTimestamp())->last();
            $sources = $group->map(fn ($c) => self::shortChannel($c['key'], true))->unique()->implode(', ');
            $n = $group->count();
            $start = Carbon::createFromFormat('Y-m-d H', $hour)->minute(0);
            $add('miss:'.$hour, 'missed', 'red',
                $n === 1 ? 'Пропущенный звонок — '.$sources : $n.' '.self::plural($n, 'пропущенный звонок', 'пропущенных звонка', 'пропущенных звонков'),
                $n === 1 ? ($this->dealInfo([$last['deal']])[$last['deal']]['client'] ?? 'клиент не перезвонил — стоит набрать')
                    : 'с '.$start->format('H:i').' до '.$start->copy()->addHour()->format('H:i').' · '.$sources,
                $last['at'], $n === 1 ? $last['deal'] : null);
        }

        // формы с сайта и лид-формы ВК
        $forms = DB::table('deal_activities')->where('account_id', $acc)->where('type', 'lead_form')
            ->where('created_at', '>=', $since)->where('created_at', '<=', $now)->orderBy('id')
            ->get(['id', 'deal_id', 'payload', 'created_at']);
        $info = $this->dealInfo($forms->pluck('deal_id')->map(fn ($id) => (int) $id)->all());
        foreach ($forms as $f) {
            $p = json_decode((string) $f->payload, true) ?: [];
            $provider = mb_strtolower((string) ($p['provider'] ?? ''));
            $label = match ($provider) {
                'tilda' => 'Новая форма с сайта',
                'vk' => 'Новая лид-форма ВКонтакте',
                default => 'Новая форма'.($provider !== '' ? ' ('.$provider.')' : ''),
            };
            $i = $info[(int) $f->deal_id] ?? [];
            $add('form:'.$f->id, 'form', 'violet', $label, implode(' · ', array_filter([$i['client'] ?? null, $i['status'] ?? null])),
                Carbon::parse($f->created_at), (int) $f->deal_id);
        }

        // итоги дня — утром следующего дня
        foreach ($this->summaryDays($since, $now) as [$day, $at]) {
            $s = Cache::remember('owner_feed:ceilings_day:'.$day->toDateString(), now()->addMinutes(30), function () use ($kpi, $day) {
                $to = $day->copy()->addDay();
                $sum = $kpi->daySummary($day, $to);
                $sheet = MarketingStats::measurements($day, $to);

                return $sum + ['sheet' => $sheet['available'] ? (int) $sheet['total'] : null, 'spend' => (float) MarketingStats::forPeriod($day, $to)['spend_total']];
            });
            $parts = ['обратились '.$s['contacts'], $s['sheet'] !== null ? 'замеров '.$s['sheet'] : 'записано на замер '.$s['bookings']];
            if (($s['calls']['missed'] ?? 0) > 0) {
                $parts[] = 'пропущено звонков '.$s['calls']['missed'];
            }
            if ($s['spend'] > 0) {
                $parts[] = 'реклама '.self::money($s['spend']);
            }
            $add('day:c:'.$day->toDateString(), 'report', 'green', 'Итоги за '.$day->format('d.m').' по потолкам', implode(' · ', $parts), $at,
                null, route('owner.ceilings', ['period' => 'custom', 'from' => $day->toDateString(), 'to' => $day->toDateString()]));
        }
    }

    private function sneakersNotifications(int $acc, Carbon $since, Carbon $now, callable $add): void
    {
        $sales = DB::table('deals')->where('account_id', $acc)->whereNotNull('stock_deducted_at')
            ->whereBetween('stock_deducted_at', [$since, $now])->get(['id', 'title', 'amount', 'sold_quantity', 'responsible_user_id', 'stock_deducted_at']);
        $returns = DB::table('deals')->where('account_id', $acc)->whereNotNull('returned_at')
            ->whereBetween('returned_at', [$since, $now])->get(['id', 'title', 'amount', 'returned_at']);
        $names = DB::table('users')->whereIn('id', $sales->pluck('responsible_user_id')->filter()->unique())->pluck('name', 'id');
        $model = fn ($d) => trim(preg_replace('/^Продажа:\s*/u', '', (string) $d->title)) ?: 'пара';
        foreach ($sales as $d) {
            $add('sale:'.$d->id, 'sale', 'green', 'Продажа — '.$model($d),
                implode(' · ', array_filter([(float) $d->amount > 0 ? self::money((float) $d->amount) : null, $names[$d->responsible_user_id] ?? null])),
                Carbon::parse($d->stock_deducted_at), (int) $d->id);
        }
        foreach ($returns as $d) {
            $add('ret:'.$d->id, 'return', 'red', 'Возврат — '.$model($d), (float) $d->amount > 0 ? self::money((float) $d->amount) : '',
                Carbon::parse($d->returned_at), (int) $d->id);
        }
        foreach ($this->summaryDays($since, $now) as [$day, $at]) {
            $sold = DB::table('deals')->where('account_id', $acc)->whereNotNull('stock_deducted_at')
                ->whereBetween('stock_deducted_at', [$day, $day->copy()->endOfDay()])
                ->selectRaw('COUNT(*) c, COALESCE(SUM(sold_quantity), 0) u, COALESCE(SUM(amount), 0) a')->first();
            $add('day:s:'.$day->toDateString(), 'report', 'green', 'Итоги за '.$day->format('d.m').' по кроссовкам',
                (int) $sold->c > 0 ? 'продано '.(int) $sold->u.' '.self::plural((int) $sold->u, 'пара', 'пары', 'пар').' на '.self::money((float) $sold->a) : 'продаж не было',
                $at, null, route('sale.day', ['date' => $day->toDateString()]));
        }
    }

    /** Дни, итоги которых вышли (в 9:00 следующего дня) в окне уведомлений. @return list<array{0: Carbon, 1: Carbon}> */
    private function summaryDays(Carbon $since, Carbon $now): array
    {
        $out = [];
        for ($day = $since->copy()->startOfDay()->subDay(); $day->lt($now); $day->addDay()) {
            $at = $day->copy()->addDay()->setTime(9, 0);
            if ($at->gte($since) && $at->lte($now)) {
                $out[] = [$day->copy(), $at];
            }
        }

        return $out;
    }

    /** Строки сделок для списков поиска и клиента. */
    private function dealRows(Collection $deals): array
    {
        $contacts = DB::table('contacts')->whereIn('id', $deals->pluck('contact_id')->filter()->unique())->get(['id', 'name', 'phone'])->keyBy('id');
        $ceilIds = $deals->filter(fn ($d) => (int) $d->account_id === $this->ceilings)->pluck('id')->map(fn ($id) => (int) $id)->all();
        $channels = $ceilIds ? (new CeilingsKpi($this->ceilings))->channelsFor($ceilIds) : [];

        return $deals->map(fn ($d) => [
            'id' => (int) $d->id,
            'title' => $this->title($d, $contacts[$d->contact_id] ?? null),
            'sub' => implode(' · ', array_filter([$this->status($d), isset($channels[(int) $d->id]) ? self::shortChannel($channels[(int) $d->id], false) : null])),
            'amount' => (float) $d->amount > 0 ? self::money((float) $d->amount) : null,
            'date' => Carbon::parse($d->created_at)->format('d.m.y'),
            'biz' => $this->bizLabel((int) $d->account_id),
        ])->values()->all();
    }

    /** Клиент и статус сделок — для подписей уведомлений. @return array<int, array{client: ?string, status: string}> */
    private function dealInfo(array $ids): array
    {
        $deals = DB::table('deals')->whereIn('id', array_unique($ids))->get();
        $contacts = DB::table('contacts')->whereIn('id', $deals->pluck('contact_id')->filter()->unique())->get(['id', 'name', 'phone'])->keyBy('id');
        $out = [];
        foreach ($deals as $d) {
            $c = $contacts[$d->contact_id] ?? null;
            $name = $c ? trim((string) $c->name) : '';
            $out[(int) $d->id] = [
                'client' => $name !== '' && ! preg_match('/^\+?\d[\d\s()-]*$/', $name) ? $name : ($c?->phone ? self::maskPhone($c->phone) : null),
                'status' => $this->status($d),
            ];
        }

        return $out;
    }

    /** Лента сделки: этапы, звонки, формы, сообщения клиента (по дням), закрытие. Новые сверху. */
    private function timeline(object $d): array
    {
        $acts = DB::table('deal_activities')->where('deal_id', $d->id)
            ->whereIn('type', ['stage_changed', 'deal_closed', 'deal_reopened', 'system', 'lead_form', 'call', 'message_in', 'comment'])
            ->orderBy('id')->limit(2000)->get(['type', 'body', 'payload', 'author_user_id', 'created_at']);
        $names = DB::table('users')->whereIn('id', $acts->pluck('author_user_id')->filter()->unique())->pluck('name', 'id');
        $stages = $this->stageNames();
        $events = [];
        $calls = [];
        $messages = [];
        $ev = function (string $at, string $text, string $tone, ?int $by = null) use (&$events, $names) {
            $events[] = ['at' => Carbon::parse($at)->toIso8601String(), 'text' => $text, 'tone' => $tone, 'who' => $by ? ($names[$by] ?? null) : null, 'n' => count($events)];
        };
        $ev((string) $d->created_at, 'Сделка создана', 'gray');
        foreach ($acts as $a) {
            $p = json_decode((string) $a->payload, true) ?: [];
            switch ($a->type) {
                case 'stage_changed':
                    $from = $stages[(int) ($p['from_stage_id'] ?? 0)] ?? null;
                    $to = $stages[(int) ($p['to_stage_id'] ?? 0)] ?? null;
                    if ($to !== null) {
                        $ev($a->created_at, ($from !== null ? $from.' → ' : 'Этап: ').$to, 'violet', $a->author_user_id);
                    }
                    break;
                case 'deal_closed':
                case 'deal_reopened':
                case 'system':
                case 'comment':
                    $text = trim(preg_replace('/\s+/u', ' ', (string) $a->body));
                    if ($text !== '') {
                        $ev($a->created_at, ($a->type === 'comment' ? 'Комментарий: ' : '').mb_strimwidth($text, 0, 160, '…'),
                            $a->type === 'deal_closed' ? 'green' : 'gray', $a->author_user_id);
                    }
                    break;
                case 'lead_form':
                    $provider = mb_strtolower((string) ($p['provider'] ?? ''));
                    $ev($a->created_at, $provider === 'vk' ? 'Лид-форма ВКонтакте'.(! empty($p['form_name']) ? ': '.$p['form_name'] : '') : 'Форма с сайта', 'violet');
                    break;
                case 'call':
                    $cid = (string) ($p['callid'] ?? '');
                    $type = (string) ($p['type'] ?? '');
                    if ($cid === '' || ! in_array($type, ['INCOMING', 'ACCEPTED', 'OUTGOING'], true)) {
                        break;
                    }
                    $calls[$cid] ??= ['at' => $a->created_at, 'out' => false, 'in' => false, 'accepted' => false];
                    $calls[$cid][$type === 'OUTGOING' ? 'out' : ($type === 'INCOMING' ? 'in' : 'accepted')] = true;
                    break;
                case 'message_in':
                    $day = substr((string) $a->created_at, 0, 10);
                    $messages[$day] ??= ['at' => $a->created_at, 'n' => 0, 'last' => ''];
                    $messages[$day]['n']++;
                    $messages[$day]['last'] = trim(preg_replace('/\s+/u', ' ', (string) $a->body));
                    break;
            }
        }
        foreach ($calls as $c) {
            if ($c['out']) {
                $ev($c['at'], 'Исходящий звонок', 'gray');
            } elseif ($c['in']) {
                $ev($c['at'], $c['accepted'] ? 'Входящий звонок — принят' : 'Входящий звонок — пропущен', $c['accepted'] ? 'green' : 'red');
            }
        }
        foreach ($messages as $m) {
            $ev($m['at'], 'Клиент написал'.($m['n'] > 1 ? ' ('.$m['n'].' '.self::plural($m['n'], 'сообщение', 'сообщения', 'сообщений').')' : '')
                .($m['last'] !== '' ? ': «'.mb_strimwidth($m['last'], 0, 90, '…').'»' : ''), 'gray');
        }
        // новые сверху; в одну секунду — что записано позже, выше («Сделка создана» — последней)
        usort($events, fn ($a, $b) => strcmp($b['at'], $a['at']) ?: $b['n'] <=> $a['n']);

        return array_map(fn ($e) => array_diff_key($e, ['n' => 0]), array_slice($events, 0, 15));
    }

    private function status(object $d): string
    {
        $isCeilings = (int) $d->account_id === $this->ceilings;
        if (! $isCeilings) {
            if ($d->returned_at ?? null) {
                return 'Возврат';
            }
            if ($d->stock_deducted_at ?? null) {
                return 'Продано';
            }
        }
        if ($d->closed_at !== null) {
            $cat = NonTargetCategory::label($d->non_target_category ?? null);

            return match ($d->closed_result) {
                'won' => $isCeilings ? 'Завершена · успешно' : 'Продано',
                'lost' => 'Завершена · отказ',
                'extra_non_target' => 'Нецелевое'.($cat ? ' · '.$cat : ''),
                default => 'Завершена',
            };
        }
        $stage = $this->stageNames()[(int) $d->stage_id] ?? '';
        $cat = NonTargetCategory::label($d->non_target_category ?? null);

        return $stage.($cat && mb_stripos($stage, 'нецелев') !== false ? ' · '.$cat : '');
    }

    private function title(object $d, ?object $contact): string
    {
        $title = trim((string) $d->title);
        $name = $contact ? trim((string) $contact->name) : '';
        // сделки звонков называются «Звонки: 79001234567» — у владельца номер красивее, а имя клиента — понятнее
        if (preg_match('/^Звонки:\s*(\+?\d+)$/u', $title, $m)) {
            return $name !== '' && ! preg_match('/^\+?\d[\d\s()-]*$/', $name) ? $name.' — звонки' : 'Звонки: '.self::formatPhone($m[1]);
        }

        return $title !== '' ? $title : ($name !== '' ? $name : 'Сделка №'.$d->id);
    }

    /** Названия этапов без хвоста после «/» («Поступил лид / Назначить ответственного» → «Поступил лид»). */
    private function stageNames(): array
    {
        return $this->stageNames ??= DB::table('pipeline_stages')->whereIn('account_id', array_keys($this->accounts))->pluck('name', 'id')
            ->map(fn ($n) => trim(explode('/', (string) $n)[0]))->all();
    }

    private function bizLabel(int $accountId): ?string
    {
        return count($this->accounts) > 1 ? ($this->accounts[$accountId] ?? null) : null;
    }

    /** Источник коротко: «Звонок · Директ (…4552)» → «Директ» (для звонков) или «Звонок · Директ». */
    private static function shortChannel(string $key, bool $dropCall): string
    {
        $label = preg_replace('/\s*\(…\d+\)$/u', '', CeilingsKpi::channelLabel($key));

        return $dropCall ? preg_replace('/^Звонок · /u', '', $label) : $label;
    }

    public static function maskPhone(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($d) === 11 && in_array($d[0], ['7', '8'], true)) {
            return '+7 '.substr($d, 1, 3).' ••• '.substr($d, 7, 2).' '.substr($d, 9, 2);
        }

        return strlen($d) > 4 ? '••• '.substr($d, -4) : (string) $phone;
    }

    public static function formatPhone(?string $phone): string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($d) === 11 && in_array($d[0], ['7', '8'], true)) {
            return '+7 ('.substr($d, 1, 3).') '.substr($d, 4, 3).'-'.substr($d, 7, 2).'-'.substr($d, 9, 2);
        }

        return trim((string) $phone);
    }

    private static function telLink(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if (strlen($d) === 11 && in_array($d[0], ['7', '8'], true)) {
            return '+7'.substr($d, 1);
        }

        return strlen($d) >= 5 ? $d : null;
    }

    private static function money(float $v): string
    {
        return number_format(round($v), 0, ',', ' ').' ₽';
    }

    private static function plural(int $n, string $one, string $few, string $many): string
    {
        $n = abs($n) % 100;
        $n1 = $n % 10;

        return $n > 10 && $n < 20 ? $many : ($n1 > 1 && $n1 < 5 ? $few : ($n1 === 1 ? $one : $many));
    }
}
