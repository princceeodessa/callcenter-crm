@extends('layouts.app')

@push('styles')
<style>
  .ccrm-chat-wrap { height: calc(100vh - 120px); }
  .ccrm-chat-list { width: 360px; }
  @media (max-width: 992px) {
    .ccrm-chat-list { width: 100%; }
    .ccrm-chat-pane { display: none; }
    .ccrm-chat-pane.show { display: flex; }
  }
  .ccrm-chat-item.active { background: rgba(13,110,253,.08); }
</style>
@endpush

@section('content')
@php
  $active = $activeConversation;
  $activeId = $active?->id;

  $badgeFor = fn($ch) => match($ch) {
    'vk' => 'VK',
    'telegram' => 'TG',
    'avito' => 'Avito',
    default => strtoupper((string)$ch),
  };

  $mediaFor = function($conversation, $message) {
    $payload = is_array($message->payload ?? null) ? $message->payload : [];
    $items = $payload['media'] ?? [];
    if (!is_array($items)) $items = [];

    $out = [];
    foreach ($items as $it) {
      if (!is_array($it)) continue;
      $type = (string)($it['type'] ?? 'file');
      $url = $it['url'] ?? null;
      $fileId = $it['file_id'] ?? null;
      if ($conversation?->channel === 'telegram' && is_string($fileId) && $fileId !== '') {
        $url = route('media.telegram', ['conversation' => $conversation->id, 'fileId' => $fileId]);
      }
      if (is_string($url) && $url !== '') {
        $out[] = [
          'type' => $type,
          'url' => $url,
          'file_name' => $it['file_name'] ?? null,
        ];
      }
    }
    return $out;
  };
@endphp

<div class="d-flex gap-3 ccrm-chat-wrap">
  {{-- Left: chat list --}}
  <div class="card shadow-sm ccrm-chat-list flex-shrink-0">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div class="fw-semibold">Чаты</div>
      @if($active)
        <a class="btn btn-sm btn-outline-secondary d-lg-none" href="{{ route('chats.index') }}">Список</a>
      @endif
    </div>
    <div class="card-body p-0" style="overflow-y:auto;">
      @if($conversations->count() === 0)
        <div class="p-4 text-muted">Пока нет диалогов. Напиши в бота/сообщество — и чат появится.</div>
      @else
        <div class="list-group list-group-flush">
          @foreach($conversations as $c)
            @php($last = $c->lastMessage)
            <a href="{{ route('chats.index', ['c' => $c->id]) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-start ccrm-chat-item {{ (int)$c->id === (int)$activeId ? 'active' : '' }}">
              <div class="me-3">
                <div class="d-flex align-items-center gap-2">
                  <span class="badge text-bg-secondary">{{ $badgeFor($c->channel) }}</span>
                  <div class="fw-semibold">{{ $c->deal?->title ?? ('Сделка #'.$c->deal_id) }}</div>
                </div>
                <div class="text-muted small mt-1">
                  {{ $last?->direction === 'out' ? 'Вы: ' : '' }}{{ \Illuminate\Support\Str::limit($last?->body ?? '—', 80) }}
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

  {{-- Right: active chat --}}
  <div class="card shadow-sm flex-grow-1 d-flex flex-column ccrm-chat-pane {{ $active ? 'show' : '' }}">
    @if(!$active)
      <div class="card-body text-muted d-flex align-items-center justify-content-center">Выбери чат слева</div>
    @else
      @php($badge = $badgeFor($active->channel))
      <div class="card-header d-flex align-items-center justify-content-between">
        <div>
          <div class="d-flex align-items-center gap-2">
            <span class="badge text-bg-secondary">{{ $badge }}</span>
            <div class="fw-semibold">{{ $active->deal?->title ?? ('Сделка #'.$active->deal_id) }}</div>
          </div>
          <div class="text-muted small mt-1">
            external_id: {{ $active->external_id }} • <a href="{{ route('deals.show', $active->deal_id) }}">Открыть сделку</a>
          </div>
        </div>
        <div class="text-muted small">{{ $messages->count() }} сообщений</div>
      </div>

      <div id="chatScroll" class="card-body bg-white" style="overflow-y:auto;">
        <div id="chatMessages" class="d-flex flex-column gap-2">
          @forelse($messages as $m)
            @php($mm = $mediaFor($active, $m))
            <div class="d-flex {{ $m->direction === 'out' ? 'justify-content-end' : 'justify-content-start' }}">
              <div class="p-2 rounded-3 {{ $m->direction === 'out' ? 'bg-primary text-white' : 'bg-light border' }}" style="max-width: 78%;">
                @if(!empty($mm))
                  <div class="d-flex flex-column gap-2 mb-2">
                    @foreach($mm as $it)
                      @php($t = strtolower((string)($it['type'] ?? 'file')))
                      @if(str_contains($t, 'photo') || str_contains($t, 'image'))
                        <img src="{{ $it['url'] }}" class="img-fluid rounded" alt="image">
                      @elseif(str_contains($t, 'video'))
                        <video src="{{ $it['url'] }}" controls class="w-100 rounded"></video>
                      @else
                        <a href="{{ $it['url'] }}" target="_blank" rel="noopener" class="{{ $m->direction === 'out' ? 'text-white' : '' }}">
                          📎 {{ $it['file_name'] ?? 'Файл' }}
                        </a>
                      @endif
                    @endforeach
                  </div>
                @endif

                @if(($m->body ?? '') !== '')
                  <div class="small" style="white-space: pre-wrap;">{{ $m->body }}</div>
                @endif

                <div class="d-flex justify-content-between gap-3 mt-1">
                  <div class="text-muted small" style="opacity: .85;">
                    {{ $m->direction === 'out' ? 'Вы' : ($m->author ?? 'Клиент') }}
                  </div>
                  <div class="text-muted small" style="opacity: .85;">
                    {{ optional($m->created_at)->format('H:i') }}
                    @if($m->direction === 'out') • {{ $m->status ?? 'ok' }} @endif
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
        <form id="sendForm" method="POST" action="{{ route('chats.send', $active) }}" class="d-flex gap-2 align-items-center" enctype="multipart/form-data">
          @csrf
          <label class="btn btn-outline-secondary mb-0" title="Прикрепить файл">
            <i class="bi bi-paperclip"></i>
            <input id="sendMedia" type="file" name="media" class="d-none" accept="image/*,video/*,application/pdf">
          </label>
          <input id="sendInput" name="text" class="form-control" placeholder="Сообщение…" autocomplete="off" maxlength="4000">
          <button class="btn btn-primary" type="submit">Отправить</button>
        </form>
        <div id="sendError" class="text-danger small mt-2" style="display:none"></div>
      </div>
    @endif
  </div>
