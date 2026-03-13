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
  $formatPhone = function ($value) {
      $digits = preg_replace('/\D+/', '', (string) $value);
      if (!$digits) {
          return 'вЂ”';
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
          return 'вЂ”';
      }
      $value = (int) $seconds;
      return sprintf('%02d:%02d', intdiv($value, 60), $value % 60);
  };

  $formatCallMoment = function ($raw) {
      $raw = trim((string) $raw);
      if ($raw === '') {
          return 'вЂ”';
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

  $formatEmployee = function ($value) {
      $value = trim((string) $value);
      if ($value === '') {
          return 'вЂ”';
      }
      if (preg_match('/^[a-z0-9_.-]+$/i', $value) === 1) {
          $value = str_replace(['_', '.', '-'], ' ', $value);
          $value = mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
      }
      return $value;
  };

  $stageNameById = function ($id) use ($stages) {
      if (!$id) {
          return 'вЂ”';
      }
      return $stages->firstWhere('id', (int) $id)?->name ?? ('#'.$id);
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
      <span class="badge text-bg-warning">РќРµ Р·Р°РїРѕР»РЅРµРЅРѕ</span>
    @endif
    <div class="text-muted small mt-1">
      РљР»РёРµРЅС‚: {{ $dealLeadDisplayName !== '' ? $dealLeadDisplayName : 'Р‘РµР· РёРјРµРЅРё' }}
      @if($deal->contact?->phone) вЂў {{ $deal->contact->phone }} @endif
      вЂў РћС‚РІРµС‚СЃС‚РІРµРЅРЅС‹Р№: {{ $deal->responsible?->name ?? 'вЂ”' }}
      @if($deal->closed_at)
        <span class="badge text-bg-{{ $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : 'secondary') }}">
            {{ $deal->closed_result === 'won' ? 'РЈСЃРїРµС€РЅРѕ Р·Р°РєСЂС‹С‚Р°' : ($deal->closed_result === 'lost' ? 'РћС‚РєР°Р·' : 'Р—Р°РєСЂС‹С‚Р°') }}
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
    <button class="btn btn-sm btn-outline-primary">РР·РјРµРЅРёС‚СЊ СЃС‚Р°РґРёСЋ</button>
  </form>
</div>

<div class="row g-3">
  <div class="col-lg-4">
    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Рћ СЃРґРµР»РєРµ</div>
      <div class="card-body small">
        <div class="mb-1"><b>РЎС‚Р°РґРёСЏ:</b> {{ $deal->stage?->name }}</div>
        <div class="mb-1">
          <b>РСЃС‚РѕС‡РЅРёРє:</b>
          @if($dealSourceChatUrl)
            <a href="{{ $dealSourceChatUrl }}" class="{{ $dealSourceBadgeClass }} text-decoration-none" target="_blank" rel="noopener">РћС‚РєСЂС‹С‚СЊ {{ $dealSourceLabel }}</a>
          @else
            <span class="{{ $dealSourceBadgeClass }}">{{ $dealSourceLabel }}</span>
          @endif
        </div>
        @if($deal->incoming_phone_source_display)
          <div class="mb-1"><b>Источник номера:</b> {{ $deal->incoming_phone_source_display }}</div>
        @endif
        <div class="mb-1"><b>РљР»РёРµРЅС‚:</b> {{ $dealLeadDisplayName !== '' ? $dealLeadDisplayName : 'Р‘РµР· РёРјРµРЅРё' }}</div>
        <div class="mb-1"><b>РЎРѕР·РґР°РЅРѕ:</b> {{ optional($deal->created_at)->format('d.m.Y H:i') }}</div>
        <div class="mb-1"><b>РЎСѓРјРјР°:</b> {{ $deal->amount ? number_format($deal->amount,2,',',' ') : 'вЂ”' }} {{ $deal->currency ?? 'RUB' }}</div>
        <div class="mb-1"><b>Р“РѕС‚РѕРІРЅРѕСЃС‚СЊ РїРѕРјРµС‰РµРЅРёСЏ:</b> {{ $deal->readiness_status ?? 'вЂ”' }}</div>

        @php
          $missing = collect($deal->missing_fields)->map(fn($f) => match($f) {
            'title' => 'РЅР°Р·РІР°РЅРёРµ СЃРґРµР»РєРё',
            'amount' => 'СЃСѓРјРјР°',
            'responsible' => 'РѕС‚РІРµС‚СЃС‚РІРµРЅРЅС‹Р№',
            default => $f,
          })->values()->all();
        @endphp

        @if(!$deal->is_ready)
          <div class="alert alert-warning mt-2 mb-2 py-2 small">
            Р РµРєРѕРјРµРЅРґСѓРµРј Р·Р°РїРѕР»РЅРёС‚СЊ: <b>{{ implode(', ', $missing) }}</b>.
          </div>
        @endif

        <hr class="my-2">
        <div class="fw-semibold mb-2">Р—Р°РїРѕР»РЅРµРЅРёРµ / СЂРµРґР°РєС‚РёСЂРѕРІР°РЅРёРµ</div>
        <form method="POST" action="{{ route('deals.update', $deal) }}" class="row g-2">
          @csrf
          @method('PATCH')
          <div class="col-12">
            <label class="form-label mb-1">РќР°Р·РІР°РЅРёРµ СЃРґРµР»РєРё</label>
            <input name="title" class="form-control form-control-sm" value="{{ $deal->title }}" required>
          </div>
          <div class="col-7">
            <label class="form-label mb-1">РћС‚РІРµС‚СЃС‚РІРµРЅРЅС‹Р№</label>
            <select name="responsible_user_id" class="form-select form-select-sm" required>
              @foreach($users as $u)
                <option value="{{ $u->id }}" @selected($deal->responsible_user_id === $u->id)>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="col-5">
            <label class="form-label mb-1">РЎСѓРјРјР° (RUB)</label>
            <input name="amount" type="number" step="0.01" min="0.01" class="form-control form-control-sm" value="{{ $deal->amount ?? '' }}" required>
          </div>
          <div class="col-12">
            <button class="btn btn-sm btn-primary">РЎРѕС…СЂР°РЅРёС‚СЊ</button>
          </div>
        </form>
        @if($deal->closed_at)
          <div class="mt-2">
            <div class="mb-1"><b>Р—Р°РєСЂС‹С‚Р°:</b> {{ $deal->closed_at->format('d.m.Y H:i') }}</div>
            <div class="mb-1"><b>Р РµР·СѓР»СЊС‚Р°С‚:</b> {{ $deal->closed_result }}</div>
            @if($deal->closed_reason)
              <div class="mb-1"><b>РџСЂРёС‡РёРЅР°:</b> {{ $deal->closed_reason }}</div>
            @endif
          </div>
        @else
          <hr class="my-2">
          <div class="fw-semibold mb-2">Р—Р°РєСЂС‹С‚РёРµ СЃРґРµР»РєРё</div>
          <form method="POST" action="{{ route('deals.close', $deal) }}" class="d-flex gap-2 mb-2">
            @csrf
            <input type="hidden" name="result" value="won">
            <input class="form-control form-control-sm" name="reason" placeholder="РљРѕРјРјРµРЅС‚Р°СЂРёР№ (РЅРµРѕР±СЏР·Р°С‚РµР»СЊРЅРѕ)">
            <button class="btn btn-sm btn-success">РЈСЃРїРµС€РЅРѕ</button>
          </form>
          <form method="POST" action="{{ route('deals.close', $deal) }}" class="d-flex gap-2">
            @csrf
            <input type="hidden" name="result" value="lost">
            <input class="form-control form-control-sm" name="reason" placeholder="РџСЂРёС‡РёРЅР° РѕС‚РєР°Р·Р° (РЅРµРѕР±СЏР·Р°С‚РµР»СЊРЅРѕ)">
            <button class="btn btn-sm btn-danger">РћС‚РєР°Р·</button>
          </form>
        @endif
      </div>
    </div>

    <div class="card shadow-sm mb-3">
      <div class="card-header fw-semibold">Р§Р°С‚С‹</div>
      <div class="card-body">
        @if(($dealConversations->count() ?? 0) === 0)
          <div class="text-muted small">РџРѕРєР° РЅРµС‚ СЃРІСЏР·Р°РЅРЅС‹С… РґРёР°Р»РѕРіРѕРІ</div>
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
      <div class="card-header fw-semibold">Р—Р°РґР°С‡Рё / Р”РµР»Р°</div>
      <div class="card-body">
        <form method="POST" action="{{ route('tasks.store', $deal) }}" class="mb-3">
          @csrf
          <div class="mb-2">
            <input name="title" class="form-control form-control-sm" placeholder="РќР°РїСЂ.: СЃРІСЏР·Р°С‚СЊСЃСЏ СЃ РєР»РёРµРЅС‚РѕРј" required>
          </div>
          <div class="mb-2">
            <textarea name="description" class="form-control form-control-sm" rows="2" placeholder="РљРѕРјРјРµРЅС‚Р°СЂРёР№ (РЅРµРѕР±СЏР·Р°С‚РµР»СЊРЅРѕ)"></textarea>
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">РљРѕРіРґР° РЅР°РїРѕРјРЅРёС‚СЊ</label>
            <input name="due_at" type="datetime-local" class="form-control form-control-sm" required>
          </div>
          <div class="mb-2">
            <label class="form-label small mb-1">РљРѕРјСѓ РЅР°Р·РЅР°С‡РёС‚СЊ</label>
            <select name="assigned_user_id" class="form-select form-select-sm">
              <option value="0">Р’СЃРµРј</option>
              @foreach($users as $u)
                <option value="{{ $u->id }}" @selected(($deal->responsible_user_id ?? auth()->id()) === $u->id)>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
          <button class="btn btn-sm btn-primary">Р”РѕР±Р°РІРёС‚СЊ РґРµР»Рѕ</button>
        </form>

        @forelse($deal->tasks as $task)
          <div class="border rounded p-2 mb-2 bg-white">
            <div class="d-flex justify-content-between align-items-start">
              <div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                  <div class="fw-semibold">{{ $task->title }}</div>
                  @if($task->external_sync_label)
                    <span class="badge text-bg-{{ ($task->external_sync_status ?? '') === 'error' ? 'danger' : (($task->external_sync_status ?? '') === 'pending' ? 'warning' : 'secondary') }}">{{ $task->external_sync_label }}</span>
                  @endif
                </div>
                <div class="text-muted small">
                  РЎС‚Р°С‚СѓСЃ: <b>{{ $task->status }}</b>
                  @if($task->due_at) вЂў РґРѕ {{ $task->due_at->format('d.m.Y H:i') }} @endif
                  вЂў РћС‚РІРµС‚СЃС‚РІРµРЅРЅС‹Р№: <b>{{ $task->assignee_label }}</b>
                </div>
              </div>
              @if($task->status !== 'done')
                <form method="POST" action="{{ route('tasks.complete', $task) }}">
                  @csrf
                  <button class="btn btn-sm btn-success">Р’С‹РїРѕР»РЅРµРЅРѕ</button>
                </form>
              @endif
            </div>
            @if($task->description)
              <div class="text-muted small mt-1">{{ $task->description }}</div>
            @endif
            @if(($task->external_sync_error ?? '') !== '')
              <div class="text-danger small mt-1">{{ $task->external_sync_error }}</div>
            @endif
          </div>
        @empty
          <div class="text-muted small">Р”РµР»Р° РѕС‚СЃСѓС‚СЃС‚РІСѓСЋС‚</div>
        @endforelse
      </div>
    </div>
  </div>

  <div class="col-lg-8">
    <div class="card shadow-sm">
      <div class="card-header fw-semibold">Р›РµРЅС‚Р° (РёСЃС‚РѕСЂРёСЏ)</div>
      <div class="card-body">
        @forelse($deal->activities as $a)
          @php
            $payload = is_array($a->payload ?? null) ? $a->payload : [];
            $callid = $payload['callid'] ?? null;
            $recModel = ($callid && isset($recordingsByCallid[$callid])) ? $recordingsByCallid[$callid] : null;
            $recUrl = $recModel?->recording_url ?? ($payload['recording_url'] ?? null);
            $actorName = trim((string) ($a->author?->name ?? ''));
          @endphp

          <div class="activity-item border-bottom pb-3 mb-3">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
              <div>
                <div class="fw-semibold">{{ $a->type_label }}</div>
                @if($a->type === 'stage_changed')
                  <div class="activity-meta mt-1">РР·РјРµРЅРёР»: {{ $actorName !== '' ? $actorName : 'РЎРёСЃС‚РµРјР°' }}</div>
                  <div class="small mt-1">{{ $stageNameById($payload['from_stage_id'] ?? null) }} в†’ <b>{{ $stageNameById($payload['to_stage_id'] ?? null) }}</b></div>
                @elseif($actorName !== '')
                  <div class="activity-meta mt-1">РђРІС‚РѕСЂ: {{ $actorName }}</div>
                @endif
              </div>
              <div class="text-muted small">{{ optional($a->created_at)->format('d.m.Y H:i') }}</div>
            </div>

            @if($a->type === 'call')
              @php
                $callType = trim((string) ($payload['type'] ?? ''));
                $callStatus = trim((string) ($payload['status'] ?? ''));
                $throughPhone = $payload['telnum'] ?? $payload['diversion'] ?? null;
                $clientPhone = $payload['phone'] ?? $payload['client_phone'] ?? $payload['phone_client'] ?? $payload['caller'] ?? $deal->contact?->phone;
                $callNumberSource = \App\Models\Deal::resolveIncomingPhoneSourceFromPayload($payload);
              @endphp
              <div class="activity-call-card">
                <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                  <div class="d-flex align-items-center gap-2 flex-wrap">
                    <span class="badge text-bg-success">{{ $callType === 'out' ? 'РСЃС…РѕРґСЏС‰РёР№' : ($callType === 'missed' ? 'РџСЂРѕРїСѓС‰РµРЅРЅС‹Р№' : 'Р’С…РѕРґСЏС‰РёР№') }}</span>
                    @if($callStatus !== '')
                      <span class="badge text-bg-light">{{ $callStatus }}</span>
                    @endif
                  </div>
                  @if($callid)
                    <span class="text-muted small">callid: {{ $callid }}</span>
                  @endif
                </div>

                <div class="activity-call-grid">
                  <div class="activity-call-field">
                    <span class="activity-call-label">РљР»РёРµРЅС‚</span>
                    <span class="activity-call-value">{{ $formatPhone($clientPhone) }}</span>
                  </div>
                  <div class="activity-call-field">
                    <span class="activity-call-label">РЎРѕС‚СЂСѓРґРЅРёРє</span>
                    <span class="activity-call-value">{{ $formatEmployee($payload['user'] ?? '') }}</span>
                  </div>
                  <div class="activity-call-field">
                    <span class="activity-call-label">Р§РµСЂРµР·</span>
                    <span class="activity-call-value">{{ $formatPhone($throughPhone) }}</span>
                  </div>
                  @if($callNumberSource)
                    <div class="activity-call-field">
                      <span class="activity-call-label">Источник номера</span>
                      <span class="activity-call-value">{{ $callNumberSource['label'] }} - {{ $formatPhone($callNumberSource['number']) }}</span>
                    </div>
                  @endif
                  <div class="activity-call-field">
                    <span class="activity-call-label">РќР°С‡Р°Р»Рѕ</span>
                    <span class="activity-call-value">{{ $formatCallMoment($payload['start'] ?? '') }}</span>
                  </div>
                  <div class="activity-call-field">
                    <span class="activity-call-label">РћР¶РёРґР°РЅРёРµ</span>
                    <span class="activity-call-value">{{ $formatDuration($payload['wait'] ?? null) }}</span>
                  </div>
                  <div class="activity-call-field">
                    <span class="activity-call-label">Р”Р»РёС‚РµР»СЊРЅРѕСЃС‚СЊ</span>
                    <span class="activity-call-value">{{ $formatDuration($payload['duration'] ?? null) }}</span>
                  </div>
                </div>

                @if(is_string($recUrl) && $recUrl !== '')
                  <div class="activity-call-audio mt-3">
                    <audio controls preload="none" src="{{ $recUrl }}"></audio>
                    <div class="small mt-2 d-flex align-items-center gap-2 flex-wrap">
                      <a href="{{ $recUrl }}" target="_blank" rel="noopener">РћС‚РєСЂС‹С‚СЊ Р·Р°РїРёСЃСЊ</a>
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
                            Р Р°СЃС€РёС„СЂРѕРІРєР°
                          </button>
                          <div class="collapse mt-2" id="tr-{{ $recModel->id }}">
                            <div class="border rounded p-2 bg-white small" style="white-space: pre-wrap;">{{ $recModel->transcript_text }}</div>
                          </div>
                        @elseif(in_array($st, ['none','failed'], true))
                          <form method="POST" action="{{ route('recordings.transcribe', $recModel) }}">
                            @csrf
                            <button class="btn btn-sm btn-outline-primary">Р Р°СЃС€РёС„СЂРѕРІР°С‚СЊ</button>
                          </form>
                          @if($st === 'failed' && $recModel->transcript_error)
                            <div class="text-danger small">РћС€РёР±РєР°: {{ $recModel->transcript_error }}</div>
                          @endif
                        @endif
                      @else
                        <span class="text-muted small">(РЅРµС‚ Р·Р°РїРёСЃРё РІ Р‘Р”)</span>
                      @endif
                    </div>
                  </div>
                @endif
              </div>
            @else
              <div class="small mt-2">{!! nl2br(e($a->body ?? '')) !!}</div>
            @endif
          </div>
        @empty
          <div class="text-muted small">РџРѕРєР° РЅРµС‚ СЃРѕР±С‹С‚РёР№</div>
        @endforelse
      </div>
    </div>
  </div>
</div>
@endsection
