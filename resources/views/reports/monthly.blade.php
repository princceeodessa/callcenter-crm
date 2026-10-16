@extends('layouts.app')

@push('styles')
  <link rel="stylesheet" href="{{ asset('css/bd-daterange.css') }}?v={{ @filemtime(public_path('css/bd-daterange.css')) ?: 1 }}">
@endpush
@push('scripts')
  <script src="{{ asset('js/bd-daterange.js') }}?v={{ @filemtime(public_path('js/bd-daterange.js')) ?: 1 }}"></script>
  <script>window.bdDateRange.init();</script>
  <script>
  // числа таблицы «Операторы» — список сделок окном (без скриптов ссылка откроет его отдельной страницей)
  document.addEventListener('click', function (e) {
    var a = e.target.closest('a.drill-link');
    if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.button !== 0) return;
    e.preventDefault();
    var body = document.getElementById('drillBody');
    body.textContent = 'Загружаю…';
    bootstrap.Modal.getOrCreateInstance(document.getElementById('drillModal')).show();
    fetch(a.href + '&partial=1', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
      .then(function (r) { return r.ok ? r.text() : Promise.reject(r.status); })
      .then(function (html) { body.innerHTML = html; })
      .catch(function () { body.textContent = 'Не получилось загрузить список — попробуйте ещё раз.'; });
  });
  </script>
@endpush
@push('styles')
  <style>a.drill-link{color:inherit;text-decoration:underline;text-decoration-style:dotted;text-underline-offset:3px}a.drill-link:hover{text-decoration-style:solid}</style>
@endpush

@section('content')
@php
  $thisMonth = now()->format('Y-m');
  $prevMonth = now()->subMonthNoOverflow()->format('Y-m');
  $exportQuery = $period['custom'] ? ['from' => $period['from'], 'to' => $period['to'], 'export' => 'contacts'] : ['month' => $month, 'export' => 'contacts'];
  // число таблицы «Операторы» → ссылка на список его сделок (ReportController::drill); ноль — просто текст
  $drillQuery = $period['custom'] ? ['from' => $period['from'], 'to' => $period['to']] : ['month' => $month];
  $dl = function (string $metric, array $row, $value) use ($drillQuery) {
      if ((int) $value === 0 || ! isset($row['id'])) {
          return e($value);
      }

      return '<a class="drill-link" title="Показать сделки" href="'.e(route('reports.monthly', $drillQuery + ['drill' => $metric, 'user' => $row['id']])).'">'.e($value).'</a>';
  };
@endphp
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0">Отчёты</h4>
    <div class="text-muted small">
      @if($mode === 'manager')
        Общий отчёт по операторам и замерщикам.
      @elseif($mode === 'measurer')
        Твой отчёт строится по календарю замеров: где замер выполнен, а где нет.
      @else
        Твой персональный отчёт по сделкам и звонкам.
      @endif
    </div>
  </div>
  <div class="d-flex gap-2 align-items-center flex-wrap">
    <a href="{{ route('reports.monthly', ['month' => $thisMonth]) }}" class="btn btn-sm {{ ! $period['custom'] && $month === $thisMonth ? 'btn-primary' : 'btn-outline-primary' }}">Этот месяц</a>
    <a href="{{ route('reports.monthly', ['month' => $prevMonth]) }}" class="btn btn-sm {{ ! $period['custom'] && $month === $prevMonth ? 'btn-primary' : 'btn-outline-primary' }}">Прошлый месяц</a>
    {{-- период — календарём из дизайн-системы сводок: любой день, неделя или диапазон --}}
    @include('owner._daterange', ['crm' => true, 'action' => route('reports.monthly'), 'params' => [], 'from' => $period['from'], 'to' => $period['to']])
    @if($mode === 'manager' && !empty($showLeads))
      <a href="{{ route('reports.monthly', $exportQuery) }}" class="btn btn-sm btn-outline-success"
         title="Номер телефона, менеджер (кто первым взял лид), записался ли. Без нецелевых и исходящих дозвонов.">⬇ Обращения в Excel</a>
    @endif
  </div>
</div>

@if(!empty($dataPeriodHint))
  <div class="alert alert-info py-2">
    {{ $dataPeriodHint }}
  </div>
@endif

