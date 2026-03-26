@extends('layouts.app')

@push('styles')
<style>
  .ccrm-chat-wrap {
    height: calc(100vh - 112px);
    min-height: 600px;
    align-items: stretch;
  }
  .ccrm-chat-list {
    width: 380px;
    min-height: 0;
  }
  .ccrm-chat-list .card-body {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
  }
  .ccrm-chat-pane {
    min-height: 0;
    height: 100%;
  }
  .ccrm-chat-pane #chatScroll {
    flex: 1 1 auto;
    min-height: 0;
    overflow-y: auto;
  }
  .ccrm-chat-item.active { background: rgba(79,70,229,.08); }
  .ccrm-chat-bubble-out { background: linear-gradient(135deg, rgba(79,70,229,.95), rgba(59,130,246,.92)); color: #fff; }
  .ccrm-chat-bubble-in { background: rgba(255,255,255,.78); }
  @media (max-width: 992px) {
    .ccrm-chat-wrap {
      height: auto;
      min-height: auto;
    }
    .ccrm-chat-list { width: 100%; }
    .ccrm-chat-pane {
      display: none;
      height: auto;
    }
    .ccrm-chat-pane.show { display: flex; }
  }
</style>
@endpush

@section('content')
@php
  $active = $activeConversation;
  $activeId = $active?->id;

  $displayAuthor = function($message, $conversation) {
    $author = trim((string) ($message->author ?? ''));
    $lower = mb_strtolower($author);
    foreach (['vk:', 'tg:', 'avito:', 'telegram:', 'user ', 'id '] as $prefix) {
      if (str_starts_with($lower, $prefix)) {
        return $conversation?->lead_name ?? 'Клиент';
      }
    }
    return $author !== '' ? $author : ($conversation?->lead_name ?? 'Клиент');
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
          'file_name' => $it['file_name'] ?? ($it['title'] ?? null),
        ];
      }
    }
    return $out;
  };

  $markAllReadLabel = "\u{041F}\u{0440}\u{043E}\u{0447}\u{0438}\u{0442}\u{0430}\u{0442}\u{044C} \u{0432}\u{0441}\u{0435}";
  $unreadSummaryText = ($unreadConversationCount ?? 0) > 0
    ? "\u{041D}\u{0435}\u{043F}\u{0440}\u{043E}\u{0447}\u{0438}\u{0442}\u{0430}\u{043D}\u{043E}: ".($unreadConversationCount ?? 0)." \u{0447}\u{0430}\u{0442}\u{043E}\u{0432}, ".($unreadMessageCount ?? 0)." \u{0441}\u{043E}\u{043E}\u{0431}\u{0449}\u{0435}\u{043D}\u{0438}\u{0439}"
    : "\u{041D}\u{0435}\u{043F}\u{0440}\u{043E}\u{0447}\u{0438}\u{0442}\u{0430}\u{043D}\u{043D}\u{044B}\u{0445} \u{0447}\u{0430}\u{0442}\u{043E}\u{0432} \u{043D}\u{0435}\u{0442}";
@endphp

