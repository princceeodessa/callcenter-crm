@extends('layouts.app')

@php
  // редизайн «Nordika» (этап 3) — у колл-центра потолков исходы плитками и таблица как в прототипе; у кроссовок прежняя
  $nd = \App\Support\Ui\Nordika::enabled();
  $ndUi = \App\Support\Ui\Nordika::class;
  $ndResult = [
      'won' => ['Успешно', 'var(--accent)'],
      'lost' => ['Отказ', 'var(--red)'],
      'extra_non_target' => ['Доп. / нецелевая', 'var(--amber)'],
  ];
  $ndPeriod = \Carbon\Carbon::parse($fromValue)->format('d.m.Y').($toValue !== $fromValue ? ' – '.\Carbon\Carbon::parse($toValue)->format('d.m.Y') : '');
@endphp

@if($nd)
  @section('nd_sub', '— завершённые')
  @section('nd_search')
    <div class="nd-search">
      <svg class="nd-icon nd-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
      <input type="search" form="closedFilterForm" name="q" value="{{ $q }}" placeholder="Имя, телефон, название сделки">
    </div>
  @endsection
  @section('nd_toolbar')
    <form method="GET" action="{{ route('deals.closed') }}" id="closedFilterForm">
      <span class="nd-tb-note">с</span>
      <input type="date" name="from" class="nd-input" value="{{ $fromValue }}" aria-label="С даты">
      <span class="nd-tb-note">по</span>
      <input type="date" name="to" class="nd-input" value="{{ $toValue }}" aria-label="По дату">
      <select class="nd-input" name="result" aria-label="Результат">
        <option value="all" @selected($result === 'all')>Все результаты</option>
        <option value="won" @selected($result === 'won')>Успешно</option>
        <option value="lost" @selected($result === 'lost')>Отказ</option>
        <option value="extra_non_target" @selected($result === 'extra_non_target')>Доп.Работы / Не целевой</option>
      </select>
      @if($category !== '')
        <input type="hidden" name="category" value="{{ $category }}">
      @endif
      <button class="nd-btn nd-sm nd-primary">Показать</button>
      @if($q !== '' || $result !== 'all' || $category !== '')
        <a class="nd-btn nd-sm" href="{{ route('deals.closed', ['from' => $fromValue, 'to' => $toValue]) }}">Сбросить</a>
      @endif
    </form>
  @endsection
@endif