@if($mode === 'measurer')
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Выполнен замер</div>
          <div class="fs-3 fw-semibold text-success">{{ $measurementSummary['completed'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Не выполнен замер</div>
          <div class="fs-3 fw-semibold text-danger">{{ $measurementSummary['notCompleted'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Всего в календаре</div>
          <div class="fs-3 fw-semibold">{{ $measurementSummary['total'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Выполнение</div>
          <div class="fs-3 fw-semibold">{{ $measurementSummary['completionRate'] !== null ? $measurementSummary['completionRate'].'%' : '—' }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-header fw-semibold">Твои замеры</div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Замерщик</th>
            <th>Выполнен</th>
            <th>Не выполнен</th>
            <th>Всего</th>
            <th>Выполнение</th>
          </tr>
        </thead>
        <tbody>
          @forelse($measurementRows as $row)
            <tr>
              <td>{{ $row['name'] }}</td>
              <td class="text-success fw-semibold">{{ $row['completed'] }}</td>
              <td class="text-danger fw-semibold">{{ $row['notCompleted'] }}</td>
              <td>{{ $row['total'] }}</td>
              <td>{{ $row['completionRate'] !== null ? $row['completionRate'].'%' : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="5" class="text-muted">Данных за месяц нет.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@else
  <div class="row g-3 mb-4">
    <div class="col-lg col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Обработано сделок</div>
          <div class="fs-3 fw-semibold">{{ $operatorSummary['created'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-lg col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Закрыто успешно</div>
          <div class="fs-3 fw-semibold text-success">{{ $operatorSummary['closedWon'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-lg col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Закрыто с отказом</div>
          <div class="fs-3 fw-semibold text-danger">{{ $operatorSummary['closedLost'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-lg col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Доп.Работы / Не целевой</div>
          <div class="fs-3 fw-semibold text-warning">{{ $operatorSummary['closedExtraNonTarget'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-lg col-md-6">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Событий звонков</div>
          <div class="fs-3 fw-semibold">{{ $operatorSummary['callActivities'] }}</div>
        </div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header fw-semibold">
      {{ $mode === 'manager' ? 'Операторы' : 'Твой результат' }}
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Пользователь</th>
            @if($mode === 'manager')<th>Роль</th>@endif
            @if(!empty($showLeads))
              <th title="первым перетащил новый лид с «Поступил лид»">Взял новых лидов</th>
              <th>Записал сам</th>
              <th title="из взятых им — не записал он сам (в том числе если потом записал другой)">Не записал</th>
              <th title="записал на замер лиды, которые первым брал другой">Дожал чужих</th>
            @endif
            <th>Обработано сделок</th>
            <th>Успешно</th>
            <th>Отказ</th>
            <th>Доп.Работы / Не целевой</th>
            <th>Звонки</th>
            <th>Конверсия</th>
          </tr>
        </thead>
        <tbody>
          @forelse($operatorRows as $row)
            <tr>
              <td>{{ $row['name'] }}</td>
              @if($mode === 'manager')
                <td><span class="badge text-bg-secondary">{{ $row['role'] }}</span></td>
              @endif
              @if(!empty($showLeads))
                @if($row['leads'] !== null)
                  <td class="fw-semibold">{!! $dl('leads.taken', $row, $row['leads']['taken']) !!}</td>
                  <td class="text-success fw-semibold">{!! $dl('leads.booked_self', $row, $row['leads']['booked_self']) !!}</td>
                  <td class="text-danger fw-semibold">{!! $dl('leads.not_booked', $row, $row['leads']['not_booked']) !!}@if($row['leads']['rescued'] > 0) <span class="text-muted small fw-normal">(записали другие: {{ $row['leads']['rescued'] }})</span>@endif</td>
                  <td class="fw-semibold">{!! $dl('leads.booked_others', $row, $row['leads']['booked_others']) !!}</td>
                @else
                  <td>—</td><td>—</td><td>—</td><td>—</td>
                @endif
              @endif
              <td>{!! $dl('created', $row, $row['created']) !!}</td>
              <td class="text-success fw-semibold">{!! $dl('closedWon', $row, $row['closedWon']) !!}</td>
              <td class="text-danger fw-semibold">{!! $dl('closedLost', $row, $row['closedLost']) !!}</td>
              <td class="text-warning fw-semibold">{!! $dl('closedExtraNonTarget', $row, $row['closedExtraNonTarget']) !!}</td>
              <td>{!! $dl('callActivities', $row, $row['callActivities']) !!}</td>
              <td>{{ $row['winRate'] !== null ? $row['winRate'].'%' : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="{{ ($mode === 'manager' ? 8 : 7) + (!empty($showLeads) ? 4 : 0) }}" class="text-muted">Данных за месяц нет.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="card shadow-sm mb-4">
    <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
      <span class="fw-semibold">Источники звонков</span>
      <span class="small text-muted">Разбивка строится по входящим телефонным номерам: авито частник, радио, вк, авито, сайт и прочее.</span>
    </div>
    <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead>
          <tr>
            <th>Пользователь</th>
            @foreach($callSourceOptions as $sourceKey => $sourceLabel)
              <th>{{ $sourceLabel }}</th>
            @endforeach
            <th>Без категории</th>
            <th>Всего звонков</th>
          </tr>
        </thead>
        <tbody>
          @forelse($operatorRows as $row)
            <tr>
              <td>{{ $row['name'] }}</td>
              @foreach($callSourceOptions as $sourceKey => $sourceLabel)
                <td>{{ $row['callSourceCounts'][$sourceKey] ?? 0 }}</td>
              @endforeach
              <td>{{ $row['uncategorizedCallActivities'] ?? 0 }}</td>
              <td class="fw-semibold">{{ $row['callActivities'] }}</td>
            </tr>
          @empty
            <tr>
              <td colspan="{{ count($callSourceOptions) + 3 }}" class="text-muted">Данных по источникам звонков за месяц нет.</td>
            </tr>
          @endforelse
        </tbody>
        @if($operatorRows->count() > 1)
          <tfoot>
            <tr class="fw-semibold">
              <td>Итого</td>
              @foreach($callSourceOptions as $sourceKey => $sourceLabel)
                <td>{{ $operatorSummary['callSourceCounts'][$sourceKey] ?? 0 }}</td>
              @endforeach
              <td>{{ $operatorSummary['uncategorizedCallActivities'] ?? 0 }}</td>
              <td>{{ $operatorSummary['callActivities'] }}</td>
            </tr>
          </tfoot>
        @endif
      </table>
    </div>
  </div>

  @if($isManager)
    <div class="card shadow-sm">
      <div class="card-header d-flex align-items-center justify-content-between">
        <span class="fw-semibold">Замерщики</span>
        <span class="small text-muted">Сравнение идёт только по заключённым и не заключённым, отменённые не входят в процент.</span>
      </div>
      <div class="table-responsive">
        <table class="table table-sm align-middle mb-0">
          <thead>
            <tr>
              <th>Замерщик</th>
              <th>Заключён</th>
              <th>Не заключённые</th>
              <th>Запланирован / принят</th>
              <th>Отменены</th>
              <th>Успешность</th>
            </tr>
          </thead>
          <tbody>
            @forelse($measurementRows as $row)
              <tr>
                <td>{{ $row['name'] }}</td>
                <td class="text-success fw-semibold">{{ $row['successful'] }}</td>
                <td class="text-danger fw-semibold">{{ $row['refused'] }}</td>
                <td>{{ $row['planned'] }}</td>
                <td class="text-muted">{{ $row['cancelled'] }}</td>
                <td>{{ $row['successRate'] !== null ? $row['successRate'].'%' : '—' }}</td>
              </tr>
            @empty
              <tr><td colspan="6" class="text-muted">Замерщиков пока нет.</td></tr>
            @endforelse
          </tbody>
          @if($measurementRows->isNotEmpty())
            <tfoot>
              <tr class="fw-semibold">
                <td>Итого</td>
                <td class="text-success">{{ $measurementSummary['successful'] }}</td>
                <td class="text-danger">{{ $measurementSummary['refused'] }}</td>
                <td>{{ $measurementSummary['planned'] }}</td>
                <td class="text-muted">{{ $measurementSummary['cancelled'] }}</td>
                <td>{{ $measurementSummary['successRate'] !== null ? $measurementSummary['successRate'].'%' : '—' }}</td>
              </tr>
            </tfoot>
          @endif
        </table>
      </div>
    </div>
  @endif
@endif
<div class="modal fade" id="drillModal" tabindex="-1" aria-label="Сделки">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2"><span class="fw-semibold">Сделки</span><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button></div>
      <div class="modal-body" id="drillBody"></div>
    </div>
  </div>
</div>
@endsection