<div class="d-flex gap-3 ccrm-chat-wrap flex-column flex-lg-row">
  <div class="card shadow-sm ccrm-chat-list flex-shrink-0 d-flex flex-column">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div class="fw-semibold">Чаты</div>
      @if($active)
        <a class="btn btn-sm btn-outline-secondary d-lg-none" href="{{ route('chats.index') }}">Список</a>
      @endif
    </div>
    <div class="px-3 py-2 border-bottom d-flex align-items-center justify-content-between gap-2 flex-wrap">
      <div class="text-muted small">{{ $unreadSummaryText }}</div>
      @if(($unreadConversationCount ?? 0) > 0)
        <form method="POST" action="{{ route('chats.read-all') }}" class="m-0">
          @csrf
          @if($activeId)
            <input type="hidden" name="c" value="{{ $activeId }}">
          @endif
          <button type="submit" class="btn btn-sm btn-outline-primary">
            <i class="bi bi-check2-all me-1"></i>{{ $markAllReadLabel }}
          </button>
        </form>
      @endif
    </div>
    <div class="card-body p-0">
      @if($conversations->count() === 0)
        <div class="p-4 text-muted">Пока нет диалогов. Как только кто-то напишет из VK, Telegram или Avito, чат появится здесь.</div>
      @else
        <div class="list-group list-group-flush">
          @foreach($conversations as $c)
            @php($last = $c->lastMessage)
            <a href="{{ route('chats.index', ['c' => $c->id]) }}"
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-start ccrm-chat-item {{ $c->source_surface_class }} {{ (int)$c->id === (int)$activeId ? 'active' : '' }}">
              <div class="me-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  {!! $c->source_icon_html !!}
                  <div class="fw-semibold">{{ $c->display_title }}</div>
                  <span class="{{ $c->source_badge_class }}">{{ $c->source_label }}</span>
                </div>
                <div class="text-muted small mt-1">{{ $c->display_subtitle }}</div>
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
      <div class="card-footer bg-transparent">
        {{ $conversations->links() }}
      </div>
    @endif
  </div>

  <div class="card shadow-sm flex-grow-1 d-flex flex-column ccrm-chat-pane {{ $active ? 'show' : '' }}">
    @if(!$active)
      <div class="card-body text-muted d-flex align-items-center justify-content-center">Выбери чат слева</div>
    @else
      <div class="card-header d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
          <div class="d-flex align-items-center gap-2 flex-wrap">
            {!! $active->source_icon_html !!}
            <div class="fw-semibold">{{ $active->display_title }}</div>
            <span class="{{ $active->source_badge_class }}">{{ $active->source_label }}</span>
          </div>
          <div class="text-muted small mt-1">
            {{ $active->display_subtitle }}
            @if($active->external_id)
              • id: {{ $active->external_id }}
            @endif
            • <a href="{{ route('deals.show', $active->deal_id) }}">Открыть сделку</a>
          </div>
        </div>
        <div class="text-muted small">{{ $messages->count() }} сообщений</div>
      </div>

      <div id="chatScroll" class="card-body">
        <div id="chatMessages" class="d-flex flex-column gap-2">
          @forelse($messages as $m)
            @php($mm = $mediaFor($active, $m))
            <div class="d-flex {{ $m->direction === 'out' ? 'justify-content-end' : 'justify-content-start' }}">
              <div class="p-2 rounded-3 {{ $m->direction === 'out' ? 'ccrm-chat-bubble-out' : 'ccrm-chat-bubble-in border' }}" style="max-width: 78%;">
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
                    {{ $m->direction === 'out' ? 'Вы' : $displayAuthor($m, $active) }}
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

      <div class="card-footer bg-transparent">
        <form id="sendForm" method="POST" action="{{ route('chats.send', $active) }}" class="d-flex flex-column gap-2" enctype="multipart/form-data">
          @csrf
          <div class="d-flex gap-2 align-items-center">
            <label class="btn btn-outline-secondary mb-0" title="Прикрепить файл">
              <i class="bi bi-paperclip me-1"></i>Файлы
              <input id="sendMedia" type="file" name="media[]" class="d-none" accept="*/*" multiple>
            </label>
            <input id="sendInput" name="text" class="form-control" placeholder="Сообщение или подпись к первому файлу…" autocomplete="off" maxlength="4000">
            <button class="btn btn-primary" type="submit">Отправить</button>
          </div>
          <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
            <div id="sendFilesInfo" class="small text-muted">Можно отправлять текст, фото, видео и файлы.</div>
            <button id="sendFilesClear" type="button" class="btn btn-sm btn-outline-secondary d-none">Очистить файлы</button>
          </div>
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
  const filesInfoEl = document.getElementById('sendFilesInfo');
  const clearFilesEl = document.getElementById('sendFilesClear');
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
      <div class="p-2 rounded-3 ${isOut ? 'ccrm-chat-bubble-out' : 'ccrm-chat-bubble-in border'}" style="max-width: 78%;">
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
    const files = mediaEl.files ? Array.from(mediaEl.files) : [];
    if (!text && !files.length) return;

    const fd = new FormData();
    fd.append('_token', csrf);
    if (text) fd.append('text', text);
    files.forEach(file => fd.append('media[]', file));

    inputEl.value = '';
    mediaEl.value = '';
    updateFilesInfo();
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

      const msgs = Array.isArray(j.messages) && j.messages.length ? j.messages : (j.message ? [j.message] : []);
      msgs.forEach((msg) => {
        listEl.appendChild(renderMessage(msg));
        lastId = Math.max(lastId, Number(msg.id || 0));
      });
      if (msgs.length) {
        scrollToBottom();
      }
    } catch (e) {
      errEl.textContent = e.message;
      errEl.style.display = 'block';
    }
  });

  function updateFilesInfo() {
    const files = mediaEl.files ? Array.from(mediaEl.files) : [];
    if (!files.length) {
      filesInfoEl.textContent = 'Можно отправлять текст, фото, видео и файлы.';
      clearFilesEl.classList.add('d-none');
      return;
    }

    filesInfoEl.textContent = 'Выбрано файлов: ' + files.length + ' — ' + files.map(f => f.name).join(', ');
    clearFilesEl.classList.remove('d-none');
  }

  mediaEl.addEventListener('change', updateFilesInfo);
  clearFilesEl.addEventListener('click', () => {
    mediaEl.value = '';
    updateFilesInfo();
  });

  updateFilesInfo();
  scrollToBottom();
  setInterval(poll, 2000);
})();
</script>
@endif
@endpush
