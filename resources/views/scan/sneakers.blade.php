@extends('layouts.sneakers')
{{--
    «Скан» кроссовочного отдела — каркас «Нордика» (прототип: PAGES.scan, снимок 04). Большое поле для сканера, «Только информация»
    (без него один найденный товар сразу открывает продажу — это делает ScanController). Здесь — результат: найдено / не найдено,
    карточка товара (наличие, продано, склад в ценах продажи), кнопки и таблица размеров с «Продать». Закуп и наценка — руководителю.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $cardsCount = $cards->count();
    $markDeal = $mark?->deal;
@endphp

@section('title', 'Скан')
@section('ns_sub', 'сканируйте товар — откроется продажа')
@section('ns_no_search', '1')

@section('content')
<div class="ns-scanbox">
    <form method="GET" action="{{ route('scan') }}" id="nsScanForm">
        <div class="ns-scanin">{!! $ui::icon('scan') !!}<input type="text" name="code" id="scanInput" class="ns-mono" data-scan="own" value="{{ $raw }}" placeholder="Сканируйте товар или введите артикул" autocomplete="off" autofocus aria-label="Код товара"><button class="ns-btn ns-primary" type="submit">Найти</button></div>
        <input type="hidden" name="info" id="nsScanInfoV" value="{{ request()->boolean('info') ? 1 : '' }}">
        <div class="ns-scan-opts"><button class="ns-chipf @if(request()->boolean('info')) ns-on @endif" type="button" id="nsScanInfo"><span class="ns-sw"></span>Только информация</button><span>Без галочки после скана сразу откроется продажа первого размера в наличии</span></div>
    </form>

    @if($raw === '')
        <div class="ns-panel ns-empty">{!! $ui::icon('scan') !!}<b>Курсор уже в поле</b>Поднесите сканер к штрих-коду — код введётся сам</div>
    @else
        @if($markHr)
            <div @class(['ns-resline', 'ns-ok' => $mark && $mark->status !== 'sold', 'ns-warn' => $mark && $mark->status === 'sold', 'ns-err' => ! $mark])>{!! $ui::icon('tag') !!}
                <span>Честный знак: <span class="ns-mono">{{ $markHr }}</span> ·
                    @if($mark){{ $mark->status === 'sold' ? 'продан' : 'на складе' }}@if($markDeal) · <a href="{{ route('deals.show', $markDeal->id) }}">сделка #{{ $markDeal->id }}</a>@endif
                    @else этого кода нет в CRM @endif</span>
            </div>
        @endif

        @if($cardsCount === 0)
            <div class="ns-resline ns-err">{!! $ui::icon('warn') !!}<span>Код <span class="ns-mono">{{ $code }}</span> не найден на складе@if($code !== $raw) <small>(сканер печатал в русской раскладке — исправлено)</small>@endif</span></div>
            <div class="ns-panel ns-empty">{!! $ui::icon('box') !!}<b>Товар не найден</b>Проверьте код или заведите новый товар<br>
                <a class="ns-btn ns-primary" style="margin-top:14px" href="{{ route('warehouse.index', ['new' => $code]) }}">{!! $ui::icon('plus', 'ns-sm') !!}Завести товар</a>
            </div>
        @elseif($similar)
            <div class="ns-resline ns-warn">{!! $ui::icon('search') !!}<span>Точно по коду <span class="ns-mono">{{ $code }}</span> ничего нет — похожих: {{ $cardsCount }}</span></div>
        @else
            <div class="ns-resline ns-ok">{!! $ui::icon('check') !!}<span>Найдено по коду <span class="ns-mono">{{ $code }}</span>@if($code !== $raw) <small>(раскладка исправлена)</small>@endif</span></div>
        @endif

        @foreach($cards as $card)
            @php
                $p = $card['product'];
                $items = $card['items'];
                $total = (int) $items->sum(fn ($i) => max(0, (int) $i->available));
                $image = $p->exists ? $p->image_url : null;
                $saleQuery = $p->article ?: trim($p->brand.' '.$p->model);
                $marks = $card['marks'];
                $marksTotal = (int) $marks->sum();
                $facts = array_values(array_filter([
                    $p->category ? ($categoryOptions[$p->category] ?? $p->category) : null,
                    $p->gender ? ($genderOptions[$p->gender] ?? $p->gender) : null,
                    $p->season ? ($seasonOptions[$p->season] ?? $p->season) : null,
                ]));
                $facts = array_map(fn ($f) => preg_replace('/^[^\p{L}]+/u', '', $f), $facts);
                $stockValue = (float) $items->sum(fn ($i) => max(0, (int) $i->quantity) * (float) ($i->sale_price ?? 0));
                $brand = $ui::brandOf($p->display_name);
            @endphp
            <div class="ns-panel ns-sc-card">
                <div class="ns-res-card">
                    <div class="ns-pimg ns-lg">@if($image)<img src="{{ $image }}" alt="" data-b="{{ $brand }}">@else<span class="ns-phb">{{ mb_strtoupper(mb_substr($brand, 0, 2)) }}</span>@endif</div>
                    <div class="ns-grow">
                        <h2>{{ $p->display_name }}</h2>
                        <div class="ns-part">@if($p->article)<span class="ns-mono">{{ $p->article }}</span>·@endif<span>{{ $brand }}</span>@foreach($facts as $f)·<span>{{ $f }}</span>@endforeach</div>
                        <div class="ns-stats-line">
                            <div>В наличии<b>{{ $total }} пар</b></div>
                            <div>Продано всего<b>{{ $card['soldTotal'] }}</b></div>
                            <div>Склад в ценах продажи<b>{{ $rub($stockValue) }}</b></div>
                        </div>
                        @if($marksTotal > 0)<div class="ns-sc-marks">{!! $ui::icon('tag', 'ns-sm') !!}Кодов «Честного знака» на складе: {{ $marksTotal }}</div>@endif
                        <div class="ns-acts">
                            <a class="ns-btn ns-primary" href="{{ route('sale.quick', ['q' => $saleQuery]) }}">{!! $ui::icon('sell', 'ns-sm') !!}Продать</a>
                            @if($p->exists)
                                <a class="ns-btn" href="{{ route('print.labels', ['product' => $p->id, 'type' => 'price']) }}">{!! $ui::icon('tag', 'ns-sm') !!}Ценники</a>
                                <a class="ns-btn" href="{{ route('print.labels', ['product' => $p->id, 'type' => 'mark']) }}">{!! $ui::icon('scan', 'ns-sm') !!}Честный знак</a>
                            @endif
                            <a class="ns-btn" href="{{ route('warehouse.index', ['q' => $p->article ?: $p->model]) }}">{!! $ui::icon('box', 'ns-sm') !!}На складе</a>
                        </div>
                    </div>
                </div>
                <table class="ns-tbl">
                    <thead><tr><th>Размер</th><th class="ns-num">Доступно</th><th class="ns-num">Резерв</th><th class="ns-num">Цена</th>@if($seesCost)<th class="ns-num">Закуп</th><th class="ns-num">Наценка</th>@endif<th class="ns-num">ЧЗ</th><th></th></tr></thead>
                    <tbody>
                    @foreach($items as $item)
                        @php
                            $avail = (int) $item->available;
                            $hit = $highlightItemId === $item->id;
                            $cost = $item->avg_cost !== null && (float) $item->avg_cost > 0 ? (float) $item->avg_cost : null;
                            $markup = $cost && $item->sale_price !== null ? round(((float) $item->sale_price - $cost) / $cost * 100) : null;
                        @endphp
                        <tr @class(['ns-hit' => $hit])>
                            <td><b>{{ $item->size !== '' ? $item->size : '—' }}</b>@if($hit) <span class="ns-tag ns-ok">эта пара</span>@endif</td>
                            <td class="ns-num">@if($avail > 0)<b>{{ $avail }}</b>@else<span class="ns-dn">{{ $avail }}</span>@endif</td>
                            <td class="ns-num ns-muted">{{ (int) $item->reserved ?: '—' }}</td>
                            <td class="ns-num"><b>{{ $item->sale_price !== null ? $rub($item->sale_price) : '—' }}</b></td>
                            @if($seesCost)
                                <td class="ns-num ns-muted">{{ $cost !== null ? $rub($cost) : '—' }}</td>
                                <td @class(['ns-num', 'ns-dn' => $markup !== null && $markup < 0, 'ns-up' => $markup !== null && $markup >= 0])>{{ $markup !== null ? $markup.'%' : '—' }}</td>
                            @endif
                            <td class="ns-num ns-muted">{{ (int) ($marks[$item->id] ?? 0) ?: '—' }}</td>
                            <td style="text-align:right">
                                @if($avail > 0)
                                    <a class="ns-btn ns-sm ns-primary" href="{{ route('sale.quick', ['q' => $saleQuery, 'item' => $item->id]) }}">Продать</a>
                                @else
                                    <span class="ns-muted" style="font-size:12px">нет</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if($card['sales']->count())
                    <div class="ns-sc-sales">
                        <div class="ns-sec-h">Последние продажи</div>
                        <div class="ns-hlist">
                            @foreach($card['sales'] as $sale)
                                <div class="ns-hr">
                                    <span class="ns-muted" style="width:110px;flex:none">{{ $sale->stock_deducted_at->format('d.m.Y H:i') }}</span>
                                    <div class="ns-grow"><b>р. {{ optional($sale->warehouseItem)->size ?? '—' }} × {{ (int) $sale->sold_quantity }}</b><small>{{ optional($sale->responsible)->name }}</small></div>
                                    <b>{{ $sale->amount !== null ? $rub($sale->amount) : '—' }}</b>
                                    <a class="ns-ib" href="{{ route('deals.show', $sale->id) }}" title="Открыть сделку">{!! $ui::icon('chev') !!}</a>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const input = document.getElementById('scanInput'), form = document.getElementById('nsScanForm');
    const chip = document.getElementById('nsScanInfo'), infoV = document.getElementById('nsScanInfoV');
    // «Только информация» запоминается: с ней скан показывает карточку, без неё — сразу продажа
    let info = infoV.value === '1';
    try { if (!@json(request()->has('info')) && localStorage.getItem('scanInfo') === '1') info = true; } catch (e) {}
    const paint = () => { chip.classList.toggle('ns-on', info); infoV.value = info ? '1' : ''; };
    paint();
    chip.addEventListener('click', () => { info = !info; paint(); try { localStorage.setItem('scanInfo', info ? '1' : '0'); } catch (e) {} input.focus(); });
    form.addEventListener('submit', () => { if (!infoV.value) infoV.disabled = true; });
    input.select();
    // Сканер без Enter в конце: отправляем сами после короткой паузы, если код «напечатан» быстро.
    let first = 0, last = 0, timer = null;
    input.addEventListener('input', () => {
        const now = performance.now();
        if (now - last > 300) first = now;
        last = now;
        clearTimeout(timer);
        timer = setTimeout(() => {
            const v = input.value.trim(), avg = (last - first) / Math.max(1, v.length - 1);
            if (v.length >= 6 && avg < 90) form.requestSubmit();
        }, 250);
    });
    document.querySelectorAll('.ns-pimg img').forEach((img) => img.addEventListener('error', () => { img.parentNode.innerHTML = '<span class="ns-phb">' + ns.esc((img.dataset.b || '?').slice(0, 2).toUpperCase()) + '</span>'; }, { once: true }));
})();
</script>
@endpush
