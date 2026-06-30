@extends('layouts.app')

@push('styles')
    <style>
        .kanban-board{ align-items: start; }
        .kanban-board.mode-row{ display: flex; flex-wrap: nowrap; overflow-x: auto; gap: 1rem; padding-bottom: .25rem; }
        .kanban-board.mode-row .kanban-col{ flex: 0 0 320px; }
        .kanban-board.mode-wrap{ display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); }
        .kanban-list{ min-height: 64px; }
        .kanban-card{ cursor: grab; user-select: none; }
        .kanban-card:active{ cursor: grabbing; }
        .drag-ghost{ opacity: .5; }
        .drag-chosen{ outline: 2px dashed rgba(13,110,253,.6); outline-offset: 2px; }
    </style>
@endpush

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div class="d-flex flex-column gap-2">
            <h4 class="mb-0">Закупки — канбан</h4>
            <form method="GET" action="{{ route('purchases.kanban') }}" class="d-flex gap-2 flex-wrap align-items-center">
                <input type="search" class="form-control form-control-sm" id="kanbanSearch" name="q" value="{{ $q }}"
                       placeholder="Поиск: модель, бренд, артикул, поставщик" style="min-width: 320px;">
                <button type="submit" class="btn btn-sm btn-primary">Применить</button>
                @if($q !== '')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchases.kanban') }}">Сбросить</a>
                @endif
            </form>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="btn-group" role="group">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="kbModeRow">В один ряд</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="kbModeWrap">С переносом</button>
            </div>
            <a class="btn btn-sm btn-success" href="{{ route('purchases.create') }}">+ Закупка</a>
        </div>
    </div>

    @if($stages->isEmpty())
        <div class="alert alert-warning">Стадии закупок не настроены. Запустите сидер кроссовочного пространства.</div>
    @endif

    <div class="kanban-board mode-row" id="kanbanBoard">
        @foreach ($stages as $stage)
            @php($items = $purchasesByStage[$stage->id] ?? collect())
            <div class="card shadow-sm kanban-col">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $stage->name }}</div>
                        <div class="text-muted small">
                            <span class="kanban-count" data-stage-id="{{ $stage->id }}">{{ $items->count() }}</span> шт.
                        </div>
                    </div>
                    <a class="btn btn-sm btn-success" href="{{ route('purchases.create') }}" title="Добавить закупку">+</a>
                </div>

                <div class="card-body d-flex flex-column gap-2 kanban-list" data-stage-id="{{ $stage->id }}">
                    @foreach ($items as $purchase)
                        @php($subtitle = trim(collect([$purchase->brand, $purchase->model])->filter()->implode(' ')))
                        @php($searchParts = array_filter([
                            $purchase->title, $purchase->brand, $purchase->model,
                            $purchase->article, $purchase->supplier, $purchase->responsible?->name,
                        ]))
                        <div class="border rounded p-2 kanban-card" data-purchase-id="{{ $purchase->id }}"
                             data-search="{{ mb_strtolower(trim(implode(' ', $searchParts))) }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <a class="fw-semibold text-decoration-none" href="{{ route('purchases.show', $purchase) }}">{{ $purchase->title }}</a>
                                @if($purchase->quantity > 1)
                                    <span class="badge text-bg-secondary flex-shrink-0">{{ $purchase->quantity }} пар</span>
                                @endif
                            </div>
                            @if($subtitle !== '')
                                <div class="small text-body-secondary mt-1">{{ $subtitle }}@if($purchase->size) · р. {{ $purchase->size }}@endif</div>
                            @elseif($purchase->size)
                                <div class="small text-body-secondary mt-1">Размер: {{ $purchase->size }}</div>
                            @endif
                            @if(!is_null($purchase->cost))
                                <div class="text-muted small mt-1">Закупка: {{ number_format((float) $purchase->cost, 0, ',', ' ') }} ₽@if(!is_null($purchase->expected_sale_price)) → {{ number_format((float) $purchase->expected_sale_price, 0, ',', ' ') }} ₽@endif</div>
                            @endif
                            @if($purchase->supplier)
                                <div class="text-muted small mt-1">Поставщик: {{ $purchase->supplier }}</div>
                            @endif
                            @if($purchase->responsible)
                                <div class="text-muted small mt-1">Ответственный: {{ $purchase->responsible->name }}</div>
                            @endif
                        </div>
                    @endforeach
                    <div class="text-muted small kanban-empty-note" @if(!$items->isEmpty()) style="display:none" @endif>Пусто</div>
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        (() => {
            const board = document.getElementById('kanbanBoard');
            const btnRow = document.getElementById('kbModeRow');
            const btnWrap = document.getElementById('kbModeWrap');
            const moveErrorMessage = 'Не удалось переместить карточку. Обновите страницу и попробуйте снова.';

            const applyMode = (mode) => {
                board.classList.remove('mode-row', 'mode-wrap');
                board.classList.add(mode === 'wrap' ? 'mode-wrap' : 'mode-row');
                btnRow.classList.toggle('btn-outline-secondary', mode === 'wrap');
                btnRow.classList.toggle('btn-secondary', mode !== 'wrap');
                btnWrap.classList.toggle('btn-outline-secondary', mode !== 'wrap');
                btnWrap.classList.toggle('btn-secondary', mode === 'wrap');
                localStorage.setItem('purchasesKanbanMode', mode);
            };
            applyMode(localStorage.getItem('purchasesKanbanMode') || 'row');
            btnRow.addEventListener('click', () => applyMode('row'));
            btnWrap.addEventListener('click', () => applyMode('wrap'));

            const tokenEl = document.querySelector('meta[name="csrf-token"]');
            const csrf = tokenEl ? tokenEl.getAttribute('content') : '';
            const searchInput = document.getElementById('kanbanSearch');

            const updateCount = (stageId) => {
                const list = document.querySelector(`.kanban-list[data-stage-id="${stageId}"]`);
                const countEl = document.querySelector(`.kanban-count[data-stage-id="${stageId}"]`);
                if (!list || !countEl) return;
                countEl.textContent = Array.from(list.querySelectorAll('.kanban-card'))
                    .filter((card) => card.style.display !== 'none').length;
            };

            const applySearch = () => {
                const needle = (searchInput?.value || '').trim().toLowerCase();
                document.querySelectorAll('.kanban-list').forEach((listEl) => {
                    listEl.querySelectorAll('.kanban-card').forEach((card) => {
                        const hay = (card.dataset.search || '').toLowerCase();
                        card.style.display = !needle || hay.includes(needle) ? '' : 'none';
                    });
                    const visible = Array.from(listEl.querySelectorAll('.kanban-card')).filter((c) => c.style.display !== 'none');
                    const emptyEl = listEl.querySelector('.kanban-empty-note');
                    if (emptyEl) emptyEl.style.display = visible.length ? 'none' : '';
                    updateCount(listEl.dataset.stageId);
                });
            };
            searchInput?.addEventListener('input', applySearch);

            document.querySelectorAll('.kanban-list').forEach((listEl) => {
                new Sortable(listEl, {
                    group: 'purchases-kanban',
                    animation: 150,
                    ghostClass: 'drag-ghost',
                    chosenClass: 'drag-chosen',
                    onEnd: async (evt) => {
                        const cardEl = evt.item;
                        const purchaseId = cardEl?.dataset?.purchaseId;
                        const fromStageId = evt.from?.dataset?.stageId;
                        const toStageId = evt.to?.dataset?.stageId;
                        if (!purchaseId || !toStageId) return;
                        if (fromStageId === toStageId) { updateCount(fromStageId); return; }

                        try {
                            const res = await fetch(`{{ url('/purchases') }}/${purchaseId}/move`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify({ to_stage_id: Number(toStageId) })
                            });
                            const payload = await res.json().catch(() => ({}));
                            if (!res.ok || payload.ok === false) throw new Error(payload.message || 'Move failed');
                            updateCount(fromStageId);
                            updateCount(toStageId);
                        } catch (e) {
                            const ref = evt.from.children[evt.oldIndex] || null;
                            evt.from.insertBefore(cardEl, ref);
                            updateCount(fromStageId);
                            updateCount(toStageId);
                            alert(moveErrorMessage);
                        }
                    }
                });
            });

            applySearch();
        })();
    </script>
@endpush
