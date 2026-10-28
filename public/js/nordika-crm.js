/* Редизайн колл-центра «Nordika» — поведение каркаса (layouts/app): меню, тема, сделка во всплывающем окне,
   окно уведомлений у колокольчика, подсказки поиска, звук уведомлений. Настройки — window.ndConfig из шаблона.
   В iframe (сделка в окне, html.nd-embed) — только ссылки «наружу» в основное окно и Esc. */
(() => {
    const cfg = window.ndConfig || {};
    const root = document.documentElement;
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
    const esc = (s) => String(s ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    const isDealUrl = (u) => u.origin === location.origin && /^\/deals\/\d+$/.test(u.pathname);

    /* ---------- выпадающие списки в стиле Nordika ----------
       Список <select> рисует система (синяя полоса, прямые углы) — поверх него наш: кнопка со стрелкой и всплывающий
       список. Настоящий <select> остаётся источником значения: выбор ставит его value и шлёт input/change, а установка
       select.value из скриптов страниц и hidden/disabled сразу видны на кнопке. */
    const SEL_SCOPE = '.nd-toolbar select, .nd-deal select, .nd-modal select, .nd-field select, .nd-cal-page select, #measurementModal select, '
        + '.nd-embed-page select, .nd-page[data-route^="deals."] select, .nd-page[data-route^="tasks."] select, '
        + '.nd-page[data-route^="settings.users"] select, .nd-page[data-route^="reports."] select';
    const valueDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'value');
    const indexDesc = Object.getOwnPropertyDescriptor(HTMLSelectElement.prototype, 'selectedIndex');
    const chevron = '<svg class="nd-sel-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>';
    const closeSelects = (except) => document.querySelectorAll('.nd-sel.nd-open').forEach((w) => { if (w !== except) w.ndClose(); });
    const enhanceSelect = (sel) => {
        if (sel.multiple || sel.size > 1 || sel.dataset.ndSel || sel.closest('.nd-no-enhance')) return;
        sel.dataset.ndSel = '1';
        const wrap = document.createElement('div');
        wrap.className = 'nd-sel' + (sel.classList.contains('nd-input') ? ' nd-sel-tb' : '') + (sel.classList.contains('form-select-sm') ? ' nd-sel-sm' : '');
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'nd-sel-btn';
        btn.setAttribute('aria-haspopup', 'listbox');
        if (sel.getAttribute('aria-label')) btn.setAttribute('aria-label', sel.getAttribute('aria-label'));
        btn.innerHTML = '<span class="nd-sel-val"></span>' + chevron;
        wrap.appendChild(btn);
        const list = document.createElement('div');
        list.className = 'nd-sel-list';
        list.setAttribute('role', 'listbox');
        list.hidden = true;
        wrap.appendChild(list);
        sel.tabIndex = -1;
        sel.classList.add('nd-sel-native');
        if (sel.id) document.querySelectorAll(`label[for="${CSS.escape(sel.id)}"]`).forEach((l) => l.addEventListener('click', (e) => { e.preventDefault(); btn.focus(); }));

        const paint = () => {
            const o = sel.options[indexDesc.get.call(sel)];
            btn.querySelector('.nd-sel-val').textContent = o ? o.textContent.trim() : '';
            wrap.hidden = sel.hidden;
            btn.disabled = sel.disabled;
            wrap.classList.toggle('nd-sel-placeholder', !!o && o.value === '' && sel.required);
        };
        // значение, поставленное скриптом страницы (select.value = …), — сразу на кнопке
        Object.defineProperty(sel, 'value', { configurable: true, get() { return valueDesc.get.call(this); }, set(v) { valueDesc.set.call(this, v); paint(); } });
        Object.defineProperty(sel, 'selectedIndex', { configurable: true, get() { return indexDesc.get.call(this); }, set(v) { indexDesc.set.call(this, v); paint(); } });
        new MutationObserver(paint).observe(sel, { attributes: true, attributeFilter: ['hidden', 'disabled'], childList: true, subtree: true });
        sel.addEventListener('change', paint);

        let active = -1;
        const items = () => Array.from(list.querySelectorAll('.nd-sel-opt:not([disabled])'));
        const mark = (i) => {
            const all = items();
            if (!all.length) return;
            active = Math.max(0, Math.min(all.length - 1, i));
            all.forEach((el, k) => el.classList.toggle('nd-on', k === active));
            all[active].scrollIntoView({ block: 'nearest' });
        };
        const place = () => {
            const r = btn.getBoundingClientRect();
            const below = window.innerHeight - r.bottom - 12;
            const above = r.top - 12;
            const h = Math.min(list.scrollHeight, 320);
            list.style.minWidth = r.width + 'px';
            list.style.left = Math.min(r.left, window.innerWidth - Math.max(r.width, list.offsetWidth) - 8) + 'px';
            if (below < h && above > below) { list.style.top = 'auto'; list.style.bottom = (window.innerHeight - r.top + 6) + 'px'; list.style.maxHeight = Math.min(320, above) + 'px'; }
            else { list.style.bottom = 'auto'; list.style.top = (r.bottom + 6) + 'px'; list.style.maxHeight = Math.min(320, Math.max(below, 160)) + 'px'; }
        };
        wrap.ndClose = () => { list.hidden = true; wrap.classList.remove('nd-open'); btn.setAttribute('aria-expanded', 'false'); };
        const open = () => {
            closeSelects(wrap);
            let html = '', idx = 0;
            const cur = indexDesc.get.call(sel);
            Array.from(sel.children).forEach((node) => {
                const opts = node.tagName === 'OPTGROUP' ? Array.from(node.children) : [node];
                if (node.tagName === 'OPTGROUP') html += `<div class="nd-sel-grp">${esc(node.label)}</div>`;
                opts.forEach((o) => {
                    const i = Array.prototype.indexOf.call(sel.options, o);
                    if (o.hidden) return;
                    html += `<button type="button" role="option" class="nd-sel-opt${i === cur ? ' nd-sel-cur' : ''}" data-i="${i}"${o.disabled ? ' disabled' : ''}>`
                        + `<span>${esc(o.textContent.trim())}</span>${i === cur ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-11"/></svg>' : ''}</button>`;
                    idx++;
                });
            });
            list.innerHTML = html || '<div class="nd-sel-grp">Нет вариантов</div>';
            list.hidden = false;
            wrap.classList.add('nd-open');
            btn.setAttribute('aria-expanded', 'true');
            place();
            mark(items().findIndex((el) => +el.dataset.i === cur));
        };
        const choose = (i) => {
            if (indexDesc.get.call(sel) !== i) {
                indexDesc.set.call(sel, i);
                sel.dispatchEvent(new Event('input', { bubbles: true }));
                sel.dispatchEvent(new Event('change', { bubbles: true }));
            }
            paint();
            wrap.ndClose();
            btn.focus();
        };
        btn.addEventListener('click', (e) => { e.stopPropagation(); list.hidden ? open() : wrap.ndClose(); });
        list.addEventListener('mousedown', (e) => e.preventDefault());
        list.addEventListener('click', (e) => { e.stopPropagation(); const o = e.target.closest('.nd-sel-opt'); if (o && !o.disabled) choose(+o.dataset.i); });
        btn.addEventListener('keydown', (e) => {
            if (list.hidden) {
                if (['ArrowDown', 'ArrowUp', 'Enter', ' '].includes(e.key)) { e.preventDefault(); open(); }
                return;
            }
            if (e.key === 'ArrowDown') { e.preventDefault(); mark(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); mark(active - 1); }
            else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); const o = items()[active]; if (o) choose(+o.dataset.i); }
            else if (e.key === 'Escape' || e.key === 'Tab') { if (e.key === 'Escape') { e.preventDefault(); e.stopPropagation(); } wrap.ndClose(); }
            else if (e.key.length === 1) {                        // по первой букве
                const k = e.key.toLowerCase();
                const all = items();
                const from = active + 1;
                const hit = [...all.slice(from), ...all.slice(0, from)].find((el) => el.textContent.trim().toLowerCase().startsWith(k));
                if (hit) mark(all.indexOf(hit));
            }
        });
        // select.focus() из скрипта страницы (например «почему нецелевая?» сразу после hidden = false) — открыть наш список
        // (после текущего клика — иначе его «клик мимо» сразу закроет список)
        sel.focus = () => { paint(); if (wrap.hidden) return; btn.focus(); setTimeout(() => { if (list.hidden) open(); }, 0); };
        paint();
    };
    const enhanceSelects = (rootEl) => rootEl.querySelectorAll(SEL_SCOPE).forEach(enhanceSelect);
    window.ndEnhanceSelects = enhanceSelects;
    document.addEventListener('click', () => closeSelects());
    window.addEventListener('resize', () => closeSelects());
    document.addEventListener('scroll', (e) => { if (!e.target.closest?.('.nd-sel-list')) closeSelects(); }, true);
    enhanceSelects(document);

    /* ---------- поле «выбрать файл»: имя выбранного файла рядом с кнопкой ---------- */
    document.addEventListener('change', (e) => {
        const inp = e.target;
        if (!(inp instanceof HTMLInputElement) || inp.type !== 'file' || !inp.closest('.nd-file')) return;
        const name = inp.closest('.nd-file').querySelector('.nd-file-name');
        const files = Array.from(inp.files || []);
        name.textContent = files.length ? files.map((f) => f.name).join(', ') : name.dataset.empty;
        inp.closest('.nd-file').classList.toggle('nd-has-file', files.length > 0);
    });

    /* ---------- страница внутри окна сделки ---------- */
    if (root.classList.contains('nd-embed')) {
        document.addEventListener('click', (e) => {
            if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
            const a = e.target.closest('a[href]');
            if (!a || (a.target && a.target !== '_self')) return;
            const u = new URL(a.getAttribute('href'), location.href);
            if (u.origin !== location.origin) return;
            if (u.pathname === location.pathname) return;          // якоря, «Изменить» дело — внутри окна
            e.preventDefault();
            window.top.location.href = u.href;                    // чаты, проект и т.п. — в основном окне
        });
        document.addEventListener('keydown', (e) => {
            if (e.key !== 'Escape' || e.defaultPrevented) return;            // Esc в открытом списке закрывает только список
            if (window.parent !== window) window.parent.postMessage({ nd: 'close' }, location.origin);
        });
        // сделку переоткрыли — окно закрывается само, страница под ним обновится и скажет, что сделано
        const done = document.getElementById('ndEmbedDone');
        if (done && window.parent !== window) window.parent.postMessage({ nd: 'close', toast: done.dataset.msg || '' }, location.origin);
        return;
    }

    /* ---------- короткое сообщение после обновления страницы (из окна сделки) ---------- */
    let ndToast = null;
    try { ndToast = sessionStorage.getItem('ndToast'); sessionStorage.removeItem('ndToast'); } catch (e) {}
    if (ndToast) {
        const t = document.createElement('div');                 // .nd-toast / .nd-show — из макета дизайнера
        t.className = 'nd-toast';
        t.setAttribute('role', 'status');
        t.textContent = ndToast;
        document.body.appendChild(t);
        requestAnimationFrame(() => requestAnimationFrame(() => t.classList.add('nd-show')));
        setTimeout(() => t.classList.remove('nd-show'), 4000);
        setTimeout(() => t.remove(), 4500);
    }

    /* ---------- меню (телефон) и тема ---------- */
    const side = document.getElementById('ndSide');
    const mscrim = document.getElementById('ndMscrim');
    const closeMenu = () => { side?.classList.remove('nd-open'); mscrim?.classList.remove('nd-on'); };
    document.getElementById('ndBurger')?.addEventListener('click', () => { side?.classList.add('nd-open'); mscrim?.classList.add('nd-on'); });
    mscrim?.addEventListener('click', closeMenu);
    document.getElementById('ndThemeBtn')?.addEventListener('click', () => {
        const theme = root.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-theme', theme);
        root.setAttribute('data-bs-theme', theme);
        try { localStorage.setItem('ndTheme', theme); } catch (e) {}
    });

    /* ---------- сделка во всплывающем окне ---------- */
    const dScrim = document.getElementById('ndDealScrim');
    const dFrame = document.getElementById('ndDealFrame');
    const dLoading = document.getElementById('ndDealLoading');
    const dNewTab = document.getElementById('ndDealNewTab');
    let dLoads = 0;
    window.ndOpenDeal = (href) => {
        if (!dScrim || !dFrame) { location.href = href; return; }
        const u = new URL(href, location.href);
        dNewTab.href = u.href;
        u.searchParams.set('embed', '1');
        dLoads = 0;
        dLoading.hidden = false;
        dFrame.src = u.href;
        dScrim.classList.add('nd-on');
        dScrim.setAttribute('aria-hidden', 'false');
        document.body.classList.add('nd-modal-open');
    };
    const closeDeal = (force) => {
        if (!dScrim?.classList.contains('nd-on')) return;
        dScrim.classList.remove('nd-on');
        dScrim.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('nd-modal-open');
        // в окне отправляли форму — обновим страницу; окно само сказало, что сделано (force), — тоже,
        // даже если его вторая загрузка ещё не досчитана: сообщение приходит раньше события load
        const changed = force === true || dLoads > 1;
        dFrame.src = 'about:blank';
        if (changed) location.reload();
    };
    dFrame?.addEventListener('load', () => {
        if (dFrame.src === 'about:blank' || !dScrim.classList.contains('nd-on')) return;
        dLoads++;
        dLoading.hidden = true;
    });
    document.getElementById('ndDealClose')?.addEventListener('click', closeDeal);
    dScrim?.addEventListener('click', (e) => { if (e.target === dScrim) closeDeal(); });
    window.addEventListener('message', (e) => {
        if (e.origin !== location.origin || e.data?.nd !== 'close') return;
        if (e.data.toast) { try { sessionStorage.setItem('ndToast', String(e.data.toast)); } catch (err) {} }
        closeDeal(Boolean(e.data.toast));
    });

    // клик по ссылке на сделку или по строке таблицы — окно; Ctrl/колёсико — как обычно, в новой вкладке
    document.addEventListener('click', (e) => {
        if (e.defaultPrevented || e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        const a = e.target.closest('a[href]');
        if (a) {
            if (a.target && a.target !== '_self') return;
            const u = new URL(a.getAttribute('href'), location.href);
            if (isDealUrl(u) && !u.search) { e.preventDefault(); window.ndOpenDeal(u.href); }
            return;
        }
        const row = e.target.closest('[data-nd-href]');
        if (!row || e.target.closest('button, input, select, textarea, label, form, details')) return;
        const u = new URL(row.dataset.ndHref, location.href);
        if (isDealUrl(u)) window.ndOpenDeal(u.href); else location.href = u.href;
    });

    /* ---------- звук уведомлений ---------- */
    const sndBtn = document.getElementById('ndSoundBtn');
    const isMuted = () => { try { return localStorage.getItem('ndSoundMuted') === '1'; } catch (e) { return false; } };
    window.ndSoundMuted = isMuted;
    const paintSound = () => {
        const m = isMuted();
        sndBtn?.classList.toggle('nd-muted', m);
        sndBtn?.setAttribute('aria-pressed', m ? 'true' : 'false');
        sndBtn?.setAttribute('title', m ? 'Звук уведомлений выключен — нажмите, чтобы включить' : 'Звук уведомлений включён — нажмите, чтобы выключить');
    };
    sndBtn?.addEventListener('click', () => {
        const m = !isMuted();
        try { localStorage.setItem('ndSoundMuted', m ? '1' : '0'); } catch (e) {}
        paintSound();
        if (!m && 'Notification' in window && Notification.permission === 'default') {
            try { Notification.requestPermission(); } catch (e) {}
        }
    });
    paintSound();

    /* ---------- окно уведомлений ---------- */
    const bell = document.getElementById('ndBellBtn');
    const nPop = document.getElementById('ndNotifPop');
    const nList = document.getElementById('ndNotifList');
    const badge = document.getElementById('navNotifBadge');
    const setBadge = (n) => {
        if (!badge) return;
        badge.textContent = String(n);
        badge.classList.toggle('d-none', !(n > 0));
    };
    const post = (url) => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });
    // иконка уведомления: по смыслу текста (звонок, замер, сообщение…), иначе по типу; цвет — по типу
    const NI = {
        clock: '<circle cx="12" cy="13" r="8"/><path d="M12 9v4l2.5 2M5 3L2 6M19 3l3 3"/>',
        phone: '<path d="M5 4h3l1.5 5-2 1.5a12 12 0 006 6l1.5-2 5 1.5V20a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>',
        ruler: '<rect x="3" y="4" width="18" height="17" rx="2"/><path d="M16 2v4M8 2v4M3 10h18M8 14h.01M12 14h.01M16 14h.01"/>',
        chat: '<path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/>',
        doc: '<path d="M14 3H7a2 2 0 00-2 2v14a2 2 0 002 2h10a2 2 0 002-2V8z"/><path d="M14 3v5h5M9 13h6M9 17h4"/>',
        task: '<path d="M9 11l3 3 8-8M4 12v6a2 2 0 002 2h12"/>',
        bag: '<path d="M6 7h12l1 14H5zM9 7a3 3 0 016 0"/>',
        box: '<path d="M21 8l-9-5-9 5v8l9 5 9-5z"/><path d="M3 8l9 5 9-5M12 13v8"/>',
        bell: '<path d="M18 8a6 6 0 00-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 01-3.4 0"/>',
    };
    const iconFor = (n) => {
        const t = ((n.title || '') + ' ' + (n.body || '')).toLowerCase();
        const color = { task_due: 'var(--amber)', task_created: 'var(--blue)', sneaker_sale: 'var(--accent)', low_stock: 'var(--red)' }[n.type] || 'var(--lemana)';
        const icon = /перезвон|звон|дозвон/.test(t) ? 'phone'
            : /замер/.test(t) ? 'ruler'
            : /сообщ|чат|напис/.test(t) ? 'chat'
            : /договор|документ|счёт|счет/.test(t) ? 'doc'
            : ({ task_due: 'clock', task_created: 'task', sneaker_sale: 'bag', low_stock: 'box' }[n.type] || 'bell');
        return `<span class="nd-notif-ic" style="--ic:${color}"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">${NI[icon]}</svg></span>`;
    };
    const loadNotifs = async () => {
        nList.innerHTML = '<div class="nd-notif-empty">Загружаю…</div>';
        try {
            const r = await fetch(cfg.notifListUrl, { headers: { 'Accept': 'application/json' } });
            const j = await r.json();
            setBadge(j.unread_count || 0);
            const items = j.items || [];
            nList.innerHTML = items.length ? items.map((n) => `
                <button type="button" class="nd-notif-i${n.is_read ? '' : ' nd-unread'}" data-url="${esc(n.url || '')}" data-read="${esc(n.read_url)}">
                    ${iconFor(n)}
                    <span class="nd-notif-txt"><b>${esc(n.title)}</b>${n.body ? `<span>${esc(n.body)}</span>` : ''}<small>${esc(n.time)}</small></span>
                    <span class="nd-notif-dot" title="Не прочитано"></span>
                </button>`).join('') : '<div class="nd-notif-empty">Уведомлений пока нет</div>';
        } catch (e) {
            nList.innerHTML = '<div class="nd-notif-empty">Не получилось загрузить — попробуйте ещё раз</div>';
        }
    };
    bell?.addEventListener('click', (e) => {
        if (e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
        e.preventDefault();
        e.stopPropagation();
        const open = nPop.hidden;
        nPop.hidden = !open;
        if (open) loadNotifs();
    });
    nPop?.addEventListener('click', async (e) => {
        e.stopPropagation();
        const it = e.target.closest('.nd-notif-i');
        if (it) {
            if (it.classList.contains('nd-unread')) {
                it.classList.remove('nd-unread');
                setBadge(Math.max(0, (parseInt(badge?.textContent || '0', 10) || 0) - 1));
                post(it.dataset.read).catch(() => {});
            }
            const url = it.dataset.url;
            if (url) {
                nPop.hidden = true;
                const u = new URL(url, location.href);
                if (isDealUrl(u)) window.ndOpenDeal(u.href); else location.href = u.href;
            }
            return;
        }
        if (e.target.closest('#ndNotifReadAll')) {
            const first = nList.querySelector('.nd-notif-i');
            if (!first) return;
            const u = new URL(first.dataset.read, location.href);
            u.searchParams.set('all', '1');
            await post(u.href).catch(() => {});
            nList.querySelectorAll('.nd-unread').forEach((x) => x.classList.remove('nd-unread'));
            setBadge(0);
        }
    });
    document.addEventListener('click', () => { if (nPop) nPop.hidden = true; });

    /* ---------- поиск: недавние запросы и подсказки по сделкам ---------- */
    const sInput = document.querySelector('.nd-top .nd-search input[type="search"]');
    if (sInput && cfg.suggestUrl) {
        const box = sInput.closest('.nd-search');
        const form = sInput.form || sInput.closest('form');
        const drop = document.createElement('div');
        drop.className = 'nd-sdrop';
        drop.hidden = true;
        box.appendChild(drop);
        sInput.setAttribute('autocomplete', 'off');
        const HKEY = 'ndSearchHistory';
        const history = () => { try { return JSON.parse(localStorage.getItem(HKEY) || '[]'); } catch (e) { return []; } };
        const remember = (q) => {
            q = (q || '').trim();
            if (q.length < 2) return;
            const h = [q, ...history().filter((x) => x !== q)].slice(0, 7);
            try { localStorage.setItem(HKEY, JSON.stringify(h)); } catch (e) {}
        };
        let active = -1, timer = null, seq = 0;
        const items = () => Array.from(drop.querySelectorAll('[data-s]'));
        const highlight = (i) => {
            const list = items();
            active = list.length ? (i + list.length) % list.length : -1;
            list.forEach((el, k) => el.classList.toggle('nd-on', k === active));
        };
        const clock = '<svg class="nd-icon nd-sm" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>';
        const showHistory = () => {
            const h = history();
            if (!h.length) { drop.hidden = true; return; }
            drop.innerHTML = '<div class="nd-sdrop-h">Недавние запросы</div>'
                + h.map((q) => `<button type="button" class="nd-sdrop-i" data-s="q" data-q="${esc(q)}">${clock}<span>${esc(q)}</span></button>`).join('');
            drop.hidden = false;
            active = -1;
        };
        const showResults = (q, list) => {
            const here = form ? `<button type="button" class="nd-sdrop-i nd-sdrop-all" data-s="submit">${'<svg class="nd-icon nd-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>'}<span>Найти «${esc(q)}» ${esc(cfg.searchHint || 'на этой странице')}</span><kbd>Enter</kbd></button>` : '';
            drop.innerHTML = (list.length
                ? '<div class="nd-sdrop-h">Сделки</div>' + list.map((d) => `
                    <button type="button" class="nd-sdrop-i nd-sdrop-deal" data-s="deal" data-url="${esc(d.url)}">
                        <span class="nd-src" style="--src:${esc(d.src.color)}">${esc(d.src.label)}</span>
                        <span class="nd-sdrop-main"><b>${esc(d.title)}</b><small>#${d.id}${d.phone ? ' · ' + esc(d.phone) : ''}</small></span>
                        <span class="nd-stage-pill"><span class="nd-d" style="background:${esc(d.stage_color)}"></span>${esc(d.stage)}</span>
                    </button>`).join('')
                : '<div class="nd-sdrop-none">Сделок по запросу «' + esc(q) + '» не нашлось</div>') + here;
            drop.hidden = false;
            active = -1;
        };
        const query = () => {
            const q = sInput.value.trim();
            if (q.length < 2) { showHistory(); return; }
            const my = ++seq;
            const u = new URL(cfg.suggestUrl, location.href);
            u.searchParams.set('suggest', '1');
            u.searchParams.set('q', q);
            fetch(u.href, { headers: { 'Accept': 'application/json' } })
                .then((r) => r.json())
                .then((j) => { if (my === seq && document.activeElement === sInput) showResults(q, j.items || []); })
                .catch(() => {});
        };
        const pick = (el) => {
            if (!el) return;
            if (el.dataset.s === 'q') { sInput.value = el.dataset.q; sInput.dispatchEvent(new Event('input', { bubbles: true })); query(); sInput.focus(); return; }
            if (el.dataset.s === 'deal') { remember(sInput.value); drop.hidden = true; window.ndOpenDeal(el.dataset.url); return; }
            if (el.dataset.s === 'submit' && form) { remember(sInput.value); drop.hidden = true; form.requestSubmit ? form.requestSubmit() : form.submit(); }
        };
        sInput.addEventListener('focus', () => { sInput.value.trim().length >= 2 ? query() : showHistory(); });
        sInput.addEventListener('input', () => { clearTimeout(timer); timer = setTimeout(query, 220); });
        sInput.addEventListener('keydown', (e) => {
            if (drop.hidden) return;
            if (e.key === 'ArrowDown') { e.preventDefault(); highlight(active + 1); }
            else if (e.key === 'ArrowUp') { e.preventDefault(); highlight(active - 1); }
            else if (e.key === 'Escape') { drop.hidden = true; }
            else if (e.key === 'Enter' && active >= 0) { e.preventDefault(); pick(items()[active]); }
        });
        drop.addEventListener('mousedown', (e) => e.preventDefault());   // не терять фокус поля
        drop.addEventListener('click', (e) => { e.stopPropagation(); pick(e.target.closest('[data-s]')); });
        sInput.addEventListener('blur', () => setTimeout(() => { drop.hidden = true; }, 120));
        form?.addEventListener('submit', () => remember(sInput.value));
    }

    /* ---------- всплывающие окна страниц (data-nd-modal): «Новое дело», «Рассылка», «Изменить дело»… ---------- */
    const closeModal = (m) => {
        if (!m) return;
        if (m.dataset.ndCloseHref) { location.href = m.dataset.ndCloseHref; return; }
        m.classList.remove('nd-on');
    };
    document.querySelectorAll('.nd-scrim[data-nd-modal]').forEach((m) => {
        if (m.hasAttribute('data-nd-autoopen')) m.classList.add('nd-on');
        m.addEventListener('click', (e) => { if (e.target === m) closeModal(m); });
    });
    document.addEventListener('click', (e) => {
        const o = e.target.closest('[data-nd-open]');
        if (o) { e.preventDefault(); document.querySelector(o.dataset.ndOpen)?.classList.add('nd-on'); return; }
        const c = e.target.closest('[data-nd-close]');
        if (c) { e.preventDefault(); closeModal(c.closest('.nd-scrim')); }
    });

    /* ---------- Esc и календари выбора дат ---------- */
    document.addEventListener('keydown', (e) => {
        if (e.key !== 'Escape' || e.defaultPrevented) return;            // Esc в открытом списке закрывает только список
        closeMenu();
        closeDeal();
        if (nPop) nPop.hidden = true;
        document.querySelectorAll('.nd-scrim[data-nd-modal].nd-on').forEach(closeModal);
    });
    window.bdDateRange?.init();
})();
