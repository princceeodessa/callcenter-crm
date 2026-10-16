@extends('layouts.owner')

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
    // изменение к прошлому периоду той же длины — бейдж дизайн-системы
    $delta = function ($cur, $prev) {
        if ($prev === null || $cur === null) return '';
        if ((float) $prev == 0.0) return $cur > 0 ? '<span class="badge up">новое</span>' : '';
        $d = ($cur - $prev) / $prev * 100;
        if (abs($d) < 0.5) return '<span class="badge flat">без изменений</span>';
        return '<span class="badge '.($d > 0 ? 'up' : 'down').'">'.($d > 0 ? '▲' : '▼').' '.number_format(abs($d), 0, ',', ' ').'%</span>';
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
    $catCell = fn (array $cats, string $key) => ($cats[$key] ?? 0) > 0 ? $n($cats[$key]) : '<span class="muted">0</span>';

    // ряд по дням (или месяцам): замеры по таблице, обратились, входящие звонки, расход — для графиков
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
        $sp = 0.0;
        foreach ($ads['spend_by_day'] as $d => $v) {
            if ($series['by'] === 'month' ? str_starts_with($d, $r['key']) : $d === $r['key']) {
                $sp += $v;
            }
        }
        $chartRows[] = $r + ['m' => $m, 'spend' => round($sp)];
    }
    $showChart = count($chartRows) >= 2;   // за один день столбик и линия ничего не добавляют к цифрам наверху
    $axis = array_map(fn ($r) => $series['by'] === 'month' ? \Carbon\Carbon::parse($r['key'].'-01')->translatedFormat('M') : \Carbon\Carbon::parse($r['key'])->format('d'), $chartRows);
    $titles = array_map(fn ($r) => $series['by'] === 'month' ? \Carbon\Carbon::parse($r['key'].'-01')->translatedFormat('F Y') : \Carbon\Carbon::parse($r['key'])->format('d.m.Y'), $chartRows);
    $spark = fn (string $key, $unit) => json_encode(['data' => array_column($chartRows, $key), 'labels' => $titles, 'unit' => $unit], JSON_UNESCAPED_UNICODE);
    $bars = json_encode(['labels' => $axis, 'titles' => $titles, 'sets' => [
        ['label' => 'обратились', 'data' => array_column($chartRows, 'contacts'), 'color' => '--blue'],
        ['label' => 'замеры', 'data' => array_column($chartRows, 'm'), 'color' => '--accent'],
    ]], JSON_UNESCAPED_UNICODE);

    // Карточки источников — только те, где за период есть замеры. У платных (Директ, Авито, ВК) — обратившиеся этого
    // канала, расход, цена обращения и замера.
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

    $periodLabel = $kpi['from']->format('d.m.Y').($days > 1 ? ' — '.$kpi['to']->copy()->subDay()->format('d.m.Y') : '');
@endphp

@section('title', 'Потолки')
@section('subtitle')Сводка бизнеса · Потолки · {{ $periodLabel }} · данные на {{ now()->format('H:i:s') }}, страница обновляется сама каждые 30 с@endsection

@section('nav')
    <a href="#top" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg> Обзор</a>
    <a href="#contacts"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg> Обращения</a>
    @if($showChart)
        <a href="#days"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M8 17V11M13 17V7M18 17v-4"/></svg> {{ $series['by'] === 'month' ? 'По месяцам' : 'По дням' }}</a>
    @endif
    <a href="#sources"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l9 5-9 5-9-5 9-5z"/><path d="M3 12l9 5 9-5M3 17l9 5 9-5"/></svg> Источники</a>
    <a href="#ads"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/></svg> Реклама</a>
    <a href="#profit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg> Прибыль договоров</a>
    <a href="#nonclosures"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><path d="M14 2v6h6M9 13h6M9 17h4"/></svg> Незаключённые</a>
    <a href="#operators"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.9"/></svg> Операторы</a>
    <span class="lbl">Бизнес</span>
    <a href="{{ route('owner.smm', ['business' => 'ceilings']) }}" class="sub"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M10 8l5 4-5 4V8z"/></svg> SMM-контент</a>
    <a href="{{ route('owner.dashboard') }}" class="sub"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 17h20v3H2zM4 17l2-7 5 2 3-4 6 4 2 5"/></svg> Кроссовки</a>
    <a href="{{ route('sale.day') }}" class="sub" title="Продажи кроссовок за день"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="3"/><path d="M3 9h18M8 2v4M16 2v4"/></svg> Продажи за день</a>
