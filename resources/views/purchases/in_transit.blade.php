@extends('layouts.app')

@push('styles')
<style>
    .it-hero{ display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:.7rem; margin-bottom:1rem; }
    .it-stat{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:16px;
              padding:.75rem 1rem; box-shadow:var(--crm-shadow); }
    .it-stat .l{ font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--crm-muted); font-weight:600; }
    .it-stat .v{ font-size:1.6rem; font-weight:800; line-height:1.15; letter-spacing:-.02em; }
    .it-bar{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:16px;
             padding:.7rem 1rem; box-shadow:var(--crm-shadow); margin-bottom:1rem;
             display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:.6rem; }
</style>
@endpush

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
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">🏬 Склад</a>
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

<div class="it-hero">
    <div class="it-stat"><div class="l">Пар в пути</div><div class="v">{{ $totalPairs }}</div></div>
    <div class="it-stat"><div class="l">Позиций</div><div class="v">{{ $purchases->count() }}</div></div>
    <div class="it-stat"><div class="l">Расцветок</div><div class="v">{{ $articlesCount }}</div></div>
    <div class="it-stat"><div class="l">Брендов</div><div class="v">{{ $brandsCount }}</div></div>
    <div class="it-stat"><div class="l">Сумма · по закупке</div><div class="v">{{ $money($totalCost) }} ₽</div></div>
    <div class="it-stat"><div class="l">Средняя пара</div><div class="v">{{ $money($avgPairCost) }} ₽</div></div>
    <div class="it-stat">
        <div class="l">Ввоз в белую</div>
        <div class="v" @if($whitePairs > 0) style="color:#10b981" @endif>{{ $whitePairs }}<span class="text-muted" style="font-size:.9rem; font-weight:600"> / {{ $totalPairs }}</span></div>
    </div>
</div>

@if ($q !== '')
    <div class="text-muted small mb-3">
        Показан результат поиска «{{ $q }}» — {{ $totalPairs }} из {{ $grandTotalPairs }} пар в пути.
    </div>
@endif

@if ($purchases->isEmpty())
    <div class="card shadow-sm"><div class="card-body text-muted small text-center py-4">
        {{ $q !== '' ? 'По этому запросу ничего не найдено.' : 'Сейчас в пути ничего нет — всё принято на склад.' }}
    </div></div>
