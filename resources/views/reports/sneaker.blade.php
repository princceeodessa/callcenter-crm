@extends('layouts.sneakers')
{{--
    «Отчёт» кроссовочного отдела — каркас «Нордика» (прототип: PAGES.report, снимок 14). Месяц, KPI со сравнением к прошлому месяцу,
    деньги и склад, динамика 6 месяцев, воронка, топ моделей, сотрудники, источники, возвраты, «Дозаказать», залежавшийся товар,
    продажи с прибылью по каждому заказу и CSV. Расчёты — SneakerReportController; страница только руководителю.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $sgnRub = fn ($v) => ($v < 0 ? '−' : '').number_format(abs((float) $v), 0, ',', ' ').' ₽';
    $dlt = fn (int $d) => $d === 0 ? '<span>±0% к пр. мес.</span>' : '<span class="'.($d > 0 ? 'ns-up' : 'ns-dn').'">'.($d > 0 ? '▲' : '▼').abs($d).'%</span> к пр. мес.';
    $mon = ['январь', 'февраль', 'март', 'апрель', 'май', 'июнь', 'июль', 'август', 'сентябрь', 'октябрь', 'ноябрь', 'декабрь'];
    $months = collect(range(0, 17))->map(fn ($k) => now()->startOfMonth()->subMonthsNoOverflow($k))
        ->map(fn ($m) => ['v' => $m->format('Y-m'), 'l' => mb_convert_case($mon[$m->month - 1], MB_CASE_TITLE).' '.$m->year]);
    $monthTitle = \Carbon\Carbon::createFromFormat('Y-m', $monthValue)->startOfMonth();
    $monGen = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $funnelColors = ['var(--blue)', '#16b3c4', 'var(--amber)', '#8b5cf6', 'var(--accent)', 'var(--red)'];
    $dynMaxK = max(1, (float) $dynamics->max('revenue'));
@endphp

@section('title', 'Отчёт')
@section('ns_sub', 'Кроссовки')
@section('ns_no_search', '1')
@section('ns_no_sell', '1')
@section('ns_actions')
    <form method="GET" action="{{ route('sneaker.report') }}" class="ns-rep-form">
        <select name="month" class="ns-select" onchange="this.form.submit()" aria-label="Месяц">
            @foreach($months as $m)
                <option value="{{ $m['v'] }}" @selected($m['v'] === $monthValue)>{{ $m['l'] }}</option>
            @endforeach
        </select>
        <button class="ns-btn" type="submit">Показать</button>
    </form>
    <a class="ns-btn" href="{{ route('sneaker.report.export', ['month' => $monthValue]) }}">{!! $ui::icon('down', 'ns-sm') !!}CSV</a>
@endsection

@section('content')
<div class="ns-kpis">
    <div class="ns-kpi"><div class="ns-kl">Выручка</div><div class="ns-kv">{{ $rub($cur['revenue']) }}</div><div class="ns-kd">{!! $dlt($delta['revenue']) !!}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Прибыль · маржа {{ round($cur['margin']) }}%</div><div @class(['ns-kv', 'ns-up' => $cur['profit'] >= 0, 'ns-dn' => $cur['profit'] < 0])>{{ $sgnRub($cur['profit']) }}</div><div class="ns-kd">{!! $dlt($delta['profit']) !!}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Продано пар</div><div class="ns-kv">{{ $cur['units'] }}</div><div class="ns-kd">сделок {{ $cur['count'] }} · {!! $dlt($delta['units']) !!}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Средний чек</div><div class="ns-kv">{{ $cur['count'] ? $rub($cur['avg']) : '—' }}</div><div class="ns-kd">{!! $dlt($delta['avg']) !!}</div></div>
</div>
<div class="ns-kpis">
    <div class="ns-kpi"><div class="ns-kl">Конверсия в продажу</div><div class="ns-kv">{{ $conversion !== null ? $conversion.'%' : '—' }}</div><div class="ns-kd">продаж {{ $wonCount }} · отказов {{ $lostCount }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Закуплено за период</div><div class="ns-kv">{{ $rub($purchaseSpend) }}</div><div class="ns-kd">открытых закупок: {{ $openPurchases }}@if($whiteCount) · в белую {{ $rub($whiteSpend) }} ({{ $whiteCount }})@endif</div></div>
    <div class="ns-kpi"><div class="ns-kl">Склад по себестоимости</div><div class="ns-kv">{{ $rub($stockCostValue) }}</div><div class="ns-kd">{{ number_format($stockUnits, 0, ',', ' ') }} пар@if($reservedUnits) · резерв {{ $reservedUnits }}@endif</div></div>
    <div class="ns-kpi"><div class="ns-kl">Склад в ценах продажи</div><div class="ns-kv">{{ $rub($stockSaleValue) }}</div><div class="ns-kd">потенц. выручка склада</div></div>
</div>

<div class="ns-grid2 ns-rep-row">
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">Динамика за 6 месяцев <span class="ns-sm">· выручка</span></div>
        <div class="ns-chart" style="height:190px">
            @foreach($dynamics as $i => $d)
                @php
                    $h = $d['revenue'] > 0 ? max(4, $d['revenue'] / $dynMaxK * 100) : 1.5;
                @endphp
                <div @class(['ns-bar', 'ns-soft' => $d['revenue'] <= 0]) style="height:{{ number_format($h, 1, '.', '') }}%" title="{{ $d['label'] }}: выручка {{ $rub($d['revenue']) }}, прибыль {{ $sgnRub($d['profit']) }}">@if($d['revenue'] > 0)<span>{{ number_format($d['revenue'] / 1000, 0, ',', ' ') }}к</span>@endif<small>{{ $d['label'] }}</small></div>
            @endforeach
        </div>
        <div class="ns-chart-pad" style="height:26px"></div>
    </div>
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">Воронка продаж <span class="ns-sm">· в работе</span></div>
        @if($funnel->isEmpty())
            <div class="ns-empty ns-sm"><b>Воронка не настроена</b></div>
        @else
            <div class="ns-hlist">
                @foreach($funnel as $i => $f)
                    <div class="ns-hr"><span class="ns-fdot" style="background:{{ $funnelColors[$i % count($funnelColors)] }}"></span><div class="ns-grow"><b>{{ $f['name'] }}</b></div><b>{{ $f['count'] }} шт@if($f['sum'] > 0) · {{ $rub($f['sum']) }}@endif</b></div>
                @endforeach
            </div>
            @if($pipelineCount === 0)
                <div class="ns-note" style="margin-top:8px">Все продажи {{ $monGen[$monthTitle->month - 1] }} прошли через быструю продажу — воронка сейчас пустая.</div>
            @else
                <div class="ns-note" style="margin-top:8px">В работе {{ $pipelineCount }} сделок на {{ $rub($pipelineValue) }}.</div>
            @endif
        @endif
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-grid3 ns-rep-3">
    <div class="ns-panel" style="overflow:hidden">
        <div class="ns-panel-pad" style="padding-bottom:4px"><div class="ns-sec-h">Топ моделей</div></div>
        @if($topModels->isEmpty())
            <div class="ns-empty ns-sm"><b>Продаж за период нет</b></div>
        @else
            <table class="ns-tbl"><tbody>
                @foreach($topModels as $t)
                    <tr title="выручка {{ $rub($t['revenue']) }}"><td class="ns-tname">{{ $t['name'] }}</td><td class="ns-num">{{ $t['units'] }}</td><td @class(['ns-num', 'ns-up' => $t['profit'] >= 0, 'ns-dn' => $t['profit'] < 0])><b>{{ $sgnRub($t['profit']) }}</b></td></tr>
                @endforeach
            </tbody></table>
        @endif
    </div>
    <div class="ns-panel" style="overflow:hidden">
        <div class="ns-panel-pad" style="padding-bottom:4px"><div class="ns-sec-h">По сотрудникам</div></div>
        @if($byOperator->isEmpty())
            <div class="ns-empty ns-sm"><b>Продаж за период нет</b></div>
        @else
            <table class="ns-tbl">
                <thead><tr><th>Сотрудник</th><th class="ns-num">Пар</th><th class="ns-num">Прибыль</th></tr></thead>
                <tbody>
                @foreach($byOperator as $o)
                    <tr title="сделок {{ $o['count'] }} · выручка {{ $rub($o['revenue']) }}"><td class="ns-tname">{{ $o['name'] }}</td><td class="ns-num">{{ $o['units'] }}</td><td @class(['ns-num', 'ns-up' => $o['profit'] >= 0, 'ns-dn' => $o['profit'] < 0])><b>{{ $sgnRub($o['profit']) }}</b></td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="ns-panel" style="overflow:hidden">
        <div class="ns-panel-pad" style="padding-bottom:4px"><div class="ns-sec-h">По источникам</div></div>
        @if($bySource->isEmpty())
            <div class="ns-empty ns-sm"><b>Нет данных за период</b></div>
        @else
            <table class="ns-tbl"><tbody>
                @foreach($bySource as $s)
                    <tr><td>@if($s['name'] === 'Не указан')<span class="ns-tag ns-warn">не указан</span>@else{!! $ui::srcChip($s['name']) !!}@endif</td><td class="ns-num">{{ $s['count'] }}</td><td class="ns-num"><b>{{ $rub($s['revenue']) }}</b></td></tr>
                @endforeach
            </tbody></table>
        @endif
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-grid3 ns-rep-3">
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h"><svg class="ns-icon ns-sm" viewBox="0 0 24 24"><path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/></svg>Возвраты за период</div>
        <div class="ns-rep-big @if($returnsCount) ns-dn @endif">{{ $returnsCount }}</div>
        <div class="ns-note" style="margin-top:2px">оформленных возвратов товара на склад</div>
    </div>
    <div class="ns-panel" style="overflow:hidden">
        <div class="ns-panel-pad" style="padding-bottom:4px"><div class="ns-sec-h">Дозаказать <span class="ns-sm">· низкий остаток</span></div></div>
        @if($reorder->isEmpty())
            <div class="ns-empty ns-sm">{!! $ui::icon('check') !!}<b>Всё в норме</b>Мин. остаток задаётся в окне размера на складе. Подсказки по скорости продаж — <a href="{{ route('warehouse.reorder') }}">«Что заказать»</a></div>
        @else
            <table class="ns-tbl">
                <thead><tr><th>Позиция</th><th class="ns-num">Доступно</th><th class="ns-num">Мин.</th><th class="ns-num">Заказать ≈</th></tr></thead>
                <tbody>
                @foreach($reorder as $r)
                    <tr><td class="ns-tname">{{ $r['name'] }}</td><td class="ns-num ns-dn"><b>{{ $r['available'] }}</b></td><td class="ns-num ns-muted">{{ $r['threshold'] }}</td><td class="ns-num">{{ $r['suggest'] }}</td></tr>
                @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">Залежавшийся товар <span class="ns-sm">· без продаж 30+ дней</span></div>
        @if($deadStock->isEmpty())
            <div class="ns-empty ns-sm"><b>Залежавшихся позиций нет</b></div>
        @else
            <div class="ns-hlist">
                @foreach($deadStock as $d)
                    <div class="ns-hr"><div class="ns-grow"><b>{{ $d['name'] }}</b><small>{{ $d['qty'] }} пар · {{ $d['last'] ? 'посл. продажа '.$d['last'] : 'не продавалось' }}</small></div><b>{{ $rub($d['value']) }}</b></div>
                @endforeach
            </div>
        @endif
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-panel" style="overflow:hidden">
    <div class="ns-panel-pad" style="padding-bottom:6px"><div class="ns-sec-h">Продажи за период <span class="ns-tag">{{ $soldDeals->count() }}</span><span class="ns-grow"></span><span class="ns-sm">маржа = прибыль ÷ сумма; «—» — закупочная цена не указана</span></div></div>
    @if($soldDeals->isEmpty())
        <div class="ns-empty ns-sm"><b>Продаж за период нет</b></div>
    @else
        <div style="overflow-x:auto"><table class="ns-tbl">
            <thead><tr><th>Дата</th><th>Товар</th><th>Источник</th><th class="ns-num">Пар</th><th class="ns-num">Сумма</th><th class="ns-num">Себест.</th><th class="ns-num">Прибыль</th><th class="ns-num">Маржа</th></tr></thead>
            <tbody>
            @foreach($soldDeals as $d)
                @php
                    $dProfit = $d->sale_profit;
                    $dMargin = $d->sale_margin_percent;
                    $dCost = $d->unit_cost_basis;
                @endphp
                <tr>
                    <td class="ns-muted">{{ optional($d->stock_deducted_at)->format('d.m H:i') }}</td>
                    <td class="ns-tname"><a class="ns-link" href="{{ route('deals.show', $d->id) }}">{{ $d->warehouseItem?->display_name ?? $d->title }}</a></td>
                    <td>{!! $ui::srcChip($d->manual_source) !!}</td>
                    <td class="ns-num">{{ (int) $d->sold_quantity }}</td>
                    <td class="ns-num"><b>{{ $rub($d->amount) }}</b></td>
                    <td class="ns-num ns-muted">{{ $dCost !== null ? $rub($dCost) : '—' }}</td>
                    <td @class(['ns-num', 'ns-muted' => $dProfit === null, 'ns-up' => $dProfit !== null && $dProfit >= 0, 'ns-dn' => $dProfit !== null && $dProfit < 0])><b>{{ $dProfit !== null ? $sgnRub($dProfit) : '—' }}</b></td>
                    <td @class(['ns-num', 'ns-muted' => $dMargin === null, 'ns-dn' => $dMargin !== null && $dMargin < 0])>{{ $dMargin !== null ? $dMargin.'%' : '—' }}</td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td>Итого</td><td></td><td></td><td class="ns-num">{{ $cur['units'] }}</td><td class="ns-num">{{ $rub($cur['revenue']) }}</td><td></td><td @class(['ns-num', 'ns-up' => $cur['profit'] >= 0, 'ns-dn' => $cur['profit'] < 0])>{{ $sgnRub($cur['profit']) }}</td><td class="ns-num">{{ round($cur['margin']) }}%</td></tr></tfoot>
        </table></div>
    @endif
</div>
@endsection