@endsection

@section('content')
<div id="dash">
    <div class="filters" id="top">
        @include('owner._biz_tabs', ['biz' => 'ceilings', 'tab' => 'summary'])
        <div class="seg">
            @foreach($periods as $key => $label)
                <a href="{{ route('owner.ceilings', ['period' => $key]) }}" class="{{ $period === $key ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
        <div class="spacer"></div>
        @include('owner._daterange', ['action' => route('owner.ceilings'), 'params' => ['period' => 'custom'], 'from' => $fromValue, 'to' => $toValue])
        <a class="export" href="{{ request()->fullUrlWithQuery(['export' => 'contacts']) }}" title="Номер телефона, менеджер (кто первым взял лид), записался ли. Без нецелевых и исходящих дозвонов.">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3v12M8 11l4 4 4-4"/><path d="M4 17v2a2 2 0 002 2h12a2 2 0 002-2v-2"/></svg> Обращения в Excel
        </a>
    </div>

    {{-- KPI --}}
    <div class="kpis">
        <div class="card kpi accent {{ $showChart ? '' : 'no-chart' }}">
            <div class="top"><span class="lbl">Замеры</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 3L3 21M14 4l6 6M4 14l6 6M9 9l1.5 1.5M13 5l1.5 1.5M5 13l1.5 1.5"/></svg></span>
            </div>
            <div class="val num">{{ $n($mTotal) }}</div>
            <div style="margin-bottom:4px">{!! $delta($mTotal, $mPrev) !!}</div>
            <div class="cap">@if($mPrev !== null)прошлые {{ $days }} дн. — {{ $n($mPrev) }}@else прошлого периода в таблице нет целиком@endif · {{ $mSourceNote }}</div>
            @if($mCoverage)<div class="warn">{{ $mCoverage }}</div>@endif
            @if($showChart)<div class="kpi-chart"><canvas id="sp-m" data-on-accent="1" data-spark="{{ $spark('m', ['замер', 'замера', 'замеров']) }}"></canvas></div>@endif
        </div>

        <div class="card kpi {{ $showChart ? '' : 'no-chart' }}">
            <div class="top"><span class="lbl">Обратились в КЦ</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></span>
            </div>
            <div class="val num">{{ $n($ct['total']) }}</div>
            <div style="margin-bottom:4px">{!! $delta($ct['total'], $prev['contacts']) !!}</div>
            <div class="cap">прошлые {{ $days }} дн. — {{ $n($prev['contacts']) }} · нецелевых {{ $n($ct['non_target']) }}</div>
            @if($showChart)<div class="kpi-chart"><canvas id="sp-c" data-spark="{{ $spark('contacts', ['обращение', 'обращения', 'обращений']) }}"></canvas></div>@endif
        </div>

        <div class="card kpi {{ $showChart ? '' : 'no-chart' }}">
            <div class="top"><span class="lbl">Входящие звонки</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3-8.6A2 2 0 014.1 2h3a2 2 0 012 1.7c.1.9.4 1.8.7 2.7a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6l1.4-1.2a2 2 0 012.1-.4c.9.3 1.8.6 2.7.7a2 2 0 011.7 2z"/></svg></span>
            </div>
            <div class="val num">{{ $n($calls['incoming']) }}</div>
            <div style="margin-bottom:4px">{!! $delta($calls['incoming'], $prev['incoming']) !!}</div>
            <div class="cap">пропущено {{ $n($calls['missed']) }}</div>
            @if($showChart)<div class="kpi-chart"><canvas id="sp-k" data-spark="{{ $spark('calls', ['звонок', 'звонка', 'звонков']) }}"></canvas></div>@endif
        </div>

        <div class="card kpi {{ $showChart && $spendTotal > 0 ? '' : 'no-chart' }}">
            <div class="top"><span class="lbl">Расход на рекламу</span>
                <span class="ic"><svg width="19" height="19" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/></svg></span>
            </div>
            <div class="val num">{{ $spendTotal > 0 ? $money($spendTotal) : '—' }}</div>
            <div style="margin-bottom:4px"><span class="badge up">за период</span></div>
            <div class="cap">цена обращения {{ $per($spendTotal, $ct['total']) }} · цена замера {{ $per($spendTotal, $mTotal) }}</div>
            @if($showChart && $spendTotal > 0)<div class="kpi-chart"><canvas id="sp-s" data-spark="{{ $spark('spend', '₽') }}"></canvas></div>@endif
        </div>
    </div>

    <div class="minis">
        <div class="card mini"><div class="lbl">Нецелевые</div><div class="val num">{{ $n($ct['non_target']) }}</div></div>
        <div class="card mini"><div class="lbl">Отказ</div><div class="val num">{{ $n($ct['lost']) }}</div></div>
        <div class="card mini"><div class="lbl">Ещё в работе</div><div class="val num">{{ $n($ct['open']) }}</div></div>
        <div class="card mini"><div class="lbl">Принято звонков</div><div class="val num">{{ $n($calls['answered']) }}</div></div>
        <div class="card mini"><div class="lbl">Исходящие звонки</div><div class="val num">{{ $n($calls['outgoing']) }}</div></div>
    </div>

    {{-- Обращения в колл-центр --}}
    <div class="sec" id="contacts"><h2>Обращения в колл-центр</h2><span class="note">клиент считается один раз — по первому обращению за период · всё по CRM</span></div>
    <div class="cc">
        @foreach($how as $h)
            @php $hb = $h['total'] > 0 ? round($h['booked'] / $h['total'] * 100) : 0; @endphp
            <div class="card">
                <div class="lbl">{{ $h['label'] }}</div>
                <div class="val num">{{ $n($h['total']) }}</div>
                <div class="sub">нецелевые {{ $n($h['non_target']) }} ({{ $share($h['non_target'], $h['total']) }})@if(count($h['parts']) > 0) · @foreach($h['parts'] as $pname => $pcnt){{ $pname }} {{ $n($pcnt) }}@if(! $loop->last), @endif @endforeach @endif</div>
                <div class="bar"><span style="width:{{ $hb }}%"></span></div>
                <div class="bar-cap"><span>записались {{ $n($h['booked']) }}</span><b>{{ $share($h['booked'], $h['total']) }}</b></div>
            </div>
        @endforeach
    </div>

    <div class="card tbl-card">
        <div class="tbl-head"><h2>По источникам и исходам</h2><span class="note">нецелевые — по категориям, которые ставит оператор · <a href="{{ request()->fullUrlWithQuery(['export' => 'contacts']) }}">списком в Excel</a></span></div>
        <div class="tbl-wrap"><table class="t compact">
            <thead><tr>
                <th>Источник</th><th class="num">Обратились</th>
                @foreach($ctCols as $cl)<th class="num cat">{{ $cl }}</th>@endforeach
                <th class="num">% записи</th>
            </tr></thead>
            <tbody>
            @forelse($ctRows as $g)
                @php $multi = count($g['kinds']) > 1; @endphp
                <tr class="src-row">
                    <td class="wrap">{{ $g['label'] }}@if(! $multi)<div class="note">{{ $kindLabel((string) array_key_first($g['kinds']), $g['key']) }}</div>@endif</td>
                    <td class="num">{{ $n($g['total']) }}</td>
                    @foreach($ctCols as $ck => $cl)<td class="num">{!! $catCell($g['cats'], $ck) !!}</td>@endforeach
                    <td class="num">{{ $share($g['booked'], $g['total']) }}</td>
                </tr>
                @if($multi)
                    @foreach($g['kinds'] as $k => $v)
                        <tr class="sub-row">
                            <td>{{ $kindLabel($k, $g['key']) }}</td>
                            <td class="num">{{ $n($v['total']) }}</td>
                            @foreach($ctCols as $ck => $cl)<td class="num">{!! $catCell($v['cats'], $ck) !!}</td>@endforeach
                            <td class="num">{{ $share($v['booked'], $v['total']) }}</td>
                        </tr>
                    @endforeach
                @endif
            @empty
                <tr><td colspan="{{ count($ctCols) + 3 }}" class="muted">За период обращений нет.</td></tr>
            @endforelse
            @if(count($ctRows) > 0)
                <tr class="grp">
                    <td>Итого</td>
                    <td class="num">{{ $n($ct['total']) }}</td>
                    @foreach($ctCols as $ck => $cl)<td class="num">{!! $catCell($ct['cats'], $ck) !!}</td>@endforeach
                    <td class="num">{{ $share($ct['booked'], $ct['total']) }}</td>
                </tr>
            @endif
            </tbody>
        </table></div>
    </div>

    {{-- По дням --}}
    @if($showChart)
        <div class="card chartcard" id="days">
            <div class="sec" style="margin:0 0 4px">
                <h2>{{ $series['by'] === 'month' ? 'По месяцам' : 'По дням' }}</h2>
                <div class="legend">
                    <span class="lg"><span class="dot2" style="background:var(--blue)"></span>обратились</span>
                    <span class="lg"><span class="dot2" style="background:var(--accent)"></span>замеры{{ $useSheet ? ' (по таблице)' : '' }}</span>
                </div>
            </div>
            <div class="chart-h"><canvas id="bars-days" data-bars="{{ $bars }}"></canvas></div>
        </div>
    @endif

    {{-- Источники замеров --}}
    <div class="sec" id="sources"><h2>Источники замеров</h2><span class="note">только источники с замерами за период{{ $useSheet ? ' · по таблице замеров' : '' }}</span></div>
    @if(count($sourceCards) === 0)
        <div class="card empty-card">За период замеров нет.</div>
    @else
        <div class="bento">
            @foreach($sourceCards as $c)
                <div class="card src">
                    <div class="nm">@include('owner._src_logo', ['name' => $c['name']]){{ $c['name'] }}</div>
                    <div class="cnt num">{{ $n($c['cnt']) }} <small>{{ $plural((int) $c['cnt'], 'замер', 'замера', 'замеров') }}</small></div>
                    @if($c['paid'])
                        <div class="rows">
                            <div class="rw"><span>обратились</span><b class="num">{{ $n($c['leads']) }}</b></div>
                            <div class="rw"><span>нецелевые</span><b class="num">{{ $n($c['non_target']) }}</b></div>
                            @if($c['spend'])
                                <div class="rw" title="по данным: {{ $c['spend']['from'] }}"><span>{{ $c['paid'] === 'avito' && $avApi ? 'расход на потолки' : 'расход' }}</span><b class="num">{{ $money($c['spend']['value']) }}</b></div>
                                <div class="rw"><span>цена обращения</span><b class="num">{{ $per($c['spend']['value'], $c['leads']) }}</b></div>
                                <div class="rw"><span>цена замера</span><b class="num">{{ $per($c['spend']['value'], $c['cnt']) }}</b></div>
                            @endif
                        </div>
                        @if($c['paid'] === 'avito' && $avCabinet > 0 && count($avRest) > 0)
                            <div class="note">весь кабинет Авито <b>{{ $money($avCabinet) }}</b>: потолки {{ $money($avDirs['ceilings']['spend']) }} · {{ implode(' · ', $avRest) }}</div>
                        @endif
                    @elseif($c['dir'])
                        <div class="rows">
                            <div class="rw"><span>расход ({{ mb_strtolower($c['dir']['label']) }} на Авито)</span><b class="num">{{ $money($c['dir']['spend']) }}</b></div>
                            <div class="rw"><span>цена замера</span><b class="num">{{ $per($c['dir']['spend'], $c['cnt']) }}</b></div>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif

    {{-- Реклама по площадкам --}}
    <div class="sec" id="ads"><h2>Реклама по площадкам</h2>
        <span class="status-line">@foreach($adsStatus as $st)<span class="{{ $st['warn'] ? 'bad' : '' }}">{{ $st['text'] }}</span>@endforeach</span>
    </div>
    <div class="cc">
        <div class="card">
            <div class="lbl nm">@include('owner._src_logo', ['name' => 'Директ']) Яндекс Директ</div>
            <div class="val num">{{ $money($di['cost'] ?? null) }}</div>
            <div class="rows">
                <div class="rw"><span>клики</span><b class="num">{{ $n($di['clicks'] ?? 0) }}</b></div>
                <div class="rw"><span>показы</span><b class="num">{{ $n($di['impr'] ?? 0) }}</b></div>
                <div class="rw"><span>цена клика</span><b class="num">{{ $per($di['cost'] ?? null, (int) ($di['clicks'] ?? 0)) }}</b></div>
            </div>
        </div>
        <div class="card">
            <div class="lbl nm">@include('owner._src_logo', ['name' => 'ВК']) VK Реклама</div>
            <div class="val num">{{ $money($vk['spent'] ?? null) }}</div>
            <div class="rows">
                <div class="rw"><span>показы</span><b class="num">{{ $n($vk['shows'] ?? 0) }}</b></div>
                <div class="rw"><span>просмотры 3с</span><b class="num">{{ $n($vk['views3'] ?? 0) }} <span class="muted">· {{ $per($vk['spent'] ?? null, (int) ($vk['views3'] ?? 0)) }}</span></b></div>
                <div class="rw"><span>досмотры</span><b class="num">{{ $n($vk['views100'] ?? 0) }}</b></div>
                <div class="rw"><span>подписки</span><b class="num">{{ $n($vk['joins'] ?? 0) }} <span class="muted">· {{ $per($vk['spent'] ?? null, (int) ($vk['joins'] ?? 0)) }}</span></b></div>
                <div class="rw"><span>клики</span><b class="num">{{ $n($vk['clicks'] ?? 0) }}</b></div>
            </div>
        </div>
        <div class="card">
            <div class="lbl nm">@include('owner._src_logo', ['name' => 'Авито']) Авито · потолки</div>
            <div class="val num" title="{{ $avSpend ? 'по данным: '.$avSpend['from'] : 'расхода нет ни в API, ни в таблице' }}">{{ $money($avSpend['value'] ?? null) }}</div>
            <div class="rows">
                <div class="rw"><span>контакты</span><b class="num">{{ $n($avContacts) }}</b></div>
                <div class="rw"><span>просмотры</span><b class="num">{{ $n($avViews) }}</b></div>
                <div class="rw"><span>цена контакта</span><b class="num">{{ $per($avSpend['value'] ?? null, $avContacts) }}</b></div>
            </div>
            @if($avCabinet > 0 && count($avRest) > 0)
                <div class="sub" style="margin-top:8px">весь кабинет {{ $money($avCabinet) }}, кроме потолков: {{ implode(' · ', $avRest) }}</div>
            @endif
        </div>
    </div>

    @if(count($vkGroups) > 0)
        <div class="card tbl-card" id="clips">
            <div class="tbl-head"><h2>VK Реклама — группы объявлений</h2><span class="note">в кампании «Клипы» группа — это клип · подписки — вступления в сообщество</span></div>
            <div class="tbl-wrap"><table class="t">
                <thead><tr><th>Группа</th><th class="num">Расход</th><th class="num">Показы</th><th class="num">Просмотры 3с</th><th class="num">Цена просм.</th><th class="num">Досмотры</th><th class="num">Подписки</th><th class="num">Цена подписки</th>@if($vkHasLeads)<th class="num">Лиды с формы</th>@endif</tr></thead>
                <tbody>
                @foreach($vkGroups as $g)
                    <tr>
                        <td class="clip-cell">
                            @if($g['clip'] && preg_match('~clip(-?\d+)_(\d+)~', $g['clip'], $cm))
                                {{-- ▶ — плеер клипа поверх страницы (layouts/owner, public/js/bd-owner-head.js) --}}
                                <button type="button" class="watch" data-oid="{{ $cm[1] }}" data-vid="{{ $cm[2] }}" data-url="{{ $g['clip'] }}" data-title="{{ $g['name'] }}"
                                        data-sub="VK Клип · БлагоДар · Потолки · {{ $g['active'] ? 'в работе' : 'остановлена' }}" title="Смотреть клип">{{ $g['name'] }} <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg></button>
                            @elseif($g['clip'])<a href="{{ $g['clip'] }}" target="_blank" rel="noopener">{{ $g['name'] }}</a>@else{{ $g['name'] }}@endif
                            <span class="tag {{ $g['active'] ? 'green' : 'gray' }}">{{ $g['active'] ? 'в работе' : 'остановлена' }}</span>
                        </td>
                        <td class="num">{{ $money($g['spent'] ?? 0) }}</td>
                        <td class="num">{{ $n($g['shows'] ?? 0) }}</td>
                        <td class="num">{{ $n($g['views3'] ?? 0) }}</td>
                        <td class="num">{{ $per($g['spent'] ?? null, (int) ($g['views3'] ?? 0)) }}</td>
                        <td class="num">{{ $n($g['views100'] ?? 0) }}</td>
                        <td class="num">{{ $n($g['joins'] ?? 0) }}</td>
                        <td class="num">{{ $per($g['spent'] ?? null, (int) ($g['joins'] ?? 0)) }}</td>
                        @if($vkHasLeads)<td class="num">{{ $g['leadads'] ? $n($g['goals'] ?? 0) : '—' }}</td>@endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
    @endif

    {{-- Блоки из CRM БлагоДар: прибыль договоров и незаключённые. Нет в кэше — подгружаются после открытия страницы. --}}
    @if($blagodarReady)
        @include('owner._blagodar_blocks')
    @else
        <div class="bd-lazy" data-lazy-src="{{ request()->fullUrlWithQuery(['part' => 'blagodar']) }}">
            <div class="sec" id="profit"><h2>Прибыль договоров</h2><span class="note">итоги CRM БлагоДар</span></div>
            <div class="card empty-card">Загружаю итоги из CRM БлагоДар…</div>
            <div class="sec" id="nonclosures"><h2>Незаключённые договоры по замерщикам</h2></div>
            <div class="card empty-card">Загружаю…</div>
        </div>
    @endif

    {{-- Операторы --}}
    <div class="card tbl-card" id="operators">
        <div class="tbl-head"><h2>Операторы</h2><span class="note">лид — за тем, кто первым его перетащил; «дожал чужих» — записал лиды, которые первым брал другой</span></div>
        <div class="tbl-wrap"><table class="t">
            <thead><tr>
                <th>Сотрудник</th><th class="num cat">Взял новых лидов</th><th class="num cat">Записал сам</th><th class="num cat">Не записал</th>
                <th class="num cat">Нецелевые</th><th class="num cat">Дожал чужих</th><th class="num cat">Всего на замер</th><th class="num cat">Обработано сделок</th>
            </tr></thead>
            <tbody>
            @forelse($kpi['operators'] as $o)
                <tr>
                    <td>@include('owner._op_ava', ['name' => $o['name']])</td>
                    <td class="num">{{ $n($o['taken']) }}</td>
                    <td class="num">{{ $n($o['booked_self']) }}</td>
                    <td class="num">{{ $n($o['not_booked']) }}@if($o['rescued'] > 0) <span class="note">записали другие: {{ $n($o['rescued']) }}</span>@endif</td>
                    <td class="num">{{ $n($o['non_target_taken']) }}</td>
                    <td class="num">{{ $n($o['booked_others']) }}</td>
                    <td class="num">{{ $n($o['bookings']) }}</td>
                    <td class="num">{{ $n($o['handled']) }}</td>
                </tr>
            @empty
                <tr><td colspan="8" class="muted">За период по сделкам никто не работал.</td></tr>
            @endforelse
            </tbody>
        </table></div>
    </div>

    {{-- Как считается: коротко по каждому показателю --}}
    <div class="card how" id="how">
        <h2><span class="ni-ic green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M12 16v-4M12 8h.01"/></svg></span>Как считается</h2>
        <div class="how-grid">
            <div class="how-item"><span class="ni-ic green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 3L3 21M14 4l6 6M4 14l6 6"/></svg></span><div><b>Замеры</b><p>Из Google-таблицы замеров КЦ — сводка перечитывает её каждые 30 секунд. Если за период в таблице пусто — по CRM: сделка дошла до «Замер назначен» или закрыта «Успешно».</p></div></div>
            <div class="how-item"><span class="ni-ic violet"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15a2 2 0 01-2 2H7l-4 4V5a2 2 0 012-2h14a2 2 0 012 2z"/></svg></span><div><b>Обратились в КЦ</b><p>Клиенты, которые сами позвонили, написали (ВК, Авито, Telegram) или оставили форму. Один номер — одно обращение. Исходящие звонки операторов не считаются.</p></div></div>
            <div class="how-item"><span class="ni-ic green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2l9 5-9 5-9-5 9-5z"/><path d="M3 12l9 5 9-5"/></svg></span><div><b>Источник</b><p>Первое обращение клиента за период: рекламный номер, чат или форма. «Прочее» — звонки на нерекламные номера.</p></div></div>
            <div class="how-item"><span class="ni-ic red"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="9"/><path d="M8 12h8"/></svg></span><div><b>Нецелевые, отказ, в работе, записались</b><p>По сделкам этих клиентов в CRM. Категорию нецелевого выбирает оператор (с 15.10.2026). «Записались» — тоже по CRM, поэтому может отличаться от плитки «Замеры».</p></div></div>
            <div class="how-item"><span class="ni-ic violet"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 16.9v3a2 2 0 01-2.2 2 19.8 19.8 0 01-8.6-3 19.5 19.5 0 01-6-6 19.8 19.8 0 01-3-8.6A2 2 0 014.1 2h3a2 2 0 012 1.7c.1.9.4 1.8.7 2.7a2 2 0 01-.5 2.1L8.1 9.9a16 16 0 006 6"/></svg></span><div><b>Звонки</b><p>События МегаФона. Пропущенный — входящий, который никто не принял.</p></div></div>
            <div class="how-item"><span class="ni-ic green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></span><div><b>Операторы</b><p>Лид закреплён за тем, кто первым взял его из «Поступил лид». «Дожал чужих» — записал лиды, которые первым брал другой. Дозвоны («Нецелевое дозвон») — не лиды.</p></div></div>
            <div class="how-item"><span class="ni-ic violet"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="5" width="20" height="14" rx="3"/><path d="M2 10h20M6 15h4"/></svg></span><div><b>Реклама</b><p>Расход — из кабинетов: Директ и VK каждые 2 часа (без НДС), Авито — каждый час, только объявления потолков. В таблицу Авито вносят до конца дня, поэтому там он обычно меньше.</p></div></div>
            <div class="how-item"><span class="ni-ic green"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 11-3-6.7L21 8"/><path d="M21 3v5h-5"/></svg></span><div><b>Обновление</b><p>Страница обновляется сама каждые 30 секунд, пока открыта; свернутая вкладка догоняет при возврате.</p></div></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
