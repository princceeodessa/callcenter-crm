@extends('layouts.app')

@php
  $currentLifecycle = $lifecycle ?? 'active';
@endphp

@section('content')
<div class="d-flex align-items-start justify-content-between flex-wrap gap-3 mb-3">
  <div>
    <h3 class="mb-1">Проектировка</h3>
    <div class="text-muted">Standalone-проекты потолков. Сделку можно привязать позже, а завершённые версии убирать в архив.</div>
  </div>
  <form method="GET" class="d-flex gap-2 flex-wrap">
    <input type="text" name="q" class="form-control" placeholder="Поиск по проекту, сделке, клиенту" value="{{ $q }}">
    <select name="lifecycle" class="form-select">
      @foreach($lifecycleOptions as $value => $label)
        <option value="{{ $value }}" @selected($currentLifecycle === $value)>{{ $label }}</option>
      @endforeach
    </select>
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
      <div class="card-header fw-semibold d-flex justify-content-between align-items-center flex-wrap gap-2">
        <span>Проекты</span>
        <div class="small text-muted">
          @if($currentLifecycle === 'archived')
            Показан архив проектировок.
          @elseif($currentLifecycle === 'all')
            Показаны активные и архивные проектировки.
          @else
            Показаны только активные проектировки.
          @endif
        </div>
      </div>
      <div class="card-body">
        @if($projects->count() === 0)
          <div class="text-muted">
            @if($currentLifecycle === 'archived')
              Архивных проектов пока нет.
            @else
              Проектов пока нет.
            @endif
          </div>
        @else
          <div class="table-responsive">
            <table class="table align-middle">
              <thead>
                <tr>
                  <th>Проект</th>
                  <th>Сделка</th>
                  <th>Комнаты</th>
                  <th>Статус</th>
                  <th>Обновлён</th>
                  <th class="text-end">Действия</th>
                </tr>
              </thead>
              <tbody>
                @foreach($projects as $project)
                  @php
                    $isArchived = $project->archived_at !== null;
                  @endphp
                  <tr>
                    <td>
                      <div class="fw-semibold">{{ $project->title ?: 'Без названия' }}</div>
                      <div class="small text-muted d-flex gap-2 flex-wrap align-items-center">
                        <span>#{{ $project->id }}</span>
                        @if($isArchived)
                          <span class="badge text-bg-secondary">Архив</span>
                          <span>с {{ optional($project->archived_at)->format('d.m.Y H:i') }}</span>
                        @endif
                      </div>
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
                      <div class="d-flex gap-1 justify-content-end flex-wrap">
                        <a href="{{ route('ceiling-projects.show', $project) }}" class="btn btn-sm btn-outline-primary">Открыть</a>

                        <form method="POST" action="{{ route('ceiling-projects.duplicate', $project) }}">
                          @csrf
                          <button class="btn btn-sm btn-outline-dark">Копия</button>
                        </form>

                        @if($isArchived)
                          <form method="POST" action="{{ route('ceiling-projects.restore', $project) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="redirect" value="index">
                            <input type="hidden" name="q" value="{{ $q }}">
                            <input type="hidden" name="lifecycle" value="{{ $currentLifecycle }}">
                            <button class="btn btn-sm btn-outline-success">Восстановить</button>
                          </form>

                          <form method="POST" action="{{ route('ceiling-projects.destroy', $project) }}" onsubmit="return confirm('Удалить архивный проект безвозвратно?');">
                            @csrf
                            @method('DELETE')
                            <input type="hidden" name="redirect" value="index">
                            <input type="hidden" name="q" value="{{ $q }}">
                            <input type="hidden" name="lifecycle" value="{{ $currentLifecycle }}">
                            <button class="btn btn-sm btn-outline-danger">Удалить</button>
                          </form>
                        @else
                          <form method="POST" action="{{ route('ceiling-projects.archive', $project) }}">
                            @csrf
                            @method('PATCH')
                            <input type="hidden" name="redirect" value="index">
                            <input type="hidden" name="q" value="{{ $q }}">
                            <input type="hidden" name="lifecycle" value="{{ $currentLifecycle }}">
                            <button class="btn btn-sm btn-outline-secondary">В архив</button>
                          </form>
                        @endif
                      </div>
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
