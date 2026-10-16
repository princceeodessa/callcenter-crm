@extends('layouts.owner')

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $navUser = auth()->user();
    $isOwner = $navUser?->role === 'sneaker_owner';
    $pFrom = \Carbon\Carbon::parse($fromValue);
    $pTo = \Carbon\Carbon::parse($toValue);
    $periodLabel = $pFrom->isSameDay($pTo) ? $pFrom->format('d.m.Y') : $pFrom->format('d.m.Y').' — '.$pTo->format('d.m.Y');

    // ряд по дням (или месяцам) из контроллера — графики в карточках и «по дням»
    $rows = $series['rows'];
    $showChart = count($rows) >= 2;
    $byMonth = $series['by'] === 'month';
    $titles = array_map(fn ($r) => $byMonth ? \Carbon\Carbon::parse($r['key'].'-01')->translatedFormat('F Y') : \Carbon\Carbon::parse($r['key'])->format('d.m.Y'), $rows);
    $axis = array_map(fn ($r) => $byMonth ? \Carbon\Carbon::parse($r['key'].'-01')->translatedFormat('M') : \Carbon\Carbon::parse($r['key'])->format('d'), $rows);
    $spark = fn (string $key, $unit) => json_encode(['data' => array_map(fn ($r) => round($r[$key]), $rows), 'labels' => $titles, 'unit' => $unit], JSON_UNESCAPED_UNICODE);
    $bars = json_encode(['labels' => $axis, 'titles' => $titles, 'sets' => [
        ['label' => 'выручка, ₽', 'data' => array_map(fn ($r) => round($r['revenue']), $rows), 'color' => '--blue'],
        ['label' => 'прибыль, ₽', 'data' => array_map(fn ($r) => round($r['profit']), $rows), 'color' => '--accent'],
    ]], JSON_UNESCAPED_UNICODE);
@endphp

@section('brand', 'shag')
@section('title', 'Кроссовки')
@section('subtitle')Сводка бизнеса · Кроссовки · {{ $periodLabel }} · склад в деньгах, продажи и прибыль по каждому заказу@endsection

@section('nav')
    <a href="#top" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg> Обзор</a>
    <a href="#stock"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8l-9-5-9 5 9 5 9-5z"/><path d="M3 8v8l9 5 9-5V8"/><path d="M12 13v8"/></svg> Склад</a>
    @if($showChart)
        <a href="#days"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M8 17V11M13 17V7M18 17v-4"/></svg> {{ $byMonth ? 'По месяцам' : 'По дням' }}</a>
    @endif
    <a href="#models"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l9 5-9 5-9-5 9-5z"/><path d="M3 12l9 5 9-5M3 17l9 5 9-5"/></svg> Модели и источники</a>
    <a href="#sales"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/></svg> Все продажи</a>
    <span class="lbl">{{ $isOwner ? 'Ещё' : 'CRM' }}</span>
    <a href="{{ route('sale.day') }}" class="sub" title="Продажи кроссовок за день"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M3 9h18M8 2v4M16 2v4"/></svg> Продажи за день</a>
    <a href="{{ route('owner.smm', ['business' => 'sneakers']) }}" class="sub"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M10 8l5 4-5 4V8z"/></svg> SMM-контент</a>
    @unless($isOwner)
        <a href="{{ route('sale.quick') }}" class="sub"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M15 18l-6-6 6-6"/></svg> Вернуться в CRM</a>
    @endunless
@endsection

