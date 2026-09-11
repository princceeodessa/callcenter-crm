@extends('layouts.app')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0">🚚 Кроссовки в пути</h4>
        <div class="text-muted small">закуплено, но ещё не заведено на склад — примите всё или только выбранные пары</div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a class="btn btn-sm btn-outline-success" href="{{ route('purchases.import.form') }}">📦 Загрузить поставку</a>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchases.kanban') }}">← К закупкам</a>
    </div>
</div>

@if (session('status'))
    <div class="alert alert-success py-2 small">{{ session('status') }}</div>
@endif

@if ($errors->any())
    <div class="alert alert-danger py-2 small">
        @foreach ($errors->all() as $error)
            <div>{{ $error }}</div>
        @endforeach
    </div>
@endif

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <form method="GET" action="{{ route('purchases.inTransit') }}" class="d-flex gap-2 flex-wrap align-items-center">
        <input type="search" class="form-control form-control-sm" name="q" value="{{ $q }}"
               placeholder="модель, артикул, размер" style="min-width: 280px;">
        <button type="submit" class="btn btn-sm btn-primary">Найти</button>
        @if ($q !== '')
            <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchases.inTransit') }}">Сбросить</a>
        @endif
    </form>
    <div class="text-muted small">
        В пути: <b>{{ $purchases->count() }}</b> поз. · <b>{{ $totalPairs }}</b> пар · на <b>{{ $money($totalCost) }} ₽</b>
    </div>
</div>

@if ($purchases->isEmpty())
    <div class="card shadow-sm"><div class="card-body text-muted small text-center py-4">
        {{ $q !== '' ? 'По этому запросу ничего не найдено.' : 'Сейчас в пути ничего нет — всё принято на склад.' }}
    </div></div>
@else
    <form method="POST" action="{{ route('purchases.receiveBatch') }}" id="receiveForm">
        @csrf
        <div class="card shadow-sm mb-3">
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width:36px;">
                                <input type="checkbox" class="form-check-input" id="checkAll" title="Выделить все">
                            </th>
                            <th>Бренд / модель</th>
                            <th>Размер</th>
                            <th class="text-end">Пар</th>
                            <th>Артикул</th>
                            <th class="text-end">Себестоимость пары</th>
                            <th>Стадия</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($purchases as $purchase)
                            <tr>
                                <td>
                                    <input type="checkbox" class="form-check-input row-check" name="purchase_ids[]"
                                           value="{{ $purchase->id }}"
                                           data-qty="{{ (int) $purchase->quantity }}"
                                           data-cost="{{ (int) $purchase->quantity * (float) ($purchase->cost ?? 0) }}">
                                </td>
                                <td><a href="{{ route('purchases.show', $purchase) }}">{{ $purchase->brand }} {{ $purchase->model }}</a></td>
                                <td>{{ $purchase->size }}</td>
                                <td class="text-end">{{ (int) $purchase->quantity }}</td>
                                <td class="text-muted small">{{ $purchase->article ?: '—' }}</td>
                                <td class="text-end">{{ $purchase->cost !== null ? $money($purchase->cost).' ₽' : '—' }}</td>
                                <td class="text-muted small">{{ $purchase->stage?->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
            <div class="text-muted small" id="selectionSummary">Ничего не выбрано</div>
            <div class="d-flex gap-2 flex-wrap">
                <button type="submit" class="btn btn-primary" id="receiveSelected" disabled
                        onclick="return confirm('Принять выбранные позиции на склад? Остатки увеличатся.');">
                    📥 Принять выбранные
                </button>
                <button type="submit" class="btn btn-outline-primary" name="scope" value="all"
                        onclick="return confirm('Принять на склад ВСЁ, что в пути ({{ $totalPairs }} пар)? Остатки увеличатся.');">
                    📥 Принять всё ({{ $totalPairs }} пар)
                </button>
            </div>
        </div>
    </form>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('receiveForm');
    if (!form) return;

    const checkAll = document.getElementById('checkAll');
    const rows = Array.from(form.querySelectorAll('.row-check'));
    const summary = document.getElementById('selectionSummary');
    const receiveSelected = document.getElementById('receiveSelected');
    const money = (v) => new Intl.NumberFormat('ru-RU').format(Math.round(v));

    const refresh = () => {
        const picked = rows.filter((r) => r.checked);
        const pairs = picked.reduce((sum, r) => sum + Number(r.dataset.qty || 0), 0);
        const cost = picked.reduce((sum, r) => sum + Number(r.dataset.cost || 0), 0);

        summary.textContent = picked.length === 0
            ? 'Ничего не выбрано'
            : `Выбрано: ${picked.length} поз. · ${pairs} пар · на ${money(cost)} ₽`;
        receiveSelected.disabled = picked.length === 0;
        if (checkAll) {
            checkAll.checked = picked.length > 0 && picked.length === rows.length;
            checkAll.indeterminate = picked.length > 0 && picked.length < rows.length;
        }
    };

    rows.forEach((r) => r.addEventListener('change', refresh));
    if (checkAll) {
        checkAll.addEventListener('change', () => {
            rows.forEach((r) => { r.checked = checkAll.checked; });
            refresh();
        });
    }
    refresh();
})();
</script>
@endpush
