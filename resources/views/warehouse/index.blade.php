@extends('layouts.app')

@section('content')
<style>
    :root {
        --wh-radius: 1.2rem;
        --wh-pill: 999px;
        --wh-shadow-sm: 0 2px 6px rgba(15,23,42,.04), 0 1px 2px rgba(15,23,42,.04);
        --wh-shadow-md: 0 10px 30px rgba(15,23,42,.06), 0 2px 6px rgba(15,23,42,.05);
        --wh-shadow-lg: 0 20px 45px rgba(15,23,42,.10);
        --wh-green: #10b981; --wh-amber: #f59e0b; --wh-red: #ef4444; --wh-blue: #6366f1;
    }
    body[data-theme="night"] {
        --wh-shadow-sm: 0 2px 6px rgba(0,0,0,.35);
        --wh-shadow-md: 0 10px 30px rgba(0,0,0,.4);
        --wh-shadow-lg: 0 20px 45px rgba(0,0,0,.5);
    }

    /* ============ HERO STATS ============ */
    .wh-hero{ display:grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap:.75rem; margin-bottom:1.25rem; }
    .wh-stat{
        position:relative; overflow:hidden;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:var(--wh-radius); padding:.9rem 1rem; box-shadow:var(--wh-shadow-md);
        transition:transform .15s ease, box-shadow .15s ease;
    }
    .wh-stat:hover{ transform:translateY(-1px); box-shadow:var(--wh-shadow-lg); }
    .wh-stat::before{
        content:""; position:absolute; inset:auto -20% -60% auto;
        width:180px; height:180px; border-radius:50%;
        background:radial-gradient(circle, var(--_c, var(--crm-accent)) 0%, transparent 65%);
        opacity:.10; pointer-events:none;
    }
    .wh-stat .l{ font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:var(--crm-muted); font-weight:600; }
    .wh-stat .v{ font-size:2rem; font-weight:800; line-height:1.1; margin-top:.15rem; letter-spacing:-.02em; }
    .wh-stat .s{ font-size:.75rem; color:var(--crm-muted); margin-top:.15rem; }
    .wh-stat.stat-products{ --_c:#6366f1; }
    .wh-stat.stat-units{ --_c:#0ea5e9; }
    .wh-stat.stat-available{ --_c:#10b981; }
    .wh-stat.stat-value{ --_c:#f59e0b; }

    /* ============ TOOLBAR ============ */
    .wh-toolbar{
        position:sticky; top:56px; z-index:5;
        display:flex; flex-wrap:wrap; align-items:center; gap:.5rem;
        padding:.6rem .8rem; margin-bottom:1rem;
        background:color-mix(in srgb, var(--crm-surface-strong) 82%, transparent);
        backdrop-filter: blur(14px) saturate(140%);
        border:1px solid var(--crm-border); border-radius:var(--wh-pill);
        box-shadow:var(--wh-shadow-sm);
    }
    .wh-toolbar .search{
        flex:1; min-width:200px; display:flex; align-items:center; gap:.4rem;
        padding:.35rem .8rem; background:var(--crm-surface); border-radius:var(--wh-pill);
        border:1px solid var(--crm-border);
    }
    .wh-toolbar .search input{ flex:1; border:0; background:transparent; outline:none; font-size:.9rem; color:var(--crm-text); }
    .wh-toolbar .search input::placeholder{ color:var(--crm-muted); }
    .wh-toolbar .btn-pill{ border-radius:var(--wh-pill); padding:.35rem .85rem; font-size:.82rem; font-weight:600; }
    .wh-toolbar .filter-chip{
        display:inline-flex; align-items:center; gap:.35rem; padding:.35rem .8rem;
        border-radius:var(--wh-pill); border:1px solid var(--crm-border);
        background:var(--crm-surface); font-size:.8rem; color:var(--crm-text);
        text-decoration:none; transition:all .15s;
    }
    .wh-toolbar .filter-chip:hover{ border-color:var(--crm-accent); color:var(--crm-accent); }
    .wh-toolbar .filter-chip.active{ background:var(--crm-accent); color:#fff; border-color:transparent; }
    .wh-toolbar .filter-chip .count{ background:rgba(255,255,255,.25); padding:.05rem .4rem; border-radius:var(--wh-pill); font-size:.72rem; }

    /* ============ PRODUCT GRID ============ */
    .wh-grid{ display:grid; grid-template-columns: repeat(auto-fill, minmax(340px, 1fr)); gap:1rem; }
    .prod{
        position:relative;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:var(--wh-radius); padding:1rem 1.1rem 1.1rem;
        box-shadow:var(--wh-shadow-sm);
        transition:transform .15s ease, box-shadow .15s ease, border-color .15s;
    }
    .prod:hover{ transform:translateY(-2px); box-shadow:var(--wh-shadow-lg); border-color:color-mix(in srgb, var(--crm-accent) 40%, var(--crm-border)); }
    .prod.is-low{ border-color:color-mix(in srgb, var(--wh-amber) 45%, var(--crm-border)); }
    .prod.is-danger{ border-color:color-mix(in srgb, var(--wh-red) 45%, var(--crm-border)); }

    .prod-head{ display:flex; align-items:flex-start; gap:.9rem; margin-bottom:.75rem; }
    .brand-avatar{
        position:relative;
        width:120px; height:120px; border-radius:18px; flex-shrink:0;
        display:flex; align-items:center; justify-content:center;
        color:#fff; font-weight:800; font-size:2.4rem; letter-spacing:-.02em;
        box-shadow:0 6px 18px rgba(15,23,42,.18), inset 0 1px 0 rgba(255,255,255,.15);
        overflow:hidden; background-size:cover; background-position:center;
    }
    .brand-avatar .change-photo{
        position:absolute; inset:auto 0 0 0; padding:.25rem 0;
        background:rgba(0,0,0,.55); color:#fff; font-size:.65rem; text-align:center;
        cursor:pointer; opacity:0; transition:opacity .12s; letter-spacing:.02em;
    }
    .brand-avatar:hover .change-photo{ opacity:1; }
    .brand-avatar .change-photo:hover{ background:rgba(0,0,0,.75); }
    .brand-avatar img{ width:100%; height:100%; object-fit:cover; }
    .brand-avatar .photo-actions{
        position:absolute; top:.3rem; right:.3rem; display:flex; gap:.2rem;
        opacity:0; transition:opacity .12s;
    }
    .brand-avatar:hover .photo-actions{ opacity:1; }
    .brand-avatar .photo-actions button{
        width:24px; height:24px; border-radius:50%; border:0; padding:0;
        background:rgba(0,0,0,.55); color:#fff; font-size:.85rem; line-height:1; cursor:pointer;
    }
    .brand-avatar .photo-actions button:hover{ background:rgba(220,38,38,.85); }

    /* Inline edit имени */
    .prod-name-wrap{ display:flex; align-items:center; gap:.4rem; }
    .prod-name-edit-btn{
        flex-shrink:0; align-self:flex-start;
        border:0; background:transparent; color:var(--crm-muted); font-size:.85rem;
        padding:.15rem .4rem; border-radius:.35rem; line-height:1; cursor:pointer;
        opacity:.55; transition:opacity .12s, background .12s, color .12s;
    }
    .prod:hover .prod-name-edit-btn{ opacity:1; }
    .prod-name-edit-btn:hover{ background:var(--crm-surface); color:var(--crm-accent); opacity:1; }
    details.name-edit > summary{ list-style:none; }
    details.name-edit > summary::-webkit-details-marker{ display:none; }
    details.name-edit[open] .prod-name{ display:none; }
    details.name-edit[open] .prod-name-edit-btn{ display:none; }
    .name-edit-form{ display:none; }
    details.name-edit[open] .name-edit-form{
        display:flex; gap:.3rem; align-items:center; width:100%;
    }
    .name-edit-form input{
        flex:1; padding:.25rem .5rem; font-size:.9rem; border:1px solid var(--crm-border);
        border-radius:.4rem; background:var(--crm-surface); color:var(--crm-text); outline:none;
    }
    .name-edit-form input:focus{ border-color:var(--crm-accent); }
    .name-edit-form button{ padding:.25rem .55rem; font-size:.78rem; border-radius:.4rem; font-weight:600; }
    .prod-name{
        font-weight:700; font-size:.98rem; line-height:1.2; letter-spacing:-.01em;
        white-space:normal; word-break:break-word; overflow-wrap:anywhere;
    }
    .prod-sub{ font-size:.72rem; color:var(--crm-muted); margin-top:.15rem; }
    .prod-badge{
        margin-left:auto; padding:.15rem .6rem; border-radius:var(--wh-pill);
        font-size:.68rem; font-weight:700; text-transform:uppercase; letter-spacing:.05em;
    }
    .prod-badge.low{ background:rgba(245,158,11,.15); color:#b45309; }
    body[data-theme="night"] .prod-badge.low{ color:#fbbf24; }

    .prod-summary{
        display:flex; gap:1rem; padding:.55rem .8rem;
        background:color-mix(in srgb, var(--crm-surface) 60%, transparent);
        border-radius:.7rem; margin-bottom:.75rem;
        font-size:.78rem;
    }
    .prod-summary > div{ display:flex; flex-direction:column; }
    .prod-summary .k{ color:var(--crm-muted); font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; }
    .prod-summary .v{ font-weight:700; font-size:.95rem; letter-spacing:-.01em; }

    /* ============ SIZE PILLS ============ */
    .sizes{ display:flex; flex-wrap:wrap; gap:.4rem; }
    .size-pill{
        position:relative;
        display:inline-flex; align-items:center; gap:.4rem;
        padding:.35rem .55rem .35rem .7rem;
        background:var(--crm-surface); border:1px solid var(--crm-border);
        border-radius:var(--wh-pill); font-size:.8rem;
        cursor:pointer; user-select:none;
        transition:all .12s;
    }
    .size-pill:hover{ border-color:var(--crm-accent); transform:translateY(-1px); }
    .size-pill .sz{ color:var(--crm-muted); font-weight:600; font-size:.72rem; }
    .size-pill .qty{
        min-width:22px; height:22px; padding:0 .4rem;
        display:inline-flex; align-items:center; justify-content:center;
        border-radius:var(--wh-pill); color:#fff; font-weight:800; font-size:.75rem;
        letter-spacing:-.02em;
    }
    .size-pill.q-success .qty{ background:var(--wh-green); }
    .size-pill.q-warning .qty{ background:var(--wh-amber); }
    .size-pill.q-danger  .qty{ background:var(--wh-red); }
    .size-pill.q-warning{ border-color:color-mix(in srgb, var(--wh-amber) 50%, var(--crm-border)); }
    .size-pill.q-danger { border-color:color-mix(in srgb, var(--wh-red) 50%, var(--crm-border)); }

    /* + размер — dashed */
    .size-pill.add{ border-style:dashed; color:var(--crm-muted); }
    .size-pill.add:hover{ color:var(--crm-accent); border-color:var(--crm-accent); }

    /* details popover under the pill */
    details.pill-more > summary{ list-style:none; cursor:pointer; }
    details.pill-more > summary::-webkit-details-marker{ display:none; }
    details.pill-more[open] .size-pill{ background:color-mix(in srgb, var(--crm-accent) 12%, var(--crm-surface)); border-color:var(--crm-accent); }
    .pill-popover{
        margin-top:.5rem; padding:.75rem; width:100%; box-sizing:border-box;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:.8rem; box-shadow:var(--wh-shadow-md);
        animation:pop .12s ease-out;
    }
    .pill-popover .qmini{
        display:flex; align-items:center; justify-content:space-between; gap:.5rem;
        margin-bottom:.5rem; padding-bottom:.5rem;
        border-bottom:1px dashed var(--crm-border);
    }
    .pill-popover .qmini .label{ font-size:.7rem; color:var(--crm-muted); text-transform:uppercase; letter-spacing:.06em; }
    .pill-popover .qbig{ font-size:1.6rem; font-weight:800; letter-spacing:-.02em; line-height:1; }
    .pill-popover .qbtn{
        width:32px; height:32px; border-radius:50%;
        display:inline-flex; align-items:center; justify-content:center;
        border:1px solid var(--crm-border); background:var(--crm-surface);
        font-size:1.1rem; font-weight:700; line-height:1;
    }
    .pill-popover .qbtn.plus{ color:var(--wh-green); border-color:var(--wh-green); }
    .pill-popover .qbtn.minus{ color:var(--wh-red); border-color:var(--wh-red); }
    .pill-popover .frow{ display:flex; align-items:center; gap:.5rem; margin-bottom:.4rem; font-size:.8rem; }
    .pill-popover .frow label{ min-width:82px; color:var(--crm-muted); margin:0; font-size:.72rem; }
    .pill-popover .frow input{
        flex:1; padding:.3rem .55rem; font-size:.85rem;
        border:1px solid var(--crm-border); background:var(--crm-surface);
        border-radius:.5rem; color:var(--crm-text); outline:none;
    }
    .pill-popover .frow input:focus{ border-color:var(--crm-accent); }
    .pill-popover .actions{ display:flex; gap:.4rem; margin-top:.5rem; }
    .pill-popover .actions button{ flex:1; padding:.35rem .5rem; font-size:.78rem; border-radius:.5rem; font-weight:600; }

    /* ============ FAB ============ */
    .fab{
        position:fixed; right:1.5rem; bottom:1.5rem; z-index:20;
        width:56px; height:56px; border-radius:50%;
        background:linear-gradient(135deg, var(--crm-accent), color-mix(in srgb, var(--crm-accent) 60%, #8b5cf6));
        color:#fff; border:0; font-size:1.6rem; font-weight:300; line-height:1;
        box-shadow:0 12px 28px rgba(79,70,229,.35), 0 4px 10px rgba(79,70,229,.25);
        display:flex; align-items:center; justify-content:center;
        transition:transform .15s ease, box-shadow .15s ease;
    }
    .fab:hover{ transform:scale(1.05) rotate(90deg); }
    .new-product-panel{
        position:fixed; right:1.5rem; bottom:5.5rem; z-index:20;
        width:min(420px, calc(100vw - 3rem));
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:var(--wh-radius); box-shadow:var(--wh-shadow-lg);
        padding:1rem;
    }
    .new-product-panel h6{ margin:0 0 .5rem; font-size:.9rem; letter-spacing:-.01em; }

    /* ============ TIMELINE MOVES ============ */
    .moves-title{
        display:inline-flex; align-items:center; gap:.4rem;
        padding:.4rem .9rem; border-radius:var(--wh-pill);
        background:var(--crm-surface); border:1px solid var(--crm-border);
        font-size:.8rem; font-weight:600; cursor:pointer;
    }
    .moves-title::-webkit-details-marker{ display:none; }
    .moves-timeline{ margin-top:1rem; position:relative; padding-left:1.2rem; }
    .moves-timeline::before{ content:""; position:absolute; left:.35rem; top:.4rem; bottom:.4rem; width:2px; background:var(--crm-border); border-radius:2px; }
    .move{ position:relative; padding:.35rem 0 .35rem .3rem; font-size:.8rem; }
    .move::before{ content:""; position:absolute; left:-1.05rem; top:.55rem; width:.7rem; height:.7rem; border-radius:50%; background:var(--crm-surface); border:2px solid var(--crm-muted); }
    .move.in::before, .move.replenish::before{ border-color:var(--wh-green); }
    .move.out::before, .move.return::before{ border-color:var(--wh-red); }
    .move .m-when{ color:var(--crm-muted); font-size:.72rem; }
    .move .m-delta{ font-weight:800; }
    .move .m-delta.pos{ color:var(--wh-green); } .move .m-delta.neg{ color:var(--wh-red); }
</style>

    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $state = fn ($i) => (int) $i->available <= 0 ? 'danger' : ($i->is_low ? 'warning' : 'success');

        // Стабильный «цвет бренда» из хэша названия
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

        $lowFilter = request('low') == '1';
        $productsView = $products;
        if ($lowFilter) {
            $productsView = $productsView->filter(fn ($p) => $p['low'])->values();
        }
        $exportParams = array_filter(['q' => $q ?: null, 'low' => $lowFilter ? '1' : null]);
        $lowToggleParams = array_filter(['q' => $q ?: null, 'low' => $lowFilter ? null : '1']);
        $isEmpty = $productsView->isEmpty();
        $hasMovements = $movements->isNotEmpty();
        $lowCount = $products->filter(fn ($p) => $p['low'])->count();

        $moveLabels = [
            'in' => 'Приход', 'in_adjust' => 'Корректировка прихода',
            'in_reversal' => 'Откат прихода', 'out' => 'Продажа',
            'out_reversal' => 'Возврат на склад', 'return' => 'Возврат продажи',
            'reserve' => 'Резерв', 'reserve_release' => 'Снятие резерва',
            'replenish' => 'Пополнение', 'adjust' => 'Корректировка',
        ];
    @endphp

    <div class="mb-3">
        <h4 class="mb-0" style="letter-spacing:-.02em">Склад</h4>
        <div class="text-muted small">кроссовки · управление позициями</div>
    </div>

    {{-- HERO STATS --}}
    <div class="wh-hero">
        <div class="wh-stat stat-products">
            <div class="l">Товаров</div>
            <div class="v">{{ $products->count() }}</div>
            <div class="s">уникальных моделей</div>
        </div>
        <div class="wh-stat stat-units">
            <div class="l">Пар на складе</div>
            <div class="v">{{ $totalUnits }}</div>
            <div class="s">штук всего</div>
        </div>
        <div class="wh-stat stat-available">
            <div class="l">Доступно</div>
            <div class="v">{{ $products->sum('available') }}</div>
            <div class="s">без учёта резерва</div>
        </div>
        <div class="wh-stat stat-value">
            <div class="l">Стоимость</div>
            <div class="v">{{ $money($stockValue) }} ₽</div>
            <div class="s">в ценах продажи</div>
        </div>
    </div>

    {{-- TOOLBAR --}}
    <form method="GET" action="{{ route('warehouse.index') }}" class="wh-toolbar">
        <div class="search">
            <span aria-hidden="true" style="color:var(--crm-muted)">🔎</span>
            <input type="search" name="q" value="{{ $q }}" placeholder="Найти по бренду, модели или размеру…">
        </div>
        @if($lowFilter)<input type="hidden" name="low" value="1">@endif
        <button class="btn btn-primary btn-pill" type="submit">Найти</button>
        @if($q !== '' || $lowFilter)
            <a class="filter-chip" href="{{ route('warehouse.index') }}">Сброс</a>
        @endif
        <a class="filter-chip {{ $lowFilter ? 'active' : '' }}" href="{{ route('warehouse.index', $lowToggleParams) }}">
            Заканчивается
            @if($lowCount > 0)<span class="count">{{ $lowCount }}</span>@endif
        </a>
        <a class="filter-chip" href="{{ route('warehouse.export', $exportParams) }}">Экспорт CSV</a>
    </form>

    {{-- PRODUCTS --}}
    @if($isEmpty)
        <div class="alert alert-light border" style="border-radius:var(--wh-radius)">
            @if($q !== '' || $lowFilter)
                Ничего не нашли по запросу. <a href="{{ route('warehouse.index') }}">Сбросить фильтры</a>.
            @else
                На складе пока пусто. Закупки попадут сюда автоматически при переходе на стадию «Получено / На складе», либо нажмите «+» справа внизу.
            @endif
        </div>
    @else
        <div class="wh-grid">
            @foreach($productsView as $prod)
                @php
                    $hasDanger = $prod['sizes']->contains(fn ($i) => (int) $i->available <= 0);
                    $prodClass = $prod['low'] ? ($hasDanger ? 'is-danger' : 'is-low') : '';
                @endphp
                <article class="prod {{ $prodClass }}">
                    <div class="prod-head">
                        <label class="brand-avatar" style="background:{{ empty($prod['image_url']) ? $brandColor($prod['brand']) : 'transparent' }}" title="Нажмите чтобы {{ empty($prod['image_url']) ? 'загрузить' : 'заменить' }} фото">
                            @if(!empty($prod['image_url']))
                                <img src="{{ $prod['image_url'] }}" alt="{{ $prod['name'] }}">
                            @else
                                {{ $brandLetter($prod['brand']) }}
                            @endif
                            <form method="POST" action="{{ route('warehouse.product.photo.upload', $prod['entity']) }}" enctype="multipart/form-data" style="position:absolute;inset:0;margin:0;">
                                @csrf
                                <input type="file" name="photo" accept="image/*" onchange="this.form.submit()" style="position:absolute;inset:0;opacity:0;cursor:pointer;">
                            </form>
                            @if(!empty($prod['image_url']))
                                <div class="photo-actions">
                                    <form method="POST" action="{{ route('warehouse.product.photo.delete', $prod['entity']) }}" onsubmit="return confirm('Удалить фото?')">
                                        @csrf @method('DELETE')
                                        <button type="submit" title="Удалить фото">×</button>
                                    </form>
                                </div>
                            @endif
                            <div class="change-photo">Сменить фото</div>
                        </label>
                        <div style="min-width:0;flex:1">
                            <details class="name-edit">
                                <div class="prod-name-wrap">
                                    <div class="prod-name" title="{{ $prod['name'] }}">{{ $prod['name'] }}</div>
                                    <summary class="prod-name-edit-btn" title="Изменить название">✎</summary>
                                </div>
                                <form method="POST" action="{{ route('warehouse.product.update', $prod['entity']) }}" class="name-edit-form">
                                    @csrf @method('PATCH')
                                    <input type="text" name="custom_name" value="{{ $prod['custom_name'] ?? $prod['auto_name'] }}" maxlength="255" placeholder="{{ $prod['auto_name'] }}">
                                    <button type="submit" class="btn btn-primary btn-sm">OK</button>
                                </form>
                            </details>
                            <div class="prod-sub">{{ $prod['brand'] ?: '—' }} · {{ $prod['sizes']->count() }} разм.</div>
                        </div>
                        @if($prod['low'])
                            <span class="prod-badge low">заканчивается</span>
                        @endif
                    </div>

                    <div class="prod-summary">
                        <div><span class="k">Пар</span><span class="v">{{ $prod['total'] }}</span></div>
                        <div><span class="k">Доступно</span><span class="v">{{ $prod['available'] }}</span></div>
                        @if($prod['reserved'] > 0)
                            <div><span class="k">Резерв</span><span class="v">{{ $prod['reserved'] }}</span></div>
                        @endif
                        @if($prod['value'] > 0)
                            <div><span class="k">Стоимость</span><span class="v">{{ $money($prod['value']) }} ₽</span></div>
                        @endif
                    </div>

                    <div class="sizes">
                        @foreach($prod['sizes'] as $i)
                            @php($st = $state($i))
                            <details class="pill-more">
                                <summary>
                                    <div class="size-pill q-{{ $st }}">
                                        <span class="sz">{{ $i->size !== '' ? 'р.'.$i->size : '—' }}</span>
                                        <span class="qty">{{ (int) $i->available }}</span>
                                    </div>
                                </summary>
                                <div class="pill-popover">
                                    <div class="qmini">
                                        <div>
                                            <div class="label">Размер {{ $i->size !== '' ? $i->size : '—' }}</div>
                                            <div class="qbig">{{ (int) $i->quantity }} <span style="font-size:.7rem;color:var(--crm-muted);font-weight:500">пар</span></div>
                                            @if($i->reserved > 0)<div style="font-size:.7rem;color:var(--crm-muted)">резерв: {{ $i->reserved }}</div>@endif
                                        </div>
                                        <div class="d-flex gap-2">
                                            <form method="POST" action="{{ route('warehouse.replenish', $i) }}">
                                                @csrf<input type="hidden" name="delta" value="-1">
                                                <button class="qbtn minus" title="−1 пара">−</button>
                                            </form>
                                            <form method="POST" action="{{ route('warehouse.replenish', $i) }}">
                                                @csrf<input type="hidden" name="delta" value="1">
                                                <button class="qbtn plus" title="+1 пара">+</button>
                                            </form>
                                        </div>
                                    </div>

                                    <form method="POST" action="{{ route('warehouse.update', $i) }}">
                                        @csrf @method('PATCH')
                                        <div class="frow"><label>Остаток</label><input type="number" name="quantity" min="0" value="{{ $i->quantity }}"></div>
                                        @if($isHead)
                                            <div class="frow"><label>Цена ₽</label><input type="number" step="0.01" min="0" name="sale_price" value="{{ $i->sale_price }}" placeholder="—"></div>
                                            <div class="frow"><label>Мин. остаток</label><input type="number" min="0" name="low_stock_threshold" value="{{ $i->low_stock_threshold }}"></div>
                                        @endif
                                        <div class="actions">
                                            <button type="submit" class="btn btn-primary">Сохранить</button>
                                        </div>
                                    </form>
                                    @if($isHead)
                                        <form method="POST" action="{{ route('warehouse.destroy', $i) }}" onsubmit="return confirm('Удалить размер?')" class="mt-2">
                                            @csrf @method('DELETE')
                                            <button type="submit" class="btn btn-outline-danger w-100" style="font-size:.78rem;padding:.35rem .5rem;border-radius:.5rem">Удалить размер</button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        @endforeach

                        {{-- + размер --}}
                        <details class="pill-more">
                            <summary>
                                <div class="size-pill add">
                                    <span class="sz">＋</span>
                                    <span class="sz" style="color:inherit">размер</span>
                                </div>
                            </summary>
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
                </article>
            @endforeach
        </div>
    @endif

    {{-- FAB + panel --}}
    <details class="wh-details">
        <summary class="fab" title="Добавить товар">+</summary>
        <div class="new-product-panel">
            <h6>Новый товар</h6>
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
    <style>
        .wh-details > summary::-webkit-details-marker{ display:none; }
        .wh-details > summary{ list-style:none; }
        .wh-details:not([open]) .new-product-panel{ display:none; }
    </style>

    {{-- MOVEMENTS TIMELINE --}}
    @if($hasMovements)
        <details class="mt-4 wh-details">
            <summary class="moves-title">📜 Последние движения · {{ $movements->count() }}</summary>
            <div class="moves-timeline">
                @foreach($movements as $m)
                    <div class="move {{ $m->type }}">
                        <span class="m-when">{{ optional($m->created_at)->format('d.m H:i') }}</span>
                        · <b>{{ $m->item?->display_name ?? '—' }}</b>
                        · {{ $moveLabels[$m->type] ?? $m->type }}
                        · <span class="m-delta {{ $m->quantity < 0 ? 'neg' : 'pos' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</span>
                        @if($m->note) <span class="text-muted">— {{ $m->note }}</span>@endif
                    </div>
                @endforeach
            </div>
        </details>
    @endif
@endsection
