@extends('layouts.app')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $pendingCount = $rows->count();
    $linkedCount = $linked->count();
    $skippedCount = $skipped->count();
@endphp
<style>
    .us-stat{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:12px; padding:.6rem 1rem; min-width:150px; }
    .us-stat .l{ font-size:.72rem; color:var(--crm-muted); }
    .us-stat .v{ font-size:1.35rem; font-weight:700; }
    .us-card{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:12px; padding:.8rem .95rem; margin-bottom:.8rem; }
    .us-card .head{ display:flex; flex-wrap:wrap; gap:.3rem .8rem; align-items:baseline; margin-bottom:.5rem; }
    .us-card .title{ font-weight:700; font-size:1.02rem; }
    .us-sug{ display:flex; flex-wrap:wrap; align-items:center; gap:.35rem .7rem; padding:.4rem .5rem; border-top:1px dashed var(--crm-border); }
    .us-sug:first-of-type{ border-top:0; }
    .us-sug .nm{ flex:1; min-width:220px; }
    .us-sug.best{ background:color-mix(in srgb, #10b981 9%, transparent); border-radius:8px; }
    .us-size{ display:inline-block; padding:.05rem .45rem; border-radius:999px; border:1px solid var(--crm-border); font-weight:700; font-size:.82rem; }
    .us-other{ display:flex; flex-wrap:wrap; gap:.4rem; align-items:center; margin-top:.55rem; }
    .us-other input{ flex:1; min-width:240px; }
</style>

<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
    <div>
        <h4 class="mb-0" style="letter-spacing:-.02em">⚠ Продажи без списания со склада</h4>
        <div class="text-muted small">продажа отмечена «Продано», но пара со склада не списалась — склад показывает пары, которых уже нет</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">← К складу</a>
</div>

<div class="alert alert-light border small mb-3">
    Продажи от <b>07.07.2026 14:34</b> были загружены из файла истории продаж — в файле не было размеров, поэтому пары
    к складу не привязались и остатки не уменьшились. Для каждой продажи выберите пару, которую на самом деле продали:
    она <b>спишется со склада</b>. Дата продажи, выручка и закупочная цена из файла не меняются.
    Если такой пары в CRM не было или её уже списали вручную — «Списывать не нужно».
    Ошиблись — внизу страницы любую строку можно отменить.
</div>

<div class="d-flex gap-2 flex-wrap mb-3">
    <div class="us-stat"><div class="l">Осталось разобрать</div><div class="v {{ $pendingCount ? 'text-danger' : 'text-success' }}">{{ $pendingCount }}</div></div>
    <div class="us-stat"><div class="l">Списано при сверке</div><div class="v">{{ $linkedCount }}</div></div>
    <div class="us-stat"><div class="l">Списывать не нужно</div><div class="v">{{ $skippedCount }}</div></div>
    @if($negative->isNotEmpty())
        <div class="us-stat"><div class="l">Отрицательных остатков</div><div class="v text-danger">{{ $negative->count() }}</div></div>
    @endif
</div>

<datalist id="wh-items">
    @foreach($inStock as $wi)
        <option value="#{{ $wi->id }} · {{ $wi->display_name }} · на складе {{ $wi->quantity }}"></option>
    @endforeach
</datalist>

@forelse($rows as $row)
    @php
        $deal = $row['deal'];
        $suggestions = $row['suggestions'];
    @endphp
    <div class="us-card" id="sale-{{ $deal->id }}">
        <div class="head">
            <span class="title">{{ $deal->title }}</span>
            <a class="small" href="{{ route('deals.show', $deal) }}">#{{ $deal->id }}</a>
            <span class="small text-muted">продано {{ optional($deal->stock_deducted_at)->format('d.m.Y H:i') }}@if($deal->amount !== null) · {{ $money($deal->amount) }} ₽@endif</span>
            @if($isHead && $deal->sold_unit_cost !== null)
                <span class="small text-muted">· закуп по файлу <b>{{ $money($deal->sold_unit_cost) }} ₽</b></span>
            @endif
        </div>

        @forelse($suggestions as $s)
            @php
                $wi = $s['item'];
            @endphp
            <div class="us-sug {{ $s['same_cost'] ? 'best' : '' }}">
                <div class="nm">
                    {{ trim($wi->brand.' '.$wi->model) }}
                    <span class="us-size">р. {{ $wi->size !== '' ? $wi->size : '—' }}</span>
                    <span class="small text-muted ms-1">на складе {{ $wi->quantity }}</span>
                    @if($isHead && $wi->avg_cost !== null)
                        <span class="small text-muted">· закуп {{ $money($wi->avg_cost) }} ₽</span>
                    @endif
                    @if($s['same_cost'])
                        <span class="badge text-bg-success ms-1">закупочная цена совпала</span>
                    @endif
                </div>
                <form method="POST" action="{{ route('warehouse.unlinked.link', $deal) }}" class="m-0">
                    @csrf
                    <input type="hidden" name="item_id" value="{{ $wi->id }}">
                    <button class="btn btn-sm btn-success">Списать эту пару</button>
                </form>
            </div>
        @empty
            <div class="small text-muted">Похожих пар в наличии нет — найдите пару вручную ниже или отметьте «Списывать не нужно».</div>
        @endforelse

        <div class="us-other">
            <form method="POST" action="{{ route('warehouse.unlinked.link', $deal) }}" class="d-flex flex-wrap gap-2 align-items-center m-0" style="flex:1; min-width:260px">
                @csrf
                <input type="text" name="item_ref" list="wh-items" class="form-control form-control-sm" placeholder="Другая пара: начните вводить модель или артикул…" autocomplete="off" data-scan="own">
                <button class="btn btn-sm btn-outline-success">Списать</button>
            </form>
            <form method="POST" action="{{ route('warehouse.unlinked.skip', $deal) }}" class="m-0" onsubmit="return confirm('Не списывать пару по этой продаже? (пары не было в CRM или её уже списали вручную)')">
                @csrf
                <button class="btn btn-sm btn-outline-secondary">Списывать не нужно</button>
            </form>
        </div>
    </div>
@empty
    <div class="alert alert-success">✓ Все продажи привязаны к складу — разбирать нечего.</div>
@endforelse

@if($negative->isNotEmpty())
    <div class="card shadow-sm mb-3 border-danger-subtle">
        <div class="card-header fw-semibold">Отрицательные остатки</div>
        <div class="card-body small">
            <div class="text-muted mb-2">Меньше нуля пар не бывает — это ошибка учёта (например, откатили приход закупки, а пару уже продали). «Обнулить» ставит остаток 0.</div>
            @foreach($negative as $ni)
                @php
                    $moves = $negativeMoves[$ni->id] ?? collect();
                @endphp
                <div class="d-flex flex-wrap align-items-center gap-2 py-2 border-top">
                    <div style="flex:1; min-width:240px">
                        <b>{{ $ni->display_name }}</b> · остаток <b class="text-danger">{{ $ni->quantity }}</b>
                        @foreach($moves as $mv)
                            <div class="text-muted">{{ $mv->created_at?->format('d.m.Y H:i') }} · {{ $mv->quantity > 0 ? '+' : '' }}{{ $mv->quantity }} · {{ $mv->note }}@if($mv->user) · {{ $mv->user->name }}@endif</div>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('warehouse.item.zero', $ni) }}" class="m-0" onsubmit="return confirm('Поставить остаток 0?')">
                        @csrf
                        <button class="btn btn-sm btn-outline-danger">Обнулить</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($linkedCount)
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Списано при сверке · {{ $linkedCount }}</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle small">
                    <tbody>
                    @foreach($linked as $ld)
                        <tr>
                            <td class="ps-3"><a href="{{ route('deals.show', $ld) }}">#{{ $ld->id }}</a></td>
                            <td>{{ $ld->title }}</td>
                            <td>→ <b>{{ $ld->warehouseItem?->display_name ?? '—' }}</b></td>
                            <td class="text-muted">{{ $ld->stock_linked_at?->format('d.m H:i') }}</td>
                            <td class="text-end pe-3">
                                <form method="POST" action="{{ route('warehouse.unlinked.undo', $ld) }}" class="m-0" onsubmit="return confirm('Отменить списание? Пара вернётся на склад, продажа — в список.')">
                                    @csrf
                                    <button class="btn btn-sm btn-link p-0">Отменить</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

