@extends('layouts.owner')

@php
    $titles = ['ceilings' => 'Потолки · БлагоДар', 'sneakers' => 'Кроссовки · Шаговый'];
    $back = $business === 'ceilings' ? route('owner.ceilings') : route('owner.dashboard');
    $isSmm = $isSmm ?? false;
    $smmBusinesses = $smmBusinesses ?? [$business];
    $rn = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $rm = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' ₽';
@endphp

@section('brand', $business === 'sneakers' ? 'shag' : 'blagodar')
@section('title', 'SMM-контент')
@section('subtitle')SMM-контент · {{ $titles[$business] }} · реклама клипов VK: что идёт, какие клипы тянут, что заменить@endsection

@section('nav')
    @if($isSmm)
        {{-- SMM-специалисту — только его сообщества: сводки бизнеса ему не открываются --}}
        {{-- сообщество выбирается вкладками сверху; здесь — разделы страницы (подсвечивает прокрутка) --}}
        <a href="#results" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><path d="M7 15l4-4 3 3 5-6"/></svg> Результат</a>
        <a href="#brief"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M10 8l5 4-5 4V8z"/></svg> Реклама клипов</a>
        <a href="#instagram"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg> Instagram</a>
        @foreach($smmBusinesses as $b)
            @continue($b === $business)
            <a href="{{ route('owner.smm', ['business' => $b]) }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg> {{ $b === 'ceilings' ? 'ВК · БлагоДар' : 'ВК · Шаговый' }}</a>
        @endforeach
    @else
        <a href="{{ $back }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg> Сводка</a>
        <a href="#top" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M10 8l5 4-5 4V8z"/></svg> Клипы</a>
        <a href="#instagram"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="2" width="20" height="20" rx="5"/><circle cx="12" cy="12" r="4"/><circle cx="17.5" cy="6.5" r="1"/></svg> Instagram</a>
    @endif
@endsection

@section('content')
<div class="filters" id="top">
    @if($isSmm)
        @if(count($smmBusinesses) > 1)
            <div class="seg accent">
                @foreach($smmBusinesses as $b)
                    <a href="{{ route('owner.smm', ['business' => $b] + request()->only(['days', 'from', 'to'])) }}" class="{{ $b === $business ? 'active' : '' }}">{{ $b === 'ceilings' ? 'ВК · БлагоДар' : 'ВК · Шаговый' }}</a>
                @endforeach
            </div>
        @endif
    @else
        @include('owner._biz_tabs', ['biz' => $business, 'tab' => 'smm'])
    @endif
    <div class="seg">
        @foreach($periods as $d => $label)
            <a href="{{ route('owner.smm', ['business' => $business, 'days' => $d]) }}" class="{{ $days === $d ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="spacer"></div>
    @include('owner._daterange', ['action' => route('owner.smm', ['business' => $business]), 'params' => [], 'from' => $report['date_from'], 'to' => $report['date_to']])
</div>

{{-- Результат для бизнеса: что продвижение ВК дало в CRM за этот же период (SmmResults) — без имён и телефонов --}}
@if(!empty($results))
    <div class="sec" id="results"><h2>Результат для бизнеса</h2><span class="note">клиенты, пришедшие из ВКонтакте, по CRM за выбранный период · цена — расход кабинета VK Рекламы, делённый на них</span></div>
    <div class="minis">
        @if($results['business'] === 'ceilings')
            <div class="card mini"><div class="lbl">Обратились из ВК</div><div class="val num">{{ $rn($results['leads']) }}</div>
                <div class="sub">@forelse($results['sources'] as $s){{ $s['label'] }} — {{ $rn($s['leads']) }}@if(!$loop->last) · @endif @empty сообщения, лид-формы и звонки на номер из ВК @endforelse</div></div>
            <div class="card mini"><div class="lbl">Нецелевые</div><div class="val num">{{ $rn($results['non_target']) }}</div>
                <div class="sub">колл-центр отметил их нецелевыми · целевых {{ $rn($results['target']) }}</div></div>
            <div class="card mini"><div class="lbl">Записаны на замер</div><div class="val num">{{ $rn($results['booked']) }}</div>
                <div class="sub">{{ $results['conversion'] !== null ? $results['conversion'].'% целевых клиентов' : 'целевых клиентов из ВК пока нет' }}</div></div>
            <div class="card mini"><div class="lbl">Цена целевого клиента</div><div class="val num {{ $results['per_target'] === null ? 'muted' : '' }}">{{ $rm($results['per_target']) }}</div>
                <div class="sub">расход {{ $results['spend'] > 0 ? $rm($results['spend']) : '—' }}@if($results['per_lead'] !== null) · на любого обратившегося {{ $rm($results['per_lead']) }}@endif</div></div>
            <div class="card mini"><div class="lbl">Цена замера</div><div class="val num {{ $results['per_booked'] === null ? 'muted' : '' }}">{{ $rm($results['per_booked']) }}</div>
                <div class="sub">расход на одного записанного</div></div>
        @else
            <div class="card mini"><div class="lbl">Продаж из ВК</div><div class="val num">{{ $rn($results['sales']) }}</div>
                <div class="sub">источник «ВК» в продаже@if($results['vk_connected']) или покупатель из сообщений ВК@endif</div></div>
            <div class="card mini"><div class="lbl">Цена продажи</div><div class="val num {{ $results['per_sale'] === null ? 'muted' : '' }}">{{ $rm($results['per_sale']) }}</div>
                <div class="sub">расход на одну продажу из ВК</div></div>
            <div class="card mini"><div class="lbl">Расход</div><div class="val num {{ $results['spend'] > 0 ? '' : 'muted' }}">{{ $results['spend'] > 0 ? $rm($results['spend']) : '—' }}</div>
                <div class="sub">кабинет VK Рекламы за период</div></div>
            <div class="card mini"><div class="lbl">Всего продаж</div><div class="val num">{{ $rn($results['sales_total']) }}</div>
                <div class="sub">@if($results['no_source'] > 0)у {{ $rn($results['no_source']) }} источник не указан — часть может быть из ВК@else у всех указан источник@endif</div></div>
            <div class="card mini"><div class="lbl">Обращений из ВК</div><div class="val num {{ $results['vk_connected'] ? '' : 'muted' }}">{{ $results['vk_connected'] ? $rn($results['leads']) : '—' }}</div>
                <div class="sub">{{ $results['vk_connected'] ? 'сообщения сообществу и лид-формы' : 'сообщения сообщества «Шаговый» в CRM не подключены' }}</div></div>
        @endif
    </div>
