<!doctype html>
@php($isSneakerSpace = in_array(auth()->user()?->role, ['sneaker_head','sneaker_operator','sneaker_owner'], true))
{{-- редизайн «Nordika» (архив дизайнера 26.10.2026): каркас с боковым меню и светлой/тёмной темой — у сотрудников потолков --}}
@php($nd = \App\Support\Ui\Nordika::enabled())
{{-- сделка во всплывающем окне: страница в iframe (и всё, куда она ведёт после форм) — без меню и верхней панели --}}
@php($ndEmbed = $nd && (request()->header('Sec-Fetch-Dest') === 'iframe' || request()->boolean('embed')))
<html lang="ru" @if($nd) class="nd-html{{ $ndEmbed ? ' nd-embed' : '' }}" data-theme="light" data-bs-theme="light" @endif>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    @if($nd)
        <script>try{if(localStorage.getItem('ndTheme')==='dark'){document.documentElement.setAttribute('data-theme','dark');document.documentElement.setAttribute('data-bs-theme','dark');}}catch(e){}</script>
    @endif
    @if($isSneakerSpace)
        <link rel="apple-touch-icon" href="{{ asset('icons/sneaker-touch-icon-180.png') }}">
        <link rel="icon" type="image/png" href="{{ asset('icons/sneaker-favicon-32.png') }}">
        <meta name="apple-mobile-web-app-title" content="Кроссовки">
    @endif
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

        body[data-role="documents_operator"] .navbar a[href$="/calendar"],
        body[data-role="documents_operator"] .navbar a[href$="/reports/monthly"],
        body[data-role="documents_operator"] .navbar a[href$="/settings/users"],
        body[data-role="documents_operator"] .navbar a[href$="/settings/integrations"],
        body[data-role="documents_operator"] .navbar a[href$="/settings/imports/bitrix"],
        body[data-role="documents_operator"] .navbar a[href$="/deals/create"],
        body[data-role="documents_operator"] .navbar a[href$="/deals/kanban"],
        body[data-role="documents_operator"] .navbar a[href$="/deals"],
        body[data-role="documents_operator"] .navbar a[href$="/deals/closed"],
        body[data-role="documents_operator"] .navbar a[href$="/chats"],
        body[data-role="documents_operator"] .navbar a[href$="/ceiling-projects"] {
            display: none !important;
        }

        body[data-role="sneaker_head"] .navbar a[href$="/calendar"],
        body[data-role="sneaker_head"] .navbar a[href$="/nonclosures"],
        body[data-role="sneaker_head"] .navbar a[href$="/ceiling-projects"],
        body[data-role="sneaker_head"] .navbar a[href$="/chats"],
        body[data-role="sneaker_head"] .navbar a[href$="/settings/integrations"],
        body[data-role="sneaker_head"] .navbar a[href$="/settings/imports/bitrix"],
        body[data-role="sneaker_operator"] .navbar a[href$="/calendar"],
        body[data-role="sneaker_operator"] .navbar a[href$="/nonclosures"],
        body[data-role="sneaker_operator"] .navbar a[href$="/ceiling-projects"],
        body[data-role="sneaker_operator"] .navbar a[href$="/chats"],
        body[data-role="sneaker_operator"] .navbar a[href$="/settings/integrations"],
        body[data-role="sneaker_operator"] .navbar a[href$="/settings/imports/bitrix"],
        body[data-role="sneaker_operator"] .navbar a[href$="/settings/users"] {
            display: none !important;
        }
    </style>
    @if($nd)
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link href="{{ asset('css/nordika-crm.css') }}?v={{ @filemtime(public_path('css/nordika-crm.css')) }}" rel="stylesheet">
        <link href="{{ asset('css/nordika-crm-app.css') }}?v={{ @filemtime(public_path('css/nordika-crm-app.css')) }}" rel="stylesheet">
        {{-- календарь выбора дня/периода из дизайн-системы (как в сводке владельца) — в панелях фильтров --}}
        <link href="{{ asset('css/bd-daterange.css') }}?v={{ @filemtime(public_path('css/bd-daterange.css')) }}" rel="stylesheet">
    @endif
    @stack('styles')
