@extends('layouts.app')

@section('content')
<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <h4 class="mb-1">{{ $deal->title }} <span class="text-muted">#{{ $deal->id }}</span></h4>
    <div class="text-muted small">
      Клиент: {{ $deal->contact?->name ?? 'Без имени' }}
      @if($deal->contact?->phone) • {{ $deal->contact->phone }} @endif
      • Ответственный: {{ $deal->responsible?->name ?? '—' }}
    </div>
  </div>

  <form method="POST" action="{{ route('deals.stage', $deal) }}" class="d-flex gap-2">
    @csrf
    <select name="stage_id" class="form-select form-select-sm" style="min-width: 280px;">
      @foreach($stages as $s)
        <option value="{{ $s->id }}" @selected($deal->stage_id === $s->id)>{{ $s->name }}</option>
      @endforeach
    </select>
    <button class="btn btn-sm btn-outline-primary">Сменить стадию</button>
  </form>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">О сделке</div>
      <div class="card-body small">
        <div class="mb-1"><b>Стадия:</b> {{ $deal->stage?->name }}</div>
        <div class="mb-1"><b>Создано:</b> {{ optional($deal->created_at)->format('d.m.Y H:i') }}</div>
        <div class="mb-1"><b>Сумма:</b> {{ $deal->amount ? number_format($deal->amount,2,',',' ') : '—' }} {{ $deal->currency ?? 'RUB' }}</div>
        <div class="mb-1"><b>Готовность помещения:</b> {{ $deal->readiness_status ?? '—' }}</div>
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Чаты</div>
      <div class="card-body">
        @if(($deal->conversations?->count() ?? 0) === 0)
          <div class="text-muted small">Пока нет связанных диалогов</div>
        @else
          <div class="list-group">
            @foreach($deal->conversations as $c)
              @php
                $badge = match($c->channel) {
                  'vk' => 'VK',
                  'telegram' => 'TG',
                  'avito' => 'Avito',
                  default => strtoupper($c->channel),
                };
              @endphp
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start" href="{{ route('chats.show', $c) }}">
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="badge text-bg-secondary">{{ $badge }}</span>
                    <span class="fw-semibold">Диалог</span>
                  </div>
                  <div class="text-muted small mt-1">{{ \Illuminate\Support\Str::limit($c->lastMessage?->body ?? '—', 80) }}</div>
                </div>
                <div class="text-end">
                  <div class="text-muted small">{{ $c->last_message_at ? $c->last_message_at->format('d.m H:i') : '' }}</div>
                  @if(($c->unread_count ?? 0) > 0)
                    <span class="badge text-bg-primary mt-1">{{ $c->unread_count }}</span>
                  @endif
                </div>
              </a>
            @endforeach
          </div>
        @endif
      </div>
    </div>

    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Задачи / Дела</div>
      <div class="card-body">
        <form method="POST" action="{{ route('tasks.store', $deal) }}" class="mb-3">
          @csrf
          <div class="mb-2">
            <input name="title" class="form-control form-control-sm" placeholder="Напр.: Связаться с клиентом" required>
          </div>
          <div class="mb-2">
            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="Комментарий (необязательно)"></textarea>
          </div>
          <div class="mb-2">
            <input name="due_at" type="datetime-local" class="form-control form-control-sm">
          </div>
          <button class="btn btn-sm btn-primary">Добавить дело</button>
        </form>

        @forelse($deal->tasks as $task)
          <div class="border rounded p-2 mb-2 bg-white">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="fw-semibold">{{ $task->title }}</div>
                <div class="text-muted small">
                  Статус: <b>{{ $task->status }}</b>
                  @if($task->due_at) • до {{ $task->due_at->format('d.m.Y H:i') }} @endif
                </div>
              </div>

              @if($task->status !== 'done')
                <form method="POST" action="{{ route('tasks.complete', $task) }}">
                  @csrf
                  <button class="btn btn-sm btn-success">Выполнено</button>
                </form>
              @endif
            </div>
            @if($task->description)
              <div class="text-muted small mt-1">{{ $task->description }}</div>
            @endif
          </div>
        @empty
          <div class="text-muted small">Дела отсутствуют</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Лента (История)</div>
      <div class="card-body">
        @forelse($deal->activities as $a)
          <div class="border-bottom pb-2 mb-2">
            <div class="d-flex justify-content-between">
              <div class="fw-semibold">{{ $a->type }}</div>
              <div class="text-muted small">{{ optional($a->created_at)->format('d.m.Y H:i') }}</div>
            </div>
            <div class="small">{!! nl2br(e($a->body ?? '')) !!}</div>
            @php($rec = is_array($a->payload ?? null) ? ($a->payload['recording_url'] ?? null) : null)
            @if(is_string($rec) && $rec !== '')
              <div class="small mt-1">
                <a href="{{ $rec }}" target="_blank" rel="noopener">🎧 Запись звонка</a>
              </div>
            @endif
          </div>
        @empty
          <div class="text-muted small">Пока нет событий</div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