@else
    <form method="POST" action="{{ route('purchases.receiveBatch') }}" id="receiveForm">
        @csrf

        <div class="it-bar">
            <div class="small" id="selectionSummary">Отметьте пары галочками в списке ниже</div>
            <div class="d-flex gap-2 flex-wrap align-items-center">
                {{-- Пометка «в белую» шлёт ту же форму (тот же выбор) другому маршруту через formaction. --}}
                <span class="text-muted small d-none d-lg-inline">Ввоз в белую:</span>
                <div class="btn-group btn-group-sm">
                    <button type="submit" class="btn btn-outline-success it-white-btn" id="markWhiteOn"
                            formaction="{{ route('purchases.markWhite') }}" name="white" value="1" disabled>
                        🤍 Отметить
                    </button>
                    <button type="submit" class="btn btn-outline-secondary it-white-btn" id="markWhiteOff"
                            formaction="{{ route('purchases.markWhite') }}" name="white" value="0" disabled>
                        снять
                    </button>
                </div>
                <span class="text-muted d-none d-lg-inline">·</span>
                <button type="submit" class="btn btn-primary" id="receiveSelected" disabled
                        onclick="return confirm('Принять выбранные позиции на склад? Остатки увеличатся.');">
                    📥 Принять выбранные
                </button>
                @if ($q !== '')
                    {{-- При активном поиске «всё» = всё найденное: иначе легко принять всю
                         поставку, имея на экране пару строк. Отмечаем видимое и шлём как обычный выбор. --}}
                    <button type="submit" class="btn btn-outline-primary" id="receiveFound"
                            data-confirm="Принять на склад всё найденное по запросу «{{ $q }}» ({{ $totalPairs }} пар)? Остатки увеличатся.">
                        📥 Принять всё найденное ({{ $totalPairs }} пар)
                    </button>
                @else
                    <button type="submit" class="btn btn-outline-primary" name="scope" value="all"
                            onclick="return confirm('Принять на склад ВСЁ, что в пути ({{ $grandTotalPairs }} пар)? Остатки увеличатся.');">
                        📥 Принять всё ({{ $grandTotalPairs }} пар)
                    </button>
                @endif
            </div>
        </div>

        <div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
            <div class="d-flex gap-2 flex-wrap align-items-center">
                <input type="search" class="form-control form-control-sm" name="q" form="searchForm" value="{{ $q }}"
                       placeholder="модель, артикул, размер" style="min-width: 280px;">
                <button type="submit" form="searchForm" class="btn btn-sm btn-primary">Найти</button>
                @if ($q !== '')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchases.inTransit') }}">Сбросить</a>
                @endif
            </div>
        </div>

        <div class="card shadow-sm mb-4">
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
                            <th>Ввоз</th>
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
                                <td>
                                    @if ($purchase->is_white)
                                        <span class="badge text-bg-success" title="Заказано в белую">белая</span>
                                    @else
                                        <span class="text-muted small">—</span>
                                    @endif
                                </td>
                                <td class="text-muted small">{{ $purchase->stage?->name }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </form>

    {{-- Поиск отдельной формой: иначе он ушёл бы в POST приёмки вместе с галочками. --}}
    <form method="GET" action="{{ route('purchases.inTransit') }}" id="searchForm"></form>
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
    const whiteButtons = Array.from(form.querySelectorAll('.it-white-btn'));
    const isFiltered = @json($q !== '');
    const money = (v) => new Intl.NumberFormat('ru-RU').format(Math.round(v));

    const refresh = () => {
        const picked = rows.filter((r) => r.checked);
        const pairs = picked.reduce((sum, r) => sum + Number(r.dataset.qty || 0), 0);
        const cost = picked.reduce((sum, r) => sum + Number(r.dataset.cost || 0), 0);

        summary.textContent = picked.length === 0
            ? 'Отметьте пары галочками в списке ниже'
            : `Выбрано: ${picked.length} поз. · ${pairs} пар · на ${money(cost)} ₽`;
        summary.classList.toggle('text-muted', picked.length === 0);
        summary.classList.toggle('fw-semibold', picked.length > 0);
        receiveSelected.disabled = picked.length === 0;
        whiteButtons.forEach((b) => { b.disabled = picked.length === 0; });
        if (checkAll) {
            checkAll.checked = picked.length > 0 && picked.length === rows.length;
            checkAll.indeterminate = picked.length > 0 && picked.length < rows.length;
        }
    };

    // «Принять всё найденное» — отмечаем видимые строки и отправляем их как обычный выбор.
    // Подтверждение здесь же, а не в inline-onclick: иначе при отмене галочки всё равно проставятся.
    const receiveFound = document.getElementById('receiveFound');
    if (receiveFound) {
        receiveFound.addEventListener('click', (event) => {
            if (! window.confirm(receiveFound.dataset.confirm)) {
                event.preventDefault();
                return;
            }
            rows.forEach((r) => { r.checked = true; });
            refresh();
        });
    }

    // Пометка «в белую»: подтверждение с реальными числами выбранного.
    whiteButtons.forEach((button) => {
        button.addEventListener('click', (event) => {
            const picked = rows.filter((r) => r.checked);
            const pairs = picked.reduce((sum, r) => sum + Number(r.dataset.qty || 0), 0);
            const text = button.value === '1'
                ? `Отметить как ввоз в белую: ${picked.length} поз. (${pairs} пар)?`
                : `Снять пометку «в белую» с ${picked.length} поз. (${pairs} пар)?`;
            if (! window.confirm(text)) {
                event.preventDefault();
            }
        });
    });

    // Когда выбрано вообще всё и поиск не активен — шлём scope=all вместо сотен id:
    // PHP по умолчанию режет форму на max_input_vars (1000), и на крупной поставке
    // часть строк молча не дошла бы до сервера.
    form.addEventListener('submit', (event) => {
        const allPicked = rows.length > 0 && rows.every((r) => r.checked);
        // Кнопка «Принять всё» уже несёт scope сама — второй раз не добавляем.
        const submitterHasScope = event.submitter && event.submitter.name === 'scope';
        if (! allPicked || isFiltered || submitterHasScope) {
            return;
        }
        rows.forEach((r) => { r.checked = false; });
        const scope = document.createElement('input');
        scope.type = 'hidden';
        scope.name = 'scope';
        scope.value = 'all';
        form.appendChild(scope);
    });

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
