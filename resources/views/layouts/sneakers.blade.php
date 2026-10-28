<!doctype html>
{{--
    Каркас кроссовочного отдела «Нордика» (пакет дизайнера 28.10.2026): боковое меню по группам, шапка раздела (заголовок,
    поиск, тема, звук, колокольчик, «Продать»), всплывающие окна и toast — по прототипу crm-sneakers-nordika.html.
    Стили — public/css/nordika-sneakers.css (классы дизайнера с приставкой ns-) + nordika-sneakers-app.css (наши дополнения),
    поведение — public/js/nordika-sneakers.js. Bootstrap не подключается: у страниц отдела своя разметка (страница может
    попросить его на время перехода — @section('ns_bootstrap', '1')). Колл-центр этот каркас не использует.

    Секции страницы: title, ns_title, ns_sub, ns_actions (кнопки перед общими), ns_no_search / ns_no_sell (без поиска /
    «Продать» в шапке), content (внутри .ns-view > .ns-page) или ns_view (весь блок под шапкой — например, канбан).
--}}
@php
    $nsUser = auth()->user();
    $nsUi = \App\Support\Ui\Sneakers::class;
    $nsNav = $nsUi::nav($nsUser);
    $nsShop = in_array($nsUser->role, ['sneaker_head', 'sneaker_operator'], true);
    $nsSwitch = $nsUser->role === 'sneaker_owner' && $nsUser->all_businesses && Route::has('owner.ceilings');
    $nsTitle = trim($__env->yieldContent('ns_title')) ?: trim($__env->yieldContent('title')) ?: 'Кроссовки';
    $nsSub = trim($__env->yieldContent('ns_sub'));
    $nsSearch = $nsShop && ! $__env->hasSection('ns_no_search');
    $nsSell = $nsShop && ! $__env->hasSection('ns_no_sell') && Route::has('sale.quick');
    $nsUnread = $nsUi::unread($nsUser);
    $nsV = fn (string $f) => asset($f).'?v='.(@filemtime(public_path($f)) ?: 1);
    $nsCfg = [
        'pollUrl' => route('notifications.poll'),
        'listUrl' => route('notifications.index', ['list' => 1]),
        'allUrl' => route('notifications.index'),
        'saleUrl' => Route::has('sale.quick') && $nsShop ? route('sale.quick') : null,
        'notifKey' => 'lastNotifId_u'.$nsUser->id.'_a'.$nsUser->account_id,
    ];
@endphp
<html lang="ru" class="ns-html" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Кроссовки') · Кроссовки</title>
    <script>try{if(localStorage.getItem('nsTheme')==='dark')document.documentElement.setAttribute('data-theme','dark');}catch(e){}</script>
    <link rel="apple-touch-icon" href="{{ asset('icons/sneaker-touch-icon-180.png') }}">
    <link rel="icon" type="image/png" href="{{ asset('icons/sneaker-favicon-32.png') }}">
    <meta name="apple-mobile-web-app-title" content="Кроссовки">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    @hasSection('ns_bootstrap')
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @endif
    <link rel="stylesheet" href="{{ $nsV('css/nordika-sneakers.css') }}">
    <link rel="stylesheet" href="{{ $nsV('css/nordika-sneakers-app.css') }}">
    @stack('styles')
