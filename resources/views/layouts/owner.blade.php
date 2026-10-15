{{--
    Страницы владельца (сводки «Потолки», «Кроссовки», SMM-контент) — в дизайн-системе дашборда «БлагоДар»
    (public/css/blagodar-dashboard.css — CSS дизайнера как есть, blagodar-dashboard-crm.css — дополнения под CRM).
    Отдельный шаблон без Bootstrap: у дизайн-системы короткие общие классы (.card, .nav, .badge), с CRM они бы спорили.
    Тема — data-theme на <html>, запоминается в браузере; графики — Chart.js (window.bdCharts.draw()).
--}}
@php
    $ownerUser = auth()->user();
    $ownerFirst = trim(explode(' ', trim((string) ($ownerUser?->name ?? '')))[0] ?? '');
    $hour = (int) now()->format('G');
    $greeting = $hour < 5 ? 'Доброй ночи' : ($hour < 12 ? 'Доброе утро' : ($hour < 18 ? 'Добрый день' : 'Добрый вечер'));
    $css = fn (string $f) => asset('css/'.$f).'?v='.(@filemtime(public_path('css/'.$f)) ?: 1);
    $roleLabel = ['sneaker_owner' => 'Владелец · БлагоДар', 'sneaker_head' => 'Руководитель · Кроссовки'][$ownerUser?->role] ?? 'Сотрудник';
@endphp
<!doctype html>
<html lang="ru" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Сводка') · БлагоДар</title>
    <script>try{var t=localStorage.getItem('bdTheme');if(t==='dark'||t==='light'){document.documentElement.setAttribute('data-theme',t);}}catch(e){}</script>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ $css('blagodar-dashboard.css') }}">
    <link rel="stylesheet" href="{{ $css('blagodar-dashboard-crm.css') }}">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"
            integrity="sha512-CQBWl4fJHWbryGE+Pc7UAxWMUMNMWzWxF4SQo9CgkJIN1kx6djDQZjh3Y8SZ1d+6I+1zze6Z7kHXO7q3UyZAWw=="
            crossorigin="anonymous" referrerpolicy="no-referrer"></script>
    @stack('head')
</head>
<body>
<div class="app">
    <div class="side-backdrop" id="bdSideBack"></div>
    <aside class="side" id="bdSide">
        <div class="brand">
            <span class="logo-sq">@include('owner._brand_logo')</span>
            <span class="brand-tx">БлагоДар<small>Сводка бизнеса</small></span>
        </div>
        <nav class="nav" id="bdNav">
            @yield('nav')
        </nav>
        <div class="foot">@yield('side-foot')</div>
    </aside>

    <main class="main">
        <div class="wrap">
            <div class="top">
                <div>
                    <div class="m-brand" style="margin-bottom:10px">@include('owner._brand_mark')</div>
                    <h1 class="hello">{{ $greeting }}@if($ownerFirst !== ''), <span class="name">{{ $ownerFirst }}</span>@endif
                        <img class="emoji" src="https://cdn.jsdelivr.net/npm/emoji-datasource-apple@14.0.0/img/apple/64/1f44b.png" alt="👋"></h1>
                    <p class="hsub">@yield('subtitle')</p>
                </div>
                <div class="top-right">
                    {{-- на телефоне меню слева скрыто — открывается этой кнопкой --}}
                    <button class="iconbtn m-menu" id="bdMenu" type="button" title="Меню" aria-label="Меню" aria-controls="bdSide" aria-expanded="false">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M4 7h16M4 12h16M4 17h16"/></svg>
                    </button>
                    {{-- поиск по сделкам и клиентам (на телефоне — кнопкой, поле открывается поверх) --}}
                    <div class="searchbox" id="bdSearchBox">
                        <label class="search" title="Поиск по сделкам и клиентам">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
                            <input id="bdSearch" type="search" autocomplete="off" spellcheck="false" placeholder="Поиск по сделкам, клиентам" aria-label="Поиск по сделкам и клиентам">
                        </label>
                        <div class="search-pop" id="bdSearchPop" hidden></div>
                    </div>
                    <button class="iconbtn" id="bdTheme" type="button" title="Светлая / тёмная тема">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z"/></svg>
                    </button>
                    <div class="pop-wrap">
                        <button class="iconbtn" id="bdBell" type="button" title="Уведомления" aria-label="Уведомления"><span class="dot" id="bdBellDot" hidden></span><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 01-3.4 0"/></svg></button>
                        <div class="menu-pop notif-pop" id="bdNotifPop" hidden>
                            <div class="notif-head"><span>Уведомления</span><button class="notif-read" id="bdNotifRead" type="button" disabled>Прочитать все</button></div>
                            <div class="notif-list" id="bdNotifList"><div class="sr-empty">Загружаю…</div></div>
                        </div>
                    </div>
                    <div class="pop-wrap">
                        <button class="avatar" id="bdAvatar" type="button" title="{{ $ownerUser?->name }}">{{ mb_strtoupper(mb_substr($ownerFirst !== '' ? $ownerFirst : 'В', 0, 1)) }}</button>
                        <div class="menu-pop acc-pop" id="bdAccPop" hidden>
                            <div class="acc-head">
                                <span class="acc-ava">{{ mb_strtoupper(mb_substr($ownerFirst !== '' ? $ownerFirst : 'В', 0, 1)) }}</span>
                                <div><div class="acc-name">{{ $ownerUser?->name }}</div><div class="acc-mail">{{ $roleLabel }}</div></div>
                            </div>
                            <div class="menu-list">
                                @yield('account-links')
                                <form method="POST" action="{{ route('logout') }}" class="menu-form">
                                    @csrf
                                    <button class="menu-item danger" type="submit">
                                        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 01-2-2V5a2 2 0 012-2h4"/><path d="M16 17l5-5-5-5M21 12H9"/></svg> Выйти
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            @yield('content')
        </div>
    </main>
