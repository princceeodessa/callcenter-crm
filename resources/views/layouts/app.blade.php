<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('deals.kanban') }}">{{ config('app.name') }}</a>
        <div class="d-flex gap-2 flex-wrap">
            @auth
                @php($isPriv = in_array(auth()->user()?->role, ['admin','main_operator'], true))
                @php($isNc = in_array(auth()->user()?->role, ['admin','main_operator','operator'], true))
                <a class="btn btn-sm btn-outline-light" href="{{ route('deals.kanban') }}">Канбан</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('deals.index') }}">Список</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('deals.closed') }}">Завершённые</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('chats.index') }}">Чаты</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('calendar.index') }}">Календарь</a>
                @if($isNc)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('nonclosures.index') }}">Незаключёнки</a>
                @endif
                @if($isPriv)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.integrations.index') }}">Интеграции</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.users.index') }}">Пользователи</a>
                @endif

                {{-- Notifications: bright bell icon (always visible) --}}
                <a class="btn btn-sm btn-warning position-relative" href="{{ route('notifications.index') }}" title="Уведомления" aria-label="Уведомления">
                    <i class="bi bi-bell-fill"></i>
                    <span id="navNotifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0</span>
                </a>
                <button type="button" class="btn btn-sm btn-outline-info d-none" id="enableNotifBtn" title="Системные уведомления">🔊</button>

                <a class="btn btn-sm btn-outline-light" href="{{ route('reports.monthly') }}">Отчёты</a>
                <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}">+ Сделка</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning">Выйти</button>
                </form>
            @endauth
        </div>
    </div>
</nav>

@auth
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1080;"></div>
@endauth

<main class="container-fluid py-3">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

@auth
<script>
(() => {
    const badgeEl = document.getElementById('navNotifBadge');
    const enableBtn = document.getElementById('enableNotifBtn');

    const supportsSystemNotif = ('Notification' in window);
    const supportsAudio = ('AudioContext' in window) || ('webkitAudioContext' in window);

    let audioCtx = null;
    let audioEnabled = false;

    const ensureAudio = async () => {
        if (!supportsAudio) return;
        if (!audioCtx) audioCtx = new (window.AudioContext || window.webkitAudioContext)();
        if (audioCtx.state === 'suspended') {
            try { await audioCtx.resume(); } catch (e) {}
        }
        audioEnabled = true;
    };

    const playBeep = async () => {
        if (!supportsAudio) return;
        await ensureAudio();
        if (!audioCtx || !audioEnabled) return;
        try {
            const o = audioCtx.createOscillator();
            const g = audioCtx.createGain();
            o.type = 'sine';
            o.frequency.value = 880;
            g.gain.value = 0.06;
            o.connect(g);
            g.connect(audioCtx.destination);
            o.start();
            o.stop(audioCtx.currentTime + 0.15);
        } catch (e) {}
    };

    // One user gesture is usually required by browsers to enable sound.
    document.addEventListener('click', () => { ensureAudio(); }, { once: true });

    const requestSystemPermission = async () => {
        if (!supportsSystemNotif) return;
        try {
            const res = await Notification.requestPermission();
            if (res === 'granted') {
                if (enableBtn) enableBtn.classList.add('d-none');
            }
        } catch (e) {}
    };

    if (supportsSystemNotif && Notification.permission === 'default') {
        if (enableBtn) {
            enableBtn.classList.remove('d-none');
            enableBtn.addEventListener('click', requestSystemPermission);
        }
    }

    const showToast = (title, body, url) => {
        const container = document.getElementById('toastContainer');
        if (!container) return;

        const el = document.createElement('div');
        el.className = 'toast';
        el.setAttribute('role','alert');
        el.setAttribute('aria-live','assertive');
        el.setAttribute('aria-atomic','true');
        el.innerHTML = `
          <div class="toast-header">
            <strong class="me-auto">${title}</strong>
            <small class="text-muted">сейчас</small>
            <button type="button" class="btn-close" data-bs-dismiss="toast" aria-label="Close"></button>
          </div>
          <div class="toast-body">
            <div>${(body || '').replace(/</g,'&lt;').replace(/>/g,'&gt;')}</div>
            ${url ? `<div class="mt-2"><a class="btn btn-sm btn-primary" href="${url}">Открыть</a></div>` : ''}
          </div>
        `;
        container.appendChild(el);
        const t = new bootstrap.Toast(el, { delay: 8000 });
        t.show();
        el.addEventListener('hidden.bs.toast', () => el.remove());
    };

    const showSystem = (title, body, url) => {
        if (!supportsSystemNotif) return;
        if (Notification.permission !== 'granted') return;
        try {
            const n = new Notification(title, { body: body || '' });
            if (url) {
                n.onclick = () => { window.focus(); window.location.href = url; };
            }
        } catch (e) {}
    };

    const setBadge = (count) => {
        if (!badgeEl) return;
        const c = Number(count || 0);
        if (c > 0) {
            badgeEl.textContent = String(c);
            badgeEl.classList.remove('d-none');
            // a subtle pulse to make the bell noticeable
            badgeEl.closest('a')?.classList.add('shadow');
        } else {
            badgeEl.classList.add('d-none');
            badgeEl.closest('a')?.classList.remove('shadow');
        }
    };

    const storageKey = `lastNotifId_u{{ auth()->user()->id }}_a{{ auth()->user()->account_id }}`;
    let lastId = Number(localStorage.getItem(storageKey) || '0');

    const poll = async () => {
        try {
            const res = await fetch(`{{ route('notifications.poll') }}?after_id=${lastId}`, {
                headers: { 'Accept': 'application/json' }
            });
            if (!res.ok) return;
            const data = await res.json();
            if (data && typeof data.max_id !== 'undefined') {
                const maxId = Number(data.max_id || 0);
                if (lastId > maxId) {
                    lastId = 0;
                    localStorage.setItem(storageKey, '0');
                }
            }
            setBadge(data.unread_count);

            const items = Array.isArray(data.notifications) ? data.notifications : [];
            for (const it of items) {
                if (!it || !it.id) continue;
                lastId = Math.max(lastId, Number(it.id));
                localStorage.setItem(storageKey, String(lastId));

                try { showToast(it.title || 'Уведомление', it.body || '', it.url || null); } catch (e) {}
                try { showSystem(it.title || 'Уведомление', it.body || '', it.url || null); } catch (e) {}
                try { playBeep(); } catch (e) {}
            }
        } catch (e) {
            // ignore
        }
    };

    // Initial + interval
    poll();
    setInterval(poll, 25000);
})();
</script>
@endauth

@stack('scripts')
</body>
</html>