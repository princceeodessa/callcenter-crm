{{-- Сканер штрихкодов работает как клавиатура: очень быстро «печатает» код и (обычно) жмёт Enter.
     Скан с ценника в любом месте раздела кроссовок → продажа этих кроссовок (через /scan).
     Поля, которые сами ждут скан (поиск в «Продаже», «Скан», «Приёмка», коды ЧЗ), помечены data-scan="own" — их не трогаем.
     В обычном поле ввода скан тоже ловим: сканер печатает в разы быстрее человека, набранный им текст из поля убираем. --}}
<script>
(() => {
    const SCAN_URL = @json(route('scan'));
    const MIN_LEN = 4;
    const MAX_AVG_FREE = 90;    // мс между символами, когда курсор не в поле
    const MAX_AVG_FIELD = 35;   // в поле ввода — строже: быстрый человек печатает от ~60 мс
    let buf = '', first = 0, last = 0, idle = null, field = null, fieldStart = '';

    const isField = (t) => t && (t.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT'].includes(t.tagName));
    const own = (t) => t && t.closest && t.closest('[data-scan="own"]');
    const avg = () => (last - first) / Math.max(1, buf.length - 1);

    const go = () => { location.href = SCAN_URL + '?code=' + encodeURIComponent(buf); };

    const fire = () => {
        if (buf.length < MIN_LEN) return false;
        if (field) {
            if (avg() >= MAX_AVG_FIELD) return false;
            // убрать из поля то, что «напечатал» сканер
            if (typeof field.value === 'string' && field.value.endsWith(buf)) field.value = field.value.slice(0, -buf.length);
            else if (typeof field.value === 'string') field.value = fieldStart;
        } else if (avg() >= MAX_AVG_FREE) {
            return false;
        }
        go();
        return true;
    };

    document.addEventListener('keydown', (e) => {
        const t = e.target;
        if (own(t) || t.tagName === 'SELECT') { buf = ''; return; }
        if (e.ctrlKey || e.altKey || e.metaKey) return;
        if (e.repeat) { buf = ''; return; }                          // зажатая клавиша — автоповтор, не сканер
        const now = performance.now();
        if (now - last > 300) {                                   // новый ввод
            buf = ''; first = now;
            field = isField(t) ? t : null;
            fieldStart = field && typeof field.value === 'string' ? field.value : '';
        }
        last = now;
        clearTimeout(idle);
        if (e.key === 'Enter' || e.key === 'Tab') {               // конец скана: Enter или Tab
            if (fire()) e.preventDefault();
            buf = '';
            return;
        }
        if (e.key.length === 1) {
            buf += e.key;
            // сканер без Enter/Tab в конце — срабатываем после короткой паузы
            idle = setTimeout(() => { if (buf.length >= 6) fire(); buf = ''; }, 250);
        }
    }, true);
})();
</script>
