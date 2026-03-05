@extends('layouts.app')

@section('content')
<div class="d-flex align-items-start justify-content-between mb-3">
  <div>
    <h4 class="mb-1">
      {{ $deal->title }} <span class="text-muted">#{{ $deal->id }}</span>
      @if(!$deal->is_ready)
        <span class="badge text-bg-warning ms-2">не заполнено</span>
      @endif
    </h4>
    <div class="text-muted small">
      Клиент: {{ $deal->contact?->name ?? 'Без имени' }}
      @if($deal->contact?->phone) • {{ $deal->contact->phone }} @endif
      • Ответственный: {{ $deal->responsible?->name ?? '—' }}
      @if($deal->closed_at)
        • <span class="badge text-bg-{{ $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : 'secondary') }}">
            {{ $deal->closed_result === 'won' ? 'Успешно закрыта' : ($deal->closed_result === 'lost' ? 'Отказ' : 'Закрыта') }}
          </span>
      @endif
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

        @php
          $missing = collect($deal->missing_fields)->map(fn($f) => match($f) {
            'title' => 'название сделки',
            'amount' => 'сумму',
            'responsible' => 'ответственного',
            default => $f,
          })->values()->all();
        @endphp

        @if(!$deal->is_ready)
          <div class="alert alert-warning mt-2 mb-2 py-2 small">
            Рекомендуем заполнить: <b>{{ implode(', ', $missing) }}</b>.
          </div>
        @endif

        <hr class="my-2">
        <div class="fw-semibold mb-2">Заполнение / редактирование</div>
        <form method="POST" action="{{ route('deals.update', $deal) }}" class="row g-2">
          @csrf
          @method('PATCH')
          <div class="col-12">
            <label class="form-label mb-1">Название сделки</label>
            <input name="title" class="form-control form-control-sm" value="{{ $deal->title }}" required>
          </div>
          <div class="col-7">
            <label class="form-label mb-1">Ответственный</label>
            <select name="responsible_user_id" class="form-select form-select-sm" required>
              @foreach($users as $u)
                <option value="{{ $u->id }}" @selected($deal->responsible_user_id === $u->id)>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-5">
            <label class="form-label mb-1">Сумма (RUB)</label>
            <input name="amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" value="{{ $deal->amount ?? '' }}" required>
          </div>
          <div class="col-12">
            <button class="btn btn-sm btn-primary">Сохранить</button>
          </div>
        </form>
        @if($deal->closed_at)
          <div class="mt-2">
            <div class="mb-1"><b>Закрыта:</b> {{ $deal->closed_at->format('d.m.Y H:i') }}</div>
            <div class="mb-1"><b>Результат:</b> {{ $deal->closed_result }}</div>
            @if($deal->closed_reason)
              <div class="mb-1"><b>Причина:</b> {{ $deal->closed_reason }}</div>
            @endif
          </div>
        @else
          <hr class="my-2">
          <div class="fw-semibold mb-2">Закрытие сделки</div>
          <form method="POST" action="{{ route('deals.close', $deal) }}" class="d-flex gap-2 mb-2">
            @csrf
            <input type="hidden" name="result" value="won">
            <input class="form-control form-control-sm" name="reason" placeholder="Комментарий (необязательно)">
            <button class="btn btn-sm btn-success">✅ Успешно</button>
          </form>
          <form method="POST" action="{{ route('deals.close', $deal) }}" class="d-flex gap-2">
            @csrf
            <input type="hidden" name="result" value="lost">
            <input class="form-control form-control-sm" name="reason" placeholder="Причина отказа (необязательно)">
            <button class="btn btn-sm btn-danger">❌ Отказ</button>
          </form>
        @endif
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
            <label class="form-label small mb-1">Когда напомнить</label>
            <input name="due_at" type="datetime-local" class="form-control form-control-sm" required>
          </div>

          <div class="mb-2">
            <label class="form-label small mb-1">Кому назначить</label>
            <select name="assigned_user_id" class="form-select form-select-sm" required>
              @foreach($users as $u)
                <option value="{{ $u->id }}" @selected(($deal->responsible_user_id ?? auth()->id()) === $u->id)>{{ $u->name }}</option>
              @endforeach
            </select>
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
                  @if($task->assignedTo) • ответственный: <b>{{ $task->assignedTo->name }}</b> @endif
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
              <div class="fw-semibold">{{ $a->type_label }}</div>
              <div class="text-muted small">{{ optional($a->created_at)->format('d.m.Y H:i') }}</div>
            </div>
            <div class="small">{!! nl2br(e($a->body ?? '')) !!}</div>

            @php
              $payload = is_array($a->payload ?? null) ? $a->payload : [];
              $callid = $payload['callid'] ?? null;
              $recModel = ($callid && isset($recordingsByCallid[$callid])) ? $recordingsByCallid[$callid] : null;
              $recUrl = $recModel?->recording_url ?? ($payload['recording_url'] ?? null);
            @endphp

            @if(is_string($recUrl) && $recUrl !== '')
              <div class="small mt-1 d-flex align-items-center gap-2 flex-wrap">
                <a href="{{ $recUrl }}" target="_blank" rel="noopener">🎧 Запись звонка</a>

                @if($recModel)
                  @php
                    $st = $recModel->transcript_status;
                    $badge = match($st) {
                      'done' => 'success',
                      'queued' => 'secondary',
                      'processing' => 'warning',
                      'failed' => 'danger',
                      default => 'light'
                    };
                  @endphp
                  <span class="badge text-bg-{{ $badge }}">{{ $st }}</span>

                  @if($st === 'done' && $recModel->transcript_text)
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#tr-{{ $recModel->id }}">
                      📝 Расшифровка
                    </button>
                    <div class="collapse mt-2" id="tr-{{ $recModel->id }}">
                      <div class="border rounded p-2 bg-white small" style="white-space: pre-wrap;">{{ $recModel->transcript_text }}</div>
                    </div>
                  @elseif(in_array($st, ['none','failed'], true))
                    <form method="POST" action="{{ route('recordings.transcribe', $recModel) }}">
                      @csrf
                      <button class="btn btn-sm btn-outline-primary">📝 Расшифровать</button>
                    </form>
                    @if($st === 'failed' && $recModel->transcript_error)
                      <div class="text-danger small">Ошибка: {{ $recModel->transcript_error }}</div>
                    @endif
                  @endif
                @else
                  <span class="text-muted small">(нет записи в БД)</span>
                @endif
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
