{{--
    Поиск по списку «Сделка» в окне «Новое дело»: поле [data-deal-filter] внутри [data-deal-pick] оставляет в select только
    подходящие варианты (все слова запроса; телефон — по цифрам, 8… = 7…). Неподходящие варианты снимаются из select, а не
    прячутся: родной список iOS hidden не понимает, а список «Нордики» собирается из того, что в select есть.
    Слушатель на document — окно кроссовок вставляется из <template> уже после загрузки.
--}}
<script>
(() => {
    const digits = (s) => s.replace(/\D+/g, '');
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && e.target.matches?.('[data-deal-filter]')) e.preventDefault();
    });
    document.addEventListener('input', (e) => {
        const input = e.target;
        if (!input.matches?.('[data-deal-filter]')) return;
        const sel = input.closest('[data-deal-pick]')?.querySelector('select[name="deal_id"]');
        if (!sel) return;
        sel._dealNodes ??= Array.from(sel.children).map((n) => ({ n, kids: n.tagName === 'OPTGROUP' ? Array.from(n.children) : null }));
        const words = input.value.trim().toLowerCase().split(/\s+/).filter(Boolean);
        const hit = (o) => o.value === '' || words.every((w) => {
            if (o.dataset.s.includes(w)) return true;
            let d = w.replace(/[()+-]/g, '');
            if (!/^\d{4,}$/.test(d)) return false;
            if (d.length === 11 && /^[78]/.test(d)) d = d.slice(1);
            return o.dataset.s.split(' ').some((t) => digits(t).includes(d));
        });
        const before = sel.value;
        const found = [];
        sel.replaceChildren();
        sel._dealNodes.forEach(({ n, kids }) => {
            if (!kids) { if (hit(n)) { sel.append(n); if (n.value) found.push(n); } return; }
            const keep = kids.filter(hit);
            n.replaceChildren(...keep);
            if (keep.length) { sel.append(n); found.push(...keep); }
        });
        sel.value = found.length === 1 && words.length ? found[0].value : (found.some((o) => o.value === before) ? before : '');
        sel.dispatchEvent(new Event('change', { bubbles: true }));
    });
})();
</script>
