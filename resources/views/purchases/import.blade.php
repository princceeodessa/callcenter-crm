@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Поставка — загрузка таблицы</h4>
            <div class="text-muted small">каждая строка станет карточкой закупки в стадии «В пути»; на склад заводится отдельной кнопкой</div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-primary" href="{{ route('purchases.inTransit') }}">🚚 Что в пути</a>
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchases.kanban') }}">← К закупкам</a>
        </div>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <h6 class="mb-2">📗 Файл Excel (.xlsx)</h6>
            <div class="text-muted small mb-3">
                Колонки распознаются <b>по заголовкам</b>, поэтому подойдёт и таблица поставщика со своей калькуляцией
                (юани, курс, пошлина, НДС) — лишние столбцы просто игнорируются, порядок не важен.
                <div class="mt-2">
                    Нужны: <b>Название</b> и <b>Размер</b> (или «Размер (EU)»). Желательно: <b>Артикул</b>,
                    <b>Количество</b> и цена — берётся <b>«Цена с НДС»</b>, иначе «Сумма (с НДС)» ÷ количество,
                    иначе «Закупочная цена».
                </div>
                <div class="mt-2">
                    Строки без названия или размера (итоги, примечания под таблицей) пропускаются.
                    Один и тот же артикул — один и тот же товар, даже если название в строках написано по-разному.
                    Размеры вида «44 1/2» и «44.5» считаются одним размером, «43 1/3» — отдельным от «43».
                </div>
            </div>
            <form method="POST" action="{{ route('purchases.import.run') }}" enctype="multipart/form-data" class="row g-2 align-items-end">
                @csrf
                <div class="col-lg-8">
                    <input type="file" name="xlsx" accept=".xlsx" class="form-control" required>
                </div>
                <div class="col-lg-4">
                    <button type="submit" class="btn btn-success w-100">Загрузить</button>
                </div>
            </form>
        </div>
    </div>
@endsection
