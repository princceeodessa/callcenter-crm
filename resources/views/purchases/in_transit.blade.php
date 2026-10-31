@extends('layouts.sneakers')
{{--
    «В пути» — закуплено, но ещё не заведено на склад (кнопка на складе и на канбане закупок). Каркас «Нордика» (окно openTransit
    прототипа — здесь страницей): KPI, список с галочками, «Принять выбранные / всё», ввоз «в белую / в серую» (руководителю).
    Продавец принимает поставку, но закупочных цен не видит. Логика выбора прежняя: всё выбранное без поиска уходит как scope=all
    (PHP режет форму на max_input_vars), при поиске «всё» = всё найденное.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
@endphp

@section('title', 'В пути')
@section('ns_sub', 'закуплено, но ещё не на складе')
@section('ns_actions')
    @if($seesCost)
        <a class="ns-btn" href="{{ route('purchases.import.form') }}">{!! $ui::icon('up', 'ns-sm') !!}Загрузить поставку</a>
        <a class="ns-btn" href="{{ route('purchases.kanban') }}">{!! $ui::icon('kanban', 'ns-sm') !!}Закупки</a>
    @endif
@endsection

@section('content')
<div @class(['ns-kpis', 'ns-k6' => $seesCost, 'ns-k5' => ! $seesCost])>
    <div class="ns-kpi"><div class="ns-kl">Пар в пути</div><div class="ns-kv">{{ number_format($totalPairs, 0, ',', ' ') }}</div>@if($q !== '')<div class="ns-kd">из {{ number_format($grandTotalPairs, 0, ',', ' ') }} — по поиску</div>@endif</div>
    <div class="ns-kpi"><div class="ns-kl">Позиций</div><div class="ns-kv">{{ $purchases->count() }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Расцветок</div><div class="ns-kv">{{ $articlesCount }}</div><div class="ns-kd">брендов {{ $brandsCount }}</div></div>
    @if($seesCost)
        <div class="ns-kpi"><div class="ns-kl">По закупке</div><div class="ns-kv">{{ $rub($totalCost) }}</div></div>
        <div class="ns-kpi"><div class="ns-kl">Средняя пара</div><div class="ns-kv">{{ $rub($avgPairCost) }}</div></div>
    @endif
    <div class="ns-kpi"><div class="ns-kl">Белая / серая</div><div class="ns-kv"><span @class(['ns-up' => $whitePairs > 0])>{{ $whitePairs }}</span><span class="ns-muted" style="font-size:16px"> / {{ $totalPairs - $whitePairs }}</span></div></div>
</div>

<div class="ns-panel" style="overflow:hidden">
    <form method="GET" action="{{ route('purchases.inTransit') }}" id="searchForm"></form>
    @if($purchases->isEmpty())
        <div class="ns-panel-pad ns-it-bar"><div class="ns-search" style="min-width:280px">{!! $ui::icon('search', 'ns-sm') !!}<input type="search" name="q" form="searchForm" value="{{ $q }}" placeholder="Модель, артикул, размер" data-scan="own"></div>
            @if($q !== '')<a class="ns-btn ns-sm" href="{{ route('purchases.inTransit') }}">Сбросить</a>@endif</div>
        <div class="ns-empty">{!! $ui::icon('truck') !!}<b>{{ $q !== '' ? 'По этому запросу ничего не найдено' : 'В пути ничего нет' }}</b>{{ $q !== '' ? '' : 'Всё принято на склад' }}</div>
    @else
        <form method="POST" action="{{ route('purchases.receiveBatch') }}" id="receiveForm">
            @csrf
            <div class="ns-panel-pad ns-it-bar">
                <div class="ns-search" style="min-width:260px">{!! $ui::icon('search', 'ns-sm') !!}<input type="search" name="q" form="searchForm" value="{{ $q }}" placeholder="Модель, артикул, размер — Enter" data-scan="own"></div>
                @if($q !== '')<a class="ns-btn ns-sm" href="{{ route('purchases.inTransit') }}">Сбросить</a>@endif
                <span class="ns-it-sum ns-muted" id="selectionSummary">Отметьте пары галочками</span>
                <span class="ns-grow"></span>
                @if($seesCost)
                    {{-- пометка канала шлёт ту же форму (тот же выбор) другому маршруту --}}
                    <div class="ns-seg ns-it-white"><button type="submit" class="it-white-btn" formaction="{{ route('purchases.markWhite') }}" name="white" value="1" disabled>в белую</button><button type="submit" class="it-white-btn" formaction="{{ route('purchases.markWhite') }}" name="white" value="0" disabled>в серую</button></div>
                @endif
                <button type="submit" class="ns-btn ns-primary" id="receiveSelected" disabled onclick="return confirm('Принять выбранные позиции на склад? Остатки увеличатся.');">{!! $ui::icon('inbox', 'ns-sm') !!}Принять выбранные</button>
                @if($q !== '')
                    <button type="submit" class="ns-btn" id="receiveFound" data-confirm="Принять на склад всё найденное по запросу «{{ $q }}» ({{ $totalPairs }} пар)? Остатки увеличатся.">Принять всё найденное ({{ $totalPairs }})</button>
                @else
                    <button type="submit" class="ns-btn" name="scope" value="all" onclick="return confirm('Принять на склад ВСЁ, что в пути ({{ $grandTotalPairs }} пар)? Остатки увеличатся.');">Принять всё ({{ $grandTotalPairs }})</button>
                @endif
            </div>
            <div style="overflow-x:auto"><table class="ns-tbl">
                <thead><tr>
                    <th style="width:40px"><input type="checkbox" class="ns-chk" id="checkAll" title="Выделить все"></th>
                    <th>Модель</th><th>Размер</th><th class="ns-num">Пар</th><th>Артикул</th>@if($seesCost)<th class="ns-num">Закупка</th>@endif<th>Ввоз</th><th>Этап</th>
                </tr></thead>
                <tbody>
                @foreach($purchases as $purchase)
                    <tr>
                        <td><input type="checkbox" class="ns-chk row-check" name="purchase_ids[]" value="{{ $purchase->id }}" data-qty="{{ (int) $purchase->quantity }}" @if($seesCost) data-cost="{{ (int) $purchase->quantity * (float) ($purchase->cost ?? 0) }}" @endif></td>
                        <td class="ns-tname">@if($seesCost)<a class="ns-link" href="{{ route('purchases.show', $purchase) }}">{{ trim($purchase->brand.' '.$purchase->model) ?: $purchase->title }}</a>@else{{ trim($purchase->brand.' '.$purchase->model) ?: $purchase->title }}@endif</td>
                        <td>{{ $purchase->size }}</td>
                        <td class="ns-num">@if((int) $purchase->quantity > 1)<input type="number" class="ns-it-qty" value="{{ (int) $purchase->quantity }}" min="0" max="{{ (int) $purchase->quantity }}" data-full="{{ (int) $purchase->quantity }}" aria-label="Сколько пар пришло" title="Пришла часть — поставьте, сколько пар"><span class="ns-it-part" hidden>из {{ (int) $purchase->quantity }}</span>@else{{ (int) $purchase->quantity }}@endif</td>
                        <td class="ns-muted ns-mono">{{ $purchase->article ?: '—' }}</td>
                        @if($seesCost)<td class="ns-num">{{ $purchase->cost !== null ? $rub($purchase->cost) : '—' }}</td>@endif
                        <td>@if($purchase->is_white)<span class="ns-tag ns-ok">белая</span>@else<span class="ns-tag">серая</span>@endif</td>
                        <td class="ns-muted">{{ $purchase->stage?->name }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </form>
    @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('receiveForm');
    if (!form) return;
    const checkAll = document.getElementById('checkAll');
    const rows = [...form.querySelectorAll('.row-check')];
    const summary = document.getElementById('selectionSummary');
    const receiveSelected = document.getElementById('receiveSelected');
    const whiteButtons = [...form.querySelectorAll('.it-white-btn')];
    const isFiltered = @json($q !== '');
    const seesCost = @json($seesCost);
    const money = (v) => new Intl.NumberFormat('ru-RU').format(Math.round(v));
    // сколько пар принять по строке: пришла часть — число в колонке «Пар» меньше заказанного
    const qtyInput = (r) => r.closest('tr').querySelector('.ns-it-qty');
    const take = (r) => { const i = qtyInput(r); return i ? Math.max(0, Math.min(Number(i.dataset.full), Math.floor(Number(i.value) || 0))) : Number(r.dataset.qty || 0); };
    const refresh = () => {
        const picked = rows.filter((r) => r.checked);
        const pairs = picked.reduce((s, r) => s + take(r), 0);
        const cost = picked.reduce((s, r) => s + Number(r.dataset.cost || 0) / Math.max(1, Number(r.dataset.qty || 1)) * take(r), 0);
        summary.textContent = picked.length === 0 ? 'Отметьте пары галочками' : 'Выбрано: ' + picked.length + ' поз. · ' + pairs + ' пар' + (seesCost ? ' · на ' + money(cost) + ' ₽' : '');
        summary.classList.toggle('ns-muted', picked.length === 0);
        receiveSelected.disabled = picked.length === 0;
        whiteButtons.forEach((b) => { b.disabled = picked.length === 0; });
        checkAll.checked = picked.length > 0 && picked.length === rows.length;
        checkAll.indeterminate = picked.length > 0 && picked.length < rows.length;
    };
    // «Принять всё найденное» — отмечаем видимые строки и шлём как обычный выбор
    document.getElementById('receiveFound')?.addEventListener('click', (e) => {
        if (!confirm(e.currentTarget.dataset.confirm)) { e.preventDefault(); return; }
        rows.forEach((r) => { r.checked = true; });
        refresh();
    });
    whiteButtons.forEach((b) => b.addEventListener('click', (e) => {
        const picked = rows.filter((r) => r.checked), pairs = picked.reduce((s, r) => s + Number(r.dataset.qty || 0), 0);
        if (!confirm((b.value === '1' ? 'Отметить как ввоз В БЕЛУЮ: ' : 'Отметить как ввоз В СЕРУЮ: ') + picked.length + ' поз. (' + pairs + ' пар)?')) e.preventDefault();
    }));
    form.querySelectorAll('.ns-it-qty').forEach((i) => {
        const r = i.closest('tr').querySelector('.row-check');
        i.addEventListener('input', () => {
            const n = take(r);
            i.classList.toggle('ns-it-less', n < Number(i.dataset.full));
            i.nextElementSibling.hidden = n >= Number(i.dataset.full);
            r.checked = n > 0;                                  // поправили число — строка выбрана (0 — снята)
            refresh();
        });
        // Enter здесь — не отправка формы (первой кнопкой формы у руководителя сработала бы пометка ввоза)
        i.addEventListener('keydown', (e) => { if (e.key === 'Enter') { e.preventDefault(); i.blur(); } });
    });
    form.addEventListener('submit', () => {
        form.querySelectorAll('input[name^="receive_qty["]').forEach((h) => h.remove());
        rows.forEach((r) => {
            const i = qtyInput(r);
            if (!i || take(r) >= Number(i.dataset.full)) return;
            const h = document.createElement('input');
            h.type = 'hidden'; h.name = 'receive_qty[' + r.value + ']'; h.value = String(take(r));
            form.appendChild(h);
        });
    });
    // выбрано всё и поиска нет — шлём scope=all вместо сотен id (PHP режет форму на max_input_vars)
    form.addEventListener('submit', (e) => {
        const allPicked = rows.length > 0 && rows.every((r) => r.checked);
        if (!allPicked || isFiltered || (e.submitter && e.submitter.name === 'scope')) return;
        rows.forEach((r) => { r.checked = false; });
        const scope = document.createElement('input');
        scope.type = 'hidden'; scope.name = 'scope'; scope.value = 'all';
        form.appendChild(scope);
    });
    rows.forEach((r) => r.addEventListener('change', refresh));
    checkAll.addEventListener('change', () => { rows.forEach((r) => { r.checked = checkAll.checked; }); refresh(); });
    refresh();
})();
</script>
@endpush
