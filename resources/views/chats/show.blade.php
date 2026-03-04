@extends('layouts.app')

@section('content')
@php
  $badge = match($conversation->channel) {
    'vk' => 'VK',
    'telegram' => 'TG',
    'avito' => 'Avito',
    default => strtoupper($conversation->channel),
  };
@endphp

<div class="d-flex align-items-center justify-content-between mb-3">
  <div>
    <div class="d-flex align-items-center gap-2">
      <a href="{{ route('chats.index') }}" class="btn btn-sm btn-outline-secondary">← Назад</a>
      <span class="badge text-bg-secondary">{{ $badge }}</span>
      <h5 class="mb-0">{{ $conversation->deal?->title ?? ('Сделка #'.$conversation->deal_id) }}</h5>
    </div>
    <div class="text-muted small mt-1">
      external_id: {{ $conversation->external_id }} • <a href="{{ route('deals.show', $conversation->deal_id) }}">Открыть сделку</a>
    </div>
  </div>

  <form method="POST" action="{{ route('chats.read', $conversation) }}" class="m-0" onsubmit="return false;">
    @csrf
    <span class="text-muted small">Сообщений: {{ $messages->count() }}</span>
  </form>
</div>

<div class="card shadow-sm" style="height: calc(100vh - 180px);">
  <div id="chatScroll" class="card-body bg-white" style="overflow-y: auto;">
    <div id="chatMessages" class="d-flex flex-column gap-2">
      @forelse($messages as $m)
        <div class="d-flex {{ $m->direction === 'out' ? 'justify-content-end' : 'justify-content-start' }}">
          <div class="p-2 rounded-3 {{ $m->direction === 'out' ? 'bg-primary text-white' : 'bg-light border' }}" style="max-width: 75%;">
            <div class="small" style="white-space: pre-wrap;">{{ $m->body }}</div>
            <div class="d-flex justify-content-between gap-3 mt-1">
              <div class="text-muted small" style="opacity: .8;">
                {{ $m->direction === 'out' ? 'Вы' : ($m->author ?? 'Клиент') }}
              </div>
              <div class="text-muted small" style="opacity: .8;">
                {{ optional($m->created_at)->format('H:i') }}
                @if($m->direction === 'out')
                  • {{ $m->status ?? 'ok' }}
                @endif
              </div>
            </div>
          </div>
        </div>
      @empty
        <div class="text-muted">Пока нет сообщений.</div>
      @endforelse
    </div>
  </div>

  <div class="card-footer bg-white">
    <form id="sendForm" method="POST" action="{{ route('chats.send', $conversation) }}" class="d-flex gap-2">
      @csrf
      <input id="sendInput" name="text" class="form-control" placeholder="Напиши сообщение…" autocomplete="off" maxlength="4000" required>
      <button class="btn btn-primary" type="submit">Отправить</button>
    </form>
    <div id="sendError" class="text-danger small mt-2" style="display:none"></div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(function() {
  const pollUrl = @json(route('chats.poll', $conversation));
  const sendUrl = @json(route('chats.send', $conversation));
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  const scrollEl = document.getElementById('chatScroll');
  const listEl = document.getElementById('chatMessages');
  const formEl = document.getElementById('sendForm');
  const inputEl = document.getElementById('sendInput');
  const errEl = document.getElementById('sendError');

  function scrollToBottom() {
    scrollEl.scrollTop = scrollEl.scrollHeight;
  }

  let lastId = 0;
  const lastItem = listEl.querySelector('[data-msg-id]:last-child');
  // If server-rendered messages do not have data attributes, use a safe fallback.
  lastId = @json($messages->last()?->id ?? 0);

  function renderMessage(m) {
    const wrap = document.createElement('div');
    wrap.className = 'd-flex ' + (m.direction === 'out' ? 'justify-content-end' : 'justify-content-start');
    wrap.innerHTML = `
      <div class="p-2 rounded-3 ${m.direction === 'out' ? 'bg-primary text-white' : 'bg-light border'}" style="max-width: 75%;">
        <div class="small" style="white-space: pre-wrap;"></div>
        <div class="d-flex justify-content-between gap-3 mt-1">
          <div class="text-muted small" style="opacity:.8;"></div>
          <div class="text-muted small" style="opacity:.8;"></div>
        </div>
      </div>
    `;
    wrap.querySelector('div.small').textContent = m.body ?? '';
    const metaLeft = wrap.querySelectorAll('.text-muted.small')[0];
    metaLeft.textContent = m.direction === 'out' ? 'Вы' : (m.author ?? 'Клиент');
    const metaRight = wrap.querySelectorAll('.text-muted.small')[1];
    const t = (m.created_at || '').toString().slice(11,16);
    metaRight.textContent = t + (m.direction === 'out' ? ` • ${(m.status || 'ok')}` : '');
    return wrap;
  }

  async function poll() {
    try {
      const url = new URL(pollUrl, window.location.origin);
      url.searchParams.set('after_id', String(lastId));
      const r = await fetch(url.toString(), { headers: { 'Accept': 'application/json' } });
      if (!r.ok) return;
      const j = await r.json();
      const msgs = j.messages || [];
      if (!msgs.length) return;
      msgs.forEach(m => {
        listEl.appendChild(renderMessage(m));
        lastId = Math.max(lastId, Number(m.id || 0));
      });
      scrollToBottom();
    } catch (e) {
      // ignore
    }
  }

  formEl.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    errEl.style.display = 'none';
    const text = (inputEl.value || '').trim();
    if (!text) return;

    inputEl.value = '';
    inputEl.focus();

    try {
      const r = await fetch(sendUrl, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8',
          'X-CSRF-TOKEN': csrf,
        },
        body: new URLSearchParams({ text })
      });

      const j = await r.json();
      if (!r.ok || !j.ok) {
        throw new Error(j.error || 'send_failed');
      }

      if (j.message) {
        listEl.appendChild(renderMessage(j.message));
        lastId = Math.max(lastId, Number(j.message.id || 0));
        scrollToBottom();
      }
    } catch (e) {
      errEl.textContent = e.message;
      errEl.style.display = 'block';
    }
  });

  // initial scroll
  scrollToBottom();
  // polling
  setInterval(poll, 2000);
})();
</script>
@endpush
