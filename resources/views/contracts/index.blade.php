@extends('layouts.app')
{{-- «Договоры 1С» колл-центра (26.11.2026): поиск договора сразу во всех базах 1С через CRM «БлагоДар» — номер, фамилия,
     адрес или телефон. Поиск — по кнопке или Enter (каждый поиск — сеансы во всех базах 1С), опрос раз в 2 с не дольше
     2 минут. Сканов нет: в них паспорта, их открывает отдел документов. Данные заказчиков не храним. --}}

@push('styles')
<style>
  .kc-c{display:flex;flex-direction:column;gap:16px;max-width:1100px}
  .kc-c-form{display:flex;gap:10px;flex-wrap:wrap}
  .kc-c-form input{flex:1 1 320px;height:44px;border:1px solid var(--line);border-radius:12px;background:var(--panel-2);color:var(--ink);padding:0 14px;font:inherit;font-size:15px}
  .kc-c-form input:focus{outline:2px solid var(--accent-soft);border-color:var(--accent)}
  .kc-c-form .nd-btn{height:44px;padding:0 22px}
  .kc-c-hint{color:var(--muted);font-size:12.5px;margin-top:10px;line-height:1.45}
  .kc-c-state{display:flex;align-items:center;gap:10px;flex-wrap:wrap;color:var(--muted);font-size:13px}
  .kc-c-state b{color:var(--ink)}
  .kc-c-spin{width:14px;height:14px;border:2px solid var(--line);border-top-color:var(--accent);border-radius:50%;animation:kcspin .8s linear infinite}
  @keyframes kcspin{to{transform:rotate(360deg)}}
  .kc-c-err{color:var(--red);font-weight:600}
  .kc-c-base{display:flex;align-items:baseline;gap:8px;margin:4px 0 8px;font-size:15px;font-weight:800}
  .kc-c-base small{font-size:12px;font-weight:600;color:var(--muted)}
  .kc-c-list{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(100%,330px),1fr));gap:12px}
  .kc-c-card{padding:14px 16px;display:flex;flex-direction:column;gap:6px}
  .kc-c-name{font-weight:800;font-size:15px;line-height:1.3}
  .kc-c-meta{display:flex;flex-wrap:wrap;gap:4px 12px;font-size:12.5px;color:var(--muted)}
  .kc-c-meta b{color:var(--ink);font-weight:700}
  .kc-c-row{font-size:13px;line-height:1.4}
  .kc-c-row span{color:var(--muted)}
  .kc-c-row .kc-c-guess{color:var(--amber)}
  .kc-c-note{font-size:12.5px;color:var(--muted);background:var(--panel-2);border-radius:9px;padding:6px 9px;white-space:pre-wrap;word-break:break-word}
  .kc-c-scan{font-size:12px;color:var(--muted)}
  .kc-c-scan.kc-has{color:var(--accent)}
  .kc-c-misc{font-size:13px;color:var(--muted);line-height:1.6}
  .kc-c-misc b{color:var(--ink)}
</style>
@endpush

