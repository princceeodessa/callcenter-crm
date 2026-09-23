@extends('layouts.app')

@section('content')
<style>
    .sd-wrap{ max-width:1200px; margin:0 auto; }
    .sd-kpi{ border:1px solid var(--crm-border); border-radius:.9rem; box-shadow:var(--crm-shadow); background:var(--crm-surface-strong); padding:.8rem .95rem; height:100%; }
    .sd-kpi .l{ font-size:.72rem; color:var(--crm-muted); }
    .sd-kpi .v{ font-size:1.45rem; font-weight:800; line-height:1.15; }
    .sd-kpi .s{ font-size:.72rem; color:var(--crm-muted); }
    .sd-strip{ display:flex; gap:4px; align-items:flex-end; height:92px; }
    .sd-day{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; text-decoration:none; color:var(--crm-muted); border-radius:.4rem; padding:2px 0; }
    .sd-day:hover{ background:rgba(148,163,184,.14); }
    .sd-day .b{ width:70%; min-height:2px; border-radius:.3rem .3rem 0 0; background:linear-gradient(180deg,#a5b4fc,#6366f1); }
    .sd-day.active .b{ background:linear-gradient(180deg,#34d399,#059669); }
    .sd-day.active{ color:var(--crm-text); font-weight:700; }
    .sd-day .t{ font-size:.66rem; line-height:1.1; margin-top:2px; text-align:center; white-space:nowrap; }
    .sd-day.weekend .t{ color:#f97316; }
    @media (max-width: 575.98px){ .sd-day.sd-old{ display:none; } }
    .sd-hours{ display:flex; gap:3px; align-items:flex-end; height:110px; }
    .sd-hour{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; }
    .sd-hour .b{ width:72%; min-height:2px; border-radius:.3rem .3rem 0 0; background:linear-gradient(180deg,#818cf8,#4f46e5); }
    .sd-hour .n{ font-size:.66rem; font-weight:700; min-height:.9rem; }
    .sd-hour .t{ font-size:.66rem; color:var(--crm-muted); }
    .sd-table td, .sd-table th{ vertical-align:middle; }
    .sd-time{ font-family:ui-monospace,Menlo,monospace; font-size:.85rem; }
</style>

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $cmp = function (float $now, float $was, string $label) {
        if ($was <= 0 && $now <= 0) {
            return '<span class="text-muted">'.$label.': 0</span>';
        }
        if ($was <= 0) {
            return '<span class="text-success">'.$label.': было 0</span>';
        }
        $pct = (int) round(($now - $was) / $was * 100);
        if ($pct === 0) {
            return '<span class="text-muted">'.$label.': ±0%</span>';
        }
        $cls = $pct > 0 ? 'text-success' : 'text-danger';
        $arrow = $pct > 0 ? '▲' : '▼';

        return '<span class="'.$cls.'">'.$arrow.' '.abs($pct).'% '.$label.'</span>';
    };
    $dayTitle = $day->locale('ru')->isoFormat('D MMMM YYYY, dddd');
    $dateValue = $day->toDateString();
    $returnsAmount = (float) $returns->sum(fn ($r) => (float) ($r->amount ?? 0));
    $returnsUnits = (int) $returns->sum(fn ($r) => (int) $r->sold_quantity);
    $salesCount = $sales->count();
    $returnsCount = $returns->count();
    $stripOldBefore = $strip->count() - 7; // на телефоне показываем только последнюю неделю
    $profitKnown = $cur['count'] > $cur['no_cost'];
    $marginLabel = $cur['margin'] !== null ? ' · маржа '.$cur['margin'].'%' : '';
    $returnsNote = $returnsCount > 0 ? $returnsUnits.' пар · '.$money($returnsAmount).' ₽' : 'нет';
@endphp

<div class="sd-wrap">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">🗓 Продажи за день</h4>
            <div class="text-muted small">{{ $dayTitle }}
                @if($isToday)
                    · <span class="text-success fw-semibold">сегодня</span>
                @endif
            </div>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('sale.day', ['date' => $prevDate]) }}" title="Предыдущий день">← Вчера</a>
            <form method="GET" action="{{ route('sale.day') }}" class="d-flex gap-2 align-items-center">
                <input type="date" name="date" value="{{ $dateValue }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" onchange="this.form.submit()">
            </form>
            @if($nextDate)
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('sale.day', ['date' => $nextDate]) }}" title="Следующий день">Завтра →</a>
            @endif
            @if(! $isToday)
                <a class="btn btn-sm btn-primary" href="{{ route('sale.day') }}">Сегодня</a>
            @endif
            <a class="btn btn-sm btn-success" href="{{ route('sale.quick') }}">💵 Продать</a>
        </div>
    </div>

    {{-- KPI дня --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg">
            <div class="sd-kpi">
                <div class="l">Выручка</div>
                <div class="v">{{ $money($cur['revenue']) }} ₽</div>
                <div class="s">{!! $cmp($cur['revenue'], $vsYesterday['revenue'], 'к вчера') !!}<br>{!! $cmp($cur['revenue'], $vsWeekAgo['revenue'], 'к пр. неделе') !!}</div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="sd-kpi">
                <div class="l">Продано пар</div>
                <div class="v">{{ $cur['units'] }}</div>
                <div class="s">{!! $cmp($cur['units'], $vsYesterday['units'], 'к вчера') !!}<br>{!! $cmp($cur['units'], $vsWeekAgo['units'], 'к пр. неделе') !!}</div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="sd-kpi">
                <div class="l">Продаж (чеков)</div>
                <div class="v">{{ $cur['count'] }}</div>
                <div class="s">вчера {{ $vsYesterday['count'] }} · неделю назад {{ $vsWeekAgo['count'] }}</div>
            </div>
        </div>
        <div class="col-6 col-lg">
            <div class="sd-kpi">
                <div class="l">Средний чек</div>
                <div class="v">{{ $cur['count'] ? $money($cur['avg']).' ₽' : '—' }}</div>
                <div class="s">вчера {{ $vsYesterday['count'] ? $money($vsYesterday['avg']).' ₽' : '—' }}</div>
            </div>
        </div>
        @if($isHead)
            <div class="col-6 col-lg">
                <div class="sd-kpi">
                    <div class="l">Прибыль{{ $marginLabel }}</div>
                    <div class="v {{ ! $profitKnown ? 'text-muted' : ($cur['profit'] >= 0 ? 'text-success' : 'text-danger') }}">{{ $profitKnown ? $money($cur['profit']).' ₽' : '—' }}</div>
                    <div class="s">
                        @if($cur['no_cost'] > 0)
                            <span class="text-warning">без себестоимости: {{ $cur['no_cost'] }}</span>
                        @else
                            {!! $cmp($cur['profit'], $vsYesterday['profit'], 'к вчера') !!}
                        @endif
                    </div>
                </div>
            </div>
        @endif
        <div class="col-6 col-lg">
            <div class="sd-kpi">
                <div class="l">Возвраты за день</div>
                <div class="v {{ $returnsCount > 0 ? 'text-danger' : '' }}">{{ $returnsCount }}</div>
                <div class="s">{{ $returnsNote }}</div>
            </div>
        </div>
    </div>

    {{-- Последние дни: клик — перейти на день --}}
    <div class="card shadow-sm mb-3"><div class="card-body py-2">
        <div class="small text-muted mb-1">Выручка по дням · нажмите на день, чтобы открыть</div>
        <div class="sd-strip">
            @foreach($strip as $idx => $s)
                @php
                    $h = $s['revenue'] > 0 ? max(4, (int) round($s['revenue'] / $stripMax * 60)) : 2;
                    $tip = $s['label'].': '.$money($s['revenue']).' ₽, '.$s['units'].' пар';
                    $cls = 'sd-day'.($s['active'] ? ' active' : '').($s['weekend'] ? ' weekend' : '').($idx < $stripOldBefore ? ' sd-old' : '');
                @endphp
                <a class="{{ $cls }}" href="{{ route('sale.day', ['date' => $s['date']]) }}" title="{{ $tip }}">
                    <div class="b" style="height: {{ $h }}px"></div>
                    <div class="t">{{ $s['label'] }}<br>{{ $s['weekday'] }}</div>
                </a>
            @endforeach
        </div>
    </div></div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="fw-semibold mb-2">По часам <span class="small text-muted">(пар)</span></div>
                <div class="sd-hours">
                    @foreach($byHour as $hr)
                        @php
                            $hh = $hr['units'] > 0 ? max(4, (int) round($hr['units'] / $hourMax * 80)) : 2;
                        @endphp
                        <div class="sd-hour" title="{{ $hr['hour'] }}:00–{{ $hr['hour'] + 1 }}:00 · {{ $hr['units'] }} пар · {{ $money($hr['revenue']) }} ₽">
                            <div class="n">{{ $hr['units'] ?: '' }}</div>
                            <div class="b" style="height: {{ $hh }}px"></div>
                            <div class="t">{{ $hr['hour'] }}</div>
                        </div>
                    @endforeach
                </div>
            </div></div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="fw-semibold mb-2">Продавцы</div>
                @forelse($bySeller as $row)
                    <div class="d-flex justify-content-between small py-1 border-bottom">
                        <span>{{ $row['name'] }}</span>
                        <span class="text-nowrap"><b>{{ $row['units'] }}</b> пар · {{ $money($row['revenue']) }} ₽</span>
                    </div>
                @empty
                    <div class="text-muted small">Продаж нет</div>
                @endforelse
            </div></div>
        </div>
        <div class="col-lg-3 col-md-6">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="fw-semibold mb-2">Откуда клиенты</div>
                @forelse($bySource as $row)
                    <div class="d-flex justify-content-between small py-1 border-bottom">
                        <span>{{ $row['name'] }}</span>
                        <span class="text-nowrap"><b>{{ $row['count'] }}</b> · {{ $money($row['revenue']) }} ₽</span>
                    </div>
                @empty
                    <div class="text-muted small">Продаж нет</div>
                @endforelse
            </div></div>
        </div>
    </div>

    {{-- Все продажи дня --}}
    <div class="card shadow-sm mb-3"><div class="card-body">
        <div class="fw-semibold mb-2">Продажи · {{ $salesCount }}</div>
        @if($salesCount === 0)
            <div class="text-muted py-3 text-center">
                За этот день продаж нет.
                @if($isToday)<a href="{{ route('sale.quick') }}">Оформить продажу →</a>@endif
            </div>
        @else
            <div class="table-responsive">
                <table class="table table-sm table-hover sd-table mb-0">
                    <thead>
                        <tr class="small text-muted">
                            <th>Время</th>
                            <th>Товар</th>
                            <th class="text-center">Размер</th>
                            <th class="text-end">Пар</th>
                            <th class="text-end">Сумма</th>
                            @if($isHead)
                                <th class="text-end">Себест.</th>
                                <th class="text-end">Прибыль</th>
                            @endif
                            <th>Продавец</th>
                            <th>Источник</th>
                            <th>Клиент</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($sales as $deal)
                            @php
                                $item = $deal->warehouseItem;
                                $itemName = ($item ? trim($item->brand.' '.$item->model) : '') ?: $deal->title;
                                $unitCost = $deal->unit_cost_basis;
                                $profit = $deal->sale_profit;
                                $client = $deal->contact;
                                $clientLabel = $client ? trim(($client->name ?? '').' '.($client->phone ?? '')) : '';
                            @endphp
                            <tr>
                                <td class="sd-time">{{ $deal->stock_deducted_at->format('H:i') }}</td>
                                <td>{{ $itemName }}</td>
                                <td class="text-center">{{ $item->size ?? '—' }}</td>
                                <td class="text-end">{{ (int) $deal->sold_quantity }}</td>
                                <td class="text-end fw-semibold text-nowrap">{{ $deal->amount !== null ? $money($deal->amount).' ₽' : '—' }}</td>
                                @if($isHead)
                                    <td class="text-end text-muted text-nowrap">{{ $unitCost !== null ? $money($unitCost * (int) $deal->sold_quantity).' ₽' : '—' }}</td>
                                    <td class="text-end text-nowrap {{ $profit === null ? 'text-muted' : ($profit >= 0 ? 'text-success' : 'text-danger') }}">{{ $profit !== null ? $money($profit).' ₽' : '—' }}</td>
                                @endif
                                <td class="small">{{ optional($deal->responsible)->name ?? '—' }}</td>
                                <td class="small">{{ $deal->manual_source ?: '—' }}</td>
                                <td class="small">{{ $clientLabel !== '' ? $clientLabel : '—' }}</td>
                                <td class="text-end text-nowrap">
                                    <a href="{{ route('deals.receipt', $deal->id) }}" target="_blank" title="Чек" class="text-decoration-none">🖨️</a>
                                    <a href="{{ route('deals.show', $deal->id) }}" title="Открыть сделку" class="text-decoration-none ms-1">↗</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="fw-semibold">
                            <td colspan="3">Итого</td>
                            <td class="text-end">{{ $cur['units'] }}</td>
                            <td class="text-end text-nowrap">{{ $money($cur['revenue']) }} ₽</td>
                            @if($isHead)
                                <td></td>
                                <td class="text-end text-nowrap {{ $cur['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($cur['profit']) }} ₽</td>
                            @endif
                            <td colspan="4"></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div></div>

    @if($returnsCount > 0)
        <div class="card shadow-sm mb-3"><div class="card-body">
            <div class="fw-semibold mb-2 text-danger">Возвраты за день · {{ $returnsCount }}</div>
            <table class="table table-sm mb-0">
                <tbody>
                    @foreach($returns as $r)
                        @php
                            $rItem = $r->warehouseItem;
                            $rName = ($rItem ? trim($rItem->brand.' '.$rItem->model) : '') ?: $r->title;
                            $rSize = $rItem && $rItem->size !== null && $rItem->size !== '' ? ' · р. '.$rItem->size : '';
                        @endphp
                        <tr>
                            <td class="sd-time">{{ $r->returned_at->format('H:i') }}</td>
                            <td>{{ $rName.$rSize }}</td>
                            <td class="text-end">{{ (int) $r->sold_quantity }} пар</td>
                            <td class="text-end">{{ $r->amount !== null ? $money($r->amount).' ₽' : '—' }}</td>
                            <td class="text-end"><a href="{{ route('deals.show', $r->id) }}">↗</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div></div>
    @endif
</div>
@endsection
