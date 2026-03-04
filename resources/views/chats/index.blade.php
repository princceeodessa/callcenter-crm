@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <h4 class="mb-0">Чаты</h4>
  <div class="text-muted small">Входящие из Telegram / VK / Avito появляются здесь автоматически</div>
</div>

<div class="card shadow-sm">
  <div class="card-body p-0">
    @if($conversations->count() === 0)
      <div class="p-4 text-muted">Пока нет диалогов. Напиши в бота/сообщество — и чат появится.</div>
    @else
      <div class="list-group list-group-flush">
        @foreach($conversations as $c)
          @php
            $badge = match($c->channel) {
              'vk' => 'VK',
              'telegram' => 'TG',
              'avito' => 'Avito',
              default => strtoupper($c->channel),
            };
            $last = $c->lastMessage;
          @endphp
          <a href="{{ route('chats.show', $c) }}" class="list-group-item list-group-item-action d-flex justify-content-between align-items-start">
            <div class="me-3">
              <div class="d-flex align-items-center gap-2">
                <span class="badge text-bg-secondary">{{ $badge }}</span>
                <div class="fw-semibold">{{ $c->deal?->title ?? ('Сделка #'.$c->deal_id) }}</div>
              </div>
              <div class="text-muted small mt-1">
                {{ $last?->direction === 'out' ? 'Вы: ' : '' }}{{ \Illuminate\Support\Str::limit($last?->body ?? '—', 120) }}
              </div>
            </div>
            <div class="text-end">
              <div class="text-muted small">{{ $c->last_message_at ? $c->last_message_at->format('d.m H:i') : '' }}</div>
              @if(($c->unread_count ?? 0) > 0)
                <span class="badge text-bg-primary mt-1">{{ $c->unread_count }}</span>
              @endif
            </div>
          </a>
        @endforeach
      </div>
    @endif
  </div>

  @if($conversations->hasPages())
    <div class="card-footer bg-white">
      {{ $conversations->links() }}
    </div>
  @endif
</div>
@endsection
