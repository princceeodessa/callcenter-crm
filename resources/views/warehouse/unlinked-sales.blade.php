@extends('layouts.sneakers')
{{-- «Продажи без списания» — сверка продаж, по которым пара не списалась со склада, и отрицательных остатков (оранжевая
     кнопка на складе). Каркас «Нордика», логика прежняя: UnlinkedSalesController. Закупка — только руководителю. --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $pendingCount = $rows->count();
    $linkedCount = $linked->count();
    $skippedCount = $skipped->count();
@endphp

@section('title', 'Продажи без списания')
@section('ns_sub', 'продано, но пара со склада не списалась')

@section('content')
<div class="ns-flash ns-info ns-long">{!! $ui::icon('warn', 'ns-sm') !!}<span>
    Продажи от <b>07.07.2026 14:34</b> загружены из файла истории — в нём не было размеров, поэтому пары не привязались к складу и остатки
    не уменьшились. Для каждой продажи выберите пару, которую продали на самом деле, — она <b>спишется со склада</b>; дата, выручка и закупка
    из файла не меняются. Пары не было в CRM или её уже списали вручную — «Списывать не нужно». Ошиблись — внизу любую строку можно отменить.
</span></div>

<div class="ns-kpis">
    <div @class(['ns-kpi', 'ns-warn' => $pendingCount])><div class="ns-kl">Осталось разобрать</div><div @class(['ns-kv', 'ns-dn' => $pendingCount, 'ns-up' => ! $pendingCount])>{{ $pendingCount }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Списано при сверке</div><div class="ns-kv">{{ $linkedCount }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Списывать не нужно</div><div class="ns-kv">{{ $skippedCount }}</div></div>
    <div @class(['ns-kpi', 'ns-warn' => $negative->isNotEmpty()])><div class="ns-kl">Отрицательных остатков</div><div @class(['ns-kv', 'ns-dn' => $negative->isNotEmpty()])>{{ $negative->count() }}</div></div>
</div>

<datalist id="wh-items">
    @foreach($inStock as $wi)
        <option value="#{{ $wi->id }} · {{ $wi->display_name }} · на складе {{ $wi->quantity }}"></option>
    @endforeach
</datalist>

@forelse($rows as $row)
    @php
        $deal = $row['deal'];
    @endphp
    <div class="ns-panel ns-us" id="sale-{{ $deal->id }}">
        <div class="ns-us-h">
            <b>{{ $deal->title }}</b>
            <a href="{{ route('deals.show', $deal) }}">#{{ $deal->id }}</a>
            <span class="ns-muted">продано {{ optional($deal->stock_deducted_at)->format('d.m.Y H:i') }}@if($deal->amount !== null) · {{ $money($deal->amount) }}@endif</span>
            @if($isHead && $deal->sold_unit_cost !== null)
                <span class="ns-muted">· закуп по файлу <b>{{ $money($deal->sold_unit_cost) }}</b></span>
            @endif
        </div>
        @forelse($row['suggestions'] as $s)
            @php
                $wi = $s['item'];
            @endphp
            <div @class(['ns-us-sug', 'ns-best' => $s['same_cost']])>
                <div class="ns-grow">
                    {{ trim($wi->brand.' '.$wi->model) }} <span class="ns-tag">р. {{ $wi->size !== '' ? $wi->size : '—' }}</span>
                    <span class="ns-muted">на складе {{ $wi->quantity }}</span>
                    @if($isHead && $wi->avg_cost !== null)<span class="ns-muted">· закуп {{ $money($wi->avg_cost) }}</span>@endif
                    @if($s['same_cost'])<span class="ns-tag ns-ok">закупочная цена совпала</span>@endif
                </div>
                <form method="POST" action="{{ route('warehouse.unlinked.link', $deal) }}">
                    @csrf
                    <input type="hidden" name="item_id" value="{{ $wi->id }}">
                    <button class="ns-btn ns-sm ns-primary" type="submit">Списать эту пару</button>
                </form>
            </div>
        @empty
            <div class="ns-muted ns-us-none">Похожих пар в наличии нет — найдите пару вручную ниже или отметьте «Списывать не нужно».</div>
        @endforelse
        <div class="ns-us-other">
            <form method="POST" action="{{ route('warehouse.unlinked.link', $deal) }}" class="ns-us-pick">
                @csrf
                <input type="text" name="item_ref" list="wh-items" placeholder="Другая пара: начните вводить модель или артикул…" autocomplete="off" data-scan="own">
                <button class="ns-btn ns-sm" type="submit">Списать</button>
            </form>
            <form method="POST" action="{{ route('warehouse.unlinked.skip', $deal) }}" onsubmit="return confirm('Не списывать пару по этой продаже? (пары не было в CRM или её уже списали вручную)')">
                @csrf
                <button class="ns-btn ns-sm" type="submit">Списывать не нужно</button>
            </form>
        </div>
    </div>
@empty
    <div class="ns-flash ns-ok">{!! $ui::icon('check', 'ns-sm') !!}Все продажи привязаны к складу — разбирать нечего.</div>
@endforelse

@if($negative->isNotEmpty())
    <div class="ns-sec-h" style="margin-top:8px">Отрицательные остатки</div>
    <div class="ns-panel ns-panel-pad" style="margin-bottom:18px">
        <div class="ns-muted" style="font-size:12.5px;margin-bottom:6px">Меньше нуля пар не бывает — это ошибка учёта (например, откатили приход закупки, а пару уже продали). «Обнулить» ставит остаток 0.</div>
        <div class="ns-hlist">
            @foreach($negative as $ni)
                <div class="ns-hr">
                    <div class="ns-grow"><b>{{ $ni->display_name }} · остаток <span class="ns-dn">{{ $ni->quantity }}</span></b>
                        @foreach($negativeMoves[$ni->id] ?? collect() as $mv)
                            <small style="display:block">{{ $mv->created_at?->format('d.m.Y H:i') }} · {{ $mv->quantity > 0 ? '+' : '' }}{{ $mv->quantity }} · {{ $mv->note }}@if($mv->user) · {{ $mv->user->name }}@endif</small>
                        @endforeach
                    </div>
                    <form method="POST" action="{{ route('warehouse.item.zero', $ni) }}" onsubmit="return confirm('Поставить остаток 0?')">
                        @csrf
                        <button class="ns-btn ns-sm ns-danger" type="submit">Обнулить</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($linkedCount)
    <div class="ns-sec-h">Списано при сверке <span class="ns-sm">{{ $linkedCount }}</span></div>
    <div class="ns-panel" style="overflow:hidden;margin-bottom:18px">
        <table class="ns-tbl">
            <tbody>
            @foreach($linked as $ld)
                <tr>
                    <td><a href="{{ route('deals.show', $ld) }}">#{{ $ld->id }}</a></td>
                    <td>{{ $ld->title }}</td>
                    <td>→ <b>{{ $ld->warehouseItem?->display_name ?? '—' }}</b></td>
                    <td class="ns-muted">{{ $ld->stock_linked_at?->format('d.m H:i') }}</td>
                    <td class="ns-num">
                        <form method="POST" action="{{ route('warehouse.unlinked.undo', $ld) }}" onsubmit="return confirm('Отменить списание? Пара вернётся на склад, продажа — в список.')">
                            @csrf
                            <button class="ns-btn ns-sm" type="submit">Отменить</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif

@if($skippedCount)
    <div class="ns-sec-h">Списывать не нужно <span class="ns-sm">{{ $skippedCount }}</span></div>
    <div class="ns-panel" style="overflow:hidden">
        <table class="ns-tbl">
            <tbody>
            @foreach($skipped as $sd)
                <tr>
                    <td><a href="{{ route('deals.show', $sd) }}">#{{ $sd->id }}</a></td>
                    <td>{{ $sd->title }}</td>
                    <td class="ns-muted">{{ optional($sd->stock_deducted_at)->format('d.m.Y') }}</td>
                    <td class="ns-num">
                        <form method="POST" action="{{ route('warehouse.unlinked.undo', $sd) }}">
                            @csrf
                            <button class="ns-btn ns-sm" type="submit">Вернуть в список</button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
@endif
@endsection

@push('scripts')
<script>
/* после кнопки страница возвращается на то же место (прокрутка — у .ns-page), итог — всплывашкой снизу */
(() => {
    const page = document.querySelector('.ns-page');
    document.querySelectorAll('.ns-page form').forEach((f) => f.addEventListener('submit', () => { try { sessionStorage.setItem('usScrollY', String(page.scrollTop)); } catch (e) {} }));
    try {
        const y = sessionStorage.getItem('usScrollY');
        if (y !== null) { sessionStorage.removeItem('usScrollY'); page.scrollTop = parseInt(y, 10) || 0; }
    } catch (e) {}
    @if(session('status') || $errors->any())
        ns.toast(@json($errors->any() ? $errors->first() : session('status')));
    @endif
})();
</script>
@endpush
