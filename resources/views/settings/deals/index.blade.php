@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Сделки — список</h4>
  <form class="d-flex gap-2" method="GET" action="{{ route('deals.index') }}">
    <select class="form-select form-select-sm" name="status" style="width: 170px;">
      <option value="open" @selected(($status ?? 'open') === 'open')>Открытые</option>
      <option value="closed" @selected(($status ?? 'open') === 'closed')>Завершённые</option>
      <option value="all" @selected(($status ?? 'open') === 'all')>Все</option>
    </select>
    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="поиск: имя, телефон, заголовок">
    <button class="btn btn-sm btn-outline-primary">Найти</button>
  </form>
</div>

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm table-striped align-middle mb-0">
      <thead>
        <tr>
          <th>Сделка</th>
          <th>Стадия</th>
          <th>Клиент</th>
          <th>Ответственный</th>
          <th>Создано</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($deals as $deal)
          <tr>
            <td>
              <a href="{{ route('deals.show', $deal) }}">{{ $deal->title }}</a>
              @if($deal->is_unread) <span class="badge text-bg-warning ms-1">не прочитан</span> @endif
              @if($deal->has_script_deviation) <span class="badge text-bg-danger ms-1">отклонения</span> @endif
              @if($deal->closed_at)
                @php($badge = $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : 'secondary'))
                <span class="badge text-bg-{{ $badge }} ms-1">{{ $deal->closed_result ?? 'closed' }}</span>
              @endif
            </td>
            <td>{{ $deal->stage?->name }}</td>
            <td>
              {{ $deal->contact?->name ?? 'Без имени' }}
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