// Автообновление сводки: раз в 30 с та же страница запрашивается заново и подменяет содержимое — без перезагрузки,
// мигания и прыжка прокрутки; графики перерисовываются. Свернутая вкладка не опрашивает, при возврате обновляется
// сразу; пока вводят даты — ждёт.
(function () {
    var busy = false;
    // блоки CRM БлагоДар, которых не было в кэше: страница уже открыта, они догружаются отдельным запросом
    function lazyBlocks() {
        document.querySelectorAll('[data-lazy-src]').forEach(function (box) {
            if (box.dataset.loading) return;
            box.dataset.loading = '1';
            fetch(box.dataset.lazySrc, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.ok && !r.redirected ? r.text() : Promise.reject(r.status); })
                .then(function (html) {
                    var tpl = document.createElement('template');
                    tpl.innerHTML = html;
                    box.replaceWith(tpl.content);
                    window.bdCharts.draw();
                })
                .catch(function () { delete box.dataset.loading; box.querySelectorAll('.empty-card').forEach(function (e) { e.textContent = 'CRM БлагоДар пока не ответила — попробую при следующем обновлении страницы.'; }); });
        });
    }
    lazyBlocks();
    function refresh() {
        if (busy || document.hidden) return;
        var a = document.activeElement;
        if ((a && a.closest && a.closest('#dash form')) || window.bdDateRange.isOpen()) return;
        busy = true;
        fetch(location.href, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.ok && !r.redirected ? r.text() : null; })
            .then(function (html) {
                if (!html) return;
                var doc = new DOMParser().parseFromString(html, 'text/html');
                var fresh = doc.getElementById('dash'), cur = document.getElementById('dash');
                if (fresh && cur) { cur.replaceWith(fresh); window.bdCharts.draw(); window.bdDateRange.init(); lazyBlocks(); }
                var sub = doc.querySelector('.hsub'), curSub = document.querySelector('.hsub');
                if (sub && curSub) curSub.textContent = sub.textContent;
            })
            .catch(function () {})
            .then(function () { busy = false; });
    }
    setInterval(refresh, 30000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) refresh(); });
})();
</script>
@endpush
