{{-- список чатов: страница «Чаты» и его автообновление (ChatController::list, каждые 5 с) --}}
@php
  $markAllReadLabel = "\u{041F}\u{0440}\u{043E}\u{0447}\u{0438}\u{0442}\u{0430}\u{0442}\u{044C} \u{0432}\u{0441}\u{0435}";
  $unreadSummaryText = ($unreadConversationCount ?? 0) > 0
    ? "\u{041D}\u{0435}\u{043F}\u{0440}\u{043E}\u{0447}\u{0438}\u{0442}\u{0430}\u{043D}\u{043E}: ".($unreadConversationCount ?? 0)." \u{0447}\u{0430}\u{0442}\u{043E}\u{0432}, ".($unreadMessageCount ?? 0)." \u{0441}\u{043E}\u{043E}\u{0431}\u{0449}\u{0435}\u{043D}\u{0438}\u{0439}"
    : "\u{041D}\u{0435}\u{043F}\u{0440}\u{043E}\u{0447}\u{0438}\u{0442}\u{0430}\u{043D}\u{043D}\u{044B}\u{0445} \u{0447}\u{0430}\u{0442}\u{043E}\u{0432} \u{043D}\u{0435}\u{0442}";
@endphp
@if($nd)
    <div class="nd-chat-listhd">
      <span>{{ $unreadSummaryText }}</span>
      @if(($unreadConversationCount ?? 0) > 0)
        <form method="POST" action="{{ route('chats.read-all') }}">
          @csrf
          @if($activeId)
            <input type="hidden" name="c" value="{{ $activeId }}">
          @endif
          <button type="submit" class="nd-btn nd-sm">{{ $markAllReadLabel }}</button>
        </form>
      @endif
    </div>
    @forelse($conversations as $c)
      @php
        $ndLast = $c->lastMessage;
        $ndSrc = \App\Support\Ui\Nordika::source(\App\Support\Ui\Nordika::chatSourceKey($c));   // цвет кружка — как у чипа источника на канбане
        $ndName = trim((string) $c->display_title);
      @endphp
      <a href="{{ route('chats.index', ['c' => $c->id]) }}" @class(['nd-chat-i', 'nd-on' => (int) $c->id === (int) $activeId]) data-conv="{{ $c->id }}" data-at="{{ $c->last_message_at?->timestamp }}">
        <div class="nd-ca" style="background:{{ $ndSrc[1] }}">{{ mb_strtoupper(mb_substr($ndName !== '' ? $ndName : '?', 0, 1)) }}</div>
        <div class="nd-chat-txt">
          <div class="nd-cn"><span class="nd-cn-name">{{ $ndName !== '' ? $ndName : 'Без имени' }}</span><span class="nd-tm">{{ $c->last_message_at ? ($c->last_message_at->isToday() ? $c->last_message_at->format('H:i') : $c->last_message_at->format('d.m')) : '' }}</span></div>
          <div class="nd-cm"><span class="nd-cm-text">{{ $ndLast?->direction === 'out' ? 'Вы: ' : '' }}{{ \Illuminate\Support\Str::limit($ndLast?->body ?? '—', 80) }}</span>@if(($c->unread_count ?? 0) > 0)<span class="nd-un">{{ $c->unread_count }}</span>@endif</div>
          <div class="nd-chat-src">{{ $c->display_subtitle ?: $c->source_label }}</div>
        </div>
      </a>
    @empty
      <div class="nd-empty nd-empty-sm"><b>Пока нет диалогов</b>Как только кто-то напишет из VK, Telegram или Avito, чат появится здесь.</div>
    @endforelse
    @if($conversations->hasPages())
      <div class="nd-chat-pager">{{ $conversations->onEachSide(1)->links('pagination.nordika') }}</div>
    @endif
@else
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
               class="list-group-item list-group-item-action d-flex justify-content-between align-items-start ccrm-chat-item {{ $c->source_surface_class }} {{ (int)$c->id === (int)$activeId ? 'active' : '' }}"
               data-conv="{{ $c->id }}" data-at="{{ $c->last_message_at?->timestamp }}">
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
@endif
