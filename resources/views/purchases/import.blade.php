@extends('layouts.sneakers')
{{-- «Загрузить поставку» — таблица поставщика (.xlsx) → карточки закупок в «В пути». Каркас «Нордика», только руководителю. --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
@endphp
@section('title', 'Загрузить поставку')
@section('ns_sub', 'таблица поставщика → «В пути»')
@section('ns_actions')
    <a class="ns-btn" href="{{ route('purchases.inTransit') }}">{!! $ui::icon('truck', 'ns-sm') !!}Что в пути</a>
    <a class="ns-btn" href="{{ route('purchases.kanban') }}">← К закупкам</a>
@endsection

@section('content')
<div class="ns-panel ns-panel-pad ns-pf-panel">
    <div class="ns-sec-h">{!! $ui::icon('report') !!}Файл Excel (.xlsx)</div>
    <div class="ns-imp-t">
        Колонки распознаются <b>по заголовкам</b> — подойдёт и таблица поставщика со своей калькуляцией (юани, курс, пошлина, НДС): лишние
        столбцы игнорируются, порядок не важен.<br>
        Нужны: <b>Название</b> и <b>Размер</b> (или «Размер (EU)»). Желательно: <b>Артикул</b>, <b>Количество</b> и цена — берётся
        <b>«Цена с НДС»</b>, иначе «Сумма (с НДС)» ÷ количество, иначе «Закупочная цена».<br>
        Строки без названия или размера (итоги, примечания) пропускаются. Один артикул — один товар, даже если название написано по-разному.
        «44 1/2» и «44.5» — один размер, «43 1/3» — отдельный от «43».
    </div>
    <form method="POST" action="{{ route('purchases.import.run') }}" enctype="multipart/form-data" class="ns-pf-upload">
        @csrf
        <div class="ns-field" style="flex:1;margin:0"><input type="file" name="xlsx" accept=".xlsx" required></div>
        <button type="submit" class="ns-btn ns-primary">{!! $ui::icon('up', 'ns-sm') !!}Загрузить</button>
    </form>
    <div class="ns-note">Каждая строка станет карточкой закупки в этапе «В пути»; на склад пары заводятся на странице «В пути».</div>
</div>
@endsection