@section('content')
<div class="kc-c">
  <div class="nd-panel nd-panel-pad">
    @if($enabled)
      <form class="kc-c-form" id="kcForm" autocomplete="off">
        <input type="search" id="kcQ" maxlength="200" placeholder="Номер договора, фамилия, адрес или телефон" aria-label="Что искать" autofocus>
        <button class="nd-btn nd-primary" type="submit" id="kcGo">Найти</button>
      </form>
      <div class="kc-c-hint">Ищет сразу во всех базах 1С (ИП): обычно 3–10 секунд, по телефону — до 15. У договора видно базу, сумму, заказчика и кто замерял.
        Сканов здесь нет — в них паспорта заказчиков, скан открывает отдел документов.</div>
    @else
      <div class="nd-sec-h" style="margin:0 0 6px">Поиск договоров ещё не подключён</div>
      <div class="kc-c-hint" style="margin:0">Нужна учётка CRM «БлагоДар» с ролью «Сайт колл-центра». Логин и пароль передаёт владелец CRM — их вносят в настройки сервера.</div>
    @endif
  </div>
  <div class="kc-c-state" id="kcState" hidden></div>
  <div id="kcOut"></div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const form = document.getElementById('kcForm');
  if (!form) return;
  const q = document.getElementById('kcQ'), go = document.getElementById('kcGo'), stateEl = document.getElementById('kcState'), out = document.getElementById('kcOut');
  const START = @json(route('contracts.start')), STATUS = @json(route('contracts.status', ['id' => '__ID__']));
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const POLL_MS = 2000, MAX_MS = 120000;
  let run = 0;   // номер текущего поиска: новый поиск гасит опрос старого

  const esc = (v) => String(v ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const day = (v) => { const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(v || ''); return m ? m[3] + '.' + m[2] + '.' + m[1] : ''; };
  const rub = (v) => { const n = parseFloat(v); return Number.isFinite(n) ? Math.round(n).toLocaleString('ru-RU') + ' ₽' : ''; };
  const plural = (n, a, b, c) => { const m10 = n % 10, m100 = n % 100; return m10 === 1 && m100 !== 11 ? a : (m10 >= 2 && m10 <= 4 && (m100 < 12 || m100 > 14) ? b : c); };
  const names = (list) => list.map((b) => esc(b.label || b.key)).join(', ');

  const setState = (html, spin) => { stateEl.hidden = !html; stateEl.innerHTML = (spin ? '<span class="kc-c-spin" aria-hidden="true"></span>' : '') + (html || ''); };

  const measurer = (list) => {
    if (!list || !list.length) return '<div class="kc-c-row"><span>Замерщик в 1С не записан</span></div>';
    return list.map((m) => {
      const via = m.via === 'customer' ? ' <span class="kc-c-guess">— по замеру этого заказчика, к договору не привязан</span>'
        : (m.via === 'crm' ? ' <span>— по замеру в CRM</span>' : '');
      return '<div class="kc-c-row"><span>Замерщик:</span> <b>' + esc(m.name) + '</b>' + (day(m.date) ? ', замер ' + day(m.date) : '')
        + (m.visits > 1 ? ', выездов ' + m.visits : '') + via + '</div>';
    }).join('');
  };

  const card = (c) => {
    const meta = [
      c.number ? '№ <b>' + esc(c.number) + '</b>' : '',
      day(c.date) ? 'от <b>' + day(c.date) + '</b>' : '',
      rub(c.amount) ? '<b>' + rub(c.amount) + '</b>' : '',
      c.organization ? esc(c.organization) : '',
    ].filter(Boolean);
    return '<div class="nd-panel kc-c-card">'
      + '<div class="kc-c-name">' + esc(c.name || ('Договор ' + (c.number || ''))) + '</div>'
      + (meta.length ? '<div class="kc-c-meta">' + meta.map((x) => '<span>' + x + '</span>').join('') + '</div>' : '')
      + (c.customer ? '<div class="kc-c-row"><span>Заказчик:</span> <b>' + esc(c.customer) + '</b>' + (c.isOrg ? ' <span>(организация)</span>' : '') + '</div>' : '')
      + measurer(c.measurers)
      + (c.note ? '<div class="kc-c-note">' + esc(c.note) + '</div>' : '')
      + (Number(c.fileCount) > 0 ? '<div class="kc-c-scan kc-has">Скан договора в 1С есть — открывает отдел документов</div>' : '<div class="kc-c-scan">Скана договора в 1С нет</div>')
      + '</div>';
  };

  const render = (d, finalNote) => {
    const results = d.results || [];
    const found = results.filter((r) => r.ok && (r.contracts || []).length);
    const empty = results.filter((r) => r.ok && !(r.contracts || []).length);
    const failed = results.filter((r) => !r.ok);
    let html = '';
    found.forEach((r) => {
      const n = r.contracts.length;
      html += '<div class="kc-c-base">' + esc(r.label || r.base) + ' <small>' + n + ' ' + plural(n, 'договор', 'договора', 'договоров') + '</small></div>'
        + (r.error ? '<div class="kc-c-misc">' + esc(r.error) + '</div>' : '')
        + '<div class="kc-c-list">' + r.contracts.map(card).join('') + '</div><div style="height:10px"></div>';
    });
    const misc = [];
    if (failed.length) misc.push(failed.map((r) => '<b>' + esc(r.label || r.base) + '</b> не ответила' + (r.error ? ': ' + esc(r.error) : '')).join('<br>'));
    if (empty.length) misc.push('Нет в базах: ' + names(empty));
    if (finalNote) misc.push(finalNote);
    if (html === '' && d.state === 'done' && !failed.length) html = '<div class="nd-panel nd-panel-pad kc-c-misc">Ни в одной базе 1С ничего не нашлось. Проверьте номер или попробуйте фамилию, адрес, телефон.</div>';
    out.innerHTML = html + (misc.length ? '<div class="nd-panel nd-panel-pad kc-c-misc">' + misc.join('<br>') + '</div>' : '');
  };

  const fail = (msg) => { setState('<span class="kc-c-err">' + esc(msg) + '</span>'); go.disabled = false; };

  const poll = async (id, me, startedAt) => {
    if (me !== run) return;
    let d;
    try {
      const r = await fetch(STATUS.replace('__ID__', encodeURIComponent(id)), { headers: { Accept: 'application/json' } });
      d = await r.json().catch(() => ({}));
      if (!r.ok) { if (me === run) fail(d.message || 'Не удалось получить ответ — попробуйте ещё раз.'); return; }
    } catch (e) {
      if (me === run) fail('Нет связи с сервером — попробуйте ещё раз.');
      return;
    }
    if (me !== run) return;
    const total = (d.bases || []).length, waiting = d.waiting || [];
    const late = Date.now() - startedAt >= MAX_MS;
    if (d.state === 'agentSilent') { render(d); fail('1С сейчас не отвечает — попробуйте позже.'); return; }
    if (d.state === 'done') { render(d); setState(total ? 'Ответили все базы: ' + total : ''); go.disabled = false; return; }
    if (d.state === 'stale' || late) {
      render(d, waiting.length ? 'Не ответили: ' + names(waiting) : '');
      setState('Поиск остановлен' + (waiting.length ? ' — не ответили ' + waiting.length + ' из ' + total : ''));
      go.disabled = false;
      return;
    }
    render(d);
    setState(d.state === 'queued' ? 'Поиск ждёт агента 1С…' : 'Ищу… ответили <b>' + (total - waiting.length) + '</b> из ' + total
      + (waiting.length ? ' · ищут: ' + names(waiting) : ''), true);
    setTimeout(() => poll(id, me, startedAt), POLL_MS);
  };

  const search = async () => {
    const text = q.value.trim();
    if (!text) { q.focus(); return; }
    const me = ++run;
    go.disabled = true;
    out.innerHTML = '';
    setState('Начинаю поиск…', true);
    try {
      const r = await fetch(START, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf }, body: JSON.stringify({ query: text }) });
      const d = await r.json().catch(() => ({}));
      if (me !== run) return;
      if (!r.ok || !d.id) { fail(d.message || (d.errors && Object.values(d.errors)[0]?.[0]) || 'Поиск не начался — попробуйте ещё раз.'); return; }
      poll(d.id, me, Date.now());
    } catch (e) {
      if (me === run) fail('Нет связи с сервером — попробуйте ещё раз.');
    }
  };

  form.addEventListener('submit', (e) => { e.preventDefault(); search(); });
  // из карточки сделки — кнопка «Договор в 1С»: телефон приходит после # (в адрес запроса и журналы сервера он не попадает)
  const h = new URLSearchParams(location.hash.slice(1)).get('q');
  if (h) { q.value = h; history.replaceState(null, '', location.pathname); search(); }
})();
</script>
@endpush
