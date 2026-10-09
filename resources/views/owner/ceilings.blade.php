@extends('layouts.app')

@section('content')
<style>
    .own-wrap{ max-width:1280px; margin:0 auto; }
    .own-title{ font-size:1.5rem; font-weight:700; letter-spacing:-.02em; margin:0; }
    .own-hero{ display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,200px),1fr)); gap:.7rem; margin-bottom:.8rem; }
    .own-stat{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:16px; padding:.85rem 1.05rem; box-shadow:var(--crm-shadow); min-width:0; }
    .own-stat .l{ font-size:.7rem; text-transform:uppercase; letter-spacing:.06em; color:var(--crm-muted); font-weight:700; }
    .own-stat .v{ font-size:1.8rem; font-weight:800; line-height:1.15; letter-spacing:-.02em; margin-top:.1rem; }
    .own-stat .s{ font-size:.8rem; color:var(--crm-muted); margin-top:.15rem; }
    .own-stat.green .v{ color:#10b981; }
    .delta{ font-weight:700; font-size:.78rem; }
    .delta.up{ color:#10b981; } .delta.down{ color:#ef4444; } .delta.flat{ color:var(--crm-muted); }

    .own-kpi{ display:grid; grid-template-columns:repeat(auto-fit,minmax(min(100%,150px),1fr)); gap:.7rem; margin-bottom:1rem; }
    .own-kpi .k{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:14px; padding:.6rem .85rem; min-width:0; }
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
    .num{ text-align:right; white-space:nowrap; }

    .src-grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(min(100%,210px),1fr)); gap:.7rem; }
    .src{ border:1px solid var(--crm-border); border-radius:14px; padding:.7rem .9rem; min-width:0; background:var(--crm-surface-strong); }
    .src.paid{ border-color:rgba(37,99,235,.35); }
    .src .name{ font-weight:700; font-size:.9rem; overflow-wrap:anywhere; }
    .src .cnt{ font-size:1.6rem; font-weight:800; letter-spacing:-.02em; color:#10b981; line-height:1.2; }
    .src .cnt small{ font-size:.75rem; font-weight:600; color:var(--crm-muted); }
    .src dl{ display:grid; grid-template-columns:auto 1fr; gap:.1rem .6rem; margin:.35rem 0 0; font-size:.8rem; }
    .src dt{ color:var(--crm-muted); font-weight:400; }
    .src dd{ margin:0; text-align:right; font-weight:600; white-space:nowrap; }

    .chips{ display:flex; gap:.35rem; flex-wrap:wrap; align-items:center; }
    .chips a{ padding:.25rem .7rem; border-radius:999px; border:1px solid var(--crm-border); font-size:.82rem; text-decoration:none; color:inherit; }
    .chips a.on{ background:#2563eb; border-color:#2563eb; color:#fff; }
    .range{ display:flex; gap:.5rem; align-items:center; flex-wrap:wrap; }
    .range input{ width:auto; max-width:150px; }

    .bars{ display:flex; align-items:flex-end; justify-content:center; gap:3px; height:170px; padding-top:.5rem; }
    .bars .col{ flex:1 1 0; min-width:3px; max-width:56px; display:flex; flex-direction:column; justify-content:flex-end; align-items:stretch; height:100%; position:relative; }
    .bars .b{ border-radius:3px 3px 0 0; }
    .bars .b.leads{ background:#93c5fd; }
    .bars .b.book{ background:#10b981; position:absolute; bottom:0; left:25%; right:25%; }
    .bars-x{ display:flex; justify-content:center; gap:3px; font-size:.65rem; color:var(--crm-muted); margin-top:.25rem; }
    .bars-x span{ flex:1 1 0; min-width:3px; max-width:56px; text-align:center; overflow:hidden; white-space:nowrap; }
    .legend{ display:flex; gap:1rem; font-size:.8rem; color:var(--crm-muted); flex-wrap:wrap; }
    .legend i{ display:inline-block; width:.7rem; height:.7rem; border-radius:3px; margin-right:.3rem; vertical-align:-1px; }
    .note{ font-size:.8rem; color:var(--crm-muted); }

    /* телефон: таблицы складываются в карточки строк, крупные цифры меньше */
    @media (max-width: 575.98px){
        .own-title{ font-size:1.2rem; }
        .own-stat{ padding:.7rem .85rem; }
        .own-stat .v{ font-size:1.45rem; }
        .own-card .hd, .own-card .bd{ padding:.6rem .75rem; }
        .bars{ height:120px; gap:2px; }
        .bars-x{ gap:2px; }
        table.stack thead{ display:none; }
        table.stack tr{ display:grid; grid-template-columns:1fr 1fr; gap:.1rem .6rem; padding:.5rem .75rem; border-bottom:1px solid var(--crm-border); }
        table.stack tr:last-child{ border-bottom:0; }
        table.stack td{ border:0; padding:0; font-size:.82rem; }
        table.stack td:first-child{ grid-column:1 / -1; font-weight:700; font-size:.88rem; }
        table.stack td.num{ text-align:left; white-space:normal; }
        table.stack td[data-l]::before{ content:attr(data-l) ': '; color:var(--crm-muted); }
    }
</style>

@php
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $pct = fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ').'%';
    $plural = function (int $k, string $one, string $few, string $many) {
        $m10 = $k % 10; $m100 = $k % 100;
        return ($m10 === 1 && $m100 !== 11) ? $one : (($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) ? $few : $many);
    };
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' ₽';
    $per = fn ($spend, $cnt) => ($spend !== null && $cnt > 0) ? number_format($spend / $cnt, 0, ',', ' ').' ₽' : '—';
    $delta = function ($cur, $prev) {
        if ($prev === null || $cur === null) return '';
        if ((float) $prev == 0.0) return $cur > 0 ? '<span class="delta up">новое</span>' : '';
        $d = ($cur - $prev) / $prev * 100;
        $cls = abs($d) < 0.5 ? 'flat' : ($d > 0 ? 'up' : 'down');
        return '<span class="delta '.$cls.'">'.($d > 0 ? '+' : '').number_format($d, 0, ',', ' ').'%</span>';
    };
    $prev = $kpi['prev'];
    $calls = $kpi['calls'];
    $days = (int) round(($kpi['to']->getTimestamp() - $kpi['from']->getTimestamp()) / 86400);
    $series = $kpi['series'];
    $spendTotal = (float) $ads['spend_total'];
    $vk = $ads['vk']; $di = $ads['direct']; $av = $ads['avito'];
    $adsStatus = collect($ads['sources'])->map(function ($s) {
        if (! $s['enabled']) return ['text' => $s['label'].' — не подключено', 'warn' => false];
        if ($s['last_error']) {
            return ['text' => $s['label'].' — ошибка: '.\Illuminate\Support\Str::limit($s['last_error'], 120), 'warn' => true];
        }
        if (! $s['last_ok_at']) return ['text' => $s['label'].' — ещё не собиралось', 'warn' => true];
        $stale = $s['last_ok_at']->lt(now()->subHours(26));
        return ['text' => $s['label'].' — '.($stale ? 'устарело, последний сбор ' : '').$s['last_ok_at']->format($s['last_ok_at']->isToday() ? 'H:i' : 'd.m H:i'), 'warn' => $stale];
    })->values();

    // Замеры — по таблице замеров (решение владельца 09.10.2026); если за период в таблице пусто — по этапам CRM
    $ms = $measures;
    $useSheet = $ms['available'];
    $mTotal = $useSheet ? $ms['total'] : $kpi['bookings'];
    $mPrev = $useSheet ? $ms['prev_total'] : $prev['bookings'];
    $mSourceNote = $useSheet ? 'по таблице замеров' : 'по CRM — в таблице замеров за период пусто';
    $mCoverage = ($useSheet && $ms['days'] < $ms['period_days']) ? 'в таблице замеров есть '.$ms['days'].' из '.$ms['period_days'].' дн. периода' : null;

    $chartRows = [];
    foreach ($series['rows'] as $r) {
        $m = 0;
        if ($useSheet) {
            foreach ($ms['by_day'] as $d => $v) {
                if ($series['by'] === 'month' ? str_starts_with($d, $r['key']) : $d === $r['key']) {
                    $m += $v;
                }
            }
        } else {
            $m = $r['bookings'];
        }
        $chartRows[] = $r + ['m' => $m];
    }
    $maxBar = max(1, collect($chartRows)->max('leads') ?? 0, collect($chartRows)->max('m') ?? 0);
    $showChart = count($chartRows) >= 2;   // за один день столбик ничего не добавляет к цифрам наверху
    $labelEvery = count($chartRows) <= 16 ? 1 : (count($chartRows) <= 40 ? 3 : 7);

    // Карточки источников — только те, где за период есть замеры. У платных (Директ, Авито, ВК) — лиды CRM этого
    // канала, расход, цена лида и замера.
    $groups = collect($kpi['channels'])->keyBy('key');
    $primary = ['директ' => 'direct', 'авито' => 'avito', 'вк' => 'vk'];
    $sourceCards = [];
    if ($useSheet) {
        foreach ($ms['by_source'] as $gkey => $list) {
            foreach ($list as $name => $cnt) {
                if ($cnt <= 0) continue;
                $paidKey = $primary[mb_strtolower($name)] ?? null;
                $sourceCards[] = ['name' => $name, 'cnt' => $cnt, 'paid' => $paidKey];
            }
        }
    } else {
        foreach ($groups as $g) {
            if ($g['booked'] <= 0) continue;
            $sourceCards[] = ['name' => $g['label'], 'cnt' => $g['booked'], 'paid' => in_array($g['key'], ['direct', 'avito', 'vk'], true) ? $g['key'] : null];
        }
    }
    usort($sourceCards, fn ($a, $b) => $b['cnt'] <=> $a['cnt']);
    foreach ($sourceCards as &$c) {
        $c['leads'] = $c['paid'] ? (int) ($groups[$c['paid']]['leads'] ?? 0) : null;
        $c['non_target'] = $c['paid'] ? (int) ($groups[$c['paid']]['non_target'] ?? 0) : null;
        $c['spend'] = $c['paid'] ? ($ads['spend'][$c['paid']] ?? null) : null;
    }
    unset($c);

    $avSpend = $ads['spend']['avito'] ?? null;
    $avOther = ($avSpend['from'] ?? null) === 'API Авито' ? (float) ($av['spend_other'] ?? 0) : 0.0;

    $ncState = $nonclosures['state'];
    $ncData = $nonclosures['data'] ?? null;
    $ncUpdated = $ncData ? collect($ncData['updated'])->map(fn ($t, $k) => ($k === 'kc_sheet' ? 'таблица КЦ' : ($k === 'onec' ? '1С' : $k)).' — '.$t->format($t->isToday() ? 'H:i' : 'd.m H:i'))->implode(', ') : '';
@endphp

<div class="own-wrap">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4 class="own-title">📊 Сводка бизнеса · Потолки</h4>
            <div class="text-muted small">колл-центр: лиды, замеры, звонки, источники и операторы</div>
        </div>
        @include('owner._switch', ['active' => 'ceilings'])
    </div>

    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div class="chips">
            @foreach($periods as $key => $label)
                <a href="{{ route('owner.ceilings', ['period' => $key]) }}" class="{{ $period === $key ? 'on' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <form method="GET" action="{{ route('owner.ceilings') }}" class="range">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="from" value="{{ $fromValue }}" class="form-control form-control-sm">
            <span class="text-muted">—</span>
            <input type="date" name="to" value="{{ $toValue }}" class="form-control form-control-sm">
            <button class="btn btn-sm btn-outline-primary">Показать</button>
        </form>
    </div>

    <div class="own-hero">
        <div class="own-stat green">
            <div class="l">Замеры</div>
            <div class="v">{{ $n($mTotal) }}</div>
            <div class="s">@if($mPrev !== null){!! $delta($mTotal, $mPrev) !!} к прошлым {{ $days }} дн. ({{ $n($mPrev) }})@else прошлого периода в таблице нет целиком@endif · {{ $mSourceNote }}</div>
            @if($mCoverage)
                <div class="s text-danger">{{ $mCoverage }}</div>
            @endif
        </div>
        <div class="own-stat">
            <div class="l">Входящие звонки</div>
            <div class="v">{{ $n($calls['incoming']) }}</div>
            <div class="s">{!! $delta($calls['incoming'], $prev['incoming']) !!} · пропущено {{ $n($calls['missed']) }}</div>
        </div>
        @if($spendTotal > 0)
            <div class="own-stat">
                <div class="l">Расход на рекламу</div>
                <div class="v">{{ $money($spendTotal) }}</div>
                <div class="s">цена лида {{ $per($spendTotal, $kpi['leads']) }} · цена замера {{ $per($spendTotal, $mTotal) }}</div>
            </div>
        @endif
    </div>

    <div class="own-kpi">
        <div class="k"><div class="l">Нецелевые</div><div class="v">{{ $n($kpi['non_target']) }}</div></div>
        <div class="k"><div class="l">Отказ</div><div class="v">{{ $n($kpi['lost']) }}</div></div>
        <div class="k"><div class="l">Ещё в работе</div><div class="v">{{ $n($kpi['open']) }}</div></div>
        <div class="k"><div class="l">Принято звонков</div><div class="v">{{ $n($calls['answered']) }}</div></div>
        <div class="k"><div class="l">Исходящие звонки</div><div class="v">{{ $n($calls['outgoing']) }}</div></div>
    </div>

    <div class="own-card">
        <div class="hd"><span>Источники замеров</span><span class="note">только источники с замерами за период{{ $useSheet ? ', по таблице замеров' : '' }}</span></div>
        <div class="bd">
            @if(count($sourceCards) === 0)
                <div class="note">За период замеров нет.</div>
            @else
                <div class="src-grid">
                    @foreach($sourceCards as $c)
                        <div class="src {{ $c['paid'] ? 'paid' : '' }}">
                            <div class="name">{{ $c['name'] }}</div>
                            <div class="cnt">{{ $n($c['cnt']) }} <small>{{ $plural((int) $c['cnt'], 'замер', 'замера', 'замеров') }}</small></div>
                            @if($c['paid'])
                                <dl>
                                    <dt>лиды в CRM</dt><dd>{{ $n($c['leads']) }}</dd>
                                    <dt>нецелевые</dt><dd>{{ $n($c['non_target']) }}</dd>
                                    @if($c['spend'])
                                        <dt>расход</dt><dd title="по данным: {{ $c['spend']['from'] }}">{{ $money($c['spend']['value']) }}</dd>
                                        <dt>цена лида</dt><dd>{{ $per($c['spend']['value'], $c['leads']) }}</dd>
                                        <dt>цена замера</dt><dd>{{ $per($c['spend']['value'], $c['cnt']) }}</dd>
                                    @endif
                                </dl>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    @if($showChart)
    <div class="own-card">
        <div class="hd">
            <span>{{ $series['by'] === 'month' ? 'По месяцам' : 'По дням' }}</span>
            <span class="legend"><span><i style="background:#93c5fd"></i>лиды</span><span><i style="background:#10b981"></i>замеры{{ $useSheet ? ' (по таблице)' : '' }}</span></span>
        </div>
        <div class="bd">
            <div class="bars">
                @foreach($chartRows as $r)
                    <div class="col" title="{{ $r['key'] }}: лидов {{ $r['leads'] }}, замеров {{ $r['m'] }}, входящих звонков {{ $r['calls'] }}">
                        <div class="b leads" style="height:{{ round($r['leads'] / $maxBar * 100, 2) }}%"></div>
                        <div class="b book" style="height:{{ round($r['m'] / $maxBar * 100, 2) }}%"></div>
                    </div>
                @endforeach
            </div>
            <div class="bars-x">
                @foreach($chartRows as $i => $r)
                    <span>@if($series['by'] === 'month'){{ \Carbon\Carbon::parse($r['key'].'-01')->translatedFormat('M') }}@elseif($i % $labelEvery === 0){{ \Carbon\Carbon::parse($r['key'])->format('d') }}@endif</span>
                @endforeach
            </div>
        </div>
    </div>
    @endif

    <div class="own-card">
        <div class="hd"><span>Реклама по площадкам</span><span class="note">
            @foreach($adsStatus as $st)<span class="{{ $st['warn'] ? 'text-danger' : '' }}">{{ $st['text'] }}</span>@if(! $loop->last) · @endif @endforeach
        </span></div>
        <div class="bd">
            <div class="own-kpi" style="margin-bottom:0">
                <div class="k">
                    <div class="l">Яндекс Директ</div>
                    <div class="v">{{ $money($di['cost'] ?? null) }}</div>
                    <div class="note">клики {{ $n($di['clicks'] ?? 0) }} · показы {{ $n($di['impr'] ?? 0) }} · клик {{ $per($di['cost'] ?? null, (int) ($di['clicks'] ?? 0)) }}</div>
                </div>
                <div class="k">
                    <div class="l">VK Реклама</div>
                    <div class="v">{{ $money($vk['spent'] ?? null) }}</div>
                    <div class="note">показы {{ $n($vk['shows'] ?? 0) }} · клики {{ $n($vk['clicks'] ?? 0) }} · просмотры 3с {{ $n($vk['views3'] ?? 0) }} · вступления {{ $n($vk['joins'] ?? 0) }}</div>
                </div>
                <div class="k">
                    <div class="l">Авито</div>
                    <div class="v" title="{{ $avSpend ? 'по данным: '.$avSpend['from'] : 'расхода нет ни в API, ни в таблице' }}">{{ $money($avSpend['value'] ?? null) }}</div>
                    <div class="note">контакты {{ $n($av['contacts'] ?? 0) }} · просмотры {{ $n($av['views'] ?? 0) }} · контакт {{ $per($avSpend['value'] ?? null, (int) ($av['contacts'] ?? 0)) }}@if($avOther > 0) · в т.ч. тариф и прочее {{ $money($avOther) }}@endif</div>
                </div>
            </div>
        </div>
    </div>

    <div class="own-card">
        <div class="hd">
            <span>Незаключённые договоры по замерщикам</span>
            <span class="note">
                @if($ncState === 'ok')
                    сверка таблицы КЦ с 1С@if($ncUpdated !== '') · обновлено: {{ $ncUpdated }}@endif
                    @if($ncData['url']) · <a href="{{ $ncData['url'] }}" target="_blank" rel="noopener">полный отчёт →</a>@endif
                @endif
            </span>
        </div>
        <div class="bd">
            @if($ncState !== 'ok')
                <div class="{{ $ncState === 'error' ? 'text-danger' : 'note' }}">{{ $nonclosures['message'] }}</div>
            @else
                @foreach($ncData['stale'] as $warn)
                    <div class="text-danger small mb-1">⚠ {{ $warn }}</div>
                @endforeach
                <div class="row g-3">
                    @foreach($ncData['blocks'] as $blk)
                        <div class="col-lg-4 col-md-6">
                            <div class="fw-bold mb-1">{{ $blk['title'] }}</div>
                            <table class="stack">
                                <thead><tr><th>Замерщик</th><th class="num">Замеров</th><th class="num">Незаключ.</th><th class="num">%</th></tr></thead>
                                <tbody>
                                @foreach($blk['rows'] as $r)
                                    <tr><td>{{ $r['measurer'] }}</td><td class="num" data-l="замеров">{{ $n($r['measurements']) }}</td><td class="num" data-l="незаключ.">{{ $n($r['not_concluded']) }}</td><td class="num" data-l="%">{{ $pct($r['percent']) }}</td></tr>
                                @endforeach
                                <tr class="grp"><td>Итого</td><td class="num" data-l="замеров">{{ $n($blk['total']['measurements']) }}</td><td class="num" data-l="незаключ.">{{ $n($blk['total']['not_concluded']) }}</td><td class="num" data-l="%">{{ $pct($blk['total']['percent']) }}</td></tr>
                                </tbody>
                            </table>
                        </div>
                    @endforeach
                </div>
                @if($ncData['discrepancies'])
                    <div class="note mt-2">Строк, где таблица КЦ спорит с 1С: {{ $n($ncData['discrepancies']) }} — подробно в полном отчёте.</div>
                @endif
            @endif
        </div>
    </div>

    <div class="own-card">
        <div class="hd"><span>Операторы</span><span class="note">по действиям в CRM за период</span></div>
        <table class="stack">
            <thead><tr><th>Сотрудник</th><th class="num">Обработано сделок</th><th class="num">Перевели на замер</th><th class="num">Нецелевые</th><th class="num">Отказы</th></tr></thead>
            <tbody>
            @forelse($kpi['operators'] as $o)
                <tr>
                    <td>{{ $o['name'] }}</td>
                    <td class="num" data-l="обработано">{{ $n($o['handled']) }}</td>
                    <td class="num" data-l="на замер">{{ $n($o['bookings']) }}</td>
                    <td class="num" data-l="нецелевые">{{ $n($o['non_target']) }}</td>
                    <td class="num" data-l="отказы">{{ $n($o['lost']) }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="text-muted">За период по сделкам никто не работал.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    <p class="note">
        Как считается. <b>Замеры</b> — по Google-таблице замеров колл-центра (столбец «Сумма» и источники по дням), сводка
        перечитывает её каждые 30 секунд; если за период в таблице пусто — по CRM (сделка впервые попала на этап «Замер назначен»
        или закрыта «Успешно»). <b>Источники</b> — только те, где за период есть замеры. У Директа, Авито и ВК — лиды CRM этого
        канала (по самому раннему сигналу сделки: звонок на рекламный номер, чат, форма на сайте), расход и цены.
        <b>Лид</b> — новая сделка в колл-центре потолков за период. <b>Звонки</b> — по событиям Мегафона: пропущенный —
        входящий, который никто не принял. <b>Операторы</b> — по действиям в CRM: «перевели на замер» — кто первым перевёл сделку
        на «Замер назначен» (или закрыл «Успешно»); «обработано» — сделки, которые сотрудник двигал по этапам или закрывал.
        <b>Реклама</b> собирается сама каждые 2 часа: расход Директа, VK и Авито — из их кабинетов (Директ и VK — без НДС,
        как в таблице; Авито — все списания дня, вместе с тарифом). В таблицу расход Авито вносят до конца дня, поэтому там
        он обычно на несколько сотен рублей меньше.
    </p>
</div>
@endsection
