<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Печать этикеток · {{ $types[$type] }}</title>
    @php
        $w = $format['w'];
        $h = $format['h'];
        $compact = $h <= 30 || $w <= 43;
        $isMark = $type === 'mark';
        $labelCount = $labels->count();
        $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $noPrice = $labels->filter(fn ($l) => ($l['price'] ?? null) === null)->count();
        $noCrypto = $isMark ? $labels->filter(fn ($l) => ! $l['has_crypto'])->count() : 0;
        $itemsCount = $items->count();
        $marksCount = $marks->count();
        $productsCount = $products->count();
        // Квадрат под DataMatrix: высота наклейки минус поля.
        $dmBox = $h - 3;
    @endphp
    <style>
        /*
         * Размер листа НЕ задаём: если указать «60mm 40mm», Chrome/Яндекс видит ширину > высоты,
         * сам включает альбомную ориентацию, драйвер поворачивает наклейку, и ценник выходит
         * боком на стыке двух наклеек. Лист берётся из драйвера (Page Setup 60×40, книжная),
         * а каждая этикетка — ровно один лист за счёт разрыва страницы после неё.
         */
        @page { margin: 0; }
        * { box-sizing: border-box; }
        html, body { margin: 0; }
        body { font-family: Arial, "Helvetica Neue", Helvetica, sans-serif; color: #000; background: #eef1f5; }

        /* ---------- экранная панель (на печать не идёт) ---------- */
        .toolbar { max-width: 1100px; margin: 0 auto; padding: 16px; font-size: 14px; }
        .toolbar h1 { font-size: 20px; margin: 0 0 12px; }
        .card { background: #fff; border: 1px solid #d8dee8; border-radius: 12px; padding: 12px 14px; margin-bottom: 12px; }
        .row { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }
        .seg { display: inline-flex; border: 1px solid #c7cfdb; border-radius: 8px; overflow: hidden; }
        .seg label { padding: 6px 10px; cursor: pointer; border-right: 1px solid #c7cfdb; user-select: none; }
        .seg label:last-child { border-right: 0; }
        .seg input { display: none; }
        .seg input:checked + span { font-weight: 700; color: #1d4ed8; }
        .seg label:has(input:checked) { background: #e8efff; }
        select, input[type=number], textarea { font: inherit; padding: 5px 8px; border: 1px solid #c7cfdb; border-radius: 6px; }
        .btn { font: inherit; padding: 8px 16px; border-radius: 8px; border: 1px solid #c7cfdb; background: #fff; cursor: pointer; text-decoration: none; color: #111; display: inline-block; }
        .btn-primary { background: #1d4ed8; border-color: #1d4ed8; color: #fff; font-weight: 700; }
        .btn-primary:disabled { opacity: .5; cursor: wait; }
        .btn-sm { padding: 4px 10px; font-size: 13px; }
        .muted { color: #64748b; }
        .warn { color: #b45309; }
        .bad { color: #b91c1c; }
        table.pick { border-collapse: collapse; width: 100%; font-size: 13px; }
        table.pick td, table.pick th { padding: 4px 6px; border-bottom: 1px solid #eef1f5; text-align: left; }
        table.pick input[type=number] { width: 64px; }
        .saved { background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; border-radius: 8px; padding: 6px 10px; margin-bottom: 8px; }
        input.price-in.empty { border-color: #f59e0b; background: #fffbeb; }
        .mono { font-family: Consolas, "Courier New", monospace; font-size: 12px; word-break: break-all; }
        details.guide summary { cursor: pointer; font-weight: 700; }
        details.guide ol { margin: 8px 0 0; padding-left: 20px; line-height: 1.5; }
        details.guide li { margin-bottom: 6px; }
        .sheet { display: flex; flex-wrap: wrap; gap: 10px; padding: 0 16px 40px; max-width: 1100px; margin: 0 auto; }

        /* ---------- сама этикетка ---------- */
        .lbl {
            width: {{ $w }}mm; height: {{ $h }}mm; overflow: hidden; background: #fff;
            padding: 1.5mm 2mm; display: flex; flex-direction: column;
            outline: 1px dashed #94a3b8; break-after: page; page-break-after: always;
        }
        .lbl:last-child { break-after: auto; page-break-after: auto; }
        .name { font-weight: 700; font-size: {{ $compact ? '7pt' : '8.5pt' }}; line-height: 1.12; max-height: {{ $compact ? '2.3em' : '3.4em' }}; overflow: hidden; }
        .brand { font-size: {{ $compact ? '5.5pt' : '6.5pt' }}; letter-spacing: .08em; text-transform: uppercase; }
        .size { font-size: {{ $compact ? '7pt' : '8.5pt' }}; margin-top: .6mm; }
        .size b { font-size: {{ $compact ? '9pt' : '11pt' }}; }
        .price { font-weight: 800; font-size: {{ $compact ? '14pt' : '19pt' }}; line-height: 1; margin-top: auto; white-space: nowrap; }
        .code { margin-top: auto; text-align: center; }
        .code svg { display: block; width: 100%; height: 100%; shape-rendering: crispEdges; }
        .art { font-family: Consolas, "Courier New", monospace; font-size: {{ $compact ? '6pt' : '7pt' }}; letter-spacing: .05em; margin-top: .3mm; }

        .lbl.mark { flex-direction: row; gap: 1.5mm; align-items: center; padding: 1.5mm; }
        /* ширина по самому коду (+ тихая зона 1 модуль), высота — не больше наклейки */
        .dm { height: {{ $dmBox }}mm; flex: none; display: flex; align-items: center; padding: 0 .5mm; }
        .dm svg { shape-rendering: crispEdges; }
        .mark-text { min-width: 0; flex: 1; display: flex; flex-direction: column; gap: .6mm; }
        .mark-text .hr { font-family: Consolas, "Courier New", monospace; font-size: {{ $compact ? '5pt' : '6pt' }}; line-height: 1.15; word-break: break-all; }
        .screen-only { }

        @media print {
            body { background: #fff; }
            .toolbar, .screen-only { display: none !important; }
            .sheet { display: block; padding: 0; margin: 0; max-width: none; }
            /* на полмиллиметра ниже листа: иначе при чуть меньшей печатной области драйвера
               этикетка переливается на второй лист и после каждой выходит пустая наклейка */
            .lbl { outline: 0; height: {{ $h - 0.5 }}mm; }
        }
    </style>
</head>
<body>

<div class="toolbar">
    <div class="row" style="justify-content:space-between;margin-bottom:12px">
        <h1 style="margin:0">🖨️ Печать этикеток</h1>
        @if($driverMb)
            <a class="btn btn-sm" href="{{ route('print.driver') }}" title="Официальный установщик для Windows — ставится на компьютер, к которому подключён принтер">⬇ Драйвер принтера XP-365B</a>
        @endif
    </div>

    <form method="POST" action="{{ route('print.labels') }}" id="printForm">
        @csrf
        <input type="hidden" name="products" value="{{ $productIdsCsv }}">

        <div class="card">
            <div class="row" style="margin-bottom:10px">
                <div class="seg">
                    @foreach($types as $key => $title)
                        <label><input type="radio" name="type" value="{{ $key }}" @checked($type === $key) onchange="this.form.submit()"><span>{{ $title }}</span></label>
                    @endforeach
                </div>
                <label>Размер наклейки
                    <select name="format" onchange="this.form.submit()">
                        @foreach($formats as $key => $f)
                            <option value="{{ $key }}" @selected($formatKey === $key)>{{ $f['label'] }}</option>
                        @endforeach
                    </select>
                </label>
                @if($type === 'price')
                    <label><input type="hidden" name="show_price" value="0"><input type="checkbox" name="show_price" value="1" @checked($showPrice) onchange="this.form.submit()"> цена на ценнике</label>
                @endif
            </div>

            <div class="row">
                <button type="button" class="btn btn-primary" id="printBtn" @disabled($labelCount === 0)>🖨️ Печать · {{ $labelCount }} шт.</button>
                <button type="submit" class="btn">↻ Обновить</button>
                <span class="muted">Рулон {{ $format['label'] }} · принтер XP-365B</span>
                @if($truncated)
                    <span class="warn">Показаны первые {{ $maxLabels }} — печатайте частями.</span>
                @endif
                @if($type === 'price' && $showPrice && $noPrice > 0)
                    <span class="warn">⚠ Без цены продажи: {{ $noPrice }} шт. — задайте цену на складе.</span>
                @endif
            </div>
        </div>

        @if($productsCount === 0 && ! $isMark)
            <div class="card">
                Не выбран товар. Откройте склад и нажмите <b>«🏷 Печать»</b> в карточке модели,
                или отметьте несколько моделей галочками и выберите <b>«🏷 Ценники / этикетки»</b> внизу.
                <div style="margin-top:8px"><a class="btn btn-sm" href="{{ route('warehouse.index') }}">→ На склад</a></div>
            </div>
        @endif

        @if(! $isMark && $itemsCount > 0)
            <div class="card">
                <div class="row" style="justify-content:space-between;margin-bottom:6px">
                    <b>Сколько штук по размерам</b>
                    <span class="row">
                        <button type="button" class="btn btn-sm" data-fill="1">по 1</button>
                        <button type="button" class="btn btn-sm" data-fill="stock">по остатку</button>
                        <button type="button" class="btn btn-sm" data-fill="0">обнулить</button>
                    </span>
                </div>
                @if($pricesSaved > 0)
                    <div class="saved">💾 Цена сохранена для {{ $pricesSaved }} размер(ов) — она же теперь на складе и в «Быстрой продаже».</div>
                @endif
                @if($isHead)
                    <div class="row" style="margin-bottom:8px">
                        <span>Цена на все размеры:</span>
                        <input type="text" inputmode="decimal" name="p_all" class="price-in" placeholder="например 12990" style="width:130px">
                        <button type="submit" class="btn btn-sm">Применить</button>
                        <span class="muted">или впишите цену у нужного размера — сохранится сразу</span>
                    </div>
                @else
                    <div class="muted" style="margin-bottom:6px">Цену меняет руководитель.</div>
                @endif
                <table class="pick">
                    <tr class="muted"><th>Модель</th><th>Размер</th><th>Остаток</th><th>Цена, ₽</th><th>Штук</th></tr>
                    @foreach($items as $item)
                        @php
                            $priceValue = $item->sale_price !== null ? rtrim(rtrim(number_format((float) $item->sale_price, 2, '.', ''), '0'), '.') : '';
                        @endphp
                        <tr>
                            <td>{{ trim($item->brand.' '.$item->model) }}</td>
                            <td><b>{{ $item->size }}</b></td>
                            <td>{{ (int) $item->quantity }}</td>
                            <td>
                                @if($isHead)
                                    <input type="text" inputmode="decimal" name="p[{{ $item->id }}]" value="{{ $priceValue }}" class="price-in {{ $priceValue === '' ? 'empty' : '' }}" placeholder="нет цены" style="width:100px">
                                @else
                                    {{ $item->sale_price !== null ? $money($item->sale_price).' ₽' : '—' }}
                                @endif
                            </td>
                            <td><input type="number" min="0" max="99" name="c[{{ $item->id }}]" value="{{ $copies[$item->id] }}" data-stock="{{ max(0, (int) $item->quantity) }}" class="copies"></td>
                        </tr>
                    @endforeach
                </table>
            </div>
        @endif

        @if($isMark)
            <div class="card">
                <input type="hidden" name="marks_sent" value="1">
                @if($marksCount > 0)
                    <div class="row" style="justify-content:space-between;margin-bottom:6px">
                        <b>Коды на складе по выбранным моделям · {{ $marksCount }}</b>
                        <span class="row">
                            <button type="button" class="btn btn-sm" data-marks="all">все</button>
                            <button type="button" class="btn btn-sm" data-marks="none">ни одного</button>
                        </span>
                    </div>
                    <table class="pick">
                        @foreach($markRows as $row)
                            <tr>
                                <td style="width:28px"><input type="checkbox" name="marks[]" value="{{ $row['id'] }}" class="mark-check" @checked($row['checked'])></td>
                                <td>{{ $row['item'] }}</td>
                                <td class="mono">{{ $row['hr'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                @elseif($productsCount > 0)
                    <div class="muted" style="margin-bottom:8px">У выбранных моделей нет кодов на складе. Коды добавляются при приёмке (режим «Код маркировки») или вставляются ниже.</div>
                @endif

                <div style="margin-top:10px">
                    <b>Вставить коды</b> <span class="muted">— по одному в строке: из файла «Честного знака» (CSV/TXT), от поставщика или сканером</span>
                    <textarea name="codes" rows="4" style="width:100%;margin-top:6px" class="mono" placeholder="010460123456789021…">{{ $pastedCodes }}</textarea>
                    @if($pastedInvalid > 0)
                        <div class="bad">Пропущено строк, не похожих на код маркировки: {{ $pastedInvalid }}.</div>
                    @endif
                    @if($noCrypto > 0)
                        <div class="warn" style="margin-top:6px">⚠ У {{ $noCrypto }} код(ов) нет криптохвоста (91/92) — это не полный код маркировки, касса его не примет. Возьмите полный код из «Честного знака» или от поставщика.</div>
                    @endif
                </div>
            </div>
        @endif

        <div class="card">
            <details class="guide">
                <summary>Как настроить Xprinter XP-365B (один раз)</summary>
                <ol>
                    <li><b>Драйвер.</b>
                        @if($driverMb)
                            <a class="btn btn-sm btn-primary" href="{{ route('print.driver') }}">⬇ Скачать драйвер XP-365B ({{ $driverMb }} МБ)</a>
                            — распакуйте архив и запустите <span class="mono">Xprinter_2022.1_M-3.exe</span> (официальный установщик Seagull для Xprinter).
                        @else
                            С диска из коробки или с сайта xprinters.ru → «Драйвер для XP-237B, XP-365B, XP-370B».
                        @endif
                        Принтер включён и подключён кабелем → в мастере <b>USB</b> → модель <b>XP-365B</b> → «Далее» → «Готово».</li>
                    <li><b>Режим «этикетки».</b> XP-365B умеет печатать и этикетки, и чеки; режим задаётся DIP-переключателями на задней панели. Если принтер тянет ленту «насквозь» и не останавливается на промежутке между наклейками — он в режиме чеков.</li>
                    <li><b>Калибровка после заправки рулона</b> (и при смене размера наклеек): выключите принтер → зажмите <b>FEED</b> и <b>PAUSE</b> → включите → когда загорится Online, погаснет Error и прозвучит двойной сигнал — отпустите.</li>
                    <li><b>Размер бумаги в Windows.</b> Параметры → Принтеры и сканеры → XP-365B → Настройки печати → Page Setup → <i>New</i>: ширина и высота как у рулона (в магазине — <b>60 × 40 мм</b>), тип — <i>Labels with gaps</i> (этикетки с промежутками). Сделайте его размером по умолчанию.</li>
                    <li><b>Чёткость.</b> В тех же настройках (Options): темнота (Darkness) повыше, скорость пониже — DataMatrix «Честного знака» читается лучше. Если код бледный или «рваный» — +2 к темноте.</li>
                    <li><b>При печати из браузера:</b> принтер XP-365B, размер бумаги — тот же (USER {{ $w }} × {{ $h }}), <b>Макет: Книжная</b>, <b>Поля: нет</b>, <b>Масштаб: 100 %</b> (не «по размеру страницы»), колонтитулы выключены. Браузер запомнит — дальше просто «Печать».</li>
                    <li><b>Если ценник повёрнут или залез на две наклейки</b> — в драйвере не тот размер бумаги: Настройки печати → Page Setup → New → <b>Width 60</b> (поперёк ленты), <b>Height 40</b> (вдоль), Labels with gaps, ориентация Portrait (книжная); в окне печати браузера — этот размер и <b>Макет: Книжная</b>. Если всё равно боком — попробуйте «Альбомная».</li>
                    <li><b>Если внизу напечатался адрес сайта</b> — это колонтитулы браузера: в окне печати «Дополнительные настройки» → снимите «Верхние и нижние колонтитулы», поля «Нет».</li>
                    <li><b>Если печать висит в очереди</b> — принтер привязан не к тому порту: свойства принтера → «Порты» → выберите USB-порт «Порт виртуального принтера для USB» (не COM и не LPT).</li>
                    <li><b>Проверка.</b> Напечатайте 1 этикетку «Честный знак» и отсканируйте её сканером на кассе/в приёмке: код должен считаться целиком.</li>
                </ol>
            </details>
        </div>
    </form>
</div>

<div class="sheet" id="sheet">
    @foreach($labels as $l)
        @if($isMark)
            <div class="lbl mark">
                <div class="dm" data-code="{{ $l['dm'] }}"></div>
                <div class="mark-text">
                    @if($l['name'] !== '')
                        <div class="name">{{ $l['name'] }}</div>
                    @endif
                    @if($l['size'] !== '')
                        <div class="size">Размер <b>{{ $l['size'] }}</b></div>
                    @endif
                    <div class="hr">(01) {{ $l['gtin'] }}<br>(21) {{ $l['serial'] }}</div>
                </div>
            </div>
        @elseif($type === 'price')
            <div class="lbl">
                @if($l['brand'] !== '')
                    <div class="brand">{{ $l['brand'] }}</div>
                @endif
                <div class="name">{{ $l['name'] }}</div>
                <div class="size">Размер <b>{{ $l['size'] !== '' ? $l['size'] : '—' }}</b></div>
                @if($showPrice && $l['price'] !== null)
                    <div class="price">{{ $money($l['price']) }} ₽</div>
                @endif
                @if($l['barcode'])
                    <div class="code" style="margin-top: {{ $showPrice && $l['price'] !== null ? '0.8mm' : 'auto' }}">
                        <div style="width: {{ $l['barcode_mm'] }}mm; height: {{ $compact ? 4 : 5.5 }}mm; margin: 0 auto">{!! $l['barcode'] !!}</div>
                        <div class="art">{{ $l['article'] }}</div>
                    </div>
                @endif
            </div>
        @else
            <div class="lbl">
                <div class="name">{{ $l['name'] }}</div>
                <div class="size">Размер <b>{{ $l['size'] !== '' ? $l['size'] : '—' }}</b></div>
                @if($l['barcode'])
                    <div class="code">
                        <div style="width: {{ $l['barcode_mm'] }}mm; height: {{ $compact ? 7 : 12 }}mm; margin: 0 auto">{!! $l['barcode'] !!}</div>
                        <div class="art">{{ $l['article'] }}</div>
                    </div>
                @endif
            </div>
        @endif
    @endforeach
</div>

@if($isMark && $labelCount > 0)
    <script src="https://cdn.jsdelivr.net/npm/bwip-js@4.11.4/dist/bwip-js-min.js"></script>
@endif
<script>
(() => {
    const printBtn = document.getElementById('printBtn');
    const boxMm = {{ $dmBox }};

    // DataMatrix: модуль кратен точкам 203 dpi (4 точки = 0,5 мм, если не влезает — 3 точки = 0,375 мм).
    const renderMarks = () => {
        const boxes = document.querySelectorAll('.dm[data-code]');
        if (! boxes.length) return true;
        if (typeof bwipjs === 'undefined') {
            boxes.forEach(b => { b.innerHTML = '<span class="bad" style="font-size:7pt">нет связи с генератором кода</span>'; });
            return false;
        }
        let ok = true;
        boxes.forEach(box => {
            try {
                // scale: 1 → в viewBox ровно 2 единицы на модуль (по умолчанию масштаб 2 — модули посчитались бы вдвое больше).
                const svg = bwipjs.toSVG({ bcid: 'datamatrix', text: box.dataset.code, parsefnc: true, scale: 1 });
                const vb = /viewBox="0 0 (\d+(?:\.\d+)?)/.exec(svg);
                const modules = vb ? parseFloat(vb[1]) / 2 : 36;
                const quiet = 2; // тихая зона по 1 модулю с каждой стороны
                let mm = [0.5, 0.375].find(m => (modules + quiet) * m <= boxMm) || (boxMm / (modules + quiet));
                box.innerHTML = svg;
                const el = box.querySelector('svg');
                el.setAttribute('width', (modules * mm).toFixed(3) + 'mm');
                el.setAttribute('height', (modules * mm).toFixed(3) + 'mm');
            } catch (e) {
                ok = false;
                box.innerHTML = '<span class="bad" style="font-size:7pt">код не распознан</span>';
            }
        });
        return ok;
    };

    const ready = renderMarks();
    if (printBtn) {
        printBtn.addEventListener('click', () => {
            if (! ready && ! confirm('Часть кодов не удалось построить. Всё равно печатать?')) return;
            window.print();
        });
    }

    document.querySelectorAll('[data-fill]').forEach(btn => btn.addEventListener('click', () => {
        const mode = btn.dataset.fill;
        document.querySelectorAll('input.copies').forEach(i => { i.value = mode === 'stock' ? i.dataset.stock : mode; });
        document.getElementById('printForm').submit();
    }));
    document.querySelectorAll('[data-marks]').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('.mark-check').forEach(c => { c.checked = btn.dataset.marks === 'all'; });
        document.getElementById('printForm').submit();
    }));
    document.querySelectorAll('input.copies, .mark-check, input.price-in[name^="p["]').forEach(el => el.addEventListener('change', () => document.getElementById('printForm').submit()));
})();
</script>
</body>
</html>
