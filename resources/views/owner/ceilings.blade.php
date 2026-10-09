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
    // Замеры — по таблице замеров (решение владельца 09.10.2026); если за период в таблице пусто — по этапам CRM
    $ms = $measures;
    $useSheet = $ms['available'];
    $mTotal = $useSheet ? $ms['total'] : $kpi['bookings'];
    $mPrev = $useSheet ? $ms['prev_total'] : $prev['bookings'];
    $mConv = $kpi['leads'] > 0 ? $mTotal / $kpi['leads'] * 100 : null;
    $targeted = $kpi['leads'] - $kpi['non_target'];
    $mConvT = $targeted > 0 ? $mTotal / $targeted * 100 : null;
    $mConvPrev = ($mPrev !== null && $prev['leads'] > 0) ? $mPrev / $prev['leads'] * 100 : null;
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
    $channelRows = [];
    foreach ($kpi['channels'] as $g) {
        $gm = $useSheet ? (int) ($ms['by_group'][$g['key']] ?? 0) : (int) $g['booked'];
        if ($g['leads'] === 0 && $gm === 0 && ! in_array($g['key'], ['direct', 'avito', 'vk'], true)) {
            continue;
        }
        $sheetList = $useSheet ? ($ms['by_source'][$g['key']] ?? []) : [];
        $sheetLine = '';
        if (count($sheetList) > 1 || (count($sheetList) === 1 && array_key_first($sheetList) !== $g['label'])) {
            $sheetLine = collect($sheetList)->map(fn ($c, $name) => $name.' '.$c)->implode(' · ');
        }
        $crmSources = array_values(array_filter($g['sources'], fn ($s) => ! (count($g['sources']) === 1 && $s['label'] === $g['label'])));
        $channelRows[] = $g + ['m' => $gm, 'spend' => $ads['spend'][$g['key']] ?? null, 'sheet_line' => $sheetLine, 'crm_sources' => $crmSources];
    }
    $money = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' ₽';
    $per = fn ($spend, $cnt) => ($spend !== null && $cnt > 0) ? number_format($spend / $cnt, 0, ',', ' ').' ₽' : '—';
    $spendTotal = (float) $ads['spend_total'];
    $adsStatus = collect($ads['sources'])->map(function ($s) {
        if (! $s['enabled']) return ['text' => $s['label'].' — не подключено', 'warn' => false];
        if ($s['last_error']) {
            return ['text' => $s['label'].' — ошибка: '.\Illuminate\Support\Str::limit($s['last_error'], 120), 'warn' => true];
        }
        if (! $s['last_ok_at']) return ['text' => $s['label'].' — ещё не собиралось', 'warn' => true];
        $stale = $s['last_ok_at']->lt(now()->subHours(26));
        return ['text' => $s['label'].' — '.($stale ? 'устарело, последний сбор ' : '').$s['last_ok_at']->format($s['last_ok_at']->isToday() ? 'H:i' : 'd.m H:i'), 'warn' => $stale];
    })->values();
    $vk = $ads['vk']; $di = $ads['direct']; $av = $ads['avito']; $sh = $ads['sheet'];
    $sheetTop = array_slice(array_filter($sh['sources'], fn ($c) => $c > 0), 0, 6, true);
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
        <form method="GET" action="{{ route('owner.ceilings') }}" class="d-flex gap-2 align-items-center flex-wrap">
            <input type="hidden" name="period" value="custom">
            <input type="date" name="from" value="{{ $fromValue }}" class="form-control form-control-sm" style="width:auto; max-width:150px">
            <span class="text-muted">—</span>
            <input type="date" name="to" value="{{ $toValue }}" class="form-control form-control-sm" style="width:auto; max-width:150px">
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
            <div class="l">Замеры</div>
            <div class="v">{{ $n($mTotal) }}</div>
            <div class="s">{!! $delta($mTotal, $mPrev) !!} к прошлым {{ $days }} дн.@if($mPrev !== null) ({{ $n($mPrev) }})@endif · {{ $mSourceNote }}</div>
            @if($mCoverage)
                <div class="s text-danger">{{ $mCoverage }}</div>
            @endif
        </div>
        <div class="own-stat">
            <div class="l">Конверсия лид → замер</div>
            <div class="v">{{ $pct($mConv) }}</div>
            <div class="s">{!! $delta($mConv, $mConvPrev, true) !!} · из целевых {{ $pct($mConvT) }}</div>
        </div>
        <div class="own-stat">
            <div class="l">Входящие звонки</div>
            <div class="v">{{ $n($calls['incoming']) }}</div>
            <div class="s">{!! $delta($calls['incoming'], $prev['incoming']) !!} · пропущено {{ $n($calls['missed']) }}@if($calls['incoming'] > 0) ({{ $pct($calls['missed'] / $calls['incoming'] * 100) }})@endif</div>
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
        <div class="hd">
            <span>{{ $series['by'] === 'month' ? 'По месяцам' : 'По дням' }}</span>
            <span class="legend"><span><i style="background:#93c5fd"></i>лиды</span><span><i style="background:#10b981"></i>замеры{{ $useSheet ? ' (по таблице)' : '' }}</span></span>
        </div>
        <div class="bd">
            <div class="bars">
                @foreach($chartRows as $r)
                    <div class="col" title="{{ $r['key'] }}: лидов {{ $r['leads'] }}, замеров {{ $r['m'] }}, входящих звонков {{ $r['calls'] }}">
                        <div class="b leads" style="height:{{ round($r['leads'] / $maxBar * 100, 2) }}%"></div>
                        <div class="b book" style="height:{{ round($r['m'] / $maxBar * 100, 2) }}%; position:absolute; bottom:0; left:25%; right:25%"></div>
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
        <div class="hd"><span>Каналы — откуда лиды</span><span class="note">лиды — по CRM, замеры — {{ $useSheet ? 'по таблице замеров' : 'по CRM' }}; цена — расход канала на них</span></div>
        <div class="tbl-scroll">
            <table>
                <thead><tr><th>Канал</th><th class="num">Лиды</th><th class="num">Замеры</th><th class="num">Конверсия</th><th class="num">Нецелевые</th><th class="num">Расход</th><th class="num">Цена лида</th><th class="num">Цена замера</th></tr></thead>
                <tbody>
                @foreach($channelRows as $g)
                    <tr class="grp">
                        <td>{{ $g['label'] }}</td>
                        <td class="num">{{ $n($g['leads']) }}</td>
                        <td class="num">{{ $n($g['m']) }}</td>
                        <td class="num">{{ $g['leads'] > 0 ? $pct($g['m'] / $g['leads'] * 100) : '—' }}</td>
                        <td class="num">{{ $n($g['non_target']) }}</td>
                        <td class="num" title="{{ $g['spend'] ? 'по данным: '.$g['spend']['from'] : '' }}">{{ $g['spend'] ? $money($g['spend']['value']) : '' }}</td>
                        <td class="num">{{ $g['spend'] ? $per($g['spend']['value'], $g['leads']) : '' }}</td>
                        <td class="num">{{ $g['spend'] ? $per($g['spend']['value'], $g['m']) : '' }}</td>
                    </tr>
                    @foreach($g['crm_sources'] as $s)
                        <tr class="sub">
                            <td>{{ $s['label'] }}</td>
                            <td class="num">{{ $n($s['leads']) }}</td>
                            <td></td>
                            <td></td>
                            <td class="num">{{ $n($s['non_target']) }}</td>
                            <td colspan="3"></td>
                        </tr>
                    @endforeach
                    @if($g['sheet_line'] !== '')
                        <tr class="sub"><td colspan="8">замеры по таблице: {{ $g['sheet_line'] }}</td></tr>
                    @endif
                @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="own-card">
        <div class="hd"><span>Реклама по площадкам</span><span class="note">
            @foreach($adsStatus as $st)<span class="{{ $st['warn'] ? 'text-danger' : '' }}">{{ $st['text'] }}</span>@if(! $loop->last) · @endif @endforeach
        </span></div>
        <div class="bd">
            <div class="own-kpi" style="margin-bottom:0">
                <div class="k">
                    <div class="l">Яндекс Директ</div>
                    <div class="v">{{ $money($di['cost'] ?? null) }}</div>
                    <div class="note">клики {{ $n($di['clicks'] ?? 0) }} · показы {{ $n($di['impr'] ?? 0) }}<br>
                        CTR {{ ($di['impr'] ?? 0) > 0 ? $pct(($di['clicks'] ?? 0) / $di['impr'] * 100) : '—' }} · клик {{ $per($di['cost'] ?? null, (int) ($di['clicks'] ?? 0)) }}</div>
                </div>
                <div class="k">
                    <div class="l">VK Реклама</div>
                    <div class="v">{{ $money($vk['spent'] ?? null) }}</div>
                    <div class="note">показы {{ $n($vk['shows'] ?? 0) }} · клики {{ $n($vk['clicks'] ?? 0) }}<br>
                        просмотры 3с {{ $n($vk['views3'] ?? 0) }} · вступления {{ $n($vk['joins'] ?? 0) }}</div>
                </div>
                <div class="k">
                    <div class="l">Авито</div>
                    <div class="v">{{ $n($av['contacts'] ?? 0) }} <span style="font-size:.8rem;font-weight:600">контактов</span></div>
                    <div class="note">просмотры {{ $n($av['views'] ?? 0) }} · избранное {{ $n($av['favorites'] ?? 0) }}<br>
                        в контакт {{ ($av['views'] ?? 0) > 0 ? $pct(($av['contacts'] ?? 0) / $av['views'] * 100) : '—' }} @if($ads['spend']['avito']) · расход {{ $money($ads['spend']['avito']['value']) }} @endif</div>
                </div>
                <div class="k">
                    <div class="l">Таблица замеров</div>
                    <div class="v">{{ $n($sh['total']) }} <span style="font-size:.8rem;font-weight:600">замеров</span></div>
                    <div class="note">
                        @foreach($sheetTop as $name => $cnt)
                            {{ $name }} {{ $n($cnt) }}@if(isset($sh['spend'][$name]) && $sh['spend'][$name] > 0) ({{ $per($sh['spend'][$name], $cnt) }}) @endif
                            @if(! $loop->last) · @endif
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>

    @php
        $ncState = $nonclosures['state'];
        $ncData = $nonclosures['data'] ?? null;
        $ncUpdated = $ncData ? collect($ncData['updated'])->map(fn ($t, $k) => ($k === 'kc_sheet' ? 'таблица КЦ' : ($k === 'onec' ? '1С' : $k)).' — '.$t->format($t->isToday() ? 'H:i' : 'd.m H:i'))->implode(', ') : '';
    @endphp
    <div class="own-card">
        <div class="hd">
            <span>Незаключённые договоры по замерщикам</span>
            <span class="note">
                @if($ncState === 'ok')
                    сверка таблицы КЦ с 1С, CRM БлагоДар@if($ncUpdated !== '') · обновлено: {{ $ncUpdated }}@endif
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
                            <table>
                                <thead><tr><th>Замерщик</th><th class="num">Замеров</th><th class="num">Незаключ.</th><th class="num">%</th></tr></thead>
                                <tbody>
                                @foreach($blk['rows'] as $r)
                                    <tr><td>{{ $r['measurer'] }}</td><td class="num">{{ $n($r['measurements']) }}</td><td class="num">{{ $n($r['not_concluded']) }}</td><td class="num">{{ $pct($r['percent']) }}</td></tr>
                                @endforeach
                                <tr class="grp"><td>Итого</td><td class="num">{{ $n($blk['total']['measurements']) }}</td><td class="num">{{ $n($blk['total']['not_concluded']) }}</td><td class="num">{{ $pct($blk['total']['percent']) }}</td></tr>
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
        <div class="hd"><span>Операторы</span><span class="note">по действиям за период: кто двигал и закрывал сделки</span></div>
        <div class="tbl-scroll">
            <table>
                <thead><tr><th>Сотрудник</th><th class="num">Обработано сделок</th><th class="num">Перевели на замер</th><th class="num">Доля от обработанных</th><th class="num">Нецелевые</th><th class="num">Отказы</th></tr></thead>
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
        Как считается. <b>Замеры</b> — по Google-таблице замеров колл-центра (столбец «Сумма» и источники по дням), сводка
        перечитывает её каждые 15 минут; если за период в таблице пусто — по CRM (сделка впервые попала на этап «Замер назначен»
        или закрыта «Успешно»). <b>Лид</b> — новая сделка в колл-центре потолков за период. <b>Конверсия</b> — замеры
        периода, делённые на его лиды; «из целевых» — без нецелевых.
        <b>Канал лида</b> — самый ранний сигнал по сделке: чат, форма на сайте, импорт или звонок на рекламный номер; замеры
        канала — по источникам таблицы (Директ, Авито и Авито Частник, ВК, Радио, ТВ; остальные — «Прочее»).
        <b>Звонки</b> — по событиям Мегафона: пропущенный — входящий, который никто не принял.
        <b>Операторы</b> — по действиям в CRM: «перевели на замер» — кто первым перевёл сделку на «Замер назначен» (или закрыл
        «Успешно»); «обработано» — сделки, которые сотрудник двигал по этапам или закрывал за период.
        <b>Реклама</b> собирается сама каждые 2 часа: расход Директа и VK — из их кабинетов, расход Авито — из таблицы замеров
        (API Авито расходов не отдаёт). Все расходы — без НДС, как в таблице. Цена лида и замера — расход канала, делённый на
        его лиды и замеры. В карточке «Таблица замеров» в скобках — цена замера по затратам из таблицы.
    </p>
</div>
@endsection
