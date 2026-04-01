<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        :root {
            --crm-bg: linear-gradient(135deg, #f5f7fb 0%, #eef4ff 45%, #f7fbff 100%);
            --crm-surface: rgba(255,255,255,.88);
            --crm-surface-strong: rgba(255,255,255,.96);
            --crm-border: rgba(15, 23, 42, .08);
            --crm-shadow: 0 20px 45px rgba(15, 23, 42, .08);
            --crm-text: #18212f;
            --crm-muted: #64748b;
            --crm-navbar: linear-gradient(90deg, #1f2937 0%, #0f172a 100%);
            --crm-accent: #4f46e5;
        }

        body[data-theme="sunset"] {
            --crm-bg: radial-gradient(circle at top right, rgba(251, 191, 36, .18), transparent 30%), linear-gradient(135deg, #fff7ed 0%, #fee2e2 45%, #fdf2f8 100%);
            --crm-surface: rgba(255,255,255,.9);
            --crm-surface-strong: rgba(255,255,255,.96);
            --crm-border: rgba(190, 24, 93, .10);
            --crm-shadow: 0 20px 45px rgba(190, 24, 93, .10);
            --crm-text: #3f1d2e;
            --crm-muted: #7c5166;
            --crm-navbar: linear-gradient(90deg, #7c2d12 0%, #9d174d 100%);
            --crm-accent: #e11d48;
        }

        body[data-theme="forest"] {
            --crm-bg: radial-gradient(circle at top left, rgba(16, 185, 129, .18), transparent 30%), linear-gradient(135deg, #effdf5 0%, #ecfdf3 40%, #f0fdf4 100%);
            --crm-surface: rgba(255,255,255,.88);
            --crm-surface-strong: rgba(255,255,255,.95);
            --crm-border: rgba(22, 101, 52, .10);
            --crm-shadow: 0 20px 45px rgba(22, 101, 52, .10);
            --crm-text: #163423;
            --crm-muted: #4d705d;
            --crm-navbar: linear-gradient(90deg, #14532d 0%, #166534 100%);
            --crm-accent: #059669;
        }

        body[data-theme="night"] {
            --crm-bg: radial-gradient(circle at top right, rgba(99, 102, 241, .20), transparent 28%), linear-gradient(135deg, #0f172a 0%, #111827 45%, #1e293b 100%);
            --crm-surface: rgba(15,23,42,.78);
            --crm-surface-strong: rgba(15,23,42,.92);
            --crm-border: rgba(148, 163, 184, .18);
            --crm-shadow: 0 20px 45px rgba(2, 6, 23, .45);
            --crm-text: #e5eefb;
            --crm-muted: #a7b3c8;
            --crm-navbar: linear-gradient(90deg, #020617 0%, #1e1b4b 100%);
            --crm-accent: #818cf8;
        }

        body {
            position: relative;
            isolation: isolate;
            min-height: 100vh;
            background: var(--crm-bg);
            color: var(--crm-text);
            background-attachment: fixed;
        }

        body::before {
            content: "";
            position: fixed;
            inset: 0;
            pointer-events: none;
            background: radial-gradient(circle at 15% 15%, rgba(255,255,255,.35), transparent 22%), radial-gradient(circle at 85% 8%, rgba(255,255,255,.22), transparent 18%);
            z-index: -1;
        }

        .navbar {
            position: sticky;
            top: 0;
            z-index: 1030;
            backdrop-filter: blur(14px);
            background: var(--crm-navbar) !important;
            box-shadow: 0 12px 30px rgba(2, 6, 23, .18);
        }

        .navbar .btn-outline-light,
        .navbar .btn-outline-warning,
        .navbar .btn-outline-info {
            border-color: rgba(255,255,255,.28);
        }

        main.container-fluid {
            position: relative;
        }

        .toast-container {
            position: relative;
            z-index: 1080;
        }

        .card,
        .modal-content,
        .list-group-item,
        .dropdown-menu,
        .table,
        .alert {
            background-color: var(--crm-surface);
            color: var(--crm-text);
            border-color: var(--crm-border);
        }

        .card,
        .modal-content,
        .dropdown-menu,
        .toast {
            border: 1px solid var(--crm-border);
            box-shadow: var(--crm-shadow);
            border-radius: 1rem;
        }

        .card-header,
        .card-footer,
        .list-group-item,
        .table > :not(caption) > * > * {
            background-color: transparent;
            border-color: var(--crm-border);
            color: inherit;
        }

        .table-striped > tbody > tr:nth-of-type(odd) > * {
            --bs-table-accent-bg: rgba(148, 163, 184, .08);
            color: inherit;
        }

        .text-muted,
        .form-text,
        .small.text-muted {
            color: var(--crm-muted) !important;
        }

        .form-control,
        .form-select,
        .btn-outline-secondary,
        .btn-outline-primary,
        .btn-outline-success,
        .btn-outline-danger {
            border-color: var(--crm-border);
        }

        .form-control,
        .form-select {
            background-color: var(--crm-surface-strong);
            color: var(--crm-text);
        }

        .form-control::placeholder {
            color: color-mix(in srgb, var(--crm-muted) 75%, transparent);
        }

        .source-badge {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .35rem .65rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .72rem;
            letter-spacing: .02em;
        }

        .source-badge-vk { background: rgba(0, 119, 255, .18); color: #0a66ff; }
        .source-badge-telegram { background: rgba(0, 136, 204, .16); color: #0ea5e9; }
        .source-badge-avito { background: rgba(151, 71, 255, .18); color: #8b5cf6; }
        .source-badge-bitrix { background: rgba(37, 99, 235, .16); color: #1d4ed8; }
        .source-badge-tilda { background: rgba(249, 115, 22, .16); color: #c2410c; }
        .source-badge-megafon_vats { background: rgba(34, 197, 94, .16); color: #166534; }
        .source-badge-default { background: rgba(100, 116, 139, .15); color: #334155; }

        .source-surface {
            border-left: 4px solid transparent;
        }

        .source-surface-vk { border-left-color: #0077ff; }
        .source-surface-telegram { border-left-color: #0891b2; }
        .source-surface-avito { border-left-color: #8b5cf6; }
        .source-surface-bitrix { border-left-color: #2563eb; }
        .source-surface-tilda { border-left-color: #f97316; }
        .source-surface-megafon_vats { border-left-color: #16a34a; }
        .source-surface-default { border-left-color: #94a3b8; }


        .source-icon {
            width: 1.7rem;
            height: 1.7rem;
            border-radius: 999px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            color: #fff;
            font-weight: 800;
            font-size: .72rem;
            text-transform: uppercase;
            box-shadow: 0 0 0 1px rgba(255,255,255,.08), 0 8px 18px rgba(15, 23, 42, .18);
            flex: 0 0 auto;
        }
        .source-icon i { font-size: .82rem; line-height: 1; }
        .source-icon svg { width: 1.05rem; height: 1.05rem; display: block; }
        .source-icon-vk { background: linear-gradient(135deg, #0ea5ff 0%, #2563eb 100%); text-transform: lowercase; letter-spacing: -.02em; }
        .source-icon-telegram { background: linear-gradient(135deg, #22d3ee 0%, #0284c7 100%); }
        .source-icon-avito { background: linear-gradient(135deg, #a855f7 0%, #7c3aed 100%); }
        .source-icon-bitrix { background: linear-gradient(135deg, #60a5fa 0%, #1d4ed8 100%); }
        .source-icon-tilda { background: linear-gradient(135deg, #fdba74 0%, #f97316 100%); }
        .source-icon-megafon_vats { background: linear-gradient(135deg, #22c55e 0%, #15803d 100%); }
        .source-icon-default { background: linear-gradient(135deg, #94a3b8 0%, #64748b 100%); }

        .theme-dot {
            width: .8rem;
            height: .8rem;
            border-radius: 999px;
            display: inline-block;
            border: 1px solid rgba(255,255,255,.35);
        }

        body[data-role="constructor"] .navbar a[href$="/calendar"],
        body[data-role="constructor"] .navbar a[href$="/nonclosures"],
        body[data-role="constructor"] .navbar a[href$="/reports/monthly"],
        body[data-role="constructor"] .navbar a[href$="/settings/users"],
        body[data-role="constructor"] .navbar a[href$="/settings/integrations"],
        body[data-role="constructor"] .navbar a[href$="/settings/imports/bitrix"],
        body[data-role="constructor"] .navbar a[href$="/notifications"],
        body[data-role="constructor"] .navbar a[href$="/deals/create"],
        body[data-role="constructor"] .navbar a[href$="/deals/kanban"],
        body[data-role="constructor"] .navbar a[href$="/deals"],
        body[data-role="constructor"] .navbar a[href$="/tasks"],
        body[data-role="constructor"] .navbar a[href$="/deals/closed"],
        body[data-role="constructor"] .navbar a[href$="/chats"],
        body[data-role="constructor"] .navbar #enableNotifBtn {
            display: none !important;
        }
    </style>
    @stack('styles')
</head>
<body data-theme="sky" data-role="{{ auth()->user()?->role ?? 'guest' }}">
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        @php($navUser = auth()->user())
        @php($isPriv = in_array($navUser?->role, ['admin','main_operator'], true))
        @php($isAdmin = $navUser?->role === 'admin')
        @php($isConstructor = $navUser?->role === 'constructor')
        @php($isNc = in_array($navUser?->role, ['admin','main_operator','operator'], true))
        @php($isMeasurer = $navUser?->role === 'measurer')
        @php($canUseProjecting = $isConstructor)
        @php($homeRoute = $isMeasurer ? 'calendar.index' : ($isConstructor ? 'ceiling-projects.index' : 'deals.kanban'))
        <a class="navbar-brand fw-semibold" href="{{ route($homeRoute) }}">{{ config('app.name') }}</a>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            @auth
                @if(!$isMeasurer && !$isConstructor)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('deals.kanban') }}">Канбан</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('deals.index') }}">Список</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('tasks.index') }}">Дела</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('deals.closed') }}">Завершённые</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('chats.index') }}">Чаты</a>
                @endif
                <a class="btn btn-sm btn-outline-light" href="{{ route('calendar.index') }}">Календарь</a>
                @if($isNc)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('nonclosures.index') }}">Документы</a>
                @endif
                @if($canUseProjecting)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('ceiling-projects.index') }}">&#1055;&#1088;&#1086;&#1077;&#1082;&#1090;&#1080;&#1088;&#1086;&#1074;&#1082;&#1072;</a>
                @endif
                @if($isAdmin)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('ceiling-projects.index') }}">Проектировка</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.integrations.index') }}">Интеграции</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.imports.bitrix.index') }}">Импорт Bitrix</a>
                @endif
                @if($isPriv)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.users.index') }}">Пользователи</a>
                @endif

                <div class="dropdown">
                    <button class="btn btn-sm btn-outline-light dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="bi bi-palette me-1"></i>Тема
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-2" id="themeMenu">
                        <button class="dropdown-item rounded d-flex align-items-center gap-2 theme-switch" type="button" data-theme="sky"><span class="theme-dot" style="background:#60a5fa"></span> Небо</button>
                        <button class="dropdown-item rounded d-flex align-items-center gap-2 theme-switch" type="button" data-theme="sunset"><span class="theme-dot" style="background:#fb7185"></span> Закат</button>
                        <button class="dropdown-item rounded d-flex align-items-center gap-2 theme-switch" type="button" data-theme="forest"><span class="theme-dot" style="background:#34d399"></span> Лес</button>
                        <button class="dropdown-item rounded d-flex align-items-center gap-2 theme-switch" type="button" data-theme="night"><span class="theme-dot" style="background:#818cf8"></span> Ночь</button>
                    </div>
                </div>

                <a class="btn btn-sm btn-outline-light" href="{{ route('reports.monthly') }}">Отчёты</a>
                @if(!$isMeasurer && !$isConstructor)
                    <a class="btn btn-sm btn-warning position-relative" href="{{ route('notifications.index') }}" title="Уведомления" aria-label="Уведомления">
                        <i class="bi bi-bell-fill"></i>
                        <span id="navNotifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-info d-none" id="enableNotifBtn" title="Системные уведомления">🔊</button>
                    <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}">+ Сделка</a>
                @endif
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
<script>
(() => {
    const storageKey = 'crmTheme';
    const body = document.body;
    const applyTheme = (theme) => {
        body.setAttribute('data-theme', theme || 'sky');
        localStorage.setItem(storageKey, theme || 'sky');
    };

    applyTheme(localStorage.getItem(storageKey) || 'sky');
    document.querySelectorAll('.theme-switch').forEach((btn) => {
        btn.addEventListener('click', () => applyTheme(btn.dataset.theme || 'sky'));
    });
})();
</script>

@auth
@if(!in_array(auth()->user()?->role, ['measurer', 'constructor'], true))
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
            badgeEl.closest('a')?.classList.add('shadow');
        } else {
            badgeEl.classList.add('d-none');
            badgeEl.closest('a')?.classList.remove('shadow');
        }
    };

    const notifStorageKey = `lastNotifId_u{{ auth()->user()->id }}_a{{ auth()->user()->account_id }}`;
    let lastId = Number(localStorage.getItem(notifStorageKey) || '0');

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
                    localStorage.setItem(notifStorageKey, '0');
                }
            }
            setBadge(data.unread_count);

            const items = Array.isArray(data.notifications) ? data.notifications : [];
            for (const it of items) {
                if (!it || !it.id) continue;
                lastId = Math.max(lastId, Number(it.id));
                localStorage.setItem(notifStorageKey, String(lastId));

                try { showToast(it.title || 'Уведомление', it.body || '', it.url || null); } catch (e) {}
                try { showSystem(it.title || 'Уведомление', it.body || '', it.url || null); } catch (e) {}
                try { playBeep(); } catch (e) {}
            }
        } catch (e) {
        }
    };

    poll();
    setInterval(poll, 25000);
})();
</script>
@endif
@endauth

@stack('scripts')
</body>
</html>
