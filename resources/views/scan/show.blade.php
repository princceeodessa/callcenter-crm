@extends('layouts.app')

@section('content')
<style>
    .sc-wrap{ max-width:1000px; margin:0 auto; }
    .sc-scan{ display:flex; gap:.5rem; align-items:center; background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:999px; padding:.5rem 1rem; margin-bottom:1rem; box-shadow:var(--crm-shadow); }
    .sc-scan input{ flex:1; border:0; background:transparent; outline:none; font-size:1.05rem; color:var(--crm-text); }
    .sc-card{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:1rem; padding:1rem; margin-bottom:1rem; box-shadow:var(--crm-shadow); }
    .sc-head{ display:flex; gap:1rem; align-items:flex-start; flex-wrap:wrap; }
    .sc-photo{ width:120px; height:120px; border-radius:12px; overflow:hidden; background:var(--crm-surface); border:1px solid var(--crm-border); display:flex; align-items:center; justify-content:center; font-size:2.2rem; font-weight:800; color:var(--crm-muted); flex-shrink:0; }
    .sc-photo img{ width:100%; height:100%; object-fit:cover; }
    .sc-name{ font-size:1.3rem; font-weight:800; line-height:1.2; }
    .sc-art{ font-family:ui-monospace,Menlo,monospace; color:var(--crm-muted); }
    .sc-sizes td, .sc-sizes th{ vertical-align:middle; }
    .sc-sizes tr.hit{ background:rgba(34,197,94,.14); }
    .sc-mark{ border-radius:.8rem; padding:.6rem .9rem; margin-bottom:1rem; }
</style>

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $cardsCount = $cards->count();
    $markStatus = $mark ? ($mark->status === 'sold' ? 'продан' : 'на складе') : null;
    $markDeal = $mark?->deal;
@endphp

<div class="sc-wrap">
    <form method="GET" action="{{ route('scan') }}" class="sc-scan">
        <span style="font-size:1.2rem">🔎</span>
        <input type="search" name="code" id="scanInput" value="" placeholder="Сканируйте следующий товар или введите артикул…" autofocus autocomplete="off">
        <button class="btn btn-sm btn-primary">Найти</button>
    </form>

    @if($raw !== '')
        <div class="text-muted small mb-2">Скан: <span class="sc-art">{{ $raw }}</span>@if($code !== $raw) → <span class="sc-art">{{ $code }}</span> <span title="Сканер печатал в русской раскладке — исправлено">(раскладка исправлена)</span>@endif</div>
    @endif

    @if($markHr)
        <div class="sc-mark {{ $mark ? ($mark->status === 'sold' ? 'bg-warning-subtle' : 'bg-success-subtle') : 'bg-secondary-subtle' }}">
            <b>Честный знак:</b> <span class="sc-art">{{ $markHr }}</span>
            @if($mark)
                · {{ $markStatus }}
                @if($markDeal)
                    · <a href="{{ route('deals.show', $markDeal->id) }}">сделка #{{ $markDeal->id }}</a>
                @endif
            @else
                · этого кода нет в CRM
            @endif
        </div>
    @endif

    @if($raw !== '' && $cardsCount === 0)
        <div class="sc-card text-center">
            <div class="fs-5 fw-semibold mb-1">Товар не найден</div>
            <div class="text-muted mb-3">По коду <span class="sc-art">{{ $code }}</span> ничего нет на складе.</div>
            <a class="btn btn-outline-secondary" href="{{ route('warehouse.index') }}">📦 Открыть склад</a>
            <a class="btn btn-outline-secondary" href="{{ route('sale.quick') }}">💵 Быстрая продажа</a>
        </div>
    @endif

    @if($cardsCount > 1)
        <div class="text-muted small mb-2">Нашлось похожих: {{ $cardsCount }}</div>
    @endif

    @foreach($cards as $card)
        @php
            $p = $card['product'];
            $items = $card['items'];
            $total = (int) $items->sum(fn ($i) => max(0, (int) $i->available));
            $initial = mb_strtoupper(mb_substr((string) $p->brand, 0, 1)) ?: '?';
            $image = $p->exists ? $p->image_url : null;
            $saleQuery = $p->article ?: trim($p->brand.' '.$p->model);
        @endphp
        <div class="sc-card">
            <div class="sc-head mb-3">
                <div class="sc-photo">
                    @if($image)
                        <img src="{{ $image }}" alt="">
                    @else
                        {{ $initial }}
                    @endif
                </div>
                <div class="flex-grow-1">
                    <div class="sc-name">{{ $p->display_name }}</div>
                    @if($p->article)
                        <div class="sc-art">{{ $p->article }}</div>
                    @endif
                    <div class="mt-1">В наличии: <b>{{ $total }}</b> пар</div>
                    <div class="d-flex gap-2 flex-wrap mt-2">
                        <a class="btn btn-success" href="{{ route('sale.quick', ['q' => $saleQuery]) }}">💵 Продать</a>
                        @if($p->exists)
                            <a class="btn btn-outline-primary" href="{{ route('print.labels', ['product' => $p->id, 'type' => 'price']) }}">🏷 Ценники</a>
                            <a class="btn btn-outline-primary" href="{{ route('print.labels', ['product' => $p->id, 'type' => 'mark']) }}">Честный знак</a>
                        @endif
                        <a class="btn btn-outline-secondary" href="{{ route('warehouse.index', ['q' => $p->model]) }}">📦 На складе</a>
                    </div>
                </div>
            </div>

            <table class="table table-sm sc-sizes mb-0">
                <thead>
                    <tr class="small text-muted"><th>Размер</th><th class="text-end">Доступно</th><th class="text-end">Резерв</th><th class="text-end">Цена</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach($items as $item)
                        @php
                            $avail = (int) $item->available;
                            $rowClass = $highlightItemId === $item->id ? 'hit' : '';
                        @endphp
                        <tr class="{{ $rowClass }}">
                            <td><b>{{ $item->size !== '' ? $item->size : '—' }}</b>@if($rowClass) <span class="badge text-bg-success">эта пара</span>@endif</td>
                            <td class="text-end">{{ $avail }}</td>
                            <td class="text-end text-muted">{{ (int) $item->reserved ?: '' }}</td>
                            <td class="text-end text-nowrap">{{ $item->sale_price !== null ? $money($item->sale_price).' ₽' : '—' }}</td>
                            <td class="text-end">
                                @if($avail > 0)
                                    <a class="btn btn-sm btn-success" href="{{ route('sale.quick', ['q' => $saleQuery, 'item' => $item->id]) }}">Продать</a>
                                @else
                                    <span class="text-muted small">нет</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endforeach
</div>
@endsection
