/* «Нордика» · кроссовочный отдел — поведение каркаса layouts/sneakers (по образцу sneakers-reference.js дизайнера):
   тема ☀/☾ (localStorage nsTheme), звук уведомлений (crmSound: on/off, сигнал — два тона 880 и 1318,5 Гц), всплывающие меню
   (отдел, профиль, уведомления), окно .ns-modal, toast, мобильное меню, опрос уведомлений каждые 25 с (как прежний каркас).
   Для страниц — window.ns: toast, showPop, hidePop, menuPop, openModal, closeModal, ding, post. */
(() => {
    'use strict';
    const cfg = window.nsConfig || {};
    const root = document.documentElement;
    const $ = (id) => document.getElementById(id);
    const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const store = { get(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }, set(k, v) { try { localStorage.setItem(k, v); } catch (e) {} } };
    const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content || '';
    const post = (url, data) => fetch(url, {
        method: 'POST',
        headers: { 'X-CSRF-TOKEN': csrf(), 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest', ...(data ? { 'Content-Type': 'application/json' } : {}) },
        body: data ? JSON.stringify(data) : null,
        credentials: 'same-origin',
    });

    /* ---------- toast ---------- */
    const toastEl = $('nsToast');
    const toast = (text) => {
        if (!toastEl) return;
        toastEl.textContent = text;
        toastEl.classList.add('ns-show');
        clearTimeout(toastEl._t);
        toastEl._t = setTimeout(() => toastEl.classList.remove('ns-show'), 2600);
    };

    /* ---------- всплывающее меню (поповер) ---------- */
    const pop = $('nsPop');
    const hidePop = () => { if (pop) { pop.hidden = true; pop.classList.remove('ns-ntf'); } };
    const showPop = (anchor, html, width) => {
        if (!pop) return null;
        pop.innerHTML = html;
        pop.hidden = false;
        pop.style.width = width ? width + 'px' : '';
        const r = anchor.getBoundingClientRect(), pw = pop.offsetWidth, ph = pop.offsetHeight;
        let x = r.left, y = r.bottom + 6;
        if (x + pw > innerWidth - 8) x = innerWidth - 8 - pw;
        if (y + ph > innerHeight - 8) y = Math.max(8, r.top - ph - 6);
        pop.style.left = Math.max(8, x) + 'px';
        pop.style.top = y + 'px';
        return pop;
    };
    const menuPop = (anchor, head, items, cur, onPick) => {
        showPop(anchor, (head ? '<div class="ns-ph">' + esc(head) + '</div>' : '') + items.map((it, i) =>
            '<div class="ns-mi" data-i="' + i + '">' + esc(it) + (it === cur ? '<span class="ns-ok">✓</span>' : '') + '</div>').join(''));
        pop.querySelectorAll('.ns-mi').forEach((m) => m.addEventListener('click', () => { hidePop(); onPick(items[+m.dataset.i]); }));
    };
    pop?.addEventListener('click', (e) => e.stopPropagation());
    document.addEventListener('click', hidePop);

    /* ---------- окно (модалка) ---------- */
    const scrim = $('nsScrim'), modal = $('nsModal');
    const openModal = (html, width) => {
        if (!scrim || !modal) return null;
        modal.style.maxWidth = (width || 560) + 'px';
        if (html !== undefined) modal.innerHTML = html;
        scrim.classList.add('ns-on');
        return modal;
    };
    const closeModal = () => scrim?.classList.remove('ns-on');
    scrim?.addEventListener('click', (e) => { if (e.target === scrim) closeModal(); });
    document.addEventListener('click', (e) => { if (e.target.closest('[data-ns-close]')) closeModal(); });

    /* ---------- тема ---------- */
    const toggleTheme = () => {
        const dark = root.getAttribute('data-theme') !== 'dark';
        root.setAttribute('data-theme', dark ? 'dark' : 'light');
        store.set('nsTheme', dark ? 'dark' : 'light');
    };
    $('nsThemeBtn')?.addEventListener('click', toggleTheme);

    /* ---------- звук уведомлений ---------- */
    let sound = store.get('crmSound') !== 'off';
    let actx = null;
    const audio = () => {
        try {
            actx = actx || new (window.AudioContext || window.webkitAudioContext)();
            if (actx.state === 'suspended') actx.resume();
        } catch (e) {}
        return actx;
    };
    // браузер не даёт звучать до первого жеста — звук «разблокируется» первым кликом или клавишей
    ['pointerdown', 'keydown'].forEach((ev) => document.addEventListener(ev, audio, { once: true, capture: true }));
    const ding = () => {
        if (!sound) return;
        const a = audio();
        if (!a) return;
        try {
            const t = a.currentTime;
            [[880, 0], [1318.5, 0.13]].forEach(([f, d]) => {
                const o = a.createOscillator(), g = a.createGain();
                o.type = 'sine'; o.frequency.value = f;
                g.gain.setValueAtTime(0, t + d);
                g.gain.linearRampToValueAtTime(0.16, t + d + 0.012);
                g.gain.exponentialRampToValueAtTime(0.001, t + d + 0.5);
                o.connect(g); g.connect(a.destination);
                o.start(t + d); o.stop(t + d + 0.55);
            });
        } catch (e) {}
    };
    const sndBtn = $('nsSoundBtn');
    const ICON_VOL = '<svg class="ns-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5L6 9H3v6h3l5 4z"/><path d="M15.5 8.5a5 5 0 010 7M18.5 5.5a9 9 0 010 13"/></svg>';
    const ICON_MUTE = '<svg class="ns-icon" viewBox="0 0 24 24" aria-hidden="true"><path d="M11 5L6 9H3v6h3l5 4z"/><path d="M16 9.5l5 5M21 9.5l-5 5"/></svg>';
    const paintSound = () => {
        if (!sndBtn) return;
        sndBtn.classList.toggle('ns-off', !sound);
        sndBtn.innerHTML = sound ? ICON_VOL : ICON_MUTE;
        sndBtn.title = sound ? 'Звук уведомлений включён — нажмите, чтобы выключить' : 'Звук уведомлений выключен — нажмите, чтобы включить';
    };
    sndBtn?.addEventListener('click', () => {
        sound = !sound;
        store.set('crmSound', sound ? 'on' : 'off');
        paintSound();
        toast(sound ? 'Звук уведомлений включён' : 'Звук выключен — уведомления придут без сигнала');
        if (sound) ding();
    });
    paintSound();

    /* ---------- мобильное меню ---------- */
    const side = $('nsSide'), mscrim = $('nsMscrim');
    const closeSide = () => { side?.classList.remove('ns-open'); mscrim?.classList.remove('ns-on'); };
    $('nsBurger')?.addEventListener('click', () => { side?.classList.add('ns-open'); mscrim?.classList.add('ns-on'); });
    mscrim?.addEventListener('click', closeSide);

    /* ---------- отдел и профиль ---------- */
    const brandMenu = $('nsBrandMenu');
    if (brandMenu) {
        $('nsBrand')?.addEventListener('click', (e) => { e.stopPropagation(); showPop(e.currentTarget, brandMenu.innerHTML, 260); });
    }
    const sysNotif = 'Notification' in window;
    $('nsUser')?.addEventListener('click', (e) => {
        e.stopPropagation();
        const p = showPop(e.currentTarget, $('nsUserMenu')?.innerHTML || '', 260);
        if (!p) return;
        const sys = p.querySelector('[data-ns-act="sysnotif"]');
        if (sys && sysNotif && Notification.permission !== 'denied') {
            sys.hidden = false;
            if (Notification.permission === 'granted') sys.querySelector('small').textContent = 'включены';
        }
        // меню у нижнего края — поднять над кнопкой
        const r = e.currentTarget.getBoundingClientRect();
        p.style.top = Math.max(8, r.top - p.offsetHeight - 6) + 'px';
        p.querySelector('[data-ns-act="theme"]')?.addEventListener('click', () => { hidePop(); toggleTheme(); });
        sys?.addEventListener('click', async () => {
            hidePop();
            if (Notification.permission === 'granted') { toast('Уведомления Windows уже включены'); return; }
            try { const res = await Notification.requestPermission(); toast(res === 'granted' ? 'Уведомления Windows включены' : 'Браузер не разрешил уведомления'); } catch (err) {}
        });
    });

    /* ---------- уведомления: колокольчик, окно, опрос ---------- */
    const bell = $('nsBellBtn'), badge = $('nsBellBadge');
    const setBadge = (n) => {
        if (!badge) return;
        n = Number(n || 0);
        badge.hidden = n < 1;
        badge.textContent = n > 99 ? '99+' : String(n);
    };
    const openNotif = async () => {
        if (!cfg.listUrl || !bell) return;
        showPop(bell, '<div class="ns-nh">Уведомления</div><div class="ns-nl"><div class="ns-ni ns-read"><div><p>Загружаю…</p></div></div></div>', 380);
        pop.classList.add('ns-ntf');
        let data;
        try { data = await (await fetch(cfg.listUrl, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })).json(); } catch (e) { return; }
        if (pop.hidden) return;
        const items = Array.isArray(data.items) ? data.items : [];
        setBadge(data.unread_count);
        pop.innerHTML = '<div class="ns-nh">Уведомления' + (data.unread_count ? ' <span class="ns-tag" style="margin-left:8px">' + data.unread_count + '</span>' : '') +
            (data.unread_count && items.length ? '<button type="button" id="nsNAll">Прочитать все</button>' : '') + '</div><div class="ns-nl">' +
            (items.length ? items.map((n, i) => '<div class="ns-ni' + (n.is_read ? ' ns-read' : '') + '" data-i="' + i + '"><span class="ns-nd"></span><div><b>' + esc(n.title) + '</b>' +
                (n.body ? '<p>' + esc(n.body) + '</p>' : '') + '<small>' + esc(n.time) + '</small></div></div>').join('')
                : '<div class="ns-ni ns-read"><div><p>Уведомлений пока нет</p></div></div>') +
            '</div>' + (cfg.allUrl ? '<a class="ns-mi ns-ntf-all" href="' + esc(cfg.allUrl) + '">Все уведомления</a>' : '');
        pop.querySelectorAll('.ns-ni[data-i]').forEach((el) => el.addEventListener('click', async () => {
            const n = items[+el.dataset.i];
            hidePop();
            if (!n.is_read && n.read_url) { try { await post(n.read_url); } catch (e) {} }
            if (n.url) location.href = n.url;
        }));
        $('nsNAll')?.addEventListener('click', async () => {
            const first = items.find((n) => n.read_url);
            hidePop();
            if (first) { try { await post(first.read_url + (first.read_url.includes('?') ? '&' : '?') + 'all=1'); } catch (e) {} }
            setBadge(0);
            toast('Все уведомления прочитаны');
        });
    };
    bell?.addEventListener('click', (e) => { e.stopPropagation(); if (!pop.hidden && pop.classList.contains('ns-ntf')) hidePop(); else openNotif(); });

    const showSystem = (title, body, url) => {
        if (!sysNotif || Notification.permission !== 'granted') return;
        try { const n = new Notification(title, { body: body || '' }); if (url) n.onclick = () => { window.focus(); location.href = url; }; } catch (e) {}
    };
    let lastId = Number(store.get(cfg.notifKey) || 0);
    const poll = async () => {
        if (!cfg.pollUrl) return;
        try {
            const res = await fetch(cfg.pollUrl + '?after_id=' + lastId, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (!res.ok) return;
            const data = await res.json();
            if (lastId > Number(data.max_id || 0)) { lastId = 0; store.set(cfg.notifKey, '0'); }
            setBadge(data.unread_count);
            const fresh = (Array.isArray(data.notifications) ? data.notifications : []).filter((n) => n && n.id);
            const first = lastId === 0 && !store.get(cfg.notifKey + '_seen');
            fresh.forEach((n) => { lastId = Math.max(lastId, Number(n.id)); });
            store.set(cfg.notifKey, String(lastId));
            store.set(cfg.notifKey + '_seen', '1');
            if (first || !fresh.length) return;      // первый заход в браузере — старые не озвучиваем
            const last = fresh[fresh.length - 1];
            toast((last.title || 'Уведомление') + (last.body ? ': ' + last.body : ''));
            fresh.forEach((n) => showSystem(n.title || 'Уведомление', n.body || '', n.url || null));
            ding();
        } catch (e) {}
    };
    poll();
    setInterval(poll, 25000);

    /* ---------- Esc ---------- */
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape' || e.defaultPrevented) return;
        hidePop(); closeModal(); closeSide();
    });

    window.ns = { toast, showPop, hidePop, menuPop, openModal, closeModal, ding, post, esc };
})();
