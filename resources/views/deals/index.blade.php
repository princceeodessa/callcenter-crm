@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h4 class="mb-0">Список сделок</h4>
  <form class="d-flex gap-2 flex-wrap" method="GET" action="{{ route('deals.index') }}">
    <select class="form-select form-select-sm" name="status" style="width: 170px;">
      <option value="open" @selected(($status ?? 'open') === 'open')>Открытые</option>
      <option value="closed" @selected(($status ?? 'open') === 'closed')>Завершённые</option>
      <option value="all" @selected(($status ?? 'open') === 'all')>Все</option>
    </select>
    <select class="form-select form-select-sm" name="source" style="width: 240px;">
      <option value="">Все источники</option>
      @foreach(($sourceOptions ?? []) as $sourceKey => $sourceLabel)
        <option value="{{ $sourceKey }}" @selected(($source ?? '') === $sourceKey)>{{ $sourceLabel }}</option>
      @endforeach
    </select>
    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="поиск: имя, телефон, заголовок">
    <button class="btn btn-sm btn-outline-primary">Найти</button>
  </form>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Сделка</th>
          <th>Источник</th>
          <th>Клиент</th>
          <th>Ответственный</th>
          <th>Создано</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($deals as $deal)
          @php($leadName = $deal->lead_display_name ?? 'Без имени')
          @php($dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title))
          @php($clientAttentionCount = (int) ($deal->client_attention_count ?? 0))
          <tr class="{{ $deal->lead_source_surface_class }}">
            <td>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                {!! $deal->lead_source_icon_html !!}
                <a href="{{ route('deals.show', $deal) }}" class="fw-semibold text-decoration-none">{{ $dealTitle }}</a>
                @if($clientAttentionCount > 0)
                  <span class="badge text-bg-success">{{ $clientAttentionCount }}</span>
                @endif
              </div>
              @if($deal->has_script_deviation) <span class="badge text-bg-danger ms-1">отклонения</span> @endif
              @if(!$deal->is_ready) <span class="badge text-bg-warning ms-1">не заполнено</span> @endif
              @if($deal->closed_at)
                @php($badge = $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : 'secondary'))
                <span class="badge text-bg-{{ $badge }} ms-1">{{ $deal->closed_result ?? 'closed' }}</span>
              @endif
              <div class="text-muted small mt-1">{{ $deal->stage?->name }}</div>
            </td>
            <td>
              @if($deal->lead_source_chat_url)
                <a href="{{ $deal->lead_source_chat_url }}" class="{{ $deal->lead_source_badge_class }} text-decoration-none" target="_blank" rel="noopener">{{ $deal->lead_source_label }}</a>
              @else
                <span class="{{ $deal->lead_source_badge_class }}">{{ $deal->lead_source_label }}</span>
              @endif
              @if($deal->incoming_phone_source_display)
                <div class="text-muted small mt-1">{{ $deal->incoming_phone_source_display }}</div>
              @endif
            </td>
            <td>
              <div class="fw-semibold">{{ $leadName }}</div>
              @if($deal->contact?->phone)
                <div class="text-muted small">{{ $deal->contact->phone }}</div>
              @endif
            </td>
            <td>{{ $deal->responsible?->name ?? '—' }}</td>
            <td class="text-muted small">{{ optional($deal->created_at)->format('d.m.Y H:i') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">
  {{ $deals->links() }}
</div>
@endsection
