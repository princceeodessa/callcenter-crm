@extends('layouts.sneakers')
{{--
    «Продажи за день» кроссовочного отдела — каркас «Нордика» (прототип: PAGES.day, снимок 03). Листание дней ‹ дата ›, «Сегодня»,
    6 KPI со сравнением со вчера и неделей назад, выручка по дням (клик — открыть день), по часам, продавцы, откуда клиенты,
    продажи с итогами и CSV, возвраты. Данные — SneakerDailySalesController; себестоимость и прибыль — только руководителю.
    Владелец смотрит этот же день в оформлении сводок (owner.day).
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $sgnRub = fn ($v) => ($v < 0 ? '−' : '').number_format(abs((float) $v), 0, ',', ' ').' ₽';
    $wd = fn (\Carbon\Carbon $d) => $d->locale('ru')->isoFormat('dd');
    $returnsAmount = (float) $returns->sum(fn ($r) => (float) ($r->amount ?? 0));
    $returnsUnits = (int) $returns->sum(fn ($r) => (int) $r->sold_quantity);
    $profitKnown = $cur['count'] > $cur['no_cost'];
    $img = fn ($item) => $item ? ($images[mb_strtolower(trim(($item->brand ?? '').'|'.($item->model ?? '')))] ?? null) : null;
    $thumb = function (?string $url, string $name) use ($ui) {
        $b = $ui::brandOf($name);
        return '<div class="ns-pimg ns-xs">'.($url ? '<img src="'.e($url).'" alt="" loading="lazy" data-b="'.e($b).'">' : '<span class="ns-phb">'.e(mb_strtoupper(mb_substr($b, 0, 2))).'</span>').'</div>';
    };
    $pay = \App\Models\Deal::PAYMENT_METHODS;
    $csv = [];
@endphp

@section('title', 'Продажи за день')
@section('ns_no_search', '1')
@section('ns_actions')
    <a class="ns-btn ns-icon-only" href="{{ route('sale.day', ['date' => $prevDate]) }}" title="Предыдущий день"><svg class="ns-icon" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></a>
    <button class="ns-btn" type="button" id="dPick" title="Выбрать день">{!! $ui::icon('cal', 'ns-sm') !!}{{ $day->format('d.m.Y') }} · {{ $isToday ? 'сегодня' : $wd($day) }}</button>
    <input type="date" id="dDate" value="{{ $day->toDateString() }}" max="{{ now()->toDateString() }}" class="ns-date-hidden" aria-label="Дата">
    @if($nextDate)
        <a class="ns-btn ns-icon-only" href="{{ route('sale.day', ['date' => $nextDate]) }}" title="Следующий день"><svg class="ns-icon" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></a>
    @else
        <span class="ns-btn ns-icon-only ns-dis" aria-disabled="true"><svg class="ns-icon" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></span>
    @endif
    @unless($isToday)<a class="ns-btn" href="{{ route('sale.day') }}">Сегодня</a>@endunless
    <span class="ns-vsp"></span>
@endsection

@section('content')
<div @class(['ns-kpis', 'ns-k6' => $showProfit, 'ns-k5' => ! $showProfit])>
    <div class="ns-kpi"><div class="ns-kl">Выручка</div><div class="ns-kv">{{ $rub($cur['revenue']) }}</div><div class="ns-kd">вчера: {{ $rub($vsYesterday['revenue']) }}<br>нед. назад: {{ $rub($vsWeekAgo['revenue']) }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Продано пар</div><div class="ns-kv">{{ $cur['units'] }}</div><div class="ns-kd">вчера: {{ $vsYesterday['units'] }} · нед. назад: {{ $vsWeekAgo['units'] }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Продаж (чеков)</div><div class="ns-kv">{{ $cur['count'] }}</div><div class="ns-kd">вчера {{ $vsYesterday['count'] }} · неделю назад {{ $vsWeekAgo['count'] }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Средний чек</div><div class="ns-kv">{{ $cur['count'] ? $rub($cur['avg']) : '—' }}</div><div class="ns-kd">вчера {{ $vsYesterday['count'] ? $rub($vsYesterday['avg']) : '—' }}</div></div>
    @if($showProfit)
        <div class="ns-kpi"><div class="ns-kl">Прибыль{{ $cur['margin'] !== null ? ' · маржа '.$cur['margin'].'%' : '' }}</div>
            <div @class(['ns-kv', 'ns-up' => $profitKnown && $cur['profit'] >= 0, 'ns-dn' => $profitKnown && $cur['profit'] < 0])>{{ $profitKnown ? $sgnRub($cur['profit']) : '—' }}</div>
            <div class="ns-kd">@if($cur['no_cost'] > 0)<span class="ns-warn-t">без себестоимости: {{ $cur['no_cost'] }}</span>@else к вчера: {{ $vsYesterday['count'] ? $sgnRub($vsYesterday['profit']) : 'было 0' }}@endif</div></div>
    @endif
    <div @class(['ns-kpi', 'ns-warn' => $returns->isNotEmpty()])><div class="ns-kl">Возвраты за день</div><div @class(['ns-kv', 'ns-dn' => $returns->isNotEmpty()])>{{ $returns->count() }}</div><div class="ns-kd">{{ $returns->isNotEmpty() ? $returnsUnits.' пар · '.$rub($returnsAmount) : 'нет' }}</div></div>
</div>

<div class="ns-panel ns-panel-pad">
    <div class="ns-sec-h">Выручка по дням <span class="ns-sm">· нажмите на день, чтобы открыть</span></div>
    <div class="ns-chart ns-clk">
        @foreach($strip as $s)
            @php
                $h = $s['revenue'] > 0 ? max(4, $s['revenue'] / $stripMax * 100) : 1.5;
                $k = $s['revenue'] >= 1000 ? number_format($s['revenue'] / 1000, 0, ',', ' ').'к' : ($s['revenue'] > 0 ? number_format($s['revenue'], 0, ',', ' ') : '');
            @endphp
            <a @class(['ns-bwrap', 'ns-on' => $s['active'], 'ns-mh' => $s['mobile_hidden']]) href="{{ route('sale.day', ['date' => $s['date']]) }}" title="{{ $s['label'] }}: {{ $rub($s['revenue']) }}, {{ $s['units'] }} пар">
                <div @class(['ns-bar', 'ns-soft' => ! $s['active']]) style="height:{{ number_format($h, 1, '.', '') }}%">@if($k !== '')<span>{{ $k }}</span>@endif<small><b>{{ $s['label'] }}</b>{{ $s['today'] ? 'сегодня' : $s['weekday'] }}</small></div>
            </a>
        @endforeach
    </div>
    <div class="ns-chart-pad"></div>
</div>
<div class="ns-gap"></div>

<div class="ns-grid3">
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">По часам <span class="ns-sm">· пар</span></div>
        <div class="ns-chart" style="height:130px">
            @foreach($byHour as $hr)
                <div @class(['ns-bar', 'ns-soft' => ! $hr['units']]) style="height:{{ $hr['units'] ? number_format($hr['units'] / $hourMax * 100, 1, '.', '') : 1.5 }}%" title="{{ $hr['hour'] }}:00–{{ $hr['hour'] + 1 }}:00 · {{ $hr['units'] }} пар · {{ $rub($hr['revenue']) }}">@if($hr['units'])<span>{{ $hr['units'] }}</span>@endif<small>{{ $hr['hour'] }}</small></div>
            @endforeach
        </div>
        <div class="ns-chart-pad" style="height:24px"></div>
    </div>
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">Продавцы</div>
        @if($bySeller->isEmpty())
            <div class="ns-empty ns-sm"><b>Продаж нет</b></div>
        @else
            <div class="ns-hlist">
                @foreach($bySeller as $row)
                    <div class="ns-hr"><div class="ns-grow"><b>{{ $row['name'] }}</b><small>{{ $row['units'] }} пар</small></div><b>{{ $rub($row['revenue']) }}</b></div>
                @endforeach
            </div>
        @endif
    </div>
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">Откуда клиенты</div>
        @if($bySource->isEmpty())
            <div class="ns-empty ns-sm"><b>Продаж нет</b></div>
        @else
            <div class="ns-hlist">
                @foreach($bySource as $row)
                    <div class="ns-hr"><div class="ns-grow">{!! $ui::srcChip($row['name']) !!}</div><span class="ns-muted">{{ $row['count'] }}</span><b>{{ $rub($row['revenue']) }}</b></div>
                @endforeach
            </div>
        @endif
    </div>
</div>
<div class="ns-gap"></div>

<div class="ns-panel" style="overflow:hidden">
    <div class="ns-panel-pad" style="padding-bottom:6px"><div class="ns-sec-h">Продажи <span class="ns-tag">{{ $sales->count() }}</span><span class="ns-grow"></span>
        @if($sales->isNotEmpty())<button class="ns-btn ns-sm" type="button" id="dCsv">{!! $ui::icon('down', 'ns-sm') !!}CSV</button>@endif</div></div>
    @if($sales->isEmpty())
        <div class="ns-empty ns-sm">{!! $ui::icon('sell') !!}<b>За этот день продаж нет</b>@if($isToday)<a class="ns-btn ns-primary" style="margin-top:12px" href="{{ route('sale.quick') }}">Оформить продажу</a>@endif</div>
    @else
        <div style="overflow-x:auto"><table class="ns-tbl ns-day-tbl">
            <thead><tr><th>Время</th><th>Товар</th><th>Размер</th><th class="ns-num">Пар</th><th class="ns-num">Сумма</th>
                @if($showProfit)<th class="ns-num">Себест.</th><th class="ns-num">Прибыль</th>@endif
                <th>Продавец</th><th>Источник</th><th>Оплата</th><th></th></tr></thead>
            <tbody>
            @foreach($sales as $deal)
                @php
                    $item = $deal->warehouseItem;
                    $name = ($item ? trim($item->brand.' '.$item->model) : '') ?: $deal->title;
                    $cost = $deal->unit_cost_basis;
                    $profit = $deal->sale_profit;
                    $client = $deal->contact;
                    $clientLabel = $client ? trim(($client->name ?? '').' '.($client->phone ?? '')) : '';
                    $payLabel = $deal->payment_method ? ($pay[$deal->payment_method] ?? $deal->payment_method) : null;
                    $row = [$deal->stock_deducted_at->format('H:i'), $name, $item->size ?? '', (int) $deal->sold_quantity, $deal->amount !== null ? (float) $deal->amount : ''];
                    if ($showProfit) { $row[] = $cost !== null ? round($cost * (int) $deal->sold_quantity, 2) : ''; $row[] = $profit ?? ''; }
                    $csv[] = array_merge($row, [optional($deal->responsible)->name ?? '', $deal->manual_source ?: '', $payLabel ?? '', $clientLabel]);
                @endphp
                <tr>
                    <td class="ns-muted">{{ $deal->stock_deducted_at->format('H:i') }}</td>
                    <td><div class="ns-day-m">{!! $thumb($img($item), $name) !!}<div><span class="ns-tname">{{ $name }}</span>@if($clientLabel !== '')<small>{{ $clientLabel }}</small>@endif</div></div></td>
                    <td>{{ $item->size ?? '—' }}</td>
                    <td class="ns-num">{{ (int) $deal->sold_quantity }}</td>
                    <td class="ns-num"><b>{{ $deal->amount !== null ? $rub($deal->amount) : '—' }}</b></td>
                    @if($showProfit)
                        <td class="ns-num ns-muted">{{ $cost !== null ? $rub($cost * (int) $deal->sold_quantity) : '—' }}</td>
                        <td @class(['ns-num', 'ns-muted' => $profit === null, 'ns-up' => $profit !== null && $profit >= 0, 'ns-dn' => $profit !== null && $profit < 0])><b>{{ $profit !== null ? $sgnRub($profit) : '—' }}</b></td>
                    @endif
                    <td>{{ optional($deal->responsible)->name ?? '—' }}</td>
                    <td>{!! $ui::srcChip($deal->manual_source) !!}</td>
                    <td class="ns-muted">{{ $payLabel ?? '—' }}</td>
                    <td style="white-space:nowrap;text-align:right">
                        <a class="ns-ib" href="{{ route('deals.receipt', $deal->id) }}" target="_blank" rel="noopener" title="Чек"><svg viewBox="0 0 24 24" class="ns-icon"><path d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg></a>
                        <a class="ns-ib" href="{{ route('deals.show', $deal->id) }}" title="Открыть сделку"><svg viewBox="0 0 24 24" class="ns-icon"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/></svg></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td>Итого</td><td></td><td></td><td class="ns-num">{{ $cur['units'] }}</td><td class="ns-num">{{ $rub($cur['revenue']) }}</td>
                @if($showProfit)<td></td><td @class(['ns-num', 'ns-up' => $cur['profit'] >= 0, 'ns-dn' => $cur['profit'] < 0])>{{ $sgnRub($cur['profit']) }}</td>@endif
                <td colspan="4"></td></tr></tfoot>
        </table></div>
    @endif
</div>

@if($returns->isNotEmpty())
    <div class="ns-gap"></div>
    <div class="ns-panel" style="overflow:hidden">
        <div class="ns-panel-pad" style="padding-bottom:6px"><div class="ns-sec-h" style="color:var(--red)">Возвраты за день <span class="ns-tag ns-bad">{{ $returns->count() }}</span></div></div>
        <table class="ns-tbl"><tbody>
            @foreach($returns as $r)
                @php
                    $rItem = $r->warehouseItem;
                    $rName = ($rItem ? trim($rItem->brand.' '.$rItem->model) : '') ?: $r->title;
                    $rSize = $rItem && $rItem->size !== null && $rItem->size !== '' ? ' · р. '.$rItem->size : '';
                @endphp
                <tr>
                    <td class="ns-muted" style="width:70px">{{ $r->returned_at->format('H:i') }}</td>
                    <td class="ns-tname">{{ $rName.$rSize }}</td>
                    <td class="ns-num">{{ (int) $r->sold_quantity }} пар</td>
                    <td class="ns-num ns-dn"><b>{{ $r->amount !== null ? '−'.$rub($r->amount) : '—' }}</b></td>
                    <td style="width:44px"><a class="ns-ib" href="{{ route('deals.show', $r->id) }}" title="Открыть сделку"><svg viewBox="0 0 24 24" class="ns-icon"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/></svg></a></td>
                </tr>
            @endforeach
        </tbody></table>
    </div>
@endif
@php
    $csvHead = array_merge(['Время', 'Товар', 'Размер', 'Пар', 'Сумма'], $showProfit ? ['Себест.', 'Прибыль'] : [], ['Продавец', 'Источник', 'Оплата', 'Клиент']);
    $pickDays = $strip->reverse()->values()->map(fn ($s) => ['date' => $s['date'], 'label' => $s['label'].' · '.($s['today'] ? 'сегодня' : $s['weekday']).($s['units'] ? ' · '.$s['units'].' пар' : '')]);
@endphp
@endsection

@push('scripts')
<script>
(() => {
    const DAY_URL = @json(route('sale.day'));
    const days = @json($pickDays);
    const go = (d) => { location.href = DAY_URL + '?date=' + encodeURIComponent(d); };
    const dateIn = document.getElementById('dDate');
    dateIn.addEventListener('change', () => { if (dateIn.value) go(dateIn.value); });
    // выбор дня — последние две недели меню, «Другая дата…» — календарь браузера
    document.getElementById('dPick').addEventListener('click', (e) => {
        e.stopPropagation();
        const labels = days.map((d) => d.label).concat(['Другая дата…']);
        ns.menuPop(e.currentTarget, 'Выберите день', labels, null, (v) => {
            const i = labels.indexOf(v);
            if (i < days.length) go(days[i].date);
            else { try { dateIn.showPicker(); } catch (err) { dateIn.focus(); dateIn.click(); } }
        });
    });
    document.querySelectorAll('.ns-pimg img').forEach((img) => img.addEventListener('error', () => { img.parentNode.innerHTML = '<span class="ns-phb">' + ns.esc((img.dataset.b || '?').slice(0, 2).toUpperCase()) + '</span>'; }, { once: true }));
    // CSV продаж дня — из той же таблицы (для Excel: «;» и BOM)
    document.getElementById('dCsv')?.addEventListener('click', () => {
        const rows = [@json($csvHead)].concat(@json($csv));
        const cell = (v) => { const s = String(v ?? ''); return /[;"\n]/.test(s) ? '"' + s.replace(/"/g, '""') + '"' : s; };
        const blob = new Blob(['﻿' + rows.map((r) => r.map(cell).join(';')).join('\r\n')], { type: 'text/csv;charset=utf-8' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'prodazhi-{{ $day->toDateString() }}.csv';
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(() => URL.revokeObjectURL(a.href), 1000);
    });
})();
</script>
@endpush
