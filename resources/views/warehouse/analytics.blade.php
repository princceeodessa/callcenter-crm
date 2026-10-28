@extends('layouts.sneakers')
{{--
    «Аналитика склада» — каркас «Нордика» (прототип: PAGES.analytics, снимок 10): KPI, сезонность за 12 месяцев, тепловая карта
    «день × час», размерная сетка склада, бренды, «Быстро уходят», «Без продаж». Ниже — прежние разделы страницы в том же
    оформлении (скоро закончатся, прогноз, закупки и продажи, клиенты, финансы, структура, ABC), чтобы ничего не пропало.
    Расчёты — WarehouseAnalyticsController; страница только руководителю.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $sgnRub = fn ($v) => ($v < 0 ? '−' : '').number_format(abs((float) $v), 0, ',', ' ').' ₽';
    $k = fn ($v) => $v >= 1000 ? number_format($v / 1000, 0, ',', ' ').'к' : number_format($v, 0, ',', ' ');
    $monGen = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
    $monTo = ['январю', 'февралю', 'марту', 'апрелю', 'маю', 'июню', 'июлю', 'августу', 'сентябрю', 'октябрю', 'ноябрю', 'декабрю'];
    $now = now();
    $prevM = $now->copy()->subMonthNoOverflow();
    $dlt = fn (int $d) => $d === 0 ? '±0%' : '<span class="'.($d > 0 ? 'ns-up' : 'ns-dn').'">'.($d > 0 ? '▲ ' : '▼ ').abs($d).'%</span>';
    $perDay = $cur['units'] / max(1, $now->day);
    $seasMaxRev = max(1, (float) $seasonality->max('revenue'));
    // тепловая карта: рабочие часы 10–21, шире — если продажи были раньше / позже
    $hoursWith = [];
    foreach ($heatmap as $row) { foreach ($row as $h => $v) { if ($v > 0) { $hoursWith[] = $h; } } }
    $hFrom = min(10, $hoursWith ? min($hoursWith) : 10);
    $hTo = max(21, $hoursWith ? max($hoursWith) : 21);
    $hours = range($hFrom, $hTo);
    // пик: день недели и часы с наибольшими продажами
    $peak = null;
    foreach ($heatmap as $d => $row) { foreach ($row as $h => $v) { if ($v > 0 && ($peak === null || $v > $peak[2])) { $peak = [$d, $h, $v]; } } }
    $sizesShown = array_values(array_filter($sizesData, fn ($s) => $s['stock'] > 0 || $s['sold'] > 0));
    $sizeStockMax = max(1, max(array_column($sizesShown, 'stock') ?: [0]));
    $brandMax = max(1, max(array_column($brandBars, 'pairs') ?: [0]));
@endphp

@section('title', 'Аналитика склада')
@section('ns_sub', 'KPI · сезонность · оборачиваемость')
@section('ns_actions')
    <a class="ns-btn" href="{{ route('warehouse.analytics.export') }}" title="Сводка в CSV">{!! $ui::icon('down', 'ns-sm') !!}CSV</a>
    <button class="ns-btn ns-icon-only" type="button" onclick="window.print()" title="Печать"><svg class="ns-icon" viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg></button>
@endsection

@section('content')
<div class="ns-kpis ns-k6">
    <div class="ns-kpi"><div class="ns-kl">Выручка за {{ $monGen[$now->month - 1] }}</div><div class="ns-kv">{{ $rub($cur['revenue']) }}</div><div class="ns-kd">{!! $dlt($delta['revenue']) !!} к {{ $monTo[$prevM->month - 1] }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Прибыль · маржа {{ round($cur['margin']) }}%</div><div @class(['ns-kv', 'ns-up' => $cur['profit'] >= 0, 'ns-dn' => $cur['profit'] < 0])>{{ $sgnRub($cur['profit']) }}</div><div class="ns-kd">{!! $dlt($delta['profit']) !!} к {{ $monTo[$prevM->month - 1] }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Продано пар</div><div class="ns-kv">{{ $cur['units'] }}</div><div class="ns-kd">≈ {{ number_format($perDay, 1, ',', '') }} пары в день</div></div>
    <div class="ns-kpi"><div class="ns-kl">Средний чек</div><div class="ns-kv">{{ $cur['count'] ? $rub($cur['avg']) : '—' }}</div><div class="ns-kd">{!! $dlt($delta['avg']) !!} · сделок {{ $cur['count'] }}</div></div>
    <div class="ns-kpi ns-warn"><div class="ns-kl">Склада хватит на</div><div class="ns-kv">{{ $monthsOfStock !== null ? '≈ '.number_format($monthsOfStock, $monthsOfStock < 10 ? 1 : 0, ',', ' ').' мес.' : '—' }}</div><div class="ns-kd">при темпе продаж последних 90 дней</div></div>
    <div class="ns-kpi"><div class="ns-kl">Мёртвый груз</div><div @class(['ns-kv', 'ns-dn' => $deadPairs > 0])>{{ number_format($deadPairs, 0, ',', ' ') }} пар</div><div class="ns-kd">{{ $deadModels }} моделей без продаж 90 дней</div></div>
</div>

<div class="ns-grid2">
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">Сезонность продаж <span class="ns-sm">· 12 месяцев, выручка</span></div>
        <div class="ns-chart">
            @foreach($seasonality as $m)
                <div @class(['ns-bar', 'ns-soft' => $m['revenue'] <= 0]) style="height:{{ $m['revenue'] > 0 ? number_format(max(4, $m['revenue'] / $seasMaxRev * 100), 1, '.', '') : 1.5 }}%" title="{{ $m['label'] }}: {{ $rub($m['revenue']) }} · {{ $m['units'] }} пар · прибыль {{ $sgnRub($m['profit']) }}">@if($m['revenue'] > 0)<span>{{ $k($m['revenue']) }}</span>@endif<small>{{ $m['label'] }}</small></div>
            @endforeach
        </div>
        <div class="ns-chart-pad" style="height:26px"></div>
    </div>
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">Тепловая карта продаж <span class="ns-sm">· день × час</span></div>
        <div class="ns-heat" style="grid-template-columns:34px repeat({{ count($hours) }},minmax(0,1fr))">
            <span></span>
            @foreach($hours as $h)<span class="ns-hh">{{ $h }}</span>@endforeach
            @foreach($dayNames as $d => $dn)
                <span class="ns-hl">{{ mb_strtolower($dn) }}</span>
                @foreach($hours as $h)
                    @php $v = $heatmap[$d][$h] ?? 0; @endphp
                    <span class="ns-hc" title="{{ $dn }} {{ $h }}:00 — {{ $v }} прод." @if($v) style="background:color-mix(in srgb,var(--accent) {{ round(25 + $v / max(1, $heatmapMax) * 75) }}%,var(--pill))" @endif></span>
                @endforeach
            @endforeach
        </div>
        <div class="ns-note" style="margin-top:12px">@if($peak)Чаще всего покупают: {{ mb_strtolower($dayNames[$peak[0]]) }}, {{ $peak[1] }}:00–{{ $peak[1] + 1 }}:00 ({{ $peak[2] }} прод. за 12 месяцев). В это время держите на кассе продавца.@else Продаж за 12 месяцев нет.@endif</div>
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-panel ns-panel-pad">
    <div class="ns-sec-h">Размерная сетка склада <span class="ns-sm">· пар в наличии · зелёным — размеры, которые покупали</span></div>
    <div class="ns-sizebars">
        @foreach($sizesShown as $s)
            <div @class(['ns-sb', 'ns-hot' => $s['sold'] > 0]) title="р. {{ $s['size'] }}: на складе {{ $s['stock'] }}, продано за 12 мес. {{ $s['sold'] }}"><em>{{ $s['stock'] }}</em><i style="height:{{ number_format($s['stock'] / $sizeStockMax * 100, 1, '.', '') }}%"></i><span>{{ $s['size'] }}</span></div>
        @endforeach
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-grid3">
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">Бренды на складе</div>
        <div class="ns-hlist">
            @forelse(array_slice($brandBars, 0, 8) as $b)
                <div class="ns-hr"><div class="ns-grow"><b>{{ $b['brand'] }}</b><div class="ns-meter"><i style="width:{{ number_format($b['pairs'] / $brandMax * 100, 1, '.', '') }}%"></i></div></div><span class="ns-muted">{{ $b['models'] }} мод.</span><b>{{ $b['pairs'] }} пар</b></div>
            @empty
                <div class="ns-empty ns-sm"><b>Склад пуст</b></div>
            @endforelse
        </div>
    </div>
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">🚀 Быстро уходят <span class="ns-sm">· 90 дней</span></div>
        <div class="ns-hlist">
            @forelse($fastMovers as $t)
                <div class="ns-hr"><div class="ns-pimg ns-xs">@if($t['img'])<img src="{{ $t['img'] }}" alt="" loading="lazy" data-b="{{ $ui::brandOf($t['name']) }}">@else<span class="ns-phb">{{ mb_strtoupper(mb_substr($ui::brandOf($t['name']), 0, 2)) }}</span>@endif</div>
                    <div class="ns-grow"><b>{{ $t['name'] }}</b><small>на складе {{ $t['stock'] }} · хватит на {{ $t['days_left'] === null ? '∞' : max(0, $t['days_left']) }} дн.</small></div><b class="ns-up">{{ $t['sold_90d'] }} пар</b></div>
            @empty
                <div class="ns-empty ns-sm"><b>За 90 дней продаж не было</b></div>
            @endforelse
        </div>
    </div>
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">💤 Без продаж <span class="ns-sm">· 90 дней</span></div>
        <div class="ns-hlist">
            @forelse($slowMovers as $t)
                <div class="ns-hr"><div class="ns-pimg ns-xs">@if($t['img'])<img src="{{ $t['img'] }}" alt="" loading="lazy" data-b="{{ $ui::brandOf($t['name']) }}">@else<span class="ns-phb">{{ mb_strtoupper(mb_substr($ui::brandOf($t['name']), 0, 2)) }}</span>@endif</div>
                    <div class="ns-grow"><b>{{ $t['name'] }}</b><small>{{ $rub($t['cost']) }} по закупке</small></div><b>{{ $t['stock'] }} пар</b></div>
            @empty
                <div class="ns-empty ns-sm"><b>Всё в движении — залежей нет</b></div>
            @endforelse
        </div>
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-grid2">
    <div class="ns-panel" style="overflow:hidden">
        <div class="ns-panel-pad" style="padding-bottom:4px"><div class="ns-sec-h">⏳ Скоро закончатся <span class="ns-sm">· при текущей скорости — за 21 день</span></div></div>
        @if(empty($endingSoon))
            <div class="ns-empty ns-sm"><b>На ближайшие 3 недели дефицита не ожидается</b></div>
        @else
            <table class="ns-tbl">
                <thead><tr><th>Модель</th><th class="ns-num">Остаток</th><th class="ns-num">Пар в день</th><th class="ns-num">Осталось</th></tr></thead>
                <tbody>
                @foreach($endingSoon as $t)
                    <tr><td class="ns-tname">{{ $t['name'] }}</td><td class="ns-num">{{ $t['stock'] }}</td><td class="ns-num">{{ number_format($t['per_day'], 2, ',', '') }}</td><td class="ns-num ns-dn"><b>≈ {{ $t['days_left'] }} дн.</b></td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="ns-panel ns-panel-pad ns-an-fc">
        <div class="ns-sec-h">🔮 Прогноз выручки на следующий месяц</div>
        <div class="ns-an-big">{{ $rub($forecastNextMonth) }}</div>
        <div class="ns-note">по среднему за последние 3 месяца ({{ $rub($trendAvg) }} в месяц) с сезонным коэффициентом ×{{ number_format($seasCoef, 2, ',', '') }}</div>
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-panel ns-panel-pad">
    <div class="ns-sec-h">Закупки и продажи <span class="ns-sm">· пар по месяцам: <span class="ns-lg-d" style="background:var(--blue)"></span>закуплено <span class="ns-lg-d" style="background:var(--accent)"></span>продано</span></div>
    <div class="ns-chart ns-flow">
        @foreach($flow as $m)
            <div class="ns-bwrap" title="{{ $m['label'] }}: закуплено {{ $m['purchased'] }}, продано {{ $m['sold'] }}">
                <div class="ns-bar ns-blue" style="height:{{ $m['purchased'] ? number_format(max(3, $m['purchased'] / $flowMax * 100), 1, '.', '') : 1.5 }}%">@if($m['purchased'])<span>{{ $m['purchased'] }}</span>@endif</div>
                <div class="ns-bar" style="height:{{ $m['sold'] ? number_format(max(3, $m['sold'] / $flowMax * 100), 1, '.', '') : 1.5 }}%">@if($m['sold'])<span>{{ $m['sold'] }}</span>@endif</div>
                <small>{{ $m['label'] }}</small>
            </div>
        @endforeach
    </div>
    <div class="ns-chart-pad" style="height:26px"></div>
</div>
<div class="ns-gap"></div>

<div class="ns-sec-h">👥 Клиенты <span class="ns-sm">· за 12 месяцев</span></div>
<div class="ns-kpis">
    <div class="ns-kpi"><div class="ns-kl">Всего клиентов</div><div class="ns-kv">{{ $totalClients }}</div><div class="ns-kd">уникальных</div></div>
    <div class="ns-kpi"><div class="ns-kl">Повторные</div><div class="ns-kv">{{ $repeatClients }}</div><div class="ns-kd">{{ $totalClients ? round($repeatClients / $totalClients * 100) : 0 }}% купили снова</div></div>
    <div class="ns-kpi"><div class="ns-kl">Заказов на клиента</div><div class="ns-kv">{{ number_format($avgOrdersPerClient, 1, ',', '') }}</div><div class="ns-kd">в среднем</div></div>
    <div class="ns-kpi"><div class="ns-kl">Выручка на клиента</div><div class="ns-kv">{{ $rub($avgClientRevenue) }}</div><div class="ns-kd">в среднем</div></div>
</div>
<div class="ns-panel" style="overflow:hidden">
    <div class="ns-panel-pad" style="padding-bottom:4px"><div class="ns-sec-h">🏆 Топ покупателей</div></div>
    @if(empty($topClients))
        <div class="ns-empty ns-sm"><b>Данных пока нет</b></div>
    @else
        <div style="overflow-x:auto"><table class="ns-tbl">
            <thead><tr><th>#</th><th>Клиент</th><th>Телефон</th><th class="ns-num">Заказов</th><th class="ns-num">Пар</th><th class="ns-num">Выручка</th><th>Последняя покупка</th></tr></thead>
            <tbody>
            @foreach($topClients as $i => $c)
                <tr><td class="ns-muted">{{ $i + 1 }}</td><td class="ns-tname">{{ $c['name'] ?: '—' }} @if($c['orders'] >= 2)<span class="ns-tag ns-ok">повторный</span>@endif</td><td class="ns-muted">{{ $c['phone'] }}</td>
                    <td class="ns-num">{{ $c['orders'] }}</td><td class="ns-num">{{ $c['units'] }}</td><td class="ns-num"><b>{{ $rub($c['revenue']) }}</b></td><td class="ns-muted">{{ $c['last_at'] ? $c['last_at']->format('d.m.Y') : '—' }}</td></tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
<div class="ns-gap"></div>

<div class="ns-sec-h">💰 Финансы</div>
<div class="ns-kpis">
    <div class="ns-kpi"><div class="ns-kl">Оборотный капитал</div><div class="ns-kv">{{ $rub($workingCapital) }}</div><div class="ns-kd">заморожено в товаре по закупке</div></div>
    <div class="ns-kpi"><div class="ns-kl">Средняя наценка</div><div class="ns-kv">{{ round($avgMarkup) }}%</div><div class="ns-kd">по позициям с ценой</div></div>
    <div class="ns-kpi"><div class="ns-kl">Распродано</div><div class="ns-kv">{{ round($sellThrough) }}%</div><div class="ns-kd">продано ÷ (продано + остаток), 12 мес.</div></div>
    <div class="ns-kpi"><div class="ns-kl">Дней оборота склада</div><div class="ns-kv">{{ $dio !== null ? $dio.' дн.' : '—' }}</div><div class="ns-kd">остаток ÷ средние продажи в день</div></div>
</div>
<div class="ns-grid2">
    <div class="ns-panel" style="overflow:hidden">
        <div class="ns-panel-pad" style="padding-bottom:4px"><div class="ns-sec-h">Маржа по категориям <span class="ns-sm">· 12 мес.</span></div></div>
        @if(empty($marginByCategory))
            <div class="ns-empty ns-sm"><b>Продаж по категориям пока нет</b>Категории размечаются на складе</div>
        @else
            <table class="ns-tbl">
                <thead><tr><th>Категория</th><th class="ns-num">Пар</th><th class="ns-num">Выручка</th><th class="ns-num">Прибыль</th><th class="ns-num">Маржа</th></tr></thead>
                <tbody>
                @foreach($marginByCategory as $c)
                    <tr><td class="ns-tname">{{ preg_replace('/^[^\p{L}]+/u', '', $c['label']) }}</td><td class="ns-num">{{ $c['units'] }}</td><td class="ns-num">{{ $rub($c['revenue']) }}</td><td @class(['ns-num', 'ns-up' => $c['profit'] >= 0, 'ns-dn' => $c['profit'] < 0])>{{ $sgnRub($c['profit']) }}</td><td class="ns-num"><b>{{ round($c['margin']) }}%</b></td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">🧩 Структура склада <span class="ns-sm">· пар на складе / продано за 12 мес.</span></div>
        <div class="ns-an-tax">
            @foreach(['category' => 'Категории', 'gender' => 'Пол', 'season' => 'Сезон'] as $tax => $title)
                <div>
                    <div class="ns-an-tax-h">{{ $title }}</div>
                    @foreach($taxonomy[$tax]['options'] as $key => $label)
                        <div class="ns-an-tax-r"><span>{{ preg_replace('/^[^\p{L}]+/u', '', $label) }}</span><b>{{ $taxonomy[$tax]['stock'][$key] ?? 0 }} / {{ $taxonomy[$tax]['sold'][$key] ?? 0 }}</b></div>
                    @endforeach
                    @if($taxonomy[$tax]['unset'] > 0)<div class="ns-an-tax-r ns-muted"><span>не указано</span><b>{{ $taxonomy[$tax]['unset'] }}</b></div>@endif
                </div>
            @endforeach
        </div>
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-panel ns-panel-pad">
    <div class="ns-sec-h">🎯 ABC-анализ моделей <span class="ns-sm">· по прибыли за 12 месяцев: A — 80% прибыли, B — ещё 15%, C — остальное</span></div>
    <div class="ns-grid3 ns-an-abc">
        @foreach(['A' => 'ns-ok', 'B' => 'ns-warn', 'C' => ''] as $klass => $cls)
            <div>
                <div class="ns-an-abc-h"><span class="ns-tag {{ $cls }}">{{ $klass }}</span><span class="ns-muted">{{ count($abc[$klass]) }} моделей</span></div>
                <div class="ns-hlist">
                    @forelse(array_slice($abc[$klass], 0, 12) as $m)
                        <div class="ns-hr"><div class="ns-grow"><b>{{ $m['name'] }}</b></div><span class="ns-muted">{{ round($m['share'] * 100, 1) }}%</span><b>{{ $sgnRub($m['profit']) }}</b></div>
                    @empty
                        <div class="ns-muted" style="font-size:12.5px;padding:6px 0">—</div>
                    @endforelse
                    @if(count($abc[$klass]) > 12)<div class="ns-muted" style="font-size:12px;padding-top:6px">и ещё {{ count($abc[$klass]) - 12 }}</div>@endif
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection

@push('scripts')
<script>
document.querySelectorAll('.ns-pimg img').forEach((img) => img.addEventListener('error', () => { img.parentNode.innerHTML = '<span class="ns-phb">' + ns.esc((img.dataset.b || '?').slice(0, 2).toUpperCase()) + '</span>'; }, { once: true }));
</script>
@endpush
