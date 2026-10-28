@extends('layouts.sneakers')
{{-- «Реализация» — пары у посредников (кнопка на складе). Каркас «Нордика»; себестоимость — только руководителю.
     Передать пары и закрыть передачу — в окне размера на складе. --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
@endphp

@section('title', 'Реализация')
@section('ns_sub', 'пары, переданные посредникам на продажу')

@section('content')
<div class="ns-kpis">
    <div class="ns-kpi"><div class="ns-kl">Пар у посредников</div><div class="ns-kv">{{ $totalQty }}</div><div class="ns-kd">сейчас на реализации</div></div>
    <div class="ns-kpi"><div class="ns-kl">Посредников</div><div class="ns-kv">{{ $byConsignee->count() }}</div><div class="ns-kd">держат пары</div></div>
    @if($isHead)
        <div class="ns-kpi"><div class="ns-kl">По себестоимости</div><div class="ns-kv">{{ $money($totalCostValue) }}</div><div class="ns-kd">закупка этих пар</div></div>
    @endif
</div>

<div class="ns-sec-h">Сейчас на реализации</div>
<div class="ns-panel" style="overflow:hidden;margin-bottom:18px">
    @if($byConsignee->isEmpty())
        <div class="ns-empty ns-sm">{!! $ui::icon('hand') !!}<b>Никто ничего не реализует</b>Все пары на складе или проданы. Передать пары — в окне размера на складе</div>
    @else
        <table class="ns-tbl">
            <thead><tr><th>Посредник</th><th>Товары</th><th class="ns-num">Пар</th>@if($isHead)<th class="ns-num">Себестоимость</th>@endif</tr></thead>
            <tbody>
            @foreach($byConsignee as $group)
                <tr>
                    <td class="ns-tname">{{ $group['name'] }}</td>
                    <td class="ns-muted">@foreach($group['items'] as $c){{ $c->item?->display_name ?? '—' }} · р. {{ $c->item?->size ?: '—' }} × {{ $c->quantity }}@if(! $loop->last), @endif @endforeach</td>
                    <td class="ns-num"><b>{{ $group['qty'] }}</b></td>
                    @if($isHead)<td class="ns-num">{{ $money($group['cost_value']) }}</td>@endif
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="ns-sec-h">История <span class="ns-sm">последние 50</span></div>
<div class="ns-panel" style="overflow:hidden">
    @if($history->isEmpty())
        <div class="ns-empty ns-sm">{!! $ui::icon('clock') !!}<b>Пока пусто</b>Здесь появятся закрытые передачи: продано или возвращено</div>
    @else
        <table class="ns-tbl">
            <thead><tr><th>Дата</th><th>Посредник</th><th>Товар</th><th class="ns-num">Пар</th><th>Итог</th></tr></thead>
            <tbody>
            @foreach($history as $c)
                <tr>
                    <td class="ns-muted">{{ optional($c->resolved_at)->format('d.m.Y H:i') }}</td>
                    <td>{{ $c->consignee }}</td>
                    <td>{{ $c->item?->display_name ?? '—' }}</td>
                    <td class="ns-num">{{ $c->quantity }}</td>
                    <td>@if($c->status === 'sold')<span class="ns-tag ns-ok">продано</span>@else<span class="ns-tag">возврат</span>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>
@endsection
