@extends('layouts.owner')

{{--
    «Расторжения договоров» подробно (потолки) — весь год по готовым итогам CRM БлагоДар из 1С (App\Services\Owner\ContractTerminations::year()).
    28.11.2026, владелец: «желательно с возможностью прям подробно показать». Итоги года, график по месяцам, месяцы, замерщики по месяцам,
    причины и базы 1С, все договоры по месяцам с фильтрами (причина, замерщик, база). Только владелец всех бизнесов (?report=terminations).
--}}
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' ₽';
    $plural = function (int $k, string $one, string $few, string $many) {
        $k = abs($k) % 100;
        $k1 = $k % 10;

        return $k > 10 && $k < 20 ? $many : ($k1 > 1 && $k1 < 5 ? $few : ($k1 === 1 ? $one : $many));
    };
    $months = $year['months'];
    $T = $year['totals'];
    $since = $year['since'];
    $partial = $since->gt(\Carbon\Carbon::parse(\App\Services\Owner\ContractTerminations::HISTORY_SINCE));
    $okMonths = collect($months)->where('ok', true);
    $items = collect($year['items']);
    $yearLabel = $since->month === 1 && $since->year === now()->year ? 'за '.$since->year.' год' : 'с '.$since->format('d.m.Y');
    $short = ['янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'окт', 'ноя', 'дек'];
    $monthName = fn ($m) => \Illuminate\Support\Str::ucfirst($m['from']->translatedFormat('F Y'));
    $chrono = array_reverse($months);
    $tmPaidCols = $items->contains(fn ($i) => $i['paid'] !== null || $i['refunded'] !== null);
    $maxAmount = max(1.0, (float) $okMonths->max('amount'));
    $bars = json_encode([
        'labels' => array_map(fn ($m) => $short[$m['from']->month - 1], $chrono),
        'titles' => array_map($monthName, $chrono),
        'stacked' => true,
        'sets' => [
            ['label' => '«Расторжение»', 'data' => array_map(fn ($m) => max(0, (int) $m['contracts'] - $m['refusals']), $chrono), 'color' => '--neg'],
            ['label' => '«Отказ»', 'data' => array_map(fn ($m) => $m['refusals'], $chrono), 'color' => '#f59e0b'],
        ],
    ], JSON_UNESCAPED_UNICODE);
    $who = fn ($name) => $name === '—' ? 'не определён' : $name;
@endphp

@section('title', 'Расторжения договоров')
@section('subtitle')Потолки · расторжения договоров {{ $yearLabel }} · из 1С через CRM БлагоДар@endsection

@section('nav')
    <a href="{{ $back }}#terminations"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg> Сводка</a>
    <a href="#top" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M9.5 12.5l5 5M14.5 12.5l-5 5"/></svg> Расторжения</a>
    <a href="#tm-months"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg> По месяцам</a>
    <a href="#tm-measurers"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c.8-3.5 3.4-5.5 6.5-5.5s5.7 2 6.5 5.5M16 4.5a3.5 3.5 0 010 7M18.5 14.8c1.6.8 2.6 2.6 3 5.2"/></svg> По замерщикам</a>
    <a href="#tm-list"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg> Все договоры</a>
@endsection

@section('content')
<div id="top"></div>
@if($okMonths->isEmpty())
    <div class="card empty-card bad">{{ $year['message'] ?? 'Расторжения договоров считает CRM БлагоДар по 1С; сейчас данные не пришли.' }}</div>
@else
    <div class="card rates-intro">
        <p><b>Расторгнут</b> — договор, у которого в 1С отменены все заказы с причиной «Расторжение» или «Отказ». <b>День расторжения</b> — когда в 1С
            отменили последний заказ договора (по истории изменений заказа). Отменённые целиком по другой причине («Пропала потребность»,
            «Выбрали другую компанию», «Неактуально») в расторжения не входят — они отдельным числом.</p>
        <p class="muted">Считает CRM БлагоДар по обеим рабочим базам 1С@if($year['updated']), 1С на {{ $year['updated']->format($year['updated']->isToday() ? 'H:i' : 'd.m.Y H:i') }}@endif.
            Номер договора открывает его в CRM БлагоДар («Договоры в 1С»).
            @if($year['failed'] > 0) За {{ $n($year['failed']) }} {{ $plural($year['failed'], 'месяц', 'месяца', 'месяцев') }} данные сейчас не пришли — итоги без {{ $year['failed'] === 1 ? 'него' : 'них' }}.@endif</p>
        @foreach($year['stale'] as $warn)
            <p class="muted">{{ $warn }}</p>
        @endforeach
        @if($partial)
            <p class="tm-warn">CRM БлагоДар пока отдаёт расторжения только по договорам с заказами с {{ $since->format('d.m.Y') }} и с примерным днём (≈ — день последнего заказа договора).
                Весь год с настоящими днями отмены появится здесь сам, когда они обновятся.</p>
        @endif
    </div>

    <div class="kpis4 tm-kpis">
        <div class="card mini">
            <div class="lbl">Расторгнуто</div>
            <div class="val num neg">{{ $n($T['contracts']) }}</div>
            <div class="sub">{{ $plural($T['contracts'], 'договор', 'договора', 'договоров') }} {{ $yearLabel }}@if($T['refusals'] > 0) · из них «Отказ» — {{ $n($T['refusals']) }}@endif</div>
        </div>
        <div class="card mini">
            <div class="lbl">На сумму</div>
            <div class="val num">{{ $money($T['amount']) }}</div>
            <div class="sub">сумма договоров до отмены</div>
        </div>
        <div class="card mini">
            <div class="lbl">Обычно через</div>
            @if($T['days'] === null)
                <div class="val muted">—</div>
                <div class="sub">точных дней отмены пока нет</div>
            @else
                <div class="val num">{{ $n($T['days']) }} {{ $plural($T['days'], 'день', 'дня', 'дней') }}</div>
                <div class="sub">после договора — у половины раньше, у половины позже</div>
            @endif
        </div>
        <div class="card mini">
            <div class="lbl">По другой причине</div>
            <div class="val num">{{ $n($T['unmarked']) }}</div>
            <div class="sub">отменены целиком, в расторжения не входят</div>
        </div>
    </div>

    @if($T['contracts'] > 0)
        <div class="card chartcard">
            <div class="sec" style="margin:0 0 4px">
                <h2>Расторжения по месяцам</h2>
                <div class="legend">
                    <span class="lg"><span class="dot2" style="background:var(--neg)"></span>«Расторжение»</span>
                    <span class="lg"><span class="dot2" style="background:#f59e0b"></span>«Отказ»</span>
                </div>
            </div>
            <div class="chart-h"><canvas id="tm-bars" data-bars="{{ $bars }}"></canvas></div>
        </div>
    @endif

    {{-- месяцы: число, причины, сумма (полоса — доля от самого тяжёлого месяца), по другой причине; месяц ведёт к его договорам --}}
    <div class="card tbl-card" id="tm-months">
        <div class="tbl-head"><h2>По месяцам</h2><span class="note">по дню расторжения · нажмите месяц — его договоры ниже</span></div>
        <div class="tbl-wrap"><table class="t">
            <thead><tr><th>Месяц</th><th class="num">Расторгнуто</th><th class="num">«Расторжение»</th><th class="num">«Отказ»</th><th class="num">На сумму</th><th class="tm-barh"></th>
                <th class="num" title="отменены в 1С целиком, но причина не «Расторжение» и не «Отказ»">По другой причине</th></tr></thead>
            <tbody>
            @foreach($months as $m)
                <tr>
                    <td>@if($m['ok'] && count($m['items']) > 0)<a class="tm-link" href="#m-{{ $m['key'] }}">{{ $monthName($m) }}</a>@else{{ $monthName($m) }}@endif</td>
                    @if($m['ok'])
                        <td class="num"><b class="{{ $m['contracts'] > 0 ? 'tm-neg' : 'muted' }}">{{ $n($m['contracts']) }}</b>@if($m['approx']) <span class="muted" title="у части договоров точного дня отмены нет">≈</span>@endif</td>
                        <td class="num">{{ $m['contracts'] - $m['refusals'] > 0 ? $n($m['contracts'] - $m['refusals']) : '—' }}</td>
                        <td class="num">{{ $m['refusals'] > 0 ? $n($m['refusals']) : '—' }}</td>
                        <td class="num">{{ $m['contracts'] > 0 ? $money($m['amount']) : '—' }}</td>
                        <td class="tm-barc">@if($m['contracts'] > 0)<span class="tm-bar" style="width:{{ max(3, round((float) $m['amount'] / $maxAmount * 100)) }}%"></span>@endif</td>
                        <td class="num">{{ $m['unmarked'] > 0 ? $n($m['unmarked']) : '—' }}</td>
                    @else
                        <td class="num muted" colspan="6">данные не пришли</td>
                    @endif
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr class="tm-total">
                <td>Итого {{ $yearLabel }}</td>
                <td class="num"><b class="tm-neg">{{ $n($T['contracts']) }}</b></td>
                <td class="num">{{ $n($T['contracts'] - $T['refusals']) }}</td>
                <td class="num">{{ $n($T['refusals']) }}</td>
                <td class="num">{{ $money($T['amount']) }}</td>
                <td></td>
                <td class="num">{{ $n($T['unmarked']) }}</td>
            </tr></tfoot>
        </table></div>
    </div>

    @if($year['by_measurer'])
        {{-- замерщик × месяц: сколько его договоров расторгли в каждом месяце; имя — фильтр списка договоров --}}
        <div class="card tbl-card" id="tm-measurers">
            <div class="tbl-head"><h2>По замерщикам</h2><span class="note">расторжений по месяцам · нажмите замерщика — ниже только его договоры</span></div>
            <div class="tbl-wrap"><table class="t sortable tm-matrix" data-sorted="{{ count($chrono) + 1 }}">
                <thead><tr><th>Замерщик</th>@foreach($chrono as $m)<th class="num" title="{{ $monthName($m) }}">{{ $short[$m['from']->month - 1] }}</th>@endforeach<th class="num">Всего</th><th class="num">Сумма</th></tr></thead>
                <tbody>
                @foreach($year['by_measurer'] as $r)
                    <tr>
                        <td><a href="#tm-list" class="tm-who" data-who-f="{{ $r['name'] }}">@include('owner._op_ava', ['name' => $who($r['name'])])</a></td>
                        @foreach($chrono as $m)
                            @php $c = $r['months'][$m['key']] ?? 0; @endphp
                            <td class="num {{ $c > 0 ? 'tm-hit' : 'muted' }}" data-v="{{ $c }}">{{ $c > 0 ? $c : '·' }}</td>
                        @endforeach
                        <td class="num"><b>{{ $n($r['contracts']) }}</b></td>
                        <td class="num">{{ $money($r['amount']) }}</td>
                    </tr>
                @endforeach
                <tr class="grp">
                    <td><b>Итого</b></td>
                    @foreach($chrono as $m)<td class="num">{{ $m['ok'] && $m['contracts'] > 0 ? $n($m['contracts']) : '·' }}</td>@endforeach
                    <td class="num"><b>{{ $n($T['contracts']) }}</b></td>
                    <td class="num"><b>{{ $money($T['amount']) }}</b></td>
                </tr>
                </tbody>
            </table></div>
        </div>

        <div class="tm-split">
            <div class="card tbl-card">
                <div class="tbl-head"><h2>По причине в 1С</h2></div>
                <div class="tbl-wrap"><table class="t">
                    <thead><tr><th>Причина</th><th class="num">Договоров</th><th class="num">Сумма</th><th class="num">Доля</th></tr></thead>
                    <tbody>
                    @foreach($year['by_kind'] as $r)
                        <tr><td><span class="tag {{ $r['name'] === 'Отказ' ? 'amber' : 'red' }}">{{ $r['name'] }}</span></td><td class="num"><b>{{ $n($r['contracts']) }}</b></td>
                            <td class="num">{{ $money($r['amount']) }}</td><td class="num">{{ $T['amount'] > 0 ? number_format($r['amount'] / $T['amount'] * 100, 0, ',', ' ').'%' : '—' }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
            <div class="card tbl-card">
                <div class="tbl-head"><h2>По базам 1С</h2></div>
                <div class="tbl-wrap"><table class="t">
                    <thead><tr><th>База</th><th class="num">Договоров</th><th class="num">Сумма</th></tr></thead>
                    <tbody>
                    @foreach($year['by_base'] as $r)
                        <tr><td>{{ $r['name'] }}</td><td class="num"><b>{{ $n($r['contracts']) }}</b></td><td class="num">{{ $money($r['amount']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        </div>
    @endif

    {{-- все договоры по месяцам; фильтры — причина, замерщик, база (итоги месяцев пересчитываются) --}}
    <div class="card tbl-card" id="tm-list">
        <div class="tbl-head"><h2>Все договоры</h2><span class="note" id="tmShown">{{ $n($T['contracts']) }} {{ $plural($T['contracts'], 'договор', 'договора', 'договоров') }} на {{ $money($T['amount']) }}</span></div>
        @if($items->isEmpty())
            <div class="empty-card" style="margin:0">Расторжений {{ $yearLabel }} нет.</div>
        @else
            <div class="tm-filters">
                <div class="seg" role="group" aria-label="Причина">
                    <button type="button" class="active" data-kind-f="">Все</button>
                    <button type="button" data-kind-f="Расторжение">«Расторжение»</button>
                    <button type="button" data-kind-f="Отказ">«Отказ»</button>
                </div>
                <select id="tmWho" aria-label="Замерщик">
                    <option value="">Все замерщики</option>
                    @foreach($year['by_measurer'] as $r)<option value="{{ $r['name'] }}">{{ $who($r['name']) }} · {{ $r['contracts'] }}</option>@endforeach
                </select>
                @if(count($year['by_base']) > 1)
                    <select id="tmBase" aria-label="База 1С">
                        <option value="">Все базы 1С</option>
                        @foreach($year['by_base'] as $r)<option value="{{ $r['name'] }}">{{ $r['name'] }} · {{ $r['contracts'] }}</option>@endforeach
                    </select>
                @endif
                <button type="button" class="tm-reset" id="tmReset" hidden>Сбросить</button>
            </div>
            <div class="tbl-wrap"><table class="t tm-list">
                <thead><tr><th>Договор</th><th>Договор от</th><th>Расторгнут</th><th class="num" title="сколько дней прошло от договора до расторжения">Через</th><th class="num">Сумма</th>
                    @if($tmPaidCols)<th class="num">Заплатили</th><th class="num">Вернули</th>@endif<th>Замерщик</th><th>Причина в 1С</th></tr></thead>
                <tbody>
                @foreach($months as $m)
                    @continue(! $m['ok'] || count($m['items']) === 0)
                    <tr class="tm-mhead" id="m-{{ $m['key'] }}" data-month-head="{{ $m['key'] }}"><td colspan="{{ $tmPaidCols ? 9 : 7 }}">
                        <b>{{ $monthName($m) }}</b> <span class="muted" data-mh-sum>{{ $n($m['contracts']) }} {{ $plural($m['contracts'], 'договор', 'договора', 'договоров') }} · {{ $money($m['amount']) }}</span></td></tr>
                    @foreach($m['items'] as $i)
                        @include('owner._tm_contract', ['i' => $i + ['month' => $m['key']]])
                    @endforeach
                @endforeach
                <tr class="tm-none" hidden><td colspan="{{ $tmPaidCols ? 9 : 7 }}" class="muted">Таких договоров нет.</td></tr>
                </tbody>
            </table></div>
            @if($items->contains(fn ($i) => $i['terminated_at'] && ! $i['date_known']))
                <p class="foot-note tm-foot">≈ — точного дня отмены в 1С нет: стоит день последнего заказа договора.</p>
            @endif
        @endif
    </div>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var box = document.getElementById('tm-list');
    if (!box || !box.querySelector('tr[data-kind]')) return;
    var kind = '', who = document.getElementById('tmWho'), base = document.getElementById('tmBase'), reset = document.getElementById('tmReset');
    var rows = [].slice.call(box.querySelectorAll('tbody tr[data-kind]'));
    var heads = [].slice.call(box.querySelectorAll('tbody tr[data-month-head]'));
    var fmt = function (v) { return Math.round(v).toLocaleString('ru-RU') + ' ₽'; };
    var word = function (k) { var d = Math.abs(k) % 100, d1 = d % 10; return d > 10 && d < 20 ? 'договоров' : (d1 > 1 && d1 < 5 ? 'договора' : (d1 === 1 ? 'договор' : 'договоров')); };
    function apply() {
        var per = {}, total = 0, sum = 0, w = who ? who.value : '', b = base ? base.value : '';
        rows.forEach(function (tr) {
            var on = (!kind || tr.dataset.kind === kind) && (!w || tr.dataset.measurer === w) && (!b || tr.dataset.base === b);
            tr.hidden = !on;
            if (!on) return;
            var m = per[tr.dataset.month] = per[tr.dataset.month] || { n: 0, s: 0 };
            m.n++; m.s += +tr.dataset.amount; total++; sum += +tr.dataset.amount;
        });
        heads.forEach(function (h) {
            var p = per[h.dataset.monthHead];
            h.hidden = !p;
            if (p) h.querySelector('[data-mh-sum]').textContent = p.n + ' ' + word(p.n) + ' · ' + fmt(p.s);
        });
        box.querySelector('tr.tm-none').hidden = total > 0;
        document.getElementById('tmShown').textContent = (kind || w || b ? 'показано ' : '') + total + ' ' + word(total) + ' на ' + fmt(sum);
        reset.hidden = !(kind || w || b);
    }
    function setKind(k) {
        kind = k;
        box.querySelectorAll('[data-kind-f]').forEach(function (x) { x.classList.toggle('active', x.dataset.kindF === k); });
    }
    box.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-kind-f]');
        if (btn) { setKind(btn.dataset.kindF); apply(); }
    });
    [who, base].forEach(function (s) { if (s) s.addEventListener('change', apply); });
    reset.addEventListener('click', function () { setKind(''); if (who) who.value = ''; if (base) base.value = ''; apply(); });
    // замерщик в таблице «По замерщикам» — список только его договоров
    document.addEventListener('click', function (e) {
        var a = e.target.closest('[data-who-f]');
        if (!a || !who) return;
        e.preventDefault();
        who.value = a.dataset.whoF;
        apply();
        box.scrollIntoView({ behavior: 'smooth', block: 'start' });
    });
})();
</script>
@endpush
