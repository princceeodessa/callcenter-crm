@extends('layouts.app')

@push('styles')
<style>
  .activity-item + .activity-item { margin-top: 1rem; }
  .activity-call-card {
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 1rem;
    background: linear-gradient(180deg, rgba(248,250,252,.96), rgba(241,245,249,.92));
    padding: 1rem;
    margin-top: .75rem;
  }
  .activity-call-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
    gap: .75rem 1rem;
  }
  .activity-call-field {
    display: flex;
    flex-direction: column;
    gap: .2rem;
  }
  .activity-call-label {
    font-size: .72rem;
    text-transform: uppercase;
    letter-spacing: .04em;
    color: #64748b;
  }
  .activity-call-value {
    font-weight: 600;
    color: #0f172a;
    word-break: break-word;
  }
  .activity-call-audio audio { width: 100%; }
  .activity-meta { color: #64748b; font-size: .85rem; }
</style>
@endpush

@php
  $editingTaskId = max(0, (int) request()->query('edit_task', 0));

  $buildDealUrl = function (array $changes = []) use ($deal) {
      $query = array_merge(request()->query(), $changes);

      foreach ($query as $key => $value) {
          if ($value === null || $value === '') {
              unset($query[$key]);
          }
      }

      $queryString = http_build_query($query);

      return route('deals.show', $deal).($queryString !== '' ? '?'.$queryString : '');
  };

  $formatPhone = function ($value) {
      $digits = preg_replace('/\D+/', '', (string) $value);
      if (!$digits) {
          return '—';
      }
      if (strlen($digits) === 11 && $digits[0] === '8') {
          $digits = '7'.substr($digits, 1);
      }
      if (strlen($digits) === 10) {
          $digits = '7'.$digits;
      }
      if (strlen($digits) === 11 && $digits[0] === '7') {
          return sprintf('+7 (%s) %s-%s-%s', substr($digits, 1, 3), substr($digits, 4, 3), substr($digits, 7, 2), substr($digits, 9, 2));
      }
      return '+'.$digits;
  };

  $formatDuration = function ($seconds) {
      if ($seconds === null || $seconds === '') {
          return '—';
      }
      $value = (int) $seconds;
      return sprintf('%02d:%02d', intdiv($value, 60), $value % 60);
  };

  $formatCallMoment = function ($raw) {
      $raw = trim((string) $raw);
      if ($raw === '') {
          return '—';
      }
      foreach (['Ymd\\THis\\Z', 'Y-m-d H:i:s', DATE_ATOM] as $format) {
          try {
              $dt = \Carbon\Carbon::createFromFormat($format, $raw);
              if ($dt) {
                  return $dt->timezone(config('app.timezone'))->format('d.m.Y H:i');
              }
          } catch (\Throwable) {
          }
      }
      try {
          return \Carbon\Carbon::parse($raw)->format('d.m.Y H:i');
      } catch (\Throwable) {
          return $raw;
      }
  };

  $mojibakeScore = function (string $value): int {
      preg_match_all('/(?:[РС][\x{0400}-\x{040F}\x{0450}-\x{045F}\x{00A0}-\x{00FF}\x{2010}-\x{203A}\x{20AC}\x{2116}\x{2122}]|[ÐÑ][\x{0080}-\x{00FF}])/u', $value, $matches);

      return count($matches[0]);
  };

  $decodeMojibakeChunk = function (string $value) use ($mojibakeScore) {
      $rawValue = $value;
      $value = trim(str_replace("\u{00A0}", ' ', $value));
      if ($value === '' || $mojibakeScore($value) === 0) {
          return $value;
      }

      $converted = @mb_convert_encoding($rawValue, 'Windows-1251', 'UTF-8');
      if (!is_string($converted) || $converted === '' || preg_match('//u', $converted) !== 1) {
          return $value;
      }

      $converted = trim(str_replace("\u{00A0}", ' ', $converted));
      preg_match_all('/[\x{0410}-\x{044F}ЁёA-Za-z0-9]/u', $converted, $readableMatches);

      return $mojibakeScore($converted) < $mojibakeScore($value) && count($readableMatches[0]) > 0
          ? $converted
          : $value;
  };

  $normalizeActivityText = function ($value) use ($decodeMojibakeChunk) {
      if (!is_scalar($value)) {
          return '';
      }

      $value = html_entity_decode((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
      $value = trim(str_replace("\u{00A0}", ' ', $value));

      if ($value === '') {
          return '';
      }

      $decodedWhole = $decodeMojibakeChunk($value);
      if ($decodedWhole !== $value) {
          return $decodedWhole;
      }

      $parts = preg_split('/(\s+)/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);
      if (!is_array($parts)) {
          return $value;
      }

      foreach ($parts as $index => $part) {
          if ($part === '' || preg_match('/^\s+$/u', $part) === 1) {
              continue;
          }

          $parts[$index] = $decodeMojibakeChunk($part);
      }

      return trim(implode('', $parts));
  };

  $formatEmployee = function ($value) use ($normalizeActivityText) {
      $value = $normalizeActivityText($value);
      if ($value === '') {
          return '—';
      }
      if (preg_match('/^[a-z0-9_.-]+$/i', $value) === 1) {
          $value = str_replace(['_', '.', '-'], ' ', $value);
          $value = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
      }
      return $value;
  };

  $stageNameById = function ($id) use ($stages) {
      if (!$id) {
          return '—';
      }
      return $stages->firstWhere('id', (int) $id)?->name ?? ('#'.$id);
  };

  $callGroupKey = function ($activity) {
      $payload = is_array($activity->payload ?? null) ? $activity->payload : [];
      $callId = trim((string) ($payload['callid'] ?? $payload['call_id'] ?? $payload['callId'] ?? $payload['uuid'] ?? ''));

      return $callId !== '' ? 'callid:'.$callId : 'activity:'.$activity->id;
  };

  $callActivityGroups = $deal->activities
      ->filter(fn ($activity) => $activity->type === 'call')
      ->groupBy($callGroupKey);

  $renderedCallKeys = [];

  $hasDisplayValue = function ($value) {
      if ($value === null) {
          return false;
      }

      if (is_string($value)) {
          return trim($value) !== '';
      }

      if (is_array($value)) {
          return $value !== [];
      }

      return true;
  };

  $pickPayloadValue = function ($payloads, array $keys) use ($hasDisplayValue, $normalizeActivityText) {
      foreach ($payloads as $payload) {
          foreach ($keys as $key) {
              $value = data_get($payload, $key);
              if (!$hasDisplayValue($value)) {
                  continue;
              }

              return is_scalar($value) ? $normalizeActivityText((string) $value) : $value;
          }
      }

      return null;
  };

  $pickEmployeeFromPayloads = function ($payloads) use ($normalizeActivityText) {
      foreach ($payloads as $payload) {
          $employee = \App\Models\Deal::resolveCallEmployeeFromPayload($payload);
          if (trim((string) $employee) !== '') {
              return $normalizeActivityText((string) $employee);
          }
      }

      return null;
  };

  $pickIncomingPhoneSourceFromPayloads = function ($payloads) {
      foreach ($payloads as $payload) {
          $source = \App\Models\Deal::resolveIncomingPhoneSourceFromPayload($payload);
          if ($source) {
              return $source;
          }
      }

      return null;
  };

  $resolveCallTypeKey = function ($payloads) {
      $types = collect($payloads)
          ->map(fn ($payload) => strtolower(trim((string) ($payload['type'] ?? ''))))
          ->filter();

      if ($types->contains(fn ($type) => in_array($type, ['out', 'outgoing'], true))) {
          return 'out';
      }

      $statuses = collect($payloads)
          ->map(fn ($payload) => strtolower(trim((string) ($payload['status'] ?? ''))))
          ->filter();

      if (
          $types->contains(fn ($type) => in_array($type, ['missed'], true))
          || $statuses->contains(fn ($status) => in_array($status, ['missed', 'no_answer', 'not_answered', 'unanswered', 'busy', 'cancelled', 'canceled', 'failed'], true))
      ) {
          return 'missed';
      }

      return 'in';
  };

  $resolveCallTypeLabel = fn ($typeKey) => match ($typeKey) {
      'out' => 'Исходящий',
      'missed' => 'Пропущенный',
      default => 'Входящий',
  };

  $resolveCallStatusMeta = function ($payloads, $callTypeKey) use ($normalizeActivityText) {
      foreach ($payloads as $payload) {
          $status = trim((string) ($payload['status'] ?? ''));
          if ($status === '') {
              continue;
          }

          return match (strtolower($status)) {
              'success', 'ok', 'answered', 'connected' => ['label' => 'Успешно', 'badge' => 'success'],
              'busy' => ['label' => 'Занято', 'badge' => 'warning'],
              'failed' => ['label' => 'Ошибка', 'badge' => 'danger'],
              'cancelled', 'canceled' => ['label' => 'Отменён', 'badge' => 'secondary'],
              'missed', 'no_answer', 'not_answered', 'unanswered' => $callTypeKey === 'missed'
                  ? null
                  : ['label' => 'Пропущен', 'badge' => 'danger'],
              default => ['label' => $normalizeActivityText($status), 'badge' => 'light'],
          };
      }

      return null;
  };

  $transcriptStatusMeta = fn ($status) => match ((string) $status) {
      'done' => ['label' => 'Расшифровано', 'badge' => 'success'],
      'queued' => ['label' => 'В очереди', 'badge' => 'secondary'],
      'processing' => ['label' => 'Обработка', 'badge' => 'warning'],
      'failed' => ['label' => 'Ошибка расшифровки', 'badge' => 'danger'],
      default => null,
  };
@endphp

@section('content')
<div class="d-flex align-items-start justify-content-between mb-3 flex-wrap gap-3">
  <div>
    <div class="d-flex align-items-center gap-2 flex-wrap mb-2">
      {!! $dealSourceIconHtml !!}
      <h4 class="mb-0">
        {{ $dealTitle }} <span class="text-muted">#{{ $deal->id }}</span>
      </h4>
    </div>
    @if($dealLeadDisplayName !== '' && $dealLeadDisplayName !== $dealTitle)
      <div class="fw-semibold text-body-secondary mb-1">{{ $dealLeadDisplayName }}</div>
    @endif
    @if(!$deal->is_ready)
      <span class="badge text-bg-warning">Не заполнено</span>
    @endif
    <div class="text-muted small mt-1">
      Клиент: {{ $dealLeadDisplayName !== '' ? $dealLeadDisplayName : 'Без имени' }}
      @if($deal->contact?->phone) • {{ $deal->contact->phone }} @endif
      • Ответственный: {{ $deal->responsible?->name ?? '—' }}
      @if($deal->closed_at)
        <span class="badge text-bg-{{ $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : 'secondary') }}">
            {{ $deal->closed_result === 'won' ? 'Успешно закрыта' : ($deal->closed_result === 'lost' ? 'Отказ' : 'Закрыта') }}
          </span>
      @endif
    </div>
  </div>

  <form method="POST" action="{{ route('deals.stage', $deal) }}" class="d-flex">
    @csrf
    <select
      name="stage_id"
      class="form-select form-select-sm"
      style="min-width: 280px;"
      onchange="this.form.submit()"
      @disabled($deal->closed_at)
    >
      @foreach($stages as $s)
        <option value="{{ $s->id }}" @selected($deal->stage_id === $s->id)>{{ $s->name }}</option>
      @endforeach
    </select>
  </form>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">О сделке</div>
      <div class="card-body small">
        <div class="mb-1"><b>Стадия:</b> {{ $deal->stage?->name }}</div>
        <div class="mb-1">
          <b>Источник:</b>
          @if($dealSourceChatUrl)
            <a href="{{ $dealSourceChatUrl }}" class="{{ $dealSourceBadgeClass }} text-decoration-none" target="_blank" rel="noopener">Открыть {{ $dealSourceLabel }}</a>
          @else
            <span class="{{ $dealSourceBadgeClass }}">{{ $dealSourceLabel }}</span>
          @endif
        </div>
        @if($deal->incoming_phone_source_display)
          <div class="mb-1"><b>Источник номера:</b> {{ $deal->incoming_phone_source_display }}</div>
        @endif
        <div class="mb-1"><b>Клиент:</b> {{ $dealLeadDisplayName !== '' ? $dealLeadDisplayName : 'Без имени' }}</div>
        <div class="mb-1"><b>Создано:</b> {{ optional($deal->created_at)->format('d.m.Y H:i') }}</div>
        <div class="mb-1"><b>Сумма:</b> {{ $deal->amount ? number_format($deal->amount,2,',',' ') : '—' }} {{ $deal->currency ?? 'RUB' }}</div>
        <div class="mb-1"><b>Готовность помещения:</b> {{ $deal->readiness_status ?? '—' }}</div>

        @php
          $missing = collect($deal->missing_fields)->map(fn($f) => match($f) {
            'title' => 'название сделки',
            'amount' => 'сумма',
            'responsible' => 'ответственный',
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
            <button class="btn btn-sm btn-success">Успешно</button>
          </form>
          <form method="POST" action="{{ route('deals.close', $deal) }}" class="d-flex gap-2">
            @csrf
            <input type="hidden" name="result" value="lost">
            <input class="form-control form-control-sm" name="reason" placeholder="Причина отказа (необязательно)">
            <button class="btn btn-sm btn-danger">Отказ</button>
          </form>
        @endif
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Чаты</div>
      <div class="card-body">
        @if(($dealConversations->count() ?? 0) === 0)
          <div class="text-muted small">Пока нет связанных диалогов</div>
        @else
          <div class="list-group">
            @foreach($dealConversations as $chat)
              <a class="list-group-item list-group-item-action d-flex justify-content-between align-items-start {{ $chat['surface_class'] }}" href="{{ $chat['url'] }}">
                <div>
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    {!! $chat['source_icon_html'] !!}
                    <span class="fw-semibold">{{ $chat['lead_name'] }}</span>
                    @if($chat['chat_url'])
                      <a href="{{ $chat['chat_url'] }}" class="{{ $chat['badge_class'] }} text-decoration-none" target="_blank" rel="noopener">{{ $chat['source_label'] }}</a>
                    @else
                      <span class="{{ $chat['badge_class'] }}">{{ $chat['source_label'] }}</span>
                    @endif
                  </div>
                  <div class="text-muted small mt-1">{{ $chat['subtitle'] }}</div>
                  <div class="text-muted small mt-1">{{ $chat['body'] }}</div>
                </div>
                <div class="text-end">
                  <div class="text-muted small">{{ $chat['last_message_at'] }}</div>
                  @if(($chat['unread_count'] ?? 0) > 0)
                    <span class="badge text-bg-primary mt-1">{{ $chat['unread_count'] }}</span>
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
            <input name="title" class="form-control form-control-sm" placeholder="Напр.: связаться с клиентом" required>
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
            <select name="assigned_user_id" class="form-select form-select-sm">
              <option value="0">Всем</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}" @selected(($deal->responsible_user_id ?? auth()->id()) === $u->id)>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
          <button class="btn btn-sm btn-primary">Добавить дело</button>
        </form>

        @forelse($deal->tasks as $task)
          <div id="task-{{ $task->id }}" class="border rounded p-2 mb-2 bg-white {{ $editingTaskId === (int) $task->id ? 'border-primary' : '' }}">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <div class="fw-semibold">{{ $task->title }}</div>
                  @if($task->external_sync_label)
                    <span class="badge text-bg-{{ ($task->external_sync_status ?? '') === 'error' ? 'danger' : (($task->external_sync_status ?? '') === 'pending' ? 'warning' : 'secondary') }}">{{ $task->external_sync_label }}</span>
                  @endif
                </div>
                <div class="text-muted small">
                  Статус: <b>{{ $task->status }}</b>
                  @if($task->due_at) • до {{ $task->due_at->format('d.m.Y H:i') }} @endif
                  • Ответственный: <b>{{ $task->assignee_label }}</b>
                </div>
              </div>
              <div class="d-flex gap-2 flex-wrap justify-content-end">
                <a class="btn btn-sm btn-outline-primary" href="{{ $buildDealUrl(['edit_task' => $task->id]) }}#task-{{ $task->id }}">Редактировать</a>
                @if($task->status !== 'done')
                  <form method="POST" action="{{ route('tasks.complete', $task) }}">
                    @csrf
                    <button class="btn btn-sm btn-success">Выполнено</button>
                  </form>
                @endif
              </div>
            </div>
            @if($task->description)
              <div class="text-muted small mt-1">{{ $task->description }}</div>
            @endif
            @if(($task->external_sync_error ?? '') !== '')
              <div class="text-danger small mt-1">{{ $task->external_sync_error }}</div>
            @endif
            @if($editingTaskId === (int) $task->id)
              @include('tasks._edit_form', [
                'task' => $task,
                'users' => $users,
                'cancelUrl' => $buildDealUrl(['edit_task' => null]).'#task-'.$task->id,
              ])
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
      <div class="card-header fw-semibold">Лента (история)</div>
      <div class="card-body">
        @forelse($deal->activities as $a)
          @php
            $payload = is_array($a->payload ?? null) ? $a->payload : [];
            $callGroupKeyValue = $a->type === 'call' ? $callGroupKey($a) : null;
          @endphp
          @continue($a->type === 'call' && isset($renderedCallKeys[$callGroupKeyValue]))

          @php
            $actorName = $normalizeActivityText($a->author?->name ?? '');
            $activityBody = $normalizeActivityText($a->body ?? '');
            $callActivities = collect([$a]);
            $callid = $payload['callid'] ?? null;
            $recModel = null;
            $recUrl = null;
            $callTypeKey = null;
            $callTypeLabel = null;
            $callTypeBadge = 'success';
            $callStatusMeta = null;
            $callFields = [];
            $hiddenCallEvents = 0;

            if ($a->type === 'call') {
                $renderedCallKeys[$callGroupKeyValue] = true;
                $callActivities = $callActivityGroups[$callGroupKeyValue] ?? collect([$a]);
                $callPayloads = $callActivities
                    ->map(fn ($activity) => is_array($activity->payload ?? null) ? $activity->payload : [])
                    ->values();

                $callid = $pickPayloadValue($callPayloads, ['callid', 'call_id', 'callId', 'uuid', 'id']);
                $recModel = ($callid && isset($recordingsByCallid[$callid])) ? $recordingsByCallid[$callid] : null;
                $recUrl = $recModel?->recording_url ?? $pickPayloadValue($callPayloads, ['recording_url', 'record_url', 'recordUrl', 'recording', 'record', 'link', 'file_url', 'url']);
                $callTypeKey = $resolveCallTypeKey($callPayloads);
                $callTypeLabel = $resolveCallTypeLabel($callTypeKey);
                $callTypeBadge = match ($callTypeKey) {
                    'out' => 'primary',
                    'missed' => 'danger',
                    default => 'success',
                };
                $callStatusMeta = $resolveCallStatusMeta($callPayloads, $callTypeKey);
                $throughPhone = $pickPayloadValue($callPayloads, ['telnum', 'diversion', 'to', 'dst', 'number', 'did']);
                $clientPhone = $pickPayloadValue($callPayloads, ['phone', 'client_phone', 'phone_client', 'caller', 'callerid', 'caller_id']) ?? $deal->contact?->phone;
                $callEmployee = $pickEmployeeFromPayloads($callPayloads);
                $callNumberSource = $pickIncomingPhoneSourceFromPayloads($callPayloads);
                $startRaw = $pickPayloadValue($callPayloads, ['start']);
                $waitRaw = $pickPayloadValue($callPayloads, ['wait']);
                $durationRaw = $pickPayloadValue($callPayloads, ['duration']);
                $hiddenCallEvents = max(0, $callActivities->count() - 1);

                $callFields = array_values(array_filter([
                    [
                        'label' => 'Клиент',
                        'value' => $formatPhone($clientPhone),
                        'show' => $hasDisplayValue($clientPhone),
                    ],
                    [
                        'label' => 'Сотрудник',
                        'value' => $formatEmployee($callEmployee),
                        'show' => $hasDisplayValue($callEmployee),
                    ],
                    [
                        'label' => 'Через',
                        'value' => $formatPhone($throughPhone),
                        'show' => $hasDisplayValue($throughPhone),
                    ],
                    [
                        'label' => 'Источник номера',
                        'value' => $callNumberSource ? ($callNumberSource['label'].' - '.$formatPhone($callNumberSource['number'])) : '',
                        'show' => $callNumberSource !== null,
                    ],
                    [
                        'label' => 'Начало',
                        'value' => $formatCallMoment($startRaw),
                        'show' => $hasDisplayValue($startRaw),
                    ],
                    [
                        'label' => 'Ожидание',
                        'value' => $formatDuration($waitRaw),
                        'show' => $hasDisplayValue($waitRaw),
                    ],
                    [
                        'label' => 'Длительность',
                        'value' => $formatDuration($durationRaw),
                        'show' => $hasDisplayValue($durationRaw),
                    ],
                ], fn ($field) => $field['show']));
            }
          @endphp

          <div class="activity-item border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
              <div>
                <div class="fw-semibold">{{ $a->type_label }}</div>
                @if($a->type === 'stage_changed')
                  <div class="activity-meta mt-1">Изменил: {{ $actorName !== '' ? $actorName : 'Система' }}</div>
                  <div class="small mt-1">{{ $stageNameById($payload['from_stage_id'] ?? null) }} → <b>{{ $stageNameById($payload['to_stage_id'] ?? null) }}</b></div>
                @elseif($actorName !== '')
                  <div class="activity-meta mt-1">Автор: {{ $actorName }}</div>
                @endif
              </div>
              <div class="text-muted small">{{ optional($a->created_at)->format('d.m.Y H:i') }}</div>
            </div>

            @if($a->type === 'call')
              <div class="activity-call-card">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge text-bg-{{ $callTypeBadge }}">{{ $callTypeLabel }}</span>
                    @if($callStatusMeta)
                      <span class="badge text-bg-{{ $callStatusMeta['badge'] }}">{{ $callStatusMeta['label'] }}</span>
                    @endif
                    @if($hiddenCallEvents > 0)
                      <span class="text-muted small">объединено событий: {{ $callActivities->count() }}</span>
                    @endif
                  </div>
                  @if($callid)
                    <span class="text-muted small">callid: {{ $callid }}</span>
                  @endif
                </div>

                @if($callFields !== [])
                  <div class="activity-call-grid">
                    @foreach($callFields as $field)
                      <div class="activity-call-field">
                        <span class="activity-call-label">{{ $field['label'] }}</span>
                        <span class="activity-call-value">{{ $field['value'] }}</span>
                      </div>
                    @endforeach
                  </div>
                @else
                  <div class="text-muted small">Детали звонка не переданы провайдером.</div>
                @endif

                @if(is_string($recUrl) && $recUrl !== '')
                  <div class="activity-call-audio mt-3">
                    <audio controls preload="none" src="{{ $recUrl }}"></audio>
                    <div class="small mt-2 d-flex align-items-center gap-2 flex-wrap">
                      <a href="{{ $recUrl }}" target="_blank" rel="noopener">Открыть запись</a>
                      @if($recModel)
                        @php
                          $st = $recModel->transcript_status;
                          $transcriptMeta = $transcriptStatusMeta($st);
                        @endphp

                        @if($transcriptMeta)
                          <span class="badge text-bg-{{ $transcriptMeta['badge'] }}">{{ $transcriptMeta['label'] }}</span>
                        @endif

                        @if($st === 'done' && $recModel->transcript_text)
                          <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#tr-{{ $recModel->id }}">
                            Расшифровка
                          </button>
                          <div class="collapse mt-2" id="tr-{{ $recModel->id }}">
                            <div class="border rounded p-2 bg-white small" style="white-space: pre-wrap;">{{ $recModel->transcript_text }}</div>
                          </div>
                        @elseif(in_array($st, ['none','failed'], true))
                          <form method="POST" action="{{ route('recordings.transcribe', $recModel) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">Расшифровать</button>
                          </form>
                          @if($st === 'failed' && $recModel->transcript_error)
                            <div class="text-danger small">Ошибка: {{ $recModel->transcript_error }}</div>
                          @endif
                        @endif
                      @endif
                    </div>
                  </div>
                @endif
              </div>
            @else
              <div class="small mt-2">{!! nl2br(e($activityBody)) !!}</div>
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