@if($skippedCount)
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">Списывать не нужно · {{ $skippedCount }}</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle small">
                    <tbody>
                    @foreach($skipped as $sd)
                        <tr>
                            <td class="ps-3"><a href="{{ route('deals.show', $sd) }}">#{{ $sd->id }}</a></td>
                            <td>{{ $sd->title }}</td>
                            <td class="text-muted">{{ optional($sd->stock_deducted_at)->format('d.m.Y') }}</td>
                            <td class="text-end pe-3">
                                <form method="POST" action="{{ route('warehouse.unlinked.undo', $sd) }}" class="m-0">
                                    @csrf
                                    <button class="btn btn-sm btn-link p-0">Вернуть в список</button>
                                </form>
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

{{-- После кнопки страница возвращается на то же место, а результат показывается всплывашкой снизу. --}}
@if(session('status') || $errors->any())
    <div id="us-toast" class="alert {{ $errors->any() ? 'alert-danger' : 'alert-success' }} shadow" style="position:fixed; left:50%; bottom:16px; transform:translateX(-50%); z-index:1080; max-width:min(560px, calc(100vw - 32px)); margin:0">
        {{ $errors->any() ? $errors->first() : session('status') }}
    </div>
@endif
<script>
(function () {
    document.querySelectorAll('form').forEach(function (f) {
        f.addEventListener('submit', function () { try { sessionStorage.setItem('usScrollY', String(window.scrollY)); } catch (e) {} });
    });
    try {
        var y = sessionStorage.getItem('usScrollY');
        if (y !== null) { sessionStorage.removeItem('usScrollY'); window.scrollTo(0, parseInt(y, 10) || 0); }
    } catch (e) {}
    var t = document.getElementById('us-toast');
    if (t) setTimeout(function () { t.style.transition = 'opacity .4s'; t.style.opacity = '0'; setTimeout(function () { t.remove(); }, 450); }, 6000);
})();
</script>
@endsection