</div>

{{-- плеер клипа VK (кнопки .watch в таблицах клипов) --}}
<div class="cm" id="bdClip" hidden>
    <div class="cm-back" data-cm-close></div>
    <div class="cm-card" role="dialog" aria-modal="true" aria-labelledby="bdClipTtl">
        <button class="cm-x" type="button" data-cm-close aria-label="Закрыть">✕</button>
        <div class="cm-frame"><iframe id="bdClipFrame" title="Клип VK" allow="autoplay; encrypted-media; fullscreen; picture-in-picture"></iframe></div>
        <div class="cm-ttl" id="bdClipTtl">Клип</div>
        <p class="cm-sub" id="bdClipSub">VK Клип · БлагоДар</p>
        <div class="cm-act"><a class="cm-btn pri" id="bdClipVk" target="_blank" rel="noopener">Открыть в VK ↗</a></div>
    </div>
</div>

{{-- карточка сделки или клиента из поиска и уведомлений (только просмотр) --}}
<div class="cm" id="bdCard" hidden>
    <div class="cm-back" data-cm-close></div>
    <div class="cm-card dc-card" role="dialog" aria-modal="true" aria-label="Карточка">
        <button class="cm-x" type="button" data-cm-close aria-label="Закрыть">✕</button>
        <div id="bdCardBody"></div>
    </div>
</div>

<script src="{{ asset('js/bd-daterange.js') }}?v={{ @filemtime(public_path('js/bd-daterange.js')) ?: 1 }}"></script>
<script>window.bdFeedUrl = @json(route('owner.dashboard'));</script>
<script src="{{ asset('js/bd-owner-head.js') }}?v={{ @filemtime(public_path('js/bd-owner-head.js')) ?: 1 }}"></script>
<script>
/* Графики дизайн-системы: <canvas data-spark='{"data":[…],"labels":[…],"unit":["замер","замера","замеров"]}' data-on-accent="1">
   (линия внизу KPI-карточки) и <canvas data-bars='{"labels":[…],"sets":[{"label":"…","data":[…],"color":"--blue"}]}'>.
   draw() перерисовывает всё на странице: при загрузке, смене темы и после автообновления сводки. */
