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
      @if($connection->last_error)
        <div class="alert alert-warning mt-2 mb-0">{{ $connection->last_error }}</div>
      @endif
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
