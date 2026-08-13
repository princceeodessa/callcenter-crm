@extends('layouts.app')

@section('content')
<style>
    :root{
        --wh-radius: 14px;
        --wh-pill: 999px;
        --wh-green:#10b981; --wh-amber:#f59e0b; --wh-red:#ef4444;
        --wh-shadow-sm: 0 1px 3px rgba(15,23,42,.05), 0 1px 2px rgba(15,23,42,.04);
        --wh-shadow-md: 0 8px 24px rgba(15,23,42,.07);
    }
    body[data-theme="night"]{
        --wh-shadow-sm: 0 1px 3px rgba(0,0,0,.4);
        --wh-shadow-md: 0 8px 24px rgba(0,0,0,.45);
    }

    /* ---------- hero ---------- */
    .wh-hero{ display:grid; grid-template-columns:repeat(auto-fit,minmax(170px,1fr)); gap:.7rem; margin-bottom:1rem; }
    .wh-stat{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:var(--wh-radius); padding:.75rem 1rem; box-shadow:var(--wh-shadow-sm); }
    .wh-stat .l{ font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--crm-muted); font-weight:600; }
    .wh-stat .v{ font-size:1.6rem; font-weight:800; line-height:1.15; letter-spacing:-.02em; }

    /* ---------- toolbar ---------- */
    .wh-bar{
        display:flex; flex-wrap:wrap; align-items:center; gap:.5rem;
        padding:.55rem .7rem; margin-bottom:.6rem;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:var(--wh-radius); box-shadow:var(--wh-shadow-sm);
    }
    .wh-bar .search{
        flex:1; min-width:190px; display:flex; align-items:center; gap:.4rem;
        padding:.3rem .75rem; background:var(--crm-surface); border:1px solid var(--crm-border);
        border-radius:var(--wh-pill);
    }
    .wh-bar .search input{ flex:1; border:0; background:transparent; outline:none; font-size:.88rem; color:var(--crm-text); }
    .wh-bar .search input::placeholder{ color:var(--crm-muted); }
    .wh-bar select.f{
        border:1px solid var(--crm-border); background:var(--crm-surface); color:var(--crm-text);
        border-radius:var(--wh-pill); padding:.3rem 1.7rem .3rem .7rem; font-size:.82rem; outline:none;
        max-width:170px;
    }
    .wh-bar select.f.on{ border-color:var(--crm-accent); color:var(--crm-accent); font-weight:600; }
    .wh-bar .chip-toggle{
        display:inline-flex; align-items:center; gap:.35rem; padding:.3rem .75rem;
        border:1px solid var(--crm-border); background:var(--crm-surface); border-radius:var(--wh-pill);
        font-size:.82rem; cursor:pointer; user-select:none; color:var(--crm-text);
    }
    .chip-toggle input{ accent-color:var(--wh-amber); margin:0; }
    .chip-toggle.on{ border-color:var(--wh-amber); color:#b45309; font-weight:600; }
    body[data-theme="night"] .chip-toggle.on{ color:#fbbf24; }
    .wh-bar .btn-find{ border-radius:var(--wh-pill); padding:.3rem .9rem; font-size:.84rem; font-weight:600; }

    .wh-actions{ display:flex; flex-wrap:wrap; gap:.4rem; margin-bottom:1rem; }
    .wh-actions a{
        font-size:.78rem; color:var(--crm-muted); text-decoration:none;
        padding:.25rem .7rem; border:1px solid var(--crm-border); border-radius:var(--wh-pill);
        background:var(--crm-surface);
    }
    .wh-actions a:hover{ color:var(--crm-accent); border-color:var(--crm-accent); }

    /* ---------- grid & card ---------- */
    .wh-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(330px,1fr)); gap:.9rem; }
    .prod{
        position:relative;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:var(--wh-radius); padding:.9rem .95rem;
        box-shadow:var(--wh-shadow-sm);
        transition:box-shadow .15s ease, border-color .15s ease;
        content-visibility:auto; contain-intrinsic-size: 300px; /* карточки за экраном не рендерятся */
    }
    .prod:hover{ box-shadow:var(--wh-shadow-md); }
    .prod.is-low{ border-color:color-mix(in srgb, var(--wh-amber) 55%, var(--crm-border)); }
    .prod.is-danger{ border-color:color-mix(in srgb, var(--wh-red) 55%, var(--crm-border)); }

    .prod-check{
        position:absolute; top:.65rem; right:.65rem; z-index:3; margin:0; cursor:pointer;
        opacity:.35; transition:opacity .12s;
    }
    .prod-check input{ width:17px; height:17px; accent-color:var(--crm-accent); cursor:pointer; display:block; }
    .prod:hover .prod-check, .prod-check:has(input:checked){ opacity:1; }

    .prod-top{ display:flex; gap:.8rem; margin-bottom:.65rem; }
    .pphoto{
        position:relative; width:96px; height:96px; flex-shrink:0;
        border-radius:12px; overflow:hidden; cursor:pointer;
        display:flex; align-items:center; justify-content:center;
        box-shadow:inset 0 0 0 1px rgba(255,255,255,.08), var(--wh-shadow-sm);
    }
    .pphoto img{ width:100%; height:100%; object-fit:cover; }
    .pphoto .pletter{ color:#fff; font-weight:800; font-size:2rem; letter-spacing:-.02em; }
    .pphoto .hint{
        position:absolute; left:0; right:0; bottom:0; padding:.15rem 0;
        font-size:.6rem; text-align:center; color:#fff; background:rgba(0,0,0,.5);
        opacity:0; transition:opacity .12s; pointer-events:none; /* клик проходит к файловому input */
    }
    .pphoto:hover .hint{ opacity:1; }
    .pphoto img{ pointer-events:none; }

    .pinfo{ min-width:0; flex:1; padding-right:1.2rem; }
    details.name-edit > summary{ list-style:none; cursor:pointer; }
    details.name-edit > summary::-webkit-details-marker{ display:none; }
    .pname{ font-weight:700; font-size:.95rem; line-height:1.25; letter-spacing:-.01em; word-break:break-word; }
    .pedit{ color:var(--crm-muted); font-size:.8rem; margin-left:.3rem; opacity:.5; }
    details.name-edit > summary:hover .pedit{ opacity:1; color:var(--crm-accent); }
    .name-form{ display:flex; gap:.35rem; margin-top:.4rem; }
    .name-form input{
        flex:1; padding:.3rem .55rem; font-size:.85rem; border:1px solid var(--crm-border);
        border-radius:.5rem; background:var(--crm-surface); color:var(--crm-text); outline:none;
    }
    .name-form input:focus{ border-color:var(--crm-accent); }

    .pmeta{ font-size:.74rem; color:var(--crm-muted); margin-top:.25rem; }
    .pmeta .mono{ font-family:ui-monospace,"SFMono-Regular",Menlo,monospace; font-weight:700; letter-spacing:.04em; }
    .pbadges{ display:flex; flex-wrap:wrap; gap:.3rem; margin-top:.35rem; }
    .pb{
        font-size:.68rem; padding:.12rem .5rem; border-radius:var(--wh-pill);
        background:color-mix(in srgb, var(--crm-accent) 8%, var(--crm-surface));
        border:1px solid color-mix(in srgb, var(--crm-accent) 18%, var(--crm-border));
        color:var(--crm-text);
    }
    .pb.warn{
        background:rgba(245,158,11,.12); border-color:rgba(245,158,11,.4); color:#b45309; font-weight:700;
    }
    body[data-theme="night"] .pb.warn{ color:#fbbf24; }
    .pnums{ font-size:.8rem; color:var(--crm-muted); margin-top:.4rem; }
    .pnums b{ color:var(--crm-text); }

    /* ---------- sizes ---------- */
    .sizes{ display:flex; flex-wrap:wrap; gap:.35rem; }
    details.pill-more{ position:relative; }
    details.pill-more > summary{ list-style:none; cursor:pointer; }
    details.pill-more > summary::-webkit-details-marker{ display:none; }
    .size-pill{
        display:inline-flex; align-items:center; gap:.35rem;
        padding:.28rem .5rem .28rem .65rem;
        background:var(--crm-surface); border:1px solid var(--crm-border);
        border-radius:var(--wh-pill); font-size:.78rem; user-select:none;
        transition:border-color .12s;
    }
    .size-pill:hover{ border-color:var(--crm-accent); }
    .size-pill .sz{ color:var(--crm-muted); font-weight:600; font-size:.72rem; }
    .size-pill .qty{
        min-width:21px; height:21px; padding:0 .38rem;
        display:inline-flex; align-items:center; justify-content:center;
        border-radius:var(--wh-pill); color:#fff; font-weight:800; font-size:.72rem;
    }
    .size-pill.q-success .qty{ background:var(--wh-green); }
    .size-pill.q-warning .qty{ background:var(--wh-amber); }
    .size-pill.q-danger  .qty{ background:var(--wh-red); }
    .size-pill.add{ border-style:dashed; color:var(--crm-muted); }
    .size-pill.add:hover{ color:var(--crm-accent); }
    details.pill-more[open] .size-pill{ border-color:var(--crm-accent); background:color-mix(in srgb, var(--crm-accent) 10%, var(--crm-surface)); }

    .pill-popover{
        margin-top:.45rem; padding:.7rem; width:100%;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:.7rem; box-shadow:var(--wh-shadow-md);
    }
    .pill-popover .qmini{
        display:flex; align-items:center; justify-content:space-between; gap:.5rem;
        margin-bottom:.5rem; padding-bottom:.5rem; border-bottom:1px dashed var(--crm-border);
    }
    .pill-popover .qbig{ font-size:1.5rem; font-weight:800; line-height:1; }
    .pill-popover .qlabel{ font-size:.68rem; color:var(--crm-muted); text-transform:uppercase; letter-spacing:.05em; }
    .pill-popover .qbtn{
        width:30px; height:30px; border-radius:50%; padding:0;
        display:inline-flex; align-items:center; justify-content:center;
        border:1px solid var(--crm-border); background:var(--crm-surface);
        font-size:1rem; font-weight:700; line-height:1; cursor:pointer;
    }
    .pill-popover .qbtn.plus{ color:var(--wh-green); border-color:var(--wh-green); }
    .pill-popover .qbtn.minus{ color:var(--wh-red); border-color:var(--wh-red); }
    .pill-popover .frow{ display:flex; align-items:center; gap:.5rem; margin-bottom:.35rem; }
    .pill-popover .frow label{ min-width:78px; color:var(--crm-muted); margin:0; font-size:.7rem; }
    .pill-popover .frow input{
        flex:1; padding:.28rem .5rem; font-size:.82rem; border:1px solid var(--crm-border);
        background:var(--crm-surface); border-radius:.45rem; color:var(--crm-text); outline:none;
    }
    .pill-popover .frow input:focus{ border-color:var(--crm-accent); }
    .pill-popover .actions{ display:flex; gap:.4rem; margin-top:.45rem; }
    .pill-popover .actions .btn{ flex:1; font-size:.76rem; padding:.3rem .4rem; border-radius:.45rem; }

    /* ---------- tags ---------- */
    .ptags{ display:flex; flex-wrap:wrap; gap:.25rem; margin-top:.55rem; }
    .ptags .t{ font-size:.66rem; padding:.1rem .5rem; border-radius:var(--wh-pill); background:rgba(148,163,184,.14); color:var(--crm-muted); }

    /* ---------- settings drawer ---------- */
    details.psettings{ margin-top:.6rem; border-top:1px dashed var(--crm-border); padding-top:.5rem; }
    details.psettings > summary{
        list-style:none; cursor:pointer; font-size:.74rem; color:var(--crm-muted);
        display:inline-flex; align-items:center; gap:.3rem;
    }
    details.psettings > summary::-webkit-details-marker{ display:none; }
    details.psettings > summary:hover{ color:var(--crm-accent); }
    .ps-body{ margin-top:.6rem; display:flex; flex-direction:column; gap:.6rem; }
    .ps-row{ display:flex; flex-wrap:wrap; align-items:center; gap:.4rem; }
    .ps-row .lbl{ font-size:.7rem; color:var(--crm-muted); min-width:74px; }
    .ps-row select{
        border:1px solid var(--crm-border); background:var(--crm-surface); color:var(--crm-text);
        border-radius:.45rem; padding:.2rem 1.5rem .2rem .45rem; font-size:.74rem; outline:none;
    }
    .ps-row input[type="text"]{
        width:110px; border:1px solid var(--crm-border); background:var(--crm-surface); color:var(--crm-text);
        border-radius:.45rem; padding:.2rem .45rem; font-size:.76rem; outline:none;
        font-family:ui-monospace,"SFMono-Regular",Menlo,monospace;
    }
    .ps-row input[type="number"]{
        width:96px; border:1px solid var(--crm-border); background:var(--crm-surface); color:var(--crm-text);
        border-radius:.45rem; padding:.2rem .45rem; font-size:.76rem; outline:none;
    }
    .ps-row .bc{ height:24px; display:inline-flex; align-items:center; }
    .ps-row .bc svg{ height:100%; width:auto; max-width:130px; }
    .btn-xxs{ font-size:.72rem; padding:.18rem .55rem; border-radius:.45rem; }

    .thumbs{ display:flex; flex-wrap:wrap; gap:.3rem; }
    .thumb{ position:relative; width:40px; height:40px; border-radius:8px; overflow:hidden; border:1px solid var(--crm-border); }
    .thumb img{ width:100%; height:100%; object-fit:cover; }
    .thumb .del{
        position:absolute; top:0; right:0; width:16px; height:16px; padding:0; border:0;
        background:rgba(220,38,38,.85); color:#fff; font-size:.65rem; line-height:1; cursor:pointer;
        opacity:0; transition:opacity .12s;
    }
    .thumb:hover .del{ opacity:1; }
    .thumb-add{
        position:relative; width:40px; height:40px; border-radius:8px;
        border:1px dashed var(--crm-border); display:flex; align-items:center; justify-content:center;
        color:var(--crm-muted); cursor:pointer; font-size:1rem;
    }
    .thumb-add:hover{ color:var(--crm-accent); border-color:var(--crm-accent); }

    /* ---------- fab / bulk / moves ---------- */
    .fab{
        position:fixed; right:1.4rem; bottom:1.4rem; z-index:20;
        width:54px; height:54px; border-radius:50%; border:0;
        background:linear-gradient(135deg, var(--crm-accent), color-mix(in srgb, var(--crm-accent) 55%, #8b5cf6));
        color:#fff; font-size:1.5rem; line-height:1;
        display:flex; align-items:center; justify-content:center;
        box-shadow:0 10px 24px rgba(79,70,229,.35); cursor:pointer;
    }
    .new-product-panel{
        position:fixed; right:1.4rem; bottom:5.4rem; z-index:20;
        width:min(400px, calc(100vw - 2.8rem));
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:var(--wh-radius); box-shadow:var(--wh-shadow-md); padding:1rem;
    }
    .wh-details > summary{ list-style:none; }
    .wh-details > summary::-webkit-details-marker{ display:none; }
    .wh-details:not([open]) .new-product-panel{ display:none; }

    .moves-title{
        display:inline-flex; align-items:center; gap:.4rem; cursor:pointer;
        padding:.35rem .85rem; border-radius:var(--wh-pill);
        background:var(--crm-surface); border:1px solid var(--crm-border);
        font-size:.78rem; font-weight:600; color:var(--crm-text);
        font-family:inherit; line-height:1.3;
    }
    .moves-title:hover{ border-color:var(--crm-accent); color:var(--crm-accent); }
    .moves-timeline{ margin-top:.8rem; position:relative; padding-left:1.1rem; }
    .moves-timeline::before{ content:""; position:absolute; left:.32rem; top:.4rem; bottom:.4rem; width:2px; background:var(--crm-border); }
    .move{ position:relative; padding:.3rem 0 .3rem .3rem; font-size:.78rem; }
    .move::before{ content:""; position:absolute; left:-.95rem; top:.5rem; width:.62rem; height:.62rem; border-radius:50%; background:var(--crm-surface); border:2px solid var(--crm-muted); }
    .move.in::before, .move.replenish::before{ border-color:var(--wh-green); }
    .move.out::before, .move.return::before{ border-color:var(--wh-red); }
    .m-delta.pos{ color:var(--wh-green); font-weight:800; }
    .m-delta.neg{ color:var(--wh-red); font-weight:800; }
</style>

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $state = fn ($i) => (int) $i->available <= 0 ? 'danger' : ($i->is_low ? 'warning' : 'success');

    $brandColor = function (string $brand) {
        $palette = [
            ['#6366f1','#4338ca'], ['#0ea5e9','#0369a1'], ['#10b981','#047857'],
            ['#f59e0b','#b45309'], ['#ef4444','#b91c1c'], ['#ec4899','#9d174d'],
            ['#8b5cf6','#6d28d9'], ['#14b8a6','#0f766e'], ['#f97316','#c2410c'],
            ['#84cc16','#4d7c0f'],
        ];
        $h = 0; foreach (str_split(strtolower($brand)) as $c) { $h = ($h * 31 + ord($c)) & 0xffffffff; }
        [$a, $b] = $palette[$h % count($palette)];
        return "linear-gradient(135deg, $a 0%, $b 100%)";
    };
    $brandLetter = fn (string $brand) => mb_strtoupper(mb_substr(trim($brand) ?: '?', 0, 1));

    $isEmpty = $productsPage->count() === 0;
    $hasPages = $productsPage->hasPages();
    $hasMovements = $movements->isNotEmpty();

    $exportParams = array_filter([
        'q' => $q ?: null, 'low' => $lowFilter ? '1' : null,
        'cat' => $filterCategory ?: null, 'sex' => $filterGender ?: null,
        'sea' => $filterSeason ?: null, 'tag' => $filterTag ?: null,
    ]);

    $moveLabels = [
        'in' => 'Приход', 'in_adjust' => 'Корректировка прихода',
        'in_reversal' => 'Откат прихода', 'out' => 'Продажа',
        'out_reversal' => 'Возврат на склад', 'return' => 'Возврат продажи',
        'reserve' => 'Резерв', 'reserve_release' => 'Снятие резерва',
        'replenish' => 'Пополнение', 'adjust' => 'Корректировка',
    ];
@endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0" style="letter-spacing:-.02em">Склад</h4>
        <div class="text-muted small">кроссовки · {{ $productsCount }} товаров</div>
    </div>
    <div class="wh-actions mb-0">
        <a href="{{ route('warehouse.reorder') }}">🧠 Что заказать</a>
        <a href="{{ route('warehouse.analytics') }}">📊 Аналитика</a>
        <a href="{{ route('warehouse.consignments') }}">🤝 Реализация</a>
        <a href="{{ route('warehouse.import.form') }}">📦 Импорт пачкой</a>
        <a href="{{ route('warehouse.export', $exportParams) }}">📥 Экспорт CSV</a>
    </div>
</div>

{{-- HERO --}}
<div class="wh-hero">
    <div class="wh-stat"><div class="l">Товаров</div><div class="v">{{ $productsCount }}</div></div>
    <div class="wh-stat"><div class="l">Пар на складе</div><div class="v">{{ $totalUnits }}</div></div>
    <div class="wh-stat"><div class="l">Доступно</div><div class="v">{{ $availableSum }}</div></div>
    @if($isHead)
        <div class="wh-stat"><div class="l">Вложено · по закупке</div><div class="v">{{ $money($stockCostValue) }} ₽</div></div>
    @endif
    <div class="wh-stat"><div class="l">Стоимость · по продаже</div><div class="v">{{ $money($stockValue) }} ₽</div></div>
    @if($isHead && $stockCostValue > 0)
        <div class="wh-stat"><div class="l">Потенц. прибыль</div><div class="v" style="color:var(--wh-green)">{{ $money($stockValue - $stockCostValue) }} ₽</div></div>
    @endif
</div>

{{-- TOOLBAR: поиск + фильтры одной строкой --}}
<form method="GET" action="{{ route('warehouse.index') }}" class="wh-bar">
    <div class="search">
        <span aria-hidden="true" style="color:var(--crm-muted)">🔎</span>
        <input type="search" name="q" value="{{ $q }}" placeholder="Бренд, модель или размер…">
    </div>
    <button class="btn btn-primary btn-sm btn-find" type="submit">Найти</button>

    <select name="cat" class="f {{ $filterCategory !== '' ? 'on' : '' }}" onchange="this.form.submit()">
        <option value="">Категория</option>
        @foreach($categoryOptions as $k => $l)
            <option value="{{ $k }}" @selected($filterCategory === $k)>{{ $l }}</option>
        @endforeach
    </select>
    <select name="sex" class="f {{ $filterGender !== '' ? 'on' : '' }}" onchange="this.form.submit()">
        <option value="">Пол</option>
        @foreach($genderOptions as $k => $l)
            <option value="{{ $k }}" @selected($filterGender === $k)>{{ $l }}</option>
        @endforeach
    </select>
    <select name="sea" class="f {{ $filterSeason !== '' ? 'on' : '' }}" onchange="this.form.submit()">
        <option value="">Сезон</option>
        @foreach($seasonOptions as $k => $l)
            <option value="{{ $k }}" @selected($filterSeason === $k)>{{ $l }}</option>
        @endforeach
    </select>
    @if($allTags->count() > 0)
        <select name="tag" class="f {{ $filterTag !== '' ? 'on' : '' }}" onchange="this.form.submit()">
            <option value="">Тег</option>
            @foreach($allTags as $t)
                <option value="{{ $t }}" @selected($filterTag === $t)>#{{ $t }}</option>
            @endforeach
        </select>
    @endif

    <label class="chip-toggle {{ $lowFilter ? 'on' : '' }}">
        <input type="checkbox" name="low" value="1" onchange="this.form.submit()" {{ $lowFilter ? 'checked' : '' }}>
        Заканчивается{{ $lowCount > 0 ? ' · '.$lowCount : '' }}
    </label>

    @if($q !== '' || $lowFilter || $filterCategory !== '' || $filterGender !== '' || $filterSeason !== '' || $filterTag !== '')
        <a class="chip-toggle" href="{{ route('warehouse.index') }}" style="text-decoration:none">✕ Сброс</a>
    @endif
</form>

<form method="POST" action="{{ route('warehouse.bulk') }}" id="bulk-form" style="display:none">@csrf</form>

{{-- PRODUCTS --}}
@if($isEmpty)
    <div class="alert alert-light border" style="border-radius:var(--wh-radius)">
        @if($q !== '' || $lowFilter || $filterCategory !== '' || $filterGender !== '' || $filterSeason !== '' || $filterTag !== '')
            Ничего не нашли. <a href="{{ route('warehouse.index') }}">Сбросить фильтры</a>.
        @else
            На складе пока пусто. Закупки попадут сюда автоматически на стадии «Получено / На складе», либо нажмите «+» справа внизу.
        @endif
    </div>
@else
    <div class="wh-grid">
        @foreach($productsPage as $prod)
            @php
                $hasDanger = $prod['sizes']->contains(fn ($i) => (int) $i->available <= 0);
                $prodClass = $prod['low'] ? ($hasDanger ? 'is-danger' : 'is-low') : '';
                $sizeCount = $prod['sizes']->count();
                $cover = $prod['image_url'];
                $catLabel = $prod['category'] !== null ? ($categoryOptions[$prod['category']] ?? null) : null;
                $genLabel = $prod['gender'] !== null ? ($genderOptions[$prod['gender']] ?? null) : null;
                $seaLabel = $prod['season'] !== null ? ($seasonOptions[$prod['season']] ?? null) : null;
            @endphp
            <article class="prod {{ $prodClass }}">
                <label class="prod-check" title="Выбрать для массовых действий">
                    <input type="checkbox" name="product_ids[]" value="{{ $prod['entity']->id }}" class="bulk-check" form="bulk-form">
                </label>

                <div class="prod-top">
                    <label class="pphoto" style="background:{{ $cover ? 'transparent' : $brandColor($prod['brand']) }}" title="{{ $cover ? 'Заменить фото' : 'Загрузить фото' }}">
                        @if($cover)
                            <img src="{{ $cover }}" alt="{{ $prod['name'] }}">
                        @else
                            <span class="pletter">{{ $brandLetter($prod['brand']) }}</span>
                        @endif
                        <form method="POST" action="{{ route('warehouse.product.photo.upload', $prod['entity']) }}" enctype="multipart/form-data" style="position:absolute;inset:0;margin:0;">
                            @csrf
                            <input type="file" name="photo" accept="image/*" onchange="this.form.submit()" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
                        </form>
                        <span class="hint">сменить фото</span>
                    </label>

                    <div class="pinfo">
                        <details class="name-edit">
                            <summary title="Нажмите ✎, чтобы переименовать">
                                <span class="pname">{{ $prod['name'] }}</span><span class="pedit">✎</span>
                            </summary>
                            <form class="name-form" method="POST" action="{{ route('warehouse.product.update', $prod['entity']) }}">
                                @csrf @method('PATCH')
                                <input type="text" name="custom_name" value="{{ $prod['custom_name'] ?? $prod['auto_name'] }}" maxlength="255" placeholder="{{ $prod['auto_name'] }}">
                                <button class="btn btn-sm btn-primary">OK</button>
                            </form>
                        </details>
                        <div class="pmeta"><span class="mono">{{ $prod['article'] }}</span> · {{ $prod['brand'] ?: '—' }} · {{ $sizeCount }} разм.</div>
                        <div class="pbadges">
                            @if($catLabel)<span class="pb">{{ $catLabel }}</span>@endif
                            @if($genLabel)<span class="pb">{{ $genLabel }}</span>@endif
                            @if($seaLabel)<span class="pb">{{ $seaLabel }}</span>@endif
                            @if($prod['low'])
                                <span class="pb warn">⚠ заканчивается</span>
                            @endif
                        </div>
                        <div class="pnums">
                            <b>{{ $prod['total'] }}</b> пар · <b>{{ $prod['available'] }}</b> доступно{{ $prod['reserved'] > 0 ? ' · резерв '.$prod['reserved'] : '' }}{{ $prod['consigned'] > 0 ? ' · на реализации '.$prod['consigned'] : '' }}{{ $prod['value'] > 0 ? ' · '.$money($prod['value']).' ₽' : '' }}
                        </div>
                    </div>
                </div>

                {{-- размеры --}}
                <div class="sizes">
                    @foreach($prod['sizes'] as $i)
                        @php
                            $st = $state($i);
                        @endphp
                        <details class="pill-more">
                            <summary>
                                <span class="size-pill q-{{ $st }}">
                                    <span class="sz">{{ $i->size !== '' ? 'р.'.$i->size : '—' }}</span>
                                    <span class="qty">{{ (int) $i->available }}</span>
                                </span>
                            </summary>
                            <div class="pill-popover">
                                <div class="qmini">
                                    <div>
                                        <div class="qlabel">Размер {{ $i->size !== '' ? $i->size : '—' }}</div>
                                        <div class="qbig">{{ (int) $i->quantity }} <span style="font-size:.65rem;color:var(--crm-muted);font-weight:500">пар</span></div>
                                        @if($i->reserved > 0)
                                            <div style="font-size:.68rem;color:var(--crm-muted)">резерв: {{ $i->reserved }}</div>
                                        @endif
                                        @if($i->consigned > 0)
                                            <div style="font-size:.68rem;color:var(--crm-muted)">на реализации: {{ $i->consigned }}</div>
                                        @endif
                                    </div>
                                    <form method="POST" action="{{ route('warehouse.replenish', $i) }}" class="d-flex gap-2">
                                        @csrf
                                        <button class="qbtn minus" name="delta" value="-1" title="−1 пара">−</button>
                                        <button class="qbtn plus" name="delta" value="1" title="+1 пара">+</button>
                                    </form>
                                </div>
                                <form method="POST" action="{{ route('warehouse.update', $i) }}">
                                    @csrf @method('PATCH')
                                    <div class="frow"><label>Остаток</label><input type="number" name="quantity" min="0" value="{{ $i->quantity }}"></div>
                                    @if($isHead)
                                        <div class="frow"><label>Закупка ₽</label><input type="number" step="0.01" min="0" name="avg_cost" value="{{ $i->avg_cost }}" placeholder="—"></div>
                                        <div class="frow"><label>Продажа ₽</label><input type="number" step="0.01" min="0" name="sale_price" value="{{ $i->sale_price }}" placeholder="—"></div>
                                        <div class="frow"><label>Мин. остаток</label><input type="number" min="0" name="low_stock_threshold" value="{{ $i->low_stock_threshold }}"></div>
                                    @endif
                                    <div class="actions">
                                        <button type="submit" class="btn btn-primary">Сохранить</button>
                                    </div>
                                </form>
                                @if($isHead)
                                    <form method="POST" action="{{ route('warehouse.destroy', $i) }}" onsubmit="return confirm('Удалить размер?')" class="mt-1">
                                        @csrf @method('DELETE')
                                        <button type="submit" class="btn btn-outline-danger w-100 btn-xxs">Удалить размер</button>
                                    </form>
                                @endif

                                @if($i->consignments->isNotEmpty())
                                    <div class="mt-2 pt-2" style="border-top:1px solid var(--crm-border)">
                                        <div style="font-size:.68rem;color:var(--crm-muted);margin-bottom:4px">На реализации</div>
                                        @foreach($i->consignments as $c)
                                            <div class="d-flex align-items-center justify-content-between gap-1 mb-1" style="font-size:.72rem">
                                                <span>{{ $c->consignee }} · {{ $c->quantity }} пар</span>
                                                <span class="d-flex gap-1">
                                                    <form method="POST" action="{{ route('warehouse.consignment.resolve', $c) }}">
                                                        @csrf
                                                        <input type="hidden" name="result" value="sold">
                                                        <button type="submit" class="btn btn-outline-success btn-xxs" title="Посредник продал">✓ продано</button>
                                                    </form>
                                                    <form method="POST" action="{{ route('warehouse.consignment.resolve', $c) }}">
                                                        @csrf
                                                        <input type="hidden" name="result" value="returned">
                                                        <button type="submit" class="btn btn-outline-secondary btn-xxs" title="Вернули на склад">↩ возврат</button>
                                                    </form>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif

                                <details class="mt-2">
                                    <summary style="font-size:.72rem;cursor:pointer;color:var(--crm-muted)">🤝 Передать под реализацию</summary>
                                    <form method="POST" action="{{ route('warehouse.item.consign', $i) }}" class="mt-1">
                                        @csrf
                                        <div class="frow"><label>Кому (посредник)</label><input type="text" name="consignee" maxlength="255" required placeholder="Напр. ТЦ Юность, точка 12"></div>
                                        <div class="frow"><label>Кол-во пар</label><input type="number" name="quantity" min="1" max="{{ (int) $i->available }}" value="{{ min(1, (int) $i->available) }}" required></div>
                                        <div class="actions">
                                            <button type="submit" class="btn btn-warning w-100" @disabled((int) $i->available <= 0)>Передать</button>
                                        </div>
                                    </form>
                                </details>
                            </div>
                        </details>
                    @endforeach

                    <details class="pill-more">
                        <summary><span class="size-pill add"><span class="sz" style="color:inherit">＋ размер</span></span></summary>
                        <div class="pill-popover">
                            <form method="POST" action="{{ route('warehouse.store') }}">
                                @csrf
                                <input type="hidden" name="brand" value="{{ $prod['brand'] }}">
                                <input type="hidden" name="model" value="{{ $prod['model'] }}">
                                <div class="frow"><label>Размер</label><input type="text" name="size" placeholder="42" required></div>
                                <div class="frow"><label>Кол-во</label><input type="number" name="quantity" min="0" value="1"></div>
                                <div class="actions"><button class="btn btn-success">Добавить</button></div>
                            </form>
                        </div>
                    </details>
                </div>

                @if(!empty($prod['tags']))
                    <div class="ptags">
                        @foreach($prod['tags'] as $t)
                            <span class="t">#{{ $t }}</span>
                        @endforeach
                    </div>
                @endif

                {{-- всё управление — за ⚙️, карточка остаётся чистой --}}
                <details class="psettings">
                    <summary>⚙️ Управление товаром</summary>
                    <div class="ps-body">
                        <div class="ps-row">
                            <span class="lbl">Разметка</span>
                            <form method="POST" action="{{ route('warehouse.product.update', $prod['entity']) }}" class="d-flex flex-wrap gap-1">
                                @csrf @method('PATCH')
                                <select name="category" onchange="this.form.submit()">
                                    <option value="">— категория —</option>
                                    @foreach($categoryOptions as $k => $l)
                                        <option value="{{ $k }}" @selected($prod['category'] === $k)>{{ $l }}</option>
                                    @endforeach
                                </select>
                                <select name="gender" onchange="this.form.submit()">
                                    <option value="">— пол —</option>
                                    @foreach($genderOptions as $k => $l)
                                        <option value="{{ $k }}" @selected($prod['gender'] === $k)>{{ $l }}</option>
                                    @endforeach
                                </select>
                                <select name="season" onchange="this.form.submit()">
                                    <option value="">— сезон —</option>
                                    @foreach($seasonOptions as $k => $l)
                                        <option value="{{ $k }}" @selected($prod['season'] === $k)>{{ $l }}</option>
                                    @endforeach
                                </select>
                            </form>
                        </div>
                        @if($isHead)
                            <div class="ps-row">
                                <span class="lbl">Цены</span>
                                <form method="POST" action="{{ route('warehouse.product.prices', $prod['entity']) }}" class="d-flex align-items-center gap-1 flex-wrap">
                                    @csrf @method('PATCH')
                                    <input type="number" step="0.01" min="0" name="cost" value="{{ $prod['cost_common'] }}" placeholder="закуп ₽" title="За сколько закупаете (себестоимость пары)">
                                    <input type="number" step="0.01" min="0" name="price" value="{{ $prod['price_common'] }}" placeholder="продажа ₽" title="За сколько обычно продаёте">
                                    <button class="btn btn-primary btn-xxs">Применить ко всем размерам</button>
                                    @if($prod['margin'] !== null)
                                        <span class="small text-muted">наценка {{ $prod['margin'] }}%</span>
                                    @endif
                                    @if($prod['mixed_prices'])
                                        <span class="small text-muted" title="У размеров этой модели сейчас разные цены">⚠ цены различаются по размерам</span>
                                    @endif
                                </form>
                            </div>
                        @endif
                        <div class="ps-row">
                            <span class="lbl">Артикул</span>
                            <form method="POST" action="{{ route('warehouse.product.update', $prod['entity']) }}" class="d-flex align-items-center gap-1">
                                @csrf @method('PATCH')
                                <input type="text" name="article" value="{{ $prod['article'] }}" maxlength="64">
                                <button class="btn btn-outline-secondary btn-xxs">OK</button>
                            </form>
                            <a class="btn btn-outline-secondary btn-xxs" href="{{ route('warehouse.product.label', $prod['entity']) }}" target="_blank">🖨️ Этикетка</a>
                        </div>
                        <div class="ps-row">
                            <span class="lbl">Доп. фото</span>
                            <div class="thumbs">
                                @foreach($prod['gallery'] as $photo)
                                    <span class="thumb">
                                        <img src="{{ $photo['url'] }}" alt="">
                                        @if($photo['id'])
                                            <form method="POST" action="{{ route('warehouse.product.photo.delete', $prod['entity']) }}" onsubmit="return confirm('Удалить фото?')" style="margin:0">
                                                @csrf @method('DELETE')
                                                <input type="hidden" name="photo_id" value="{{ $photo['id'] }}">
                                                <button type="submit" class="del" title="Удалить">×</button>
                                            </form>
                                        @endif
                                    </span>
                                @endforeach
                                <label class="thumb-add" title="Добавить фото">＋
                                    <form method="POST" action="{{ route('warehouse.product.photo.upload', $prod['entity']) }}" enctype="multipart/form-data" style="position:absolute;inset:0;margin:0;">
                                        @csrf
                                        <input type="file" name="photo" accept="image/*" onchange="this.form.submit()" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
                                    </form>
                                </label>
                            </div>
                        </div>
                    </div>
                </details>
            </article>
        @endforeach
    </div>
    @if($hasPages)
        <div class="d-flex justify-content-center mt-3">
            {{ $productsPage->onEachSide(1)->links('pagination::bootstrap-5') }}
        </div>
    @endif
@endif

{{-- Плавающая панель массовых действий --}}
<div id="bulk-panel" style="display:none;position:fixed;left:50%;bottom:1.4rem;transform:translateX(-50%);z-index:25;
     background:var(--crm-surface-strong);border:1px solid var(--crm-border);border-radius:var(--wh-radius);
     box-shadow:0 18px 40px rgba(15,23,42,.22);padding:.65rem 1rem;">
    <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="fw-semibold small">Выбрано: <span id="bulk-count">0</span></span>
        <select class="form-select form-select-sm" id="bulk-action" style="width:auto">
            <option value="">— действие —</option>
            <optgroup label="Категория">
                @foreach($categoryOptions as $k => $l)
                    <option value="category:{{ $k }}">Задать: {{ $l }}</option>
                @endforeach
                <option value="category:">Снять категорию</option>
            </optgroup>
            <optgroup label="Пол">
                @foreach($genderOptions as $k => $l)
                    <option value="gender:{{ $k }}">Задать: {{ $l }}</option>
                @endforeach
                <option value="gender:">Снять пол</option>
            </optgroup>
            <optgroup label="Сезон">
                @foreach($seasonOptions as $k => $l)
                    <option value="season:{{ $k }}">Задать: {{ $l }}</option>
                @endforeach
                <option value="season:">Снять сезон</option>
            </optgroup>
            <option value="tag_add:">➕ Добавить тег…</option>
            <option value="tag_remove:">➖ Убрать тег…</option>
        </select>
        <button type="button" class="btn btn-primary btn-sm" id="bulk-apply">Применить</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" id="bulk-clear">×</button>
    </div>
</div>
<script>
    (() => {
        const form = document.getElementById('bulk-form');
        const panel = document.getElementById('bulk-panel');
        const countEl = document.getElementById('bulk-count');
        const actionSel = document.getElementById('bulk-action');
        const update = () => {
            const n = document.querySelectorAll('.bulk-check:checked').length;
            countEl.textContent = n;
            panel.style.display = n > 0 ? 'block' : 'none';
        };
        document.querySelectorAll('.bulk-check').forEach(c => c.addEventListener('change', update));
        document.getElementById('bulk-apply').addEventListener('click', () => {
            const [action, value] = actionSel.value.split(':');
            if (!action) return alert('Выберите действие');
            let realValue = value ?? '';
            if (action === 'tag_add' || action === 'tag_remove') {
                realValue = prompt(action === 'tag_add' ? 'Название тега для добавления' : 'Название тега для удаления', '');
                if (realValue === null || realValue.trim() === '') return;
            }
            const a = document.createElement('input'); a.type = 'hidden'; a.name = 'action'; a.value = action;
            const v = document.createElement('input'); v.type = 'hidden'; v.name = 'value'; v.value = realValue;
            form.appendChild(a); form.appendChild(v);
            form.submit();
        });
        document.getElementById('bulk-clear').addEventListener('click', () => {
            document.querySelectorAll('.bulk-check:checked').forEach(c => c.checked = false);
            update();
        });
    })();
</script>

{{-- FAB: новый товар --}}
<details class="wh-details">
    <summary class="fab" title="Добавить товар">+</summary>
    <div class="new-product-panel">
        <h6 style="margin:0 0 .6rem;letter-spacing:-.01em">Новый товар</h6>
        <form method="POST" action="{{ route('warehouse.store') }}">
            @csrf
            <div class="row g-2 mb-2">
                <div class="col-6"><input type="text" name="brand" class="form-control form-control-sm" placeholder="Бренд · Nike" value="{{ old('brand') }}"></div>
                <div class="col-6"><input type="text" name="model" class="form-control form-control-sm" placeholder="Модель · Air Max 90" value="{{ old('model') }}"></div>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-4"><input type="text" name="size" class="form-control form-control-sm" placeholder="Размер · 42" value="{{ old('size') }}"></div>
                <div class="col-4"><input type="number" name="quantity" min="0" class="form-control form-control-sm" placeholder="Кол-во · 1" value="{{ old('quantity', 1) }}"></div>
                <div class="col-4"><input type="number" step="0.01" min="0" name="sale_price" class="form-control form-control-sm" placeholder="Цена, ₽" value="{{ old('sale_price') }}"></div>
            </div>
            <div class="row g-2">
                <div class="col-4"><input type="number" min="0" name="low_stock_threshold" class="form-control form-control-sm" placeholder="Мин." value="{{ old('low_stock_threshold', 0) }}"></div>
                <div class="col-8"><button class="btn btn-primary btn-sm w-100">Создать</button></div>
            </div>
        </form>
    </div>
</details>

{{-- Движения --}}
@if($hasMovements)
    <div class="mt-4">
        <button type="button" class="moves-title" id="movesToggle" aria-expanded="false">📜 Последние движения · {{ $movements->count() }}</button>
        <div class="moves-timeline" id="movesBox" style="display:none">
            @foreach($movements as $m)
                <div class="move {{ $m->type }}">
                    <span class="text-muted" style="font-size:.7rem">{{ optional($m->created_at)->format('d.m H:i') }}</span>
                    · <b>{{ $m->item?->display_name ?? '—' }}</b>
                    · {{ $moveLabels[$m->type] ?? $m->type }}
                    · <span class="m-delta {{ $m->quantity < 0 ? 'neg' : 'pos' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</span>
                    @if($m->note)
                        <span class="text-muted">— {{ $m->note }}</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>
    <script>
        (() => {
            const btn = document.getElementById('movesToggle');
            const box = document.getElementById('movesBox');
            if (!btn || !box) return;
            btn.addEventListener('click', () => {
                const show = box.style.display === 'none';
                box.style.display = show ? '' : 'none';
                btn.setAttribute('aria-expanded', show ? 'true' : 'false');
            });
        })();
    </script>
@endif
@endsection
