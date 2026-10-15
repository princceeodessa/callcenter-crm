@extends('layouts.owner')

@php
    $titles = ['ceilings' => 'Потолки · БлагоДар', 'sneakers' => 'Кроссовки · Шаговый'];
    $back = $business === 'ceilings' ? route('owner.ceilings') : route('owner.dashboard');
@endphp

@section('title', 'SMM-контент')
@section('subtitle')SMM-контент · {{ $titles[$business] }} · реклама клипов VK: что идёт, какие клипы тянут, что заменить@endsection

@section('nav')
    <a href="{{ $back }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg> Сводка</a>
    <a href="#top" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="2" y="4" width="20" height="16" rx="3"/><path d="M10 8l5 4-5 4V8z"/></svg> Клипы</a>
@endsection

@section('content')
@verbatim
<style>
/* SMM-контент: стили отчёта SMM-специалиста (blagodar-clips), в цветах CRM и только внутри .smm-report */
.smm-report { --surface: var(--card); --lead: #94a3b8; --other: #c9b98f;
  --good: #15803d; --good-bg: rgba(22,163,74,.14); --warn: #b45309; --warn-bg: rgba(245,158,11,.18); --bad: #dc2626; --bad-bg: rgba(220,38,38,.13);
  --idle: var(--muted); --idle-bg: rgba(127,127,127,.14); display: grid; gap: 1.6rem; }
.smm-report #view { display: grid; gap: 1.6rem; }
.smm-report h2 { font-size: 1.1rem; font-weight: 700; margin: 0; letter-spacing: -.01em; }
.smm-report h3 { margin: 0; font-size: 1rem; font-weight: 700; }
.smm-report p { margin: 0; }
.smm-report .muted { color: var(--muted); }
.smm-report .small { font-size: .82rem; }
.smm-report .num { font-variant-numeric: tabular-nums; }
.smm-report .section { display: grid; gap: .7rem; }
.smm-report .section-head { display: grid; gap: .15rem; }
.smm-report .alert { border-radius: 12px; padding: .7rem .9rem; background: var(--bad-bg); color: var(--bad); font-size: .9rem; margin: 0; border: 0; }
.smm-report .tabs { display: flex; flex-wrap: wrap; gap: 4px; border-bottom: 1px solid var(--line); }
.smm-report .tab { font: inherit; font-weight: 600; background: transparent; color: var(--muted); border: 0; border-bottom: 3px solid transparent; padding: .5rem .8rem; }
.smm-report .tab[aria-selected="true"] { color: inherit; border-bottom-color: var(--accent); }
.smm-report .tab .tab-note { font-weight: 400; font-size: .8rem; margin-left: 6px; }
.smm-report .brief { background: var(--surface); border: 1px solid var(--line); border-radius: 16px; box-shadow: var(--shadow-sm); padding: 1rem 1.15rem; display: grid; gap: .55rem; }
.smm-report .brief-head { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: baseline; }
.smm-report .brief ul { margin: 0; padding-left: 1.2rem; display: grid; gap: .3rem; max-width: 85ch; }
.smm-report .kpis { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 160px), 1fr)); gap: .7rem; }
.smm-report .kpi { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: .7rem .9rem; display: grid; gap: 2px; align-content: start; }
.smm-report .kpi b { font-size: 1.35rem; font-weight: 800; font-variant-numeric: tabular-nums; letter-spacing: -.02em; }
.smm-report .kpi span { font-size: .8rem; color: var(--muted); }
.smm-report .empty-note { background: var(--surface); border: 1px dashed var(--line); border-radius: 12px; padding: .8rem 1rem; color: var(--muted); }
.smm-report .table-box { overflow-x: auto; background: var(--surface); border: 1px solid var(--line); border-radius: 14px; }
.smm-report table { border-collapse: collapse; width: 100%; min-width: 820px; font-variant-numeric: tabular-nums; }
.smm-report table.narrow { min-width: 640px; }
.smm-report th, .smm-report td { text-align: right; padding: .5rem .75rem; border-bottom: 1px solid var(--line); white-space: nowrap; font-size: .86rem; vertical-align: top; }
.smm-report th { font-size: .7rem; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: .04em; }
.smm-report th:first-child, .smm-report td:first-child, .smm-report th:nth-child(2), .smm-report td:nth-child(2) { text-align: left; }
.smm-report td.wrap-cell { white-space: normal; text-align: left; min-width: 200px; }
.smm-report tr:last-child td { border-bottom: 0; }
.smm-report tr.dim td { color: var(--muted); }
.smm-report td.empty { text-align: left; color: var(--muted); }
.smm-report .sub { display: block; font-size: .75rem; color: var(--muted); white-space: normal; max-width: 240px; }
.smm-report .pill { display: inline-block; font-size: .75rem; font-weight: 700; padding: 2px 9px; border-radius: 999px; white-space: nowrap; }
.smm-report .p-good { color: var(--good); background: var(--good-bg); }
.smm-report .p-warn { color: var(--warn); background: var(--warn-bg); }
.smm-report .p-bad { color: var(--bad); background: var(--bad-bg); }
.smm-report .p-idle { color: var(--idle); background: var(--idle-bg); }
.smm-report .trend-down { color: var(--bad); }
.smm-report .trend-up { color: var(--good); }
.smm-report .chart-box { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: .8rem; overflow-x: auto; }
.smm-report .chart-box svg { width: 100%; min-width: 560px; height: auto; display: block; }
.smm-report .legend { display: flex; flex-wrap: wrap; gap: 1rem; font-size: .8rem; color: var(--muted); margin-top: .35rem; }
.smm-report .sw { display: inline-block; width: 10px; height: 10px; border-radius: 2px; margin-right: 6px; vertical-align: -1px; }
.smm-report .recs { display: grid; gap: .6rem; }
.smm-report .rec { background: var(--surface); border: 1px solid var(--line); border-radius: 14px; padding: .8rem 1rem; display: grid; gap: .3rem; }
.smm-report .rec-top { display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: baseline; }
.smm-report .rec p { max-width: 85ch; }
.smm-report .rec .act { font-weight: 600; }
.smm-report .brief, .smm-report .kpi, .smm-report .table-box, .smm-report .chart-box, .smm-report .rec { border-radius: var(--r-sm); }
.smm-report .kpi { overflow: visible; }
.smm-note { font-size: 12px; color: var(--muted); }
.smm-report a { color: var(--accent); font-weight: 600; text-decoration: none; }
.smm-report a:hover { text-decoration: underline; }
@media (max-width: 575.98px) { .smm-report th, .smm-report td { padding: .4rem .5rem; font-size: .8rem; } }
</style>
@endverbatim

