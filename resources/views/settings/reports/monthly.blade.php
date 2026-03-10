@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <div>
    <h4 class="mb-0">Отчёты</h4>
    <div class="text-muted small">
      @if($mode === 'manager')
        Общий отчёт по операторам и замерщикам.
      @elseif($mode === 'measurer')
        Твой отчёт по замерам. Отменённые не участвуют в сравнении успешных и отказных.
      @else
        Твой персональный отчёт по сделкам и звонкам.
      @endif
    </div>
  </div>
  <form method="GET" action="{{ route('reports.monthly') }}" class="d-flex gap-2">
    <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}" style="width: 170px;">
    <button class="btn btn-sm btn-outline-primary">Показать</button>
  </form>
</div>

@if($mode === 'measurer')
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Заключённые</div>
          <div class="fs-3 fw-semibold text-success">{{ $measurementSummary['successful'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Не заключённые</div>
          <div class="fs-3 fw-semibold text-danger">{{ $measurementSummary['refused'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Запланирован / принят</div>
          <div class="fs-3 fw-semibold">{{ $measurementSummary['planned'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Успешность</div>
          <div class="fs-3 fw-semibold">{{ $measurementSummary['successRate'] !== null ? $measurementSummary['successRate'].'%' : '—' }}</div>
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
            <tr><td colspan="6" class="text-muted">Данных за месяц нет.</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
@else
  <div class="row g-3 mb-4">
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Создано сделок</div>
          <div class="fs-3 fw-semibold">{{ $operatorSummary['created'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Закрыто успешно</div>
          <div class="fs-3 fw-semibold text-success">{{ $operatorSummary['closedWon'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
      <div class="card shadow-sm h-100">
        <div class="card-body">
          <div class="text-muted small">Закрыто с отказом</div>
          <div class="fs-3 fw-semibold text-danger">{{ $operatorSummary['closedLost'] }}</div>
        </div>
      </div>
    </div>
    <div class="col-md-3">
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
            <th>Создано сделок</th>
            <th>Успешно</th>
            <th>Отказ</th>
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
              <td>{{ $row['created'] }}</td>
              <td class="text-success fw-semibold">{{ $row['closedWon'] }}</td>
              <td class="text-danger fw-semibold">{{ $row['closedLost'] }}</td>
              <td>{{ $row['callActivities'] }}</td>
              <td>{{ $row['winRate'] !== null ? $row['winRate'].'%' : '—' }}</td>
            </tr>
          @empty
            <tr><td colspan="{{ $mode === 'manager' ? 7 : 6 }}" class="text-muted">Данных за месяц нет.</td></tr>
          @endforelse
        </tbody>
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
@endsection