@section('content')
<div id="dash">
    <div class="filters" id="top">
        @include('owner._biz_tabs', ['biz' => 'sneakers', 'tab' => 'summary'])
        <div class="seg">
            @foreach($periods as $key => $label)
                <a href="{{ route('owner.dashboard', ['period' => $key]) }}" class="{{ $period === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="spacer"></div>
        @include('owner._daterange', ['action' => route('owner.dashboard'), 'params' => ['period' => 'custom'], 'from' => $fromValue, 'to' => $toValue])
    </div>

    {{-- KPI за месяц --}}
    <div class="kpis">
        <div class="card kpi accent {{ $showChart ? '' : 'no-chart' }}">
            <div class="top"><span class="lbl">Выручка</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/></svg></span>
            </div>
            <div class="val num">{{ $money($revenue) }} ₽</div>
            <div class="cap">заказов {{ $count }} · средний чек {{ $count > 0 ? $money($avg).' ₽' : '—' }}</div>
            @if($showChart)<div class="kpi-chart"><canvas id="sp-rev" data-on-accent="1" data-spark="{{ $spark('revenue', '₽') }}"></canvas></div>@endif
        </div>
        <div class="card kpi {{ $showChart ? '' : 'no-chart' }}">
            <div class="top"><span class="lbl">Чистая прибыль</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 17l6-6 4 4 8-8"/><path d="M14 7h7v7"/></svg></span>
            </div>
            <div class="val num" style="{{ $profit < 0 ? 'color:var(--neg)' : '' }}">{{ $money($profit) }} ₽</div>
            <div style="margin-bottom:4px"><span class="badge {{ $profit >= 0 ? 'up' : 'down' }}">маржа {{ $revenue > 0 ? round($margin).'%' : '—' }}</span></div>
            @if($showChart)<div class="kpi-chart"><canvas id="sp-prof" data-spark="{{ $spark('profit', '₽') }}"></canvas></div>@endif
        </div>
        <div class="card kpi {{ $showChart ? '' : 'no-chart' }}">
            <div class="top"><span class="lbl">Продано пар</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 17h20v3H2zM4 17l2-7 5 2 3-4 6 4 2 5"/></svg></span>
            </div>
            <div class="val num">{{ $units }}</div>
            <div class="cap">{{ $periodLabel }}</div>
            @if($showChart)<div class="kpi-chart"><canvas id="sp-units" data-spark="{{ $spark('units', ['пара', 'пары', 'пар']) }}"></canvas></div>@endif
        </div>
        <div class="card kpi {{ $showChart ? '' : 'no-chart' }}">
            <div class="top"><span class="lbl">Заказов</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/></svg></span>
            </div>
            <div class="val num">{{ $count }}</div>
            <div class="cap">средний чек {{ $count > 0 ? $money($avg).' ₽' : '—' }}</div>
            @if($showChart)<div class="kpi-chart"><canvas id="sp-orders" data-spark="{{ $spark('orders', ['заказ', 'заказа', 'заказов']) }}"></canvas></div>@endif
        </div>
    </div>

    @if($noCostCount > 0)
        <div class="card insights" style="border-left-color:var(--neg)">
            <div class="st">Прибыль посчитана не по всем заказам</div>
            <ul><li>У {{ $noCostCount }} заказ(ов) не указана закупочная цена — их прибыль в расчёт не вошла и показана как «—».
                Проставьте закуп на складе (⚙️ Управление товаром → Цены), чтобы прибыль считалась полностью.</li></ul>
        </div>
    @endif

    {{-- Склад в деньгах --}}
    <div class="sec" id="stock"><h2>Склад в деньгах</h2><span class="note">сейчас, без учёта месяца</span></div>
    <div class="kpis4">
        <div class="card mini"><div class="lbl">Товаров</div><div class="val num">{{ $productsCount }}</div></div>
        <div class="card mini"><div class="lbl">Пар на складе</div><div class="val num">{{ $totalUnits }}</div></div>
        <div class="card mini"><div class="lbl">Доступно</div><div class="val num">{{ $availableSum }}</div></div>
        <div class="card mini"><div class="lbl">Вложено · по закупке</div><div class="val num">{{ $money($stockCostValue) }} ₽</div></div>
        <div class="card mini"><div class="lbl">Стоимость · по продаже</div><div class="val num">{{ $money($stockSaleValue) }} ₽</div></div>
        <div class="card mini"><div class="lbl">Потенц. прибыль</div><div class="val num" style="color:var(--accent)">{{ $money($potentialProfit) }} ₽</div></div>
        <div class="card mini"><div class="lbl">Пар в доставке</div><div class="val num">{{ $inDeliveryUnits }}</div></div>
        <div class="card mini"><div class="lbl">В доставке · по закупке</div><div class="val num">{{ $money($inDeliveryValue) }} ₽</div></div>
    </div>

    @if($showChart)
        <div class="card chartcard" id="days" style="margin-top:10px">
            <div class="sec" style="margin:0 0 4px">
                <h2>Выручка и прибыль {{ $byMonth ? 'по месяцам' : 'по дням' }}</h2>
                <div class="legend">
                    <span class="lg"><span class="dot2" style="background:var(--blue)"></span>выручка</span>
                    <span class="lg"><span class="dot2" style="background:var(--accent)"></span>прибыль</span>
                </div>
            </div>
            <div class="chart-h"><canvas id="bars-days" data-bars="{{ $bars }}"></canvas></div>
        </div>
    @endif

    {{-- Топ моделей и источники --}}
    <div class="grid2" id="models">
        <div class="card tbl-card">
            <div class="tbl-head"><h2>Топ моделей за период</h2><span class="note">по прибыли</span></div>
            <div class="tbl-wrap"><table class="t">
                <thead><tr><th>Модель</th><th class="num">Пар</th><th class="num">Выручка</th><th class="num">Прибыль</th></tr></thead>
                <tbody>
                @forelse($topModels as $t)
                    <tr>
                        <td class="wrap">{{ $t['name'] }}</td>
                        <td class="num">{{ $t['units'] }}</td>
                        <td class="num">{{ $money($t['revenue']) }} ₽</td>
                        <td class="num" style="color:var(--pos);font-weight:700">{{ $money($t['profit']) }} ₽</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Продаж за период нет.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card tbl-card">
            <div class="tbl-head"><h2>По источникам за период</h2></div>
            <div class="tbl-wrap"><table class="t">
                <thead><tr><th>Источник</th><th class="num">Заказов</th><th class="num">Выручка</th><th class="num">Прибыль</th></tr></thead>
                <tbody>
                @forelse($bySource as $s)
                    <tr>
                        <td><span class="nm-inline">@include('owner._src_logo', ['name' => $s['name']]){{ $s['name'] }}</span></td>
                        <td class="num">{{ $s['count'] }}</td>
                        <td class="num">{{ $money($s['revenue']) }} ₽</td>
                        <td class="num" style="color:var(--pos);font-weight:700">{{ $money($s['profit']) }} ₽</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="muted">Нет данных.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    {{-- Все продажи за месяц с маржой по каждому заказу --}}
    <div class="card tbl-card" id="sales">
        <div class="tbl-head"><h2>Все продажи за период — прибыль по каждому заказу</h2><span class="note">{{ $count }} заказ(ов) · «—» = закуп не указан</span></div>
        <div class="tbl-wrap tbl-scroll"><table class="t">
            <thead><tr>
                <th>Дата</th><th>Товар</th><th>Источник</th><th>Продавец</th>
                <th class="num">Пар</th><th class="num">Сумма</th><th class="num">Себест.</th><th class="num">Прибыль</th><th class="num">Маржа</th>
            </tr></thead>
            <tbody>
            @forelse($sold as $d)
                @php
                    $dProfit = $d->sale_profit;
                    $dMargin = $d->sale_margin_percent;
                    $dCost = $d->unit_cost_basis;
                @endphp
                <tr>
                    <td class="muted">{{ optional($d->stock_deducted_at)->format('d.m H:i') }}</td>
                    <td class="wrap">{{ $d->warehouseItem?->display_name ?? $d->title }}</td>
                    <td>{{ $d->manual_source ?: '—' }}</td>
                    <td>{{ $d->responsible?->name ?? '—' }}</td>
                    <td class="num">{{ (int) $d->sold_quantity }}</td>
                    <td class="num">{{ $money($d->amount) }} ₽</td>
                    <td class="num muted">{{ $dCost !== null ? $money($dCost).' ₽' : '—' }}</td>
                    <td class="num" style="font-weight:700;color:{{ $dProfit === null ? 'var(--muted)' : ($dProfit >= 0 ? 'var(--pos)' : 'var(--neg)') }}">{{ $dProfit !== null ? $money($dProfit).' ₽' : '—' }}</td>
                    <td class="num {{ $dMargin === null ? 'muted' : '' }}">{{ $dMargin !== null ? $dMargin.'%' : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="9" class="muted">Продаж за выбранный период нет.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>
@endsection
