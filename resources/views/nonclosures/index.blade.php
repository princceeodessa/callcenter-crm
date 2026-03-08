@extends('layouts.app')

@push('styles')
<style>
  .nc-table td, .nc-table th { vertical-align: top; }
  .nc-card { border-radius: 1rem; }
  .nc-muted { color: var(--bs-secondary-color); }
  .nc-compact { line-height: 1.25; }
  .nc-sticky-toolbar { position: sticky; top: 0; z-index: 10; }
  .nc-table-wrapper { overflow-x: auto; }
  .nc-table td { min-width: 120px; }
  .nc-table .col-address { min-width: 220px; }
  .nc-table .col-reason { min-width: 260px; }
  .nc-table .col-comment { min-width: 260px; }
  .nc-table .col-special { min-width: 240px; }
</style>
@endpush

@section('content')
@php
  $statusLabels = [
    'pending' => 'Без статуса',
    'concluded' => 'Заключен',
    'not_concluded' => 'Не заключен',
    'all' => 'Все',
  ];
@endphp

<div class="d-flex flex-column gap-3">
  <div class="card shadow-sm nc-card">
    <div class="card-body d-flex flex-column flex-lg-row justify-content-between gap-3 align-items-lg-center">
      <div>
        <h4 class="mb-1">Незаключёнки</h4>
        <div class="nc-muted">Импорт поддерживает реальные Excel-файлы незаключёнок, а фильтры позволяют отдельно смотреть строки без статуса, заключённые и не заключённые.</div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <button class="btn btn-outline-secondary" data-bs-toggle="modal" data-bs-target="#importModal">
          <i class="bi bi-upload me-1"></i>Импорт из Excel
        </button>
        <button class="btn btn-primary" id="btnNewRow">
          <i class="bi bi-plus-lg me-1"></i>Новая запись
        </button>
      </div>
    </div>
  </div>

  <div class="card shadow-sm nc-card nc-sticky-toolbar">
    <div class="card-body">
      <form class="row g-2 align-items-end" method="GET" action="{{ route('nonclosures.index') }}">
        <div class="col-12 col-lg-3">
          <label class="form-label small">Поиск</label>
          <input type="text" class="form-control" name="q" value="{{ $search }}" placeholder="Адрес, причина, комментарий...">
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label small">Статус</label>
          <select class="form-select" name="status">
            <option value="pending" @selected($statusFilter==='pending')>Без статуса</option>
            <option value="concluded" @selected($statusFilter==='concluded')>Заключен</option>
            <option value="not_concluded" @selected($statusFilter==='not_concluded')>Не заключен</option>
            <option value="all" @selected($statusFilter==='all')>Все</option>
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label small">Замерщик</label>
          <select class="form-select" name="measurer_id">
            <option value="0">Все</option>
            @foreach($measurers as $u)
              <option value="{{ $u->id }}" @selected($measurerId === (int)$u->id)>{{ $u->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-lg-2">
          <label class="form-label small">Ответственный</label>
          <select class="form-select" name="responsible_id">
            <option value="0">Все</option>
            @foreach($responsibles as $u)
              <option value="{{ $u->id }}" @selected($responsibleId === (int)$u->id)>{{ $u->name }}</option>
            @endforeach
          </select>
        </div>
        <div class="col-6 col-lg-3 d-flex gap-2">
          <button class="btn btn-primary flex-grow-1">Применить</button>
          <a class="btn btn-outline-secondary" href="{{ route('nonclosures.index') }}">Сбросить</a>
        </div>
      </form>
    </div>
  </div>

  <div class="card shadow-sm nc-card">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div class="fw-semibold">Записи — {{ $rows->total() }}</div>
        <div class="nc-muted small">Фильтр: {{ $statusLabels[$statusFilter] ?? 'Все' }}</div>
      </div>

      <div class="nc-table-wrapper">
        <table class="table table-hover table-sm align-middle nc-table">
          <thead>
            <tr>
              <th>Дата</th>
              <th class="col-address">Адрес</th>
              <th class="col-reason">Причина незаключения</th>
              <th>Замерщик</th>
              <th>Ответственный</th>
              <th class="col-comment">Комментарий</th>
              <th>Дата повторной встречи</th>
              <th>Статус</th>
              <th class="col-special">Спецпросчет</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            @forelse($rows as $row)
              <tr>
                <td class="nc-compact">{{ optional($row->entry_date)->format('d.m.Y') ?: '—' }}</td>
                <td class="col-address nc-compact">{{ $row->address ?: '—' }}</td>
                <td class="col-reason nc-compact">{{ $row->reason ?: '—' }}</td>
                <td class="nc-compact">{{ $row->display_measurer ?: '—' }}</td>
                <td class="nc-compact">{{ $row->display_responsible ?: '—' }}</td>
                <td class="col-comment nc-compact">{{ $row->comment ?: '—' }}</td>
                <td class="nc-compact">{{ optional($row->follow_up_date)->format('d.m.Y') ?: '—' }}</td>
                <td>
                  @if($row->result_status === 'concluded')
                    <span class="badge text-bg-success">Заключен</span>
                  @elseif($row->result_status === 'not_concluded')
                    <span class="badge text-bg-danger">Не заключен</span>
                  @else
                    <span class="badge text-bg-secondary">Без статуса</span>
                  @endif
                </td>
                <td class="col-special nc-compact">{{ $row->special_calculation ?: '—' }}</td>
                <td class="text-end">
                  @php
                    $editRow = [
                      'id' => $row->id,
                      'entry_date' => optional($row->entry_date)->format('Y-m-d'),
                      'address' => $row->address,
                      'reason' => $row->reason,
                      'measurer_user_id' => $row->measurer_user_id,
                      'measurer_name' => $row->measurer_name,
                      'responsible_user_id' => $row->responsible_user_id,
                      'responsible_name' => $row->responsible_name,
                      'comment' => $row->comment,
                      'follow_up_date' => optional($row->follow_up_date)->format('Y-m-d'),
                      'result_status' => $row->result_status,
                      'special_calculation' => $row->special_calculation,
                    ];
                  @endphp
                  <button
                    type="button"
                    class="btn btn-sm btn-outline-primary btn-edit-row"
                    data-row='{{ json_encode($editRow, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) }}'>
                    Изменить
                  </button>
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="10" class="text-center text-muted py-4">Пока записей нет.</td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>

      <div class="mt-3">
        {{ $rows->links() }}
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="rowModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <form method="POST" id="rowForm" action="{{ route('nonclosures.store') }}">
        @csrf
        <input type="hidden" name="_method" id="rowFormMethod" value="POST">
        <div class="modal-header">
          <h5 class="modal-title" id="rowModalTitle">Новая запись</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-12 col-md-3">
              <label class="form-label">Дата</label>
              <input type="date" class="form-control" name="entry_date" id="f_entry_date" required>
            </div>
            <div class="col-12 col-md-5">
              <label class="form-label">Адрес</label>
              <input type="text" class="form-control" name="address" id="f_address" maxlength="500" required>
            </div>
            <div class="col-12 col-md-4">
              <label class="form-label">Дата повторной встречи</label>
              <input type="date" class="form-control" name="follow_up_date" id="f_follow_up_date">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Причина незаключения</label>
              <textarea class="form-control" name="reason" id="f_reason" rows="4"></textarea>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Комментарий</label>
              <textarea class="form-control" name="comment" id="f_comment" rows="4"></textarea>
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Замерщик</label>
              <select class="form-select" name="measurer_user_id" id="f_measurer_user_id">
                <option value="">—</option>
                @foreach($measurers as $u)
                  <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
              </select>
              <div class="form-text">Если замерщика нет в списке, можно указать имя вручную ниже.</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Имя замерщика (вручную)</label>
              <input type="text" class="form-control" name="measurer_name" id="f_measurer_name" maxlength="120">
            </div>

            <div class="col-12 col-md-6">
              <label class="form-label">Ответственный</label>
              <select class="form-select" name="responsible_user_id" id="f_responsible_user_id">
                <option value="">—</option>
                @foreach($responsibles as $u)
                  <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
              </select>
              <div class="form-text">Например, кто звонил клиенту из колл-центра.</div>
            </div>
            <div class="col-12 col-md-6">
              <label class="form-label">Ответственный (вручную)</label>
              <input type="text" class="form-control" name="responsible_name" id="f_responsible_name" maxlength="120">
            </div>

            <div class="col-12 col-md-4">
              <label class="form-label">Итоговый статус</label>
              <select class="form-select" name="result_status" id="f_result_status">
                <option value="">Без статуса</option>
                <option value="concluded">Заключен</option>
                <option value="not_concluded">Не заключен</option>
              </select>
            </div>
            <div class="col-12 col-md-8">
              <label class="form-label">Спецпросчет</label>
              <textarea class="form-control" name="special_calculation" id="f_special_calculation" rows="2"></textarea>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
          <button type="submit" class="btn btn-primary">Сохранить</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="importModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <form method="POST" action="{{ route('nonclosures.import') }}" enctype="multipart/form-data">
        @csrf
        <div class="modal-header">
          <h5 class="modal-title">Импорт из Excel</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-muted">Загрузи Excel-файл в формате вашей рабочей таблицы. Импортируются строки с пустым статусом, а также с пометками «заключен» и «не заключен».</p>
          <input type="file" class="form-control" name="file" accept=".xlsx" required>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Закрыть</button>
          <button type="submit" class="btn btn-primary">Импортировать</button>
        </div>
      </form>
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const rowModalEl = document.getElementById('rowModal');
  const rowModal = new bootstrap.Modal(rowModalEl);
  const form = document.getElementById('rowForm');
  const methodEl = document.getElementById('rowFormMethod');
  const titleEl = document.getElementById('rowModalTitle');
  const btnNew = document.getElementById('btnNewRow');

  const setVal = (id, val) => { document.getElementById(id).value = val ?? ''; };

  function openNew() {
    titleEl.textContent = 'Новая запись';
    form.action = @json(route('nonclosures.store'));
    methodEl.value = 'POST';
    setVal('f_entry_date', new Date().toISOString().slice(0, 10));
    ['f_address','f_reason','f_measurer_user_id','f_measurer_name','f_responsible_user_id','f_responsible_name','f_comment','f_follow_up_date','f_result_status','f_special_calculation'].forEach(id => setVal(id, ''));
    rowModal.show();
  }

  function openEdit(btn) {
    const row = JSON.parse(btn.dataset.row || '{}');
    const id = row.id;
    titleEl.textContent = 'Редактирование записи #' + id;
    form.action = @json(url('/nonclosures')) + '/' + id;
    methodEl.value = 'PATCH';
    setVal('f_entry_date', row.entry_date || '');
    setVal('f_address', row.address || '');
    setVal('f_reason', row.reason || '');
    setVal('f_measurer_user_id', row.measurer_user_id || '');
    setVal('f_measurer_name', row.measurer_name || '');
    setVal('f_responsible_user_id', row.responsible_user_id || '');
    setVal('f_responsible_name', row.responsible_name || '');
    setVal('f_comment', row.comment || '');
    setVal('f_follow_up_date', row.follow_up_date || '');
    setVal('f_result_status', row.result_status || '');
    setVal('f_special_calculation', row.special_calculation || '');
    rowModal.show();
  }

  btnNew?.addEventListener('click', openNew);
  document.querySelectorAll('.btn-edit-row').forEach(btn => btn.addEventListener('click', () => openEdit(btn)));

  @if($errors->any())
    @if(old('_method') === 'PATCH' || old('address') || old('entry_date'))
      openNew();
      titleEl.textContent = 'Проверь данные записи';
      setVal('f_entry_date', @json(old('entry_date')));
      setVal('f_address', @json(old('address')));
      setVal('f_reason', @json(old('reason')));
      setVal('f_measurer_user_id', @json(old('measurer_user_id')));
      setVal('f_measurer_name', @json(old('measurer_name')));
      setVal('f_responsible_user_id', @json(old('responsible_user_id')));
      setVal('f_responsible_name', @json(old('responsible_name')));
      setVal('f_comment', @json(old('comment')));
      setVal('f_follow_up_date', @json(old('follow_up_date')));
      setVal('f_result_status', @json(old('result_status')));
      setVal('f_special_calculation', @json(old('special_calculation')));
    @endif
  @endif
})();
</script>
@endpush
