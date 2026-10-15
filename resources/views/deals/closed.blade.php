@extends('layouts.app')

@section('content')
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
    <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}" style="width: 170px;">
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
        <span class="fw-semibold">Исходы закрытых за месяц</span>
        <span class="text-muted small">нажмите на колонку — покажем эти сделки</span>
      </div>
      <div class="nt-strip">
        @foreach($outcomeColumns as $key => $label)
          @continue($key === 'none' && ($outcomeCounts['none'] ?? 0) === 0)
          <a href="{{ route('deals.closed', array_filter(['month' => $month, 'q' => $q, 'category' => $category === $key ? null : $key])) }}" class="nt-cell {{ $category === $key ? 'on' : '' }}">
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
@endsection
