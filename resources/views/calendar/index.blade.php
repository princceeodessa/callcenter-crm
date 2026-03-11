@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">
  <style>
    .ccrm-cal-wrap { height: calc(100vh - 120px); }
    .ccrm-cal-left { width: 360px; }
    @media (max-width: 992px) {
      .ccrm-cal-wrap { flex-direction: column; height: auto; }
      .ccrm-cal-left { width: 100%; }
      #ccrmCalendar { height: 75vh; }
    }
    #ccrmCalendar { height: 100%; }
    .fc .fc-toolbar-title { font-size: 1.1rem; }
    .ccrm-event-main { font-weight: 600; line-height: 1.2; }
    .ccrm-event-address { opacity: .95; line-height: 1.2; white-space: normal; word-break: break-word; font-size: .84em; margin-top: 2px; }
    .fc-timegrid-event .fc-event-main, .fc-daygrid-event .fc-event-main { white-space: normal; }
  </style>
@endpush

@section('content')
@php
  $isMeasurer = in_array(auth()->user()?->role, ['measurer'], true);
@endphp

<div class="d-flex gap-3 ccrm-cal-wrap">
  <div class="card shadow-sm ccrm-cal-left flex-shrink-0">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div class="fw-semibold">РљР°Р»РµРЅРґР°СЂСЊ Р·Р°РјРµСЂРѕРІ</div>
      <button class="btn btn-sm btn-primary" id="btnNew">+ Р—Р°РїРёСЃСЊ</button>
    </div>
    <div class="card-body">
      <div class="mb-2">
        <label class="form-label small">Р¤РёР»СЊС‚СЂ РїРѕ Р·Р°РјРµСЂС‰РёРєСѓ</label>
        <select id="filterUser" class="form-select form-select-sm">
          @unless($isMeasurer)
            <option value="0">Р’СЃРµ</option>
          @endunless
          @foreach($measurers as $u)
            <option value="{{ $u->id }}" @selected((int)$selectedUserId === (int)$u->id)>{{ $u->name }}@unless($isMeasurer) ({{ $u->role }})@endunless</option>
          @endforeach
        </select>
        <div class="form-text">
          @if($isMeasurer)
            Р—Р°РјРµСЂС‰РёРє РІРёРґРёС‚ СЃРІРѕРё Р·Р°РїРёСЃРё Рё СЃРІРѕР±РѕРґРЅС‹Рµ Р·Р°РјРµСЂС‹ Р±РµР· РЅР°Р·РЅР°С‡РµРЅРЅРѕРіРѕ РёСЃРїРѕР»РЅРёС‚РµР»СЏ.
          @else
            Р—Р°РјРµСЂС‰РёРє РїРѕ СѓРјРѕР»С‡Р°РЅРёСЋ РІРёРґРёС‚ СЃРІРѕР№ РєР°Р»РµРЅРґР°СЂСЊ.
          @endif
        </div>
      </div>

      <div class="border rounded p-2 bg-light">
        <div class="fw-semibold small mb-1">РЎС‚Р°С‚СѓСЃС‹</div>
        <div class="d-flex flex-column gap-1 small">
          @foreach($statuses as $k => $v)
            <div><span class="badge text-bg-secondary">{{ $k }}</span> вЂ” {{ $v }}</div>
          @endforeach
        </div>
      </div>

      <div class="mt-3 text-muted small">
        РџРѕРґСЃРєР°Р·РєР°: РєР»РёРєРЅРё РїРѕ РґРЅСЋ РёР»Рё РІСЂРµРјРµРЅРё РІ РєР°Р»РµРЅРґР°СЂРµ вЂ” С„РѕСЂРјР° РѕС‚РєСЂРѕРµС‚СЃСЏ СЃСЂР°Р·Сѓ РЅР° РІС‹Р±СЂР°РЅРЅРѕРј СЃР»РѕС‚Рµ.
      </div>
    </div>
  </div>

  <div class="card shadow-sm flex-grow-1">
    <div class="card-body p-2">
      <div id="ccrmCalendar"></div>
    </div>
  </div>
</div>