@section('content')
@if($nd)
  @if(!empty($outcomeColumns))
    {{-- потолки: исходы закрытых за период — плитка = фильтр списка --}}
    <div class="nd-sec-row" style="margin-top:0"><h2 class="nd-sec-h">Исходы закрытых за {{ $ndPeriod }}</h2><span class="nd-sec-n">нажмите плитку — покажем эти сделки</span></div>
    <div class="nd-kpis nd-kpis-auto">
      @foreach($outcomeColumns as $key => $label)
        @continue($key === 'none' && ($outcomeCounts['none'] ?? 0) === 0)
        <a href="{{ route('deals.closed', array_filter(['from' => $fromValue, 'to' => $toValue, 'q' => $q, 'category' => $category === $key ? null : $key])) }}" @class(['nd-kpi', 'nd-on' => $category === $key])>
          <div class="nd-kl">{{ $label }}</div>
          <div class="nd-kv nd-sm">{{ $outcomeCounts[$key] ?? 0 }}</div>
        </a>
      @endforeach
    </div>
  @endif
  <div class="nd-panel">
    <div class="nd-tbl-wrap">
      <table class="nd-tbl">
        <thead>
          <tr><th>#</th><th>Клиент</th><th>Источник</th><th>Результат</th><th>Ответственный</th><th>Закрыта</th></tr>
        </thead>
        <tbody>
          @forelse ($deals as $deal)
            @php
              $leadName = $deal->lead_display_name ?? 'Без имени';
              $dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title);
              $res = $ndResult[$deal->closed_result] ?? ['Закрыта', 'var(--muted)'];
              $ntLabel = \App\Support\Deals\NonTargetCategory::label($deal->non_target_category);
            @endphp
            <tr data-nd-href="{{ route('deals.show', $deal) }}">
              <td class="nd-faint nd-nowrap">#{{ $deal->id }}</td>
              <td>
                <a class="nd-tname" href="{{ route('deals.show', $deal) }}">{{ $dealTitle }}</a>
                <div class="nd-sub2">{{ $leadName !== $dealTitle ? $leadName.' · ' : '' }}{{ $ndUi::phone($deal->contact?->phone) ?? 'без телефона' }}</div>
              </td>
              <td>{!! $ndUi::sourceChip($ndUi::sourceKey($deal)) !!}</td>
              <td>
                <span class="nd-stage-pill" style="color:{{ $res[1] }}"><span class="nd-d" style="background:{{ $res[1] }}"></span>{{ $res[0] }}</span>
                @if($ntLabel)<div class="nd-sub2">{{ $ntLabel }}</div>@endif
                @if($deal->closed_reason)<div class="nd-sub2">{{ $deal->closed_reason }}</div>@endif
              </td>
              <td>@if($deal->responsible)<span class="nd-person">{!! $ndUi::avatar($deal->responsible->name) !!}{{ $ndUi::firstName($deal->responsible->name) }}</span>@else<span class="nd-faint">—</span>@endif</td>
              <td class="nd-nowrap nd-faint">{{ optional($deal->closed_at)->format('d.m.Y H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="nd-faint">Нет закрытых сделок за выбранный период</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  {{ $deals->links('pagination.nordika') }}
@else
<style>
  .nt-strip{ display:grid; grid-template-columns:repeat(auto-fit, minmax(118px, 1fr)); gap:.5rem; }
  .nt-cell{ border:1px solid var(--bs-border-color); border-radius:10px; padding:.45rem .65rem; text-decoration:none; color:inherit; min-width:0; }
  .nt-cell:hover{ border-color:#93c5fd; }
  .nt-cell.on{ border-color:#2563eb; background:rgba(37,99,235,.08); }
  .nt-cell .l{ font-size:.72rem; line-height:1.2; color:var(--bs-secondary-color); }
  .nt-cell .v{ font-size:1.3rem; font-weight:700; }
</style>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h4 class="mb-0">Завершённые сделки</h4>
  <form class="d-flex gap-2 flex-wrap" method="GET" action="{{ route('deals.closed') }}">
    <div class="d-flex align-items-center gap-1">
      <span class="text-muted small">с</span>
      <input type="date" name="from" class="form-control form-control-sm" value="{{ $fromValue }}" style="width: 150px;">
      <span class="text-muted small">по</span>
      <input type="date" name="to" class="form-control form-control-sm" value="{{ $toValue }}" style="width: 150px;">
    </div>
    <select class="form-select form-select-sm" name="result" style="width: 170px;">
      <option value="all" @selected($result === 'all')>Все</option>
      <option value="won" @selected($result === 'won')>Успешно</option>
      <option value="lost" @selected($result === 'lost')>Отказ</option>
      <option value="extra_non_target" @selected($result === 'extra_non_target')>Доп.Работы / Не целевой</option>
    </select>
    @if(!empty($outcomeColumns))
      <select class="form-select form-select-sm" name="category" style="width: 230px;">
        <option value="">Все исходы</option>
        @foreach($outcomeColumns as $key => $label)
          <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
        @endforeach
      </select>
    @endif
    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="поиск: имя, телефон, заголовок">
    <button class="btn btn-sm btn-outline-primary">Показать</button>
  </form>
</div>

@if(!empty($outcomeColumns))
  {{-- потолки: исходы закрытых за месяц — целевые и нецелевые по категориям; колонка — фильтр списка --}}
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
        <span class="fw-semibold">Исходы закрытых за {{ \Carbon\Carbon::parse($fromValue)->format('d.m.Y') }}{{ $toValue !== $fromValue ? ' – '.\Carbon\Carbon::parse($toValue)->format('d.m.Y') : '' }}</span>
        <span class="text-muted small">нажмите на колонку — покажем эти сделки</span>
      </div>
      <div class="nt-strip">
        @foreach($outcomeColumns as $key => $label)
          @continue($key === 'none' && ($outcomeCounts['none'] ?? 0) === 0)
          <a href="{{ route('deals.closed', array_filter(['from' => $fromValue, 'to' => $toValue, 'q' => $q, 'category' => $category === $key ? null : $key])) }}" class="nt-cell {{ $category === $key ? 'on' : '' }}">
            <div class="l">{{ $label }}</div>
            <div class="v">{{ $outcomeCounts[$key] ?? 0 }}</div>
          </a>
        @endforeach
      </div>
    </div>
  </div>
@endif

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Сделка</th>
          <th>Результат</th>
          <th>Источник</th>
          <th>Клиент</th>
          <th>Закрыта</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($deals as $deal)
          @php($leadName = $deal->lead_display_name ?? 'Без имени')
          @php($dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title))
          <tr class="{{ $deal->lead_source_surface_class }}">
            <td>
              <a href="{{ route('deals.show', $deal) }}" class="fw-semibold text-decoration-none">{{ $dealTitle }}</a>
              <span class="text-muted small ms-1">#{{ $deal->id }}</span>
              @if($deal->closed_reason)
                <div class="text-muted small">Причина: {{ $deal->closed_reason }}</div>
              @endif
            </td>
            <td>
              @php($badge = $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : ($deal->closed_result === 'extra_non_target' ? 'warning' : 'secondary')))
              <span class="badge text-bg-{{ $badge }}">
                {{ $deal->closed_result === 'won' ? 'Успешно' : ($deal->closed_result === 'lost' ? 'Отказ' : ($deal->closed_result === 'extra_non_target' ? 'Доп.Работы / Не целевой' : 'Закрыта')) }}
              </span>
              @if(\App\Support\Deals\NonTargetCategory::label($deal->non_target_category))
                <div class="small text-muted mt-1">{{ \App\Support\Deals\NonTargetCategory::label($deal->non_target_category) }}</div>
              @endif
            </td>
            <td>
              @if($deal->lead_source_chat_url)
                <a href="{{ $deal->lead_source_chat_url }}" class="{{ $deal->lead_source_badge_class }} text-decoration-none" target="_blank" rel="noopener">{{ $deal->lead_source_label }}</a>
              @else
                <span class="{{ $deal->lead_source_badge_class }}">{{ $deal->lead_source_label }}</span>
              @endif
            </td>
            <td>
              <div class="fw-semibold">{{ $leadName }}</div>
              @if($deal->contact?->phone)
                <div class="text-muted small">{{ $deal->contact->phone }}</div>
              @endif
            </td>
            <td class="text-muted small">{{ optional($deal->closed_at)->format('d.m.Y H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-muted small">Нет данных за выбранный период</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">
  {{ $deals->links() }}
</div>
@endif
@endsection
