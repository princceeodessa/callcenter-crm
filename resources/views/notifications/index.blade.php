@extends('layouts.app')

@section('content')
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0">Уведомления</h4>
    <div class="text-muted small">Непрочитанных: <b>{{ $unreadCount }}</b></div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      @forelse($notifications as $n)
        @php
          $payload = is_array($n->payload ?? null) ? $n->payload : [];
          $dealId = $payload['deal_id'] ?? null;
          $url = $dealId ? route('deals.show', ['deal' => $dealId]) : null;
        @endphp
        <div class="border-bottom pb-2 mb-2">
          <div class="d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold">
                {{ $n->title }}
                @if(!$n->is_read)
                  <span class="badge text-bg-primary ms-2">новое</span>
                @endif
              </div>
              @if($n->body)
                <div class="text-muted small">{{ $n->body }}</div>
              @endif
              <div class="text-muted small">{{ optional($n->created_at)->format('d.m.Y H:i') }}</div>
            </div>
            <div class="d-flex gap-2">
              @if($url)
                <a class="btn btn-sm btn-outline-primary" href="{{ $url }}">Открыть</a>
              @endif
              @if(!$n->is_read)
                <form method="POST" action="{{ route('notifications.read', $n) }}">
                  @csrf
                  <button class="btn btn-sm btn-outline-secondary">Отметить прочитанным</button>
                </form>
              @endif
            </div>
          </div>
        </div>
      @empty
        <div class="text-muted">Уведомлений пока нет</div>
      @endforelse

      <div class="mt-3">
        {{ $notifications->links() }}
      </div>
    </div>
  </div>
@endsection
