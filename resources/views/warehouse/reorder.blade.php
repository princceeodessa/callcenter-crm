@extends('layouts.app')

@section('content')
<style>
    .prio-high{ background:rgba(220,38,38,.08); }
    .prio-mid{ background:rgba(217,119,6,.08); }
    .prio-low{ background:rgba(16,185,129,.05); }
    .prio-tag{ padding:.15rem .55rem; border-radius:999px; font-size:.72rem; font-weight:700; color:#fff; }
    .prio-tag.high{ background:#dc2626; }
    .prio-tag.mid{ background:#d97706; }
    .prio-tag.low{ background:#059669; }
</style>

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0" style="letter-spacing:-.02em">🧠 Умные подсказки закупок</h4>
        <div class="text-muted small">рекомендации на основе продаж за 90 дней + запас на неделю</div>
    </div>
    <div class="d-flex gap-2">
        <form method="GET" action="{{ route('warehouse.reorder') }}" class="d-flex gap-1">
            <select name="days" onchange="this.form.submit()" class="form-select form-select-sm">
                @foreach([7, 14, 30, 60, 90] as $d)
                    <option value="{{ $d }}" @selected($days === $d)>Прогноз на {{ $d }} дн.</option>
                @endforeach
            </select>
        </form>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">← К складу</a>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle">
                <thead>
                    <tr>
                        <th></th>
                        <th>Позиция</th>
                        <th class="text-end">Продано за 90 дн.</th>
                        <th class="text-end">Скорость (пар/день)</th>
                        <th class="text-end">Остаток сейчас</th>
                        <th class="text-end">Хватит на</th>
                        <th class="text-end">Рекомендуем заказать</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($suggestions as $s)
                    <tr class="prio-{{ $s['priority'] }}">
                        <td><span class="prio-tag {{ $s['priority'] }}">{{ $s['priority'] === 'high' ? 'СРОЧНО' : ($s['priority'] === 'mid' ? 'СКОРО' : 'Норма') }}</span></td>
                        <td>{{ $s['name'] }}</td>
                        <td class="text-end">{{ $s['sold_90d'] }}</td>
                        <td class="text-end">{{ number_format($s['per_day'], 2, ',', '') }}</td>
                        <td class="text-end fw-semibold {{ $s['available'] <= 0 ? 'text-danger' : '' }}">{{ $s['available'] }}</td>
                        <td class="text-end">{{ $s['days_left'] ?? '∞' }} дн.</td>
                        <td class="text-end fw-bold h5 mb-0">{{ $s['suggested'] }} пар</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-muted small text-center py-4">
                        📉 Рекомендаций нет: за последние 90 дней продаж не было или всех позиций хватит с запасом.
                    </td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="mt-3 text-muted small">
    <b>Как считаем:</b> берём среднюю скорость продаж по позиции за последние 90 дней и прогнозируем расход за выбранный горизонт.
    К прогнозу добавляем недельный страховой запас, вычитаем текущий остаток.
    Приоритет <span class="prio-tag high">СРОЧНО</span> — хватит менее чем на 7 дней,
    <span class="prio-tag mid">СКОРО</span> — 7–21 день,
    <span class="prio-tag low">Норма</span> — больше 21 дня.
</div>
@endsection
