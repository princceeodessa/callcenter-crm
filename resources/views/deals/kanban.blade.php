@extends('layouts.app')

@push('styles')
    <style>
        .kanban-toolbar{ gap: .5rem; }
        .kanban-board{ align-items: start; }
        .kanban-board.mode-row{ display: flex; flex-wrap: nowrap; overflow-x: auto; gap: 1rem; padding-bottom: .25rem; }
        .kanban-board.mode-row .kanban-col{ flex: 0 0 360px; }
        .kanban-board.mode-wrap{ display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); }
        .kanban-list{ min-height: 64px; }
        .kanban-card{ cursor: grab; user-select: none; }
        .kanban-card:active{ cursor: grabbing; }
        .drag-ghost{ opacity: .5; }
        .drag-chosen{ outline: 2px dashed rgba(13,110,253,.6); outline-offset: 2px; }
        .kanban-card .deal-title { line-height: 1.25; }
    </style>
@endpush

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap kanban-toolbar">
        <div class="d-flex flex-column gap-2">
            <h4 class="mb-0">Сделки — канбан</h4>
            <form method="GET" action="{{ route('deals.kanban') }}" class="d-flex gap-2 flex-wrap align-items-center">
                @if(!empty($showSpam))
                    <input type="hidden" name="show_spam" value="1">
                @endif
                <input
                    type="search"
                    class="form-control form-control-sm"
                    id="kanbanSearch"
                    name="q"
                    value="{{ $q }}"
                    placeholder="Поиск по имени, телефону, сделке, id, ответственному"
                    style="min-width: 320px;"
                >
                <button type="submit" class="btn btn-sm btn-primary">Найти</button>
                @if($q !== '')
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.kanban', array_filter(['show_spam' => !empty($showSpam) ? 1 : null])) }}">Сбросить</a>
                @endif
            </form>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="btn-group" role="group" aria-label="Kanban mode">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="kbModeRow">В один ряд</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="kbModeWrap">С переносом</button>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('deals.closed') }}">Завершённые</a>
            @if(empty($showSpam))
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.kanban', ['show_spam' => 1, 'q' => $q ?: null]) }}">Показать нецелевое</a>
            @else
                <a class="btn btn-sm btn-secondary" href="{{ route('deals.kanban', ['q' => $q ?: null]) }}">Скрыть нецелевое</a>
            @endif
            <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}">+ Сделка</a>
        </div>
    </div>

    <div class="kanban-board mode-row" id="kanbanBoard">
        @foreach ($stages as $stage)
            @php($stageDeals = $dealsByStage[$stage->id] ?? collect())
            <div class="card shadow-sm kanban-col">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $stage->name }}</div>
                        <div class="text-muted small">
                            <span class="kanban-count" data-stage-id="{{ $stage->id }}">{{ $stageDeals->count() }}</span> шт.
                        </div>
                        @if(in_array($stage->id, $todayFocusedStageIds ?? [], true))
                            <div class="text-muted small mt-1">Показываются только сделки за текущую дату</div>
                            <input
                                type="search"
                                class="form-control form-control-sm mt-2 kanban-stage-search"
                                data-stage-id="{{ $stage->id }}"
                                placeholder="Поиск по сделкам в этой стадии"
                            >
                        @endif
                    </div>
                    <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}" title="Создать сделку">+</a>
                </div>

                <div class="card-body d-flex flex-column gap-2 kanban-list" data-stage-id="{{ $stage->id }}">
                    @foreach ($stageDeals as $deal)
                        @php($leadName = $deal->lead_display_name ?? 'Без имени')
                        @php($dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title))
                        <div class="border rounded p-2 kanban-card {{ $deal->lead_source_surface_class }}" data-deal-id="{{ $deal->id }}" data-search="{{ mb_strtolower(trim(implode(' ', array_filter([$dealTitle, $leadName, $deal->contact?->phone, $deal->responsible?->name, $deal->id])))) }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    {!! $deal->lead_source_icon_html !!}
                                    <a class="fw-semibold text-decoration-none deal-title" href="{{ route('deals.show', $deal) }}">{{ $dealTitle }}</a>
                                </div>
                                <span class="text-muted small">#{{ $deal->id }}</span>
                            </div>
                            @if($leadName && $leadName !== $dealTitle)
                                <div class="mt-2 small fw-semibold text-body-secondary">{{ $leadName }}</div>
                            @endif
                            <div class="text-muted small mt-1">
                                @if($deal->contact?->phone){{ $deal->contact->phone }}@else Без телефона @endif
                            </div>
                            <div class="text-muted small mt-2 kanban-last-moved">{{ $deal->last_moved_by_label }}</div>
                        </div>
                    @endforeach

                    <div class="text-muted small kanban-empty-note" @if(!$stageDeals->isEmpty()) style="display:none" @endif>Пусто</div>
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

            const applyMode = (mode) => {
                board.classList.remove('mode-row','mode-wrap');
                board.classList.add(mode === 'wrap' ? 'mode-wrap' : 'mode-row');

                btnRow.classList.toggle('btn-outline-secondary', mode === 'wrap');
                btnRow.classList.toggle('btn-secondary', mode !== 'wrap');
                btnWrap.classList.toggle('btn-outline-secondary', mode !== 'wrap');
                btnWrap.classList.toggle('btn-secondary', mode === 'wrap');

                localStorage.setItem('kanbanMode', mode);
            };

            const saved = localStorage.getItem('kanbanMode') || 'row';
            applyMode(saved);

            btnRow.addEventListener('click', () => applyMode('row'));
            btnWrap.addEventListener('click', () => applyMode('wrap'));

            const tokenEl = document.querySelector('meta[name="csrf-token"]');
            const csrf = tokenEl ? tokenEl.getAttribute('content') : '';
            const searchInput = document.getElementById('kanbanSearch');
            const stageSearchInputs = document.querySelectorAll('.kanban-stage-search');

            const updateCount = (stageId) => {
                const list = document.querySelector(`.kanban-list[data-stage-id="${stageId}"]`);
                const countEl = document.querySelector(`.kanban-count[data-stage-id="${stageId}"]`);
                if (!list || !countEl) return;
                countEl.textContent = Array.from(list.querySelectorAll('.kanban-card'))
                    .filter((card) => card.style.display !== 'none')
                    .length;
            };

            const applySearch = () => {
                const needle = (searchInput?.value || '').trim().toLowerCase();
                document.querySelectorAll('.kanban-card').forEach((card) => {
                    const hay = (card.dataset.search || '').toLowerCase();
                    const listEl = card.closest('.kanban-list');
                    const stageId = listEl?.dataset?.stageId || '';
                    const stageSearch = document.querySelector(`.kanban-stage-search[data-stage-id="${stageId}"]`);
                    const stageNeedle = (stageSearch?.value || '').trim().toLowerCase();
                    const matchesGlobal = !needle || hay.includes(needle);
                    const matchesStage = !stageNeedle || hay.includes(stageNeedle);
                    card.style.display = matchesGlobal && matchesStage ? '' : 'none';
                });

                document.querySelectorAll('.kanban-list').forEach((listEl) => {
                    const stageId = listEl.dataset.stageId;
                    const visibleCards = Array.from(listEl.querySelectorAll('.kanban-card')).filter((card) => card.style.display !== 'none');
                    const emptyEl = listEl.querySelector('.kanban-empty-note');
                    if (emptyEl) {
                        emptyEl.style.display = visibleCards.length ? 'none' : '';
                    }
                    updateCount(stageId);
                });
            };

            searchInput?.addEventListener('input', applySearch);
            stageSearchInputs.forEach((input) => input.addEventListener('input', applySearch));

            document.querySelectorAll('.kanban-list').forEach((listEl) => {
                new Sortable(listEl, {
                    group: 'kanban',
                    animation: 150,
                    ghostClass: 'drag-ghost',
                    chosenClass: 'drag-chosen',

                    onEnd: async (evt) => {
                        const cardEl = evt.item;
                        const dealId = cardEl?.dataset?.dealId;
                        const fromStageId = evt.from?.dataset?.stageId;
                        const toStageId = evt.to?.dataset?.stageId;

                        if (!dealId || !toStageId) return;
                        if (fromStageId === toStageId) { updateCount(fromStageId); return; }

                        try {
                            const res = await fetch(`{{ url('/deals') }}/${dealId}/move`, {
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

                            const lastMovedEl = cardEl?.querySelector('.kanban-last-moved');
                            if (lastMovedEl && payload.last_moved_by_label) {
                                lastMovedEl.textContent = payload.last_moved_by_label;
                            }

                            updateCount(fromStageId);
                            updateCount(toStageId);
                        } catch (e) {
                            const ref = evt.from.children[evt.oldIndex] || null;
                            evt.from.insertBefore(cardEl, ref);
                            updateCount(fromStageId);
                            updateCount(toStageId);
                            alert('Не удалось переместить сделку. Обнови страницу и попробуй снова.');
                        }
                    }
                });
           });

            applySearch();
        })();
    </script>
@endpush