</div>
@endsection

@push('scripts')
@if($active)
<script>
(function() {
  const pollUrl = @json(route('chats.poll', $active));
  const sendUrl = @json(route('chats.send', $active));
  const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

  const scrollEl = document.getElementById('chatScroll');
  const listEl = document.getElementById('chatMessages');
  const formEl = document.getElementById('sendForm');
  const inputEl = document.getElementById('sendInput');
  const mediaEl = document.getElementById('sendMedia');
  const errEl = document.getElementById('sendError');

  let lastId = @json($messages->last()?->id ?? 0);

  function scrollToBottom() {
    scrollEl.scrollTop = scrollEl.scrollHeight;
  }

  function escapeHtml(s) {
    return (s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function renderMedia(items, isOut) {
    if (!items || !items.length) return '';
    return items.map(it => {
      const t = (it.type || 'file').toLowerCase();
      const url = it.url;
      if (!url) return '';
      if (t.includes('photo') || t.includes('image')) {
        return `<img src="${escapeHtml(url)}" class="img-fluid rounded" alt="image">`;
      }
      if (t.includes('video')) {
        return `<video src="${escapeHtml(url)}" controls class="w-100 rounded"></video>`;
      }
      const name = it.file_name || 'Файл';
      return `<a href="${escapeHtml(url)}" target="_blank" rel="noopener" class="${isOut ? 'text-white' : ''}">📎 ${escapeHtml(name)}</a>`;
    }).join('');
  }

  function renderMessage(m) {
    const wrap = document.createElement('div');
    wrap.className = 'd-flex ' + (m.direction === 'out' ? 'justify-content-end' : 'justify-content-start');
    const isOut = m.direction === 'out';

    const mediaHtml = renderMedia(m.media || [], isOut);
    const bodyHtml = m.body ? `<div class="small" style="white-space: pre-wrap;">${escapeHtml(m.body)}</div>` : '';

    wrap.innerHTML = `
      <div class="p-2 rounded-3 ${isOut ? 'bg-primary text-white' : 'bg-light border'}" style="max-width: 78%;">
        ${mediaHtml ? `<div class="d-flex flex-column gap-2 mb-2">${mediaHtml}</div>` : ''}
        ${bodyHtml}
        <div class="d-flex justify-content-between gap-3 mt-1">
          <div class="text-muted small" style="opacity:.85;">${isOut ? 'Вы' : (escapeHtml(m.author || 'Клиент'))}</div>
          <div class="text-muted small" style="opacity:.85;">${(m.created_at || '').toString().slice(11,16)}${isOut ? ` • ${(m.status || 'ok')}` : ''}</div>
        </div>
      </div>
    `;

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
    } catch (e) {}
  }

  formEl.addEventListener('submit', async (ev) => {
    ev.preventDefault();
    errEl.style.display = 'none';

    const text = (inputEl.value || '').trim();
    const file = mediaEl.files && mediaEl.files[0] ? mediaEl.files[0] : null;
    if (!text && !file) return;

    const fd = new FormData();
    fd.append('_token', csrf);
    if (text) fd.append('text', text);
    if (file) fd.append('media', file);

    inputEl.value = '';
    mediaEl.value = '';
    inputEl.focus();

    try {
      const r = await fetch(sendUrl, {
        method: 'POST',
        headers: { 'Accept': 'application/json' },
        body: fd,
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

  scrollToBottom();
  setInterval(poll, 2000);
})();
</script>
@endif
@endpush
