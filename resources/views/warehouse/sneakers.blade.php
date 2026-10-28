@extends('layouts.sneakers')
{{--
    Склад кроссовочного отдела — каркас «Нордика» (прототип: PAGES.warehouse, снимки 05, 06). Сверху кнопки на разделы склада,
    блоки «Остатки» (гистограмма пар по размерам) и «Склад в деньгах», ниже карточки товаров. Товары приходят одним JSON
    (WarehouseController::index), карточки, поиск, фильтры и итоги рисует скрипт ниже; правки (размер, «+ размер», название,
    управление, фото, посредники, массовые действия, новый товар) — прежние маршруты склада, они отвечают JSON с обновлённым
    товаром, страница не перезагружается. Закупка в данных только у руководителя — продавцу её не видно и в коде страницы.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $n = count($products);
    $m10 = $n % 10; $m100 = $n % 100;
    $word = ($m10 === 1 && $m100 !== 11) ? 'товар' : (($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) ? 'товара' : 'товаров');
    $id = '__ID__';
    $cfg = [
        'products' => $products,
        'seesCost' => $seesCost,
        'isHead' => $isHead,
        // подписи разметки — без значков в начале («👟 Повседневные» → «Повседневные»), как в прототипе
        'opts' => collect(['cat' => $categoryOptions, 'sex' => $genderOptions, 'sea' => $seasonOptions])
            ->map(fn ($o) => collect($o)->map(fn ($l) => preg_replace('/^[^\p{L}]+/u', '', $l))->all())->all(),
        'initial' => $initial,
        'moves' => $movements,
        'url' => [
            'store' => route('warehouse.store'),
            'update' => route('warehouse.update', $id),
            'destroy' => route('warehouse.destroy', $id),
            'consign' => route('warehouse.item.consign', $id),
            'resolve' => route('warehouse.consignment.resolve', $id),
            'product' => route('warehouse.product.update', $id),
            'prices' => route('warehouse.product.prices', $id),
            'photo' => route('warehouse.product.photo.upload', $id),
            'photoDel' => route('warehouse.product.photo.delete', $id),
            'bulk' => route('warehouse.bulk'),
            'labels' => route('print.labels'),
        ],
        'ic' => collect(['check', 'pen', 'gear', 'tag', 'cam', 'x', 'box', 'sell', 'clock', 'chev', 'plus'])->mapWithKeys(fn ($i) => [$i => $ui::icon($i)])->all(),
    ];
@endphp

@section('title', 'Склад')
@section('ns_sub', 'кроссовки · '.$n.' '.$word)

@section('ns_view')
<div class="ns-wchips">
    @if($unlinkedSalesCount > 0)
        <a class="ns-chipf ns-warn" href="{{ route('warehouse.unlinked') }}" title="Продажи, по которым пара не списалась со склада">{!! $ui::icon('warn') !!}Продажи без списания · {{ $unlinkedSalesCount }}</a>
    @elseif($negativeCount > 0)
        <a class="ns-chipf ns-warn" href="{{ route('warehouse.unlinked') }}" title="Остаток меньше нуля — ошибка учёта">{!! $ui::icon('warn') !!}Отрицательный остаток · {{ $negativeCount }}</a>
    @endif
    <a class="ns-chipf" href="{{ route('purchases.inTransit') }}" title="Закуплено, но ещё не принято на склад">{!! $ui::icon('truck') !!}В пути@if($inTransitPairs > 0) · {{ number_format($inTransitPairs, 0, ',', ' ') }}@endif</a>
    <a class="ns-chipf" href="{{ route('warehouse.reorder') }}" title="Подсказки закупок по скорости продаж">{!! $ui::icon('bulb') !!}Что заказать</a>
    @if($seesCost)
        <a class="ns-chipf" href="{{ route('warehouse.analytics') }}">{!! $ui::icon('chart') !!}Аналитика</a>
    @endif
    <a class="ns-chipf" href="{{ route('warehouse.consignments') }}" title="Пары у посредников">{!! $ui::icon('hand') !!}Реализация</a>
    @if($isHead)
        <a class="ns-chipf" href="{{ route('onec.index') }}" title="Продажи белых пар в 1С">{!! $ui::icon('doc1c') !!}1С</a>
    @endif
    @if($movements->isNotEmpty())
        <button class="ns-chipf" type="button" id="nsMoves" title="Последние изменения остатков">{!! $ui::icon('clock') !!}Движения</button>
    @endif
    <span class="ns-grow"></span>
    <a class="ns-btn ns-sm" href="{{ route('warehouse.import.form') }}">{!! $ui::icon('up', 'ns-sm') !!}Импорт</a>
    <a class="ns-btn ns-sm" href="{{ route('warehouse.export') }}">{!! $ui::icon('down', 'ns-sm') !!}Экспорт CSV</a>
</div>
<div class="ns-view"><div class="ns-page" id="nsWpage">
    @include('partials.ns-flash')
    <div class="ns-wsum" id="nsWsum"></div>
    <div class="ns-brands ns-wfilters">
        <div class="ns-search ns-wsearch">{!! $ui::icon('search', 'ns-sm') !!}<input id="nsWq" type="search" placeholder="Бренд, модель, артикул или размер" autocomplete="off" data-scan="own" aria-label="Поиск по складу"></div>
        <button class="ns-chipf" type="button" data-f="cat" data-l="Категория"></button>
        <button class="ns-chipf" type="button" data-f="sex" data-l="Пол"></button>
        <button class="ns-chipf" type="button" data-f="sea" data-l="Сезон"></button>
        <button class="ns-chipf" type="button" id="nsWlow"><span class="ns-sw"></span>Заканчивается<span class="ns-cn" id="nsWlowN"></span></button>
        <span class="ns-wcount" id="nsWcount"></span>
    </div>
    <div class="ns-wgrid" id="nsWgrid"></div>
</div></div>
<button class="ns-fab" type="button" id="nsNewProd">{!! $ui::icon('plus', 'ns-sm') !!}Товар</button>
<div class="ns-bulk" id="nsBulk" hidden></div>
@endsection

@push('scripts')
<script>window.nsWh = {!! json_encode($cfg, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
<script>
(() => {
    'use strict';
    const W = window.nsWh, I = W.ic, $ = (id) => document.getElementById(id), esc = ns.esc;
    let P = W.products;
    const F = { q: W.initial.q || '', low: !!W.initial.low, cat: W.initial.cat || '', sex: W.initial.sex || '', sea: W.initial.sea || '' };
    const sel = new Set();
    const fmt = (v) => Math.round(+v || 0).toLocaleString('ru-RU').replace(/-/, '−');
    const rub = (v) => fmt(v) + ' ₽';
    const u = (k, id) => W.url[k].replace('__ID__', id);
    const byId = (id) => P.find((p) => p.id === id);
    const plural = (k, a, b, c) => { const m = k % 10, h = k % 100; return m === 1 && h !== 11 ? a : (m >= 2 && m <= 4 && (h < 12 || h > 14) ? b : c); };

    /* ---------- запросы: прежние маршруты склада, ответ — JSON с товаром ---------- */
    const api = async (method, url, data, form) => {
        const opt = { method, credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' } };
        if (form) opt.body = data;
        else if (data) { opt.headers['Content-Type'] = 'application/json'; opt.body = JSON.stringify(data); }
        const r = await fetch(url, opt);
        let j = null;
        try { j = await r.json(); } catch (e) {}
        if (!r.ok) {
            if (r.status === 419) throw new Error('Страница устарела — обновите её (F5)');
            if (r.status === 403) throw new Error('Нет прав на это действие');
            throw new Error(j && j.errors ? Object.values(j.errors)[0][0] : ((j && j.message) || 'Ошибка ' + r.status));
        }
        return j;
    };
    const run = (btn, promise) => {
        if (btn) btn.disabled = true;
        return promise.then((j) => { apply(j); ns.hidePop(); if (j && j.msg) ns.toast(j.msg); return j; })
            .catch((e) => ns.toast(e.message))
            .finally(() => { if (btn) btn.disabled = false; });
    };
    const apply = (j) => {
        if (!j) return;
        (j.products || (j.product !== undefined ? [j.product] : [])).forEach((np) => {
            if (!np) return;
            const i = P.findIndex((p) => p.id === np.id);
            if (i >= 0) P[i] = np; else P.unshift(np);
            redraw(np.id);
        });
        if (j.product === null && j.id) {
            P = P.filter((p) => p.id !== j.id);
            sel.delete(j.id);
            document.querySelector('.ns-wc[data-pid="' + j.id + '"]')?.remove();
        }
        drawSum(); filter(); drawBulk();
    };

    /* ---------- расчёты (как на прежней странице склада) ---------- */
    const pairs = (p) => p.sz.reduce((a, z) => a + z.q, 0);
    const avail = (p) => p.sz.reduce((a, z) => a + Math.max(0, z.a), 0);
    const value = (p) => p.sz.reduce((a, z) => a + z.q * (z.p || 0), 0);
    // «Заканчивается» — мин. остаток размера, где он задан, иначе ≤ 2 пар у модели (решение 28.10.2026)
    const isLow = (p) => { const t = p.sz.filter((z) => z.min > 0); return t.length ? t.some((z) => z.a <= z.min) : avail(p) <= 2; };
    const priceOf = (p) => {
        const ps = [...new Set(p.sz.map((z) => z.p).filter((v) => v !== null))].sort((a, b) => a - b);
        return ps.length ? (ps.length > 1 ? 'от ' : '') + rub(ps[0]) : '—';
    };

    /* ---------- блоки «Остатки» и «Склад в деньгах» ---------- */
    const drawSum = () => {
        let q = 0, a = 0, r = 0, k = 0, cost = 0, val = 0, priced = 0;
        const zg = {};
        P.forEach((p) => p.sz.forEach((z) => {
            q += z.q; a += z.a; r += z.r; k += z.k;
            val += z.q * (z.p || 0);
            if (z.p !== null && z.q > 0) priced += z.q;
            if (W.seesCost) cost += z.q * (z.c || 0);
            const s = parseFloat(String(z.s).replace(',', '.'));
            if (z.q > 0 && s >= 15 && s <= 55) { const key = Math.floor(s); zg[key] = (zg[key] || 0) + z.q; }   // половинки — к меньшему целому
        }));
        const zk = Object.keys(zg).map(Number).sort((x, y) => x - y);
        const zmax = Math.max(1, ...zk.map((s) => zg[s])), zall = zk.reduce((x, s) => x + zg[s], 0);
        const hot = zk.slice().sort((x, y) => zg[y] - zg[x]).slice(0, 3);
        const hotP = zall ? Math.round(hot.reduce((x, s) => x + zg[s], 0) / zall * 100) : 0;
        const left = '<div class="ns-panel"><div class="ns-ws-h">' + I.box + 'Остатки' +
            (hot.length ? '<span class="ns-tag ns-ok" title="Больше всего пар этих размеров">' + Math.min(...hot) + (hot.length > 1 ? '–' + Math.max(...hot) : '') + ' р. · ' + hotP + '% пар</span>' : '') + '</div>' +
            '<div class="ns-ws-row"><div class="ns-ws-i"><span>Товаров</span><b>' + fmt(P.length) + '</b><small>моделей</small></div>' +
            '<div class="ns-ws-i"><span>Пар на складе</span><b>' + fmt(q) + '</b><small>всего</small></div>' +
            '<div class="ns-ws-i"><span>Доступно</span><b>' + fmt(a) + '</b><small>резерв ' + fmt(r) + (k ? ' · у посредников ' + fmt(k) : '') + '</small></div></div>' +
            (zk.length ? '<div class="ns-ws-foot"><div class="ns-wmini">' + zk.map((s) => '<i class="' + (hot.includes(s) ? 'ns-hot' : '') + '" style="height:' + Math.max(6, zg[s] / zmax * 100).toFixed(1) + '%" title="Размер ' + s + '–' + s + '.5: ' + zg[s] + ' ' + plural(zg[s], 'пара', 'пары', 'пар') + '"></i>').join('') + '</div>' +
                '<div class="ns-wmini-l">' + zk.map((s) => '<span class="' + (hot.includes(s) ? 'ns-hot' : '') + '">' + s + '</span>').join('') + '</div></div>' : '') + '</div>';
        let right;
        if (W.seesCost) {
            const profit = val - cost, cp = val > 0 ? Math.min(100, cost / val * 100) : 0, mg = val > 0 ? Math.round(profit / val * 100) : 0;
            right = '<div class="ns-panel"><div class="ns-ws-h">' + I.sell + 'Склад в деньгах<span class="ns-tag ns-ok">маржа ' + mg + '%</span></div><div class="ns-ws-row">' +
                '<div class="ns-ws-i"><span><i style="background:color-mix(in srgb,var(--ink) 22%,var(--pill))"></i>Вложено · по закупке</span><b>' + rub(cost) + '</b></div>' +
                '<div class="ns-ws-i"><span><i style="background:var(--accent)"></i>Потенц. прибыль</span><b class="ns-up">' + rub(profit) + '</b></div>' +
                '<div class="ns-ws-i"><span>Стоимость · по продаже</span><b>' + rub(val) + '</b></div></div>' +
                '<div class="ns-ws-foot"><div class="ns-wbar"><i class="ns-c" style="width:' + cp.toFixed(1) + '%"></i><i class="ns-p" style="flex:1"></i></div><div class="ns-wbar-l"><span>закупка ' + Math.round(cp) + '%</span><span>прибыль ' + mg + '%</span></div></div></div>';
        } else {
            // продавцу — без закупки и прибыли (решение 28.10.2026)
            right = '<div class="ns-panel"><div class="ns-ws-h">' + I.sell + 'Склад в деньгах</div><div class="ns-ws-row ns-ws-row2">' +
                '<div class="ns-ws-i"><span>Стоимость · по продаже</span><b>' + rub(val) + '</b><small>все пары по цене продажи</small></div>' +
                '<div class="ns-ws-i"><span>Средняя цена пары</span><b>' + rub(priced ? val / priced : 0) + '</b><small>' + fmt(priced) + ' пар с ценой</small></div></div></div>';
        }
        $('nsWsum').innerHTML = left + right;
    };

    /* ---------- карточка товара ---------- */
    const imgHtml = (p) => p.img
        ? '<img src="' + esc(p.img) + '" alt="" loading="lazy" data-b="' + esc(p.brand) + '">'
        : '<span class="ns-phb">' + esc((p.brand || '?').slice(0, 2).toUpperCase()) + '</span>';
    const card = (p) => {
        const st = pairs(p), av = avail(p), low = isLow(p), r = p.sz.reduce((x, z) => x + z.r, 0), k = p.sz.reduce((x, z) => x + z.k, 0);
        return '<div class="ns-wc' + (sel.has(p.id) ? ' ns-chk' : '') + '" data-pid="' + p.id + '"><button class="ns-wck" type="button" title="Выбрать">' + I.check + '</button>' +
            '<label class="ns-wimg" title="' + (p.img ? 'Сменить фото' : 'Загрузить фото') + '">' + imgHtml(p) +
            (low ? '<span class="ns-tag ns-wtag ' + (av <= 0 ? 'ns-bad' : 'ns-warn') + '">' + (av <= 0 ? 'нет в наличии' : 'заканчивается') + '</span>' : '') +
            '<span class="ns-wimg-h">' + I.cam + (p.img ? 'сменить фото' : 'загрузить фото') + '</span><input type="file" accept="image/*" hidden data-photo></label>' +
            '<div class="ns-wbody"><div class="ns-pname"><span title="' + esc(p.name) + '">' + esc(p.name) + '</span><button class="ns-ib" type="button" data-ren title="Переименовать">' + I.pen + '</button></div>' +
            '<div class="ns-part">' + (p.art ? '<span class="ns-mono">' + esc(p.art) + '</span>·' : '') + '<span>' + esc(p.brand) + '</span>·<span>' + p.sz.length + ' разм.</span></div>' +
            '<div class="ns-wstat"><div><span>Пар</span><b>' + fmt(st) + '</b></div><div><span>На сумму</span><b>' + rub(value(p)) + '</b></div><div><span>За пару</span><b>' + priceOf(p) + '</b></div></div>' +
            (r || k ? '<div class="ns-wnote">доступно ' + fmt(av) + (r ? ' · резерв ' + r : '') + (k ? ' · у посредников ' + k : '') + '</div>' : '') +
            '<div class="ns-wsz-h">Размер · остаток<em>нажмите для правки</em></div>' +
            '<div class="ns-wpills">' + p.sz.map((z) => '<button class="ns-wp' + (z.a <= 0 ? ' ns-z' : '') + '" type="button" data-iid="' + z.id + '" title="Размер ' + esc(z.s || '—') + ' — доступно ' + z.a + '"><span>' + esc(z.s || '—') + '</span><i class="' + (z.a <= 0 ? 'ns-z' : z.a === 1 ? 'ns-l' : '') + '">' + String(z.a).replace('-', '−') + '</i></button>').join('') +
            '<button class="ns-wadd" type="button">+ размер</button></div></div>' +
            '<div class="ns-wfoot"><button class="ns-btn ns-sm" type="button" data-mng>' + I.gear + 'Управление</button><a class="ns-btn ns-sm ns-icon-only" href="' + esc(W.url.labels + '?type=price&product=' + p.id) + '" target="_blank" rel="noopener" title="Ценники и этикетки">' + I.tag + '</a></div></div>';
    };
    const imgFallback = (root) => root.querySelectorAll('.ns-wimg img').forEach((img) => {
        const swap = () => { const s = document.createElement('span'); s.className = 'ns-phb'; s.textContent = (img.dataset.b || '?').slice(0, 2).toUpperCase(); img.replaceWith(s); };
        if (img.complete && img.naturalWidth === 0) swap(); else img.addEventListener('error', swap, { once: true });
    });
    const grid = $('nsWgrid');
    const drawAll = () => { grid.innerHTML = P.map(card).join('') + '<div class="ns-panel ns-empty" id="nsWempty" hidden style="grid-column:1/-1">' + I.box + '<b>Ничего не нашлось</b>Сбросьте поиск и фильтры</div>'; imgFallback(grid); };
    const redraw = (id) => {
        const p = byId(id), old = grid.querySelector('.ns-wc[data-pid="' + id + '"]');
        if (!p) return;
        const tmp = document.createElement('div');
        tmp.innerHTML = card(p);
        const el = tmp.firstElementChild;
        if (old) old.replaceWith(el); else grid.prepend(el);
        imgFallback(el);
    };

    /* ---------- поиск и фильтры ---------- */
    const hay = (p) => (p.name + ' ' + p.auto + ' ' + p.art + ' ' + p.brand + ' ' + p.sz.map((z) => z.s).join(' ')).toLowerCase();
    const filter = () => {
        const q = F.q.toLowerCase();
        let shown = 0, lowN = 0;
        P.forEach((p) => {
            const low = isLow(p);
            if (low) lowN++;
            const ok = (!F.cat || p.cat === F.cat) && (!F.sex || p.sex === F.sex) && (!F.sea || p.sea === F.sea) && (!F.low || low) && (!q || hay(p).includes(q));
            const el = grid.querySelector('.ns-wc[data-pid="' + p.id + '"]');
            if (el) el.hidden = !ok;
            if (ok) shown++;
        });
        $('nsWempty').hidden = shown > 0;
        $('nsWlowN').textContent = lowN ? lowN : '';
        $('nsWlow').classList.toggle('ns-on', F.low);
        $('nsWcount').textContent = shown === P.length ? '' : shown + ' из ' + P.length;
        document.querySelectorAll('[data-f]').forEach((b) => {
            const k = b.dataset.f, v = F[k];
            b.classList.toggle('ns-on', !!v);
            b.innerHTML = esc(v ? (W.opts[k][v] || v) : b.dataset.l) + I.chev.replace('ns-icon', 'ns-icon ns-chev');
        });
    };
    const qi = $('nsWq');
    qi.value = F.q;
    let qt;
    qi.addEventListener('input', () => { clearTimeout(qt); qt = setTimeout(() => { F.q = qi.value.trim(); filter(); }, 90); });
    $('nsWlow').addEventListener('click', () => { F.low = !F.low; filter(); });
    document.querySelectorAll('[data-f]').forEach((b) => b.addEventListener('click', (e) => {
        e.stopPropagation();
        const k = b.dataset.f, keys = Object.keys(W.opts[k]), labels = ['Все'].concat(keys.map((x) => W.opts[k][x]));
        ns.menuPop(b, b.dataset.l, labels, F[k] ? W.opts[k][F[k]] : 'Все', (v) => { F[k] = v === 'Все' ? '' : keys[labels.indexOf(v) - 1]; filter(); });
    }));

    /* ---------- окно размера (sizeEditor прототипа) ---------- */
    const sizeEditor = (btn, p, z) => {
        const head = W.isHead;
        const pop = ns.showPop(btn, '<div class="ns-ph">Размер ' + esc(z.s || '—') + ' · ' + esc(p.brand) + '</div><div class="ns-szed">' +
            '<div class="ns-field"' + (head ? '' : ' style="grid-column:1/-1"') + '><label>Остаток</label><div class="ns-step" style="height:38px"><button type="button" data-d="-1">−</button><input id="seQ" type="number" min="0" value="' + z.q + '"><button type="button" data-d="1">+</button></div></div>' +
            (head ? '<div class="ns-field"><label>Мин. остаток</label><input id="seMin" type="number" min="0" value="' + z.min + '"></div>' +
                '<div class="ns-field"><label>Закупка, ₽</label><input id="seC" type="number" min="0" step="0.01" value="' + (z.c ?? '') + '" placeholder="—"></div>' +
                '<div class="ns-field"><label>Продажа, ₽</label><input id="seP" type="number" min="0" step="0.01" value="' + (z.p ?? '') + '" placeholder="—"></div>' : '') +
            '</div><div class="ns-szm" id="seM">' + (head ? '' : (z.p !== null ? 'Цена продажи <b>' + rub(z.p) + '</b>' : 'Цена не задана — её ставит руководитель')) + '</div>' +
            (z.r || z.k ? '<div class="ns-szm">доступно ' + z.a + (z.r ? ' · резерв ' + z.r : '') + (z.k ? ' · у посредников ' + z.k : '') + '</div>' : '') +
            '<div class="ns-szf">' + (head ? '<button class="ns-btn ns-sm ns-danger" type="button" id="seDel">Удалить размер</button>' : '') + '<button class="ns-btn ns-sm ns-primary" type="button" id="seSave">Сохранить</button></div>' +
            (z.cons.length ? '<div class="ns-szc"><label>У посредников</label>' + z.cons.map((c) => '<div class="ns-cons"><span>' + esc(c.who) + ' · ' + c.q + ' ' + plural(c.q, 'пара', 'пары', 'пар') + '</span>' +
                '<button class="ns-btn ns-sm" type="button" data-res="sold" data-cid="' + c.id + '" title="Посредник продал">продано</button><button class="ns-btn ns-sm" type="button" data-res="returned" data-cid="' + c.id + '" title="Вернули на склад">возврат</button></div>').join('') + '</div>' : '') +
            '<div class="ns-szc"><label>Передать посреднику на реализацию</label><div class="ns-row"><input id="scW" placeholder="Кому" maxlength="255"><input id="scQ" type="number" min="1" max="' + Math.max(1, z.a) + '" value="1" style="flex:0 0 60px"><button class="ns-btn ns-sm" type="button" id="scGo"' + (z.a <= 0 ? ' disabled title="Свободных пар нет"' : '') + '>Передать</button></div></div>', 320);
        const m = () => {
            if (!head) return;
            const c = parseFloat($('seC').value), pr = parseFloat($('seP').value);
            $('seM').innerHTML = c > 0 && pr > 0 ? 'Наценка <b>' + Math.round((pr / c - 1) * 100) + '%</b> · прибыль с пары <b>' + rub(pr - c) + '</b>' : '';
        };
        pop.querySelectorAll('.ns-step button').forEach((b) => b.addEventListener('click', () => { const i = $('seQ'); i.value = Math.max(0, (parseInt(i.value, 10) || 0) + +b.dataset.d); }));
        if (head) { ['seC', 'seP'].forEach((id) => $(id).addEventListener('input', m)); m(); }
        const save = () => {
            const d = { quantity: Math.max(0, parseInt($('seQ').value, 10) || 0) };
            if (head) { d.low_stock_threshold = Math.max(0, parseInt($('seMin').value, 10) || 0); d.avg_cost = $('seC').value === '' ? null : +$('seC').value; d.sale_price = $('seP').value === '' ? null : +$('seP').value; }
            run($('seSave'), api('PATCH', u('update', z.id), d));
        };
        $('seSave').addEventListener('click', save);
        pop.querySelectorAll('input').forEach((i) => i.addEventListener('keydown', (e) => { if (e.key === 'Enter' && i.id !== 'scW' && i.id !== 'scQ') save(); }));
        $('seDel')?.addEventListener('click', () => { if (confirm('Удалить размер ' + (z.s || '—') + ' у «' + p.name + '»?')) run($('seDel'), api('DELETE', u('destroy', z.id))); });
        pop.querySelectorAll('[data-res]').forEach((b) => b.addEventListener('click', () => run(b, api('POST', u('resolve', b.dataset.cid), { result: b.dataset.res }))));
        $('scGo').addEventListener('click', () => {
            const who = $('scW').value.trim();
            if (!who) { ns.toast('Кому передаёте? Укажите посредника'); $('scW').focus(); return; }
            run($('scGo'), api('POST', u('consign', z.id), { consignee: who, quantity: Math.max(1, parseInt($('scQ').value, 10) || 1) }));
        });
        $('seQ').focus();
        $('seQ').select();
    };

    const addSizePop = (btn, p) => {
        ns.showPop(btn, '<div class="ns-ph">Новый размер · ' + esc(p.brand) + '</div><div class="ns-szed"><div class="ns-field"><label>Размер</label><input id="asS" placeholder="42.5" maxlength="20"></div><div class="ns-field"><label>Пар</label><input id="asQ" type="number" min="0" value="1"></div></div>' +
            '<div class="ns-szm">Цена — как у других размеров модели</div><div class="ns-szf"><button class="ns-btn ns-sm ns-primary" type="button" id="asGo">Добавить</button></div>', 260);
        const go = () => {
            const s = $('asS').value.trim().replace(',', '.');
            if (!s) { $('asS').focus(); return; }
            run($('asGo'), api('POST', W.url.store, { brand: p.b, model: p.m, size: s, quantity: Math.max(0, parseInt($('asQ').value, 10) || 0) }));
        };
        $('asGo').addEventListener('click', go);
        ['asS', 'asQ'].forEach((id) => $(id).addEventListener('keydown', (e) => { if (e.key === 'Enter') go(); }));
        $('asS').focus();
    };

    const renamePop = (btn, p) => {
        ns.showPop(btn, '<div class="ns-ph">Название товара</div><div style="padding:6px 10px 2px"><div class="ns-field"><input id="rnN" maxlength="255" value="' + esc(p.name) + '" placeholder="' + esc(p.auto) + '"></div>' +
            '<div class="ns-szm" style="padding:0 0 8px">Пусто — вернуть исходное: ' + esc(p.auto) + '</div></div><div class="ns-szf"><button class="ns-btn ns-sm ns-primary" type="button" id="rnGo">Сохранить</button></div>', 320);
        const go = () => run($('rnGo'), api('PATCH', u('product', p.id), { custom_name: $('rnN').value.trim() === p.auto ? '' : $('rnN').value.trim() }));
        $('rnGo').addEventListener('click', go);
        $('rnN').addEventListener('keydown', (e) => { if (e.key === 'Enter') go(); });
        $('rnN').select();
    };

    /* ---------- управление товаром (managePop прототипа) + артикул и фото ---------- */
    const options = (k, v) => '<option value="">—</option>' + Object.entries(W.opts[k]).map(([key, l]) => '<option value="' + esc(key) + '"' + (key === v ? ' selected' : '') + '>' + esc(l) + '</option>').join('');
    const managePop = (btn, p) => {
        const prices = (k) => [...new Set(p.sz.map((z) => z[k]).filter((v) => v !== null && v !== undefined))];
        const one = (k) => { const v = prices(k); return v.length === 1 ? v[0] : ''; };
        const pop = ns.showPop(btn, '<div class="ns-ph">Управление товаром</div><div style="padding:6px 10px">' +
            '<div class="ns-field"><label>Категория</label><select id="mgC">' + options('cat', p.cat) + '</select></div>' +
            '<div class="ns-frow"><div class="ns-field"><label>Пол</label><select id="mgS">' + options('sex', p.sex) + '</select></div><div class="ns-field"><label>Сезон</label><select id="mgSe">' + options('sea', p.sea) + '</select></div></div>' +
            '<div class="ns-field"><label>Артикул <i>для штрихкода и сканера</i></label><input id="mgA" class="ns-mono" maxlength="64" value="' + esc(p.art) + '"></div>' +
            (W.isHead ? '<div class="ns-field"><label>Цена для всех размеров, ₽ <i>закупка · продажа</i></label><div class="ns-mg-row"><input id="mgCost" type="number" min="0" step="0.01" placeholder="закупка" value="' + one('c') + '"><input id="mgP" type="number" min="0" step="0.01" placeholder="продажа" value="' + one('p') + '"><button class="ns-btn ns-sm" type="button" id="mgAll">Применить</button></div>' +
                (prices('p').length > 1 || prices('c').length > 1 ? '<div class="ns-szm" style="padding:4px 0 0">Сейчас цены у размеров разные</div>' : '') + '</div>' : '') +
            '<div class="ns-field"><label>Фото <i>первое — на карточке</i></label><div class="ns-thumbs">' + p.gal.map((g) => '<span class="ns-thumb"><img src="' + esc(g.url) + '" alt="">' +
                (g.id ? '<button type="button" data-gdel="' + g.id + '" title="Удалить фото">' + I.x + '</button>' : '') + '</span>').join('') +
            '<label class="ns-thumb ns-thumb-add" title="Добавить фото">' + I.plus + '<input type="file" accept="image/*" hidden data-photo></label></div></div>' +
            '</div><div class="ns-szf"><a class="ns-btn ns-sm" href="' + esc(W.url.labels + '?type=price&product=' + p.id) + '" target="_blank" rel="noopener">' + I.tag + 'Ценники</a><button class="ns-btn ns-sm ns-primary" type="button" id="mgSave">Сохранить</button></div>', 340);
        $('mgSave').addEventListener('click', () => run($('mgSave'), api('PATCH', u('product', p.id), { category: $('mgC').value, gender: $('mgS').value, season: $('mgSe').value, article: $('mgA').value.trim() })));
        $('mgAll')?.addEventListener('click', () => {
            const d = { cost: $('mgCost').value === '' ? null : +$('mgCost').value, price: $('mgP').value === '' ? null : +$('mgP').value };
            if (d.cost === null && d.price === null) { ns.toast('Укажите закупку и/или цену продажи'); return; }
            run($('mgAll'), api('PATCH', u('prices', p.id), d));
        });
        pop.querySelectorAll('[data-gdel]').forEach((b) => b.addEventListener('click', () => { if (confirm('Удалить это фото?')) run(b, api('DELETE', u('photoDel', p.id), { photo_id: +b.dataset.gdel })); }));
        pop.querySelector('[data-photo]').addEventListener('change', (e) => upload(p, e.target));
    };
    const upload = (p, input) => {
        const f = input.files && input.files[0];
        if (!f) return;
        const fd = new FormData();
        fd.append('photo', f);
        ns.toast('Загружаю фото…');
        run(null, api('POST', u('photo', p.id), fd, true));
        input.value = '';
    };

    /* ---------- новый товар (openNewProduct прототипа) ---------- */
    const openNew = (code) => {
        const m = ns.openModal('<div class="ns-m-head"><div><div class="ns-mt">Новый товар</div><div class="ns-msub">Фото можно добавить после создания</div></div><button class="ns-m-x" type="button" data-ns-close aria-label="Закрыть">' + I.x + '</button></div><div class="ns-m-body">' +
            '<div class="ns-field"><label>Название</label><input id="npN" maxlength="255" placeholder="Например, NIKE air force 1’07"></div>' +
            '<div class="ns-frow"><div class="ns-field"><label>Артикул <i>необязательно</i></label><input id="npA" class="ns-mono" maxlength="64" placeholder="выдаст CRM"></div><div class="ns-field"><label>Бренд <i>необязательно</i></label><input id="npB" maxlength="100" placeholder="Nike"></div></div>' +
            '<div class="ns-field"><label>Размеры и пары</label><input id="npS" placeholder="41:1, 42:2, 43:1"></div>' +
            '<div class="ns-frow">' + (W.isHead ? '<div class="ns-field"><label>Закупка, ₽</label><input id="npC" type="number" min="0" step="0.01"></div>' : '') + '<div class="ns-field"' + (W.isHead ? '' : ' style="grid-column:1/-1"') + '><label>Продажа, ₽</label><input id="npP" type="number" min="0" step="0.01"></div></div></div>' +
            '<div class="ns-m-foot"><button class="ns-btn" type="button" data-ns-close>Отмена</button><button class="ns-btn ns-primary" type="button" id="npGo">Создать</button></div>', 520);
        $('npGo').addEventListener('click', () => {
            const name = $('npN').value.trim();
            if (!name) { ns.toast('Введите название'); $('npN').focus(); return; }
            const d = { name, article: $('npA').value.trim(), brand: $('npB').value.trim(), sizes: $('npS').value.trim(), price: $('npP').value === '' ? null : +$('npP').value };
            if (W.isHead) d.cost = $('npC').value === '' ? null : +$('npC').value;
            const b = $('npGo');
            b.disabled = true;
            api('POST', W.url.store, d).then((j) => { ns.closeModal(); F.q = ''; qi.value = ''; apply(j); ns.toast(j.msg); grid.querySelector('.ns-wc[data-pid="' + j.id + '"]')?.scrollIntoView({ block: 'center', behavior: 'smooth' }); })
                .catch((e) => ns.toast(e.message)).finally(() => { b.disabled = false; });
        });
        if (code) m.querySelector('#npA').value = code;
        m.querySelector('#npN').focus();
    };
    $('nsNewProd').addEventListener('click', () => openNew(''));
    if (W.initial.new) { openNew(W.initial.new); history.replaceState(null, '', location.pathname); }

    /* ---------- выбор карточек и массовые действия ---------- */
    const drawBulk = () => {
        const b = $('nsBulk');
        [...sel].forEach((id) => { if (!byId(id)) sel.delete(id); });
        if (!sel.size) { b.hidden = true; return; }
        b.innerHTML = 'Выбрано: ' + sel.size +
            '<a class="ns-btn" href="' + esc(W.url.labels + '?type=price&products=' + [...sel].join(',')) + '" target="_blank" rel="noopener">' + I.tag + 'Ценники</a>' +
            '<button class="ns-btn" type="button" data-bk="category" data-o="cat">Категория</button><button class="ns-btn" type="button" data-bk="gender" data-o="sex">Пол</button><button class="ns-btn" type="button" data-bk="season" data-o="sea">Сезон</button>' +
            '<button class="ns-btn" type="button" id="nsBulkX" title="Снять выбор">' + I.x + '</button>';
        b.hidden = false;
        b.querySelectorAll('[data-bk]').forEach((btn) => btn.addEventListener('click', (e) => {
            e.stopPropagation();
            const o = W.opts[btn.dataset.o], keys = Object.keys(o), labels = keys.map((k) => o[k]).concat(['Снять']);
            ns.menuPop(btn, btn.textContent + ' для ' + sel.size, labels, null, (v) => {
                const val = v === 'Снять' ? '' : keys[labels.indexOf(v)];
                run(btn, api('POST', W.url.bulk, { product_ids: [...sel], action: btn.dataset.bk, value: val }));
            });
        }));
        $('nsBulkX').addEventListener('click', () => { sel.clear(); grid.querySelectorAll('.ns-wc.ns-chk').forEach((c) => c.classList.remove('ns-chk')); drawBulk(); });
    };

    /* ---------- клики по карточкам ---------- */
    grid.addEventListener('click', (e) => {
        const c = e.target.closest('.ns-wc');
        if (!c) return;
        const p = byId(+c.dataset.pid);
        if (!p) return;
        const t = e.target;
        if (t.closest('.ns-wck')) { sel.has(p.id) ? sel.delete(p.id) : sel.add(p.id); c.classList.toggle('ns-chk', sel.has(p.id)); drawBulk(); return; }
        const pill = t.closest('.ns-wp');
        if (pill) { e.stopPropagation(); sizeEditor(pill, p, p.sz.find((z) => z.id === +pill.dataset.iid)); return; }
        if (t.closest('.ns-wadd')) { e.stopPropagation(); addSizePop(t.closest('.ns-wadd'), p); return; }
        if (t.closest('[data-mng]')) { e.stopPropagation(); managePop(t.closest('[data-mng]'), p); return; }
        if (t.closest('[data-ren]')) { e.stopPropagation(); renamePop(t.closest('[data-ren]'), p); }
    });
    grid.addEventListener('change', (e) => {
        if (!e.target.matches('[data-photo]')) return;
        const p = byId(+e.target.closest('.ns-wc').dataset.pid);
        if (p) upload(p, e.target);
    });

    /* ---------- последние движения ---------- */
    $('nsMoves')?.addEventListener('click', () => {
        ns.openModal('<div class="ns-m-head"><div><div class="ns-mt">Последние движения</div><div class="ns-msub">' + W.moves.length + ' последних изменений остатков</div></div><button class="ns-m-x" type="button" data-ns-close aria-label="Закрыть">' + I.x + '</button></div>' +
            '<div class="ns-m-body ns-mv">' + W.moves.map((m) => '<div class="ns-mv-r"><span class="ns-mv-t">' + esc(m.at) + '</span><div class="ns-mv-n"><b>' + esc(m.name) + '</b><small>' + esc(m.type) + (m.note ? ' · ' + esc(m.note) : '') + (m.user ? ' · ' + esc(m.user) : '') + '</small></div>' +
                '<b class="' + (m.q < 0 ? 'ns-dn' : 'ns-up') + '">' + (m.q > 0 ? '+' : '') + String(m.q).replace('-', '−') + '</b></div>').join('') + '</div>', 620);
    });

    drawSum();
    drawAll();
    filter();
})();
</script>
@endpush
