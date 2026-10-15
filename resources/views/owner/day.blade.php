{{-- «Продажи за день» у владельца — в дизайн-системе сводок (у продавцов и руководителя — sale/day в CRM). --}}
@extends('layouts.owner')

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    // сравнение с прошлым днём / тем же днём прошлой недели — бейдж дизайн-системы
    $cmp = function (float $now, float $was, string $label) {
        if ($was <= 0) {
            return $now > 0 ? '<span class="badge up">новое · '.$label.'</span>' : '';
        }
        $pct = (int) round(($now - $was) / $was * 100);
        if ($pct === 0) {
            return '<span class="badge flat">±0% '.$label.'</span>';
        }

        return '<span class="badge '.($pct > 0 ? 'up' : 'down').'">'.($pct > 0 ? '▲' : '▼').' '.abs($pct).'% '.$label.'</span>';
    };
    $dayTitle = $day->locale('ru')->isoFormat('D MMMM YYYY, dddd');
    $returnsAmount = (float) $returns->sum(fn ($r) => (float) ($r->amount ?? 0));
    $returnsUnits = (int) $returns->sum(fn ($r) => (int) $r->sold_quantity);
    $salesCount = $sales->count();
    $returnsCount = $returns->count();
    $profitKnown = $cur['count'] > $cur['no_cost'];
    $hours = json_encode(['labels' => array_map(fn ($h) => (string) $h['hour'], $byHour->all()),
        'titles' => array_map(fn ($h) => $h['hour'].':00–'.($h['hour'] + 1).':00', $byHour->all()),
        'sets' => [['label' => 'пар', 'data' => array_map(fn ($h) => (int) $h['units'], $byHour->all()), 'color' => '--accent']]], JSON_UNESCAPED_UNICODE);
@endphp

@section('title', 'Продажи за день')
@section('subtitle')Кроссовки · продажи за {{ $dayTitle }}@if($isToday) · сегодня@endif @endsection

@section('nav')
    <a href="{{ route('owner.dashboard') }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg> Сводка</a>
    <a href="#top" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M3 9h18M8 2v4M16 2v4"/></svg> День</a>
    <a href="#strip"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M8 17V11M13 17V7M18 17v-4"/></svg> Последние дни</a>
    <a href="#sales"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/></svg> Продажи</a>
@endsection

