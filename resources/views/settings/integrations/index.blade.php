@extends('layouts.app')

@section('content')
<h4 class="mb-3">Настройки — Интеграции</h4>

<div class="row g-3">
  @foreach($providers as $p)
    @php($conn = $p['connection'])
    @php($settings = $conn->settings ?? [])

    <div class="col-12 col-xl-6">
      <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
          <div class="fw-semibold">{{ $p['title'] }}</div>
          <div class="d-flex gap-2 align-items-center">
            @if(($conn->status ?? 'disabled') === 'active')
              <span class="badge text-bg-success">подключено</span>
            @elseif(($conn->status ?? 'disabled') === 'error')
              <span class="badge text-bg-danger">ошибка</span>
            @else
              <span class="badge text-bg-secondary">отключено</span>
            @endif

            <a class="btn btn-sm btn-outline-secondary" href="{{ route('settings.integrations.show', $p['provider']) }}">Логи</a>
          </div>
        </div>

        <div class="card-body">
          @if($p['last_event_at'])
            <div class="text-muted small mb-2">Последнее событие: {{ $p['last_event_at']->format('d.m.Y H:i:s') }}</div>
          @else
            <div class="text-muted small mb-2">Событий пока нет</div>
          @endif

          @if($p['provider'] === 'megafon_vats')
            <div class="alert alert-info small">
              <div class="fw-semibold mb-1">Как подключить</div>
              <ol class="mb-0">
                <li>В личном кабинете МегаФон ВАТС включи интеграцию по REST API и получи «Адрес АТС» и «Ключ авторизации».</li>
                <li>Вставь их ниже и сохрани.</li>
                <li>В настройках ВАТС укажи наш webhook URL (ниже) и «ключ CRM» (token), чтобы ВАТС присылала события звонков.</li>
              </ol>
            </div>

            <form method="POST" action="{{ route('settings.integrations.connect', 'megafon_vats') }}" class="mb-2">
              @csrf
              <div class="mb-2">
                <label class="form-label small">Адрес API ВАТС (Base URL)</label>
                <input name="ats_api_base_url" class="form-control form-control-sm" placeholder="Напр.: https://{domain}/sys/crm_api.wcgp" value="{{ $settings['ats_api_base_url'] ?? '' }}" required>
              </div>
              <div class="mb-2">
                <label class="form-label small">Ключ авторизации в ВАТС (API key)</label>
                <input name="ats_api_key" class="form-control form-control-sm" value="{{ $settings['ats_api_key'] ?? '' }}" required>
              </div>
              <div class="mb-2">
                <label class="form-label small">Ключ CRM / token для webhook (можно оставить пустым — мы сгенерируем)</label>
                <input name="crm_webhook_token" class="form-control form-control-sm" value="{{ $settings['crm_webhook_token'] ?? '' }}">
              </div>

              @php($token = $settings['crm_webhook_token'] ?? null)
              @if($token)
                <div class="mb-2">
                  <label class="form-label small">Webhook URL для МегаФон ВАТС</label>
                  <input class="form-control form-control-sm" readonly value="{{ url('/webhooks/megafon/vats?token='.$token) }}">
                </div>
              @endif

              <button class="btn btn-sm btn-primary">Сохранить / Подключить</button>

              @if(($conn->status ?? 'disabled') === 'active')
                <button class="btn btn-sm btn-outline-danger" form="disconnect-megafon" type="submit" onclick="return confirm('Отключить интеграцию?')">Отключить</button>
              @endif
            </form>

            <form id="disconnect-megafon" method="POST" action="{{ route('settings.integrations.disconnect', 'megafon_vats') }}">
              @csrf
            </form>

            @if(!empty($conn->last_error))
              <div class="alert alert-warning small mb-0">{{ $conn->last_error }}</div>
            @endif

          @else
            <div class="text-muted small mb-2">
              Сохрани ключи. Для входящих событий используй webhook URL ниже (если сервис поддерживает webhooks).
            </div>

            <form method="POST" action="{{ route('settings.integrations.connect', $p['provider']) }}" class="mb-2">
              @csrf

              @if($p['provider'] === 'telegram')
                <div class="mb-2">
                  <label class="form-label small">Bot token</label>
                  <input name="bot_token" class="form-control form-control-sm" value="{{ $settings['bot_token'] ?? '' }}" required>
                </div>

                @if(!empty($settings['crm_webhook_token']))
                  <div class="mb-2">
                    <label class="form-label small">Webhook URL</label>
                    <input class="form-control form-control-sm" readonly value="{{ url('/webhooks/telegram?token='.($settings['crm_webhook_token'])) }}">
                  </div>
                @endif

                @if(!empty($settings['webhook_secret']))
                  <div class="mb-2">
                    <label class="form-label small">Webhook secret (Telegram header X-Telegram-Bot-Api-Secret-Token)</label>
                    <input class="form-control form-control-sm" readonly value="{{ $settings['webhook_secret'] }}">
                  </div>
                @endif
              @elseif($p['provider'] === 'vk')
                <div class="mb-2">
                  <label class="form-label small">ID сообщества</label>
                  <input name="group_id" class="form-control form-control-sm" value="{{ $settings['group_id'] ?? '' }}" required>
                </div>
                <div class="mb-2">
                  <label class="form-label small">Access token</label>
                  <input name="access_token" class="form-control form-control-sm" value="{{ $settings['access_token'] ?? '' }}" required>
                </div>

                @if(!empty($settings['crm_webhook_token']))
                  <div class="mb-2">
                    <label class="form-label small">Webhook URL</label>
                    <input class="form-control form-control-sm" readonly value="{{ url('/webhooks/vk?token='.($settings['crm_webhook_token'])) }}">
                  </div>
                @endif

                @if(!empty($settings['webhook_secret']))
                  <div class="mb-2">
                    <label class="form-label small">Secret для Callback API</label>
                    <input class="form-control form-control-sm" readonly value="{{ $settings['webhook_secret'] }}">
                  </div>
                @endif

                @if(!empty($settings['confirmation_code']))
                  <div class="mb-2">
                    <label class="form-label small">Confirmation code</label>
                    <input class="form-control form-control-sm" readonly value="{{ $settings['confirmation_code'] }}">
                  </div>
                @endif
              @elseif($p['provider'] === 'avito')
                <div class="mb-2">
                  <label class="form-label small">Access token</label>
                  <input name="access_token" class="form-control form-control-sm" value="{{ $settings['access_token'] ?? '' }}" required>
                </div>

                <div class="mb-2">
                  <label class="form-label small">User ID (account id) — нужен для Messenger API</label>
                  <input name="user_id" class="form-control form-control-sm" value="{{ $settings['user_id'] ?? '' }}" placeholder="например: 123456789">
                </div>

                @if(!empty($settings['crm_webhook_token']))
                  <div class="mb-2">
                    <label class="form-label small">Webhook URL (если в Avito включены webhooks)</label>
                    <input class="form-control form-control-sm" readonly value="{{ url('/webhooks/avito?token='.($settings['crm_webhook_token'])) }}">
                  </div>
                @endif
              @endif

              <button class="btn btn-sm btn-primary">Сохранить / Подключить</button>

              @if(($conn->status ?? 'disabled') === 'active')
                <button class="btn btn-sm btn-outline-danger" form="disconnect-{{ $p['provider'] }}" type="submit" onclick="return confirm('Отключить интеграцию?')">Отключить</button>
              @endif
            </form>

            <form id="disconnect-{{ $p['provider'] }}" method="POST" action="{{ route('settings.integrations.disconnect', $p['provider']) }}">
              @csrf
            </form>
          @endif
        </div>
      </div>
    </div>
  @endforeach
</div>
@endsection