</head>
<body data-route="{{ Route::currentRouteName() }}">
<div class="ns-app">
    <aside class="ns-side" id="nsSide">
        <div class="ns-brand @unless($nsSwitch) ns-brand-fixed @endunless" id="nsBrand" @if($nsSwitch) title="Сменить отдел" @endif>
            <div class="ns-lg"><svg viewBox="0 0 24 24" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 16h15.5a2.5 2.5 0 000-5H14l-2.2-3.3a1.5 1.5 0 00-1.3-.7H9L7.5 10 4 9.5 3 12z"/><path d="M3 16v2h18"/></svg></div>
            <div><div class="ns-bt">Кроссовки @if($nsSwitch)<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M7 10l5 5 5-5"/></svg>@endif</div><div class="ns-bs">Callcenter CRM</div></div>
        </div>
        @foreach($nsNav as $group => $items)
            <div class="ns-nav-h">{{ $group }}</div>
            <nav class="ns-nav">
                @foreach($items as $it)
                    <a href="{{ route($it['route']) }}" @class(['ns-active' => $nsUi::active($it['active'])])>{!! $nsUi::icon($it['icon']) !!}{{ $it['label'] }}@isset($it['count'])<span class="ns-cnt">{{ $it['count'] }}</span>@endisset</a>
                @endforeach
            </nav>
        @endforeach
        <div class="ns-sp"></div>
        <div class="ns-side-user" id="nsUser" title="Меню профиля">
            <div class="ns-ava">{{ $nsUi::initials($nsUser->name) }}</div>
            <div><div class="ns-un">{{ $nsUser->name }}</div><div class="ns-ur">{{ $nsUi::ROLE_LABELS[$nsUser->role] ?? $nsUser->role }} · кроссовки</div></div>
        </div>
    </aside>

    <main class="ns-main" id="nsMain">
        <header class="ns-top">
            <button class="ns-btn ns-icon-only ns-burger" type="button" id="nsBurger" aria-label="Меню"><svg class="ns-icon" viewBox="0 0 24 24"><path d="M3 6h18M3 12h18M3 18h18"/></svg></button>
            <h1>{{ $nsTitle }}@if($nsSub !== '') <span class="ns-sub">{{ $nsSub }}</span>@endif</h1>
            <div class="ns-grow"></div>
            @yield('ns_actions')
            @if($nsSearch)
                <form class="ns-search" method="GET" action="{{ route('sale.quick') }}" role="search">
                    {!! $nsUi::icon('search', 'ns-sm') !!}<input name="q" id="nsSearch" placeholder="Модель или артикул — Enter" autocomplete="off" data-scan="own" aria-label="Поиск модели или артикула">
                </form>
            @endif
            <button class="ns-btn ns-icon-only" type="button" id="nsThemeBtn" title="Светлая / тёмная тема"><span class="ns-theme-ic"><svg class="ns-icon ns-sun" viewBox="0 0 24 24"><circle cx="12" cy="12" r="4.5"/><path d="M12 2v2M12 20v2M2 12h2M20 12h2M5 5l1.5 1.5M17.5 17.5L19 19M19 5l-1.5 1.5M6.5 17.5L5 19"/></svg><svg class="ns-icon ns-moon" viewBox="0 0 24 24"><path d="M21 13a8.5 8.5 0 01-10-10A8.5 8.5 0 1021 13z"/></svg></span></button>
            <button class="ns-btn ns-icon-only ns-snd" type="button" id="nsSoundBtn" title="Звук уведомлений включён — нажмите, чтобы выключить">{!! $nsUi::icon('vol') !!}</button>
            <button class="ns-btn ns-icon-only" type="button" id="nsBellBtn" title="Уведомления">{!! $nsUi::icon('bell') !!}<span class="ns-bd" id="nsBellBadge" @if($nsUnread < 1) hidden @endif>{{ $nsUnread > 99 ? '99+' : $nsUnread }}</span></button>
            @if($nsSell)
                <a class="ns-btn ns-primary" href="{{ route('sale.quick') }}">{!! $nsUi::icon('sell', 'ns-sm') !!}Продать</a>
            @endif
        </header>
        @hasSection('ns_view')
            @yield('ns_view')
        @else
            <div class="ns-view"><div class="ns-page">
                @include('partials.ns-flash')
                @yield('content')
            </div></div>
        @endif
    </main>
</div>

<div class="ns-scrim" id="nsScrim"><div id="nsModal" class="ns-modal" role="dialog" aria-modal="true"></div></div>
<div class="ns-mscrim" id="nsMscrim"></div>
<div class="ns-pop" id="nsPop" hidden></div>
<div class="ns-toast" id="nsToast" role="status" aria-live="polite"></div>

{{-- меню отдела (владельцу всех бизнесов) и профиля — показывает nordika-sneakers.js --}}
@if($nsSwitch)
<template id="nsBrandMenu">
    <div class="ns-ph">Отдел</div>
    <div class="ns-mi">👟 <div>Кроссовки<small>магазин и склад</small></div><span class="ns-ok">✓</span></div>
    <a class="ns-mi" href="{{ route('owner.ceilings') }}">☎️ <div>Потолки · колл-центр<small>сводка потолков</small></div></a>
</template>
@endif
<template id="nsUserMenu">
    <div class="ns-ph">{{ $nsUser->name }}</div>
    <div class="ns-mi" data-ns-act="theme">{!! $nsUi::icon('pc', 'ns-sm') !!}Тёмная / светлая тема</div>
    <div class="ns-mi" data-ns-act="sysnotif" hidden>{!! $nsUi::icon('bell', 'ns-sm') !!}<div>Уведомления Windows<small>всплывающие окна браузера о продажах и делах</small></div></div>
    <form method="POST" action="{{ route('logout') }}" class="ns-mi-form">@csrf<button class="ns-mi" type="submit">{!! $nsUi::icon('logout', 'ns-sm') !!}Выйти</button></form>
</template>

<script>window.nsConfig = @json($nsCfg);</script>
<script src="{{ $nsV('js/nordika-sneakers.js') }}"></script>
@if($nsShop)
    @include('partials.scanner-listener')
@endif
@hasSection('ns_bootstrap')
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@endif
@stack('scripts')
</body>
</html>
