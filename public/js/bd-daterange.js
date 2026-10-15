/* Календарь выбора периода из дизайн-системы «БлагоДар» (макет дизайнера): разметка — resources/views/owner/_daterange.
   На страницах владельца стили — в blagodar-dashboard.css, на страницах CRM — public/css/bd-daterange.css (обёртка .bd-dr).
   Подключается файлом и в layouts/owner, и на страницах CRM; init() можно звать повторно. */
window.bdDateRange = (function () {
    const MONTHS = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    const WD = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'];
    const pad = n => String(n).padStart(2, '0');
    const parse = s => { const m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(s || ''); return m ? new Date(+m[1], +m[2] - 1, +m[3]) : null; };
    const iso = d => d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    const ts = d => new Date(d.getFullYear(), d.getMonth(), d.getDate()).getTime();
    const same = (a, b) => !!(a && b) && ts(a) === ts(b);
    const fmtD = d => pad(d.getDate()) + '.' + pad(d.getMonth() + 1);
    const fmtFull = d => fmtD(d) + '.' + d.getFullYear();
    const rangeText = (a, b) => same(a, b) ? fmtFull(a) : (a.getFullYear() === b.getFullYear() ? fmtD(a) : fmtFull(a)) + ' — ' + fmtFull(b);
    const closeAll = () => document.querySelectorAll('[data-dr-pop]').forEach(p => { p.hidden = true; });

    function setup(root) {
        if (root.dataset.drReady) return;
        root.dataset.drReady = '1';
        const day = root.dataset.mode === 'day';
        const btn = root.querySelector('[data-dr-btn]'), pop = root.querySelector('[data-dr-pop]');
        const max = parse(root.dataset.max);
        let selStart = parse(root.dataset.from), selEnd = parse(root.dataset.to) || selStart;
        const base = selEnd || new Date();
        let view = { y: base.getFullYear(), m: base.getMonth() };
        function render() {
            let h = '<div class="cal-head"><button type="button" class="cal-nav" data-nav="-1" aria-label="Предыдущий месяц">‹</button>'
                + '<div class="m">' + MONTHS[view.m] + ' ' + view.y + '</div>'
                + '<button type="button" class="cal-nav" data-nav="1" aria-label="Следующий месяц">›</button></div><div class="cal-grid">';
            WD.forEach(w => { h += '<div class="wd">' + w + '</div>'; });
            const startIdx = (new Date(view.y, view.m, 1).getDay() + 6) % 7;
            const dim = new Date(view.y, view.m + 1, 0).getDate();
            const prevDays = new Date(view.y, view.m, 0).getDate();
            const today = new Date();
            for (let i = startIdx - 1; i >= 0; i--) h += '<div class="d muted">' + (prevDays - i) + '</div>';
            for (let dd = 1; dd <= dim; dd++) {
                const d = new Date(view.y, view.m, dd), t = ts(d);
                if (max && t > ts(max)) { h += '<div class="d muted">' + dd + '</div>'; continue; }
                let cls = 'd';
                if (selStart && selEnd && t > ts(selStart) && t < ts(selEnd)) cls += ' in-range';
                if (same(d, selStart)) cls += ' range-start';
                if (same(d, selEnd) || (selStart && !selEnd && same(d, selStart))) cls += ' range-end';
                if (same(d, today)) cls += ' today';
                h += '<div class="' + cls + '" data-day="' + dd + '">' + dd + '</div>';
            }
            const trailing = (7 - ((startIdx + dim) % 7)) % 7;
            for (let i = 1; i <= trailing; i++) h += '<div class="d muted">' + i + '</div>';
            const rng = selStart && selEnd ? rangeText(selStart, selEnd) : (selStart ? fmtD(selStart) + ' — …' : (day ? 'выберите день' : 'выберите период'));
            h += '</div><div class="cal-foot"><button type="button" class="cal-reset" data-dr-reset>Сбросить</button><span class="rng">' + rng + '</span>'
                + '<button type="button" class="cal-apply" data-dr-apply>Применить</button></div>';
            pop.innerHTML = h;
        }
        btn.addEventListener('click', e => {
            e.stopPropagation();
            const open = pop.hidden;
            closeAll();
            if (open) { pop.hidden = false; render(); }
        });
        pop.addEventListener('click', e => {
            e.stopPropagation();   // клик внутри календаря — не «клик мимо»
            const nav = e.target.closest('[data-nav]');
            if (nav) {
                view.m += +nav.dataset.nav;
                if (view.m < 0) { view.m = 11; view.y--; }
                if (view.m > 11) { view.m = 0; view.y++; }
                render();
                return;
            }
            const dc = e.target.closest('.d[data-day]');
            if (dc) {
                const d = new Date(view.y, view.m, +dc.dataset.day);
                if (day) { selStart = d; selEnd = d; }
                else if (!selStart || selEnd) { selStart = d; selEnd = null; }
                else if (ts(d) < ts(selStart)) { selEnd = selStart; selStart = d; }
                else selEnd = d;
                render();
                return;
            }
            if (e.target.closest('[data-dr-reset]')) { selStart = null; selEnd = null; render(); return; }
            if (e.target.closest('[data-dr-apply]')) {
                if (selStart && !selEnd) selEnd = selStart;
                if (!selStart) return;
                const q = new URLSearchParams(JSON.parse(root.dataset.params || '{}'));
                if (day) q.set('date', iso(selStart)); else { q.set('from', iso(selStart)); q.set('to', iso(selEnd)); }
                root.querySelector('[data-dr-label]').textContent = day ? fmtFull(selStart) : rangeText(selStart, selEnd);
                pop.hidden = true;
                location.href = root.dataset.action + '?' + q.toString();
            }
        });
    }
    document.addEventListener('click', closeAll);
    document.addEventListener('keydown', e => { if (e.key === 'Escape') closeAll(); });

    return {
        init: () => document.querySelectorAll('[data-daterange]').forEach(setup),
        isOpen: () => !!document.querySelector('[data-dr-pop]:not([hidden])'),
    };
})();
