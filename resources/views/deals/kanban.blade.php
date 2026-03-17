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
    @php
        $focusDateValue = $focusDate?->format('Y-m-d');
        $kanbanParamsWithoutSpam = array_filter([
            'q' => $q !== '' ? $q : null,
            'focus_date' => $focusDateValue ?: null,
        ], fn ($value) => !is_null($value) && $value !== '');
        $kanbanParamsWithoutDate = array_filter([
            'show_spam' => !empty($showSpam) ? 1 : null,
            'q' => $q !== '' ? $q : null,
        ], fn ($value) => !is_null($value) && $value !== '');
        $kanbanResetParams = array_filter([
            'show_spam' => !empty($showSpam) ? 1 : null,
        ], fn ($value) => !is_null($value) && $value !== '');

        $ui = [
            'title' => "\u{0421}\u{0434}\u{0435}\u{043B}\u{043A}\u{0438} - \u{043A}\u{0430}\u{043D}\u{0431}\u{0430}\u{043D}",
            'search_placeholder' => $canSeeKanbanIds
                ? 'Поиск по имени, телефону, сделке, id, ответственному'
                : 'Поиск по имени, телефону, сделке, ответственному',
            'apply' => "\u{041F}\u{0440}\u{0438}\u{043C}\u{0435}\u{043D}\u{0438}\u{0442}\u{044C}",
            'yesterday' => "\u{0412}\u{0447}\u{0435}\u{0440}\u{0430}",
            'today' => "\u{0421}\u{0435}\u{0433}\u{043E}\u{0434}\u{043D}\u{044F}",
            'tomorrow' => "\u{0417}\u{0430}\u{0432}\u{0442}\u{0440}\u{0430}",
            'all_dates' => "\u{0412}\u{0441}\u{0435} \u{0434}\u{0430}\u{0442}\u{044B}",
            'reset' => "\u{0421}\u{0431}\u{0440}\u{043E}\u{0441}\u{0438}\u{0442}\u{044C}",
            'mode_row' => "\u{0412} \u{043E}\u{0434}\u{0438}\u{043D} \u{0440}\u{044F}\u{0434}",
            'mode_wrap' => "\u{0421} \u{043F}\u{0435}\u{0440}\u{0435}\u{043D}\u{043E}\u{0441}\u{043E}\u{043C}",
            'closed' => "\u{0417}\u{0430}\u{0432}\u{0435}\u{0440}\u{0448}\u{0435}\u{043D}\u{043D}\u{044B}\u{0435}",
            'show_non_target' => "\u{041F}\u{043E}\u{043A}\u{0430}\u{0437}\u{0430}\u{0442}\u{044C} \u{043D}\u{0435}\u{0446}\u{0435}\u{043B}\u{0435}\u{0432}\u{043E}\u{0435}",
            'hide_non_target' => "\u{0421}\u{043A}\u{0440}\u{044B}\u{0442}\u{044C} \u{043D}\u{0435}\u{0446}\u{0435}\u{043B}\u{0435}\u{0432}\u{043E}\u{0435}",
            'create_deal' => "\u{0421}\u{0434}\u{0435}\u{043B}\u{043A}\u{0430}",
            'create_deal_title' => "\u{0421}\u{043E}\u{0437}\u{0434}\u{0430}\u{0442}\u{044C} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0443}",
            'count_suffix' => "\u{0448}\u{0442}.",
            'stage_hint_prefix' => "\u{0424}\u{0438}\u{043B}\u{044C}\u{0442}\u{0440} \u{043F}\u{043E} \u{0434}\u{0430}\u{0442}\u{0435}: ",
            'stage_hint_suffix' => "\u{0423}\u{0447}\u{0438}\u{0442}\u{044B}\u{0432}\u{0430}\u{0435}\u{0442}\u{0441}\u{044F} \u{0441}\u{043E}\u{0437}\u{0434}\u{0430}\u{043D}\u{0438}\u{0435} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0438} \u{0438} \u{043F}\u{0435}\u{0440}\u{0435}\u{043D}\u{043E}\u{0441} \u{0432} \u{0441}\u{0442}\u{0430}\u{0434}\u{0438}\u{044E}.",
            'stage_hint_all' => "\u{041F}\u{043E}\u{043A}\u{0430}\u{0437}\u{0430}\u{043D}\u{044B} \u{0432}\u{0441}\u{0435} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0438}. \u{0414}\u{043B}\u{044F} \u{043E}\u{0442}\u{0431}\u{043E}\u{0440}\u{0430} \u{043F}\u{043E} \u{0434}\u{043D}\u{044E} \u{0432}\u{044B}\u{0431}\u{0435}\u{0440}\u{0438}\u{0442}\u{0435} \u{0434}\u{0430}\u{0442}\u{0443} \u{0432}\u{044B}\u{0448}\u{0435}.",
            'stage_search_placeholder' => "\u{041F}\u{043E}\u{0438}\u{0441}\u{043A} \u{043F}\u{043E} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0430}\u{043C} \u{0432} \u{044D}\u{0442}\u{043E}\u{0439} \u{0441}\u{0442}\u{0430}\u{0434}\u{0438}\u{0438}",
            'no_name' => "\u{0411}\u{0435}\u{0437} \u{0438}\u{043C}\u{0435}\u{043D}\u{0438}",
            'no_phone' => "\u{0411}\u{0435}\u{0437} \u{0442}\u{0435}\u{043B}\u{0435}\u{0444}\u{043E}\u{043D}\u{0430}",
            'empty' => "\u{041F}\u{0443}\u{0441}\u{0442}\u{043E}",
            'move_error' => "\u{041D}\u{0435} \u{0443}\u{0434}\u{0430}\u{043B}\u{043E}\u{0441}\u{044C} \u{043F}\u{0435}\u{0440}\u{0435}\u{043C}\u{0435}\u{0441}\u{0442}\u{0438}\u{0442}\u{044C} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0443}. \u{041E}\u{0431}\u{043D}\u{043E}\u{0432}\u{0438} \u{0441}\u{0442}\u{0440}\u{0430}\u{043D}\u{0438}\u{0446}\u{0443} \u{0438} \u{043F}\u{043E}\u{043F}\u{0440}\u{043E}\u{0431}\u{0443}\u{0439} \u{0441}\u{043D}\u{043E}\u{0432}\u{0430}.",
        ];

        $focusDatePresets = [
            $ui['yesterday'] => now()->copy()->subDay()->format('Y-m-d'),
            $ui['today'] => now()->format('Y-m-d'),
            $ui['tomorrow'] => now()->copy()->addDay()->format('Y-m-d'),
        ];
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap kanban-toolbar">
        <div class="d-flex flex-column gap-2">
            <h4 class="mb-0">{{ $ui['title'] }}</h4>
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
                    placeholder="{{ $ui['search_placeholder'] }}"
                    style="min-width: 320px;"
                >
                <input
                    type="date"
                    class="form-control form-control-sm"
                    id="kanbanFocusDate"
                    name="focus_date"
                    value="{{ $focusDateValue }}"
                    style="min-width: 170px;"
                >
                <button type="submit" class="btn btn-sm btn-primary">{{ $ui['apply'] }}</button>
                @foreach($focusDatePresets as $label => $presetDate)
                    <a
                        class="btn btn-sm {{ $focusDateValue === $presetDate ? 'btn-secondary' : 'btn-outline-secondary' }}"
                        href="{{ route('deals.kanban', array_merge($kanbanParamsWithoutDate, ['focus_date' => $presetDate])) }}"
                    >{{ $label }}</a>
                @endforeach
                @if($focusDateValue)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.kanban', $kanbanParamsWithoutDate) }}">{{ $ui['all_dates'] }}</a>
                @endif
                @if($q !== '' || $focusDateValue)
                    <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.kanban', $kanbanResetParams) }}">{{ $ui['reset'] }}</a>
                @endif
            </form>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <div class="btn-group" role="group" aria-label="Kanban mode">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="kbModeRow">{{ $ui['mode_row'] }}</button>
                <button type="button" class="btn btn-sm btn-outline-secondary" id="kbModeWrap">{{ $ui['mode_wrap'] }}</button>
            </div>
            <a class="btn btn-sm btn-outline-primary" href="{{ route('deals.closed') }}">{{ $ui['closed'] }}</a>
            @if(empty($showSpam))
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.kanban', array_merge($kanbanParamsWithoutDate, ['show_spam' => 1])) }}">{{ $ui['show_non_target'] }}</a>
            @else
                <a class="btn btn-sm btn-secondary" href="{{ route('deals.kanban', $kanbanParamsWithoutSpam) }}">{{ $ui['hide_non_target'] }}</a>
            @endif
            <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}">+ {{ $ui['create_deal'] }}</a>
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
                            <span class="kanban-count" data-stage-id="{{ $stage->id }}">{{ $stageDeals->count() }}</span> {{ $ui['count_suffix'] }}
                        </div>
                        @if(in_array($stage->id, $dateFilteredStageIds ?? [], true))
                            <div class="text-muted small mt-1">
                                @if($focusDateValue)
                                    {{ $ui['stage_hint_prefix'] }}{{ optional($focusDate)->format('d.m.Y') }}. {{ $ui['stage_hint_suffix'] }}
                                @else
                                    {{ $ui['stage_hint_all'] }}
                                @endif
                            </div>
                            <input
                                type="search"
                                class="form-control form-control-sm mt-2 kanban-stage-search"
                                data-stage-id="{{ $stage->id }}"
                                placeholder="{{ $ui['stage_search_placeholder'] }}"
                            >
                        @endif
                    </div>
                    <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}" title="{{ $ui['create_deal_title'] }}">+</a>
                </div>

                <div class="card-body d-flex flex-column gap-2 kanban-list" data-stage-id="{{ $stage->id }}">
                    @foreach ($stageDeals as $deal)
                        @php($leadName = $deal->lead_display_name ?? $ui['no_name'])
                        @php($dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title))
                        @php($answeredBy = $deal->latest_call_answered_by_label)
                        @php($clientAttentionCount = (int) ($deal->client_attention_count ?? 0))
                        @php($searchParts = array_filter([
                            $dealTitle,
                            $leadName,
                            $deal->contact?->phone,
                            $deal->responsible?->name,
                            $answeredBy,
                            $canSeeKanbanIds ? $deal->id : null,
                        ]))
                        <div class="border rounded p-2 kanban-card {{ $deal->lead_source_surface_class }}" data-deal-id="{{ $deal->id }}" data-search="{{ mb_strtolower(trim(implode(' ', $searchParts))) }}">
                            <div class="d-flex justify-content-between align-items-start gap-2">
                                <div class="d-flex align-items-center gap-2 min-w-0">
                                    {!! $deal->lead_source_icon_html !!}
                                    <a class="fw-semibold text-decoration-none deal-title" href="{{ route('deals.show', $deal) }}">{{ $dealTitle }}</a>
                                    @if($clientAttentionCount > 0)
                                        <span class="badge text-bg-success flex-shrink-0">{{ $clientAttentionCount }}</span>
                                    @endif
                                </div>
                                @if($canSeeKanbanIds)
                                    <span class="text-muted small">#{{ $deal->id }}</span>
                                @endif
                            </div>
                            @if($leadName && $leadName !== $dealTitle)
                                <div class="mt-2 small fw-semibold text-body-secondary">{{ $leadName }}</div>
                            @endif
                            <div class="text-muted small mt-1">
                                @if($deal->contact?->phone){{ $deal->contact->phone }}@else {{ $ui['no_phone'] }} @endif
                            </div>
                            @if($answeredBy)
                                <div class="text-muted small mt-1">Ответил: {{ $answeredBy }}</div>
                            @endif
                            <div class="text-muted small mt-2 kanban-last-moved">{{ $deal->last_moved_by_label }}</div>
                        </div>
                    @endforeach

                    <div class="text-muted small kanban-empty-note" @if(!$stageDeals->isEmpty()) style="display:none" @endif>{{ $ui['empty'] }}</div>
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
            const moveErrorMessage = @json($ui['move_error']);

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
                            alert(moveErrorMessage);
                        }
                    }
                });
            });

            applySearch();
        })();
    </script>
@endpush
