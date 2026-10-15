/* Шапка страниц владельца (layouts/owner): поиск по сделкам и клиентам, уведомления, карточка сделки или клиента
   (только просмотр) и плеер клипа VK. Данные — JSON с window.bdFeedUrl (OwnerDashboardController, ?feed=…).
   Что из уведомлений прочитано — помнит браузер (localStorage bdNotifSeen). */
window.bdHead = (function () {
    'use strict';
    const FEED = window.bdFeedUrl || '/owner';
    const $ = id => document.getElementById(id);
    const esc = s => String(s == null ? '' : s).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    const plural = (n, a, b, c) => { const d = Math.abs(n) % 100, d1 = d % 10; return d > 10 && d < 20 ? c : (d1 > 1 && d1 < 5 ? b : (d1 === 1 ? a : c)); };
    const get = (params, signal) => fetch(FEED + '?' + new URLSearchParams(params), {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin', signal: signal,
    }).then(r => { if (!r.ok) throw new Error('HTTP ' + r.status); return r.json(); });

    const svg = (d, size) => '<svg width="' + (size || 16) + '" height="' + (size || 16) + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">' + d + '</svg>';
    const ICONS = {
        deal: svg('<path d="M21 3L3 21M14 4l6 6M4 14l6 6"/>', 15),
        measure: svg('<path d="M21 3L3 21M14 4l6 6M4 14l6 6"/>'),
        missed: svg('<path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3-8.6A2 2 0 014.1 2h3a2 2 0 012 1.7c.1.9.4 1.8.7 2.7a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6"/><path d="M23 1l-6 6M17 1l6 6"/>'),
        form: svg('<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/>'),
        report: svg('<path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6"/>'),
        sale: svg('<circle cx="9" cy="21" r="1.4"/><circle cx="18" cy="21" r="1.4"/><path d="M1 1h4l2.7 13.4a2 2 0 002 1.6h9.7a2 2 0 002-1.6L23 6H6"/>'),
        'return': svg('<path d="M3 12a9 9 0 109-9 9.7 9.7 0 00-6.7 2.7L3 8"/><path d="M3 3v5h5"/>'),
        phone: svg('<path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3-8.6A2 2 0 014.1 2h3a2 2 0 012 1.7c.1.9.4 1.8.7 2.7a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6"/>', 14),
    };
    const initials = name => (String(name).match(/[A-Za-zА-Яа-яЁё]+/g) || ['?']).slice(0, 2).map(w => w[0].toUpperCase()).join('');

    /* ---------- карточка сделки / клиента ---------- */
    const card = $('bdCard'), cardBody = $('bdCardBody');
    let cardBack = null; // из карточки клиента в сделку — кнопка «назад»
    function showModal(m) { m.hidden = false; document.body.classList.add('cm-open'); }
    function hideModal(m) {
        m.hidden = true;
        if (card.hidden && clip.hidden) document.body.classList.remove('cm-open');
    }
    function cardLoading() { cardBody.innerHTML = '<div class="sr-empty">Загружаю…</div>'; showModal(card); }
    function cardError() { cardBody.innerHTML = '<div class="sr-empty">Не получилось открыть — обновите страницу и попробуйте ещё раз</div>'; }
    const dealRow = (d, q) => '<a class="sr-item" href="#" data-deal="' + d.id + '"><span class="sr-ic green">' + ICONS.deal + '</span>' +
        '<div class="sr-main"><div class="sr-tt">' + hi(d.title, q) + '</div><div class="sr-sub">' + esc([d.biz, d.sub].filter(Boolean).join(' · ')) + '</div></div>' +
        '<div class="sr-amt' + (d.amount ? '' : ' muted') + '">' + esc(d.amount || d.date) + '</div></a>';
    function contactLine(c, withClient) {
        if (!c || (!c.name && !c.phone)) return '';
        const phone = c.phone ? (c.tel ? '<a href="tel:' + esc(c.tel) + '">' + ICONS.phone + ' ' + esc(c.phone) + '</a>' : esc(c.phone)) : '';
        return '<div class="dc-contact">' + (c.name ? '<b>' + esc(c.name) + '</b>' : '') + phone +
            (withClient && c.id ? '<a href="#" data-client="' + c.id + '">все сделки клиента →</a>' : '') + '</div>';
    }
    function openDeal(id, back) {
        cardBack = back || null;
        cardLoading();
        get({ feed: 'deal', id: id }).then(d => {
            cardBody.innerHTML =
                (cardBack ? '<a href="#" class="dc-back" data-client="' + cardBack + '">← к клиенту</a>' : '') +
                '<div class="dc-head"><span class="sr-ic green">' + ICONS.deal + '</span><div class="sr-main"><div class="dc-ttl">' + esc(d.title) + '</div>' +
                '<div class="dc-sub">' + esc(d.biz) + ' · сделка №' + d.id + '</div></div></div>' +
                contactLine(d.contact, !cardBack) +
                '<dl class="dc-facts">' + d.facts.map(f => '<div><dt>' + esc(f.label) + '</dt><dd>' + esc(f.value) + '</dd></div>').join('') + '</dl>' +
                (d.events.length ? '<div class="dc-sec">История</div><ul class="dc-tl">' + d.events.map(e =>
                    '<li class="' + esc(e.tone) + '"><span class="dc-dot"></span><div><div class="dc-tx">' + esc(e.text) + '</div>' +
                    '<div class="dc-tm">' + esc(when(e.at)) + (e.who ? ' · ' + esc(e.who) : '') + '</div></div></li>').join('') + '</ul>' : '');
        }).catch(cardError);
    }
    function openClient(id) {
        cardBack = null;
        cardLoading();
        get({ feed: 'client', id: id }).then(c => {
            cardBody.innerHTML =
                '<div class="dc-head"><span class="sr-ic violet">' + esc(initials(c.name)) + '</span><div class="sr-main"><div class="dc-ttl">' + esc(c.name) + '</div>' +
                '<div class="dc-sub">' + esc(c.biz) + ' · клиент</div></div></div>' +
                contactLine({ phone: c.phone, tel: c.tel }, false) +
                '<div class="dc-sec">Сделки · ' + c.deals.length + '</div>' +
                (c.deals.length ? '<div class="dc-deals" data-from-client="' + c.id + '">' + c.deals.map(d => dealRow(d)).join('') + '</div>' : '<div class="sr-empty">Сделок нет</div>');
        }).catch(cardError);
    }
    card.addEventListener('click', e => {
        if (e.target.closest('[data-cm-close]')) { hideModal(card); return; }
        const d = e.target.closest('[data-deal]'), c = e.target.closest('[data-client]');
        if (d) { e.preventDefault(); const from = d.closest('[data-from-client]'); openDeal(d.dataset.deal, from ? from.dataset.fromClient : null); }
        else if (c) { e.preventDefault(); openClient(c.dataset.client); }
    });

    /* ---------- плеер клипа VK ---------- */
    const clip = $('bdClip'), frame = $('bdClipFrame');
    function openClip(b) {
        const oid = b.dataset.oid, vid = b.dataset.vid;
        frame.src = 'https://vk.com/video_ext.php?oid=' + encodeURIComponent(oid) + '&id=' + encodeURIComponent(vid) + '&hd=2&autoplay=1';
        $('bdClipTtl').textContent = b.dataset.title || ('Клип ' + vid);
        $('bdClipSub').textContent = b.dataset.sub || 'VK Клип · БлагоДар';
        $('bdClipVk').href = b.dataset.url || ('https://vk.com/clip' + oid + '_' + vid);
        showModal(clip);
    }
    function closeClip() { hideModal(clip); frame.src = 'about:blank'; }
    clip.addEventListener('click', e => { if (e.target.closest('[data-cm-close]')) closeClip(); });
    document.addEventListener('click', e => { const w = e.target.closest('.watch[data-vid]'); if (w) { e.preventDefault(); openClip(w); } });

    /* ---------- поиск ---------- */
    const sBox = $('bdSearchBox'), sInput = $('bdSearch'), sPop = $('bdSearchPop');
    const narrow = window.matchMedia('(max-width: 900px)');
    let sTimer = null, sCtl = null, sQuery = null;
    const HINT = '<div class="sr-hint">Начните вводить — имя клиента, номер телефона или название сделки</div>';
    function hi(text, q) {
        const i = q ? String(text).toLowerCase().indexOf(q.toLowerCase()) : -1;
        if (i < 0) return esc(text);
        return esc(text.slice(0, i)) + '<mark>' + esc(text.slice(i, i + q.length)) + '</mark>' + esc(text.slice(i + q.length));
    }
    function renderSearch(data, q) {
        if (!data.deals.length && !data.clients.length) { sPop.innerHTML = '<div class="sr-empty">Ничего не найдено по «' + esc(q) + '»</div>'; return; }
        let h = '<div class="sr-scroll">';
        if (data.deals.length) {
            h += '<div class="sr-group">Сделки</div>' + data.deals.map(d => dealRow(d, q)).join('');
        }
        if (data.clients.length) {
            h += '<div class="sr-group">Клиенты</div>' + data.clients.map(c => '<a class="sr-item" href="#" data-client="' + c.id + '"><span class="sr-ic violet">' + esc(initials(c.name)) + '</span>' +
                '<div class="sr-main"><div class="sr-tt">' + hi(c.name, q) + '</div><div class="sr-sub">' + esc([c.biz, c.phone].filter(Boolean).join(' · ')) + '</div></div>' +
                '<div class="sr-amt muted">' + c.deals + ' ' + plural(c.deals, 'сделка', 'сделки', 'сделок') + '</div></a>').join('');
        }
        sPop.innerHTML = h + '</div>';
    }
    function runSearch() {
        const q = sInput.value.trim();
        if (q === sQuery && sPop.innerHTML !== '') return;
        sQuery = q;
        if (sCtl) sCtl.abort();
        if (q.length < 2) { sPop.innerHTML = HINT; return; }
        sCtl = new AbortController();
        if (!sPop.querySelector('.sr-scroll')) sPop.innerHTML = '<div class="sr-hint">Ищу «' + esc(q) + '»…</div>';
        get({ feed: 'search', q: q }, sCtl.signal).then(d => { if (q === sInput.value.trim()) renderSearch(d, q); })
            .catch(e => { if (e.name !== 'AbortError') sPop.innerHTML = '<div class="sr-empty">Поиск не ответил — попробуйте ещё раз</div>'; });
    }
    function openSearch() {
        document.querySelectorAll('.menu-pop').forEach(p => { p.hidden = true; });
        if (narrow.matches) { sBox.classList.add('open'); document.body.classList.add('search-open'); }
        sPop.hidden = false;
        runSearch();
    }
    function closeSearch() {
        sPop.hidden = true;
        sBox.classList.remove('open');
        document.body.classList.remove('search-open');
    }
    sInput.addEventListener('focus', openSearch);
    sInput.addEventListener('input', () => {
        if (document.activeElement !== sInput) return;
        sPop.hidden = false;
        clearTimeout(sTimer);
        sTimer = setTimeout(runSearch, 250);
    });
    sBox.querySelector('.search').addEventListener('click', () => { if (narrow.matches && !sBox.classList.contains('open')) { openSearch(); sInput.focus(); } });
    sPop.addEventListener('click', e => {
        const d = e.target.closest('[data-deal]'), c = e.target.closest('[data-client]');
        if (!d && !c) return;
        e.preventDefault();
        closeSearch();
        sInput.blur();
        d ? openDeal(d.dataset.deal) : openClient(c.dataset.client);
    });
    document.addEventListener('click', e => { if (!sBox.contains(e.target)) closeSearch(); });

    /* ---------- уведомления ---------- */
    const nList = $('bdNotifList'), nRead = $('bdNotifRead'), nDot = $('bdBellDot');
    const SEEN = 'bdNotifSeen';
    let nItems = null, nBusy = false;
    const seenAt = () => { try { return Number(localStorage.getItem(SEEN)) || 0; } catch (e) { return 0; } };
    function when(iso) {
        const t = new Date(iso), now = new Date(), min = Math.round((now - t) / 60000);
        const hm = t.toLocaleTimeString('ru-RU', { hour: '2-digit', minute: '2-digit' });
        if (min < 1) return 'только что';
        if (min < 60) return min + ' ' + plural(min, 'минуту', 'минуты', 'минут') + ' назад';
        if (min < 180) { const h = Math.floor(min / 60); return h + ' ' + plural(h, 'час', 'часа', 'часов') + ' назад'; }
        const day = new Date(now.getFullYear(), now.getMonth(), now.getDate());
        if (t >= day) return 'сегодня, ' + hm;
        if (t >= new Date(day - 864e5)) return 'вчера, ' + hm;
        return t.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' }) + ', ' + hm;
    }
    function renderNotifs() {
        if (!nItems) return;
        const seen = seenAt();
        const unread = nItems.filter(i => Date.parse(i.at) > seen).length;
        nDot.hidden = unread === 0;
        nRead.disabled = unread === 0;
        nList.innerHTML = nItems.length ? nItems.map(i =>
            '<div class="notif-item' + (Date.parse(i.at) > seen ? ' unread' : '') + (i.deal || i.url ? ' link' : '') + '" tabindex="0"' +
            (i.deal ? ' data-deal="' + i.deal + '"' : '') + (i.url ? ' data-url="' + esc(i.url) + '"' : '') + '>' +
            '<span class="ni-ic ' + esc(i.tone) + '">' + (ICONS[i.icon] || ICONS.report) + '</span>' +
            '<div><div class="ni-tx">' + esc(i.text) + '</div>' + (i.sub ? '<div class="ni-sub">' + esc(i.sub) + '</div>' : '') +
            '<div class="ni-tm">' + esc(when(i.at)) + '</div></div></div>').join('')
            : '<div class="sr-empty">За последние 3 дня событий нет</div>';
    }
    function loadNotifs() {
        if (nBusy || document.hidden) return;
        nBusy = true;
        get({ feed: 'notifications' }).then(d => { nItems = d.items || []; renderNotifs(); })
            .catch(() => { if (!nItems) nList.innerHTML = '<div class="sr-empty">Не получилось загрузить — попробуем ещё раз через минуту</div>'; })
            .finally(() => { nBusy = false; });
    }
    nRead.addEventListener('click', e => {
        e.stopPropagation();
        const last = Math.max(Date.now(), ...(nItems || []).map(i => Date.parse(i.at)));
        try { localStorage.setItem(SEEN, String(last)); } catch (err) {}
        renderNotifs();
    });
    function openNotif(el) {
        if (el.dataset.deal) { $('bdNotifPop').hidden = true; openDeal(el.dataset.deal); }
        else if (el.dataset.url) { window.location.href = el.dataset.url; }
    }
    nList.addEventListener('click', e => { const it = e.target.closest('.notif-item.link'); if (it) { e.stopPropagation(); openNotif(it); } });
    nList.addEventListener('keydown', e => { const it = e.target.closest('.notif-item.link'); if (it && (e.key === 'Enter' || e.key === ' ')) { e.preventDefault(); openNotif(it); } });
    loadNotifs();
    setInterval(loadNotifs, 60000);
    document.addEventListener('visibilitychange', () => { if (!document.hidden) loadNotifs(); });
    // прочитано в другой вкладке — точка гаснет и здесь
    window.addEventListener('storage', e => { if (e.key === SEEN) renderNotifs(); });

    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        if (!clip.hidden) closeClip();
        else if (!card.hidden) hideModal(card);
        else {
            // Esc в поле поиска браузер ещё и очищает — не даём, просто закрываем
            if (document.activeElement === sInput) { e.preventDefault(); sInput.blur(); }
            closeSearch();
        }
    });

    return { openDeal, openClient, closeSearch, loadNotifs };
})();
