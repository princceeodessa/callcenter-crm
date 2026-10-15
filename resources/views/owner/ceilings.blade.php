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
    .src .split{ margin-top:.45rem; padding-top:.4rem; border-top:1px dashed var(--crm-border); font-size:.76rem; color:var(--crm-muted); }
    .src .split b{ color:var(--crm-text, inherit); font-weight:600; }

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
    .own-card tr.src-row td:first-child{ font-weight:700; }
    .own-card tr.src-row td:first-child .note{ font-weight:400; }
    .own-card tr.sub td:first-child{ padding-left:1.8rem; color:var(--crm-muted); }
    .ct-scroll{ overflow-x:auto; }
    .own-card table.ct th, .own-card table.ct td{ padding:.4rem .5rem; }
    .own-card table.ct th.cat{ white-space:normal; line-height:1.2; min-width:66px; vertical-align:bottom; }

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
    // цена за штуку; дешевле 10 ₽ (просмотр клипа VK — ~0,3 ₽) — с копейками, иначе было бы «0 ₽»
    $per = fn ($spend, $cnt) => ($spend !== null && $cnt > 0) ? number_format($spend / $cnt, $spend / $cnt < 10 ? 2 : 0, ',', ' ').' ₽' : '—';
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

    // Обратились в КЦ — клиенты, которые сами позвонили, написали или оставили контакты в форме (CeilingsKpi::contactsBetween).
    // Всё по CRM: записались — сделка клиента с начала периода дошла до «Замер назначен» или закрыта «Успешно»
    // (с таблицей замеров, по которой плитка «Замеры», число не подгоняется — решение 13.10.2026).
    $ct = $kpi['contacts'];
    $ctGroups = collect($ct['groups'])->keyBy('key');
    $share = fn ($a, $b) => $b > 0 ? round($a / $b * 100).'%' : '—';
    $ctRows = array_values(array_filter($ct['groups'], fn ($g) => $g['total'] > 0));
    $kindLabel = fn (string $kind, string $group) => $kind === 'call' && $group === 'other'
        ? 'позвонили на другие номера' : \App\Services\Owner\CeilingsKpi::contactKindLabel($kind);
    // как обращались: написали / позвонили / формы — по первому обращению клиента
    $how = [
        'chat' => ['label' => 'Написали в соцсети и чаты', 'total' => 0, 'booked' => 0, 'non_target' => 0, 'parts' => []],
        'call' => ['label' => 'Позвонили', 'total' => 0, 'booked' => 0, 'non_target' => 0, 'parts' => []],
        'form' => ['label' => 'Формы на сайте и лид-формы', 'total' => 0, 'booked' => 0, 'non_target' => 0, 'parts' => []],
    ];
    foreach ($ct['groups'] as $g) {
        foreach ($g['kinds'] as $k => $v) {
            $cls = str_starts_with($k, 'chat_') ? 'chat' : (str_starts_with($k, 'form_') ? 'form' : 'call');
            foreach (['total', 'booked', 'non_target'] as $f) $how[$cls][$f] += $v[$f];
            if ($cls === 'chat') {
                $name = ['vk' => 'ВК', 'avito' => 'чат Авито', 'telegram' => 'Telegram'][substr($k, 5)] ?? substr($k, 5);
                $how['chat']['parts'][$name] = ($how['chat']['parts'][$name] ?? 0) + $v['total'];
            }
        }
    }
    arsort($how['chat']['parts']);
    // колонки исходов: целевой записанный / отказ / нецелевые по категориям (+ старые без категории) / в работе
    $ctCols = \App\Support\Deals\NonTargetCategory::COLUMNS;
    if (($ct['cats']['none'] ?? 0) > 0) $ctCols['none'] = 'Нецелевые без категории';
    $ctCols['open'] = 'В работе';
    $catCell = fn (array $cats, string $key) => ($cats[$key] ?? 0) > 0 ? $n($cats[$key]) : '<span class="note">0</span>';

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
    $maxBar = max(1, collect($chartRows)->max('contacts') ?? 0, collect($chartRows)->max('m') ?? 0);
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
    $avDirs = $ads['avito_dirs'];
    $avApi = str_starts_with((string) ($ads['spend']['avito']['from'] ?? ''), 'API Авито');
    foreach ($sourceCards as &$c) {
        $c['leads'] = $c['paid'] ? (int) ($ctGroups[$c['paid']]['total'] ?? 0) : null;
        $c['non_target'] = $c['paid'] ? (int) ($ctGroups[$c['paid']]['non_target'] ?? 0) : null;
        $c['spend'] = $c['paid'] ? ($ads['spend'][$c['paid']] ?? null) : null;
        // «Ремонт (Авито)», «Кондиционеры (Авито)» — замеры с объявлений другого направления того же кабинета
        $c['dir'] = null;
        if (! $c['paid'] && $avApi && mb_stripos($c['name'], 'авито') !== false) {
            $dk = \App\Services\Owner\Marketing\AvitoSource::direction($c['name']);
            if (isset($avDirs[$dk]) && $dk !== 'ceilings' && $avDirs[$dk]['spend'] > 0) {
                $c['dir'] = $avDirs[$dk];
            }
        }
    }
    unset($c);

    // Авито: в сводке потолков — только объявления потолков; остальной кабинет (кондиционеры, ремонт, тариф) — строкой ниже
    $avSpend = $ads['spend']['avito'] ?? null;
    $avByDir = isset($av['ceilings_spend']) || isset($av['ceilings_contacts']);
    $avContacts = (int) ($avByDir ? ($av['ceilings_contacts'] ?? 0) : ($av['contacts'] ?? 0));
    $avViews = (int) ($avByDir ? ($av['ceilings_views'] ?? 0) : ($av['views'] ?? 0));
    $avRest = [];
    foreach ($ads['avito_dirs'] as $dk => $dd) {
        if ($dk !== 'ceilings' && $dd['spend'] > 0) $avRest[] = mb_strtolower($dd['label']).' '.$money($dd['spend']);
    }
    if ($ads['avito_shared'] > 0) $avRest[] = 'тариф и прочее '.$money($ads['avito_shared']);
    $avCabinet = (float) ($av['spend'] ?? 0);

    // VK: подписки = вступления в сообщество; группы объявлений (в кампании «Клипы» группа — это клип)
    $vkGroups = $ads['vk_groups'];
    $vkHasLeads = collect($vkGroups)->contains(fn ($g) => $g['leadads']);

    $ncState = $nonclosures['state'];
    $ncData = $nonclosures['data'] ?? null;
    $ncUpdated = $ncData ? collect($ncData['updated'])->map(fn ($t, $k) => ($k === 'kc_sheet' ? 'таблица КЦ' : ($k === 'onec' ? '1С' : $k)).' — '.$t->format($t->isToday() ? 'H:i' : 'd.m H:i'))->implode(', ') : '';
