@extends('layouts.app')

@section('content')
<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
  <div>
    <h3 class="mb-1">Проектировка</h3>
    <div class="text-muted">Standalone-проекты потолков. Сделку можно привязать позже.</div>
  </div>
  <form method="GET" class="d-flex gap-2">
    <input type="text" name="q" class="form-control" placeholder="Поиск по проекту, сделке, клиенту" value="{{ $q }}">
    <button class="btn btn-outline-secondary">Найти</button>
  </form>
</div>

<div class="row g-3">
  <div class="col-xl-4">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Новый проект</div>
      <div class="card-body">
        <form method="POST" action="{{ route('ceiling-projects.store') }}" class="row g-3">
          @csrf
          <div class="col-12">
            <label class="form-label">Название</label>
            <input type="text" name="title" class="form-control" placeholder="Напр.: Квартира 54, кухня + гостиная">
          </div>
          <div class="col-12">
            <label class="form-label">Сделка</label>
            <select name="deal_id" class="form-select">
              <option value="">Пока без сделки</option>
              @foreach($deals as $deal)
                <option value="{{ $deal->id }}">
                  #{{ $deal->id }} {{ $deal->title }}
                  @if($deal->contact?->name)
                    · {{ $deal->contact->name }}
                  @endif
                  @if($deal->contact?->phone)
                    · {{ $deal->contact->phone }}
                  @endif
                </option>
              @endforeach
            </select>
          </div>
          <div class="col-12 d-flex justify-content-end">
            <button class="btn btn-primary">Создать проект</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="col-xl-8">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Проекты</div>
      <div class="card-body">
        @if($projects->count() === 0)
          <div class="text-muted">Проектов пока нет.</div>
        @else
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Проект</th>
                  <th>Сделка</th>
                  <th>Комнаты</th>
                  <th>Статус</th>
                  <th>Обновлен</th>
                  <th></th>
                </tr>
              </thead>
              <tbody>
                @foreach($projects as $project)
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $project->title ?: 'Без названия' }}</div>
                      <div class="small text-muted">#{{ $project->id }}</div>
                    </td>
                    <td>
                      @if($project->deal)
                        <div class="fw-semibold">#{{ $project->deal->id }} {{ $project->deal->title }}</div>
                        <div class="small text-muted">
                          {{ $project->deal->contact?->name ?: 'Без клиента' }}
                          @if($project->deal->contact?->phone)
                            · {{ $project->deal->contact->phone }}
                          @endif
                        </div>
                      @else
                        <span class="text-muted">Не привязана</span>
                      @endif
                    </td>
                    <td>{{ $project->rooms_count }}</td>
                    <td>{{ $statusOptions[$project->status] ?? $project->status }}</td>
                    <td>{{ optional($project->updated_at)->format('d.m.Y H:i') }}</td>
                    <td class="text-end">
                      <a href="{{ route('ceiling-projects.show', $project) }}" class="btn btn-sm btn-outline-primary">Открыть</a>
                    </td>
                  </tr>
                @endforeach
              </tbody>
            </table>
          </div>

          {{ $projects->links() }}
        @endif
      </div>
    </div>
  </div>
</div>
@endsection
