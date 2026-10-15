{{--
    Выбор периода — кнопка «01.10 — 14.10.2026» и календарь из макета дизайнера (стили .daterange/.cal-* — в
    blagodar-dashboard.css, поведение — window.bdDateRange в layouts/owner). «Применить» открывает $action с $params
    и выбранными датами. $mode: range (from/to) или day (одна дата, ключ date; дни позже $max недоступны).
--}}
@php
    $drMode = $mode ?? 'range';
    $drFrom = \Carbon\Carbon::parse($from);
    $drTo = \Carbon\Carbon::parse($to ?? $from);
    $drLabel = $drMode === 'day' ? $drFrom->format('d.m.Y')
        : ($drFrom->isSameDay($drTo) ? $drFrom->format('d.m.Y')
            : $drFrom->format($drFrom->year === $drTo->year ? 'd.m' : 'd.m.Y').' — '.$drTo->format('d.m.Y'));
@endphp
<div class="daterange" data-daterange data-mode="{{ $drMode }}" data-action="{{ $action }}"
     data-params="{{ json_encode((object) ($params ?? []), JSON_UNESCAPED_UNICODE) }}"
     data-from="{{ $drFrom->toDateString() }}" data-to="{{ $drTo->toDateString() }}" data-max="{{ $max ?? '' }}">
    <button class="chip" type="button" data-dr-btn title="{{ $drMode === 'day' ? 'Выбрать день' : 'Выбрать период' }}">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>
        <span data-dr-label>{{ $drLabel }}</span>
    </button>
    <div class="cal-pop" data-dr-pop hidden></div>
</div>
