{{-- Сканер штрихкодов работает как клавиатура: быстро «печатает» код и (обычно) жмёт Enter.
     Если курсор не в поле ввода — открываем карточку товара по коду. Подключается в разделе кроссовок. --}}
<script>
(() => {
    const SCAN_URL = @json(route('scan'));
    const MAX_AVG_MS = 90;      // средняя пауза между символами у сканера (у человека — 150+ мс)
    const MIN_LEN = 4;
    let buf = '', first = 0, last = 0, idle = null;

    const fire = () => {
        const avg = (last - first) / Math.max(1, buf.length - 1);
        if (buf.length >= MIN_LEN && avg < MAX_AVG_MS) {
            location.href = SCAN_URL + '?code=' + encodeURIComponent(buf);
            return true;
        }
        return false;
    };

    document.addEventListener('keydown', (e) => {
        const t = e.target;
        if (t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName))) { buf = ''; return; }
        if (e.ctrlKey || e.altKey || e.metaKey) return;
        const now = performance.now();
        if (now - last > 300) { buf = ''; first = now; }   // долгая пауза — начинается новый ввод
        last = now;
        clearTimeout(idle);
        if (e.key === 'Enter' || e.key === 'Tab') {        // одни сканеры ставят в конце Enter, другие — Tab
            if (fire()) e.preventDefault();
            buf = '';
            return;
        }
        if (e.key.length === 1) {
            buf += e.key;
            // сканер без Enter/Tab в конце: срабатываем после короткой паузы
            idle = setTimeout(() => { if (buf.length >= 6) { fire(); } buf = ''; }, 250);
        }
    }, true);
})();
</script>