@section('content')
<div id="dash">
    <div class="filters" id="top">
        @include('owner._biz_tabs', ['biz' => 'sneakers', 'tab' => 'day'])
        <div class="seg">
            <a href="{{ route('sale.day', ['date' => $prevDate]) }}">← Пред. день</a>
            <a href="{{ route('sale.day') }}" class="{{ $isToday ? 'active' : '' }}">Сегодня</a>
            @if($nextDate)<a href="{{ route('sale.day', ['date' => $nextDate]) }}">След. день →</a>@endif
        </div>
        <div class="spacer"></div>
        @include('owner._daterange', ['mode' => 'day', 'action' => route('sale.day'), 'params' => [], 'from' => $day->toDateString(), 'max' => now()->toDateString()])
    </div>

    <div class="kpis">
        <div class="card kpi accent no-chart">
            <div class="top"><span class="lbl">Выручка</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/></svg></span>
            </div>
            <div class="val num">{{ $money($cur['revenue']) }} ₽</div>
            <div class="badges">{!! $cmp($cur['revenue'], $vsYesterday['revenue'], 'к вчера') !!} {!! $cmp($cur['revenue'], $vsWeekAgo['revenue'], 'к пр. неделе') !!}</div>
        </div>
        <div class="card kpi no-chart">
            <div class="top"><span class="lbl">Продано пар</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 17h20v3H2zM4 17l2-7 5 2 3-4 6 4 2 5"/></svg></span>
            </div>
            <div class="val num">{{ $cur['units'] }}</div>
            <div class="badges">{!! $cmp($cur['units'], $vsYesterday['units'], 'к вчера') !!} {!! $cmp($cur['units'], $vsWeekAgo['units'], 'к пр. неделе') !!}</div>
        </div>
        <div class="card kpi no-chart">
            <div class="top"><span class="lbl">Продаж (чеков)</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/></svg></span>
            </div>
            <div class="val num">{{ $cur['count'] }}</div>
            <div class="cap">вчера {{ $vsYesterday['count'] }} · неделю назад {{ $vsWeekAgo['count'] }}</div>
        </div>
        <div class="card kpi no-chart">
            <div class="top"><span class="lbl">Средний чек</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 12V8H6a2 2 0 010-4h12v4"/><path d="M4 6v12a2 2 0 002 2h14v-4"/><path d="M18 12a2 2 0 000 4h4v-4z"/></svg></span>
            </div>
            <div class="val num">{{ $cur['count'] ? $money($cur['avg']).' ₽' : '—' }}</div>
            <div class="cap">вчера {{ $vsYesterday['count'] ? $money($vsYesterday['avg']).' ₽' : '—' }}</div>
        </div>
    </div>

    <div class="cc">
        @if($showProfit)
            <div class="card">
                <div class="lbl">Прибыль{{ $cur['margin'] !== null ? ' · маржа '.$cur['margin'].'%' : '' }}</div>
                <div class="val num" style="color:{{ ! $profitKnown ? 'var(--muted)' : ($cur['profit'] >= 0 ? 'var(--pos)' : 'var(--neg)') }}">{{ $profitKnown ? $money($cur['profit']).' ₽' : '—' }}</div>
                <div class="sub">@if($cur['no_cost'] > 0)<span style="color:var(--neg)">без себестоимости: {{ $cur['no_cost'] }}</span>@else{!! $cmp($cur['profit'], $vsYesterday['profit'], 'к вчера') !!}@endif</div>
            </div>
        @endif
        <div class="card">
            <div class="lbl">Возвраты за день</div>
            <div class="val num" style="{{ $returnsCount > 0 ? 'color:var(--neg)' : '' }}">{{ $returnsCount }}</div>
            <div class="sub">{{ $returnsCount > 0 ? $returnsUnits.' пар · '.$money($returnsAmount).' ₽' : 'нет' }}</div>
        </div>
        <div class="card">
            <div class="lbl">Вчера и неделю назад</div>
            <div class="val num">{{ $money($vsYesterday['revenue']) }} ₽</div>
            <div class="sub">вчера · {{ $vsYesterday['units'] }} пар; неделю назад {{ $money($vsWeekAgo['revenue']) }} ₽ · {{ $vsWeekAgo['units'] }} пар</div>
        </div>
    </div>

    {{-- Последние дни: клик — перейти на день --}}
    <div class="card chartcard" id="strip">
        <div class="sec" style="margin:0 0 10px"><h2>Выручка по дням</h2><span class="note">нажмите на день, чтобы открыть</span></div>
        <div class="day-strip">
            @foreach($strip as $s)
                @php $h = $s['revenue'] > 0 ? max(6, (int) round($s['revenue'] / $stripMax * 120)) : 3; @endphp
                <a class="ds-day {{ $s['active'] ? 'active' : '' }} {{ $s['weekend'] ? 'weekend' : '' }}" href="{{ route('sale.day', ['date' => $s['date']]) }}"
                   title="{{ $s['label'] }}: {{ $money($s['revenue']) }} ₽, {{ $s['units'] }} пар">
                    <span class="v">{{ $s['revenue'] > 0 ? $money(round($s['revenue'] / 1000)).'к' : '' }}</span>
                    <span class="b" style="height:{{ $h }}px"></span>
                    <span class="t">{{ $s['today'] ? 'сегодня' : $s['label'] }}<br>{{ $s['weekday'] }}</span>
                </a>
            @endforeach
        </div>
    </div>

    <div class="grid3">
        <div class="card chartcard" style="margin-bottom:0">
            <div class="sec" style="margin:0 0 4px"><h2>По часам</h2><span class="note">пар</span></div>
            <div style="height:200px"><canvas id="bars-hours" data-bars="{{ $hours }}"></canvas></div>
        </div>
        <div class="card tbl-card">
            <div class="tbl-head"><h2>Продавцы</h2></div>
            <div class="tbl-wrap"><table class="t">
                <tbody>
                @forelse($bySeller as $row)
                    <tr><td>@include('owner._op_ava', ['name' => $row['name']])</td><td class="num"><b>{{ $row['units'] }}</b> пар</td><td class="num">{{ $money($row['revenue']) }} ₽</td></tr>
                @empty
                    <tr><td class="muted">Продаж нет</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
        <div class="card tbl-card">
            <div class="tbl-head"><h2>Откуда клиенты</h2></div>
            <div class="tbl-wrap"><table class="t">
                <tbody>
                @forelse($bySource as $row)
                    <tr><td><span class="nm-inline">@include('owner._src_logo', ['name' => $row['name']]){{ $row['name'] }}</span></td><td class="num"><b>{{ $row['count'] }}</b></td><td class="num">{{ $money($row['revenue']) }} ₽</td></tr>
                @empty
                    <tr><td class="muted">Продаж нет</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    {{-- Все продажи дня --}}
    <div class="card tbl-card" id="sales">
        <div class="tbl-head"><h2>Продажи · {{ $salesCount }}</h2></div>
        @if($salesCount === 0)
            <div class="empty-card" style="margin:0">За этот день продаж нет.</div>
        @else
            <div class="tbl-wrap"><table class="t">
                <thead><tr>
                    <th>Время</th><th>Товар</th><th class="num">Размер</th><th class="num">Пар</th><th class="num">Сумма</th>
                    @if($showProfit)<th class="num">Себест.</th><th class="num">Прибыль</th>@endif
                    <th>Продавец</th><th>Источник</th><th>Клиент</th>
                </tr></thead>
                <tbody>
                @foreach($sales as $deal)
                    @php
                        $item = $deal->warehouseItem;
                        $itemName = ($item ? trim($item->brand.' '.$item->model) : '') ?: $deal->title;
                        $unitCost = $deal->unit_cost_basis;
                        $dProfit = $deal->sale_profit;
                        $client = $deal->contact;
                        $clientLabel = $client ? trim(($client->name ?? '').' '.($client->phone ?? '')) : '';
                    @endphp
                    <tr>
                        <td class="muted">{{ $deal->stock_deducted_at->format('H:i') }}</td>
                        <td class="wrap">{{ $itemName }}</td>
                        <td class="num">{{ $item->size ?? '—' }}</td>
                        <td class="num">{{ (int) $deal->sold_quantity }}</td>
                        <td class="num"><b>{{ $deal->amount !== null ? $money($deal->amount).' ₽' : '—' }}</b></td>
                        @if($showProfit)
                            <td class="num muted">{{ $unitCost !== null ? $money($unitCost * (int) $deal->sold_quantity).' ₽' : '—' }}</td>
                            <td class="num" style="font-weight:700;color:{{ $dProfit === null ? 'var(--muted)' : ($dProfit >= 0 ? 'var(--pos)' : 'var(--neg)') }}">{{ $dProfit !== null ? $money($dProfit).' ₽' : '—' }}</td>
                        @endif
                        <td>{{ optional($deal->responsible)->name ?? '—' }}</td>
                        <td>{{ $deal->manual_source ?: '—' }}</td>
                        <td class="wrap">{{ $clientLabel !== '' ? $clientLabel : '—' }}</td>
                    </tr>
                @endforeach
                <tr class="grp">
                    <td colspan="3">Итого</td>
                    <td class="num">{{ $cur['units'] }}</td>
                    <td class="num">{{ $money($cur['revenue']) }} ₽</td>
                    @if($showProfit)<td></td><td class="num" style="color:{{ $cur['profit'] >= 0 ? 'var(--pos)' : 'var(--neg)' }}">{{ $money($cur['profit']) }} ₽</td>@endif
                    <td colspan="3"></td>
                </tr>
                </tbody>
            </table></div>
        @endif
    </div>

    @if($returnsCount > 0)
        <div class="card tbl-card">
            <div class="tbl-head"><h2 style="color:var(--neg)">Возвраты за день · {{ $returnsCount }}</h2></div>
            <div class="tbl-wrap"><table class="t">
                <tbody>
                @foreach($returns as $r)
                    @php
                        $rItem = $r->warehouseItem;
                        $rName = ($rItem ? trim($rItem->brand.' '.$rItem->model) : '') ?: $r->title;
                        $rSize = $rItem && $rItem->size !== null && $rItem->size !== '' ? ' · р. '.$rItem->size : '';
                    @endphp
                    <tr>
                        <td class="muted">{{ $r->returned_at->format('H:i') }}</td>
                        <td class="wrap">{{ $rName.$rSize }}</td>
                        <td class="num">{{ (int) $r->sold_quantity }} пар</td>
                        <td class="num">{{ $r->amount !== null ? $money($r->amount).' ₽' : '—' }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif
</div>
@endsection
