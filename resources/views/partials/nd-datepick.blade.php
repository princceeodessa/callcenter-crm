{{--
    Выбор дня или периода в панели фильтров Nordika — календарь из дизайн-системы (как в сводке владельца;
    поведение — public/js/bd-daterange.js, стили — public/css/bd-daterange.css). «Применить» открывает $action с $params
    и датой: $mode = 'day' ($param, по умолчанию date) или 'range' (from/to). $allowEmpty — без даты = «все даты».
--}}
@php
  $dpMode = $mode ?? 'day';
  $dpFrom = ! empty($from) ? \Carbon\Carbon::parse($from) : null;
  $dpTo = ! empty($to) ? \Carbon\Carbon::parse($to) : $dpFrom;
  $dpLabel = ! $dpFrom ? ($emptyLabel ?? 'Все даты')
      : ($dpMode === 'day' || $dpFrom->isSameDay($dpTo) ? $dpFrom->format('d.m.Y')
          : $dpFrom->format($dpFrom->year === $dpTo->year ? 'd.m' : 'd.m.Y').' — '.$dpTo->format('d.m.Y'));
@endphp
<div class="daterange bd-dr nd-dr" data-daterange data-mode="{{ $dpMode }}" data-param="{{ $param ?? 'date' }}" data-action="{{ $action }}"
     data-params="{{ json_encode((object) ($params ?? []), JSON_UNESCAPED_UNICODE) }}"
     data-from="{{ $dpFrom?->toDateString() }}" data-to="{{ $dpTo?->toDateString() }}"
     @if(! empty($allowEmpty)) data-allow-empty="1" data-empty-label="все даты" @endif>
    <button class="chip" type="button" data-dr-btn title="{{ $dpMode === 'day' ? 'Выбрать день' : 'Выбрать период' }}">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M3 9h18M8 2v4M16 2v4"/></svg>
        <span data-dr-label>{{ $dpLabel }}</span>
    </button>
    <div class="cal-pop" data-dr-pop hidden></div>
</div>
