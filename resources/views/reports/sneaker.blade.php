@extends('layouts.app')

@push('styles')
<style>
    .kpi{ border:1px solid var(--crm-border); border-radius:.9rem; box-shadow:var(--crm-shadow); background:var(--crm-surface-strong); padding:.8rem .95rem; height:100%; }
    .kpi .l{ font-size:.72rem; color:var(--crm-muted); }
    .kpi .v{ font-size:1.35rem; font-weight:800; line-height:1.15; }
    .kpi .s{ font-size:.72rem; color:var(--crm-muted); }
    .bars{ display:flex; align-items:flex-end; gap:.5rem; height:150px; padding-top:.5rem; }
    .bar-col{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; }
    .bar-wrap{ display:flex; gap:3px; align-items:flex-end; height:100%; width:100%; justify-content:center; }
    .bar{ width:34%; min-height:2px; border-radius:.3rem .3rem 0 0; background:linear-gradient(180deg,#818cf8,#4f46e5); }
    .bar.profit{ background:linear-gradient(180deg,#34d399,#059669); }
    .bar-lbl{ font-size:.7rem; color:var(--crm-muted); margin-top:.25rem; text-align:center; }
    .funnel-row{ display:flex; align-items:center; gap:.6rem; margin-bottom:.4rem; }
    .funnel-name{ width:42%; font-size:.85rem; }
    .funnel-track{ flex:1; background:rgba(148,163,184,.18); border-radius:999px; height:18px; position:relative; overflow:hidden; }
    .funnel-fill{ height:100%; background:linear-gradient(90deg,#60a5fa,#4f46e5); border-radius:999px; min-width:2px; }
    .funnel-fill.final{ background:linear-gradient(90deg,#34d399,#059669); }
    .funnel-val{ width:120px; text-align:right; font-size:.8rem; color:var(--crm-muted); }
</style>
@endpush

@section('content')
    @php
        $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
        $dlt = function ($d) {
            if ($d === 0) return '<span class="text-muted">±0%</span>';
            $cls = $d > 0 ? 'text-success' : 'text-danger';
            $ar = $d > 0 ? '▲' : '▼';
            return '<span class="'.$cls.'">'.$ar.' '.abs($d).'% к пр. мес.</span>';
        };
        $funnelMax = max(1, (int) $funnel->max('count'));
    @endphp

    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="mb-0">Отчёт · Кроссовки</h4>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <form method="GET" action="{{ route('sneaker.report') }}" class="d-flex gap-2 align-items-center">
                <label class="form-label mb-0 small text-muted">Период</label>
                <input type="month" name="month" value="{{ $monthValue }}" class="form-control form-control-sm">
                <button class="btn btn-sm btn-primary">Показать</button>
            </form>
            <a class="btn btn-sm btn-outline-success" href="{{ route('sneaker.report.export', ['month' => $monthValue]) }}">Экспорт CSV</a>
        </div>
    </div>

    {{-- KPI с авто-сравнением --}}
    <div class="row g-2 mb-2">
        <div class="col-6 col-lg">
            <div class="kpi"><div class="l">Выручка</div><div class="v">{{ $money($cur['revenue']) }} ₽</div><div class="s">{!! $dlt($delta['revenue']) !!}</div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="kpi"><div class="l">Прибыль · маржа {{ round($cur['margin']) }}%</div><div class="v {{ $cur['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($cur['profit']) }} ₽</div><div class="s">{!! $dlt($delta['profit']) !!}</div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="kpi"><div class="l">Продано пар</div><div class="v">{{ $cur['units'] }}</div><div class="s">сделок {{ $cur['count'] }} · {!! $dlt($delta['units']) !!}</div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="kpi"><div class="l">Средний чек</div><div class="v">{{ $cur['count'] ? $money($cur['avg']) : '—' }} ₽</div><div class="s">{!! $dlt($delta['avg']) !!}</div></div>
        </div>
        <div class="col-6 col-lg">
            <div class="kpi"><div class="l">Конверсия в продажу</div><div class="v">{{ $conversion !== null ? $conversion.'%' : '—' }}</div><div class="s">продаж {{ $wonCount }} · отказов {{ $lostCount }}</div></div>
        </div>
    </div>

    {{-- Деньги/склад --}}
    <div class="row g-2 mb-3">
        <div class="col-6 col-lg-3"><div class="kpi"><div class="l">Закуплено за период</div><div class="v">{{ $money($purchaseSpend) }} ₽</div><div class="s">открытых закупок: {{ $openPurchases }}</div></div></div>
        <div class="col-6 col-lg-3"><div class="kpi"><div class="l">Склад по себестоимости</div><div class="v">{{ $money($stockCostValue) }} ₽</div><div class="s">{{ $stockUnits }} пар@if($reservedUnits) · резерв {{ $reservedUnits }}@endif</div></div></div>
        <div class="col-6 col-lg-3"><div class="kpi"><div class="l">Склад в ценах продажи</div><div class="v">{{ $money($stockSaleValue) }} ₽</div><div class="s">потенц. выручка склада</div></div></div>
        <div class="col-6 col-lg-3"><div class="kpi"><div class="l">В работе (воронка)</div><div class="v">{{ $money($pipelineValue) }} ₽</div><div class="s">{{ $pipelineCount }} активных сделок</div></div></div>
    </div>

    <div class="row g-3 mb-3">
        {{-- Динамика --}}
        <div class="col-lg-7">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="fw-semibold mb-1">Динамика за 6 месяцев <span class="small text-muted">(<span class="text-primary">выручка</span> / <span class="text-success">прибыль</span>)</span></div>
                <div class="bars">
                    @foreach($dynamics as $d)
                        <div class="bar-col">
                            <div class="bar-wrap">
                                <div class="bar" style="height: {{ max(2, (int) round($d['revenue'] / $dynMax * 100)) }}%" title="Выручка: {{ $money($d['revenue']) }} ₽"></div>
                                <div class="bar profit" style="height: {{ max(2, (int) round(max(0,$d['profit']) / $dynMax * 100)) }}%" title="Прибыль: {{ $money($d['profit']) }} ₽"></div>
                            </div>
                            <div class="bar-lbl">{{ $d['label'] }}<br>{{ $money($d['revenue']) }}</div>
                        </div>
                    @endforeach
                </div>
            </div></div>
        </div>
        {{-- Воронка --}}
        <div class="col-lg-5">
            <div class="card shadow-sm h-100"><div class="card-body">
                <div class="fw-semibold mb-2">Воронка продаж (в работе)</div>
                @forelse($funnel as $f)
                    <div class="funnel-row">
                        <div class="funnel-name">{{ $f['name'] }}</div>
                        <div class="funnel-track"><div class="funnel-fill {{ $f['final'] ? 'final' : '' }}" style="width: {{ max(2, (int) round($f['count'] / $funnelMax * 100)) }}%"></div></div>
                        <div class="funnel-val">{{ $f['count'] }} шт@if($f['sum'] > 0) · {{ $money($f['sum']) }} ₽@endif</div>
                    </div>
                @empty
                    <div class="text-muted small">Воронка не настроена.</div>
                @endforelse
            </div></div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        {{-- Топ моделей --}}
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Топ моделей за период</div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Модель</th><th class="text-end">Пар</th><th class="text-end">Выручка</th><th class="text-end">Прибыль</th></tr></thead>
                    <tbody>
                    @forelse($topModels as $t)
                        <tr><td>{{ $t['name'] }}</td><td class="text-end">{{ $t['units'] }}</td><td class="text-end">{{ $money($t['revenue']) }}</td><td class="text-end {{ $t['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($t['profit']) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">Продаж за период нет.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        {{-- По сотрудникам --}}
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">По сотрудникам за период</div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Сотрудник</th><th class="text-end">Сделок</th><th class="text-end">Пар</th><th class="text-end">Выручка</th><th class="text-end">Прибыль</th></tr></thead>
                    <tbody>
                    @forelse($byOperator as $o)
                        <tr><td>{{ $o['name'] }}</td><td class="text-end">{{ $o['count'] }}</td><td class="text-end">{{ $o['units'] }}</td><td class="text-end">{{ $money($o['revenue']) }}</td><td class="text-end {{ $o['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($o['profit']) }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-muted small text-center py-3">Продаж за период нет.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">По источникам за период</div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Источник</th><th class="text-end">Сделок</th><th class="text-end">Выручка</th></tr></thead>
                    <tbody>
                    @forelse($bySource as $s)
                        <tr><td>{{ $s['name'] }}</td><td class="text-end">{{ $s['count'] }}</td><td class="text-end">{{ $money($s['revenue']) }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-muted small text-center py-3">Нет данных за период.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        <div class="col-lg-6">
            <div class="card shadow-sm h-100"><div class="card-body d-flex flex-column justify-content-center">
                <div class="text-muted small">Возвраты за период</div>
                <div class="h3 mb-0">{{ $returnsCount }}</div>
                <div class="small text-muted">оформленных возвратов товара на склад</div>
            </div></div>
        </div>
    </div>

    <div class="row g-3">
        {{-- Дозаказать --}}
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Дозаказать (низкий остаток) @if($reorder->count())<span class="badge text-bg-danger">{{ $reorder->count() }}</span>@endif</div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Позиция</th><th class="text-end">Доступно</th><th class="text-end">Мин.</th><th class="text-end">Заказать ≈</th></tr></thead>
                    <tbody>
                    @forelse($reorder as $r)
                        <tr><td>{{ $r['name'] }}</td><td class="text-end text-danger fw-semibold">{{ $r['available'] }}</td><td class="text-end text-muted">{{ $r['threshold'] }}</td><td class="text-end">{{ $r['suggest'] }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">Всё в норме.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
        {{-- Залежавшийся --}}
        <div class="col-lg-6">
            <div class="card shadow-sm h-100">
                <div class="card-header fw-semibold">Залежавшийся товар <span class="small text-muted fw-normal">(без продаж 30+ дней)</span></div>
                <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                    <thead><tr><th>Позиция</th><th class="text-end">Пар</th><th class="text-end">В деньгах</th><th>Посл. продажа</th></tr></thead>
                    <tbody>
                    @forelse($deadStock as $d)
                        <tr><td>{{ $d['name'] }}</td><td class="text-end">{{ $d['qty'] }}</td><td class="text-end">{{ $money($d['value']) }}</td><td class="small text-muted">{{ $d['last'] ?? 'не продавалось' }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">Залежавшихся позиций нет.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>
@endsection
