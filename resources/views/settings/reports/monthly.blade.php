@extends('layouts.app')

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h4 class="mb-0">Отчёт за месяц</h4>
  <form method="GET" action="{{ route('reports.monthly') }}" class="d-flex gap-2">
    <input type="month" name="month" class="form-control form-control-sm" value="{{ $month }}" style="width: 170px;">
    <button class="btn btn-sm btn-outline-primary">Показать</button>
  </form>
</div>

<div class="row g-3">
  <div class="col-md-3">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Создано сделок</div>
        <div class="fs-3 fw-semibold">{{ $created }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Закрыто успешно</div>
        <div class="fs-3 fw-semibold text-success">{{ $closedWon }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Закрыто с отказом</div>
        <div class="fs-3 fw-semibold text-danger">{{ $closedLost }}</div>
      </div>
    </div>
  </div>
  <div class="col-md-3">
    <div class="card shadow-sm">
      <div class="card-body">
        <div class="text-muted small">Событий звонков</div>
        <div class="fs-3 fw-semibold">{{ $callActivities }}</div>
      </div>
    </div>
  </div>
</div>

<div class="mt-4">
  <div class="text-muted small">
    Подсказка: завершённые сделки можно смотреть в разделе <a href="{{ route('deals.closed') }}">Завершённые</a>.
  </div>
</div>
@endsection
