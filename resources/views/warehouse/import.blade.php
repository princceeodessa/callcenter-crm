@extends('layouts.sneakers')
{{-- «Импорт» склада пачкой — из Excel или из текста (кнопка на складе). Каркас «Нордика», разбор прежний:
     WarehouseController::importRun. --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
@endphp

@section('title', 'Импорт товаров')
@section('ns_sub', 'пачкой — из Excel или списком')

@section('content')
<div class="ns-grid2 ns-imp">
    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">{!! $ui::icon('report') !!}Из Excel (.xlsx)</div>
        <p class="ns-imp-t">Колонки: <b>Название</b> («Nike Dunk low retro») · <b>Размер</b> · <b>Кол-во</b> · <b>Артикул</b> («DV0833 800», необязателен).
            Строка-заголовок пропускается сама. Каждая расцветка (артикул) станет отдельной карточкой.</p>
        <form method="POST" action="{{ route('warehouse.import.run') }}" enctype="multipart/form-data">
            @csrf
            <div class="ns-field"><label>Файл</label><input type="file" name="xlsx" accept=".xlsx" required></div>
            <label class="ns-radio"><input type="radio" name="mode" value="set" checked><span><b>Файл — полный срез остатков</b>: количества станут как в файле (повторная загрузка не задваивает)</span></label>
            <label class="ns-radio"><input type="radio" name="mode" value="add"><span><b>Прибавить</b> к текущим остаткам (новая поставка)</span></label>
            <div class="ns-imp-f"><span class="ns-muted">Позиции, которых нет в файле, не трогаются.</span><button class="ns-btn ns-primary" type="submit">{!! $ui::icon('up', 'ns-sm') !!}Импортировать</button></div>
        </form>
    </div>

    <div class="ns-panel ns-panel-pad">
        <div class="ns-sec-h">{!! $ui::icon('tasks') !!}Списком</div>
        <form method="POST" action="{{ route('warehouse.import.run') }}">
            @csrf
            <div class="ns-field"><label>Список товаров</label>
                <textarea name="raw" rows="12" class="ns-mono" required placeholder="NIKE DUNK LOW PINK VELVET GS - рр 37&#10;PUMA LAMELO BALL LAFRANCÉ AMOUR SLIME- рр 42,43&#10;NEW BALANCE 237 SOUR GRAPE LEMON WOMEN'S-36,36.5">{{ old('raw') }}</textarea>
            </div>
            <div class="ns-frow">
                <div class="ns-field"><label>Пар каждого размера</label><input type="number" name="quantity" min="1" max="1000" value="{{ old('quantity', 1) }}"></div>
                <div class="ns-field" style="display:flex;align-items:flex-end"><button class="ns-btn ns-primary" type="submit" style="width:100%;justify-content:center;height:42px">{!! $ui::icon('up', 'ns-sm') !!}Импортировать</button></div>
            </div>
            <ul class="ns-imp-l">
                <li><b>Строкой:</b> «NIKE DUNK LOW - 41,42.5,43» — пар каждого размера из поля выше.</li>
                <li><b>Блоками</b> через пустую строку: название · размеры «42,43(2)» (в скобках пар, без скобок 1) · артикул.</li>
                <li>Склейки без запятой понимаются: «38(2)37.5».</li>
                <li>Первое слово — <b>бренд</b>; NEW BALANCE — из двух слов; «W Nike …» тоже понимается.</li>
                <li>Позиция уже есть — количество прибавится.</li>
            </ul>
        </form>
    </div>
</div>
@endsection
