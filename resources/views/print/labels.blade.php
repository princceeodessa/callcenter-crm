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
        // Данные для PDF (рисуется в браузере на canvas 8 точек/мм = 203 dpi, как у принтера).
        $pdfLabels = $labels->map(fn ($l) => $isMark
            ? ['name' => $l['name'], 'size' => $l['size'], 'dm' => $l['dm'], 'gtin' => $l['gtin'], 'serial' => $l['serial'], 'desc' => $l['desc']]
            : [
                'name' => $l['name'], 'brand' => $l['brand'], 'size' => $l['size'], 'article' => $l['article'],
                'price' => ($type === 'price' && $showPrice && $l['price'] !== null) ? $money($l['price']).' ₽' : null,
            ])->values();
        $pdfJson = json_encode([
            'type' => $type, 'w' => $w, 'h' => $h, 'compact' => $compact, 'labels' => $pdfLabels,
        ], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
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
        .photo-box { border: 1px dashed #93c5fd; background: #f8fbff; border-radius: 10px; padding: 10px 12px; margin-top: 10px; }
        .raw-set summary { cursor: pointer; }
        .raw-set label { display: inline-flex; align-items: center; gap: 4px; }
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
                <button type="button" class="btn btn-primary" id="rawBtn" @disabled($labelCount === 0) title="Команды прямо в принтер (TSPL) через программу QZ Tray — без драйвера и без настроек бумаги в браузере">⚡ Печать напрямую</button>
                <button type="button" class="btn btn-primary" id="pdfBtn" @disabled($labelCount === 0) title="Надёжный способ для XP-365B: PDF ровно {{ $w }}×{{ $h }} мм, печать из Adobe Reader или SumatraPDF">📄 PDF для печати</button>
                <button type="submit" class="btn">↻ Обновить</button>
                <span class="muted">Рулон {{ $format['label'] }} · принтер XP-365B</span>
                <details class="raw-set" id="rawSet">
                    <summary class="muted">⚙ прямая печать</summary>
                    <div class="row" style="margin-top:6px">
                        <label>Принтер <select id="rawPrinter"><option value="">— найти автоматически —</option></select></label>
                        <label>Сдвиг → <input type="number" id="rawX" step="0.5" min="0" max="20" style="width:64px"> мм</label>
                        <label>Сдвиг ↓ <input type="number" id="rawY" step="0.5" min="-10" max="10" style="width:64px"> мм</label>
                        <label>Промежуток <input type="number" id="rawGap" step="0.5" min="0" max="10" style="width:64px"> мм</label>
                        <label><input type="checkbox" id="rawFlip"> перевернуть 180°</label>
                        <label>Темнота <input type="number" id="rawDensity" min="1" max="15" style="width:56px"></label>
                    </div>
                    <div class="muted" style="margin-top:4px">Нужна бесплатная программа <b>QZ Tray</b> на этом компьютере (<a href="https://qz.io/download/" target="_blank" rel="noopener">qz.io/download</a>) — она передаёт команды прямо в принтер. Настройки запоминаются на этом компьютере.</div>
                </details>
                <label class="muted" title="Если из PDF ценник выходит боком — поверните страницу. Выбор запоминается на этом компьютере.">Поворот PDF
                    <select id="pdfRotate">
                        <option value="0">нет</option>
                        <option value="cw">90° ↻</option>
                        <option value="ccw">90° ↺</option>
                    </select>
                </label>
                <span class="muted" id="pdfStatus"></span>
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

                <div class="photo-box">
                    <div class="row">
                        <label class="btn btn-primary" style="margin:0">
                            📷 Код с фото
                            <input type="file" id="photoInput" accept="image/*" capture="environment" multiple hidden>
                        </label>
                        <label>Копий каждого кода <input type="number" name="mark_copies" id="markCopies" min="1" max="50" value="{{ $markCopies }}" style="width:64px"></label>
                        <span class="muted">Сфотографируйте DataMatrix (можно несколько фото) — код распознается и появится этикетка. Укажите копии и нажмите «⚡ Печать напрямую».</span>
                    </div>
                    <div class="muted" id="photoStatus" style="margin-top:6px"></div>
                    @if($itemsCount > 0)
                        <div class="row" style="margin-top:8px">
                            <label>Это код пары: <select name="attach_item" onchange="this.form.submit()">
                                <option value="">— не указывать —</option>
                                @foreach($items as $item)
                                    <option value="{{ $item->id }}" @selected($attachItemId === $item->id)>{{ trim($item->brand.' '.$item->model) }} · р. {{ $item->size }}</option>
                                @endforeach
                            </select></label>
                            <label><input type="checkbox" name="attach_save" value="1" checked> сохранить код к этому размеру</label>
                        </div>
                    @endif
                    @if($marksSaved > 0)
                        <div class="saved" style="margin-top:8px">💾 Сохранено кодов к размеру: {{ $marksSaved }}.</div>
                    @endif
                    <div style="margin-top:8px">
                        <label style="display:block">Текст на этикетке <span class="muted">— слева от кода. Пусто: «Кроссовки &lt;модель&gt;, арт., размер» из карточки</span></label>
                        <textarea name="label_text" rows="2" style="width:100%" placeholder="впишите свой текст или оставьте пустым">{{ $labelText }}</textarea>
                        <button type="submit" class="btn btn-sm" style="margin-top:4px">Применить текст</button>
                    </div>
                </div>

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
                    <li><b>Лучший способ — «⚡ Печать напрямую».</b> CRM отправляет принтеру его собственные команды (TSPL): размер наклейки, промежуток и картинку ровно под 203 dpi. Драйвер, размер бумаги и повороты браузера не участвуют. Один раз установите бесплатную программу <b>QZ Tray</b> (<a href="https://qz.io/download/" target="_blank" rel="noopener">qz.io/download</a>) и запустите её; при первой печати она спросит разрешение — нажмите <b>Allow</b>. Если ценник сдвинут или перевёрнут — «⚙ прямая печать»: сдвиг в мм и «перевернуть 180°».</li>
                    <li><b>Запасной способ — «📄 PDF для печати».</b> Скачается файл, где каждая страница ровно {{ $w }} × {{ $h }} мм. Откройте его в <b>Adobe Acrobat Reader</b> или <b>SumatraPDF</b> (не в браузере) → Печать → Xprinter XP-365B → размер <b>«Фактический» / 100 %</b>, ориентация <b>«Книжная»</b> (не «Авто»). Если всё равно выходит боком — выберите «Поворот PDF: 90°» рядом с кнопкой и скачайте PDF заново. Так печатают этикетки Wildberries на этом же принтере.</li>
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
<script>
(() => {
    const btn = document.getElementById('pdfBtn');
    const status = document.getElementById('pdfStatus');
    if (! btn) return;
    const DATA = {!! $pdfJson !!};
    const PX = 8;                              // 8 точек на мм = 203 dpi, как у XP-365B
    const mm = (v) => Math.round(v * PX);
    const pt = (v) => v * 0.3528 * PX;         // пункт -> точки
    const BWIP = 'https://cdn.jsdelivr.net/npm/bwip-js@4.11.4/dist/bwip-js-min.js';
    const JSPDF = 'https://cdn.jsdelivr.net/npm/jspdf@2.5.2/dist/jspdf.umd.min.js';

    const load = (src, ready) => new Promise((resolve, reject) => {
        if (ready()) return resolve();
        const s = document.createElement('script');
        s.src = src;
        s.onload = resolve;
        s.onerror = () => reject(new Error('не загрузилась библиотека ' + src));
        document.head.appendChild(s);
    });

    const wrap = (ctx, text, maxW, maxLines) => {
        const words = String(text || '').split(/\s+/).filter(Boolean);
        const lines = [];
        let cur = '';
        for (const word of words) {
            const t = cur ? cur + ' ' + word : word;
            if (! cur || ctx.measureText(t).width <= maxW) { cur = t; } else { lines.push(cur); cur = word; }
        }
        if (cur) lines.push(cur);
        if (maxLines > 0 && lines.length > maxLines) {
            lines.length = maxLines;
            let last = lines[maxLines - 1];
            while (last.length > 1 && ctx.measureText(last + '…').width > maxW) last = last.slice(0, -1);
            lines[maxLines - 1] = last + '…';
        }
        return lines;
    };

    // перенос по символам — для серийного номера без пробелов
    const chunk = (ctx, text, maxW) => {
        const out = [];
        let cur = '';
        for (const ch of String(text || '')) {
            if (cur && ctx.measureText(cur + ch).width > maxW) { out.push(cur); cur = ch; } else { cur += ch; }
        }
        if (cur) out.push(cur);
        return out;
    };

    const barcode = (text) => {
        const c = document.createElement('canvas');
        // 2 точки на штрих = 0,25 мм: ровные штрихи, без растягивания
        bwipjs.toCanvas(c, { bcid: 'code128', text: text, scale: 2, height: 8, includetext: false });
        return c;
    };

    const drawGoods = (ctx, l, W, H) => {
        const k = DATA.compact ? 0.82 : 1;
        const padX = mm(2), padT = mm(1.5), padB = mm(1.2), maxW = W - 2 * padX;
        ctx.fillStyle = '#000';
        ctx.textBaseline = 'top';

        // Низ: артикул, штрихкод, цена — снизу вверх.
        let bottom = H - padB;
        if (l.article) {
            const artSize = pt(7 * k);
            ctx.font = artSize + 'px Consolas, "Courier New", monospace';
            bottom -= artSize;
            const aw = ctx.measureText(l.article).width;
            ctx.fillText(l.article, (W - aw) / 2, bottom);
            bottom -= mm(0.4);
            const bc = barcode(l.article);
            const bh = mm(DATA.type === 'price' ? (DATA.compact ? 4 : 5.5) : (DATA.compact ? 7 : 12));
            const bw = Math.min(bc.width, maxW);
            bottom -= bh;
            ctx.drawImage(bc, Math.round((W - bw) / 2), Math.round(bottom), bw, bh);
        }
        if (l.price) {
            const ps = pt(19 * k);
            ctx.font = 'bold ' + ps + 'px Arial, sans-serif';
            bottom -= ps + mm(0.8);
            ctx.fillText(l.price, padX, bottom);
        }

        // Верх: бренд, название (сколько строк влезет), размер.
        let y = padT;
        if (l.brand && DATA.type === 'price') {
            const bs = pt(6.5 * k);
            ctx.font = bs + 'px Arial, sans-serif';
            ctx.fillText(String(l.brand).toUpperCase(), padX, y);
            y += bs * 1.25;
        }
        const ns = pt(8.5 * k), lh = ns * 1.15, sizeH = pt(11 * k) * 1.2 + mm(0.6);
        const room = Math.max(1, Math.floor((bottom - mm(0.6) - y - sizeH) / lh));
        ctx.font = 'bold ' + ns + 'px Arial, sans-serif';
        for (const line of wrap(ctx, l.name, maxW, Math.min(room, DATA.compact ? 2 : 3))) {
            ctx.fillText(line, padX, y);
            y += lh;
        }
        y += mm(0.6);
        ctx.font = ns + 'px Arial, sans-serif';
        ctx.fillText('Размер ', padX, y + pt(1.5 * k));
        const sw = ctx.measureText('Размер ').width;
        ctx.font = 'bold ' + pt(11 * k) + 'px Arial, sans-serif';
        ctx.fillText(l.size || '—', padX + sw, y);
    };

    // ---------- Этикетка «Честный знак» 1 в 1 с заводской ----------
    // Размеры сняты с фото оригинальной этикетки 60×40 (в мм «оригинала»). Вся картинка ужата до 90 %
    // и отцентрирована — поля ≥ 2 мм, чтобы сдвиг принтера ничего не срезал.
    const CZ = {
        icon: { x: 3.7, y: 3.2, s: 5.3, t: 0.85, arm: 2.1 },
        top: { x: 9.7, y: 2.7, w: 16.6, h: 1.95 },      // «ЧЕСТНЫЙ»
        big: { x: 9.7, y: 5.2, w: 17.2, h: 3.6 },       // «ЗНАК»
        desc: { x: 3.7, y: 10.2, w: 23.2, pitch: 2.8, cap: 2.05, bottom: 36.5 },
        div: { x: 29.3, y1: 1.6, y2: 26.8, w: 0.35 },
        dm: { x: 30.4, y: 1.3, size: 25.5 },
        box: { x1: 29.3, x2: 58.0, y: 30.6, lineW: 0.25, padX: 0.6, padY: 0.5, pitch: 2.6, cap: 2.0 },
    };

    // Шрифт такого размера, чтобы строка заняла ровно w мм и не была выше h мм (буквы заглавные).
    const fitFont = (ctx, text, weight, wPx, hPx) => {
        ctx.font = weight + ' 100px Arial, sans-serif';
        const byW = 100 * wPx / Math.max(1, ctx.measureText(text).width);
        const byH = hPx / 0.72;                           // высота заглавной ≈ 0,72 кегля у Arial
        const px = Math.min(byW, byH);
        ctx.font = weight + ' ' + px + 'px Arial, sans-serif';
        return px;
    };

    const drawCzIcon = (ctx, X, Y, S, sc) => {
        const s = S * sc, th = CZ.icon.t * sc, arm = CZ.icon.arm * sc, h = th / 2;
        ctx.lineWidth = th; ctx.lineCap = 'butt'; ctx.lineJoin = 'round';
        const corner = (x0, y0, dx, dy) => {
            ctx.beginPath();
            ctx.moveTo(x0 + h * dx, y0 + dy * arm);
            ctx.lineTo(x0 + h * dx, y0 + h * dy);
            ctx.lineTo(x0 + dx * arm, y0 + h * dy);
            ctx.stroke();
        };
        corner(X, Y, 1, 1); corner(X + s, Y, -1, 1); corner(X, Y + s, 1, -1); corner(X + s, Y + s, -1, -1);
        ctx.lineJoin = 'miter';
        ctx.beginPath();
        ctx.moveTo(X + s * 0.25, Y + s * 0.50); ctx.lineTo(X + s * 0.44, Y + s * 0.69); ctx.lineTo(X + s * 0.79, Y + s * 0.31);
        ctx.stroke();
    };

    const drawMark = (ctx, l, W, H) => {
        const sc = Math.min(W / 60, H / 40) * 0.9;       // точек на мм «оригинала»
        const ox = (W - 60 * sc) / 2, oy = (H - 40 * sc) / 2;
        const X = (v) => ox + v * sc, Y = (v) => oy + v * sc;
        ctx.fillStyle = '#000'; ctx.strokeStyle = '#000'; ctx.textBaseline = 'alphabetic';

        // Логотип
        drawCzIcon(ctx, X(CZ.icon.x), Y(CZ.icon.y), CZ.icon.s, sc);
        fitFont(ctx, 'ЧЕСТНЫЙ', 'bold', CZ.top.w * sc, CZ.top.h * sc);
        ctx.fillText('ЧЕСТНЫЙ', X(CZ.top.x), Y(CZ.top.y + CZ.top.h));
        fitFont(ctx, 'ЗНАК', 'bold', CZ.big.w * sc, CZ.big.h * sc);
        ctx.fillText('ЗНАК', X(CZ.big.x), Y(CZ.big.y + CZ.big.h));

        // Описание — обычным шрифтом, как на оригинале
        const dPx = CZ.desc.cap * sc / 0.72;
        ctx.font = dPx + 'px Arial, sans-serif';
        const text = l.desc || [l.name, l.size ? 'размер ' + l.size : ''].filter(Boolean).join(', ');
        const maxLines = Math.max(1, Math.floor((CZ.desc.bottom - CZ.desc.y) / CZ.desc.pitch));
        wrap(ctx, text, CZ.desc.w * sc, maxLines).forEach((line, i) => {
            ctx.fillText(line, X(CZ.desc.x), Y(CZ.desc.y + CZ.desc.cap + i * CZ.desc.pitch));
        });

        // Разделитель
        ctx.fillRect(Math.round(X(CZ.div.x)), Math.round(Y(CZ.div.y1)), Math.max(2, Math.round(CZ.div.w * sc)), Math.round((CZ.div.y2 - CZ.div.y1) * sc));

        // DataMatrix: целое число точек на модуль (bwip рисует 2 px на модуль при scale 1,
        // растягиваем «ближайшим соседом» — модули остаются ровными).
        const dm1 = document.createElement('canvas');
        bwipjs.toCanvas(dm1, { bcid: 'datamatrix', text: l.dm, parsefnc: true, scale: 1 });
        const modules = dm1.width / 2;
        const dpm = Math.max(2, Math.floor(CZ.dm.size * sc / modules));
        const dmPx = modules * dpm;
        ctx.imageSmoothingEnabled = false;
        ctx.drawImage(dm1, Math.round(X(CZ.dm.x)), Math.round(Y(CZ.dm.y)), dmPx, dmPx);

        // Рамка с (01)…(21)… — от разделителя до правого края, текст в 2 строки
        const bx = X(CZ.box.x1), bw = (CZ.box.x2 - CZ.box.x1) * sc, by = Math.max(Y(CZ.box.y), Y(CZ.dm.y) + dmPx + 1.5 * sc);
        const hri = '(01)' + l.gtin + '(21)' + l.serial;
        const innerW = bw - 2 * CZ.box.padX * sc;
        const perLine = Math.ceil(hri.length / 2);
        fitFont(ctx, hri.slice(0, perLine), 'normal', innerW, CZ.box.cap * sc);
        const lines = chunk(ctx, hri, innerW);
        const bh = CZ.box.padY * 2 * sc + lines.length * CZ.box.pitch * sc;
        ctx.lineWidth = Math.max(1, CZ.box.lineW * sc);
        ctx.strokeRect(Math.round(bx) + 0.5, Math.round(by) + 0.5, Math.round(bw), Math.round(bh));
        lines.forEach((ln, i) => ctx.fillText(ln, bx + CZ.box.padX * sc, by + CZ.box.padY * sc + CZ.box.cap * sc + i * CZ.box.pitch * sc + 0.3 * sc));
        ctx.textBaseline = 'top';
    };

    // Одна этикетка -> canvas ровно W x H точек (выставлено для проверки в тестах/консоли).
    const render = (l) => {
        const W = mm(DATA.w), H = mm(DATA.h);
        const c = document.createElement('canvas');
        c.width = W;
        c.height = H;
        const ctx = c.getContext('2d');
        ctx.imageSmoothingEnabled = false;
        ctx.fillStyle = '#fff';
        ctx.fillRect(0, 0, W, H);
        if (DATA.type === 'mark') drawMark(ctx, l, W, H); else drawGoods(ctx, l, W, H);
        return c;
    };

    // Поворот страницы PDF: драйвер Seagull на альбомной ориентации поворачивает наклейку,
    // а программы просмотра в режиме «Авто» выбирают её для «лежачей» страницы 60×40.
    // Повёрнутая страница (40×60, «стоячая») печатается в книжной — выбор хранится на компьютере.
    const rotateSel = document.getElementById('pdfRotate');
    try { const saved = localStorage.getItem('labelPdfRotate'); if (saved && rotateSel) rotateSel.value = saved; } catch (e) {}
    if (rotateSel) rotateSel.addEventListener('change', () => { try { localStorage.setItem('labelPdfRotate', rotateSel.value); } catch (e) {} });

    const rotated = (src, dir) => {
        const r = document.createElement('canvas');
        r.width = src.height;
        r.height = src.width;
        const ctx = r.getContext('2d');
        ctx.imageSmoothingEnabled = false;
        if (dir === 'cw') { ctx.translate(r.width, 0); ctx.rotate(Math.PI / 2); }
        else { ctx.translate(0, r.height); ctx.rotate(-Math.PI / 2); }
        ctx.drawImage(src, 0, 0);
        return r;
    };

    // Копии этикеток ЧЗ: берём из поля прямо в момент печати (без перезагрузки страницы).
    const copies = () => {
        const el = document.getElementById('markCopies');
        const n = el ? parseInt(el.value, 10) : 1;
        return DATA.type === 'mark' && n > 1 ? Math.min(50, n) : 1;
    };

    const buildPdf = async (dirArg) => {
        await load(BWIP, () => typeof window.bwipjs !== 'undefined');
        await load(JSPDF, () => typeof window.jspdf !== 'undefined');
        const dir = dirArg !== undefined ? dirArg : (rotateSel ? rotateSel.value : '0');
        const turn = dir === 'cw' || dir === 'ccw';
        const pw = turn ? DATA.h : DATA.w, ph = turn ? DATA.w : DATA.h;
        const orientation = pw > ph ? 'landscape' : 'portrait';
        const doc = new window.jspdf.jsPDF({ orientation: orientation, unit: 'mm', format: [pw, ph], compress: true });
        let page = 0;
        DATA.labels.forEach((l) => {
            const c = turn ? rotated(render(l), dir) : render(l);
            const png = c.toDataURL('image/png');
            for (let n = 0; n < copies(); n++) {
                if (page++ > 0) doc.addPage([pw, ph], orientation);
                doc.addImage(png, 'PNG', 0, 0, pw, ph, undefined, 'FAST');
            }
        });
        return doc;
    };
    window.__labelPdf = { render: render, buildPdf: buildPdf, data: DATA };

    // ---------- Прямая печать TSPL через QZ Tray (мимо драйвера Windows) ----------
    const QZ = 'https://cdn.jsdelivr.net/npm/qz-tray@2.2.6/qz-tray.js';
    const rawBtn = document.getElementById('rawBtn');
    const RAW_KEY = 'labelRawSettings';
    const rawEls = {
        printer: document.getElementById('rawPrinter'), x: document.getElementById('rawX'), y: document.getElementById('rawY'),
        gap: document.getElementById('rawGap'), flip: document.getElementById('rawFlip'), density: document.getElementById('rawDensity'),
    };
    const rawDefaults = { printer: '', x: 0, y: 0, gap: 2, flip: false, density: 10 };
    let rawCfg = Object.assign({}, rawDefaults);
    try { Object.assign(rawCfg, JSON.parse(localStorage.getItem(RAW_KEY) || '{}')); } catch (e) {}
    const showRaw = () => {
        if (! rawEls.x) return;
        rawEls.x.value = rawCfg.x; rawEls.y.value = rawCfg.y; rawEls.gap.value = rawCfg.gap;
        rawEls.flip.checked = !! rawCfg.flip; rawEls.density.value = rawCfg.density;
        if (rawCfg.printer && ! [...rawEls.printer.options].some((o) => o.value === rawCfg.printer)) {
            rawEls.printer.add(new Option(rawCfg.printer, rawCfg.printer));
        }
        rawEls.printer.value = rawCfg.printer || '';
    };
    const readRaw = () => {
        const num = (el, d, lo, hi) => { const v = parseFloat(String(el.value).replace(',', '.')); return isNaN(v) ? d : Math.min(hi, Math.max(lo, v)); };
        rawCfg = {
            printer: rawEls.printer.value, x: num(rawEls.x, 0, 0, 20), y: num(rawEls.y, 0, -10, 10),
            gap: num(rawEls.gap, 2, 0, 10), flip: rawEls.flip.checked, density: Math.round(num(rawEls.density, 10, 1, 15)),
        };
        try { localStorage.setItem(RAW_KEY, JSON.stringify(rawCfg)); } catch (e) {}
    };
    showRaw();
    Object.values(rawEls).forEach((el) => el && el.addEventListener('change', readRaw));

    // canvas -> TSPL BITMAP: 1 бит на точку, 0 = печатать (чёрный), 1 = пусто
    const tsplBitmap = (c) => {
        const W = c.width, H = c.height, wb = Math.ceil(W / 8);
        const px = c.getContext('2d').getImageData(0, 0, W, H).data;
        const out = new Uint8Array(wb * H).fill(0xFF);
        for (let y = 0; y < H; y++) {
            for (let x = 0; x < W; x++) {
                const i = (y * W + x) * 4;
                const dark = (px[i] * 0.299 + px[i + 1] * 0.587 + px[i + 2] * 0.114) < 128 && px[i + 3] > 0;
                if (dark) out[y * wb + (x >> 3)] &= ~(0x80 >> (x & 7));
            }
        }
        return { wb: wb, h: H, bytes: out };
    };

    const buildTspl = () => {
        const enc = new TextEncoder();
        const parts = [];
        const cmd = (s) => parts.push(enc.encode(s + '\r\n'));
        cmd('SIZE ' + DATA.w + ' mm,' + DATA.h + ' mm');
        cmd('GAP ' + rawCfg.gap + ' mm,0 mm');
        cmd('DIRECTION ' + (rawCfg.flip ? 0 : 1) + ',0');
        cmd('REFERENCE ' + Math.round(rawCfg.x * PX) + ',0');
        cmd('SHIFT ' + Math.round(rawCfg.y * PX));
        cmd('DENSITY ' + rawCfg.density);
        cmd('SPEED 3');
        cmd('SET TEAR ON');
        DATA.labels.forEach((l) => {
            const bm = tsplBitmap(render(l));
            cmd('CLS');
            parts.push(enc.encode('BITMAP 0,0,' + bm.wb + ',' + bm.h + ',0,'));
            parts.push(bm.bytes);
            parts.push(enc.encode('\r\n'));
            cmd('PRINT 1,' + copies());
        });
        let len = 0; parts.forEach((p) => { len += p.length; });
        const all = new Uint8Array(len);
        let off = 0; parts.forEach((p) => { all.set(p, off); off += p.length; });
        let bin = '';
        for (let i = 0; i < all.length; i += 0x8000) bin += String.fromCharCode.apply(null, all.subarray(i, i + 0x8000));
        return btoa(bin);
    };
    window.__labelRaw = { buildTspl: buildTspl, bitmap: tsplBitmap };

    // Без QZ Tray попытка соединения может висеть бесконечно — ограничиваем по времени.
    const withTimeout = (p, ms, msg) => Promise.race([p, new Promise((_, reject) => setTimeout(() => reject(new Error(msg)), ms))]);
    const QZ_DOWN = 'QZ Tray не отвечает. Установите его с qz.io/download, запустите (значок у часов) и нажмите ещё раз.';
    let qzReady = false, qzConnecting = null;
    const ensureQz = async () => {
        if (qzReady && qz.websocket.isActive()) return;
        if (! qzConnecting) {
            qzConnecting = qz.websocket.connect({ retries: 1, delay: 1 })
                .then(() => { qzReady = true; })
                .finally(() => { qzConnecting = null; });
        }
        try { await withTimeout(qzConnecting, 12000, QZ_DOWN); }
        catch (e) { qzReady = false; throw new Error(QZ_DOWN); }
    };

    const pickPrinter = (list) => {
        if (rawCfg.printer && list.includes(rawCfg.printer)) return rawCfg.printer;
        return list.find((n) => /365/.test(n)) || list.find((n) => /xprinter/i.test(n)) || null;
    };

    // Предпросмотр этикеток ЧЗ рисуем тем же кодом, что и печать: на экране — ровно то, что выйдет.
    if (DATA.type === 'mark' && DATA.labels.length) {
        load(BWIP, () => typeof window.bwipjs !== 'undefined').then(() => {
            document.querySelectorAll('.sheet .lbl.mark').forEach((el, i) => {
                const l = DATA.labels[i];
                if (! l) return;
                const img = new Image();
                img.src = render(l).toDataURL('image/png');
                img.style.cssText = 'width:100%;height:100%;display:block';
                el.innerHTML = '';
                el.style.padding = '0';
                el.appendChild(img);
            });
        }).catch(() => {});
    }

    if (rawBtn) rawBtn.addEventListener('click', async () => {
        rawBtn.disabled = true;
        status.textContent = 'Подключаюсь к QZ Tray…';
        try {
            readRaw();
            await load(BWIP, () => typeof window.bwipjs !== 'undefined');
            await load(QZ, () => typeof window.qz !== 'undefined');
            qz.security.setCertificatePromise((resolve) => resolve());
            qz.security.setSignaturePromise(() => (resolve) => resolve());
            await ensureQz();
            const list = await withTimeout(qz.printers.find(), 15000, 'QZ Tray не вернул список принтеров — перезапустите QZ Tray.');
            const names = Array.isArray(list) ? list : [list];
            [...rawEls.printer.options].slice(1).forEach((o) => o.remove());
            names.forEach((n) => rawEls.printer.add(new Option(n, n)));
            const name = pickPrinter(names);
            if (! name) { document.getElementById('rawSet').open = true; throw new Error('Не нашёл принтер XP-365B — выберите его в «⚙ прямая печать».'); }
            rawEls.printer.value = name; readRaw();
            status.textContent = 'Печатаю на ' + name + '…';
            await withTimeout(qz.print(qz.configs.create(name), [{ type: 'raw', format: 'command', flavor: 'base64', data: buildTspl() }]), 30000, 'Принтер не ответил за 30 секунд — проверьте, что он включён.');
            status.textContent = 'Отправлено на ' + name + ': ' + (DATA.labels.length * copies()) + ' шт.';
        } catch (e) {
            status.textContent = (e && e.message) ? e.message : String(e);
        } finally {
            rawBtn.disabled = false;
        }
    });

    btn.addEventListener('click', async () => {
        btn.disabled = true;
        status.textContent = 'Готовлю PDF…';
        try {
            const doc = await buildPdf();
            doc.save('etiketki-' + DATA.w + 'x' + DATA.h + '.pdf');
            status.textContent = 'PDF скачан: ' + DATA.labels.length + ' стр. Откройте в Adobe Reader / SumatraPDF → Печать → «Фактический размер».';
        } catch (e) {
            status.textContent = 'Не получилось собрать PDF: ' + e.message;
        } finally {
            btn.disabled = false;
        }
    });
})();
</script>
<script>
(() => {
    // Распознавание DataMatrix «Честного знака» с фото — прямо в браузере (zxing-wasm, ничего не уходит на сторонние серверы).
    const input = document.getElementById('photoInput');
    if (! input) return;
    const status = document.getElementById('photoStatus');
    const form = document.getElementById('printForm');
    const area = form.querySelector('textarea[name="codes"]');
    const ZX_VER = '3.1.4';
    const ZX = 'https://cdn.jsdelivr.net/npm/zxing-wasm@' + ZX_VER + '/dist/iife/reader/index.js';
    const WASM = 'https://cdn.jsdelivr.net/npm/zxing-wasm@' + ZX_VER + '/dist/reader/zxing_reader.wasm';
    let ready = null;
    const loadZx = () => ready || (ready = new Promise((resolve, reject) => {
        const s = document.createElement('script');
        s.src = ZX;
        s.onload = () => {
            try {
                ZXingWASM.prepareZXingModule({
                    overrides: { locateFile: (path, prefix) => path.endsWith('.wasm') ? WASM : prefix + path },
                    fireImmediately: true,
                });
                resolve();
            } catch (e) { reject(e); }
        };
        s.onerror = () => { ready = null; reject(new Error('не загрузился распознаватель')); };
        document.head.appendChild(s);
    }));

    // Большие фото с телефона уменьшаем до ~2000 px — быстрее и надёжнее.
    const toImageData = (file) => new Promise((resolve, reject) => {
        const url = URL.createObjectURL(file);
        const img = new Image();
        img.onload = () => {
            const k = Math.min(1, 2000 / Math.max(img.naturalWidth, img.naturalHeight));
            const c = document.createElement('canvas');
            c.width = Math.round(img.naturalWidth * k);
            c.height = Math.round(img.naturalHeight * k);
            c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
            URL.revokeObjectURL(url);
            resolve(c.getContext('2d').getImageData(0, 0, c.width, c.height));
        };
        img.onerror = () => { URL.revokeObjectURL(url); reject(new Error('не открылось фото')); };
        img.src = url;
    });

    const decode = async (file) => {
        const opts = { formats: ['DataMatrix'], tryHarder: true, tryRotate: true, tryInvert: true, tryDownscale: true, textMode: 'Plain', maxNumberOfSymbols: 20 };
        let res = await ZXingWASM.readBarcodes(await toImageData(file), opts);
        if (! res.some((r) => r.isValid)) res = await ZXingWASM.readBarcodes(file, opts);   // вторая попытка — оригинал без уменьшения
        return res.filter((r) => r.isValid).map((r) => r.text);
    };

    input.addEventListener('change', async () => {
        const files = [...input.files];
        if (! files.length) return;
        status.textContent = 'Распознаю ' + files.length + ' фото…';
        try {
            await loadZx();
            const found = [];
            let failed = 0;
            for (const f of files) {
                const texts = await decode(f);
                const codes = texts.map((t) => t.replace(/^\]d2/, '').replace(/^\x1D/, '')).filter((t) => /^01\d{14}21/.test(t));
                if (! codes.length) failed++;
                found.push(...codes);
            }
            if (! found.length) {
                status.textContent = 'Код не найден. Сфотографируйте ближе и ровнее, без бликов, чтобы квадрат DataMatrix был целиком в кадре.';
                return;
            }
            // GS показываем как <GS> — CRM превратит обратно в настоящий разделитель.
            const have = new Set(area.value.split(/\r?\n/).map((s) => s.trim()).filter(Boolean));
            found.map((c) => c.split('\x1D').join('<GS>')).forEach((c) => have.add(c));
            area.value = [...have].join('\n');
            status.textContent = 'Распознано кодов: ' + found.length + (failed ? ' (на ' + failed + ' фото код не найден)' : '') + '. Загружаю этикетку…';
            form.submit();
        } catch (e) {
            status.textContent = 'Не получилось распознать: ' + (e && e.message ? e.message : e);
        } finally {
            input.value = '';
        }
    });
    window.__photoDecode = { loadZx: loadZx, decode: decode };
})();
</script>
</body>
</html>
