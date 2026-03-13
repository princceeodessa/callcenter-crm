@extends('layouts.app')

@php
    $dealLabel = function ($deal) {
        $contact = trim((string) ($deal->contact?->name ?? ''));
        $phone = trim((string) ($deal->contact?->phone ?? ''));
        $title = trim((string) ($deal->title ?? ''));
        $parts = array_filter([$title !== '' ? $title : ('Сделка #'.$deal->id), $contact, $phone]);

        return implode(' | ', $parts);
    };

    $taskDealTitle = function ($task) {
        $deal = $task->deal;
        if (! $deal) {
            return 'Сделка не найдена';
        }

        $contact = trim((string) ($deal->contact?->name ?? ''));
        $phone = trim((string) ($deal->contact?->phone ?? ''));
        $title = trim((string) ($deal->title ?? ''));
        $parts = array_filter([$title !== '' ? $title : ('Сделка #'.$deal->id), $contact, $phone]);

        return implode(' | ', $parts);
    };

    $buildTasksUrl = function (array $changes = []) {
        $query = array_merge(request()->query(), $changes);

        foreach ($query as $key => $value) {
            if ($value === null || $value === '' || ($key === 'assigned_user_id' && (int) $value === 0)) {
                unset($query[$key]);
            }
        }

        $queryString = http_build_query($query);

        return route('tasks.index').($queryString !== '' ? '?'.$queryString : '');
    };

    $daySectionTitle = $isTodayFocusDate ? 'На сегодня' : 'На дату';
    $daySectionEmpty = $isTodayFocusDate
        ? 'На сегодня открытых дел нет.'
        : 'На выбранную дату открытых дел нет.';
    $previousSectionTitle = $isTodayFocusDate ? 'Просроченные' : 'Раньше выбранной даты';
    $previousSectionEmpty = $isTodayFocusDate ? 'Просроченных дел нет.' : 'До выбранной даты дел нет.';
    $nextSectionTitle = $isTodayFocusDate ? 'Ближайшие' : 'После выбранной даты';
    $nextSectionEmpty = $isTodayFocusDate ? 'Ближайших дел нет.' : 'После выбранной даты дел нет.';
@endphp

