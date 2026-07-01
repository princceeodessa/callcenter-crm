@extends('layouts.app')

@section('content')
<style>
    .an-title{ letter-spacing:-.02em; }
    .an-card{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:1rem; box-shadow:var(--crm-shadow); padding:1rem 1.1rem; margin-bottom:1rem; }
    .an-card h6{ margin:0 0 .8rem; letter-spacing:-.01em; }
    .bars{ display:flex; align-items:flex-end; gap:.5rem; height:170px; padding-top:.3rem; }
    .bar-col{ flex:1; display:flex; flex-direction:column; align-items:center; justify-content:flex-end; height:100%; }
    .bar-wrap{ display:flex; gap:2px; align-items:flex-end; height:100%; width:100%; justify-content:center; }
    .bar{ width:60%; min-height:2px; border-radius:.3rem .3rem 0 0; background:linear-gradient(180deg,#818cf8,#4f46e5); }
    .bar.b-sold{ background:linear-gradient(180deg,#f87171,#dc2626); }
    .bar.b-purchased{ background:linear-gradient(180deg,#34d399,#059669); }
    .bar-lbl{ font-size:.7rem; color:var(--crm-muted); margin-top:.25rem; text-align:center; }

    .stack{ display:flex; flex-direction:column-reverse; align-items:center; width:60%; height:100%; }
    .stack .seg{ width:100%; min-height:2px; }

    .brand-palette-0{ background:#6366f1; } .brand-palette-1{ background:#0ea5e9; }
    .brand-palette-2{ background:#10b981; } .brand-palette-3{ background:#f59e0b; }
    .brand-palette-4{ background:#ef4444; } .brand-palette-5{ background:#94a3b8; }

    .abc-A{ background:linear-gradient(135deg,#34d399,#059669); color:#fff; }
    .abc-B{ background:linear-gradient(135deg,#fbbf24,#d97706); color:#fff; }
    .abc-C{ background:linear-gradient(135deg,#fca5a5,#dc2626); color:#fff; }
    .abc-badge{ padding:.15rem .6rem; border-radius:999px; font-size:.7rem; font-weight:800; letter-spacing:.05em; text-transform:uppercase; }

    .kpi-row{ display:grid; grid-template-columns:repeat(auto-fit,minmax(140px,1fr)); gap:.6rem; margin-bottom:1rem; }
    .kpi{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:.9rem; padding:.6rem .8rem; }
    .kpi .l{ font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--crm-muted); font-weight:600; }
    .kpi .v{ font-size:1.4rem; font-weight:800; line-height:1.15; }

    .size-row{ display:flex; align-items:flex-end; gap:.3rem; height:120px; }
    .size-col{ flex:1; display:flex; flex-direction:column; align-items:center; }
    .size-bar-wrap{ display:flex; gap:2px; align-items:flex-end; height:100%; width:100%; justify-content:center; }
    .size-bar{ width:45%; min-height:2px; border-radius:.25rem .25rem 0 0; }
    .size-bar.stock{ background:#a5b4fc; }
    .size-bar.sold{ background:#f87171; }
    .size-lbl{ font-size:.65rem; color:var(--crm-muted); margin-top:.2rem; }
</style>

@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $brands = array_keys($topBrandsForLegend);
    $brandIndex = array_flip($brands);
@endphp

<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <div>
        <h4 class="mb-0 an-title">Аналитика склада</h4>
        <div class="text-muted small">KPI · клиенты · финансы · сезонность · оборачиваемость · ABC · размеры · бренды</div>
    </div>
    <div class="d-flex gap-2">
        <a class="btn btn-sm btn-outline-success" href="{{ route('warehouse.analytics.export') }}">📥 CSV</a>
        <button class="btn btn-sm btn-outline-primary" onclick="window.print()">🖨️ Печать</button>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">← К складу</a>
    </div>
</div>

{{-- Оглавление --}}
<div class="an-card mb-3" style="padding:.7rem 1.1rem">
    <div class="d-flex flex-wrap gap-2 align-items-center">
        <span class="small text-muted me-2">Разделы:</span>
        <a href="#kpi" class="filter-chip">📌 KPI месяца</a>
        <a href="#forecast" class="filter-chip">🔮 Прогноз</a>
        <a href="#clients" class="filter-chip">👥 Клиенты</a>
        <a href="#finance" class="filter-chip">💰 Финансы</a>
        <a href="#seasonality" class="filter-chip">📅 Сезонность</a>
        <a href="#heatmap" class="filter-chip">🔥 Heatmap</a>
        <a href="#flow" class="filter-chip">📈 Потоки</a>
        <a href="#turnover" class="filter-chip">🚀 Оборачиваемость</a>
        <a href="#abc" class="filter-chip">🎯 ABC</a>
        <a href="#taxonomy" class="filter-chip">🧩 Структура</a>
        <a href="#sizes" class="filter-chip">👟 Размеры</a>
        <a href="#brands" class="filter-chip">🏷️ Бренды</a>
    </div>
</div>

{{-- === KPI HERO === --}}
@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $dlt = function ($d) {
        if ($d === 0) return '<span class="text-muted">±0%</span>';
        $cls = $d > 0 ? 'text-success' : 'text-danger';
        $ar = $d > 0 ? '▲' : '▼';
        return '<span class="'.$cls.'">'.$ar.' '.abs($d).'% к пр. мес.</span>';
    };
@endphp

<div id="kpi" class="an-card">
    <h6>📌 KPI текущего месяца</h6>
    <div class="kpi-row">
        <div class="kpi">
            <div class="l">Выручка</div>
            <div class="v">{{ $money($cur['revenue']) }} ₽</div>
            <div class="s">{!! $dlt($delta['revenue']) !!}</div>
        </div>
        <div class="kpi">
            <div class="l">Прибыль · маржа {{ round($cur['margin']) }}%</div>
            <div class="v {{ $cur['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($cur['profit']) }} ₽</div>
            <div class="s">{!! $dlt($delta['profit']) !!}</div>
        </div>
        <div class="kpi">
            <div class="l">Продано пар</div>
            <div class="v">{{ $cur['units'] }}</div>
            <div class="s">сделок {{ $cur['count'] }} · {!! $dlt($delta['units']) !!}</div>
        </div>
        <div class="kpi">
            <div class="l">Средний чек</div>
            <div class="v">{{ $cur['count'] ? $money($cur['avg']) : '—' }} ₽</div>
            <div class="s">{!! $dlt($delta['avg']) !!}</div>
        </div>
    </div>
</div>

{{-- === 1. Сезонность продаж === --}}
<div id="seasonality" class="an-card">
    <h6>Сезонность продаж — 12 месяцев <span class="text-muted small fw-normal">(столбики по брендам)</span></h6>
    <div class="bars">
        @foreach($seasonality as $m)
            <div class="bar-col" title="{{ $m['label'] }}: {{ $m['units'] }} пар">
                <div class="bar-wrap">
                    <div class="stack">
                        @php
                            $total = max(1, $m['units']);
                            $shown = 0;
                        @endphp
                        @foreach($brands as $bi => $b)
                            @php
                                $u = $m['by_brand'][$b] ?? 0;
                                $shown += $u;
                                $hPct = (int) round(($u / max(1, $seasMax)) * 100);
                            @endphp
                            @if($u > 0)
                                <div class="seg brand-palette-{{ $bi }}" style="height: {{ $hPct }}%" title="{{ $b }}: {{ $u }}"></div>
                            @endif
                        @endforeach
                        @php
                            $other = max(0, $m['units'] - $shown);
                            $hOther = (int) round(($other / max(1, $seasMax)) * 100);
                        @endphp
                        @if($other > 0)
                            <div class="seg brand-palette-5" style="height: {{ $hOther }}%" title="Прочее: {{ $other }}"></div>
                        @endif
                    </div>
                </div>
                <div class="bar-lbl">{{ $m['label'] }}<br><b>{{ $m['units'] }}</b></div>
            </div>
        @endforeach
    </div>
    @if(!empty($brands))
        <div class="mt-2 small d-flex flex-wrap gap-3">
            @foreach($brands as $bi => $b)
                <span><span class="d-inline-block brand-palette-{{ $bi }}" style="width:.9rem;height:.9rem;border-radius:3px;vertical-align:middle;"></span> {{ $b }}</span>
            @endforeach
            <span><span class="d-inline-block brand-palette-5" style="width:.9rem;height:.9rem;border-radius:3px;vertical-align:middle;"></span> Прочее</span>
        </div>
    @endif
</div>

{{-- === 2. Закупки vs продажи по месяцам === --}}
<div id="flow" class="an-card">
    <h6>Закупки vs продажи <span class="text-muted small fw-normal">(<span class="text-success">закуплено</span> / <span class="text-danger">продано</span>)</span></h6>
    <div class="bars">
        @foreach($flow as $m)
            <div class="bar-col" title="{{ $m['label'] }}: закупили {{ $m['purchased'] }}, продали {{ $m['sold'] }}">
                <div class="bar-wrap">
                    <div class="bar b-purchased" style="height: {{ (int) round($m['purchased'] / max(1,$flowMax) * 100) }}%"></div>
                    <div class="bar b-sold" style="height: {{ (int) round($m['sold'] / max(1,$flowMax) * 100) }}%"></div>
                </div>
                <div class="bar-lbl">{{ $m['label'] }}<br><b class="text-success">{{ $m['purchased'] }}</b> / <b class="text-danger">{{ $m['sold'] }}</b></div>
            </div>
        @endforeach
    </div>
</div>

<div id="turnover" class="row g-3">
    {{-- === 3. Быстро оборачивающиеся === --}}
    <div class="col-lg-6">
        <div class="an-card h-100">
            <h6>🚀 Быстро оборачиваются (продано за 90 дней)</h6>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Модель</th><th class="text-end">Остаток</th><th class="text-end">Продано</th><th class="text-end">пар/день</th><th class="text-end">Дней до 0</th></tr></thead>
                <tbody>
                @forelse($fastMovers as $t)
                    <tr>
                        <td>{{ $t['name'] }}</td>
                        <td class="text-end">{{ $t['stock'] }}</td>
                        <td class="text-end">{{ $t['sold_90d'] }}</td>
                        <td class="text-end">{{ number_format($t['per_day'], 2, ',', '') }}</td>
                        <td class="text-end fw-semibold {{ ($t['days_left'] ?? 999) <= 14 ? 'text-danger' : '' }}">{{ $t['days_left'] ?? '∞' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-muted small text-center py-3">За 90 дней продаж не было.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>

    {{-- === 4. Мёртвый склад === --}}
    <div class="col-lg-6">
        <div class="an-card h-100">
            <h6>💤 Мёртвый склад <span class="text-muted small fw-normal">(нет продаж 90+ дней)</span></h6>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Модель</th><th class="text-end">Остаток</th></tr></thead>
                <tbody>
                @forelse($slowMovers as $t)
                    <tr>
                        <td>{{ $t['name'] }}</td>
                        <td class="text-end">{{ $t['stock'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted small text-center py-3">Всё в движении — залежей нет.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>

{{-- === 5. Скоро закончатся === --}}
<div class="an-card">
    <h6>⏳ Скоро закончатся <span class="text-muted small fw-normal">(при текущей скорости — за 21 день)</span></h6>
    <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
        <thead><tr><th>Модель</th><th class="text-end">Остаток</th><th class="text-end">Скорость (пар/день)</th><th class="text-end">Осталось дней</th></tr></thead>
        <tbody>
        @forelse($endingSoon as $t)
            <tr>
                <td>{{ $t['name'] }}</td>
                <td class="text-end">{{ $t['stock'] }}</td>
                <td class="text-end">{{ number_format($t['per_day'], 2, ',', '') }}</td>
                <td class="text-end fw-bold text-danger">≈ {{ $t['days_left'] }} дн.</td>
            </tr>
        @empty
            <tr><td colspan="4" class="text-muted small text-center py-3">На ближайшие 3 недели дефицита не ожидается.</td></tr>
        @endforelse
        </tbody>
    </table></div>
</div>

{{-- === Прогноз выручки на следующий месяц === --}}
<div id="forecast" class="an-card" style="background:linear-gradient(135deg, rgba(99,102,241,.08), rgba(139,92,246,.05));">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h6 class="mb-1">🔮 Прогноз выручки на следующий месяц</h6>
            <div class="text-muted small">по средним темпам последних 3 месяцев + сезонный коэффициент</div>
        </div>
        <div style="text-align:right">
            <div style="font-size:2.4rem;font-weight:800;line-height:1;letter-spacing:-.02em">{{ $money($forecastNextMonth) }} ₽</div>
            <div class="small text-muted">коэф. сезонности: ×{{ number_format($seasCoef, 2, '.', '') }} · тренд: {{ $money($trendAvg) }} ₽/мес</div>
        </div>
    </div>
</div>

{{-- === Клиентская аналитика === --}}
<div id="clients" class="an-card">
    <h6>👥 Клиентская аналитика <span class="text-muted small fw-normal">(за 12 месяцев)</span></h6>
    <div class="kpi-row">
        <div class="kpi"><div class="l">Всего клиентов</div><div class="v">{{ $totalClients }}</div><div class="s">уникальных</div></div>
        <div class="kpi"><div class="l">Повторные</div><div class="v">{{ $repeatClients }}</div><div class="s">{{ $totalClients ? round($repeatClients / $totalClients * 100) : 0 }}% retention</div></div>
        <div class="kpi"><div class="l">Заказов на клиента</div><div class="v">{{ number_format($avgOrdersPerClient, 1, ',', '') }}</div><div class="s">среднее</div></div>
        <div class="kpi"><div class="l">Средняя выручка/клиент</div><div class="v">{{ $money($avgClientRevenue) }} ₽</div><div class="s">LTV косвенно</div></div>
    </div>
    <div class="mt-3">
        <div class="fw-semibold small mb-2">🏆 Топ покупателей</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>#</th><th>Клиент</th><th>Телефон</th><th class="text-end">Заказов</th><th class="text-end">Пар</th><th class="text-end">Выручка</th><th>Последняя покупка</th></tr></thead>
            <tbody>
            @forelse($topClients as $i => $c)
                <tr>
                    <td class="text-muted small">{{ $i + 1 }}</td>
                    <td>{{ $c['name'] ?: '—' }} @if($c['orders'] >= 2)<span class="badge text-bg-success" style="font-size:.65rem">повторный</span>@endif</td>
                    <td class="small">{{ $c['phone'] }}</td>
                    <td class="text-end">{{ $c['orders'] }}</td>
                    <td class="text-end">{{ $c['units'] }}</td>
                    <td class="text-end fw-semibold">{{ $money($c['revenue']) }} ₽</td>
                    <td class="small text-muted">{{ $c['last_at'] ? $c['last_at']->format('d.m.Y') : '—' }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-muted small text-center py-3">Данных пока нет.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>

{{-- === Финансовые метрики === --}}
<div id="finance" class="an-card">
    <h6>💰 Финансовые метрики</h6>
    <div class="kpi-row">
        <div class="kpi"><div class="l">Оборотный капитал</div><div class="v">{{ $money($workingCapital) }} ₽</div><div class="s">заморожено в товаре</div></div>
        <div class="kpi"><div class="l">Средняя наценка</div><div class="v">{{ round($avgMarkup) }}%</div><div class="s">по позициям с ценой</div></div>
        <div class="kpi"><div class="l">Sell-through</div><div class="v">{{ round($sellThrough) }}%</div><div class="s">продано / (продано + остаток)</div></div>
        <div class="kpi"><div class="l">Дней оборота склада</div><div class="v">{{ $dio !== null ? $dio.' дн' : '—' }}</div><div class="s">DIO ≈ остаток / средн. продажи</div></div>
    </div>
    <div class="mt-3">
        <div class="fw-semibold small mb-2">Маржа по категориям (за 12 мес)</div>
        <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
            <thead><tr><th>Категория</th><th class="text-end">Пар</th><th class="text-end">Выручка</th><th class="text-end">Прибыль</th><th class="text-end">Маржа %</th></tr></thead>
            <tbody>
            @forelse($marginByCategory as $c)
                <tr>
                    <td>{{ $c['label'] }}</td>
                    <td class="text-end">{{ $c['units'] }}</td>
                    <td class="text-end">{{ $money($c['revenue']) }}</td>
                    <td class="text-end {{ $c['profit'] >= 0 ? 'text-success' : 'text-danger' }}">{{ $money($c['profit']) }}</td>
                    <td class="text-end fw-semibold">{{ round($c['margin']) }}%</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted small text-center py-3">Продаж по категориям пока нет.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>
</div>

{{-- === Heatmap: день недели × час === --}}
<div id="heatmap" class="an-card">
    <h6>🔥 Тепловая карта продаж <span class="text-muted small fw-normal">(день недели × час)</span></h6>
    <div style="overflow-x:auto">
        <table style="border-collapse:separate;border-spacing:2px;min-width:520px;">
            <thead><tr><th></th>
                @for($h = 0; $h < 24; $h++)
                    <th style="font-size:.62rem;color:var(--crm-muted);font-weight:600;text-align:center;width:22px;">{{ $h }}</th>
                @endfor
            </tr></thead>
            <tbody>
                @foreach($dayNames as $d => $dn)
                    <tr>
                        <td style="font-size:.72rem;color:var(--crm-muted);padding-right:.4rem;text-align:right;font-weight:600;">{{ $dn }}</td>
                        @for($h = 0; $h < 24; $h++)
                            @php
                                $v = $heatmap[$d][$h];
                                $opacity = $heatmapMax > 0 ? min(1, $v / $heatmapMax) : 0;
                                $bg = $v > 0 ? "rgba(99,102,241, " . number_format(max(0.1, $opacity), 2, '.', '') . ")" : 'rgba(148,163,184,.08)';
                            @endphp
                            <td style="width:22px;height:22px;background:{{ $bg }};border-radius:3px;text-align:center;font-size:.6rem;color:{{ $opacity > 0.5 ? '#fff' : 'var(--crm-muted)' }};" title="{{ $dn }} {{ $h }}:00 — {{ $v }} прод.">{{ $v > 0 ? $v : '' }}</td>
                        @endfor
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="small text-muted mt-2">Наведите на клетку — увидите точное количество. Чем ярче — тем больше продаж в этот час/день.</div>
</div>

{{-- === Разбивка по категории/полу/сезону === --}}
<div id="taxonomy" class="an-card">
    <h6>🧩 Структура склада и продаж</h6>
    <div class="row g-3">
        @foreach(['category' => 'Категории', 'gender' => 'Пол', 'season' => 'Сезон'] as $tax => $title)
            <div class="col-lg-4">
                <div class="fw-semibold small mb-2">{{ $title }}</div>
                <table class="table table-sm mb-0 align-middle">
                    <thead><tr><th></th><th class="text-end">На складе</th><th class="text-end">Продано 12 мес</th></tr></thead>
                    <tbody>
                    @foreach($taxonomy[$tax]['options'] as $key => $label)
                        <tr>
                            <td>{{ $label }}</td>
                            <td class="text-end">{{ $taxonomy[$tax]['stock'][$key] ?? 0 }}</td>
                            <td class="text-end">{{ $taxonomy[$tax]['sold'][$key] ?? 0 }}</td>
                        </tr>
                    @endforeach
                    @if($taxonomy[$tax]['unset'] > 0)
                        <tr class="text-muted small">
                            <td>— не указано —</td>
                            <td class="text-end">{{ $taxonomy[$tax]['unset'] }}</td>
                            <td class="text-end">—</td>
                        </tr>
                    @endif
                    </tbody>
                </table>
            </div>
        @endforeach
    </div>
</div>

{{-- === 6. ABC-анализ === --}}
<div id="abc" class="an-card">
    <h6>🎯 ABC-анализ моделей по прибыли за 12 месяцев <span class="text-muted small fw-normal">(A — 80% прибыли, B — ещё 15%, C — хвост)</span></h6>
    <div class="row g-3">
        @foreach(['A','B','C'] as $klass)
            <div class="col-md-4">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <span class="abc-badge abc-{{ $klass }}">{{ $klass }}</span>
                    <span class="text-muted small">{{ count($abc[$klass]) }} моделей</span>
                </div>
                <ul class="mb-0 ps-0" style="list-style:none;">
                @forelse($abc[$klass] as $m)
                    <li class="d-flex justify-content-between py-1" style="border-bottom:1px dashed var(--crm-border);font-size:.85rem;">
                        <span>{{ $m['name'] }}</span>
                        <span class="text-muted small">{{ $money($m['profit']) }} ₽ · {{ round($m['share'] * 100, 1) }}%</span>
                    </li>
                @empty
                    <li class="text-muted small">—</li>
                @endforelse
                </ul>
            </div>
        @endforeach
    </div>
</div>

<div class="row g-3">
    {{-- === 7. Топ размеров === --}}
    <div class="col-lg-6">
        <div class="an-card h-100">
            <h6>👟 Размеры: продажи vs остаток <span class="text-muted small fw-normal">(<span class="text-danger">продано 12 мес</span> / <span style="color:#4f46e5">на складе</span>)</span></h6>
            <div class="size-row">
                @foreach($sizesData as $s)
                    <div class="size-col" title="р.{{ $s['size'] }} · продано {{ $s['sold'] }}, на складе {{ $s['stock'] }}">
                        <div class="size-bar-wrap">
                            <div class="size-bar sold" style="height: {{ (int) round($s['sold'] / max(1, $sizeMax) * 100) }}%"></div>
                            <div class="size-bar stock" style="height: {{ (int) round($s['stock'] / max(1, $sizeMax) * 100) }}%"></div>
                        </div>
                        <div class="size-lbl">{{ $s['size'] }}</div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- === 8. Топ брендов === --}}
    <div class="col-lg-6" id="brands">
        <div class="an-card h-100">
            <h6>🏷️ Топ брендов на складе</h6>
            <div class="table-responsive"><table class="table table-sm mb-0 align-middle">
                <thead><tr><th>Бренд</th><th class="text-end">Пар на складе</th><th class="text-end">Стоимость</th><th class="text-end">Продано (12 мес)</th></tr></thead>
                <tbody>
                @forelse($brandStock as $b)
                    <tr>
                        <td>{{ $b['brand'] }}</td>
                        <td class="text-end">{{ $b['stock_units'] }}</td>
                        <td class="text-end">{{ $money($b['stock_value']) }} ₽</td>
                        <td class="text-end">{{ $b['sold_units_12m'] }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="text-muted small text-center py-3">Данных пока нет.</td></tr>
                @endforelse
                </tbody>
            </table></div>
        </div>
    </div>
</div>
@endsection