window.bdCharts = (function () {
    const reg = new Map();
    const cssv = n => getComputedStyle(document.documentElement).getPropertyValue(n).trim();
    const fmt = n => Number(n).toLocaleString('ru-RU');
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
    const plural = (n, f) => { const d = Math.abs(n) % 100, d1 = d % 10; return d > 10 && d < 20 ? f[2] : (d1 > 1 && d1 < 5 ? f[1] : (d1 === 1 ? f[0] : f[2])); };
    const unitText = (v, u) => !u ? fmt(v) : (typeof u === 'string' ? fmt(v) + ' ' + u : fmt(v) + ' ' + plural(v, u));
    function tooltip(extra) {
        return Object.assign({ backgroundColor: cssv('--tip'), titleColor: cssv('--tip-ink'), bodyColor: cssv('--tip-ink'), padding: 10, cornerRadius: 10 }, extra || {});
    }
    function spark(el) {
        const d = JSON.parse(el.dataset.spark || '{}');
        const onAccent = el.dataset.onAccent === '1';
        const line = onAccent ? '#ffffff' : cssv('--accent');
        const fill = onAccent ? 'rgba(255,255,255,.22)' : (isDark() ? 'rgba(36,185,116,.20)' : 'rgba(31,174,99,.14)');
        return new Chart(el, { type: 'line',
            data: { labels: d.labels || d.data.map((_, i) => i + 1), datasets: [{ data: d.data, borderColor: line, borderWidth: 2.4, fill: true, backgroundColor: fill,
                tension: .4, pointRadius: 0, pointHoverRadius: 4, pointHoverBorderWidth: 2, pointHoverBackgroundColor: line, pointHoverBorderColor: onAccent ? cssv('--accent') : '#fff' }] },
            options: { responsive: true, maintainAspectRatio: false, layout: { padding: { top: 8, bottom: 0 } }, interaction: { mode: 'index', intersect: false },
                plugins: { legend: { display: false }, tooltip: tooltip({ displayColors: false, padding: 8, cornerRadius: 8, titleFont: { size: 11 }, bodyFont: { size: 13, weight: '700' },
                    callbacks: { label: c => unitText(c.raw, d.unit) } }) },
                scales: { x: { display: false }, y: { display: false, grace: '12%' } } } });
    }
    function bars(el) {
        const d = JSON.parse(el.dataset.bars || '{}');
        const grid = cssv('--line'), muted = cssv('--muted');
        return new Chart(el, { type: 'bar',
            data: { labels: d.labels, datasets: d.sets.map(s => ({ label: s.label, data: s.data, backgroundColor: cssv(s.color || '--accent'), borderRadius: 7, maxBarThickness: 22 })) },
            options: { responsive: true, maintainAspectRatio: false,
                plugins: { legend: { display: false }, tooltip: tooltip({ callbacks: { title: it => (d.titles || d.labels)[it[0].dataIndex], label: c => ' ' + c.dataset.label + ': ' + fmt(c.raw) } }) },
                scales: { x: { grid: { display: false }, border: { display: false }, ticks: { color: muted, font: { size: 11 }, autoSkip: true, maxRotation: 0 } },
                    y: { beginAtZero: true, grid: { color: grid }, border: { display: false }, ticks: { color: muted, font: { size: 11 }, maxTicksLimit: 5, precision: 0 } } } } });
    }
    function draw() {
        if (typeof Chart === 'undefined') return;
        reg.forEach((chart, el) => { chart.destroy(); reg.delete(el); });
        document.querySelectorAll('canvas[data-spark], canvas[data-bars]').forEach(el => {
            try { reg.set(el, el.dataset.spark ? spark(el) : bars(el)); } catch (e) { console.warn('chart skipped', e); }
        });
    }
    return { draw };
})();

(function () {
    const root = document.documentElement;
    document.getElementById('bdTheme').addEventListener('click', function () {
        const dark = root.getAttribute('data-theme') !== 'dark';
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        try { localStorage.setItem('bdTheme', dark ? 'dark' : 'light'); } catch (e) {}
        window.bdCharts.draw();
    });
    // всплывашки шапки: личный кабинет и уведомления — открыта одна, закрываются кликом мимо и Esc
    const pops = [['bdAvatar', 'bdAccPop'], ['bdBell', 'bdNotifPop']].map(([b, p]) => ({ btn: document.getElementById(b), pop: document.getElementById(p) }));
    const closePops = except => pops.forEach(m => { if (m.pop !== except) m.pop.hidden = true; });
    pops.forEach(m => m.btn.addEventListener('click', e => {
        e.stopPropagation();
        const open = m.pop.hidden;
        closePops(m.pop);
        window.bdHead.closeSearch();
        m.pop.hidden = !open;
        if (open && m.btn.id === 'bdBell') window.bdHead.loadNotifs();
    }));
    document.addEventListener('click', e => pops.forEach(m => { if (!m.pop.hidden && !m.pop.contains(e.target)) m.pop.hidden = true; }));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closePops(null); });

    // телефон: меню слева выезжает по кнопке, закрывается по пункту меню, по фону и Esc
    const menuBtn = document.getElementById('bdMenu'), back = document.getElementById('bdSideBack');
    const setSide = open => { document.body.classList.toggle('side-open', open); menuBtn.setAttribute('aria-expanded', open ? 'true' : 'false'); };
    menuBtn.addEventListener('click', e => { e.stopPropagation(); setSide(! document.body.classList.contains('side-open')); });
    back.addEventListener('click', () => setSide(false));
    document.querySelectorAll('#bdSide .nav a').forEach(a => a.addEventListener('click', () => setSide(false)));
    document.addEventListener('keydown', e => { if (e.key === 'Escape') setSide(false); });

    // меню слева — якоря блоков страницы: плавная прокрутка и подсветка текущего блока
    const links = [...document.querySelectorAll('#bdNav a[href^="#"]')];
    links.forEach(a => a.addEventListener('click', e => {
        const t = document.getElementById(a.getAttribute('href').slice(1));
        if (!t) return;
        e.preventDefault();
        t.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.replaceState(null, '', a.getAttribute('href'));
    }));
    if (links.length && 'IntersectionObserver' in window) {
        const io = new IntersectionObserver(entries => {
            entries.forEach(en => {
                if (!en.isIntersecting) return;
                links.forEach(a => a.classList.toggle('active', a.getAttribute('href') === '#' + en.target.id));
            });
        }, { rootMargin: '-15% 0px -70% 0px' });
        links.forEach(a => { const t = document.getElementById(a.getAttribute('href').slice(1)); if (t) io.observe(t); });
    }
    window.bdCharts.draw();
    window.bdDateRange.init();
})();
</script>
@stack('scripts')
</body>
</html>