<div class="filters" id="top">
    @include('owner._biz_tabs', ['biz' => $business, 'tab' => 'smm'])
    <div class="seg">
        @foreach($periods as $d => $label)
            <a href="{{ route('owner.smm', ['business' => $business, 'days' => $d]) }}" class="{{ $days === $d ? 'active' : '' }}">{{ $label }}</a>
        @endforeach
    </div>
    <div class="spacer"></div>
    <span class="smm-note">цифры кабинета VK Рекламы обновляются каждые 2 часа</span>
</div>

<div class="smm-report">
    <p class="smm-note" id="period" style="margin-top:-10px"></p>
    <script id="report-data" type="application/json">{!! json_encode($report, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

@verbatim
  <nav class="tabs" role="tablist" aria-label="Бизнес" id="tabs"></nav>

  <div id="view">
    <div class="alert" id="alert" hidden></div>

    <section class="brief" aria-labelledby="briefTitle">
      <div class="brief-head"><span class="pill" id="briefPill"></span><h2 id="briefTitle"></h2></div>
      <ul id="briefList"></ul>
    </section>

    <section class="section" aria-labelledby="s-plans">
      <div class="section-head"><h2 id="s-plans">Кампании</h2><p class="muted small">Действующие кампании и созданные или тратившие за период.</p></div>
      <div class="table-box"><table class="narrow" id="planTable">
        <thead><tr><th>Кампания</th><th>Статус</th><th>Тип</th><th>Лимит в день</th><th>Групп</th><th>Что мешает</th></tr></thead>
        <tbody></tbody>
      </table></div>
    </section>

    <section class="section" aria-labelledby="s-sum">
      <div class="section-head"><h2 id="s-sum">Итоги по клипам за период</h2><p class="muted small" id="sumNote"></p></div>
      <div class="kpis" id="kpis"></div>
    </section>

    <section class="section" aria-labelledby="s-clips">
      <div class="section-head">
        <h2 id="s-clips">Каждый клип</h2>
        <p class="muted small">Динамика CTR: первые 7 дней показов против последних 7. Оценка ставится, когда у клипа набралось от 9 000 показов.</p>
      </div>
      <div class="table-box"><table id="clipTable">
        <thead><tr><th>Клип</th><th>Статус</th><th>Показы</th><th>CTR</th><th>Динамика CTR</th><th>Досмотры 100%</th><th>₽ за досмотр</th><th>₽ за клик</th><th>Расход</th></tr></thead>
        <tbody></tbody>
      </table></div>
    </section>

    <section class="section" aria-labelledby="s-leads">
      <div class="section-head"><h2 id="s-leads">Заявки (лид-формы)</h2></div>
      <div class="table-box"><table id="leadTable">
        <thead><tr><th>Группа</th><th>Статус</th><th>Показы</th><th>Клики</th><th>CTR</th><th>Заявки</th><th>₽ за заявку</th><th>Расход</th><th>Дни показа</th></tr></thead>
        <tbody></tbody>
      </table></div>
    </section>

    <section class="section" aria-labelledby="s-chart">
      <div class="section-head"><h2 id="s-chart">Расход по дням</h2><p class="muted small" id="chartNote"></p></div>
      <div class="chart-box">
        <svg id="chart" viewBox="0 0 660 230" role="img" aria-label="Расход по дням"></svg>
        <div class="legend">
          <span><i class="sw" style="background:var(--accent)"></i>клипы</span>
          <span><i class="sw" style="background:var(--lead)"></i>лид-формы</span>
          <span><i class="sw" style="background:var(--other)"></i>прочее</span>
          <span id="limitLegend"><i class="sw" style="background:var(--bad)"></i>дневной лимит клипов</span>
        </div>
      </div>
    </section>

    <section class="section" aria-labelledby="s-recs">
      <div class="section-head">
        <h2 id="s-recs">Что улучшить</h2>
        <p class="muted small">Считается из свежих цифр. Изменения в кабинете вносятся только после согласования.</p>
      </div>
      <div class="recs" id="recs"></div>
    </section>

  </div>

@endverbatim
</div>

@verbatim
<script>
(function () {
  var $ = function (id) { return document.getElementById(id); };
  var fmt = function (n, d) { return Number(n || 0).toLocaleString('ru-RU', { minimumFractionDigits: d || 0, maximumFractionDigits: d || 0 }); };
  var dm = function (s) { return s ? s.slice(8, 10) + '.' + s.slice(5, 7) : ''; };
  var dmy = function (s) { return s ? dm(s) + '.' + s.slice(0, 4) : ''; };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"]/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]; }); };
  var plural = function (n, one, few, many) { var m10 = n % 10, m100 = n % 100; return (m10 === 1 && m100 !== 11) ? one : (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14)) ? few : many; };
  var sum = function (arr, k) { return arr.reduce(function (s, g) { return s + (g[k] || 0); }, 0); };

  var R, raw = $('report-data').textContent.trim();
  try { R = JSON.parse(raw); } catch (e) {
    R = { cabinets: [], templateError: 'Данные отчёта не загрузились.' };
  }
  var cabs = R.cabinets || [];
  $('period').textContent = R.date_from
    ? 'Данные кабинетов VK Рекламы за ' + dmy(R.date_from) + '–' + dmy(R.date_to) + ' (последние ' + R.period_days + ' дн., сегодня — неполный день). Сформировано ' + dmy(R.generated.slice(0, 10)) + ' в ' + R.generated.slice(11) + '.'
    : '';
  if (!cabs.length) { $('alert').hidden = false; $('alert').textContent = R.templateError || 'В отчёте нет данных.'; return; }

  var ISSUE = {
    NO_MONEY: 'на балансе нет денег', AD_PLAN_STOPPED: 'кампания на паузе', NO_ALLOWED_BANNERS: 'нет одобренных креативов',
    STOPPED: 'группа на паузе', ARCHIVED: 'в архиве', AD_PLAN_ARCHIVED: 'кампания в архиве', ON_MODERATION: 'на модерации', BANNED: 'отклонено модерацией'
  };
  var REASON = { NO_ORGN_BANNER: 'не заполнено «О рекламодателе» (ОГРН или ИНН)' };
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
    lead: ['Лидер', 'p-good'], fatigue: ['Выгорает', 'p-warn'], mid: ['Средний', 'p-idle'], weak: ['Заменить', 'p-bad'],
    'new': ['Рано судить', 'p-idle'], moder: ['На модерации', 'p-warn'], banned: ['Отклонён', 'p-bad'], paused: ['Готов, на паузе', 'p-idle'], off: ['Остановлен', 'p-idle']
  };
  var ORDER = { lead: 0, fatigue: 0, mid: 0, weak: 0, 'new': 0, moder: 1, banned: 1, paused: 2, off: 3 };

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
    var cs = { spent: sum(clips, 'spent'), shows: sum(clips, 'shows'), clicks: sum(clips, 'clicks'), v100: sum(clips, 'v100') };
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
      pill = ['Идёт', 'p-good'];
      title = 'Реклама идёт: ' + a.running.length + ' ' + plural(a.running.length, 'клип', 'клипа', 'клипов') + ' в показах';
      items.push('За последние 7 дней — в среднем ' + fmt(Math.round(a.avg7)) + ' ₽ в день' + (a.limit ? ' из ' + fmt(a.limit) + ' ₽ лимита' : '') + '.');
      if (a.cs.shows) items.push('За ' + R.period_days + ' дн.: ' + fmt(a.cs.spent) + ' ₽, ' + fmt(a.cs.shows) + ' показов, CTR ' + fmt(100 * a.cs.clicks / a.cs.shows, 2) + '%, до конца досмотрели ' + fmt(100 * a.cs.v100 / a.cs.shows, 1) + '%.');
      var attention = [];
      var weak = a.clips.filter(function (g) { return g.v === 'weak'; }), fat = a.clips.filter(function (g) { return g.v === 'fatigue'; });
      if (weak.length) attention.push('заменить ' + weak.map(function (g) { return g.clip; }).join(', '));
      if (fat.length) attention.push('выгорает ' + fat.map(function (g) { return g.clip; }).join(', '));
      if (moder.length) attention.push(moder.length + ' ' + plural(moder.length, 'новый клип', 'новых клипа', 'новых клипов') + ' на модерации');
      if (banned.length) attention.push(banned.length + ' ' + plural(banned.length, 'клип отклонён', 'клипа отклонены', 'клипов отклонены') + ' модерацией');
      if (attention.length) { pill = ['Идёт, есть вопросы', 'p-warn']; items.push('Требует внимания: ' + attention.join('; ') + '.'); }
    } else {
      pill = ['Не идёт', 'p-bad'];
      title = 'Реклама сейчас не идёт';
      var why = [];
      if (a.mainPlan && a.mainPlan.status !== 'active') why.push('кампания «' + a.mainPlan.name + '» на паузе');
      if (noMoney) why.push('на балансе нет денег');
      if (banned.length) why.push(banned.length + ' ' + plural(banned.length, 'креатив отклонён', 'креатива отклонены', 'креативов отклонены') + ': ' + reasonText(Array.from(new Set([].concat.apply([], banned.map(function (g) { return g.reasons || []; }))))));
      if (moder.length) why.push(moder.length + ' на модерации');
      if (!a.clips.length) why.push('клиповых кампаний за период не было');
      items.push('Причины: ' + (why.join('; ') || 'не определены') + '.');
      if (a.clips.length) items.push('Подготовлено ' + a.clips.length + ' ' + plural(a.clips.length, 'клип', 'клипа', 'клипов') + (a.mainPlan ? ' в кампании ' + a.mainPlan.id : '') + '.');
    }
    var leadN = sum(a.leads, 'leads'), leadSpent = sum(a.leads, 'spent');
    if (a.leads.length) items.push('Заявки за ' + R.period_days + ' дн.: ' + fmt(leadN) + (leadN ? ' по ' + fmt(leadSpent / leadN) + ' ₽' : '') + '; лид-форма ' + (a.leads.some(isOn) ? 'работает' : 'на паузе') + '.');
    else items.push('Лид-форм за период не было: реклама даёт только охват.');
    $('briefPill').className = 'pill ' + pill[1]; $('briefPill').textContent = pill[0];
    $('briefTitle').textContent = title;
    $('briefList').innerHTML = items.map(function (t) { return '<li>' + esc(t) + '</li>'; }).join('');
  }

  function renderPlans(c, a) {
    var tb = document.querySelector('#planTable tbody');
    if (!a.plans.length) { tb.innerHTML = '<tr><td class="empty" colspan="6">Кампаний за период нет.</td></tr>'; return; }
    tb.innerHTML = a.plans.map(function (p) {
      var gs = a.groups.filter(function (g) { return g.plan_id === p.id && g.status !== 'deleted'; });
      var probs = {};
      gs.forEach(function (g) {
        (g.issues || []).forEach(function (code) { if (ISSUE[code] && code !== 'STOPPED') probs[ISSUE[code]] = 1; });
        if (g.moderation === 'banned') probs['отклонено модерацией: ' + reasonText(g.reasons)] = 1;
      });
      if (p.status === 'active') delete probs[ISSUE.AD_PLAN_STOPPED];
      var probText = Object.keys(probs).join('; ');
      return '<tr' + (p.status === 'active' ? '' : ' class="dim"') + '><td>' + esc(p.name) + '<span class="sub">№ ' + p.id + ' · создана ' + dmy((p.created || '').slice(0, 10)) + '</span></td>' +
        '<td><span class="pill ' + (p.status === 'active' ? 'p-good' : 'p-idle') + '">' + (p.status === 'active' ? 'Активна' : 'На паузе') + '</span></td>' +
        '<td>' + esc(OBJ[p.objective] || p.objective) + '</td>' +
        '<td>' + (p.day_limit ? fmt(p.day_limit) + ' ₽' : '—') + '</td>' +
        '<td>' + gs.length + '</td>' +
        '<td class="wrap-cell">' + (probText ? esc(probText) : '<span class="muted">—</span>') + '</td></tr>';
    }).join('');
  }

  function renderKpis(c, a) {
    var cs = a.cs;
    $('sumNote').textContent = 'Клиповых групп за период: ' + a.clips.length + ', в показах сейчас ' + a.running.length + '.';
    if (!cs.shows) { $('kpis').innerHTML = '<div class="empty-note">За ' + R.period_days + ' дн. показов клипов не было.</div>'; return; }
    var kp = [
      [fmt(cs.spent) + ' ₽', 'расход на клипы'],
      [fmt(cs.shows), 'показов'],
      [fmt(100 * cs.clicks / cs.shows, 2) + '%', 'CTR, ' + fmt(cs.clicks) + ' ' + plural(cs.clicks, 'клик', 'клика', 'кликов')],
      [fmt(100 * cs.v100 / cs.shows, 1) + '%', 'досмотрели до конца (' + fmt(cs.v100) + ')'],
      [cs.v100 ? fmt(cs.spent / cs.v100, 2) + ' ₽' : '—', 'за один полный досмотр'],
      [fmt(Math.round(a.avg7 / 10) * 10) + ' ₽', 'в день за последние 7 дней' + (a.limit ? ' при лимите ' + fmt(a.limit) + ' ₽' : '')]
    ];
    $('kpis').innerHTML = kp.map(function (k) { return '<div class="kpi"><b>' + k[0] + '</b><span>' + k[1] + '</span></div>'; }).join('');
  }

  function renderClips(c, a) {
    var tb = document.querySelector('#clipTable tbody');
    var rows = a.clips.slice().sort(function (x, y) { return (ORDER[x.v] - ORDER[y.v]) || (y.spent - x.spent) || (y.id - x.id); });
    if (!rows.length) { tb.innerHTML = '<tr><td class="empty" colspan="9">Клипов в рекламе за период не было.</td></tr>'; return; }
    tb.innerHTML = rows.map(function (g) {
      var dash = '<span class="muted">—</span>', has = g.shows > 0;
      var trend = dash;
      if (g.ctr_first != null && g.ctr_last != null) {
        var cls = g.ctr_last < g.ctr_first - 0.03 ? 'trend-down' : (g.ctr_last > g.ctr_first + 0.03 ? 'trend-up' : '');
        trend = '<span class="' + cls + '">' + fmt(g.ctr_first, 2) + '% → ' + fmt(g.ctr_last, 2) + '%</span>';
      }
      var label = g.clip ? '<a href="https://vk.ru/clip' + esc(g.owner) + '_' + esc(g.clip) + '" target="_blank" rel="noopener">' + esc(g.clip) + '</a>' : esc(g.name);
      var sub = g.v === 'banned' && g.reasons && g.reasons.length ? '<span class="sub">' + esc(reasonText(g.reasons)) + '</span>' : '';
      var dim = ['off', 'paused'].indexOf(g.v) >= 0;
      return '<tr' + (dim ? ' class="dim"' : '') + '><td>' + label + '</td>' +
        '<td><span class="pill ' + VD[g.v][1] + '">' + VD[g.v][0] + '</span>' + sub + '</td>' +
        '<td>' + (has ? fmt(g.shows) : dash) + '</td><td>' + (has ? fmt(ctrOf(g), 2) + '%' : dash) + '</td><td>' + trend + '</td>' +
        '<td>' + (has ? fmt(vpOf(g), 1) + '%' : dash) + '</td>' +
        '<td>' + (g.v100 ? fmt(g.spent / g.v100, 2) + ' ₽' : dash) + '</td>' +
        '<td>' + (g.clicks ? fmt(g.spent / g.clicks) + ' ₽' : dash) + '</td>' +
        '<td>' + (has ? fmt(g.spent) + ' ₽' : dash) + '</td></tr>';
    }).join('');
  }

  function renderLeads(c, a) {
    var tb = document.querySelector('#leadTable tbody');
    var rows = a.leads.slice().sort(function (x, y) { return y.spent - x.spent; });
    if (!rows.length) { tb.innerHTML = '<tr><td class="empty" colspan="9">За период лид-формы не показывались.</td></tr>'; return; }
    tb.innerHTML = rows.map(function (g) {
      return '<tr' + (isOn(g) ? '' : ' class="dim"') + '><td>' + esc(g.name) + '</td>' +
        '<td><span class="pill ' + (isOn(g) ? 'p-good' : 'p-idle') + '">' + (isOn(g) ? 'Активна' : 'На паузе') + '</span></td>' +
        '<td>' + fmt(g.shows) + '</td><td>' + fmt(g.clicks) + '</td><td>' + fmt(ctrOf(g), 2) + '%</td>' +
        '<td>' + fmt(g.leads) + '</td><td>' + (g.leads ? fmt(g.spent / g.leads) + ' ₽' : '—') + '</td>' +
        '<td>' + fmt(g.spent) + ' ₽</td><td>' + (g.first_date ? dm(g.first_date) + '–' + dm(g.last_date) : '—') + '</td></tr>';
    }).join('');
  }

  function renderChart(c, a) {
    var svg = $('chart'), NS = 'http://www.w3.org/2000/svg';
    svg.innerHTML = '';
    var X0 = 52, X1 = 648, Y0 = 196, Y1 = 14, days = a.days;
    var maxDay = days.reduce(function (m, d) { return Math.max(m, d.clip + d.lead + d.other); }, 0);
    var MAX = Math.max(1000, Math.ceil(Math.max(maxDay, a.limit) * 1.1 / 1000) * 1000);
    var step = MAX <= 5000 ? 1000 : Math.ceil(MAX / 5000) * 1000;
    var y = function (v) { return Y0 - (v / MAX) * (Y0 - Y1); };
    var el = function (tag, attrs, text) { var e = document.createElementNS(NS, tag); for (var k in attrs) e.setAttribute(k, attrs[k]); if (text) e.textContent = text; svg.appendChild(e); return e; };
    for (var v = 0; v <= MAX; v += step) {
      el('line', { x1: X0, x2: X1, y1: y(v), y2: y(v), stroke: 'var(--line)', 'stroke-width': 1 });
      el('text', { x: X0 - 8, y: y(v) + 4, 'text-anchor': 'end', 'font-size': 11, fill: 'var(--muted)' }, fmt(v));
    }
    if (days.length) {
      var slot = (X1 - X0) / days.length, bw = Math.max(2, slot * 0.66);
      days.forEach(function (d, i) {
        var x = X0 + i * slot + (slot - bw) / 2, base = 0;
        [['clip', 'var(--accent)'], ['lead', 'var(--lead)'], ['other', 'var(--other)']].forEach(function (p) {
          var val = d[p[0]] || 0; if (val <= 0) return;
          el('rect', { x: x, y: y(base + val), width: bw, height: y(base) - y(base + val), fill: p[1] });
          base += val;
        });
        if (i % 7 === 0 || i === days.length - 1) el('text', { x: x + bw / 2, y: Y0 + 18, 'text-anchor': 'middle', 'font-size': 11, fill: 'var(--muted)' }, dm(d.d));
      });
    }
    $('limitLegend').hidden = !a.limit;
    if (a.limit) {
      el('line', { x1: X0, x2: X1, y1: y(a.limit), y2: y(a.limit), stroke: 'var(--bad)', 'stroke-width': 1.5, 'stroke-dasharray': '5 4' });
      el('text', { x: X1, y: y(a.limit) - 6, 'text-anchor': 'end', 'font-size': 11, fill: 'var(--bad)' }, 'лимит клипов ' + fmt(a.limit) + ' ₽');
    }
    $('chartNote').textContent = maxDay === 0 ? 'За ' + R.period_days + ' дн. расходов не было.'
      : (a.limit ? 'Клипы за последние 7 дней тратили в среднем ' + fmt(Math.round(a.avg7)) + ' ₽ в день из ' + fmt(a.limit) + ' ₽ (' + fmt(100 * a.avg7 / a.limit) + '% лимита).' : 'Активных клиповых кампаний сейчас нет.');
  }

  function renderRecs(c, a) {
    var recs = [];
    var add = function (tag, cls, title, why, act) { recs.push({ tag: tag, cls: cls, title: title, why: why, act: act }); };
    var banned = a.clips.filter(function (g) { return g.v === 'banned'; });
    var orgn = banned.filter(function (g) { return (g.reasons || []).indexOf('NO_ORGN_BANNER') >= 0; });
    var otherBanned = banned.filter(function (g) { return orgn.indexOf(g) < 0; });
    var noMoney = a.groups.some(function (g) { return (g.issues || []).indexOf('NO_MONEY') >= 0; });
    if (orgn.length) add('Сейчас', 'p-bad', 'Заполнить «О рекламодателе»',
      'Модерация отклонила ' + orgn.length + ' ' + plural(orgn.length, 'креатив', 'креатива', 'креативов') + ' (' + orgn.map(function (g) { return g.clip; }).join(', ') + '): по закону о рекламе нужны данные рекламодателя.',
      'В кабинете VK Рекламы заполнить поле «О рекламодателе» (ООО: название, адрес, ОГРН; ИП: ФИО и ОГРНИП) и отправить креативы на повторную проверку.');
    otherBanned.forEach(function (g) { add('Сейчас', 'p-bad', 'Клип ' + g.clip + ' отклонён', 'Причина: ' + (reasonText(g.reasons) || 'не указана') + '.', 'Исправить по замечанию модерации или заменить клип.'); });
    if (!a.running.length && a.clips.length) {
      if (noMoney) add('Сейчас', 'p-bad', 'Пополнить баланс', 'Кабинет сообщает, что на балансе нет денег: без этого показы не начнутся.', 'Пополнить баланс кабинета VK Рекламы.');
      if (a.mainPlan && a.mainPlan.status !== 'active') add('Перед запуском', 'p-warn', 'Включить кампанию ' + a.mainPlan.id, 'Кампания «' + a.mainPlan.name + '» на паузе.', 'Включить после пополнения баланса и одобрения креативов.');
    }
    if (!a.running.length && !a.clips.length) add('Предложение', 'p-warn', 'Запустить продвижение клипов', 'За ' + R.period_days + ' дн. клиповой рекламы не было.', 'Подобрать клипы и согласовать запуск с SMM-специалистом.');
    var moder = a.clips.filter(function (g) { return g.v === 'moder'; });
    if (moder.length) add('Наблюдать', 'p-idle', 'На модерации: ' + moder.map(function (g) { return g.clip; }).join(', '), 'Обычно проверка занимает от нескольких минут до нескольких часов.', 'После одобрения включить новые клипы' + (a.running.length ? ' и отключить те, которые они заменяют.' : '.'));
    var leader = a.clips.filter(function (g) { return g.v === 'lead'; }).sort(function (x, y) { return ctrOf(y) - ctrOf(x); })[0];
    a.clips.filter(function (g) { return g.v === 'weak'; }).forEach(function (g) {
      add('Сейчас', 'p-bad', 'Заменить клип ' + g.clip,
        'CTR ' + fmt(ctrOf(g), 2) + '%, досмотры ' + fmt(vpOf(g), 1) + '%, клик стоит ' + (g.clicks ? fmt(g.spent / g.clicks) : '—') + ' ₽' + (leader ? '; у лидера ' + leader.clip + ': CTR ' + fmt(ctrOf(leader), 2) + '%, досмотры ' + fmt(vpOf(leader), 1) + '%.' : '.'),
        'Отключить группу ' + g.id + ' и поставить на её место новый клип.');
    });
    a.clips.filter(function (g) { return g.v === 'fatigue'; }).forEach(function (g) {
      add('На этой неделе', 'p-warn', 'Готовить замену клипу ' + g.clip, 'CTR упал с ' + fmt(g.ctr_first, 2) + '% до ' + fmt(g.ctr_last, 2) + '%, досмотры ' + fmt(vpOf(g), 1) + '%. Аудитория его уже видела.', 'Заменить клипом с похожей подачей, если CTR не вернётся выше 0,4%.');
    });
    var young = a.clips.filter(function (g) { return g.v === 'new'; });
    if (young.length) add('Наблюдать', 'p-idle', 'Рано судить: ' + young.map(function (g) { return g.clip; }).join(', '),
      young.map(function (g) { return g.clip + ': ' + fmt(g.shows) + ' показов, CTR ' + fmt(ctrOf(g), 2) + '%'; }).join('; ') + '.', 'Оценить, когда наберётся 9 000 показов (около 3 дней).');
    if (leader) add('Держать', 'p-good', 'Лидер — клип ' + leader.clip, 'CTR ' + fmt(ctrOf(leader), 2) + '%, досмотры ' + fmt(vpOf(leader), 1) + '%, клик ' + fmt(leader.spent / Math.max(leader.clicks, 1)) + ' ₽.', 'Не трогать. Можно на 3 дня поднять ставку до 170 ₽ и проверить, вырастут ли показы.');
    if (a.limit && a.running.length && a.avg7 < a.limit * 0.85) {
      var perClip = a.avg7 / a.running.length;
      add('На этой неделе', 'p-warn', 'Бюджет недорасходуется', 'За последние 7 дней клипы тратили ' + fmt(Math.round(a.avg7)) + ' ₽ в день из ' + fmt(a.limit) + ' ₽. Один клип берёт около ' + fmt(Math.round(perClip / 10) * 10) + ' ₽ в день, упор идёт в ставку.',
        'Добавить ' + Math.max(1, Math.round((a.limit - a.avg7) / Math.max(perClip, 1))) + ' ' + plural(Math.max(1, Math.round((a.limit - a.avg7) / Math.max(perClip, 1))), 'клип', 'клипа', 'клипов') + ' или поднять ставку лидеру.');
    }
    if (a.running.length) {
      var lOn = a.leads.filter(isOn), lN = sum(a.leads, 'leads'), lSpent = sum(a.leads, 'spent');
      if (lOn.length) add('Заявки', 'p-good', 'Лид-форма работает', 'За период: ' + fmt(sum(lOn, 'leads')) + ' заявок при расходе ' + fmt(sum(lOn, 'spent')) + ' ₽.', 'Держать и сравнивать с клипами по обращениям в сообщество.');
      else if (lN) add('Предложение', 'p-warn', 'Вернуть лид-форму', 'За период лид-формы дали ' + fmt(lN) + ' заявок по ' + fmt(lSpent / lN) + ' ₽, сейчас все на паузе. Охват клипов не переводится в заявки.', 'Перезапустить на 1 000 ₽ в день параллельно с клипами и через неделю сравнить цену заявки.');
      else add('Предложение', 'p-warn', 'Добавить кампанию на заявки', 'Заявок из рекламы за период не было: клипы дают только охват.', 'Запустить параллельно кампанию на сообщения в сообщество или лид-форму.');
    }
    add('Правило', 'p-good', 'Решать по клипу через 3 дня', 'За 3 дня клип набирает около 9 000 показов, этого хватает для решения.', 'Оставлять: CTR от 0,45% и досмотры от 20%. Заменять: CTR ниже 0,30% или досмотры ниже 12%.');
    add('Учёт', 'p-idle', 'Сверять рост сообщества вручную', 'Охват и вступления в группу по клипам кабинет не отдаёт.', 'Раз в неделю записывать число подписчиков и сообщений в сообществе' + (c.community ? ' (' + c.community + ')' : '') + '.');
    $('recs').innerHTML = recs.map(function (r) {
      return '<div class="rec"><div class="rec-top"><span class="pill ' + r.cls + '">' + r.tag + '</span><h3>' + esc(r.title) + '</h3></div><p>' + esc(r.why) + '</p><p class="act">' + esc(r.act) + '</p></div>';
    }).join('');
  }

  // ---- Tabs ----
  function show(key) {
    var c = cabs.filter(function (x) { return x.key === key; })[0] || cabs[0];
    current = c.key;
    [].forEach.call(document.querySelectorAll('.tab'), function (t) { t.setAttribute('aria-selected', t.dataset.key === c.key ? 'true' : 'false'); t.tabIndex = t.dataset.key === c.key ? 0 : -1; });
    var a = analyse(c);
    $('alert').hidden = !c.error;
    $('alert').textContent = c.error ? (c.stale ? 'Свежие данные по кабинету «' + c.title + '» получить не удалось, показаны данные от ' + (c.updated ? dmy(c.updated.slice(0, 10)) + ' ' + c.updated.slice(11) : 'прошлого отчёта') + '. ' : '') + 'Причина: ' + c.error : '';
    renderBrief(c, a); renderPlans(c, a); renderKpis(c, a); renderClips(c, a); renderLeads(c, a); renderChart(c, a); renderRecs(c, a);
    try { localStorage.setItem('report-tab', c.key); } catch (e) {}
  }
  $('tabs').hidden = cabs.length < 2;
  $('tabs').innerHTML = cabs.map(function (c) {
    var a = analyse(c);
    var note = a.running.length ? 'идёт' : 'не идёт';
    return '<button type="button" class="tab" role="tab" data-key="' + esc(c.key) + '" aria-controls="view">' + esc(c.title) + '<span class="tab-note">' + esc(c.brand !== c.title ? c.brand + ' · ' : '') + note + '</span></button>';
  }).join('');
  [].forEach.call(document.querySelectorAll('.tab'), function (t) {
    t.addEventListener('click', function () { show(t.dataset.key); try { history.replaceState(null, '', '#' + t.dataset.key); } catch (e) {} });
    t.addEventListener('keydown', function (e) {
      if (e.key !== 'ArrowRight' && e.key !== 'ArrowLeft') return;
      var keys = cabs.map(function (c) { return c.key; }), i = keys.indexOf(current);
      var next = keys[(i + (e.key === 'ArrowRight' ? 1 : keys.length - 1)) % keys.length];
      show(next); document.querySelector('.tab[data-key="' + next + '"]').focus();
    });
  });
  var initial = (location.hash || '').slice(1);
  if (!cabs.some(function (c) { return c.key === initial; })) { try { initial = localStorage.getItem('report-tab') || ''; } catch (e) { initial = ''; } }
  show(initial || cabs[0].key);
})();
</script>
@endverbatim
@endsection