</head>
<body data-theme="sky" data-role="{{ auth()->user()?->role ?? 'guest' }}">
        @php($navUser = auth()->user())
        @php($isPriv = in_array($navUser?->role, ['admin','main_operator'], true))
        @php($isAdmin = $navUser?->role === 'admin')
        @php($isConstructor = $navUser?->role === 'constructor')
        @php($isDocumentsOperator = $navUser?->role === 'documents_operator')
        @php($isNc = in_array($navUser?->role, ['admin','main_operator','operator','documents_operator'], true))
        @php($isMeasurer = $navUser?->role === 'measurer')
        @php($isSneaker = in_array($navUser?->role, ['sneaker_head','sneaker_operator'], true))
        @php($isSneakerHead = $navUser?->role === 'sneaker_head')
        @php($isSneakerOwner = $navUser?->role === 'sneaker_owner')
        @php($lowStock = $isSneaker ? \App\Models\WarehouseItem::where('account_id', $navUser->account_id)->where('low_stock_threshold', '>', 0)->whereRaw('(quantity - reserved) <= low_stock_threshold')->count() : 0)
        @php($canUseProjecting = $isConstructor)
        @php($homeRoute = $isSneakerOwner ? 'owner.dashboard' : ($isMeasurer ? 'calendar.index' : ($isConstructor ? 'ceiling-projects.index' : ($isDocumentsOperator ? 'nonclosures.index' : ($isSneaker ? 'sale.quick' : 'deals.kanban')))))
@if($ndEmbed)
    <div class="nd-embed-page">
        @include('partials.flash')
        @yield('content')
    </div>