@endphp

<div class="own-wrap">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div>
            <h4 class="own-title">📊 Сводка бизнеса · Потолки</h4>
            <div class="text-muted small">колл-центр: обращения, замеры, звонки, источники и операторы · данные на {{ now()->format('H:i:s') }}, страница обновляется сама каждые 30 с</div>
        </div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <a class="btn btn-sm btn-outline-primary mb-3" href="{{ route('owner.smm', ['business' => 'ceilings']) }}">📱 SMM-контент</a>
            @include('owner._switch', ['active' => 'ceilings'])
        </div>
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
            <div class="l">Обратились в КЦ</div>
            <div class="v">{{ $n($ct['total']) }}</div>
            <div class="s">{!! $delta($ct['total'], $prev['contacts']) !!} к прошлым {{ $days }} дн. ({{ $n($prev['contacts']) }}) · нецелевых {{ $n($ct['non_target']) }}</div>
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
                <div class="s">цена обращения {{ $per($spendTotal, $ct['total']) }} · цена замера {{ $per($spendTotal, $mTotal) }}</div>
            </div>
        @endif
    </div>

    <div class="own-kpi">
        <div class="k"><div class="l">Нецелевые</div><div class="v">{{ $n($ct['non_target']) }}</div></div>
        <div class="k"><div class="l">Отказ</div><div class="v">{{ $n($ct['lost']) }}</div></div>
        <div class="k"><div class="l">Ещё в работе</div><div class="v">{{ $n($ct['open']) }}</div></div>
        <div class="k"><div class="l">Принято звонков</div><div class="v">{{ $n($calls['answered']) }}</div></div>
        <div class="k"><div class="l">Исходящие звонки</div><div class="v">{{ $n($calls['outgoing']) }}</div></div>
    </div>

    <div class="own-card">
        <div class="hd"><span>Обращения в колл-центр</span><span class="note">клиент считается один раз — по первому обращению за период · всё по CRM</span></div>
        <div class="bd">
            <div class="own-kpi" style="margin-bottom:0">
                @foreach($how as $h)
                    <div class="k">
                        <div class="l">{{ $h['label'] }}</div>
                        <div class="v">{{ $n($h['total']) }}</div>
                        <div class="note">записались {{ $n($h['booked']) }} ({{ $share($h['booked'], $h['total']) }}) · нецелевые {{ $n($h['non_target']) }} ({{ $share($h['non_target'], $h['total']) }})</div>
                        @if(count($h['parts']) > 0)
                            <div class="note">@foreach($h['parts'] as $name => $cnt){{ $name }} {{ $n($cnt) }}@if(! $loop->last), @endif @endforeach</div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
        <div class="ct-scroll">
        <table class="stack ct">
            <thead><tr>
                <th>Источник</th><th class="num">Обратились</th>
                @foreach($ctCols as $cl)<th class="num cat">{{ $cl }}</th>@endforeach
                <th class="num">% записи</th>
            </tr></thead>
            <tbody>
            @forelse($ctRows as $g)
                @php $multi = count($g['kinds']) > 1; @endphp
                <tr class="src-row">
                    <td>
                        {{ $g['label'] }}
                        @if(! $multi)<div class="note">{{ $kindLabel((string) array_key_first($g['kinds']), $g['key']) }}</div>@endif
                    </td>
                    <td class="num" data-l="обратились">{{ $n($g['total']) }}</td>
                    @foreach($ctCols as $ck => $cl)<td class="num" data-l="{{ mb_strtolower($cl) }}">{!! $catCell($g['cats'], $ck) !!}</td>@endforeach
                    <td class="num" data-l="% записи">{{ $share($g['booked'], $g['total']) }}</td>
                </tr>
                @if($multi)
                    @foreach($g['kinds'] as $k => $v)
                        <tr class="sub">
                            <td>{{ $kindLabel($k, $g['key']) }}</td>
                            <td class="num" data-l="обратились">{{ $n($v['total']) }}</td>
                            @foreach($ctCols as $ck => $cl)<td class="num" data-l="{{ mb_strtolower($cl) }}">{!! $catCell($v['cats'], $ck) !!}</td>@endforeach
                            <td class="num" data-l="% записи">{{ $share($v['booked'], $v['total']) }}</td>
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr><td colspan="{{ count($ctCols) + 3 }}" class="text-muted">За период обращений нет.</td></tr>
            @endforelse
            @if(count($ctRows) > 0)
                <tr class="grp">
                    <td>Итого</td>
                    <td class="num" data-l="обратились">{{ $n($ct['total']) }}</td>
                    @foreach($ctCols as $ck => $cl)<td class="num" data-l="{{ mb_strtolower($cl) }}">{!! $catCell($ct['cats'], $ck) !!}</td>@endforeach
                    <td class="num" data-l="% записи">{{ $share($ct['booked'], $ct['total']) }}</td>
                </tr>
            @endif
            </tbody>
        </table>
        </div>
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
                                    <dt>обратились</dt><dd>{{ $n($c['leads']) }}</dd>
                                    <dt>нецелевые</dt><dd>{{ $n($c['non_target']) }}</dd>
                                    @if($c['spend'])
                                        <dt>{{ $c['paid'] === 'avito' && $avApi ? 'расход на потолки' : 'расход' }}</dt><dd title="по данным: {{ $c['spend']['from'] }}">{{ $money($c['spend']['value']) }}</dd>
                                        <dt>цена обращения</dt><dd>{{ $per($c['spend']['value'], $c['leads']) }}</dd>
                                        <dt>цена замера</dt><dd>{{ $per($c['spend']['value'], $c['cnt']) }}</dd>
                                    @endif
                                </dl>
                                @if($c['paid'] === 'avito' && $avCabinet > 0 && count($avRest) > 0)
                                    <div class="split">весь кабинет Авито <b>{{ $money($avCabinet) }}</b>: потолки {{ $money($avDirs['ceilings']['spend']) }} · {{ implode(' · ', $avRest) }}</div>
                                @endif
                            @elseif($c['dir'])
                                <dl>
                                    <dt>расход ({{ mb_strtolower($c['dir']['label']) }} на Авито)</dt><dd>{{ $money($c['dir']['spend']) }}</dd>
                                    <dt>цена замера</dt><dd>{{ $per($c['dir']['spend'], $c['cnt']) }}</dd>
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
            <span class="legend"><span><i style="background:#93c5fd"></i>обратились</span><span><i style="background:#10b981"></i>замеры{{ $useSheet ? ' (по таблице)' : '' }}</span></span>
        </div>
        <div class="bd">
            <div class="bars">
                @foreach($chartRows as $r)
                    <div class="col" title="{{ $r['key'] }}: обратились {{ $r['contacts'] }}, замеров {{ $r['m'] }}, входящих звонков {{ $r['calls'] }}">
                        <div class="b leads" style="height:{{ round($r['contacts'] / $maxBar * 100, 2) }}%"></div>
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
                    <div class="note">показы {{ $n($vk['shows'] ?? 0) }} · просмотры 3с {{ $n($vk['views3'] ?? 0) }} (просмотр {{ $per($vk['spent'] ?? null, (int) ($vk['views3'] ?? 0)) }}) · досмотры {{ $n($vk['views100'] ?? 0) }} · подписки {{ $n($vk['joins'] ?? 0) }} (подписка {{ $per($vk['spent'] ?? null, (int) ($vk['joins'] ?? 0)) }}) · клики {{ $n($vk['clicks'] ?? 0) }}</div>
                </div>
                <div class="k">
                    <div class="l">Авито · потолки</div>
                    <div class="v" title="{{ $avSpend ? 'по данным: '.$avSpend['from'] : 'расхода нет ни в API, ни в таблице' }}">{{ $money($avSpend['value'] ?? null) }}</div>
                    <div class="note">контакты {{ $n($avContacts) }} · просмотры {{ $n($avViews) }} · контакт {{ $per($avSpend['value'] ?? null, $avContacts) }}</div>
                    @if($avCabinet > 0 && count($avRest) > 0)
                        <div class="note">весь кабинет {{ $money($avCabinet) }}, кроме потолков: {{ implode(' · ', $avRest) }}</div>
                    @endif
                </div>
            </div>
        </div>
    </div>

    @if(count($vkGroups) > 0)
    <div class="own-card">
        <div class="hd"><span>VK Реклама — группы объявлений</span><span class="note">в кампании «Клипы» группа — это клип; подписки — вступления в сообщество</span></div>
        <table class="stack">
            <thead><tr><th>Группа</th><th class="num">Расход</th><th class="num">Показы</th><th class="num">Просмотры 3с</th><th class="num">Цена просмотра</th><th class="num">Досмотры</th><th class="num">Подписки</th><th class="num">Цена подписки</th>@if($vkHasLeads)<th class="num">Лиды с формы</th>@endif</tr></thead>
            <tbody>
            @foreach($vkGroups as $g)
                <tr>
                    <td>
                        @if($g['clip'])<a href="{{ $g['clip'] }}" target="_blank" rel="noopener">{{ $g['name'] }}</a>@else{{ $g['name'] }}@endif
                        <div class="note">{{ $g['plan'] }} · {{ $g['active'] ? 'в работе' : 'остановлена' }}</div>
                    </td>
                    <td class="num" data-l="расход">{{ $money($g['spent'] ?? 0) }}</td>
                    <td class="num" data-l="показы">{{ $n($g['shows'] ?? 0) }}</td>
                    <td class="num" data-l="просмотры 3с">{{ $n($g['views3'] ?? 0) }}</td>
                    <td class="num" data-l="просмотр">{{ $per($g['spent'] ?? null, (int) ($g['views3'] ?? 0)) }}</td>
                    <td class="num" data-l="досмотры">{{ $n($g['views100'] ?? 0) }}</td>
                    <td class="num" data-l="подписки">{{ $n($g['joins'] ?? 0) }}</td>
                    <td class="num" data-l="подписка">{{ $per($g['spent'] ?? null, (int) ($g['joins'] ?? 0)) }}</td>
                    @if($vkHasLeads)<td class="num" data-l="лиды с формы">{{ $g['leadads'] ? $n($g['goals'] ?? 0) : '—' }}</td>@endif
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    @endif

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
        или закрыта «Успешно»). <b>Обратились в КЦ</b> — клиенты, которые за период сами позвонили, написали (ВК, чат Авито,
        Telegram) или оставили контакты в форме (сайт, лид-форма ВК); клиент — номер телефона: позвонил трижды и написал — одно
        обращение. Источник клиента — его первое обращение за период (звонок на рекламный номер, чат, форма). Исходящие
        звонки операторов — не обращения (на новый номер CRM заводит сделку, но клиент сам не обращался). <b>Нецелевые</b>,
        <b>отказ</b>, <b>в работе</b> — по сделкам этих клиентов в CRM; нецелевые делятся по категориям, которые оператор
        выбирает при закрытии «Не целевой» и при переводе на этап «Нецелевое» (с 15.10.2026; отмеченные раньше — «без
        категории»). <b>Записались на замер</b> в обращениях — тоже по CRM:
        сделка клиента с начала периода дошла до «Замер назначен» или закрыта «Успешно» (с таблицей замеров не сверяется,
        поэтому может отличаться от плитки «Замеры»); «Прочее» — звонки на нерекламные номера. <b>Источники замеров</b> — только те, где за период есть
        замеры; у Директа, Авито и ВК — обратившиеся этого источника, расход и цены. <b>Звонки</b> — по событиям Мегафона: пропущенный —
        входящий, который никто не принял. <b>Операторы</b> — по действиям в CRM: «перевели на замер» — кто первым перевёл сделку
        на «Замер назначен» (или закрыл «Успешно»); «обработано» — сделки, которые сотрудник двигал по этапам или закрывал.
        <b>Реклама</b> собирается сама: Директ и VK — каждые 2 часа, Авито — каждый час; расход — из их кабинетов (Директ и
        VK — без НДС, как в таблице). <b>Авито</b> — только объявления потолков (по названию): кондиционеры, ремонт с
        шумоизоляцией и общий тариф кабинета в цену лида и замера потолков не входят и показаны отдельной строкой. В таблицу
        расход Авито вносят до конца дня, поэтому там он обычно меньше. <b>VK</b> — подписки это вступления в сообщество,
        досмотры — просмотры клипа до конца. Страница обновляется сама каждые 30 секунд, пока открыта (свернутая вкладка
        не обновляется и догоняет при возврате).
    </p>
</div>
<script>
// Автообновление сводки: раз в 30 с та же страница запрашивается заново и подменяет содержимое — без перезагрузки,
// мигания и прыжка прокрутки. Свернутая вкладка не опрашивает, при возврате обновляется сразу; пока вводят даты — ждёт.
(function () {
    var busy = false;
    function refresh() {
        if (busy || document.hidden) return;
        var a = document.activeElement;
        if (a && a.closest && a.closest('.own-wrap form')) return;
        busy = true;
        fetch(location.href, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok && !r.redirected ? r.text() : null; })
            .then(function (html) {
                if (!html) return;
                var fresh = new DOMParser().parseFromString(html, 'text/html').querySelector('.own-wrap');
                var cur = document.querySelector('.own-wrap');
                if (fresh && cur) cur.replaceWith(fresh);
            })
            .catch(function () {})
            .then(function () { busy = false; });
    }
    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
})();
</script>
@endsection
