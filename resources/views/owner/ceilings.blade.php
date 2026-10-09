@extends('layouts.app')

@section('content')
<style>
    .own-wrap{ max-width:1280px; margin:0 auto; }
    .own-hero{ display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,1fr)); gap:.7rem; margin-bottom:.8rem; }
    .own-stat{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:16px; padding:.85rem 1.05rem; box-shadow:var(--crm-shadow); }
    .own-stat .l{ font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--crm-muted); font-weight:700; }
    .own-stat .v{ font-size:1.8rem; font-weight:800; line-height:1.15; letter-spacing:-.02em; margin-top:.1rem; }
    .own-stat .s{ font-size:.8rem; color:var(--crm-muted); margin-top:.15rem; }
    .own-stat.green .v{ color:#10b981; }
    .delta{ font-weight:700; font-size:.78rem; }
    .delta.up{ color:#10b981; } .delta.down{ color:#ef4444; } .delta.flat{ color:var(--crm-muted); }

    .own-kpi{ display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:.7rem; margin-bottom:1rem; }
    .own-kpi .k{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:14px; padding:.6rem .85rem; }
    .own-kpi .k .l{ font-size:.68rem; text-transform:uppercase; letter-spacing:.05em; color:var(--crm-muted); font-weight:600; }
    .own-kpi .k .v{ font-size:1.3rem; font-weight:800; letter-spacing:-.02em; }

    .own-card{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:16px; box-shadow:var(--crm-shadow); margin-bottom:1rem; }
    .own-card .hd{ padding:.7rem 1.05rem; font-weight:700; border-bottom:1px solid var(--crm-border); display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:.5rem; }
    .own-card .bd{ padding:.8rem 1.05rem; }
    .own-card table{ width:100%; border-collapse:collapse; }
    .own-card th, .own-card td{ padding:.4rem .8rem; font-size:.86rem; border-bottom:1px solid var(--crm-border); }
    .own-card th{ font-size:.7rem; text-transform:uppercase; letter-spacing:.04em; color:var(--crm-muted); text-align:left; }
    .own-card tr:last-child td{ border-bottom:0; }
    .own-card tr.grp td{ font-weight:700; background:rgba(127,127,127,.06); }
    .own-card tr.sub td:first-child{ padding-left:1.8rem; color:var(--crm-muted); }
    .tbl-scroll{ overflow-x:auto; }
    .num{ text-align:right; white-space:nowrap; }

    .chips{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; }
    .chips a{ padding:.25rem .7rem; border-radius:999px; border:1px solid var(--crm-border); font-size:.82rem; text-decoration:none; color:inherit; }
    .chips a.on{ background:#2563eb; border-color:#2563eb; color:#fff; }

    .bars{ display:flex; align-items:flex-end; gap:3px; height:170px; padding-top:.5rem; }
    .bars .col{ flex:1 1 0; min-width:4px; display:flex; flex-direction:column; justify-content:flex-end; align-items:stretch; height:100%; position:relative; }
    .bars .b{ border-radius:3px 3px 0 0; }
    .bars .b.leads{ background:#93c5fd; }
    .bars .b.book{ background:#10b981; margin-top:-1px; }
    .bars-x{ display:flex; gap:3px; font-size:.65rem; color:var(--crm-muted); margin-top:.25rem; }
    .bars-x span{ flex:1 1 0; min-width:4px; text-align:center; overflow:hidden; white-space:nowrap; }
    .legend{ display:flex; gap:1rem; font-size:.8rem; color:var(--crm-muted); }
    .legend i{ display:inline-block; width:.7rem; height:.7rem; border-radius:3px; margin-right:.3rem; vertical-align:-1px; }
    .note{ font-size:.8rem; color:var(--crm-muted); }
</style>

@php
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $pct = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ').'%';
    $delta = function ($cur, $prev, bool $points = false) {
        if ($prev === null || $cur === null) return '';
        if ($points) {
            $d = $cur - $prev;
            $cls = abs($d) < 0.05 ? 'flat' : ($d > 0 ? 'up' : 'down');
            return '<span class="delta '.$cls.'">'.($d > 0 ? '+' : '').number_format($d, 1, ',', ' ').' п.п.</span>';
        }
        if ((float) $prev == 0.0) return $cur > 0 ? '<span class="delta up">новое</span>' : '';
        $d = ($cur - $prev) / $prev * 100;
        $cls = abs($d) < 0.5 ? 'flat' : ($d > 0 ? 'up' : 'down');
        return '<span class="delta '.$cls.'">'.($d > 0 ? '+' : '').number_format($d, 0, ',', ' ').'%</span>';
    };
    $prev = $kpi['prev'];
    $calls = $kpi['calls'];
    $days = (int) round(($kpi['to']->getTimestamp() - $kpi['from']->getTimestamp()) / 86400);
    $series = $kpi['series'];
    $maxBar = max(1, collect($series['rows'])->max('leads') ?? 0, collect($series['rows'])->max('bookings') ?? 0);
@endphp

<div class="own-wrap">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4 class="mb-0" style="letter-spacing:-.02em">📊 Сводка бизнеса · Потолки</h4>
            <div class="text-muted small">колл-центр: лиды, замеры, звонки, каналы и операторы</div>
        </div>
        @include('owner._switch', ['active' => 'ceilings'])
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div class="chips">
            @foreach($periods as $key => $label)
                <a href="{{ route('owner.ceilings', ['period' => $key]) }}" class="{{ $period === $key ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('owner.ceilings') }}" class="d-flex gap-2 align-items-center">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="from" value="{{ $fromValue }}" class="form-control form-control-sm" style="width:auto">
            <span class="text-muted">—</span>
            <input type="date" name="to" value="{{ $toValue }}" class="form-control form-control-sm" style="width:auto">
            <button class="btn btn-sm btn-outline-primary">Показать</button>
        </form>
    </div>

    <div class="own-hero">
        <div class="own-stat">
            <div class="l">Новые лиды</div>
            <div class="v">{{ $n($kpi['leads']) }}</div>
            <div class="s">{!! $delta($kpi['leads'], $prev['leads']) !!} к прошлым {{ $days }} дн. ({{ $n($prev['leads']) }})</div>
        </div>
        <div class="own-stat green">
            <div class="l">Замеров назначено</div>
            <div class="v">{{ $n($kpi['bookings']) }}</div>
            <div class="s">{!! $delta($kpi['bookings'], $prev['bookings']) !!} к прошлым {{ $days }} дн. ({{ $n($prev['bookings']) }})</div>
        </div>
        <div class="own-stat">
            <div class="l">Конверсия лид → замер</div>
            <div class="v">{{ $pct($kpi['conversion']) }}</div>
            <div class="s">{!! $delta($kpi['conversion'], $prev['conversion'], true) !!} · из целевых {{ $pct($kpi['conversion_targeted']) }}</div>
        </div>
        <div class="own-stat">
            <div class="l">Входящие звонки</div>
            <div class="v">{{ $n($calls['incoming']) }}</div>
            <div class="s">{!! $delta($calls['incoming'], $prev['incoming']) !!} · пропущено {{ $n($calls['missed']) }}@if($calls['incoming'] > 0) ({{ $pct($calls['missed'] / $calls['incoming'] * 100) }})@endif</div>
        </div>
    </div>

    <div class="own-kpi">
        <div class="k"><div class="l">Дошли до замера</div><div class="v">{{ $n($kpi['booked_cohort']) }}</div></div>
        <div class="k"><div class="l">Нецелевые</div><div class="v">{{ $n($kpi['non_target']) }}</div></div>
        <div class="k"><div class="l">Отказ</div><div class="v">{{ $n($kpi['lost']) }}</div></div>
        <div class="k"><div class="l">Ещё в работе</div><div class="v">{{ $n($kpi['open']) }}</div></div>
        <div class="k"><div class="l">Принято звонков</div><div class="v">{{ $n($calls['answered']) }}</div></div>
        <div class="k"><div class="l">Исходящие звонки</div><div class="v">{{ $n($calls['outgoing']) }}</div></div>
    </div>

    <div class="own-card">
        <div class="hd">
            <span>{{ $series['by'] === 'month' ? 'По месяцам' : 'По дням' }}</span>
            <span class="legend"><span><i style="background:#93c5fd"></i>лиды</span><span><i style="background:#10b981"></i>замеры назначены</span></span>
        </div>
        <div class="bd">
            <div class="bars">
                @foreach($series['rows'] as $r)
                    <div class="col" title="{{ $r['key'] }}: лидов {{ $r['leads'] }}, замеров {{ $r['bookings'] }}, входящих звонков {{ $r['calls'] }}">
                        <div class="b leads" style="height:{{ round($r['leads'] / $maxBar * 100, 2) }}%"></div>
                        <div class="b book" style="height:{{ round($r['bookings'] / $maxBar * 100, 2) }}%; position:absolute; bottom:0; left:25%; right:25%"></div>
                    </div>
                @endforeach
            </div>
            <div class="bars-x">
                @foreach($series['rows'] as $i => $r)
                    <span>@if($series['by'] === 'month'){{ \Carbon\Carbon::parse($r['key'].'-01')->translatedFormat('M') }}@elseif(count($series['rows']) <= 16 || $i % 3 === 0){{ \Carbon\Carbon::parse($r['key'])->format('d') }}@endif</span>
                @endforeach
            </div>
        </div>
    </div>

    <div class="own-card">
        <div class="hd"><span>Каналы — откуда лиды</span><span class="note">расходы на рекламу и цена лида появятся после подключения рекламных кабинетов</span></div>
        <div class="tbl-scroll">
            <table>
                <thead><tr><th>Канал</th><th class="num">Лиды</th><th class="num">Дошли до замера</th><th class="num">Конверсия</th><th class="num">Нецелевые</th></tr></thead>
                <tbody>
                @foreach($kpi['channels'] as $g)
                    <tr class="grp">
                        <td>{{ $g['label'] }}</td>
                        <td class="num">{{ $n($g['leads']) }}</td>
                        <td class="num">{{ $n($g['booked']) }}</td>
                        <td class="num">{{ $pct($g['conversion']) }}</td>
                        <td class="num">{{ $n($g['non_target']) }}</td>
                    </tr>
                    @if(count($g['sources']) > 1)
                        @foreach($g['sources'] as $s)
                            <tr class="sub">
                                <td>{{ $s['label'] }}</td>
                                <td class="num">{{ $n($s['leads']) }}</td>
                                <td class="num">{{ $n($s['booked']) }}</td>
                                <td class="num">{{ $pct($s['conversion']) }}</td>
                                <td class="num">{{ $n($s['non_target']) }}</td>
                            </tr>
                        @endforeach
                    @elseif(count($g['sources']) === 1 && $g['sources'][0]['label'] !== $g['label'])
                        <tr class="sub"><td colspan="5">{{ $g['sources'][0]['label'] }}</td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="own-card">
        <div class="hd"><span>Операторы</span><span class="note">по действиям за период: кто двигал и закрывал сделки</span></div>
        <div class="tbl-scroll">
            <table>
                <thead><tr><th>Сотрудник</th><th class="num">Обработано сделок</th><th class="num">Замеров назначено</th><th class="num">Замеров из обработанных</th><th class="num">Нецелевые</th><th class="num">Отказы</th></tr></thead>
                <tbody>
                @forelse($kpi['operators'] as $o)
                    <tr>
                        <td>{{ $o['name'] }}</td>
                        <td class="num">{{ $n($o['handled']) }}</td>
                        <td class="num">{{ $n($o['bookings']) }}</td>
                        <td class="num">{{ $pct($o['conversion']) }}</td>
                        <td class="num">{{ $n($o['non_target']) }}</td>
                        <td class="num">{{ $n($o['lost']) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-muted">За период по сделкам никто не работал.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <p class="note">
        Как считается. <b>Лид</b> — новая сделка в колл-центре потолков за период. <b>Замер назначен</b> — сделка впервые
        попала на этап «Замер назначен» или закрыта «Успешно»; «замеров назначено» — такие события за период, по сделкам любого
        возраста. <b>Конверсия</b> — доля лидов периода, которые уже дошли до замера (у свежих лидов она ещё растёт).
        <b>Канал</b> — самый ранний сигнал по сделке: чат, заявка с сайта, импорт или звонок на рекламный номер.
        <b>Звонки</b> — по событиям Мегафона: пропущенный — входящий, который никто не принял.
        <b>Операторы</b> — по действиям: замер засчитан тому, кто перевёл сделку на «Замер назначен» (или закрыл «Успешно»)
        первым; «обработано» — сделки, которые сотрудник двигал по этапам или закрывал за период.
    </p>
</div>
@endsection
