@extends('layouts.app')

    @php
        // редизайн «Nordika» — у колл-центра потолков; у кроссовок прежняя доска
        $nd = \App\Support\Ui\Nordika::enabled();
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
        $allDealsSearchParams = array_filter([
            'status' => 'all',
            'q' => $q !== '' ? $q : null,
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
            'created_at' => "\u{0421}\u{043E}\u{0437}\u{0434}\u{0430}\u{043D}\u{043E}",
            'empty' => "\u{041F}\u{0443}\u{0441}\u{0442}\u{043E}",
            'move_error' => "\u{041D}\u{0435} \u{0443}\u{0434}\u{0430}\u{043B}\u{043E}\u{0441}\u{044C} \u{043F}\u{0435}\u{0440}\u{0435}\u{043C}\u{0435}\u{0441}\u{0442}\u{0438}\u{0442}\u{044C} \u{0441}\u{0434}\u{0435}\u{043B}\u{043A}\u{0443}. \u{041E}\u{0431}\u{043D}\u{043E}\u{0432}\u{0438} \u{0441}\u{0442}\u{0440}\u{0430}\u{043D}\u{0438}\u{0446}\u{0443} \u{0438} \u{043F}\u{043E}\u{043F}\u{0440}\u{043E}\u{0431}\u{0443}\u{0439} \u{0441}\u{043D}\u{043E}\u{0432}\u{0430}.",
        ];

        $focusDatePresets = [
            $ui['yesterday'] => now()->copy()->subDay()->format('Y-m-d'),
            $ui['today'] => now()->format('Y-m-d'),
            $ui['tomorrow'] => now()->copy()->addDay()->format('Y-m-d'),
        ];
    @endphp

@unless($nd)
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
        .nt-picker{ position: fixed; inset: 0; background: rgba(15, 23, 42, .45); display: flex; align-items: center; justify-content: center; z-index: 2000; padding: 16px; }
        .nt-picker[hidden]{ display: none; }
        .nt-picker-box{ background: var(--bs-body-bg, #fff); border-radius: 14px; padding: 1rem 1.1rem; width: 320px; max-width: 100%; box-shadow: 0 20px 45px rgba(15, 23, 42, .25); }
        .drag-ghost{ opacity: .5; }
        .drag-chosen{ outline: 2px dashed rgba(13,110,253,.6); outline-offset: 2px; }
        .kanban-card .deal-title { line-height: 1.25; }
    </style>
@endpush
@endunless

@if($nd)
    {{-- каркас Nordika: поиск — в верхней панели, фильтры — в панели под ней, доска — во всю ширину --}}
    @section('nd_fullbleed', '1')
    @section('nd_search')
        <form class="nd-search" method="GET" action="{{ route('deals.kanban') }}" role="search">
            <svg class="nd-icon nd-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
            @if(!empty($showSpam))
                <input type="hidden" name="show_spam" value="1">
            @endif
            @if($focusDateValue)
                <input type="hidden" name="focus_date" value="{{ $focusDateValue }}">
            @endif
            <input type="search" id="kanbanSearch" name="q" value="{{ $q }}" placeholder="{{ $ui['search_placeholder'] }}">
        </form>
    @endsection
    @section('nd_toolbar')
        <div class="nd-seg">
            @foreach($focusDatePresets as $label => $presetDate)
                <a @class(['nd-on' => $focusDateValue === $presetDate]) href="{{ route('deals.kanban', array_merge($kanbanParamsWithoutDate, ['focus_date' => $presetDate])) }}">{{ $label }}</a>
            @endforeach
            @if($focusDateValue)
                <a href="{{ route('deals.kanban', $kanbanParamsWithoutDate) }}">{{ $ui['all_dates'] }}</a>
            @endif
        </div>
        <form method="GET" action="{{ route('deals.kanban') }}">
            @if(!empty($showSpam))
                <input type="hidden" name="show_spam" value="1">
            @endif
            @if($q !== '')
                <input type="hidden" name="q" value="{{ $q }}">
            @endif
            <input type="date" class="nd-date" id="kanbanFocusDate" name="focus_date" value="{{ $focusDateValue }}" aria-label="Дата">
            <button type="submit" class="nd-btn nd-sm"><svg class="nd-icon nd-sm" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>{{ $ui['apply'] }}</button>
        </form>
        @if($q !== '' || $focusDateValue)
            <a class="nd-btn nd-sm" href="{{ route('deals.kanban', $kanbanResetParams) }}">{{ $ui['reset'] }}</a>
        @endif
        <span class="nd-vsp"></span>
        <div class="nd-seg nd-kb-mode" role="group" aria-label="Вид доски">
            <button type="button" id="kbModeRow">{{ $ui['mode_row'] }}</button>
            <button type="button" id="kbModeWrap">{{ $ui['mode_wrap'] }}</button>
        </div>
        <span class="nd-vsp"></span>
        <a class="nd-chipf" href="{{ route('deals.closed') }}"><svg class="nd-icon nd-sm" viewBox="0 0 24 24"><path d="M20 6L9 17l-5-5"/></svg>{{ $ui['closed'] }}</a>
        <a @class(['nd-chipf', 'nd-on' => !empty($showSpam)]) href="{{ route('deals.kanban', empty($showSpam) ? array_merge($kanbanParamsWithoutDate, ['show_spam' => 1]) : $kanbanParamsWithoutSpam) }}"><span class="nd-sw"></span>{{ $ui['show_non_target'] }}</a>
        @if($q !== '')
            <a class="nd-chipf" href="{{ route('deals.index', $allDealsSearchParams) }}">Все по запросу</a>
        @endif
    @endsection
@endif

@section('content')
@if($nd)
    <div class="nd-board" id="kanbanBoard">
        @foreach ($stages as $stage)
            @php($stageDeals = $dealsByStage[$stage->id] ?? collect())
            <div class="nd-col">
                <div class="nd-col-h">
                    <span class="nd-col-dot" style="background:{{ \App\Support\Ui\Nordika::stageDot($stage->name, $loop->index) }}"></span>
                    <span class="nd-col-t">{{ $stage->name }}</span>
                    <span class="nd-col-n"><span class="kanban-count" data-stage-id="{{ $stage->id }}">{{ $stageDeals->count() }}</span> {{ $ui['count_suffix'] }}</span>
                    <a class="nd-col-add" href="{{ route('deals.create') }}" title="{{ $ui['create_deal_title'] }}">+</a>
                </div>
                @if(in_array($stage->id, $dateFilteredStageIds ?? [], true))
                    <div class="nd-col-note">
                        @if($focusDateValue)
                            {{ $ui['stage_hint_prefix'] }}{{ optional($focusDate)->format('d.m.Y') }}. {{ $ui['stage_hint_suffix'] }}
                        @else
                            {{ $ui['stage_hint_all'] }}
                        @endif
                    </div>
                    <input type="search" class="nd-col-search kanban-stage-search" data-stage-id="{{ $stage->id }}" placeholder="{{ $ui['stage_search_placeholder'] }}">
                @endif

                <div class="nd-col-body kanban-list" data-stage-id="{{ $stage->id }}">
                    @foreach ($stageDeals as $deal)
                        @php($leadName = $deal->lead_display_name ?? $ui['no_name'])
                        @php($dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title))
                        @php($answeredBy = $deal->latest_call_answered_by_label)
                        @php($clientAttentionCount = (int) ($deal->client_attention_count ?? 0))
                        @php($callsCount = (int) ($deal->phone_call_activities_count ?? 0))
                        @php($srcKey = \App\Support\Ui\Nordika::sourceKey($deal))
                        @php($phone = \App\Support\Ui\Nordika::phone($deal->contact?->phone))
                        @php($ntLabel = \App\Support\Deals\NonTargetCategory::label($deal->non_target_category))
                        @php($movedBy = $deal->latestStageHistory ? ($deal->latestStageHistory->changedBy?->name ?? 'Система') : null)
                        @php($searchParts = array_filter([
                            $dealTitle,
                            $leadName,
                            $deal->contact?->phone,
                            $deal->responsible?->name,
                            $answeredBy,
                            $canSeeKanbanIds ? $deal->id : null,
                        ]))
                        <div class="nd-card kanban-card" style="--src:{{ \App\Support\Ui\Nordika::source($srcKey)[1] }}" data-deal-id="{{ $deal->id }}" data-href="{{ route('deals.show', $deal) }}" data-search="{{ mb_strtolower(trim(implode(' ', $searchParts))) }}">
                            <div class="nd-c-top">
                                {!! \App\Support\Ui\Nordika::sourceChip($srcKey) !!}
                                @if($callsCount > 0)
                                    <span class="nd-cnt-badge" title="Звонков: {{ $callsCount }}"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M5 4h3l1.5 5-2 1.5a12 12 0 006 6l1.5-2 5 1.5V20a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/></svg>{{ $callsCount }}</span>
                                @endif
                                @if($clientAttentionCount > 0)
                                    <span class="nd-cnt-badge nd-attn" title="Новых сообщений: {{ $clientAttentionCount }}"><svg viewBox="0 0 24 24" width="11" height="11" fill="none" stroke="currentColor" stroke-width="2.4"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg>{{ $clientAttentionCount }}</span>
                                @endif
                                @if($canSeeKanbanIds)
                                    <span class="nd-cid">#{{ $deal->id }}</span>
                                @endif
                            </div>
                            <div class="nd-c-title"><a href="{{ route('deals.show', $deal) }}">@if($leadName && $leadName !== $dealTitle)<span style="color:var(--muted);font-weight:600">{{ $leadName }} · </span>@endif{{ $dealTitle }}</a></div>
                            <div @class(['nd-c-phone', 'nd-noph' => ! $phone])><svg class="nd-icon" viewBox="0 0 24 24"><path d="M5 4h3l1.5 5-2 1.5a12 12 0 006 6l1.5-2 5 1.5V20a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/></svg>{{ $phone ?? $ui['no_phone'] }}</div>
                            <div class="nd-c-foot">
                                @if(!empty($canSeeKanbanCreatedAt))
                                    <div class="nd-c-row"><span class="nd-k">{{ $ui['created_at'] }}:</span> {{ optional($deal->created_at)->format('d.m.Y H:i') }}</div>
                                @endif
                                @if($answeredBy)
                                    <div class="nd-c-row"><span class="nd-k">Ответил:</span> {{ $answeredBy }}</div>
                                @endif
                                <div class="nd-c-nt kanban-nt" @if(! $ntLabel) hidden @endif>Нецелевая: {{ $ntLabel }}</div>
                                <div class="nd-c-row kanban-last-moved"><span class="nd-k">Перенос:</span> @if($movedBy){!! \App\Support\Ui\Nordika::avatar($movedBy) !!} {{ \App\Support\Ui\Nordika::firstName($movedBy) }}@else<span style="color:var(--faint)">ещё не перемещали</span>@endif</div>
                            </div>
                        </div>
                    @endforeach

                    <div class="nd-col-empty kanban-empty-note" @if(!$stageDeals->isEmpty()) style="display:none" @endif>{{ $ui['empty'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if(!empty($nonTargetCategories) && $nonTargetStageId)
        {{-- потолки: перенос на «Нецелевое» — только с категорией (по ним статистика) --}}
        <div id="ntPicker" class="nd-nt-picker" hidden>
            <div class="nd-nt-box">
                <div class="fw-semibold">Почему сделка нецелевая?</div>
                @foreach($nonTargetCategories as $key => $label)
                    <button type="button" class="nd-btn nd-sm" data-cat="{{ $key }}">{{ $label }}</button>
                @endforeach
                <button type="button" class="nd-btn nd-sm" style="justify-content:center;color:var(--muted)" data-cat="">Отмена</button>
            </div>
        </div>
    @endif
@else

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
            @if($q !== '')
                <a class="btn btn-sm btn-outline-dark" href="{{ route('deals.index', $allDealsSearchParams) }}">Все по запросу</a>
            @endif
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
                            @if(!empty($canSeeKanbanCreatedAt))
                                <div class="text-muted small mt-1">
                                    <span class="fw-semibold">{{ $ui['created_at'] }}:</span> {{ optional($deal->created_at)->format('d.m.Y H:i') }}
                                </div>
                            @endif
                            @if($answeredBy)
                                <div class="text-muted small mt-1">Ответил: {{ $answeredBy }}</div>
                            @endif
                            <div class="small mt-1 text-warning-emphasis kanban-nt" @if(! \App\Support\Deals\NonTargetCategory::label($deal->non_target_category)) hidden @endif>Нецелевая: {{ \App\Support\Deals\NonTargetCategory::label($deal->non_target_category) }}</div>
                            <div class="text-muted small mt-2 kanban-last-moved">{{ $deal->last_moved_by_label }}</div>
                        </div>
                    @endforeach

                    <div class="text-muted small kanban-empty-note" @if(!$stageDeals->isEmpty()) style="display:none" @endif>{{ $ui['empty'] }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if(!empty($nonTargetCategories) && $nonTargetStageId)
        {{-- потолки: перенос на «Нецелевое» — только с категорией (по ним статистика) --}}
        <div id="ntPicker" class="nt-picker" hidden>
            <div class="nt-picker-box">
                <div class="fw-semibold mb-2">Почему сделка нецелевая?</div>
                <div class="d-grid gap-2">
                    @foreach($nonTargetCategories as $key => $label)
                        <button type="button" class="btn btn-outline-secondary btn-sm text-start" data-cat="{{ $key }}">{{ $label }}</button>
                    @endforeach
                    <button type="button" class="btn btn-link btn-sm text-muted" data-cat="">Отмена</button>
                </div>
            </div>
        </div>
    @endif
@endif
@endsection

@push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
    <script>
        (() => {
            const board = document.getElementById('kanbanBoard');
            const btnRow = document.getElementById('kbModeRow');
            const btnWrap = document.getElementById('kbModeWrap');
            const moveErrorMessage = @json($ui['move_error']);
            const ntStageId = @json(!empty($nonTargetCategories) && $nonTargetStageId ? (string) $nonTargetStageId : null);
            const ntLabels = @json($nonTargetCategories ?? []);
            const ntPicker = document.getElementById('ntPicker');
            // окно «почему нецелевая»: категория или null (отмена, клик мимо, Esc)
            const pickCategory = () => new Promise((resolve) => {
                if (!ntPicker) { resolve(null); return; }
                const done = (value) => {
                    ntPicker.hidden = true;
                    ntPicker.removeEventListener('click', onClick);
                    document.removeEventListener('keydown', onKey);
                    resolve(value);
                };
                const onClick = (e) => {
                    const btn = e.target.closest('[data-cat]');
                    if (btn) done(btn.dataset.cat || null);
                    else if (e.target === ntPicker) done(null);
                };
                const onKey = (e) => { if (e.key === 'Escape') done(null); };
                ntPicker.hidden = false;
                ntPicker.addEventListener('click', onClick);
                document.addEventListener('keydown', onKey);
            });

            const nd = board.classList.contains('nd-board');
            // каркас Nordika: «Перенос: [кружок] Имя» — переносит всегда тот, кто сейчас на странице
            const ndMovedByMe = @json($nd ? '<span class="nd-k">Перенос:</span> '.\App\Support\Ui\Nordika::avatar(auth()->user()->name).' '.e(\App\Support\Ui\Nordika::firstName(auth()->user()->name)) : null);

            const applyMode = (mode) => {
                if (nd) {
                    board.classList.toggle('nd-wrap', mode === 'wrap');
                    btnRow.classList.toggle('nd-on', mode !== 'wrap');
                    btnWrap.classList.toggle('nd-on', mode === 'wrap');
                } else {
                    board.classList.remove('mode-row','mode-wrap');
                    board.classList.add(mode === 'wrap' ? 'mode-wrap' : 'mode-row');

                    btnRow.classList.toggle('btn-outline-secondary', mode === 'wrap');
                    btnRow.classList.toggle('btn-secondary', mode !== 'wrap');
                    btnWrap.classList.toggle('btn-outline-secondary', mode !== 'wrap');
                    btnWrap.classList.toggle('btn-secondary', mode === 'wrap');
                }

                localStorage.setItem('kanbanMode', mode);
            };

            // Nordika: карточка открывает сделку по клику в любом месте (не сразу после перетаскивания)
            let justDragged = false;
            if (nd) {
                board.addEventListener('click', (e) => {
                    const card = e.target.closest('.kanban-card');
                    if (!card || justDragged || e.target.closest('a, button, input, select, textarea')) return;
                    if (card.dataset.href) window.location.href = card.dataset.href;
                });
            }

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
                const visible = Array.from(list.querySelectorAll('.kanban-card'))
                    .filter((card) => card.style.display !== 'none')
                    .length;
                countEl.textContent = visible;
                // «Пусто» — только у колонки без карточек (и после переноса карточки туда/оттуда)
                const emptyEl = list.querySelector('.kanban-empty-note');
                if (emptyEl) emptyEl.style.display = visible ? 'none' : '';
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
                    draggable: '.kanban-card',
                    onStart: () => { justDragged = true; },

                    onEnd: async (evt) => {
                        setTimeout(() => { justDragged = false; }, 60);
                        const cardEl = evt.item;
                        const dealId = cardEl?.dataset?.dealId;
                        const fromStageId = evt.from?.dataset?.stageId;
                        const toStageId = evt.to?.dataset?.stageId;

                        if (!dealId || !toStageId) return;
                        if (fromStageId === toStageId) { updateCount(fromStageId); return; }

                        const revert = () => {
                            const ref = evt.from.children[evt.oldIndex] || null;
                            evt.from.insertBefore(cardEl, ref);
                            updateCount(fromStageId);
                            updateCount(toStageId);
                        };
                        let category = null;
                        if (ntStageId && toStageId === ntStageId) {
                            category = await pickCategory();
                            if (!category) { revert(); return; }
                        }

                        try {
                            const res = await fetch(`{{ url('/deals') }}/${dealId}/move`, {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': csrf,
                                    'Accept': 'application/json'
                                },
                                body: JSON.stringify(category
                                    ? { to_stage_id: Number(toStageId), non_target_category: category }
                                    : { to_stage_id: Number(toStageId) })
                            });

                            const payload = await res.json().catch(() => ({}));
                            if (!res.ok || payload.ok === false) throw new Error(payload.message || 'Move failed');

                            // Кроссовки: попадание в «Продано»/«Отказ» закрывает сделку —
                            // карточка уходит с доски (в «Завершённые»).
                            if (payload.closed) {
                                cardEl.remove();
                                updateCount(fromStageId);
                                updateCount(toStageId);
                                return;
                            }

                            const lastMovedEl = cardEl?.querySelector('.kanban-last-moved');
                            if (lastMovedEl && payload.last_moved_by_label) {
                                if (nd && ndMovedByMe) lastMovedEl.innerHTML = ndMovedByMe;
                                else lastMovedEl.textContent = payload.last_moved_by_label;
                            }
                            const ntEl = cardEl?.querySelector('.kanban-nt');
                            if (ntEl) {
                                ntEl.textContent = category ? `Нецелевая: ${ntLabels[category] || category}` : '';
                                ntEl.hidden = !category;
                            }

                            updateCount(fromStageId);
                            updateCount(toStageId);
                        } catch (e) {
                            revert();
                            alert(moveErrorMessage);
                        }
                    }
                });
            });

            applySearch();
        })();
    </script>
@endpush
