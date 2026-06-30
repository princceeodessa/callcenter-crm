@extends('layouts.app')

@push('styles')
<style>
    .wh-summary{ display:flex; flex-wrap:wrap; gap:.6rem; }
    .wh-stat{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:.8rem; padding:.5rem .9rem; min-width:120px; }
    .wh-stat .v{ font-size:1.15rem; font-weight:800; line-height:1.1; }
    .wh-stat .l{ font-size:.72rem; color:var(--crm-muted); }
    .product-card{ border:1px solid var(--crm-border); border-radius:1rem; box-shadow:var(--crm-shadow); margin-bottom:1rem; overflow:hidden; }
    .product-card.is-low{ border-color:#f59e0b; }
    .product-card > .ph{ display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; padding:.7rem 1rem; background:var(--crm-surface-strong); border-bottom:1px solid var(--crm-border); }
    .product-card .pname{ font-weight:800; font-size:1.02rem; }
    .product-card .pmeta{ font-size:.8rem; color:var(--crm-muted); }
    .product-card .pmeta b{ color:var(--crm-text); }
    .size-grid{ display:flex; flex-wrap:wrap; gap:.55rem; padding:.85rem 1rem; }
    .size-cell{ width:188px; border:1px solid var(--crm-border); border-radius:.7rem; padding:.5rem .6rem; background:var(--crm-surface); transition:transform .08s ease, box-shadow .08s ease; }
    .size-cell:hover{ transform:translateY(-1px); box-shadow:0 8px 20px rgba(15,23,42,.08); }
    .size-cell.s-danger{ border-color:#ef4444; }
    .size-cell.s-warning{ border-color:#f59e0b; }
    .size-top{ display:flex; align-items:center; justify-content:space-between; }
    .size-name{ font-weight:800; }
    .qty-badge{ font-weight:800; color:#fff; border-radius:999px; padding:.05rem .5rem; font-size:.85rem; }
    .qty-success{ background:#16a34a; } .qty-warning{ background:#d97706; } .qty-danger{ background:#dc2626; }
    .size-sub{ font-size:.7rem; color:var(--crm-muted); margin-top:.1rem; }
    .size-actions{ display:flex; flex-wrap:wrap; align-items:center; gap:.25rem; margin-top:.45rem; }
    .size-actions form{ display:inline-flex; align-items:center; gap:.2rem; margin:0; }
    .btn-xs{ padding:.08rem .42rem; font-size:.74rem; line-height:1.25; border-radius:.4rem; }
    .form-control-xs{ padding:.1rem .35rem; font-size:.76rem; height:auto; width:52px; border-radius:.4rem; }
    .form-control-xs.price{ width:62px; }
    .add-size{ width:188px; border:1px dashed var(--crm-border); border-radius:.7rem; padding:.5rem .6rem; display:flex; flex-direction:column; gap:.3rem; justify-content:center; }
</style>
@endpush

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $state = fn ($i) => $i->available <= 0 ? 'danger' : ($i->is_low ? 'warning' : 'success');
        $moveLabels = [
            'in' => ['Приход (закупка)', 'success'], 'in_adjust' => ['Корректировка прихода', 'secondary'],
            'in_reversal' => ['Откат прихода', 'warning'], 'out' => ['Продажа', 'danger'],
            'out_reversal' => ['Возврат на склад', 'info'], 'reserve' => ['Резерв', 'warning'],
            'reserve_release' => ['Снятие резерва', 'info'], 'replenish' => ['Пополнение', 'success'],
            'adjust' => ['Корректировка', 'secondary'],
        ];
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="mb-0">Склад</h4>
        <form method="GET" action="{{ route('warehouse.index') }}" class="d-flex gap-2">
            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Поиск: бренд, модель, размер" style="min-width:260px;">
            <button class="btn btn-sm btn-primary">Найти</button>
            @if($q !== '')<a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">Сброс</a>@endif
        </form>
    </div>

    <div class="wh-summary mb-3">
        <div class="wh-stat"><div class="v">{{ $products->count() }}</div><div class="l">товаров</div></div>
        <div class="wh-stat"><div class="v">{{ $totalUnits }}</div><div class="l">пар на складе</div></div>
        <div class="wh-stat"><div class="v">{{ $products->sum('available') }}</div><div class="l">доступно</div></div>
        <div class="wh-stat"><div class="v">{{ $money($stockValue) }} ₽</div><div class="l">в ценах продажи</div></div>
    </div>

    {{-- Добавить товар / размер --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('warehouse.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3"><label class="form-label small mb-1">Бренд</label><input type="text" name="brand" class="form-control form-control-sm" value="{{ old('brand') }}" placeholder="Nike"></div>
                <div class="col-md-3"><label class="form-label small mb-1">Модель</label><input type="text" name="model" class="form-control form-control-sm" value="{{ old('model') }}" placeholder="Air Max 90"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Размер</label><input type="text" name="size" class="form-control form-control-sm" value="{{ old('size') }}" placeholder="42"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Кол-во</label><input type="number" name="quantity" class="form-control form-control-sm" min="0" value="{{ old('quantity', 1) }}"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Цена прод., ₽</label><input type="number" step="0.01" min="0" name="sale_price" class="form-control form-control-sm" value="{{ old('sale_price') }}"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Мин.</label><input type="number" name="low_stock_threshold" class="form-control form-control-sm" min="0" value="{{ old('low_stock_threshold', 0) }}"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-success w-100">+ Добавить</button></div>
            </form>
            <div class="form-text mt-1">Один и тот же «бренд + модель» с разными размерами объединяются в одну карточку товара.</div>
        </div>
    </div>

    {{-- Товары с размерной сеткой --}}
    @forelse($products as $prod)
        <div class="product-card {{ $prod['low'] ? 'is-low' : '' }}">
            <div class="ph">
                <div class="pname">{{ $prod['name'] }} <span class="text-muted small fw-normal">· {{ $prod['sizes']->count() }} разм.</span></div>
                <div class="pmeta">
                    пар <b>{{ $prod['total'] }}</b> · доступно <b>{{ $prod['available'] }}</b>@if($prod['reserved'] > 0) · резерв {{ $prod['reserved'] }}@endif · <b>{{ $money($prod['value']) }} ₽</b>
                    @if($prod['low']) <span class="badge text-bg-warning ms-1">заканчивается</span>@endif
                </div>
            </div>
            <div class="size-grid">
                @foreach($prod['sizes'] as $i)
                    <div class="size-cell s-{{ $state($i) }}">
                        <div class="size-top">
                            <span class="size-name">{{ $i->size !== '' ? 'р. '.$i->size : '—' }}</span>
                            <span class="qty-badge qty-{{ $state($i) }}">{{ $i->available }}</span>
                        </div>
                        @if($i->reserved > 0)<div class="size-sub">резерв {{ $i->reserved }}@if(!is_null($i->avg_cost)) · себест. {{ $money($i->avg_cost) }} ₽@endif</div>
                        @elseif(!is_null($i->avg_cost))<div class="size-sub">себест. {{ $money($i->avg_cost) }} ₽</div>@endif
                        <div class="size-actions">
                            <form method="POST" action="{{ route('warehouse.replenish', $i) }}">@csrf
                                <button name="delta" value="-1" class="btn btn-xs btn-outline-secondary" title="−1 пара">−</button>
                                <button name="delta" value="1" class="btn btn-xs btn-outline-success" title="+1 пара">+</button>
                            </form>
                            <form method="POST" action="{{ route('warehouse.update', $i) }}">@csrf @method('PATCH')
                                <input type="number" name="quantity" value="{{ $i->quantity }}" min="0" class="form-control form-control-xs" title="Остаток">
                                @if($isHead)
                                    <input type="number" step="0.01" min="0" name="sale_price" value="{{ $i->sale_price }}" class="form-control form-control-xs price" placeholder="₽" title="Цена продажи">
                                    <input type="number" name="low_stock_threshold" value="{{ $i->low_stock_threshold }}" min="0" class="form-control form-control-xs" title="Мин. остаток" style="width:46px">
                                @endif
                                <button class="btn btn-xs btn-primary" title="Сохранить">✓</button>
                            </form>
                            @if($isHead)
                                <form method="POST" action="{{ route('warehouse.destroy', $i) }}" onsubmit="return confirm('Удалить размер {{ $i->size }}?')">@csrf @method('DELETE')
                                    <button class="btn btn-xs btn-outline-danger" title="Удалить размер">✕</button>
                                </form>
                            @endif
                        </div>
                    </div>
                @endforeach

                <form method="POST" action="{{ route('warehouse.store') }}" class="add-size">
                    @csrf
                    <input type="hidden" name="brand" value="{{ $prod['brand'] }}">
                    <input type="hidden" name="model" value="{{ $prod['model'] }}">
                    <div class="small text-muted">+ размер</div>
                    <div class="d-flex gap-1">
                        <input type="text" name="size" class="form-control form-control-sm" placeholder="размер">
                        <input type="number" name="quantity" class="form-control form-control-sm" min="0" value="1" style="width:64px">
                    </div>
                    <button class="btn btn-sm btn-outline-success">Добавить</button>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-light border">На складе пока пусто. Закупки попадут сюда автоматически на стадии «Получено / На складе», либо добавьте товар вручную выше.</div>
    @endforelse

    {{-- История движений --}}
    @if($movements->isNotEmpty())
        <h6 class="mt-4 mb-2">Последние движения</h6>
        <div class="card shadow-sm">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Когда</th><th>Позиция</th><th>Операция</th><th class="text-end">Кол-во</th><th>Кто</th><th>Примечание</th></tr></thead>
                    <tbody>
                        @foreach($movements as $m)
                            @php([$label, $color] = $moveLabels[$m->type] ?? [$m->type, 'secondary'])
                            <tr>
                                <td class="text-muted small">{{ optional($m->created_at)->format('d.m.Y H:i') }}</td>
                                <td>{{ $m->item?->display_name ?? '—' }}</td>
                                <td><span class="badge text-bg-{{ $color }}">{{ $label }}</span></td>
                                <td class="text-end fw-semibold {{ $m->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $m->quantity > 0 ? '+' : '' }}{{ $m->quantity }}</td>
                                <td class="small">{{ $m->user?->name ?? 'система' }}</td>
                                <td class="small text-muted">{{ $m->note }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
@endsection
