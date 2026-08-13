@extends('layouts.app')

@section('content')
<style>
    .cons-stat{ background:var(--crm-card-bg,#fff); border:1px solid var(--crm-border); border-radius:12px; padding:.75rem 1rem; }
    .cons-stat .l{ font-size:.72rem; color:var(--crm-muted); }
    .cons-stat .v{ font-size:1.3rem; font-weight:700; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0" style="letter-spacing:-.02em">🤝 Реализация</h4>
        <div class="text-muted small">пары, переданные посредникам на продажу</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">← К складу</a>
</div>

<div class="d-flex gap-2 flex-wrap mb-3">
    <div class="cons-stat"><div class="l">Пар у посредников сейчас</div><div class="v">{{ $totalQty }}</div></div>
    @if($isHead)
        <div class="cons-stat"><div class="l">Стоимость по себестоимости</div><div class="v">{{ number_format($totalCostValue, 0, ',', ' ') }} ₽</div></div>
    @endif
</div>

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Сейчас на реализации</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Посредник</th>
                        <th>Товары</th>
                        <th class="text-end">Пар</th>
                        @if($isHead)<th class="text-end">Себестоимость</th>@endif
                    </tr>
                </thead>
                <tbody>
                @forelse($byConsignee as $group)
                    <tr>
                        <td class="fw-semibold">{{ $group['name'] }}</td>
                        <td class="small text-muted">
                            @foreach($group['items'] as $c)
                                {{ $c->item?->display_name ?? '—' }} × {{ $c->quantity }}@if(!$loop->last), @endif
                            @endforeach
                        </td>
                        <td class="text-end fw-bold">{{ $group['qty'] }}</td>
                        @if($isHead)<td class="text-end">{{ number_format($group['cost_value'], 0, ',', ' ') }} ₽</td>@endif
                    </tr>
                @empty
                    <tr><td colspan="{{ $isHead ? 4 : 3 }}" class="text-muted small text-center py-4">
                        Сейчас никто ничего не реализует — все пары либо на складе, либо проданы.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header fw-semibold">История (последние 50)</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th>Дата</th>
                        <th>Посредник</th>
                        <th>Товар</th>
                        <th class="text-end">Пар</th>
                        <th>Итог</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($history as $c)
                    <tr>
                        <td class="small text-muted">{{ optional($c->resolved_at)->format('d.m.Y H:i') }}</td>
                        <td>{{ $c->consignee }}</td>
                        <td>{{ $c->item?->display_name ?? '—' }}</td>
                        <td class="text-end">{{ $c->quantity }}</td>
                        <td>
                            @if($c->status === 'sold')
                                <span class="badge text-bg-success">продано</span>
                            @else
                                <span class="badge text-bg-secondary">возврат</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted small text-center py-4">Пока пусто.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
