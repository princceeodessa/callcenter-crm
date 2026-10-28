@extends('layouts.sneakers')
{{--
    «Продажи · канбан» кроссовок — каркас «Нордика» (прототип: PAGES.deals, снимок 11). Этапы сделок, поиск, «В один ряд / С переносом»,
    «Завершённые», «Показать нецелевое», «+ Сделка»; перетаскивание — прежний маршрут deals.move: перенос в «Продано» / «Отказ»
    закрывает сделку, карточка уходит в «Завершённые». Карточка: источник, клиент, модель и размер со склада.
    Фильтра «Вчера / Сегодня / Завтра» нет: на сайте он работает только для этапов колл-центра (квалификация / замер).
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $palette = ['#3f72ff', '#17b3c3', '#f0a526', '#8a5cf6', '#1fae55', '#e5484d'];
    $stageColor = function ($stage, int $i) use ($palette) {
        $n = mb_strtolower($stage->name);
        return str_contains($n, 'продан') ? '#1fae55' : (str_contains($n, 'отказ') ? '#e5484d' : $palette[$i % count($palette)]);
    };
    $params = fn (array $extra) => array_filter(array_merge(['q' => $q !== '' ? $q : null, 'show_spam' => $showSpam ? 1 : null], $extra), fn ($v) => $v !== null && $v !== '');
@endphp

@section('title', 'Продажи')
@section('ns_sub', '— сделки, канбан')

@section('ns_view')
@if(session('status') || $errors->any())<div class="ns-board-flash">@include('partials.ns-flash')</div>@endif
<div class="ns-toolbar">
    <div class="ns-search" style="min-width:260px">{!! $ui::icon('search', 'ns-sm') !!}<input id="dq" placeholder="Клиент, телефон, модель" value="{{ $q }}" autocomplete="off" data-scan="own" aria-label="Поиск сделок"></div>
    <div class="ns-seg" id="dwSeg"><button type="button" data-w="0">В один ряд</button><button type="button" data-w="1">С переносом</button></div>
    <span class="ns-vsp"></span>
    <a class="ns-chipf" href="{{ route('deals.closed') }}"><span class="ns-sw"></span>Завершённые</a>
    <a @class(['ns-chipf', 'ns-on' => $showSpam]) href="{{ route('deals.kanban', $params(['show_spam' => $showSpam ? null : 1])) }}"><span class="ns-sw"></span>Показать нецелевое</a>
    <span class="ns-grow"></span>
    <a class="ns-btn ns-primary" href="{{ route('deals.create') }}">{!! $ui::icon('plus', 'ns-sm') !!}Сделка</a>
</div>
<div class="ns-view"><div class="ns-board" id="dboard">
    @foreach($stages as $i => $stage)
        @php
            $deals = $dealsByStage[$stage->id] ?? collect();
            $c = $stageColor($stage, $i);
            $n = mb_strtolower($stage->name);
            $closing = str_contains($n, 'продан') || str_contains($n, 'отказ');
        @endphp
        <div class="ns-col">
            <div class="ns-col-h"><span class="ns-col-dot" style="background:{{ $c }}"></span><span class="ns-col-t">{{ $stage->name }}</span><span class="ns-col-n" data-count="{{ $stage->id }}">{{ $deals->count() }} шт.</span><a class="ns-col-add" href="{{ route('deals.create', ['stage_id' => $stage->id]) }}" title="Новая сделка в этом этапе">+</a></div>
            @if($closing)<div class="ns-col-note">Перенос сюда закрывает сделку — она уйдёт в «Завершённые». Продажи через «Продажа» закрываются сразу.</div>@endif
            <div class="ns-col-body" data-stage-id="{{ $stage->id }}">
                @foreach($deals as $deal)
                    @php
                        $client = $deal->lead_display_name ?: ($deal->contact?->name ?: 'Клиент');
                        $title = $deal->title_is_custom ? $deal->title : $client;
                        $item = $deal->warehouseItem;
                        $model = $item ? trim($item->brand.' '.$item->model) : '';
                        $attention = (int) ($deal->client_attention_count ?? 0);
                        $search = mb_strtolower(implode(' ', array_filter([$title, $client, $deal->contact?->phone, $deal->responsible?->name, $model, $deal->manual_source])));
                    @endphp
                    <div class="ns-card" data-deal-id="{{ $deal->id }}" data-search="{{ $search }}" style="--src:{{ $c }}">
                        <div class="ns-c-top">{!! $ui::srcChip($deal->manual_source ?: ($deal->lead_source_label !== 'CRM' ? $deal->lead_source_label : '')) !!}@if($attention)<span class="ns-tag ns-ok" title="Клиент ждёт ответа">{{ $attention }}</span>@endif<span class="ns-cid">#{{ $deal->id }}</span></div>
                        <a class="ns-c-title ns-link" href="{{ route('deals.show', $deal) }}">{{ $title }}</a>
                        <div class="ns-c-foot">
                            @if($title !== $client)<div class="ns-c-row"><span class="ns-k">Клиент:</span>{{ $client }}</div>@endif
                            @if($deal->contact?->phone)<div class="ns-c-row"><span class="ns-k">Телефон:</span>{{ $deal->contact->phone }}</div>@endif
                            <div class="ns-c-row"><span class="ns-k">Модель:</span>{{ $model !== '' ? $model : '—' }}</div>
                            <div class="ns-c-row"><span class="ns-k">Размер:</span>{{ $item && $item->size !== '' ? $item->size : '—' }}@if($deal->amount)<b style="margin-left:auto">{{ number_format((float) $deal->amount, 0, ',', ' ') }} ₽</b>@endif</div>
                            @if($deal->responsible)<div class="ns-c-row ns-c-moved"><span class="ns-k">Ответственный:</span>{{ $deal->responsible->name }}</div>@endif
                        </div>
                    </div>
                @endforeach
                <div class="ns-col-empty" @if($deals->isNotEmpty()) hidden @endif>Пусто</div>
            </div>
        </div>
    @endforeach
</div></div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<script>
(() => {
    const board = document.getElementById('dboard'), q = document.getElementById('dq');
    const MOVE = @json(url('/deals')) + '/__ID__/move';
    let wrap = false;
    try { wrap = localStorage.getItem('dealsKanbanMode') === 'wrap'; } catch (e) {}
    const paintWrap = () => {
        board.classList.toggle('ns-wrap', wrap);
        document.querySelectorAll('#dwSeg button').forEach((b) => b.classList.toggle('ns-on', (b.dataset.w === '1') === wrap));
        try { localStorage.setItem('dealsKanbanMode', wrap ? 'wrap' : 'row'); } catch (e) {}
    };
    document.querySelectorAll('#dwSeg button').forEach((b) => b.addEventListener('click', () => { wrap = b.dataset.w === '1'; paintWrap(); }));
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

    // перетаскивание: этап сохраняется сразу; «Продано» / «Отказ» закрывают сделку — карточка уходит с доски
    document.querySelectorAll('.ns-col-body').forEach((body) => new Sortable(body, {
        group: 'deals', animation: 150, draggable: '.ns-card', ghostClass: 'ns-dragging',
        onStart: () => document.querySelectorAll('.ns-col-body').forEach((b) => b.classList.add('ns-drop')),
        onEnd: async (evt) => {
            document.querySelectorAll('.ns-col-body').forEach((b) => b.classList.remove('ns-drop'));
            if (evt.from === evt.to) return;
            const card = evt.item, col = evt.to.closest('.ns-col');
            count(evt.from); count(evt.to);
            card.style.setProperty('--src', col.querySelector('.ns-col-dot').style.background);
            try {
                const r = await ns.post(MOVE.replace('__ID__', card.dataset.dealId), { to_stage_id: +evt.to.dataset.stageId });
                const j = await r.json().catch(() => ({}));
                if (!r.ok || j.ok === false) throw new Error(j.message || 'Ошибка ' + r.status);
                const name = card.querySelector('.ns-c-title').textContent.trim(), stage = col.querySelector('.ns-col-t').textContent;
                if (j.closed) {
                    card.remove();
                    count(evt.to);
                    ns.toast(name + ' → ' + stage + ' · сделка закрыта');
                } else {
                    ns.toast(name + ' → ' + stage);
                }
            } catch (e) {
                evt.from.insertBefore(card, evt.from.children[evt.oldIndex] || null);
                count(evt.from); count(evt.to);
                ns.toast('Не удалось перенести: ' + e.message + '. Обновите страницу.');
            }
        },
    }));
})();
</script>
@endpush