@section('content')
<div class="d-flex flex-column gap-3">
    <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
        <div>
            <h4 class="mb-1">Дела</h4>
            <div class="text-muted">{{ $isTodayFocusDate ? 'Сегодня' : 'Выбрана дата' }}: {{ $focusDateLabel }}</div>
        </div>
        <form class="d-flex gap-2 flex-wrap" method="GET" action="{{ route('tasks.index') }}">
            <select class="form-select form-select-sm" name="assigned_user_id" style="min-width: 220px;">
                <option value="0">Все сотрудники</option>
                <option value="-1" @selected($assignedUserId === -1)>Только "Всем"</option>
                @foreach($users as $worker)
                    <option value="{{ $worker->id }}" @selected($assignedUserId === (int) $worker->id)>{{ $worker->name }}</option>
                @endforeach
            </select>
            <input type="date" class="form-control form-control-sm" name="focus_date" value="{{ $focusDate }}">
            <input class="form-control form-control-sm" name="q" value="{{ $search }}" placeholder="Поиск по делу, клиенту, телефону, названию">
            <button class="btn btn-sm btn-primary">Найти</button>
            @if($search !== '' || $assignedUserId !== 0 || ! $isTodayFocusDate)
                <a class="btn btn-sm btn-outline-secondary" href="{{ route('tasks.index') }}">Сбросить</a>
            @endif
        </form>
    </div>

    <div class="card shadow-sm">
        <div class="card-header fw-semibold">Новое дело</div>
        <div class="card-body">
            <form method="POST" action="{{ route('tasks.page.store') }}" class="row g-3">
                @csrf
                <div class="col-12 col-lg-5">
                    <label class="form-label">Сделка</label>
                    <select name="deal_id" class="form-select" required>
                        <option value="">Выберите сделку</option>
                        @foreach($deals as $deal)
                            <option value="{{ $deal->id }}" @selected((int) old('deal_id') === (int) $deal->id)>{{ $dealLabel($deal) }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-3">
                    <label class="form-label">Кому назначить</label>
                    <select name="assigned_user_id" class="form-select">
                        <option value="0" @selected((string) old('assigned_user_id', '0') === '0')>Всем</option>
                        @foreach($users as $worker)
                            <option value="{{ $worker->id }}" @selected((int) old('assigned_user_id') === (int) $worker->id)>{{ $worker->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="col-12 col-lg-4">
                    <label class="form-label">Когда напомнить</label>
                    <input type="datetime-local" name="due_at" class="form-control" value="{{ old('due_at', now()->addHour()->format('Y-m-d\\TH:i')) }}" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Название</label>
                    <input name="title" class="form-control" value="{{ old('title') }}" placeholder="Например: связаться с клиентом" required>
                </div>
                <div class="col-12">
                    <label class="form-label">Комментарий</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Комментарий по делу">{{ old('description') }}</textarea>
                </div>
                <div class="col-12">
                    <button class="btn btn-primary">Добавить дело</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div class="d-flex flex-column">
                <span class="fw-semibold">{{ $daySectionTitle }}</span>
                <span class="text-muted small">{{ $focusDateLabel }}</span>
            </div>
            <span class="text-muted small">{{ $selectedTasks->count() }} шт.</span>
        </div>
        <div class="card-body">
            @forelse($selectedTasks as $task)
                <div class="border rounded p-3 mb-2">
                    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                        <div>
                            <div class="fw-semibold">{{ $task->title }}</div>
                            <div class="text-muted small mt-1">
                                @if($task->deal)
                                    <a href="{{ route('deals.show', $task->deal) }}" class="text-decoration-none">{{ $taskDealTitle($task) }}</a>
                                @else
                                    {{ $taskDealTitle($task) }}
                                @endif
                            </div>
                            <div class="text-muted small mt-1">
                                До {{ optional($task->due_at)->format('d.m.Y H:i') ?: 'без срока' }}
                                | Ответственный: {{ $task->assignee_label }}
                            </div>
                        </div>
                        <div class="d-flex gap-2 flex-wrap">
                            <a class="btn btn-sm btn-outline-primary" href="{{ $buildTasksUrl(['edit_task' => $task->id]) }}">Редактировать</a>
                            @if($task->deal)
                                <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.show', $task->deal) }}">Открыть сделку</a>
                            @endif
                            <form method="POST" action="{{ route('tasks.complete', $task) }}">
                                @csrf
                                <button class="btn btn-sm btn-success">Выполнено</button>
                            </form>
                        </div>
                    </div>
                    @if($task->description)
                        <div class="mt-2">{{ $task->description }}</div>
                    @endif

                    @if($editingTaskId === (int) $task->id)
                        @include('tasks._edit_form', [
                            'task' => $task,
                            'users' => $users,
                            'cancelUrl' => $buildTasksUrl(['edit_task' => null]),
                        ])
                    @endif
                </div>
            @empty
                <div class="text-muted">{{ $daySectionEmpty }}</div>
            @endforelse
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold">{{ $previousSectionTitle }}</span>
                    <span class="text-muted small">{{ $previousTasks->count() }} шт.</span>
                </div>
                <div class="card-body">
                    @forelse($previousTasks as $task)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    <div class="text-muted small mt-1">
                                        @if($task->deal)
                                            <a href="{{ route('deals.show', $task->deal) }}" class="text-decoration-none">{{ $taskDealTitle($task) }}</a>
                                        @else
                                            {{ $taskDealTitle($task) }}
                                        @endif
                                    </div>
                                    <div class="text-muted small mt-1">
                                        До {{ optional($task->due_at)->format('d.m.Y H:i') ?: 'без срока' }}
                                        | Ответственный: {{ $task->assignee_label }}
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $buildTasksUrl(['edit_task' => $task->id]) }}">Редактировать</a>
                                    @if($task->deal)
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.show', $task->deal) }}">Открыть сделку</a>
                                    @endif
                                    <form method="POST" action="{{ route('tasks.complete', $task) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success">Выполнено</button>
                                    </form>
                                </div>
                            </div>
                            @if($task->description)
                                <div class="mt-2">{{ $task->description }}</div>
                            @endif

                            @if($editingTaskId === (int) $task->id)
                                @include('tasks._edit_form', [
                                    'task' => $task,
                                    'users' => $users,
                                    'cancelUrl' => $buildTasksUrl(['edit_task' => null]),
                                ])
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">{{ $previousSectionEmpty }}</div>
                    @endforelse
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-6">
            <div class="card shadow-sm h-100">
                <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <span class="fw-semibold">{{ $nextSectionTitle }}</span>
                    <span class="text-muted small">{{ $nextTasks->count() }} шт.</span>
                </div>
                <div class="card-body">
                    @forelse($nextTasks as $task)
                        <div class="border rounded p-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <div class="fw-semibold">{{ $task->title }}</div>
                                    <div class="text-muted small mt-1">
                                        @if($task->deal)
                                            <a href="{{ route('deals.show', $task->deal) }}" class="text-decoration-none">{{ $taskDealTitle($task) }}</a>
                                        @else
                                            {{ $taskDealTitle($task) }}
                                        @endif
                                    </div>
                                    <div class="text-muted small mt-1">
                                        До {{ optional($task->due_at)->format('d.m.Y H:i') ?: 'без срока' }}
                                        | Ответственный: {{ $task->assignee_label }}
                                    </div>
                                </div>
                                <div class="d-flex gap-2 flex-wrap">
                                    <a class="btn btn-sm btn-outline-primary" href="{{ $buildTasksUrl(['edit_task' => $task->id]) }}">Редактировать</a>
                                    @if($task->deal)
                                        <a class="btn btn-sm btn-outline-secondary" href="{{ route('deals.show', $task->deal) }}">Открыть сделку</a>
                                    @endif
                                    <form method="POST" action="{{ route('tasks.complete', $task) }}">
                                        @csrf
                                        <button class="btn btn-sm btn-success">Выполнено</button>
                                    </form>
                                </div>
                            </div>
                            @if($task->description)
                                <div class="mt-2">{{ $task->description }}</div>
                            @endif

                            @if($editingTaskId === (int) $task->id)
                                @include('tasks._edit_form', [
                                    'task' => $task,
                                    'users' => $users,
                                    'cancelUrl' => $buildTasksUrl(['edit_task' => null]),
                                ])
                            @endif
                        </div>
                    @empty
                        <div class="text-muted">{{ $nextSectionEmpty }}</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
