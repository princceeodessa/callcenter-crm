@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <h4 class="mb-1">Интеграция — {{ $providerTitle }}</h4>
    <div class="text-muted small">Провайдер: <code>{{ $provider }}</code></div>
  </div>
  <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.integrations.index') }}">← Назад</a>
</div>

@if($connection)
  <div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Статус и настройки</div>
    <div class="card-body small">
      <div class="mb-1"><b>Статус:</b> {{ $connection->status }}</div>
      <div class="mb-1"><b>Последняя синхронизация:</b> {{ $connection->last_synced_at?->format('d.m.Y H:i:s') ?? '—' }}</div>
      @if($provider === 'megafon_vats')
        @php($s = $connection->settings ?? [])
        <div class="mb-1"><b>Base URL:</b> {{ $s['ats_api_base_url'] ?? '—' }}</div>
        <div class="mb-1"><b>Webhook URL:</b>
          @if(!empty($s['crm_webhook_token']))
            <code>{{ url('/webhooks/megafon/vats?token='.$s['crm_webhook_token']) }}</code>
          @else
            —
          @endif
        </div>
      @endif

      @if(in_array($provider, ['telegram','vk','avito'], true))
        @php($s = $connection->settings ?? [])
        @if($provider === 'telegram')
          <div class="mb-1"><b>Webhook URL:</b>
            @if(!empty($s['crm_webhook_token']))
              <code>{{ url('/webhooks/telegram?token='.$s['crm_webhook_token']) }}</code>
            @else
              —
            @endif
          </div>
          <div class="mb-1"><b>Webhook secret:</b> {{ $s['webhook_secret'] ?? '—' }}</div>
        @elseif($provider === 'vk')
          <div class="mb-1"><b>Webhook URL:</b>
            @if(!empty($s['crm_webhook_token']))
              <code>{{ url('/webhooks/vk?token='.$s['crm_webhook_token']) }}</code>
            @else
              —
            @endif
          </div>
          <div class="mb-1"><b>Secret:</b> {{ $s['webhook_secret'] ?? '—' }}</div>
          <div class="mb-1"><b>Confirmation code:</b> {{ $s['confirmation_code'] ?? '—' }}</div>
        @elseif($provider === 'avito')
          <div class="mb-1"><b>User ID:</b> {{ $s['user_id'] ?? '—' }}</div>
          <div class="mb-1"><b>client_id:</b> {{ !empty($s['client_id']) ? 'установлен' : '—' }}</div>
          <div class="mb-1"><b>Access token:</b> {{ !empty($s['access_token']) ? 'установлен' : '—' }}</div>
          @if(!empty($s['token_expires_at']))
            <div class="mb-1"><b>Token expires:</b> {{ \Carbon\Carbon::parse($s['token_expires_at'])->format('d.m.Y H:i:s') }}</div>
          @endif
          <div class="mb-1"><b>Webhook URL:</b>
            @if(!empty($s['crm_webhook_token']))
              <code>{{ url('/webhooks/avito?token='.$s['crm_webhook_token']) }}</code>
            @else
              —
            @endif
          </div>
        @endif
      @endif
      @if(!empty($s['last_setup_error'] ?? null))
        <div class="alert alert-warning mt-2 mb-2">{{ $s['last_setup_error'] }}</div>
      @endif
      @if($connection->last_error)
        <div class="alert alert-warning mt-2 mb-0">{{ $connection->last_error }}</div>
      @endif
    </div>
  </div>
@endif

@if($connection && $connection->status === 'active' && in_array($provider, ['telegram','vk','avito'], true))
  <div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Тест: отправить сообщение через API</div>
    <div class="card-body">
      <form method="POST" action="{{ route('settings.integrations.testSend', $provider) }}" class="row g-2">
        @csrf
        @if($provider === 'telegram')
          <div class="col-12 col-md-3">
            <input name="chat_id" class="form-control form-control-sm" placeholder="chat_id" required>
          </div>
        @elseif($provider === 'vk')
          <div class="col-12 col-md-3">
            <input name="peer_id" class="form-control form-control-sm" placeholder="peer_id" required>
          </div>
        @elseif($provider === 'avito')
          <div class="col-12 col-md-3">
            <input name="chat_id" class="form-control form-control-sm" placeholder="chat_id" required>
          </div>
        @endif
        <div class="col-12 col-md-7">
          <input name="text" class="form-control form-control-sm" placeholder="Текст" required>
        </div>
        <div class="col-12 col-md-2">
          <button class="btn btn-sm btn-outline-primary w-100">Отправить</button>
        </div>
      </form>
      <div class="text-muted small mt-2">Результат смотри ниже в “Последние события”.</div>
    </div>
  </div>
@endif

<div class="card shadow-sm">
  <div class="card-header fw-semibold">Последние события (100)</div>
  <div class="card-body">
    @forelse($events as $e)
      <div class="border-bottom pb-2 mb-2">
        <div class="d-flex justify-content-between align-items-center">
          <div class="fw-semibold">{{ $e->event_type ?? 'event' }}</div>
          <div class="text-muted small">{{ $e->received_at?->format('d.m.Y H:i:s') }}</div>
        </div>
        <div class="text-muted small">external_id: {{ $e->external_id ?? '—' }}</div>
        <details class="mt-1">
          <summary class="small">payload</summary>
          <pre class="small bg-light p-2 rounded" style="white-space: pre-wrap;">{{ json_encode($e->payload, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT) }}</pre>
        </details>
      </div>
    @empty
      <div class="text-muted small">Событий пока нет.</div>
    @endforelse
  </div>
</div>
@endsection
