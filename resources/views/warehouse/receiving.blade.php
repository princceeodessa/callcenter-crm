@extends('layouts.sneakers')
{{--
    Приёмка кроссовочного отдела — каркас «Нордика» (прототип: PAGES.receiving, снимки 07, 08). Режим «Артикул / Честный знак»,
    одна строка «код · пар · Принять» (Enter со сканера = «Принять»). Найден товар — плитки размеров, клик по размеру = приход
    N пар в этот размер; не найден — «Завести товар». Запросы — WarehouseController::receivingScan (JSON), без перезагрузки.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $steps = [
        ['Выберите режим', 'артикул или код «Честного знака»'],
        ['Сканируйте пары', 'курсор уже в поле, Enter сканер нажмёт сам'],
        ['Укажите количество', 'если в коробке несколько одинаковых пар'],
        ['Выберите размер', 'остаток на складе вырастет сразу'],
        ['Новый код?', 'система предложит завести карточку товара'],
    ];
    $srcIcon = ['purchase' => 'truck', 'scan' => 'scan', 'import' => 'up', 'manual' => 'pen'];
    $jsIcons = collect(['check', 'warn', 'plus', 'truck', 'scan', 'up', 'pen'])->mapWithKeys(fn ($i) => [$i => $ui::icon($i, 'ns-sm')])->all();
@endphp

@section('title', 'Приёмка')
@section('ns_sub', 'со сканером · ТСД')

@section('content')
<div class="ns-rcv-grid">
    <div class="ns-rcv-main">
        <div class="ns-panel ns-rcv-scan">
            <div class="ns-rcv-h">
                <div><b>Сканирование</b><small>Поднесите сканер к коробке — код введётся сам</small></div>
                <div class="ns-seg" id="rcvSeg"><button type="button" data-m="article" class="ns-on">{!! $ui::icon('scan', 'ns-sm') !!} Артикул</button><button type="button" data-m="mark">{!! $ui::icon('tag', 'ns-sm') !!} «Честный знак»</button></div>
            </div>
            <form class="ns-rcv-bar" id="rvForm" autocomplete="off">
                {!! $ui::icon('scan') !!}<input id="rvIn" class="ns-mono" data-scan="own" placeholder="Штрих-код или артикул" aria-label="Код со сканера" autofocus>
                <span class="ns-rcv-sep"></span>
                <div class="ns-qty" id="rvQty"><span>Пар</span><div class="ns-qty-s"><button type="button" data-d="-1" title="Меньше">−</button><input id="rvQ" type="number" min="1" max="1000" value="1" aria-label="Пар"><button type="button" data-d="1" title="Больше">+</button></div></div>
                <button class="ns-btn ns-primary" type="submit" id="rvGo">{!! $ui::icon('check', 'ns-sm') !!}Принять</button>
            </form>
        </div>

        <div id="rvRes"></div>

        <div class="ns-panel" style="overflow:hidden">
            <div class="ns-panel-pad" style="padding-bottom:6px"><div class="ns-sec-h">Последние приходы <span class="ns-tag" id="rvCount">{{ $rows->count() }}</span></div></div>
            <table class="ns-tbl ns-rcv-tbl">
                <colgroup><col style="width:118px"><col><col style="width:84px"><col style="width:84px"><col style="width:150px"></colgroup>
                <thead><tr><th>Когда</th><th>Модель</th><th>Размер</th><th class="ns-num">Пар</th><th>Откуда</th></tr></thead>
                <tbody id="rvRows">
                @forelse($rows as $r)
                    <tr>
                        <td class="ns-muted">{{ $r['at'] }}</td>
                        <td><div class="ns-rcv-m"><div class="ns-pimg ns-xs">@if($r['img'])<img src="{{ $r['img'] }}" alt="" loading="lazy" data-b="{{ $r['brand'] }}">@else<span class="ns-phb">{{ mb_strtoupper(mb_substr($r['brand'], 0, 2)) }}</span>@endif</div><span title="{{ $r['name'] }}">{{ $r['name'] }}</span></div></td>
                        <td><b>{{ $r['size'] }}</b></td>
                        <td class="ns-num ns-up"><b>+{{ $r['q'] }}</b></td>
                        <td><span class="ns-tag ns-src-{{ $r['src'] }}" title="{{ $r['note'] }}">{!! $ui::icon($srcIcon[$r['src']], 'ns-sm') !!}{{ $r['label'] }}</span></td>
                    </tr>
                @empty
                    <tr class="ns-rv-empty"><td colspan="5" class="ns-muted" style="text-align:center;padding:24px">Приходов пока не было</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <aside class="ns-rcv-side">
        <div class="ns-panel ns-panel-pad"><div class="ns-sec-h">Сегодня</div>
            <div class="ns-rcv-stat"><div><span>Принято пар</span><b id="rvTodayQ">{{ $today['pairs'] }}</b></div><div><span>Позиций</span><b id="rvTodayN">{{ $today['items'] }}</b></div></div>
        </div>
        <div class="ns-panel ns-panel-pad"><div class="ns-sec-h">Как принимать</div>
            <div class="ns-steps">
                @foreach($steps as $i => [$t, $s])
                    <div class="ns-st"><i>{{ $i + 1 }}</i><div><b>{{ $t }}</b><small>{{ $s }}</small></div></div>
                @endforeach
            </div>
        </div>
    </aside>
</div>
@endsection

@push('scripts')
<script>
(() => {
    'use strict';
    const $ = (id) => document.getElementById(id), esc = ns.esc;
    const URL_SCAN = @json(route('warehouse.receiving.scan'));
    const IC = @json($jsIcons);
    const SRC_IC = { purchase: 'truck', scan: 'scan', import: 'up', manual: 'pen' };
    const inp = $('rvIn'), qi = $('rvQ'), res = $('rvRes');
    let mode = 'article';
    try { if (localStorage.getItem('rcvMode') === 'mark') mode = 'mark'; } catch (e) {}

    const api = async (data) => {
        const r = await fetch(URL_SCAN, { method: 'POST', credentials: 'same-origin', headers: { 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content, 'Accept': 'application/json', 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, body: JSON.stringify(data) });
        let j = null;
        try { j = await r.json(); } catch (e) {}
        if (!r.ok) throw new Error(r.status === 419 ? 'Страница устарела — обновите её (F5)' : (j && j.errors ? Object.values(j.errors)[0][0] : ((j && j.message) || 'Ошибка ' + r.status)));
        return j;
    };
    const qty = () => Math.min(1000, Math.max(1, parseInt(qi.value, 10) || 1));
    const line = (cls, icon, html) => '<div class="ns-resline ns-' + cls + '">' + IC[icon] + '<span style="flex:1;min-width:0">' + html + '</span></div>';
    const pairs = (k) => k + ' ' + (k % 10 === 1 && k % 100 !== 11 ? 'пара' : (k % 10 >= 2 && k % 10 <= 4 && (k % 100 < 12 || k % 100 > 14) ? 'пары' : 'пар'));
    const thumb = (img, brand, cls) => '<div class="ns-pimg' + (cls ? ' ' + cls : '') + '">' + (img ? '<img src="' + esc(img) + '" alt="" data-b="' + esc(brand) + '">' : '<span class="ns-phb">' + esc((brand || '?').slice(0, 2).toUpperCase()) + '</span>') + '</div>';
    const imgFallback = (root) => root.querySelectorAll('.ns-pimg img').forEach((img) => {
        const swap = () => { img.parentNode.innerHTML = '<span class="ns-phb">' + esc((img.dataset.b || '?').slice(0, 2).toUpperCase()) + '</span>'; };
        if (img.complete && img.naturalWidth === 0) swap(); else img.addEventListener('error', swap, { once: true });
    });
    imgFallback(document);

    const paintMode = () => {
        document.querySelectorAll('#rcvSeg button').forEach((b) => b.classList.toggle('ns-on', b.dataset.m === mode));
        inp.placeholder = mode === 'mark' ? 'Код «Честного знака»' : 'Штрих-код или артикул';
        $('rvQty').hidden = mode === 'mark';
    };
    document.querySelectorAll('#rcvSeg button').forEach((b) => b.addEventListener('click', () => {
        mode = b.dataset.m;
        try { localStorage.setItem('rcvMode', mode); } catch (e) {}
        paintMode(); res.innerHTML = ''; inp.value = ''; inp.focus();
    }));
    paintMode();
    document.querySelectorAll('#rvQty button').forEach((b) => b.addEventListener('click', () => { qi.value = Math.max(1, qty() + +b.dataset.d); inp.focus(); }));

    /* найденный товар: плитки размеров, клик — приход в этот размер (нулевые тоже нажимаются) */
    const showCards = (j) => {
        const k = qty();
        res.innerHTML = (j.similar ? line('warn', 'warn', 'Точно по коду <span class="ns-mono">' + esc(j.code) + '</span> ничего нет — похожие товары:') : '') +
            j.cards.map((c, ci) => '<div class="ns-panel ns-rv-card">' + thumb(c.img, c.brand) + '<div style="flex:1;min-width:0"><div class="ns-pname">' + esc(c.name) + '</div>' +
                '<div class="ns-part">' + (c.art ? '<span class="ns-mono">' + esc(c.art) + '</span>·' : '') + '<span>Выберите размер — придёт ' + pairs(k) + '</span></div>' +
                '<div class="ns-sizes" style="margin-top:8px">' + c.sizes.map((z) => '<button type="button" class="ns-sz' + (z.q <= 0 ? ' ns-z' : (z.q === 1 ? ' ns-low' : '')) + (j.highlight === z.id ? ' ns-on' : '') + '" data-ci="' + ci + '" data-id="' + z.id + '"><b>' + esc(z.s || '—') + '</b><small><i></i><span>' + z.q + '</span> шт</small></button>').join('') + '</div></div></div>').join('');
        imgFallback(res);
        res.querySelectorAll('.ns-sz').forEach((b) => b.addEventListener('click', async () => {
            b.disabled = true;
            try {
                const r = await api({ item_id: +b.dataset.id, quantity: qty() });
                b.querySelector('small span').textContent = r.item.q;
                b.className = 'ns-sz ns-on';
                addRow(r.row);
                $('rvTodayQ').textContent = r.today.pairs; $('rvTodayN').textContent = r.today.items;
                ns.toast(r.msg);
                qi.value = 1; inp.value = ''; inp.focus();
            } catch (e) { ns.toast(e.message); }
            b.disabled = false;
        }));
    };
    const addRow = (r) => {
        if (!r) return;
        document.querySelector('#rvRows .ns-rv-empty')?.remove();
        const tr = document.createElement('tr');
        tr.className = 'ns-rv-new';
        tr.innerHTML = '<td class="ns-muted">' + esc(r.at) + '</td><td><div class="ns-rcv-m">' + thumb(r.img, r.brand, 'ns-xs') + '<span title="' + esc(r.name) + '">' + esc(r.name) + '</span></div></td>' +
            '<td><b>' + esc(r.size) + '</b></td><td class="ns-num ns-up"><b>+' + r.q + '</b></td><td><span class="ns-tag ns-src-' + r.src + '" title="' + esc(r.note) + '">' + IC[SRC_IC[r.src]] + esc(r.label) + '</span></td>';
        $('rvRows').prepend(tr);
        imgFallback(tr);
        $('rvCount').textContent = $('rvRows').children.length;
    };

    $('rvForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = inp.value.trim();
        if (!code) { inp.focus(); return; }
        $('rvGo').disabled = true;
        try {
            const j = await api({ code, mode, quantity: qty() });
            if (j.status === 'found') showCards(j);
            else if (j.status === 'not_found') res.innerHTML = line('err', 'warn', 'Код <span class="ns-mono">' + esc(j.code) + '</span> не найден.') .replace('</span></div>', '</span><a class="ns-btn ns-sm" href="' + esc(j.new_url) + '">' + IC.plus + 'Завести товар</a></div>');
            else { res.innerHTML = line(j.status === 'mark_ok' ? 'ok' : (j.status === 'mark_dup' ? 'warn' : 'err'), j.status === 'mark_ok' ? 'check' : 'warn', esc(j.msg)); inp.value = ''; }
            inp.select();
        } catch (err) { ns.toast(err.message); }
        $('rvGo').disabled = false;
    });
})();
</script>
@endpush