@endif

{{-- Instagram сообщества: подписчики и публикации периода (App\Services\Owner\InstagramStats) --}}
@if(!empty($instagram))
    @php
        $ig = $instagram;
        $igName = $business === 'ceilings' ? 'БлагоДар' : '«Шаговый»';
        $igTypes = ['REELS' => 'Reels', 'VIDEO' => 'Видео', 'IMAGE' => 'Фото', 'CAROUSEL_ALBUM' => 'Карусель'];
        $igN = fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ');
    @endphp
    <div class="sec" id="instagram"><h2>Instagram{{ !empty($ig['username']) ? ' · @'.$ig['username'] : '' }}</h2>
        <span class="note">@if($ig['connected'] && $ig['updated'])обновлено {{ $ig['updated'] }} · @endif просмотры, охват и реакции — за всё время публикации</span></div>
    @if(! $ig['connected'])
        <div class="card empty-card">Instagram {{ $igName }} ещё не подключён. Подключает администратор: «Настройки → Интеграции → Instagram (статистика)».</div>
    @elseif(! $ig['collected'])
        <div class="card empty-card">Instagram подключён — первые цифры появятся после ближайшего сбора (раз в два часа).@if($ig['error']) Ошибка: {{ $ig['error'] }}@endif</div>
    @else
        @if($ig['error'])<div class="card smm-alert">Последний сбор не удался: {{ $ig['error'] }} — показаны прошлые цифры.</div>@endif
        <div class="minis">
            <div class="card mini"><div class="lbl">Подписчики</div><div class="val num">{{ $igN($ig['followers']) }}</div>
                <div class="sub">@if($ig['growth'] !== null){{ $ig['growth'] >= 0 ? '+' : '' }}{{ $igN($ig['growth']) }} с {{ $ig['growth_since'] }}@elseif($ig['new_followers'] !== null)новых за период: {{ $igN($ig['new_followers']) }}@else прирост — когда накопятся ежедневные снимки@endif</div></div>
            <div class="card mini"><div class="lbl">Публикаций</div><div class="val num">{{ $igN($ig['count']) }}</div>
                <div class="sub">за выбранный период</div></div>
            <div class="card mini"><div class="lbl">Просмотры</div><div class="val num {{ $ig['views'] === null ? 'muted' : '' }}">{{ $igN($ig['views']) }}</div>
                <div class="sub">{{ $ig['avg_views'] !== null ? 'в среднем '.$igN($ig['avg_views']).' на публикацию' : 'публикаций периода нет' }}</div></div>
            <div class="card mini"><div class="lbl">Охват</div><div class="val num {{ $ig['reach'] === null ? 'muted' : '' }}">{{ $igN($ig['reach']) }}</div>
                <div class="sub">уникальные аккаунты, сумма по публикациям</div></div>
            <div class="card mini"><div class="lbl">Реакции</div><div class="val num {{ $ig['engagement'] === null ? 'muted' : '' }}">{{ $igN($ig['engagement']) }}</div>
                <div class="sub">лайки, комментарии, сохранения, репосты</div></div>
        </div>
        @php($igList = $ig['posts'] ?: $ig['latest'])
        @if($igList)
            <div class="card tbl-card">
                <div class="tbl-head"><h2>{{ $ig['posts'] ? 'Публикации за период' : 'Последние публикации' }}</h2><span class="note">{{ $ig['posts'] ? 'сначала самые просматриваемые' : 'за выбранный период публикаций не было' }}</span></div>
                <div class="tbl-wrap"><table class="t sortable">
                    <thead><tr><th>Дата</th><th>Тип</th><th>Публикация</th><th class="num">Просмотры</th><th class="num">Охват</th><th class="num">Лайки</th><th class="num">Комм.</th><th class="num">Сохр.</th><th class="num">Репосты</th></tr></thead>
                    <tbody>
                    @foreach($igList as $p)
                        <tr>
                            <td class="nowrap">{{ $p['at'] ? \Carbon\Carbon::parse($p['at'])->format('d.m H:i') : '—' }}</td>
                            <td>{{ $igTypes[$p['type']] ?? $p['type'] }}</td>
                            <td>@if($p['permalink'])<a href="{{ $p['permalink'] }}" target="_blank" rel="noopener">{{ $p['caption'] !== '' ? \Illuminate\Support\Str::limit($p['caption'], 70) : 'без подписи' }}</a>@else{{ \Illuminate\Support\Str::limit($p['caption'], 70) }}@endif</td>
                            <td class="num">{{ $igN($p['views']) }}</td>
                            <td class="num">{{ $igN($p['reach']) }}</td>
                            <td class="num">{{ $igN($p['likes']) }}</td>
                            <td class="num">{{ $igN($p['comments']) }}</td>
                            <td class="num">{{ $igN($p['saved']) }}</td>
                            <td class="num">{{ $igN($p['shares']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endif
    @endif
@endif

{{-- SMM-контент в дизайн-системе сводок (прототип дизайнера): итог, 6 плиток, кампании, расход по дням, клипы,
     лид-формы, что улучшить. Считает и рисует скрипт ниже из JSON отчёта (App\Services\Owner\SmmReport). --}}
<div class="smm-report">
    <p class="smm-note" id="period"></p>
    <script id="report-data" type="application/json" data-biz="{{ $business === 'ceilings' ? 'БлагоДар · Потолки' : 'Кроссовки' }}">{!! json_encode($report, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

@verbatim
  <div class="seg smm-tabs" role="tablist" aria-label="Кабинет" id="tabs"></div>

  <div id="view">
    <div class="card smm-alert" id="alert" hidden></div>

    <div class="card insights" id="brief">
      <div class="st"><span class="tag" id="briefPill"></span> <span id="briefTitle"></span></div>
      <ul id="briefList"></ul>
    </div>

    <div class="cc smm-kpis" id="kpis"></div>

    <div class="card tbl-card">
      <div class="tbl-head"><h2>Кампании</h2><span class="note">действующие и тратившие за период</span></div>
      <div class="tbl-wrap"><table class="t" id="planTable">
        <thead><tr><th>Кампания</th><th>Статус</th><th>Тип</th><th class="num">Лимит/день</th><th class="num">Групп</th><th>Что мешает</th></tr></thead>
        <tbody></tbody>
      </table></div>
    </div>

    <div class="card chartcard" id="spend">
      <div class="sec" style="margin:0 0 4px"><h2>Расход по дням</h2><span class="note" id="chartNote"></span></div>
      <div class="legend" id="chartLegend"></div>
      <div class="chart-h"><canvas id="smmDaily"></canvas></div>
      <p class="smm-note" id="chartSub"></p>
    </div>

    <div class="card tbl-card" id="clips">
      <div class="tbl-head"><h2>Каждый клип</h2><span class="note">динамика CTR: первые 7 дней показов против последних 7 · оценка — от 9 000 показов</span></div>
      <div class="tbl-wrap"><table class="t compact" id="clipTable">
        <thead><tr><th>Клип</th><th>Статус</th><th class="num">Показы</th><th class="num">CTR</th><th>Динамика CTR</th><th class="num">Досмотры 100%</th><th class="num">₽/досмотр</th><th class="num">₽/клик</th><th class="num vj-col">Вступления</th><th class="num vj-col">₽/вступление</th><th class="num">Расход</th></tr></thead>
        <tbody></tbody>
      </table></div>
    </div>

    <div class="card tbl-card">
      <div class="tbl-head"><h2>Заявки (лид-формы)</h2></div>
      <div class="tbl-wrap"><table class="t" id="leadTable">
        <thead><tr><th>Группа</th><th>Статус</th><th class="num">Показы</th><th class="num">Клики</th><th class="num">CTR</th><th class="num">Заявки</th><th class="num">₽/заявку</th><th class="num">Расход</th><th>Дни показа</th></tr></thead>
        <tbody></tbody>
      </table></div>
    </div>

    <div class="sec" id="recsHead"><h2>Что улучшить</h2><span class="note">считается из свежих цифр · изменения в кабинете — только после согласования</span></div>
    <div class="smm-recs" id="recs"></div>
  </div>
@endverbatim
    <p class="smm-note">цифры кабинета VK Рекламы обновляются каждые 2 часа</p>
</div>

@verbatim
<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var fmt = function (n, d) { return Number(n || 0).toLocaleString('ru-RU', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 }); };
  var dm = function (s) { return s ? s.slice(8, 10) + '.' + s.slice(5, 7) : ''; };
  var dmy = function (s) { return s ? dm(s) + '.' + s.slice(0, 4) : ''; };
  var BIZ = document.getElementById('report-data').dataset.biz || 'БлагоДар';
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var b = function (s) { return '<b>' + esc(s) + '</b>'; };
  var plural = function (n, one, few, many) { var m10 = n % 10, m100 = n % 100; return (m10 === 1 && m100 !== 11) ? one : (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) ? few : many; };
  var sum = function (arr, k) { return arr.reduce(function (s, g) { return s + (g[k] || 0); }, 0); };
  var DASH = '<span class="muted">—</span>';

  var R, raw = $('report-data').textContent.trim();
  try { R = JSON.parse(raw); } catch (e) {
    R = { cabinets: [], templateError: 'Данные отчёта не загрузились.' };
  }
  var cabs = R.cabinets || [];
  var DAYS = R.period_days + ' ' + plural(R.period_days || 0, 'день', 'дня', 'дней');
  $('period').textContent = R.date_from
    ? 'Кабинет VK Рекламы за ' + dmy(R.date_from) + '–' + dmy(R.date_to) + ' (' + DAYS + ', сегодня — неполный день) · сформировано ' + dmy(R.generated.slice(0, 10)) + ' в ' + R.generated.slice(11)
    : '';
  if (!cabs.length) { $('alert').hidden = false; $('alert').textContent = R.templateError || 'В отчёте нет данных.'; return; }

  var ISSUE = {
    NO_MONEY: 'на балансе нет денег', AD_PLAN_STOPPED: 'кампания на паузе', NO_ALLOWED_BANNERS: 'нет одобренных креативов',
    STOPPED: 'группа на паузе', ARCHIVED: 'в архиве', AD_PLAN_ARCHIVED: 'кампания в архиве', ON_MODERATION: 'на модерации', BANNED: 'отклонено модерацией'
  };
  var REASON = { NO_ORGN_BANNER: 'не заполнено «О рекламодателе» (ОГРН/ИНН)' };
  var OBJ = { branding_socialengagement: 'Продвижение клипов', leadads: 'Лид-форма (заявки)', socialengagement: 'Вовлечение' };
  var reasonText = function (codes) { return (codes || []).map(function (c) { return REASON[c] || c; }).join('; '); };
  var isOn = function (g) { return g.status === 'active' && g.plan_status === 'active'; };
  var ctrOf = function (g) { return g.shows ? 100 * g.clicks / g.shows : 0; };
  var vpOf = function (g) { return g.shows ? 100 * g.v100 / g.shows : 0; };

  function verdict(g) {
    if (g.moderation === 'banned') return 'banned';
    if (g.moderation === 'pending') return 'moder';
    if (!isOn(g)) return g.shows > 0 ? 'off' : 'paused';
    if (g.shows < 9000) return 'new';
    var ctr = ctrOf(g), vp = vpOf(g);
    if (ctr < 0.30 || vp < 12) return 'weak';
    if (g.ctr_first != null && g.ctr_last != null && g.ctr_first - g.ctr_last >= 0.12 && g.ctr_last < 0.45) return 'fatigue';
    if (ctr >= 0.45 && vp >= 20) return 'lead';
    return 'mid';
  }
  var VD = {
    lead: ['Лидер', 'green'], fatigue: ['Выгорает', 'amber'], mid: ['Средний', 'gray'], weak: ['Заменить', 'red'],
    'new': ['Рано судить', 'blue'], moder: ['На модерации', 'amber'], banned: ['Отклонён', 'red'], paused: ['Готов, на паузе', 'gray'], off: ['Остановлен', 'gray']
  };
  var ORDER = { lead: 0, fatigue: 0, mid: 0, weak: 0, 'new': 0, moder: 1, banned: 1, paused: 2, off: 3 };
  var tag = function (text, cls) { return '<span class="tag ' + cls + '">' + esc(text) + '</span>'; };

  // Derived numbers for one cabinet
  function analyse(c) {
    var groups = c.groups || [], days = c.days || [], plans = c.plans || [];
    var clips = groups.filter(function (g) { return g.type === 'clip'; });
    clips.forEach(function (g) { g.v = verdict(g); });
    var running = clips.filter(function (g) { return isOn(g) && g.moderation === 'allowed'; });
    var clipPlans = plans.filter(function (p) { return p.objective === 'branding_socialengagement'; });
    var mainPlan = clipPlans.filter(function (p) { return p.status === 'active'; })[0] || clipPlans.slice().sort(function (a, b) { return (b.created || '').localeCompare(a.created || ''); })[0] || null;
    var limit = clipPlans.filter(function (p) { return p.status === 'active'; }).reduce(function (s, p) { return s + (p.day_limit || 0); }, 0);
    var last7 = days.slice(0, -1).slice(-7);
    var avg7 = last7.length ? last7.reduce(function (s, d) { return s + d.clip; }, 0) / last7.length : 0;
    var cs = { spent: sum(clips, 'spent'), shows: sum(clips, 'shows'), clicks: sum(clips, 'clicks'), v100: sum(clips, 'v100'), joins: sum(clips, 'joins') };
    var leads = groups.filter(function (g) { return g.type === 'lead'; });
    return { groups: groups, days: days, plans: plans, clips: clips, running: running, mainPlan: mainPlan, limit: limit, avg7: avg7, cs: cs, leads: leads };
  }

  var current = null;

  function renderBrief(c, a) {
    var items = [], pill, title;
    var banned = a.clips.filter(function (g) { return g.v === 'banned' && g.status !== 'deleted'; });
    var moder = a.clips.filter(function (g) { return g.v === 'moder'; });
    var noMoney = a.groups.some(function (g) { return (g.issues || []).indexOf('NO_MONEY') >= 0; });
    if (a.running.length) {
      pill = ['Идёт', 'green'];
      title = 'Реклама идёт: ' + a.running.length + ' ' + plural(a.running.length, 'клип', 'клипа', 'клипов') + ' в показах';
      items.push('За последние 7 дней — в среднем ' + b(fmt(Math.round(a.avg7)) + ' ₽/день') + (a.limit ? ' из ' + esc(fmt(a.limit)) + ' ₽ лимита' : '') + '.');
      if (a.cs.shows) items.push('За ' + esc(DAYS) + ': ' + b(fmt(a.cs.spent) + ' ₽') + ', ' + esc(fmt(a.cs.shows)) + ' показов, CTR ' + esc(fmt(100 * a.cs.clicks / a.cs.shows, 2)) + '%, досмотрели до конца ' + esc(fmt(100 * a.cs.v100 / a.cs.shows, 1)) + '%.');
      if (a.cs.shows) items.push('Вступили в сообщество ВК: ' + b(fmt(a.cs.joins)) + (a.cs.joins ? ' — по ' + esc(fmt(a.cs.spent / a.cs.joins)) + ' ₽ за вступление' : '') + '.');
      var attention = [];
      var weak = a.clips.filter(function (g) { return g.v === 'weak'; }), fat = a.clips.filter(function (g) { return g.v === 'fatigue'; });
      if (weak.length) attention.push(b('заменить ' + weak.map(function (g) { return g.clip; }).join(', ')));
      if (fat.length) attention.push(esc('выгорает ' + fat.map(function (g) { return g.clip; }).join(', ')));
      if (moder.length) attention.push(esc(moder.length + ' ' + plural(moder.length, 'новый клип', 'новых клипа', 'новых клипов') + ' на модерации'));
      if (banned.length) attention.push(esc(banned.length + ' ' + plural(banned.length, 'клип отклонён', 'клипа отклонены', 'клипов отклонены') + ' модерацией'));
      if (attention.length) { pill = ['Идёт, есть вопросы', 'amber']; items.push('Требует внимания: ' + attention.join('; ') + '.'); }
    } else {
      pill = ['Не идёт', 'red'];
      title = 'Реклама сейчас не идёт';
      var why = [];
      if (a.mainPlan && a.mainPlan.status !== 'active') why.push('кампания «' + a.mainPlan.name + '» на паузе');
      if (noMoney) why.push('на балансе нет денег');
      if (banned.length) why.push(banned.length + ' ' + plural(banned.length, 'креатив отклонён', 'креатива отклонены', 'креативов отклонены') + ': ' + reasonText(Array.from(new Set([].concat.apply([], banned.map(function (g) { return g.reasons || []; }))))));
      if (moder.length) why.push(moder.length + ' на модерации');
      if (!a.clips.length) why.push('клиповых кампаний за период не было');
      items.push('Причины: ' + esc(why.join('; ') || 'не определены') + '.');
      if (a.clips.length) items.push(esc('Подготовлено ' + a.clips.length + ' ' + plural(a.clips.length, 'клип', 'клипа', 'клипов') + (a.mainPlan ? ' в кампании ' + a.mainPlan.id : '') + '.'));
    }
    var leadN = sum(a.leads, 'leads'), leadSpent = sum(a.leads, 'spent');
    if (a.leads.length) items.push(esc('Заявки за ' + DAYS + ': ' + fmt(leadN) + (leadN ? ' по ' + fmt(leadSpent / leadN) + ' ₽' : '') + '; лид-форма ' + (a.leads.some(isOn) ? 'работает' : 'на паузе') + '.'));
    else items.push('Лид-форм за период не было: реклама даёт только охват.');
    $('briefPill').className = 'tag ' + pill[1]; $('briefPill').textContent = pill[0];
    $('briefTitle').textContent = title;
    $('briefList').innerHTML = items.map(function (t) { return '<li>' + t + '</li>'; }).join('');
  }

  function renderPlans(c, a) {
    var tb = document.querySelector('#planTable tbody');
    if (!a.plans.length) { tb.innerHTML = '<tr><td class="muted" colspan="6">Кампаний за период нет.</td></tr>'; return; }
    tb.innerHTML = a.plans.map(function (p) {
      var gs = a.groups.filter(function (g) { return g.plan_id === p.id && g.status !== 'deleted'; });
      var probs = {};
      gs.forEach(function (g) {
        (g.issues || []).forEach(function (code) { if (ISSUE[code] && code !== 'STOPPED') probs[ISSUE[code]] = 1; });
        if (g.moderation === 'banned') probs['отклонено модерацией: ' + reasonText(g.reasons)] = 1;
      });
      if (p.status === 'active') delete probs[ISSUE.AD_PLAN_STOPPED];
      var probText = Object.keys(probs).join('; ');
      return '<tr><td class="wrap">' + esc(p.name) + '<div class="muted smm-sub">№ ' + esc(p.id) + ' · ' + dmy((p.created || '').slice(0, 10)) + '</div></td>' +
        '<td>' + tag(p.status === 'active' ? 'Активна' : 'На паузе', p.status === 'active' ? 'green' : 'gray') + '</td>' +
        '<td>' + esc(OBJ[p.objective] || p.objective) + '</td>' +
        '<td class="num">' + (p.day_limit ? fmt(p.day_limit) + ' ₽' : DASH) + '</td>' +
        '<td class="num">' + gs.length + '</td>' +
        '<td class="muted wrap">' + (probText ? esc(probText) : '—') + '</td></tr>';
    }).join('');
  }

  function renderKpis(c, a) {
    var cs = a.cs;
    if (!cs.shows) { $('kpis').innerHTML = '<div class="card smm-empty">За ' + esc(DAYS) + ' показов клипов не было.</div>'; return; }
    var kp = [
      ['Расход на клипы', fmt(cs.spent) + ' ₽', 'за ' + DAYS],
      ['Показов', fmt(cs.shows), fmt(cs.clicks) + ' ' + plural(cs.clicks, 'клик', 'клика', 'кликов')],
      ['CTR', fmt(100 * cs.clicks / cs.shows, 2) + '%', 'кликов ÷ показы'],
      ['Досмотры до конца', fmt(100 * cs.v100 / cs.shows, 1) + '%', fmt(cs.v100) + ' ' + plural(cs.v100, 'полный досмотр', 'полных досмотра', 'полных досмотров')],
      ['Цена досмотра', cs.v100 ? fmt(cs.spent / cs.v100, 2) + ' ₽' : '—', 'за один полный досмотр'],
      ['В день (7 дней)', fmt(Math.round(a.avg7 / 10) * 10) + ' ₽', a.limit ? 'при лимите ' + fmt(a.limit) + ' ₽' : 'активного лимита нет']
    ];
    $('kpis').innerHTML = kp.map(function (k) { return '<div class="card"><div class="lbl">' + esc(k[0]) + '</div><div class="val num">' + esc(k[1]) + '</div><div class="sub">' + esc(k[2]) + '</div></div>'; }).join('');
  }

  var PLAY = ' <svg width="12" height="12" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>';
  function renderClips(c, a) {
    var tb = document.querySelector('#clipTable tbody');
    var rows = a.clips.slice().sort(function (x, y) { return (ORDER[x.v] - ORDER[y.v]) || (y.spent - x.spent) || (y.id - x.id); });
    if (!rows.length) { tb.innerHTML = '<tr><td class="muted" colspan="11">Клипов в рекламе за период не было.</td></tr>'; return; }
    tb.innerHTML = rows.map(function (g) {
      var has = g.shows > 0, trend = DASH;
      if (g.ctr_first != null && g.ctr_last != null) {
        var cls = g.ctr_last < g.ctr_first - 0.03 ? 'smm-down' : (g.ctr_last > g.ctr_first + 0.03 ? 'smm-up' : 'muted');
        trend = '<span class="' + cls + '">' + fmt(g.ctr_first, 2) + '% → ' + fmt(g.ctr_last, 2) + '%</span>';
      }
      // ▶ — плеер клипа поверх страницы (layouts/owner, public/js/bd-owner-head.js)
      var label = g.clip ? '<button type="button" class="watch" data-oid="' + esc(g.owner) + '" data-vid="' + esc(g.clip) + '" data-url="https://vk.ru/clip' + esc(g.owner) + '_' + esc(g.clip) + '"' +
        ' data-title="Клип ' + esc(g.clip) + '" data-sub="' + esc('VK Клип · ' + BIZ + ' · ' + VD[g.v][0]) + '" title="Смотреть клип">' + esc(g.clip) + PLAY + '</button>' : esc(g.name);
      var sub = g.v === 'banned' && g.reasons && g.reasons.length ? '<div class="muted smm-sub">' + esc(reasonText(g.reasons)) + '</div>' : '';
      var dim = ['off', 'paused'].indexOf(g.v) >= 0;
      var num = function (v) { return '<td class="num' + (has ? '' : ' muted') + '">' + (has ? v : '—') + '</td>'; };
      return '<tr' + (dim ? ' class="smm-dim"' : '') + '><td>' + label + '</td><td>' + tag(VD[g.v][0], VD[g.v][1]) + sub + '</td>' +
        num(fmt(g.shows)) + num(fmt(ctrOf(g), 2) + '%') + '<td>' + trend + '</td>' + num(fmt(vpOf(g), 1) + '%') +
        '<td class="num">' + (g.v100 ? fmt(g.spent / g.v100, 2) + ' ₽' : DASH) + '</td>' +
        '<td class="num">' + (g.clicks ? fmt(g.spent / g.clicks) + ' ₽' : DASH) + '</td>' +
        // вступления в сообщество ВК — главный результат клипа, столбцы выделены
        '<td class="num vj-col">' + (g.joins ? '<b>' + fmt(g.joins) + '</b>' : DASH) + '</td>' +
        '<td class="num vj-col">' + (g.joins ? fmt(g.spent / g.joins) + ' ₽' : DASH) + '</td>' + num(fmt(g.spent) + ' ₽') + '</tr>';
    }).join('');
  }

  function renderLeads(c, a) {
    var tb = document.querySelector('#leadTable tbody');
    var rows = a.leads.slice().sort(function (x, y) { return y.spent - x.spent; });
    if (!rows.length) { tb.innerHTML = '<tr><td class="muted" colspan="9">За период лид-формы не показывались.</td></tr>'; return; }
    tb.innerHTML = rows.map(function (g) {
      return '<tr' + (isOn(g) ? '' : ' class="smm-dim"') + '><td class="wrap">' + esc(g.name) + '</td>' +
        '<td>' + tag(isOn(g) ? 'Активна' : 'На паузе', isOn(g) ? 'green' : 'gray') + '</td>' +
        '<td class="num">' + fmt(g.shows) + '</td><td class="num">' + fmt(g.clicks) + '</td><td class="num">' + fmt(ctrOf(g), 2) + '%</td>' +
        '<td class="num">' + fmt(g.leads) + '</td><td class="num">' + (g.leads ? fmt(g.spent / g.leads) + ' ₽' : '—') + '</td>' +
        '<td class="num">' + fmt(g.spent) + ' ₽</td><td>' + (g.first_date ? dm(g.first_date) + '–' + dm(g.last_date) : '—') + '</td></tr>';
    }).join('');
  }

  // Расход по дням — столбцы дизайн-системы (window.bdCharts в layouts/owner): клипы, лид-формы, прочее + лимит клипов
  function renderChart(c, a) {
    var days = a.days;
    var maxDay = days.reduce(function (m, d) { return Math.max(m, d.clip + d.lead + d.other); }, 0);
    var sets = [['clip', 'клипы', '--accent'], ['lead', 'лид-формы', '#94a3b8'], ['other', 'прочее', '#c9b98f']]
      .filter(function (s) { return days.some(function (d) { return (d[s[0]] || 0) > 0; }); })
      .map(function (s) { return { label: s[1], color: s[2], data: days.map(function (d) { return Math.round(d[s[0]] || 0); }) }; });
    $('smmDaily').dataset.bars = JSON.stringify({
      labels: days.map(function (d) { return dm(d.d); }), titles: days.map(function (d) { return dmy(d.d); }),
      sets: sets.length ? sets : [{ label: 'клипы', color: '--accent', data: days.map(function () { return 0; }) }],
      stacked: true, suffix: ' ₽', limit: a.limit ? { label: 'лимит клипов', value: a.limit } : null
    });
    $('chartLegend').innerHTML = sets.map(function (s) {
      return '<span class="lg"><span class="dot2" style="background:' + (s.color.indexOf('--') === 0 ? 'var(' + s.color + ')' : s.color) + '"></span>' + esc(s.label) + '</span>';
    }).join('') + (a.limit ? '<span class="lg"><span class="dot2 dash"></span>лимит ' + esc(fmt(a.limit)) + ' ₽/день</span>' : '');
    $('chartNote').textContent = DAYS + (a.limit ? ' · лимит ' + fmt(a.limit) + ' ₽/день' : '');
    $('chartSub').textContent = maxDay === 0 ? 'За ' + DAYS + ' расходов не было.'
      : (a.limit ? 'Клипы за последние 7 дней тратили в среднем ' + fmt(Math.round(a.avg7)) + ' ₽ в день — ' + fmt(100 * a.avg7 / a.limit) + '% лимита.' : 'Активных клиповых кампаний сейчас нет.');
    if (window.bdCharts) window.bdCharts.draw();
  }

  function renderRecs(c, a) {
    var recs = [];
    var add = function (tag, cls, title, why, act) { recs.push({ tag: tag, cls: cls, title: title, why: why, act: act }); };
    var banned = a.clips.filter(function (g) { return g.v === 'banned'; });
    var orgn = banned.filter(function (g) { return (g.reasons || []).indexOf('NO_ORGN_BANNER') >= 0; });
    var otherBanned = banned.filter(function (g) { return orgn.indexOf(g) < 0; });
    var noMoney = a.groups.some(function (g) { return (g.issues || []).indexOf('NO_MONEY') >= 0; });
    if (orgn.length) add('Сейчас', 'red', 'Заполнить «О рекламодателе»',
      'Модерация отклонила ' + orgn.length + ' ' + plural(orgn.length, 'креатив', 'креатива', 'креативов') + ' (' + orgn.map(function (g) { return g.clip; }).join(', ') + '): по закону о рекламе нужны данные рекламодателя.',
      'В кабинете VK Рекламы заполнить «О рекламодателе» (ООО: название, адрес, ОГРН; ИП: ФИО и ОГРНИП) и отправить креативы на повторную проверку.');
    otherBanned.forEach(function (g) { add('Сейчас', 'red', 'Клип ' + g.clip + ' отклонён', 'Причина: ' + (reasonText(g.reasons) || 'не указана') + '.', 'Исправить по замечанию модерации или заменить клип.'); });
    if (!a.running.length && a.clips.length) {
      if (noMoney) add('Сейчас', 'red', 'Пополнить баланс', 'Кабинет сообщает, что на балансе нет денег: без этого показы не начнутся.', 'Пополнить баланс кабинета VK Рекламы.');
      if (a.mainPlan && a.mainPlan.status !== 'active') add('Перед запуском', 'amber', 'Включить кампанию ' + a.mainPlan.id, 'Кампания «' + a.mainPlan.name + '» на паузе.', 'Включить после пополнения баланса и одобрения креативов.');
    }
    if (!a.running.length && !a.clips.length) add('Предложение', 'amber', 'Запустить продвижение клипов', 'За ' + DAYS + ' клиповой рекламы не было.', 'Подобрать клипы и согласовать запуск с SMM-специалистом.');
    var moder = a.clips.filter(function (g) { return g.v === 'moder'; });
    if (moder.length) add('Наблюдать', 'gray', 'На модерации: ' + moder.map(function (g) { return g.clip; }).join(', '), 'Обычно проверка занимает от нескольких минут до нескольких часов.', 'После одобрения включить новые клипы' + (a.running.length ? ' и отключить те, которые они заменяют.' : '.'));
    var leader = a.clips.filter(function (g) { return g.v === 'lead'; }).sort(function (x, y) { return ctrOf(y) - ctrOf(x); })[0];
    a.clips.filter(function (g) { return g.v === 'weak'; }).forEach(function (g) {
      add('Сейчас', 'red', 'Заменить клип ' + g.clip,
        'CTR ' + fmt(ctrOf(g), 2) + '%, досмотры ' + fmt(vpOf(g), 1) + '%, клик ' + (g.clicks ? fmt(g.spent / g.clicks) : '—') + ' ₽' + (leader ? '; у лидера ' + leader.clip + ': CTR ' + fmt(ctrOf(leader), 2) + '%, досмотры ' + fmt(vpOf(leader), 1) + '%.' : '.'),
        'Отключить группу ' + g.id + ' и поставить на её место новый клип.');
    });
    a.clips.filter(function (g) { return g.v === 'fatigue'; }).forEach(function (g) {
      add('На этой неделе', 'amber', 'Готовить замену клипу ' + g.clip, 'CTR упал с ' + fmt(g.ctr_first, 2) + '% до ' + fmt(g.ctr_last, 2) + '%, досмотры ' + fmt(vpOf(g), 1) + '%. Аудитория его уже видела.', 'Заменить клипом с похожей подачей, если CTR не вернётся выше 0,4%.');
    });
    var young = a.clips.filter(function (g) { return g.v === 'new'; });
    if (young.length) add('Наблюдать', 'blue', 'Рано судить: ' + young.map(function (g) { return g.clip; }).join(', '),
      young.map(function (g) { return g.clip + ': ' + fmt(g.shows) + ' показов, CTR ' + fmt(ctrOf(g), 2) + '%'; }).join('; ') + '.', 'Оценить, когда наберётся 9 000 показов (около 3 дней).');
    if (leader) add('Держать', 'green', 'Лидер — клип ' + leader.clip, 'CTR ' + fmt(ctrOf(leader), 2) + '%, досмотры ' + fmt(vpOf(leader), 1) + '%, клик ' + fmt(leader.spent / Math.max(leader.clicks, 1)) + ' ₽.', 'Не трогать. Можно на 3 дня поднять ставку до 170 ₽ и проверить, вырастут ли показы.');
    if (a.limit && a.running.length && a.avg7 < a.limit * 0.85) {
      var perClip = a.avg7 / a.running.length, more = Math.max(1, Math.round((a.limit - a.avg7) / Math.max(perClip, 1)));
      add('На этой неделе', 'amber', 'Бюджет недорасходуется', 'За последние 7 дней клипы тратили ' + fmt(Math.round(a.avg7)) + ' ₽ в день из ' + fmt(a.limit) + ' ₽. Один клип берёт около ' + fmt(Math.round(perClip / 10) * 10) + ' ₽ в день, упор идёт в ставку.',
        'Добавить ' + more + ' ' + plural(more, 'клип', 'клипа', 'клипов') + ' или поднять ставку лидеру.');
    }
    if (a.running.length) {
      var lOn = a.leads.filter(isOn), lN = sum(a.leads, 'leads'), lSpent = sum(a.leads, 'spent');
      if (lOn.length) add('Заявки', 'green', 'Лид-форма работает', 'За период: ' + fmt(sum(lOn, 'leads')) + ' заявок при расходе ' + fmt(sum(lOn, 'spent')) + ' ₽.', 'Держать и сравнивать с клипами по обращениям в сообщество.');
      else if (lN) add('Предложение', 'amber', 'Вернуть лид-форму', 'За период лид-формы дали ' + fmt(lN) + ' заявок по ' + fmt(lSpent / lN) + ' ₽, сейчас все на паузе. Охват клипов не переводится в заявки.', 'Перезапустить на 1 000 ₽ в день параллельно с клипами и через неделю сравнить цену заявки.');
      else add('Предложение', 'amber', 'Добавить кампанию на заявки', 'Заявок из рекламы за период не было: клипы дают только охват.', 'Запустить параллельно кампанию на сообщения в сообщество или лид-форму.');
    }
    add('Правило', 'gray', 'Решать по клипу через 3 дня', 'За 3 дня клип набирает около 9 000 показов, этого хватает для решения.', 'Оставлять: CTR от 0,45% и досмотры от 20%. Заменять: CTR ниже 0,30% или досмотры ниже 12%.');
    add('Учёт', 'gray', 'Сверять рост сообщества вручную', 'Охват и вступления в группу по клипам кабинет не отдаёт.', 'Раз в неделю записывать число подписчиков и сообщений в сообществе' + (c.community ? ' (' + c.community + ')' : '') + '.');
    $('recs').innerHTML = recs.map(function (r) {
      return '<div class="card smm-rec"><div class="smm-rec-top">' + tag(r.tag, r.cls) + '<h3>' + esc(r.title) + '</h3></div><p>' + esc(r.why) + '</p><p class="act">' + esc(r.act) + '</p></div>';
    }).join('');
  }

  // ---- кабинеты (если их несколько) ----
  function show(key) {
    var c = cabs.filter(function (x) { return x.key === key; })[0] || cabs[0];
    current = c.key;
    [].forEach.call(document.querySelectorAll('#tabs button'), function (t) { var on = t.dataset.key === c.key; t.classList.toggle('active', on); t.setAttribute('aria-selected', on ? 'true' : 'false'); t.tabIndex = on ? 0 : -1; });
    var a = analyse(c);
    $('alert').hidden = !c.error;
    $('alert').textContent = c.error ? (c.stale ? 'Свежие данные по кабинету «' + c.title + '» получить не удалось, показаны данные от ' + (c.updated ? dmy(c.updated.slice(0, 10)) + ' ' + c.updated.slice(11) : 'прошлого отчёта') + '. ' : '') + 'Причина: ' + c.error : '';
    renderBrief(c, a); renderPlans(c, a); renderKpis(c, a); renderClips(c, a); renderLeads(c, a); renderChart(c, a); renderRecs(c, a);
    try { localStorage.setItem('report-tab', c.key); } catch (e) {}
  }
  $('tabs').hidden = cabs.length < 2;
  $('tabs').innerHTML = cabs.map(function (c) {
    var a = analyse(c);
    return '<button type="button" role="tab" data-key="' + esc(c.key) + '" aria-controls="view">' + esc(c.title) + ' · ' + (a.running.length ? 'идёт' : 'не идёт') + '</button>';
  }).join('');
  [].forEach.call(document.querySelectorAll('#tabs button'), function (t) {
    t.addEventListener('click', function () { show(t.dataset.key); try { history.replaceState(null, '', '#' + t.dataset.key); } catch (e) {} });
    t.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      var keys = cabs.map(function (c) { return c.key; }), i = keys.indexOf(current);
      var next = keys[(i + (e.key === 'ArrowRight' ? 1 : keys.length - 1)) % keys.length];
      show(next); document.querySelector('#tabs button[data-key="' + next + '"]').focus();
    });
  });
  var initial = (location.hash || '').slice(1);
  if (!cabs.some(function (c) { return c.key === initial; })) { try { initial = localStorage.getItem('report-tab') || ''; } catch (e) { initial = ''; } }
  show(initial || cabs[0].key);
})();
</script>
@endverbatim
@endsection
