@extends('layouts.app')

@section('content')
    @php $money = fn ($v) => number_format((float) $v, 0, ',', ' '); @endphp

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="mb-0">Отчёт · Кроссовки</h4>
        <form method="GET" action="{{ route('sneaker.report') }}" class="d-flex gap-2 align-items-center">
            <label class="form-label mb-0 small text-muted">Период</label>
            <input type="month" name="month" value="{{ $monthValue }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-primary">Показать</button>
        </form>
    </div>

    {{-- Продажи за период --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Выручка за период</div>
                <div class="h4 mb-0">{{ $money($revenue) }} ₽</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Прибыль за период</div>
                <div class="h4 mb-0 {{ $profit >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($profit) }} ₽</div>
                <div class="small text-muted">себестоимость: {{ $money($cogs) }} ₽</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Продано пар</div>
                <div class="h4 mb-0">{{ $unitsSold }}</div>
                <div class="small text-muted">сделок: {{ $soldDeals->count() }}</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Средний чек</div>
                <div class="h4 mb-0">{{ $soldDeals->count() ? $money($revenue / $soldDeals->count()) : '—' }} ₽</div>
            </div></div>
        </div>
    </div>

    {{-- Склад сейчас --}}
    <div class="row g-3 mb-3">
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">На складе пар</div>
                <div class="h5 mb-0">{{ $stockUnits }}@if($reservedUnits > 0) <span class="small text-muted">(резерв {{ $reservedUnits }})</span>@endif</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Склад по себестоимости</div>
                <div class="h5 mb-0">{{ $money($stockCostValue) }} ₽</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Склад в ценах продажи</div>
                <div class="h5 mb-0">{{ $money($stockSaleValue) }} ₽</div>
            </div></div>
        </div>
        <div class="col-6 col-lg-3">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="text-muted small">Открытые закупки</div>
                <div class="h5 mb-0">{{ $openPurchases }}</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Продажи --}}
        <div class="col-lg-7">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Продажи за период</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Дата</th><th>Сделка</th><th>Товар</th><th class="text-end">Пар</th><th class="text-end">Сумма</th><th class="text-end">Прибыль</th></tr></thead>
                        <tbody>
                        @forelse($soldDeals as $d)
                            @php($p = (float) ($d->amount ?? 0) - (float) ($d->sold_unit_cost ?? 0) * (int) $d->sold_quantity)
                            <tr>
                                <td class="small text-muted">{{ optional($d->stock_deducted_at)->format('d.m') }}</td>
                                <td><a href="{{ route('deals.show', $d) }}" class="text-decoration-none">{{ \Illuminate\Support\Str::limit($d->title, 24) }}</a></td>
                                <td class="small">{{ $d->warehouseItem?->display_name ?? '—' }}</td>
                                <td class="text-end">{{ $d->sold_quantity }}</td>
                                <td class="text-end">{{ $money($d->amount) }}</td>
                                <td class="text-end {{ $p >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($p) }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-muted small text-center py-3">За период продаж со склада не было.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        {{-- Заканчивается --}}
        <div class="col-lg-5">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Заканчивается на складе</div>
                <div class="table-responsive">
                    <table class="table table-sm mb-0 align-middle">
                        <thead><tr><th>Позиция</th><th class="text-end">Доступно</th><th class="text-end">Порог</th></tr></thead>
                        <tbody>
                        @forelse($lowItems as $i)
                            <tr>
                                <td>{{ $i->display_name }}</td>
                                <td class="text-end fw-semibold text-danger">{{ $i->available }}</td>
                                <td class="text-end text-muted">{{ $i->low_stock_threshold }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted small text-center py-3">Всё в норме — заканчивающихся позиций нет.</td></tr>
                        @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