@elseif($nd)
    @php([$ndTitle, $ndSub] = \App\Support\Ui\Nordika::title())
    @php($ndDesk = in_array($navUser->role, ['admin','main_operator','operator'], true))
    @php($ndBell = in_array($navUser->role, ['admin','main_operator','operator','documents_operator'], true))
    @php($ndFull = trim($__env->yieldContent('nd_fullbleed')) !== '')
    <div class="nd-app">
        <aside class="nd-side" id="ndSide">
            <a class="nd-brand" href="{{ route($homeRoute) }}"><div class="nd-lg">Б</div><div><div class="nd-bt">БлагоДар</div><div class="nd-bs">Callcenter CRM</div></div></a>
            @foreach(\App\Support\Ui\Nordika::nav($navUser) as $ndGroup => $ndItems)
                <div class="nd-nav-h">{{ $ndGroup }}</div>
                <nav class="nd-nav">
                    @foreach($ndItems as [$ndRoute, $ndLabel, $ndIcon, $ndActive])
                        <a href="{{ route($ndRoute) }}" @class(['nd-active' => request()->routeIs($ndActive)])><svg class="nd-icon" viewBox="0 0 24 24">{!! \App\Support\Ui\Nordika::ICONS[$ndIcon] !!}</svg>{{ $ndLabel }}</a>
                    @endforeach
                </nav>
            @endforeach
            <div class="nd-sp"></div>
            <div class="nd-side-user">
                {!! \App\Support\Ui\Nordika::avatar($navUser->name, 34) !!}
                <div class="min-w-0"><div class="nd-un">{{ \App\Support\Ui\Nordika::shortName($navUser->name) }}</div><div class="nd-ur">{{ \App\Support\Ui\Nordika::roleLabel($navUser->role) }}</div></div>
                <a class="nd-out" href="{{ route('logout') }}" title="Выйти" aria-label="Выйти"><svg class="nd-icon nd-sm" viewBox="0 0 24 24">{!! \App\Support\Ui\Nordika::ICONS['logout'] !!}</svg></a>
            </div>
        </aside>
        <main class="nd-main">
            <header class="nd-top">
                <button type="button" class="nd-btn nd-icon-only nd-burger" id="ndBurger" aria-label="Меню"><svg class="nd-icon" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
                <h1>@hasSection('nd_title')@yield('nd_title')@else{{ $ndTitle }}@endif @hasSection('nd_sub')<span class="nd-sub">@yield('nd_sub')</span>@elseif($ndSub !== '')<span class="nd-sub">{{ $ndSub }}</span>@endif</h1>
                <div class="nd-grow"></div>
                @hasSection('nd_search')
                    @yield('nd_search')
                @elseif($ndDesk)
                    <form class="nd-search" method="GET" action="{{ route('deals.index') }}" role="search">
                        <svg class="nd-icon nd-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
                        <input type="hidden" name="status" value="all">
                        <input type="search" name="q" value="{{ request()->routeIs('deals.index') ? request('q') : '' }}" placeholder="Поиск по имени, телефону, сделке">
                    </form>
                @endif
                <button type="button" class="nd-btn nd-icon-only" id="ndThemeBtn" title="Светлая / тёмная тема" aria-label="Тема"><span class="nd-theme-ic"><svg class="nd-icon nd-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/></svg><svg class="nd-icon nd-moon" viewBox="0 0 24 24"><path d="M21 13a8.5 8.5 0 01-10-10A8.5 8.5 0 1021 13z"/></svg></span></button>
                @if($ndBell)
                    {{-- звук уведомлений: включить/выключить (запоминается в браузере); при включении — разрешение на системные --}}
                    <button type="button" class="nd-btn nd-icon-only" id="ndSoundBtn" title="Звук уведомлений включён — нажмите, чтобы выключить" aria-label="Звук уведомлений" aria-pressed="false">
                        <svg class="nd-icon nd-snd-on" viewBox="0 0 24 24"><path d="M11 5L6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 010 7M18.5 5.5a9 9 0 010 13"/></svg>
                        <svg class="nd-icon nd-snd-off" viewBox="0 0 24 24"><path d="M11 5L6 9H3v6h3l5 4z"/><path d="M22 9l-6 6M16 9l6 6"/></svg>
                    </button>
                    <div class="nd-notif-wrap">
                        <a class="nd-btn nd-icon-only" href="{{ route('notifications.index') }}" id="ndBellBtn" title="Уведомления" aria-label="Уведомления" aria-haspopup="true"><svg class="nd-icon" viewBox="0 0 24 24"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/></svg><span id="navNotifBadge" class="nd-badge d-none">0</span></a>
                        <div class="nd-notif-pop" id="ndNotifPop" hidden>
                            <div class="nd-notif-hd"><b>Уведомления</b><button type="button" class="nd-notif-all" id="ndNotifReadAll">Прочитать все</button></div>
                            <div class="nd-notif-list" id="ndNotifList"><div class="nd-notif-empty">Загружаю…</div></div>
                            <a class="nd-notif-ft" href="{{ route('notifications.index') }}">Все уведомления →</a>
                        </div>
                    </div>
                @endif
                @if($ndDesk)
                    <a class="nd-btn nd-primary" href="{{ route('deals.create') }}"><svg class="nd-icon nd-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Сделка</a>
                @endif
            </header>
            @hasSection('nd_toolbar')
                <div class="nd-toolbar">@yield('nd_toolbar')</div>
            @endif
            <div class="nd-view">
                @if($ndFull)
                    <div class="nd-alerts">@include('partials.flash')</div>
                    @yield('content')
                @else
                    <div class="nd-page" data-route="{{ request()->route()?->getName() }}">
                        @include('partials.flash')
                        @yield('content')
                    </div>
                @endif
            </div>
        </main>
    </div>
    <div class="nd-mscrim" id="ndMscrim"></div>
    {{-- сделка во всплывающем окне (карточка — та же страница в iframe) --}}
    <div class="nd-scrim" id="ndDealScrim" aria-hidden="true">
        <div class="nd-modal nd-deal-modal" role="dialog" aria-modal="true" aria-label="Сделка">
            <button type="button" class="nd-m-x nd-deal-close" id="ndDealClose" aria-label="Закрыть"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor"><path d="M6 6l12 12M18 6L6 18"/></svg></button>
            <a class="nd-deal-newtab" id="ndDealNewTab" href="#" target="_blank" rel="noopener" title="Открыть отдельной страницей" aria-label="Открыть отдельной страницей"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 01-1 1H5a1 1 0 01-1-1V7a1 1 0 011-1h5"/></svg></a>
            <div class="nd-deal-loading" id="ndDealLoading">Открываю сделку…</div>
            <iframe id="ndDealFrame" title="Сделка"></iframe>
        </div>
    </div>
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1080;"></div>
@else
<nav class="navbar navbar-expand-lg navbar-dark">
    <div class="container-fluid">
        <a class="navbar-brand fw-semibold" href="{{ route($homeRoute) }}">{{ config('app.name') }}</a>
        <div class="d-flex gap-2 flex-wrap align-items-center">
            @auth
                @if($isSneakerOwner)
                    {{-- на сводке потолков «Продажи за день» (кроссовки) не показываем — там другой бизнес --}}
                    @php($onCeilings = request()->routeIs('owner.ceilings') || (request()->routeIs('owner.smm') && request()->route('business') === 'ceilings'))
                    <a class="btn btn-sm btn-success fw-semibold" href="{{ route($onCeilings ? 'owner.ceilings' : 'owner.dashboard') }}">📊 Сводка</a>
                    @if(! $onCeilings)
                        <a class="btn btn-sm btn-outline-light" href="{{ route('sale.day') }}" title="Продажи кроссовок за день">🗓 Продажи за день</a>
                    @endif
                @endif
                @if(!$isMeasurer && !$isConstructor && !$isDocumentsOperator && !$isSneaker && !$isSneakerOwner)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('deals.kanban') }}">Канбан</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('deals.index') }}">Список</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('tasks.index') }}">Дела</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('deals.closed') }}">Завершённые</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('chats.index') }}">Чаты</a>
                @endif
                @if($isSneaker)
                    <a class="btn btn-sm btn-success fw-semibold" href="{{ route('sale.quick') }}">💵 Продажа</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('sale.day') }}" title="Продажи за день">🗓 За день</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('scan') }}" title="Найти товар сканером или по артикулу">🔎 Скан</a>
                    <a class="btn btn-sm btn-outline-light position-relative" href="{{ route('warehouse.index') }}">Склад
                        @if($lowStock > 0)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger" title="Заканчивается позиций: {{ $lowStock }}">{{ $lowStock }}</span>@endif
                    </a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('warehouse.receiving') }}">Приёмка</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('deals.kanban') }}">Продажи</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('tasks.index') }}">Дела</a>
                    @if($isSneakerHead)
                        <a class="btn btn-sm btn-outline-light" href="{{ route('purchases.kanban') }}">Закупки</a>
                        <a class="btn btn-sm btn-outline-light" href="{{ route('owner.dashboard') }}" title="Сводка для владельца">📊 Сводка</a>
                    @endif
                    <a class="btn btn-sm btn-outline-light" href="{{ route('help.index') }}" title="Инструкция">📖 Помощь</a>
                @endif
                @if($isDocumentsOperator)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('tasks.index') }}">Дела</a>
                @endif
                @if(!$isDocumentsOperator && !$isSneaker && !$isSneakerOwner)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('calendar.index') }}">Календарь</a>
                @endif
                @if($isNc)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('nonclosures.index') }}">Документы</a>
                @endif
                @if($canUseProjecting)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('ceiling-projects.index') }}">Проектировка</a>
                @endif
                @if($isAdmin)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('ceiling-projects.index') }}">Проектировка</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.integrations.index') }}">Интеграции</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.imports.bitrix.index') }}">Импорт Bitrix</a>
                @endif
                @if(($isPriv || $isSneakerHead) && !$isDocumentsOperator)
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

                @if($isSneakerHead)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('sneaker.report') }}">Отчёты</a>
                @elseif(!$isDocumentsOperator && !$isSneaker && !$isSneakerOwner)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('reports.monthly') }}">Отчёты</a>
                @endif
                @if(!$isMeasurer && !$isConstructor && !$isSneakerOwner)
                    <a class="btn btn-sm btn-warning position-relative" href="{{ route('notifications.index') }}" title="Уведомления" aria-label="Уведомления">
                        <i class="bi bi-bell-fill"></i>
                        <span id="navNotifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0</span>
                    </a>
                    <button type="button" class="btn btn-sm btn-outline-info d-none" id="enableNotifBtn" title="Системные уведомления">🔊</button>
                    @if(!$isDocumentsOperator && !$isSneaker)
                        <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}">+ Сделка</a>
                    @endif
                @endif
                <a class="btn btn-sm btn-outline-warning" href="{{ route('logout') }}">Выйти</a>
            @endauth
        </div>
    </div>
