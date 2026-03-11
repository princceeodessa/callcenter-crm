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
    .ccrm-event-address {
      opacity: .95;
      line-height: 1.2;
      white-space: normal;
      word-break: break-word;
      font-size: .84em;
      margin-top: 2px;
    }
    .fc-timegrid-event .fc-event-main,
    .fc-daygrid-event .fc-event-main {
      white-space: normal;
    }
  </style>
@endpush

@section('content')
@php
  $isMeasurer = auth()->user()?->role === 'measurer';
  $measurerScope = $measurerScope ?? 'available';
@endphp

<div class="d-flex gap-3 ccrm-cal-wrap">
  <div class="card shadow-sm ccrm-cal-left flex-shrink-0">
    <div class="card-header d-flex align-items-center justify-content-between">
      <div class="fw-semibold">Календарь замеров</div>
      <button class="btn btn-sm btn-primary" id="btnNew">+ Запись</button>
    </div>
    <div class="card-body">
      <div class="mb-2">
        <label class="form-label small">Фильтр по замерщику</label>
        <select id="filterUser" class="form-select form-select-sm">
          @unless($isMeasurer)
            <option value="0">Все</option>
          @endunless
          @foreach($measurers as $u)
            <option value="{{ $u->id }}" @selected((int) $selectedUserId === (int) $u->id)>
              {{ $u->name }}@unless($isMeasurer) ({{ $u->role }})@endunless
            </option>
          @endforeach
        </select>
        <div class="form-text">
          @if($isMeasurer)
            Замерщик видит свои записи и свободные замеры без назначенного исполнителя.
          @else
            Можно отфильтровать календарь по конкретному замерщику.
          @endif
        </div>
      </div>

      @if($isMeasurer)
        <div class="mb-2">
          <label class="form-label small">Показ записей</label>
          <div class="btn-group w-100" role="group" aria-label="scope">
            <button type="button" class="btn btn-sm {{ $measurerScope === 'available' ? 'btn-primary' : 'btn-outline-primary' }} js-scope" data-scope="available">
              Мои + свободные
            </button>
            <button type="button" class="btn btn-sm {{ $measurerScope === 'mine' ? 'btn-primary' : 'btn-outline-primary' }} js-scope" data-scope="mine">
              Только мои
            </button>
          </div>
        </div>
      @endif

      <div class="border rounded p-2 bg-light">
        <div class="fw-semibold small mb-1">Статусы</div>
        <div class="d-flex flex-column gap-1 small">
          @foreach($statuses as $k => $v)
            <div><span class="badge text-bg-secondary">{{ $k }}</span> - {{ $v }}</div>
          @endforeach
        </div>
      </div>

      <div class="mt-3 text-muted small">
        Подсказка: кликни по дню или времени в календаре, чтобы быстро создать запись на выбранный слот.
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
        <h5 class="modal-title" id="modalTitle">Запись на замер</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="row g-2">
          <input type="hidden" id="m_id">

          <div class="col-12 col-md-6">
            <label class="form-label">Дата и время</label>
            <input type="datetime-local" id="m_scheduled_at" class="form-control">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Длительность (мин)</label>
            <input type="number" id="m_duration" class="form-control" min="5" max="600" value="60">
          </div>

          <div class="col-12">
            <label class="form-label">Адрес</label>
            <input type="text" id="m_address" class="form-control" placeholder="Адрес" maxlength="500">
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Телефон клиента</label>
            <input type="text" id="m_phone" class="form-control" placeholder="+7..." maxlength="32">
          </div>
          <div class="col-12 col-md-6">
            <label class="form-label">Статус</label>
            <select id="m_status" class="form-select">
              @foreach($statuses as $k => $v)
                <option value="{{ $k }}">{{ $v }}</option>
              @endforeach
            </select>
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Кто взял (замерщик)</label>
            <select id="m_assigned" class="form-select" {{ $isMeasurer ? 'disabled' : '' }}>
              <option value="">-</option>
              @foreach($measurers as $u)
                <option value="{{ $u->id }}">{{ $u->name }}</option>
              @endforeach
            </select>
            @if($isMeasurer)
              <div class="form-text">Свободную запись можно взять на себя кнопкой ниже.</div>
            @endif
          </div>

          <div class="col-12 col-md-6">
            <label class="form-label">Комментарий колл-центра</label>
            <textarea id="m_callcenter_comment" class="form-control" rows="2" {{ $isMeasurer ? 'disabled' : '' }}></textarea>
          </div>

          <div class="col-12">
            <label class="form-label">Комментарий замерщика</label>
            <textarea id="m_measurer_comment" class="form-control" rows="3"></textarea>
          </div>
        </div>
        <div id="modalError" class="alert alert-danger mt-3 d-none"></div>
      </div>
      <div class="modal-footer">
        @if($isMeasurer)
          <button type="button" class="btn btn-outline-success" id="btnClaim">Взять на себя</button>
          <button type="button" class="btn btn-outline-danger" id="btnRelease">Отказаться</button>
        @endif
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
        <button type="button" class="btn btn-primary" id="btnSave">Сохранить</button>
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
    const releaseUrlTpl = @json(route('calendar.release', ['measurement' => 0]));
    const isMeasurer = @json($isMeasurer);
    const currentUserId = @json((int) auth()->id());

    const modalEl = document.getElementById('measurementModal');
    const modal = new bootstrap.Modal(modalEl);
    const errEl = document.getElementById('modalError');

    const fUser = document.getElementById('filterUser');
    const btnNew = document.getElementById('btnNew');
    const btnSave = document.getElementById('btnSave');
    const btnClaim = document.getElementById('btnClaim');
    const btnRelease = document.getElementById('btnRelease');
    const scopeButtons = Array.from(document.querySelectorAll('.js-scope'));

    const fld = (id) => document.getElementById(id);

    function toLocalInputValue(d) {
      const x = (d instanceof Date) ? new Date(d.getTime()) : new Date(d);
      x.setMinutes(x.getMinutes() - x.getTimezoneOffset());
      return x.toISOString().slice(0, 16);
    }

    function showError(msg) {
      errEl.textContent = msg || 'Ошибка';
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
      document.getElementById('modalTitle').textContent = 'Новая запись на замер';
      const local = dt ? toLocalInputValue(dt) : toLocalInputValue(new Date());
      setForm({ id: '', scheduled_at: local, duration_minutes: 60, status: 'planned', assigned_user_id: '' });
      if (btnClaim) btnClaim.classList.add('d-none');
      if (btnRelease) btnRelease.classList.add('d-none');
      modal.show();
    }

    function openEdit(event) {
      clearError();
      document.getElementById('modalTitle').textContent = 'Замер #' + event.id;
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
      const canClaim = isMeasurer && !p.assigned_user_id;
      const canRelease = isMeasurer
        && Number(p.assigned_user_id || 0) === currentUserId
        && !['concluded', 'not_concluded', 'cancelled'].includes(String(p.status || ''));
      if (btnClaim) {
        btnClaim.classList.toggle('d-none', !canClaim);
      }
      if (btnRelease) {
        btnRelease.classList.toggle('d-none', !canRelease);
      }
      modal.show();
    }

    function getScope() {
      const activeBtn = scopeButtons.find((btn) => btn.classList.contains('btn-primary'));
      return activeBtn ? String(activeBtn.dataset.scope || 'available') : 'available';
    }

    const calendar = new FullCalendar.Calendar(document.getElementById('ccrmCalendar'), {
      locale: 'ru',
      firstDay: 1,
      timeZone: 'local',
      buttonText: { today: 'Сегодня', month: 'Месяц', week: 'Неделя', day: 'День' },
      allDayText: 'Весь день',
      initialView: 'timeGridWeek',
      nowIndicator: true,
      selectable: true,
      slotEventOverlap: false,
      eventMaxStack: 10,
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
        if (isMeasurer) url.searchParams.set('scope', getScope());
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
        main.textContent = mainParts.join(' ') || arg.event.title || ('Замер #' + arg.event.id);
        wrap.appendChild(main);

        if (p.address) {
          const addr = document.createElement('div');
          addr.className = 'ccrm-event-address';
          addr.textContent = p.address;
          wrap.appendChild(addr);
        }

        wrap.title = [main.textContent, p.address || '', p.status_label || ''].filter(Boolean).join(' - ');
        return { domNodes: [wrap] };
      },
      eventDidMount: (info) => {
        const p = info.event.extendedProps || {};
        const mainParts = [];
        if (p.assigned_user_name) mainParts.push(p.assigned_user_name + ':');
        if (p.phone) mainParts.push(p.phone);
        info.el.title = [mainParts.join(' ') || info.event.title, p.address || '', p.status_label || ''].filter(Boolean).join(' - ');
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

    scopeButtons.forEach((btn) => {
      btn.addEventListener('click', () => {
        scopeButtons.forEach((item) => {
          const active = item === btn;
          item.classList.toggle('btn-primary', active);
          item.classList.toggle('btn-outline-primary', !active);
        });

        const url = new URL(window.location.href);
        url.searchParams.set('scope', btn.dataset.scope || 'available');
        window.history.replaceState({}, '', url.toString());
        calendar.refetchEvents();
      });
    });

    btnNew.addEventListener('click', () => openCreate());

    btnSave.addEventListener('click', async () => {
      clearError();
      const id = fld('m_id').value;
      const data = getForm();

      if (!data.scheduled_at || !data.address) {
        showError('Заполни дату/время и адрес');
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

    if (btnRelease) {
      btnRelease.addEventListener('click', async () => {
        clearError();
        const id = fld('m_id').value;
        if (!id) return;

        const url = releaseUrlTpl.replace('/0', '/' + id);
        try {
          const r = await fetch(url, {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf },
          });
          const j = await r.json().catch(() => ({}));
          if (!r.ok || !j.ok) throw new Error(j.message || j.error || 'release_failed');
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
