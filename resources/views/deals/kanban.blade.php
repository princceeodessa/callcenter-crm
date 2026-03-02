@extends('layouts.app')

@push('styles')
    <style>
        .kanban-board{
            display:grid;
            gap: 1rem;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            align-items: start;
        }
        .kanban-list{ min-height: 64px; }
        .kanban-card{ cursor: grab; user-select: none; }
        .kanban-card:active{ cursor: grabbing; }
        .drag-ghost{ opacity: .5; }
        .drag-chosen{ outline: 2px dashed rgba(13,110,253,.6); outline-offset: 2px; }
    </style>
@endpush

@section('content')
    <h4 class="mb-3">Сделки — канбан</h4>

    <div class="kanban-board">
        @foreach ($stages as $stage)
            @php($stageDeals = $dealsByStage[$stage->id] ?? collect())
            <div class="card shadow-sm">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <div class="fw-semibold">{{ $stage->name }}</div>
                        <div class="text-muted small">
                            <span class="kanban-count" data-stage-id="{{ $stage->id }}">{{ $stageDeals->count() }}</span> шт.
                        </div>
                    </div>
                    <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}" title="Создать сделку">+</a>
                </div>

                <div class="card-body d-flex flex-column gap-2 kanban-list" data-stage-id="{{ $stage->id }}">
                    @foreach ($stageDeals as $deal)
                        <div class="border rounded p-2 bg-white kanban-card" data-deal-id="{{ $deal->id }}">
                            <div class="d-flex justify-content-between">
                                <a class="fw-semibold text-decoration-none" href="{{ route('deals.show', $deal) }}">{{ $deal->title }}</a>
                                <span class="text-muted small">#{{ $deal->id }}</span>
                            </div>
                            <div class="text-muted small">
                                {{ $deal->contact?->name ?? 'Без имени' }}
                                @if($deal->contact?->phone) • {{ $deal->contact->phone }} @endif
                            </div>
                            <div class="mt-2 d-flex gap-1 flex-wrap">
                                @if($deal->is_unread) <span class="badge text-bg-warning">не прочитан</span> @endif
                                @if($deal->has_script_deviation) <span class="badge text-bg-danger">отклонения</span> @endif
                                <span class="badge text-bg-secondary">{{ $deal->responsible?->name ?? 'без ответственного' }}</span>
                            </div>
                        </div>
                    @endforeach

                    @if ($stageDeals->isEmpty())
                        <div class="text-muted small">Пусто</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        (() => {
            const tokenEl = document.querySelector('meta[name="csrf-token"]');
            const csrf = tokenEl ? tokenEl.getAttribute('content') : '';

            const updateCount = (stageId) => {
                const list = document.querySelector(`.kanban-list[data-stage-id="${stageId}"]`);
                const countEl = document.querySelector(`.kanban-count[data-stage-id="${stageId}"]`);
                if (!list || !countEl) return;
                countEl.textContent = list.querySelectorAll('.kanban-card').length;
            };

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

                            if (!res.ok) throw new Error('Move failed');
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
        })();
    </script>
@endpushgit config