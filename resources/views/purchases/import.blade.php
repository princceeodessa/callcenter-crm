@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Поставка — загрузка таблицы</h4>
            <div class="text-muted small">каждая строка станет карточкой закупки в стадии «В пути»; на склад заводится отдельной кнопкой</div>
        </div>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchases.kanban') }}">← К закупкам</a>
    </div>

    @if ($errors->any())
        <div class="alert alert-danger">
            @foreach ($errors->all() as $error)
                <div>{{ $error }}</div>
            @endforeach
        </div>
    @endif

    @if ($imported === null)
        <div class="card shadow-sm mb-3">
            <div class="card-body">
                <h6 class="mb-2">📗 Файл Excel (.xlsx)</h6>
                <div class="text-muted small mb-3">
                    Формат листа (5 столбцов): <b>Название</b> · <b>Размер</b> · <b>Кол-во</b> · <b>Артикул</b> · <b>Сумма</b>
                    (стоимость строки — при кол-ве &gt; 1 делится на кол-во, в закупке хранится цена за пару).
                    Первая строка-заголовок пропускается автоматически. Один и тот же артикул — один и тот же товар,
                    даже если название в разных строках написано по-разному.
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
    @else
        <div class="alert alert-success d-flex align-items-center justify-content-between flex-wrap gap-2">
            <div>Загружено позиций: <b>{{ $imported->count() }}</b>. Карточки созданы в стадии «В пути».</div>
            <a class="btn btn-sm btn-outline-success" href="{{ route('purchases.import.form') }}">Загрузить ещё файл</a>
        </div>

        <div class="card shadow-sm mb-3">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th>Бренд / модель</th>
                            <th>Размер</th>
                            <th>Кол-во</th>
                            <th>Артикул</th>
                            <th>Цена за пару</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($imported as $purchase)
                            <tr>
                                <td><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->brand }} {{ $purchase->model }}</a></td>
                                <td>{{ $purchase->size }}</td>
                                <td>{{ $purchase->quantity }}</td>
                                <td class="text-muted">{{ $purchase->article ?: '—' }}</td>
                                <td>{{ $purchase->cost !== null ? number_format((float) $purchase->cost, 0, ',', ' ').' ₽' : '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                <div class="text-muted small">Когда поставка физически пришла — примите её на склад одной кнопкой.</div>
                <form method="POST" action="{{ route('purchases.receiveBatch') }}"
                      onsubmit="return confirm('Принять {{ $imported->count() }} поз. на склад? Остатки на складе увеличатся.');">
                    @csrf
                    @foreach ($imported as $purchase)
                        <input type="hidden" name="purchase_ids[]" value="{{ $purchase->id }}">
                    @endforeach
                    <button type="submit" class="btn btn-primary">📥 Принять всё на склад</button>
                </form>
            </div>
        </div>
    @endif
@endsection
