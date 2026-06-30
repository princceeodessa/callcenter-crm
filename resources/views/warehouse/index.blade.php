@extends('layouts.app')

@section('content')
    @php
        $moveLabels = [
            'in' => ['Приход (закупка)', 'success'],
            'in_reversal' => ['Откат прихода', 'warning'],
            'out' => ['Продажа', 'danger'],
            'out_reversal' => ['Возврат на склад', 'info'],
            'replenish' => ['Пополнение', 'success'],
            'adjust' => ['Корректировка', 'secondary'],
        ];
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0">Склад</h4>
            <div class="text-muted small">Позиций: {{ $items->count() }} · всего пар на складе: <span class="fw-semibold">{{ $totalUnits }}</span></div>
        </div>
        <form method="GET" action="{{ route('warehouse.index') }}" class="d-flex gap-2">
            <input type="search" name="q" value="{{ $q }}" class="form-control form-control-sm" placeholder="Поиск: бренд, модель, размер" style="min-width:260px;">
            <button class="btn btn-sm btn-primary">Найти</button>
            @if($q !== '')<a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">Сброс</a>@endif
        </form>
    </div>

    {{-- Добавить позицию / пополнить существующую --}}
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <form method="POST" action="{{ route('warehouse.store') }}" class="row g-2 align-items-end">
                @csrf
                <div class="col-md-3"><label class="form-label small mb-1">Бренд</label><input type="text" name="brand" class="form-control form-control-sm" value="{{ old('brand') }}" placeholder="Nike"></div>
                <div class="col-md-3"><label class="form-label small mb-1">Модель</label><input type="text" name="model" class="form-control form-control-sm" value="{{ old('model') }}" placeholder="Air Max 90"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Размер</label><input type="text" name="size" class="form-control form-control-sm" value="{{ old('size') }}" placeholder="42"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Кол-во</label><input type="number" name="quantity" class="form-control form-control-sm" min="0" value="{{ old('quantity', 1) }}"></div>
                <div class="col-md-2"><label class="form-label small mb-1">Цена прод., ₽</label><input type="number" step="0.01" min="0" name="sale_price" class="form-control form-control-sm" value="{{ old('sale_price') }}"></div>
                <div class="col-md-1"><label class="form-label small mb-1">Порог</label><input type="number" name="low_stock_threshold" class="form-control form-control-sm" min="0" value="{{ old('low_stock_threshold', 0) }}"></div>
                <div class="col-md-1"><button class="btn btn-sm btn-success w-100">+ Добавить</button></div>
            </form>
            <div class="form-text mt-1">Если позиция «бренд+модель+размер» уже есть — кол-во прибавится к ней.</div>
        </div>
    </div>

    {{-- Остатки --}}
    @forelse($items as $item)
        <div class="card shadow-sm mb-2 {{ $item->is_low ? 'border-danger' : '' }}">
            <div class="card-body py-2">
                <div class="row g-2 align-items-end">
                    <div class="col-md-3">
                        <div class="fw-semibold">{{ $item->display_name }}</div>
                        @if($item->is_low)<span class="badge text-bg-danger">мало на складе</span>@endif
                    </div>
                    <form method="POST" action="{{ route('warehouse.update', $item) }}" class="col-md-6">
                        @csrf @method('PATCH')
                        <div class="row g-2 align-items-end">
                            <div class="col"><label class="form-label small mb-0 text-muted">Остаток</label><input type="number" name="quantity" value="{{ $item->quantity }}" min="0" class="form-control form-control-sm"></div>
                            <div class="col"><label class="form-label small mb-0 text-muted">Цена ₽</label><input type="number" step="0.01" min="0" name="sale_price" value="{{ $item->sale_price }}" class="form-control form-control-sm"></div>
                            <div class="col"><label class="form-label small mb-0 text-muted">Порог</label><input type="number" name="low_stock_threshold" value="{{ $item->low_stock_threshold }}" min="0" class="form-control form-control-sm"></div>
                            <div class="col-auto"><button class="btn btn-sm btn-primary">Сохранить</button></div>
                        </div>
                    </form>
                    <div class="col-md-3">
                        <div class="d-flex gap-1 align-items-end justify-content-md-end">
                            <form method="POST" action="{{ route('warehouse.replenish', $item) }}" class="d-flex gap-1 align-items-end">
                                @csrf
                                <div><label class="form-label small mb-0 text-muted">+/− пар</label><input type="number" name="delta" value="1" class="form-control form-control-sm" style="width:84px"></div>
                                <button class="btn btn-sm btn-outline-success" title="Изменить остаток">Приход</button>
                            </form>
                            <form method="POST" action="{{ route('warehouse.destroy', $item) }}" onsubmit="return confirm('Удалить позицию со склада?')">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="Удалить">✕</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    @empty
        <div class="alert alert-light border">На складе пока пусто. Закупки попадут сюда автоматически на стадии «Получено / На складе», либо добавьте позицию вручную выше.</div>
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