</nav>

@auth
    <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1080;"></div>
@endauth

<main class="container-fluid py-3">
    @include('partials.flash')

    @yield('content')
</main>
@endif

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@if($nd)
{{-- @json режет выражение по запятым — значения готовим заранее --}}
@php($ndCfg = [
    'suggestUrl' => in_array(auth()->user()?->role, ['admin', 'main_operator', 'operator'], true) ? route('deals.index') : null,
    'notifListUrl' => auth()->check() ? route('notifications.index', ['list' => 1]) : null,
    'searchHint' => request()->routeIs('deals.kanban') ? 'на доске' : (request()->routeIs('tasks.*') ? 'в делах' : (request()->routeIs('deals.closed') ? 'в завершённых' : 'в списке сделок')),
])
<script>
    window.ndConfig = @json($ndCfg);
</script>
<script src="{{ asset('js/bd-daterange.js') }}?v={{ @filemtime(public_path('js/bd-daterange.js')) }}"></script>
<script src="{{ asset('js/nordika-crm.js') }}?v={{ @filemtime(public_path('js/nordika-crm.js')) }}"></script>
@else
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
@endif

@auth
@if(!in_array(auth()->user()?->role, ['measurer', 'constructor'], true) && empty($ndEmbed))
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
        if (window.ndSoundMuted && window.ndSoundMuted()) return;   // кнопка звука в верхней панели
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
        if (window.ndSoundMuted && window.ndSoundMuted()) return;
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
@if(! empty($isSneaker))
    @include('partials.scanner-listener')
@endif
</body>
</html>

