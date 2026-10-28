@extends('layouts.sneakers')
{{--
    Закупки — канбан в каркасе «Нордика» (прототип: PAGES.purchases, снимок 09). Этапы закупок, «В один ряд / С переносом», поиск,
    перетаскивание карточек между этапами (прежний маршрут purchases.move: этап «Получено / На складе» заводит пары на склад),
    «В пути», «Загрузить поставку», «+ Закупка» — окно как в прототипе. Только руководителю (закупочные цены).
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $colors = ['#3f72ff', '#17b3c3', '#8a5cf6', '#f0a526', '#1fae55', 'var(--ink)'];
    $inTransit = $ui::inTransitPairs(auth()->user());
@endphp

@section('title', 'Закупки')
@section('ns_sub', '— канбан')

@section('ns_view')
@if(session('status') || $errors->any())<div class="ns-board-flash">@include('partials.ns-flash')</div>@endif
<div class="ns-toolbar">
    <div class="ns-search" style="min-width:260px">{!! $ui::icon('search', 'ns-sm') !!}<input id="pq" placeholder="Модель, бренд, артикул" value="{{ $q }}" autocomplete="off" data-scan="own" aria-label="Поиск закупок"></div>
    <div class="ns-seg" id="pwSeg"><button type="button" data-w="0">В один ряд</button><button type="button" data-w="1">С переносом</button></div>
    <span class="ns-vsp"></span>
    <a class="ns-btn" href="{{ route('purchases.inTransit') }}">{!! $ui::icon('truck', 'ns-sm') !!}В пути@if($inTransit) · {{ number_format($inTransit, 0, ',', ' ') }}@endif</a>
    <a class="ns-btn" href="{{ route('purchases.import.form') }}">{!! $ui::icon('up', 'ns-sm') !!}Загрузить поставку</a>
    <button class="ns-btn ns-primary" type="button" data-new-purchase>{!! $ui::icon('plus', 'ns-sm') !!}Закупка</button>
</div>
@if($stages->isEmpty())
    <div class="ns-page"><div class="ns-flash ns-warn">Стадии закупок не настроены. Запустите сидер кроссовочного пространства.</div></div>
@endif
<div class="ns-view"><div class="ns-board" id="pboard">
    @foreach($stages as $i => $stage)
        @php
            $items = $purchasesByStage[$stage->id] ?? collect();
            $c = $colors[$i % count($colors)];
        @endphp
        <div class="ns-col">
            <div class="ns-col-h"><span class="ns-col-dot" style="background:{{ $c }}"></span><span class="ns-col-t">{{ $stage->name }}</span><span class="ns-col-n" data-count="{{ $stage->id }}">{{ $items->count() }} шт.</span><button class="ns-col-add" type="button" data-new-purchase="{{ $stage->id }}" title="Добавить закупку">+</button></div>
            @if($stage->is_stock_in)<div class="ns-col-note">Сюда — пары заводятся на склад</div>@endif
            <div class="ns-col-body" data-stage-id="{{ $stage->id }}">
                @foreach($items as $p)
                    @php
                        $name = trim(collect([$p->brand, $p->model])->filter()->implode(' ')) ?: $p->title;
                        $search = mb_strtolower(implode(' ', array_filter([$p->title, $p->brand, $p->model, $p->article, $p->supplier, $p->size, $p->responsible?->name])));
                    @endphp
                    <div class="ns-card" data-purchase-id="{{ $p->id }}" data-search="{{ $search }}" style="--src:{{ $c }}">
                        <div class="ns-c-top"><span class="ns-src" style="--src:{{ $c }}">{{ $ui::brandOf($name) }}</span>@if($p->is_white)<span class="ns-tag ns-ok" title="Заказано в белую">белая</span>@endif<span class="ns-cid">@if($p->size)р. {{ $p->size }}@endif</span></div>
                        <a class="ns-c-title ns-link" href="{{ route('purchases.show', $p) }}">{{ $p->title }}</a>
                        <div class="ns-c-foot">
                            <div class="ns-c-row"><span class="ns-k">Пар:</span><b>{{ (int) $p->quantity }}</b>@if($p->cost !== null)<span class="ns-k" style="margin-left:auto">Закупка:</span><b>{{ $rub($p->cost) }}</b>@endif</div>
                            @if($p->supplier || $p->responsible)<div class="ns-c-row">@if($p->supplier)<span class="ns-k">{{ $p->supplier }}</span>@endif @if($p->responsible)<span style="margin-left:auto">{{ $p->responsible->name }}</span>@endif</div>@endif
                        </div>
                    </div>
                @endforeach
                <div class="ns-col-empty" @if($items->isNotEmpty()) hidden @endif>Пусто — перетащите сюда</div>
            </div>
        </div>
    @endforeach
</div></div>

<template id="npuTpl">
    <form method="POST" action="{{ route('purchases.store') }}">
        @csrf
        <input type="hidden" name="back" value="kanban">
        <div class="ns-m-head"><div class="ns-mt">Новая закупка</div><button class="ns-m-x" type="button" data-ns-close aria-label="Закрыть">{!! $ui::icon('x') !!}</button></div>
        <div class="ns-m-body">
            <div class="ns-field"><label>Модель</label><input name="title" required maxlength="255" placeholder="SALOMON XT-6"></div>
            <div class="ns-frow"><div class="ns-field"><label>Размер</label><input name="size" maxlength="50" placeholder="43"></div><div class="ns-field"><label>Пар</label><input name="quantity" type="number" min="1" value="1"></div></div>
            <div class="ns-frow"><div class="ns-field"><label>Закупка за пару, ₽</label><input name="cost" type="number" min="0" step="0.01"></div>
                <div class="ns-field"><label>Этап</label><select name="purchase_stage_id">@foreach($stages as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select></div></div>
            <div class="ns-note" style="margin-top:0">Бренд, поставщик, артикул и «в белую» — в карточке закупки после создания (она откроется по клику).</div>
        </div>
        <div class="ns-m-foot"><button class="ns-btn" type="button" data-ns-close>Отмена</button><button class="ns-btn ns-primary" type="submit">Добавить</button></div>
    </form>
</template>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(() => {
    const board = document.getElementById('pboard'), q = document.getElementById('pq');
    const MOVE = @json(route('purchases.move', '__ID__'));
    // вид доски запоминается (как раньше — purchasesKanbanMode)
    let wrap = false;
    try { wrap = localStorage.getItem('purchasesKanbanMode') === 'wrap'; } catch (e) {}
    const paintWrap = () => {
        board.classList.toggle('ns-wrap', wrap);
        document.querySelectorAll('#pwSeg button').forEach((b) => b.classList.toggle('ns-on', (b.dataset.w === '1') === wrap));
        try { localStorage.setItem('purchasesKanbanMode', wrap ? 'wrap' : 'row'); } catch (e) {}
    };
    document.querySelectorAll('#pwSeg button').forEach((b) => b.addEventListener('click', () => { wrap = b.dataset.w === '1'; paintWrap(); }));
    paintWrap();

    const count = (body) => {
        const cards = [...body.querySelectorAll('.ns-card')].filter((c) => !c.hidden);
        document.querySelector('[data-count="' + body.dataset.stageId + '"]').textContent = cards.length + ' шт.';
        body.querySelector('.ns-col-empty').hidden = cards.length > 0;
    };
    const filter = () => {
        const needle = q.value.trim().toLowerCase();
        document.querySelectorAll('.ns-col-body').forEach((body) => {
            body.querySelectorAll('.ns-card').forEach((c) => { c.hidden = !!needle && !c.dataset.search.includes(needle); });
            count(body);
        });
    };
    q.addEventListener('input', filter);
    if (q.value) filter();

    // перетаскивание: этап сохраняется сразу; не вышло — карточка возвращается
    document.querySelectorAll('.ns-col-body').forEach((body) => new Sortable(body, {
        group: 'purchases', animation: 150, draggable: '.ns-card', ghostClass: 'ns-dragging',
        onStart: () => document.querySelectorAll('.ns-col-body').forEach((b) => b.classList.add('ns-drop')),
        onEnd: async (evt) => {
            document.querySelectorAll('.ns-col-body').forEach((b) => b.classList.remove('ns-drop'));
            if (evt.from === evt.to) return;
            const card = evt.item, to = evt.to.dataset.stageId;
            count(evt.from); count(evt.to);
            card.style.setProperty('--src', evt.to.closest('.ns-col').querySelector('.ns-col-dot').style.background);
            try {
                const r = await ns.post(MOVE.replace('__ID__', card.dataset.purchaseId), { to_stage_id: +to });
                const j = await r.json().catch(() => null);
                if (!r.ok || !j || !j.ok) throw new Error((j && j.message) || 'Ошибка ' + r.status);
                ns.toast(card.querySelector('.ns-c-title').textContent.trim() + ' → ' + evt.to.closest('.ns-col').querySelector('.ns-col-t').textContent);
            } catch (e) {
                evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                count(evt.from); count(evt.to);
                ns.toast('Не удалось перенести: ' + e.message + '. Обновите страницу.');
            }
        },
    }));

    // «+ Закупка» — окно прототипа (openNewPurchase); «+» в колонке — сразу с её этапом
    document.querySelectorAll('[data-new-purchase]').forEach((b) => b.addEventListener('click', () => {
        const m = ns.openModal(document.getElementById('npuTpl').innerHTML, 520);
        if (b.dataset.newPurchase) m.querySelector('[name="purchase_stage_id"]').value = b.dataset.newPurchase;
        m.querySelector('[name="title"]').focus();
    }));
})();
</script>
@endpush
