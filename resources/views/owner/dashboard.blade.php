@extends('layouts.app')

@section('content')
<style>
    .own-wrap{ max-width:1280px; margin:0 auto; }
    .own-hero{ display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:.7rem; margin-bottom:1rem; }
    .own-stat{
        position:relative; overflow:hidden;
        background:var(--crm-surface-strong); border:1px solid var(--crm-border);
        border-radius:16px; padding:.85rem 1.05rem; box-shadow:var(--crm-shadow);
    }
    .own-stat .l{ font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--crm-muted); font-weight:700; }
    .own-stat .v{ font-size:1.7rem; font-weight:800; line-height:1.15; letter-spacing:-.02em; margin-top:.1rem; }
    .own-stat.green .v{ color:#10b981; }

    .own-kpi{ display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.7rem; margin-bottom:1rem; }
    .own-kpi .k{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:14px; padding:.7rem .9rem; }
    .own-kpi .k .l{ font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--crm-muted); font-weight:600; }
    .own-kpi .k .v{ font-size:1.4rem; font-weight:800; letter-spacing:-.02em; }

    .own-card{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:16px; box-shadow:var(--crm-shadow); margin-bottom:1rem; }
    .own-card .hd{ padding:.7rem 1.05rem; font-weight:700; border-bottom:1px solid var(--crm-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; }
    .own-card table{ width:100%; border-collapse:collapse; }
    .own-card th, .own-card td{ padding:.4rem .8rem; font-size:.85rem; border-bottom:1px solid var(--crm-border); }
    .own-card th{ font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:var(--crm-muted); text-align:left; }
    .own-card tr:last-child td{ border-bottom:0; }
    .tbl-scroll{ max-height:70vh; overflow-y:auto; }
    .num{ text-align:right; white-space:nowrap; }
</style>

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
@endphp

<div class="own-wrap">
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <div>
            <h4 class="mb-0" style="letter-spacing:-.02em">📊 Сводка бизнеса</h4>
            <div class="text-muted small">склад в деньгах · продажи и прибыль по каждому заказу</div>
        </div>
        <form method="GET" action="{{ route('owner.dashboard') }}" class="d-flex gap-2 align-items-center">
            <label class="small text-muted mb-0">Месяц</label>
            <input type="month" name="month" value="{{ $monthValue }}" class="form-control form-control-sm" style="width:auto" onchange="this.form.submit()">
        </form>
    </div>

    {{-- Склад в деньгах --}}
    <div class="own-hero">
        <div class="own-stat"><div class="l">Товаров</div><div class="v">{{ $productsCount }}</div></div>
        <div class="own-stat"><div class="l">Пар на складе</div><div class="v">{{ $totalUnits }}</div></div>
        <div class="own-stat"><div class="l">Доступно</div><div class="v">{{ $availableSum }}</div></div>
        <div class="own-stat"><div class="l">Вложено · по закупке</div><div class="v">{{ $money($stockCostValue) }} ₽</div></div>
        <div class="own-stat"><div class="l">Стоимость · по продаже</div><div class="v">{{ $money($stockSaleValue) }} ₽</div></div>
        <div class="own-stat green"><div class="l">Потенц. прибыль</div><div class="v">{{ $money($potentialProfit) }} ₽</div></div>
        <div class="own-stat"><div class="l">Пар в доставке</div><div class="v">{{ $inDeliveryUnits }}</div></div>
        <div class="own-stat"><div class="l">В доставке · по закупке</div><div class="v">{{ $money($inDeliveryValue) }} ₽</div></div>
    </div>

    {{-- KPI за месяц --}}
    <div class="own-kpi">
        <div class="k"><div class="l">Выручка за месяц</div><div class="v">{{ $money($revenue) }} ₽</div></div>
        <div class="k"><div class="l">Чистая прибыль</div><div class="v {{ $profit >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($profit) }} ₽</div></div>
        <div class="k"><div class="l">Маржа</div><div class="v">{{ $revenue > 0 ? round($margin).'%' : '—' }}</div></div>
        <div class="k"><div class="l">Продано пар</div><div class="v">{{ $units }}</div></div>
        <div class="k"><div class="l">Заказов</div><div class="v">{{ $count }}</div></div>
        <div class="k"><div class="l">Средний чек</div><div class="v">{{ $count > 0 ? $money($avg).' ₽' : '—' }}</div></div>
    </div>

    @if($noCostCount > 0)
        <div class="alert alert-warning py-2 small">
            У {{ $noCostCount }} заказ(ов) не указана закупочная цена — их прибыль в расчёт не вошла и показана как «—».
            Проставьте закуп на складе (⚙️ Управление товаром → Цены), чтобы прибыль считалась полностью.
        </div>
    @endif

    <div class="row g-3">
        {{-- Топ моделей --}}
        <div class="col-lg-6">
            <div class="own-card">
                <div class="hd">Топ моделей за месяц <span class="text-muted small fw-normal">по прибыли</span></div>
                <table>
                    <thead><tr><th>Модель</th><th class="num">Пар</th><th class="num">Выручка</th><th class="num">Прибыль</th></tr></thead>
                    <tbody>
                    @forelse($topModels as $t)
                        <tr>
                            <td>{{ $t['name'] }}</td>
                            <td class="num">{{ $t['units'] }}</td>
                            <td class="num">{{ $money($t['revenue']) }} ₽</td>
                            <td class="num text-success">{{ $money($t['profit']) }} ₽</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">Продаж за месяц нет.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
        {{-- По источникам --}}
        <div class="col-lg-6">
            <div class="own-card">
                <div class="hd">По источникам за месяц</div>
                <table>
                    <thead><tr><th>Источник</th><th class="num">Заказов</th><th class="num">Выручка</th><th class="num">Прибыль</th></tr></thead>
                    <tbody>
                    @forelse($bySource as $s)
                        <tr>
                            <td>{{ $s['name'] }}</td>
                            <td class="num">{{ $s['count'] }}</td>
                            <td class="num">{{ $money($s['revenue']) }} ₽</td>
                            <td class="num text-success">{{ $money($s['profit']) }} ₽</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-muted small text-center py-3">Нет данных.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    {{-- Все продажи за месяц с маржой по каждому заказу --}}
    <div class="own-card">
        <div class="hd">
            <span>Все продажи за месяц — прибыль по каждому заказу</span>
            <span class="small text-muted fw-normal">{{ $count }} заказ(ов) · «—» = закуп не указан</span>
        </div>
        <div class="tbl-scroll">
            <table>
                <thead><tr>
                    <th>Дата</th><th>Товар</th><th>Источник</th><th>Продавец</th>
                    <th class="num">Пар</th><th class="num">Сумма</th><th class="num">Себест.</th><th class="num">Прибыль</th><th class="num">Маржа</th>
                </tr></thead>
                <tbody>
                @forelse($sold as $d)
                    @php($dProfit = $d->sale_profit)
                    @php($dMargin = $d->sale_margin_percent)
                    @php($dCost = $d->unit_cost_basis)
                    <tr>
                        <td class="text-muted small">{{ optional($d->stock_deducted_at)->format('d.m H:i') }}</td>
                        <td>{{ $d->warehouseItem?->display_name ?? $d->title }}</td>
                        <td class="small">{{ $d->manual_source ?: '—' }}</td>
                        <td class="small">{{ $d->responsible?->name ?? '—' }}</td>
                        <td class="num">{{ (int) $d->sold_quantity }}</td>
                        <td class="num">{{ $money($d->amount) }} ₽</td>
                        <td class="num text-muted">{{ $dCost !== null ? $money($dCost).' ₽' : '—' }}</td>
                        <td class="num fw-semibold {{ $dProfit === null ? 'text-muted' : ($dProfit >= 0 ? 'text-success' : 'text-danger') }}">{{ $dProfit !== null ? $money($dProfit).' ₽' : '—' }}</td>
                        <td class="num {{ $dMargin === null ? 'text-muted' : '' }}">{{ $dMargin !== null ? $dMargin.'%' : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="text-muted small text-center py-4">Продаж за выбранный месяц нет.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
