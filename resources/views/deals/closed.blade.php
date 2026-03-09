@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h4 class="mb-0">Завершённые сделки</h4>
  <form class="d-flex gap-2 flex-wrap" method="GET" action="{{ route('deals.closed') }}">
    <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}" style="width: 170px;">
    <select class="form-select form-select-sm" name="result" style="width: 170px;">
      <option value="all" @selected($result === 'all')>Все</option>
      <option value="won" @selected($result === 'won')>Успешно</option>
      <option value="lost" @selected($result === 'lost')>Отказ</option>
    </select>
    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="поиск: имя, телефон, заголовок">
    <button class="btn btn-sm btn-outline-primary">Показать</button>
  </form>
</div>

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
              @php($badge = $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : 'secondary'))
              <span class="badge text-bg-{{ $badge }}">
                {{ $deal->closed_result === 'won' ? 'Успешно' : ($deal->closed_result === 'lost' ? 'Отказ' : 'Закрыта') }}
              </span>
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
