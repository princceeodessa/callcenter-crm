@extends('layouts.sneakers')
{{-- «Что заказать» — подсказки закупок по скорости продаж (кнопка на складе). Каркас «Нордика», расчёт прежний:
     WarehouseController::reorderSuggestions. --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $prio = ['high' => ['ns-bad', 'Срочно'], 'mid' => ['ns-warn', 'Скоро'], 'low' => ['ns-ok', 'Норма']];
    $counts = collect($suggestions)->countBy('priority');
    $total = (int) collect($suggestions)->sum('suggested');
@endphp

@section('title', 'Что заказать')
@section('ns_sub', 'по продажам за 90 дней + запас на неделю')
@section('content')
<div class="ns-rec-bar">
    <span class="ns-muted">Прогноз на</span>
    <div class="ns-seg" role="group" aria-label="Горизонт прогноза">
        @foreach([7, 14, 30, 60, 90] as $d)
            <a href="{{ route('warehouse.reorder', ['days' => $d]) }}" @class(['ns-on' => $days === $d])>{{ $d }} дн.</a>
        @endforeach
    </div>
</div>
<div class="ns-kpis">
    <div class="ns-kpi"><div class="ns-kl">Позиций заказать</div><div class="ns-kv">{{ count($suggestions) }}</div><div class="ns-kd">на {{ $days }} дн. вперёд</div></div>
    <div class="ns-kpi @if($counts->get('high')) ns-warn @endif"><div class="ns-kl">Срочно</div><div @class(['ns-kv', 'ns-dn' => $counts->get('high')])>{{ (int) $counts->get('high') }}</div><div class="ns-kd">хватит меньше чем на 7 дней</div></div>
    <div class="ns-kpi"><div class="ns-kl">Скоро</div><div class="ns-kv">{{ (int) $counts->get('mid') }}</div><div class="ns-kd">хватит на 7–21 день</div></div>
    <div class="ns-kpi"><div class="ns-kl">Пар заказать</div><div class="ns-kv ns-up">{{ number_format($total, 0, ',', ' ') }}</div><div class="ns-kd">всего по списку</div></div>
</div>

<div class="ns-panel" style="overflow:hidden">
    @if(empty($suggestions))
        <div class="ns-empty">{!! $ui::icon('bulb') !!}<b>Заказывать нечего</b>За 90 дней продаж не было или всех позиций хватит с запасом</div>
    @else
        <table class="ns-tbl">
            <thead><tr>
                <th></th><th>Позиция</th><th class="ns-num">Продано за 90 дн.</th><th class="ns-num">Пар в день</th>
                <th class="ns-num">Остаток</th><th class="ns-num">Хватит на</th><th class="ns-num">Заказать</th>
            </tr></thead>
            <tbody>
            @foreach($suggestions as $s)
                <tr>
                    <td><span class="ns-tag {{ $prio[$s['priority']][0] }}">{{ $prio[$s['priority']][1] }}</span></td>
                    <td class="ns-tname">{{ $s['name'] }}</td>
                    <td class="ns-num">{{ $s['sold_90d'] }}</td>
                    <td class="ns-num">{{ number_format($s['per_day'], 2, ',', '') }}</td>
                    <td @class(['ns-num', 'ns-dn' => $s['available'] <= 0])>{{ $s['available'] }}</td>
                    <td class="ns-num">{{ $s['days_left'] === null ? '∞' : max(0, $s['days_left']) }} дн.</td>
                    <td class="ns-num"><b>{{ $s['suggested'] }} пар</b></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

<div class="ns-note">
    <b>Как считаем:</b> средняя скорость продаж позиции за 90 дней × выбранный горизонт, плюс недельный запас, минус текущий остаток.
    «Срочно» — остатка хватит меньше чем на 7 дней, «Скоро» — на 7–21 день, «Норма» — дольше.
</div>
@endsection