<div class="modal fade" id="measurementModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">Р—Р°РїРёСЃСЊ РЅР° Р·Р°РјРµСЂ</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <input type="hidden" id="m_id">

          <div class="col-12 col-md-6">
            <label class="form-label">Р”Р°С‚Р° Рё РІСЂРµРјСЏ</label>
            <input type="datetime-local" id="m_scheduled_at" class="form-control">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Р”Р»РёС‚РµР»СЊРЅРѕСЃС‚СЊ (РјРёРЅ)</label>
            <input type="number" id="m_duration" class="form-control" min="5" max="600" value="60">
          </div>

          <div class="col-12">
            <label class="form-label">РђРґСЂРµСЃ</label>
            <input type="text" id="m_address" class="form-control" placeholder="РђРґСЂРµСЃ" maxlength="500">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">РўРµР»РµС„РѕРЅ РєР»РёРµРЅС‚Р°</label>
            <input type="text" id="m_phone" class="form-control" placeholder="+7..." maxlength="32">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">РЎС‚Р°С‚СѓСЃ</label>
            <select id="m_status" class="form-select">
              @foreach($statuses as $k => $v)
                <option value="{{ $k }}">{{ $v }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">РљС‚Рѕ РІР·СЏР» (Р·Р°РјРµСЂС‰РёРє)</label>
            <select id="m_assigned" class="form-select" {{ $isMeasurer ? 'disabled' : '' }}>
              <option value="">вЂ”</option>
              @foreach($measurers as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
              @endforeach
            </select>
            @if($isMeasurer)
              <div class="form-text">Р—Р°РјРµСЂС‰РёРє РјРѕР¶РµС‚ В«РІР·СЏС‚СЊВ» Р·Р°РїРёСЃСЊ РЅР° СЃРµР±СЏ РєРЅРѕРїРєРѕР№ РЅРёР¶Рµ.</div>
            @endif
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">РљРѕРјРјРµРЅС‚Р°СЂРёР№ РєРѕР»Р»-С†РµРЅС‚СЂР°</label>
            <textarea id="m_callcenter_comment" class="form-control" rows="2" {{ $isMeasurer ? 'disabled' : '' }}></textarea>
          </div>

          <div class="col-12">
            <label class="form-label">РљРѕРјРјРµРЅС‚Р°СЂРёР№ Р·Р°РјРµСЂС‰РёРєР°</label>
            <textarea id="m_measurer_comment" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div id="modalError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        @if($isMeasurer)
          <button type="button" class="btn btn-outline-success" id="btnClaim">Р’Р·СЏС‚СЊ РЅР° СЃРµР±СЏ</button>
        @endif
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Р—Р°РєСЂС‹С‚СЊ</button>
        <button type="button" class="btn btn-primary" id="btnSave">РЎРѕС…СЂР°РЅРёС‚СЊ</button>
      </div>
    </div>
  </div>
</div>
@endsection

@push('scripts')
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/locales-all.global.min.js"></script>
  <script>
  (() => {
    const csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
    const eventsUrl = @json(route('calendar.events'));
    const storeUrl = @json(route('calendar.store'));
    const updateUrlTpl = @json(route('calendar.update', ['measurement' => 0]));
    const claimUrlTpl = @json(route('calendar.claim', ['measurement' => 0]));
    const isMeasurer = @json($isMeasurer);

    const modalEl = document.getElementById('measurementModal');
    const modal = new bootstrap.Modal(modalEl);
    const errEl = document.getElementById('modalError');

    const fUser = document.getElementById('filterUser');
    const btnNew = document.getElementById('btnNew');
    const btnSave = document.getElementById('btnSave');
    const btnClaim = document.getElementById('btnClaim');

    const fld = (id) => document.getElementById(id);

    function toLocalInputValue(d) {
      const x = (d instanceof Date) ? new Date(d.getTime()) : new Date(d);
      x.setMinutes(x.getMinutes() - x.getTimezoneOffset());
      return x.toISOString().slice(0, 16);
    }

    function showError(msg) {
      errEl.textContent = msg || 'РћС€РёР±РєР°';
      errEl.classList.remove('d-none');
    }
    function clearError() {
      errEl.classList.add('d-none');
      errEl.textContent = '';
    }

    function setForm(data) {
      fld('m_id').value = data.id || '';
      fld('m_scheduled_at').value = data.scheduled_at || '';
      fld('m_duration').value = data.duration_minutes || 60;
      fld('m_address').value = data.address || '';
      fld('m_phone').value = data.phone || '';
      fld('m_status').value = data.status || 'planned';
      fld('m_assigned').value = data.assigned_user_id || '';
      fld('m_callcenter_comment').value = data.callcenter_comment || '';
      fld('m_measurer_comment').value = data.measurer_comment || '';
    }

    function getForm() {
      return {
        scheduled_at: fld('m_scheduled_at').value,
        duration_minutes: fld('m_duration').value,
        address: fld('m_address').value,
        phone: fld('m_phone').value,
        status: fld('m_status').value,
        assigned_user_id: fld('m_assigned').value,
        callcenter_comment: fld('m_callcenter_comment').value,
        measurer_comment: fld('m_measurer_comment').value,
      };
    }

    function openCreate(dt) {
      clearError();
      document.getElementById('modalTitle').textContent = 'РќРѕРІР°СЏ Р·Р°РїРёСЃСЊ РЅР° Р·Р°РјРµСЂ';
      const local = dt ? toLocalInputValue(dt) : toLocalInputValue(new Date());
      setForm({ id: '', scheduled_at: local, duration_minutes: 60, status: 'planned', assigned_user_id: '' });
      if (btnClaim) btnClaim.classList.add('d-none');
      modal.show();
    }

    function openEdit(event) {
      clearError();
      document.getElementById('modalTitle').textContent = 'Р—Р°РјРµСЂ #' + event.id;
      const p = event.extendedProps || {};
      setForm({
        id: event.id,
        scheduled_at: event.start ? toLocalInputValue(event.start) : '',
        duration_minutes: p.duration_minutes || 60,
        address: p.address || '',
        phone: p.phone || '',
        status: p.status || 'planned',
        assigned_user_id: p.assigned_user_id || '',
        callcenter_comment: p.callcenter_comment || '',
        measurer_comment: p.measurer_comment || '',
      });
      if (btnClaim) btnClaim.classList.toggle('d-none', !(isMeasurer && !p.assigned_user_id));
      modal.show();
    }

    const calendar = new FullCalendar.Calendar(document.getElementById('ccrmCalendar'), {
      locale: 'ru',
      firstDay: 1,
      timeZone: 'local',
      buttonText: { today: 'РЎРµРіРѕРґРЅСЏ', month: 'РњРµСЃСЏС†', week: 'РќРµРґРµР»СЏ', day: 'Р”РµРЅСЊ' },
      allDayText: 'Р’РµСЃСЊ РґРµРЅСЊ',
      initialView: 'timeGridWeek',
      nowIndicator: true,
      selectable: true,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay'
      },
      events: (info, success, failure) => {
        const url = new URL(eventsUrl, window.location.origin);
        url.searchParams.set('start', info.startStr);
        url.searchParams.set('end', info.endStr);
        const uid = Number(fUser.value || 0);
        if (uid > 0) url.searchParams.set('user_id', String(uid));
        fetch(url.toString(), { headers: { 'Accept': 'application/json' } })
          .then(r => r.json())
          .then(success)
          .catch(failure);
      },
      dateClick: (info) => openCreate(info.date),
      eventContent: (arg) => {
        const p = arg.event.extendedProps || {};
        const wrap = document.createElement('div');
        const main = document.createElement('div');
        main.className = 'ccrm-event-main';
        const mainParts = [];
        if (p.assigned_user_name) mainParts.push(p.assigned_user_name + ':');
        if (p.phone) mainParts.push(p.phone);
        main.textContent = mainParts.join(' ') || arg.event.title || ('Р—Р°РјРµСЂ #' + arg.event.id);
        wrap.appendChild(main);
        if (p.address) {
          const addr = document.createElement('div');
          addr.className = 'ccrm-event-address';
          addr.textContent = p.address;
          wrap.appendChild(addr);
        }
        wrap.title = [main.textContent, p.address || '', p.status_label || ''].filter(Boolean).join(' вЂ” ');
        return { domNodes: [wrap] };
      },
      eventDidMount: (info) => {
        const p = info.event.extendedProps || {};
        const mainParts = [];
        if (p.assigned_user_name) mainParts.push(p.assigned_user_name + ':');
        if (p.phone) mainParts.push(p.phone);
        info.el.title = [mainParts.join(' ') || info.event.title, p.address || '', p.status_label || ''].filter(Boolean).join(' вЂ” ');
      },
      eventClick: (info) => {
        info.jsEvent.preventDefault();
        openEdit(info.event);
      }
    });
    calendar.render();

    fUser.addEventListener('change', () => {
      const url = new URL(window.location.href);
      const v = Number(fUser.value || 0);
      if (v > 0) url.searchParams.set('u', String(v));
      else url.searchParams.delete('u');
      window.history.replaceState({}, '', url.toString());
      calendar.refetchEvents();
    });

    btnNew.addEventListener('click', () => openCreate());

    btnSave.addEventListener('click', async () => {
      clearError();
      const id = fld('m_id').value;
      const data = getForm();
      if (!data.scheduled_at || !data.address) {
        showError('Р—Р°РїРѕР»РЅРё РґР°С‚Сѓ/РІСЂРµРјСЏ Рё Р°РґСЂРµСЃ');
        return;
      }

      const isUpdate = !!id;
      const url = isUpdate ? updateUrlTpl.replace('/0', '/' + id) : storeUrl;
      const method = isUpdate ? 'PATCH' : 'POST';

      try {
        const r = await fetch(url, {
          method,
          headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrf,
          },
          body: JSON.stringify(data)
        });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || !j.ok) throw new Error(j.message || j.error || 'save_failed');
        modal.hide();
        calendar.refetchEvents();
      } catch (e) {
        showError(e.message);
      }
    });

    if (btnClaim) {
      btnClaim.addEventListener('click', async () => {
        clearError();
        const id = fld('m_id').value;
        if (!id) return;
        const url = claimUrlTpl.replace('/0', '/' + id);
        try {
          const r = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          });
          const j = await r.json().catch(() => ({}));
          if (!r.ok || !j.ok) throw new Error(j.error || 'claim_failed');
          calendar.refetchEvents();
          modal.hide();
        } catch (e) {
          showError(e.message);
        }
      });
    }
  })();
  </script>
@endpush
