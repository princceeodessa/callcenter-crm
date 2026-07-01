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

    .size-grid{ display:flex; flex-wrap:wrap; gap:.6rem; padding:.85rem 1rem; }
    .size-cell{ width:126px; border:1px solid var(--crm-border); border-radius:.75rem; padding:.55rem .55rem .45rem; background:var(--crm-surface); position:relative; }
    .size-cell.s-danger{ border-color:#ef4444; background:rgba(239,68,68,.05); }
    .size-cell.s-warning{ border-color:#f59e0b; background:rgba(245,158,11,.05); }

    .sc-size{ font-weight:800; font-size:.85rem; color:var(--crm-muted); letter-spacing:.02em; }
    .sc-qty{ font-size:1.9rem; font-weight:800; line-height:1; margin:.15rem 0 .3rem; }
    .sc-qty.q-danger{ color:#dc2626; } .sc-qty.q-warning{ color:#d97706; } .sc-qty.q-success{ color:#16a34a; }
    .sc-sub{ font-size:.7rem; color:var(--crm-muted); min-height:.9rem; }
    .sc-actions{ display:flex; align-items:center; gap:.3rem; margin-top:.35rem; }
    .sc-actions .btn-qty{ flex:1; padding:.15rem 0; font-weight:700; border-radius:.4rem; font-size:.9rem; }
    .sc-actions .btn-more{ padding:.15rem .4rem; border-radius:.4rem; font-size:.9rem; }

    .sc-details{ margin-top:.5rem; padding-top:.5rem; border-top:1px dashed var(--crm-border); }
    .sc-details .form-control-xs{ padding:.15rem .35rem; font-size:.78rem; height:auto; border-radius:.4rem; }
    .sc-details .row-lbl{ font-size:.7rem; color:var(--crm-muted); margin-bottom:.1rem; }
    .sc-details .fld{ margin-bottom:.35rem; }
    .btn-xs{ padding:.1rem .5rem; font-size:.75rem; line-height:1.3; border-radius:.4rem; }

    .add-size-tile{ width:126px; border:1px dashed var(--crm-border); border-radius:.75rem; padding:.55rem; display:flex; align-items:center; justify-content:center; color:var(--crm-muted); cursor:pointer; }
    .add-size-tile:hover{ color:var(--crm-text); border-color:var(--crm-text); }

    /* нативный <details>: без стрелки, стилизуем summary как кнопку */
    details.wh-details > summary{ list-style:none; cursor:pointer; }
    details.wh-details > summary::-webkit-details-marker{ display:none; }
</style>
@endpush

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $state = fn ($i) => $i->available <= 0 ? 'danger' : ($i->is_low ? 'warning' : 'success');
        $moveLabels = [
            'in' => ['Приход (закупка)', 'success'], 'in_adjust' => ['Корректировка прихода', 'secondary'],
            'in_reversal' => ['Откат прихода', 'warning'], 'out' => ['Продажа', 'danger'],
            'out_reversal' => ['Возврат на склад', 'info'], 'return' => ['Возврат продажи', 'info'],
            'reserve' => ['Резерв', 'warning'], 'reserve_release' => ['Снятие резерва', 'info'],
            'replenish' => ['Пополнение', 'success'], 'adjust' => ['Корректировка', 'secondary'],
        ];
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="mb-0">Склад</h4>
        <div class="d-flex gap-2 flex-wrap">
            <form method="GET" action="{{ route('warehouse.index') }}" class="d-flex gap-2">
                <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Поиск: бренд, модель, размер" style="min-width:240px;">
                <button class="btn btn-sm btn-primary">Найти</button>
                @if($q !== '')<a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">Сброс</a>@endif
            </form>
            <a class="btn btn-sm btn-outline-success" href="{{ route('warehouse.export', request()->only('q')) }}">Экспорт CSV</a>
        </div>
    </div>

    <div class="wh-summary mb-3">
        <div class="wh-stat"><div class="v">{{ $products->count() }}</div><div class="l">товаров</div></div>
        <div class="wh-stat"><div class="v">{{ $totalUnits }}</div><div class="l">пар на складе</div></div>
        <div class="wh-stat"><div class="v">{{ $products->sum('available') }}</div><div class="l">доступно</div></div>
        <div class="wh-stat"><div class="v">{{ $money($stockValue) }} ₽</div><div class="l">в ценах продажи</div></div>
    </div>

    {{-- Новый товар — свёрнут по умолчанию --}}
    <details class="mb-3 wh-details">
        <summary class="btn btn-sm btn-outline-success">+ Новый товар</summary>
        <div class="card shadow-sm mt-2"><div class="card-body">
            <form method="POST" action="{{ route('warehouse.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3"><label class="form-label small mb-1">Бренд</label><input type="text" name="brand" class="form-control form-control-sm" value="{{ old('brand') }}" placeholder="Nike"></div>
                <div class="col-md-3"><label class="form-label small mb-1">Модель</label><input type="text" name="model" class="form-control form-control-sm" value="{{ old('model') }}" placeholder="Air Max 90"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Размер</label><input type="text" name="size" class="form-control form-control-sm" value="{{ old('size') }}" placeholder="42"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Кол-во</label><input type="number" name="quantity" class="form-control form-control-sm" min="0" value="{{ old('quantity', 1) }}"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Цена прод., ₽</label><input type="number" step="0.01" min="0" name="sale_price" class="form-control form-control-sm" value="{{ old('sale_price') }}"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Мин.</label><input type="number" name="low_stock_threshold" class="form-control form-control-sm" min="0" value="{{ old('low_stock_threshold', 0) }}"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-success w-100">Добавить</button></div>
            </form>
            <div class="form-text mt-1">Совпадающие «бренд + модель» с разными размерами объединятся в одну карточку.</div>
        </div></div>
    </details>

    {{-- Товары --}}
    @forelse($products as $prod)
        <div class="product-card {{ $prod['low'] ? 'is-low' : '' }}">
            <div class="ph">
                <div>
                    <div class="pname">{{ $prod['name'] }} <span class="text-muted small fw-normal">· {{ $prod['sizes']->count() }} разм.</span></div>
                    <div class="pmeta">
                        пар <b>{{ $prod['total'] }}</b> · доступно <b>{{ $prod['available'] }}</b>@if($prod['reserved'] > 0) · резерв {{ $prod['reserved'] }}@endif@if($prod['value'] > 0) · <b>{{ $money($prod['value']) }} ₽</b>@endif
                        @if($prod['low']) <span class="badge text-bg-warning ms-1">заканчивается</span>@endif
                    </div>
                </div>
            </div>
            <div class="size-grid">
                @foreach($prod['sizes'] as $i)
                    @php($st = $state($i))
                    <div class="size-cell s-{{ $st }}">
                        <div class="sc-size">{{ $i->size !== '' ? 'р. '.$i->size : '—' }}</div>
                        <div class="sc-qty q-{{ $st }}">{{ $i->available }}</div>
                        <div class="sc-sub">
                            @if($i->reserved > 0)резерв {{ $i->reserved }}@endif
                        </div>
                        <div class="sc-actions">
                            {{-- быстрые -1 / +1 без открытия деталей --}}
                            <form method="POST" action="{{ route('warehouse.replenish', $i) }}" class="d-flex gap-1" style="flex:1">
                                @csrf
                                <button name="delta" value="-1" class="btn btn-xs btn-outline-secondary btn-qty" title="−1 пара">−</button>
                                <button name="delta" value="1" class="btn btn-xs btn-outline-success btn-qty" title="+1 пара">+</button>
                            </form>
                            <details class="wh-details">
                                <summary class="btn btn-xs btn-outline-secondary btn-more" title="Подробнее">⋯</summary>
                                <div class="sc-details">
                                    <form method="POST" action="{{ route('warehouse.update', $i) }}">
                                        @csrf @method('PATCH')
                                        <div class="fld">
                                            <div class="row-lbl">Остаток</div>
                                            <input type="number" name="quantity" value="{{ $i->quantity }}" min="0" class="form-control form-control-xs">
                                        </div>
                                        @if($isHead)
                                            <div class="fld">
                                                <div class="row-lbl">Цена продажи, ₽</div>
                                                <input type="number" step="0.01" min="0" name="sale_price" value="{{ $i->sale_price }}" class="form-control form-control-xs">
                                            </div>
                                            <div class="fld">
                                                <div class="row-lbl">Мин. остаток</div>
                                                <input type="number" name="low_stock_threshold" value="{{ $i->low_stock_threshold }}" min="0" class="form-control form-control-xs">
                                            </div>
                                        @endif
                                        <button class="btn btn-xs btn-primary w-100">Сохранить</button>
                                    </form>
                                    @if($isHead)
                                        <form method="POST" action="{{ route('warehouse.destroy', $i) }}" class="mt-1" onsubmit="return confirm('Удалить размер {{ $i->size }}?')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-xs btn-outline-danger w-100">Удалить размер</button>
                                        </form>
                                    @endif
                                </div>
                            </details>
                        </div>
                    </div>
                @endforeach

                {{-- Добавить размер — тоже за кликом, чтобы не шумел --}}
                <details class="wh-details">
                    <summary class="add-size-tile">+ размер</summary>
                    <div class="size-cell mt-2" style="border-style:dashed">
                        <form method="POST" action="{{ route('warehouse.store') }}">
                            @csrf
                            <input type="hidden" name="brand" value="{{ $prod['brand'] }}">
                            <input type="hidden" name="model" value="{{ $prod['model'] }}">
                            <div class="fld"><div class="row-lbl">Размер</div><input type="text" name="size" class="form-control form-control-xs" placeholder="42" required></div>
                            <div class="fld"><div class="row-lbl">Кол-во</div><input type="number" name="quantity" min="0" value="1" class="form-control form-control-xs"></div>
                            <button class="btn btn-xs btn-success w-100">Добавить</button>
                        </form>
                    </div>
                </details>
            </div>
        </div>
    @empty
        <div class="alert alert-light border">На складе пока пусто. Закупки попадут сюда автоматически на стадии «Получено / На складе», либо добавьте товар вручную (+ Новый товар).</div>
    @endforelse

    {{-- История движений --}}
    @if($movements->isNotEmpty())
        <details class="mt-4 wh-details">
            <summary class="btn btn-sm btn-outline-secondary">Последние движения ({{ $movements->count() }})</summary>
            <div class="card shadow-sm mt-2">
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
        </details>
    @endif
@endsection
