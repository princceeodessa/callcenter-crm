{{-- Карточка сделки в стиле Nordika (редизайн колл-центра, этап 2; окно сделки из прототипа дизайнера — здесь страницей).
     Формы и маршруты — прежние (deals.stage, deals.update, deals.close, deals.voice-note.store, tasks.*); лента — $prepareActivity.
     Кроссовки (28.10.2026) открывают эту же карточку в своём каркасе — с блоком «Склад · что продаём» (deals._sale_nd). --}}
@php
  $ndUi = \App\Support\Ui\Nordika::class;
  $ndSrc = $ndUi::sourceKey($deal);
  $ndPhone = $ndUi::phone($deal->contact?->phone);
  $ndTel = preg_replace('/\D+/', '', (string) $deal->contact?->phone);
  if (strlen($ndTel) === 11 && $ndTel[0] === '8') {
      $ndTel = '7'.substr($ndTel, 1);
  }
  $ndChat = collect($dealConversations)->first();
  $ndMeasureStage = $stages->first(fn ($s) => str_contains(mb_strtolower($s->name), 'замер назнач'));
  $ndNtStageId = !empty($nonTargetCategories) && $nonTargetStageId ? (int) $nonTargetStageId : null;
  $ndNtLabel = \App\Support\Deals\NonTargetCategory::label($deal->non_target_category);
  $ndClosedTone = ['success' => 'green', 'danger' => 'red', 'warning' => 'amber'][$dealClosedResult['badge']] ?? 'grey';
  $ndMissing = collect($deal->missing_fields)->map(fn ($f) => match ($f) {
      'title' => 'название сделки',
      'responsible' => 'ответственный',
      default => $f,
  })->values()->all();
  $ndCloseResult = old('result', $errors->has('category') ? 'extra_non_target' : 'won');
  $ndPill = fn ($badge) => ['success' => 'green', 'primary' => 'blue', 'danger' => 'red', 'warning' => 'amber'][$badge] ?? 'grey';
  $ndSvg = fn (string $paths, string $cls = 'nd-icon nd-sm') => '<svg class="'.$cls.'" viewBox="0 0 24 24">'.$paths.'</svg>';
  $ndPhonePath = '<path d="M5 4h3l1.5 5-2 1.5a12 12 0 006 6l1.5-2 5 1.5V20a2 2 0 01-2 2A16 16 0 013 6a2 2 0 012-2z"/>';
@endphp

<div class="nd-panel nd-deal">
  <div class="nd-m-head">
    <div class="nd-deal-headtext">
      <div class="nd-deal-chips">
        {!! !empty($isSneakerSpace) ? \App\Support\Ui\Sneakers::srcChip($deal->manual_source) : $ndUi::sourceChip($ndSrc) !!}
        @if($dealSourceChatUrl)
          <a class="nd-tag" href="{{ $dealSourceChatUrl }}" target="_blank" rel="noopener">Открыть {{ $dealSourceLabel }} ↗</a>
        @endif
        @if(!$deal->is_ready)
          <span class="nd-tag nd-tone-amber">Не заполнено</span>
        @endif
        @if($deal->closed_at)
          <span class="nd-tag nd-tone-{{ $ndClosedTone }}">{{ $dealClosedResult['label'] }}</span>
        @endif
        @if($ndNtLabel)
          <span class="nd-tag nd-tone-amber">Нецелевая: {{ $ndNtLabel }}</span>
        @endif
      </div>
      <div class="nd-mt">@if($dealLeadDisplayName !== '' && $dealLeadDisplayName !== $dealTitle)<span class="nd-deal-lead">{{ $dealLeadDisplayName }} · </span>@endif{{ $dealTitle }}</div>
      <div class="nd-msub"><span>#{{ $deal->id }}</span>·<span>создана {{ optional($deal->created_at)->format('d.m.Y H:i') }}</span>·<span>{{ $deal->stage?->name }}</span></div>
    </div>
    @if($canUseCeilingProjects)
      <a class="nd-btn nd-sm nd-deal-project" href="{{ route('deals.ceiling-project.show', $deal) }}">{!! $ndSvg(\App\Support\Ui\Nordika::ICONS['project']) !!}{{ $deal->ceilingProject ? 'Проект потолка' : 'Создать проект потолка' }}</a>
    @endif
  </div>

  <div class="nd-m-grid nd-deal-grid">
    <div class="nd-m-main">
      <div class="nd-actrow">
        @if($ndPhone)
          <a class="nd-act nd-green" href="tel:+{{ $ndTel }}">{!! $ndSvg($ndPhonePath) !!}Позвонить</a>
        @endif
        @if($ndChat)
          <a class="nd-act" href="{{ $ndChat['url'] }}">{!! $ndSvg(\App\Support\Ui\Nordika::ICONS['chats']) !!}Написать</a>
        @endif
        @if(!$deal->closed_at && $ndMeasureStage && (int) $deal->stage_id !== (int) $ndMeasureStage->id)
          <button type="button" class="nd-act" data-stage-id="{{ $ndMeasureStage->id }}">{!! $ndSvg(\App\Support\Ui\Nordika::ICONS['calendar']) !!}Записать на замер</button>
        @endif
        <a class="nd-act" href="#ndTasks">{!! $ndSvg(\App\Support\Ui\Nordika::ICONS['tasks']) !!}Дело</a>
        @if(!$deal->closed_at)
          <a class="nd-act" href="#ndClose" data-nd-close="lost">{!! $ndSvg('<path d="M18 6L6 18M6 6l12 12"/>') !!}Отказ</a>
        @endif
      </div>

      <div class="nd-field">
        <label>Контакт</label>
        <div class="nd-deal-contact">
          @if($ndPhone)
            {!! $ndSvg($ndPhonePath) !!}{{ $ndPhone }}
          @else
            <span class="nd-faint">Телефон не указан</span>
          @endif
        </div>
        <div class="nd-deal-contact-sub">
          {{ $dealLeadDisplayName !== '' ? $dealLeadDisplayName : 'Без имени' }}
          @if($deal->incoming_phone_source_display) · номер: {{ $deal->incoming_phone_source_display }} @endif
        </div>
      </div>

      @if(!empty($isSneakerSpace))
        @include('deals._sale_nd')
      @endif

      <div class="nd-lbl">Голосовое сообщение в ленту</div>
      <form class="nd-composer nd-deal-voice" method="POST" action="{{ route('deals.voice-note.store', $deal) }}" enctype="multipart/form-data">
        @csrf
        <label class="nd-file">
          <input type="file" name="voice_note" accept="audio/*,.mp3,.wav,.ogg,.oga,.webm,.m4a,.aac,.amr,.3gp,.mp4" required aria-label="Аудиофайл">
          <span class="nd-file-btn"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="3" width="6" height="11" rx="3"/><path d="M5 11a7 7 0 0014 0M12 18v3"/></svg>Выбрать запись</span>
          <span class="nd-file-name" data-empty="Аудио не выбрано">Аудио не выбрано</span>
        </label>
        <input name="voice_note_comment" maxlength="1000" placeholder="Комментарий к голосовому (необязательно)">
        <button class="nd-btn nd-primary">Добавить</button>
      </form>

      <div class="nd-deal-sec" id="ndTasks">
        <div class="nd-lbl">Дела по сделке</div>
        @foreach($deal->tasks as $task)
          <div id="task-{{ $task->id }}" @class(['nd-deal-task', 'nd-done' => $task->status === 'done', 'nd-editing' => $editingTaskId === (int) $task->id])>
            <div class="nd-deal-task-row">
              <span class="nd-deal-task-check">{!! $task->status === 'done' ? $ndSvg('<path d="M20 6L9 17l-5-5"/>') : '' !!}</span>
              <div class="nd-deal-task-text">
                <div class="nd-deal-task-title">
                  {{ $task->title }}
                  @if($task->external_sync_label)
                    <span class="nd-tag nd-tone-{{ ($task->external_sync_status ?? '') === 'error' ? 'red' : (($task->external_sync_status ?? '') === 'pending' ? 'amber' : 'grey') }}">{{ $task->external_sync_label }}</span>
                  @endif
                </div>
                <div class="nd-deal-task-meta">
                  {{ $task->status === 'done' ? 'Выполнено' : 'Открыто' }}
                  @if($task->due_at) · до {{ $task->due_at->format('d.m.Y H:i') }} @endif
                  · {{ $task->assignee_label }}
                </div>
                @if($task->description)
                  <div class="nd-deal-task-desc">{{ $task->description }}</div>
                @endif
                @if(($task->external_sync_error ?? '') !== '')
                  <div class="nd-deal-err">{{ $task->external_sync_error }}</div>
                @endif
              </div>
              <div class="nd-deal-task-btns">
                <a class="nd-btn nd-sm" href="{{ $buildDealUrl(['edit_task' => $task->id]) }}#task-{{ $task->id }}">Изменить</a>
                @if($task->status !== 'done')
                  <form method="POST" action="{{ route('tasks.complete', $task) }}">
                    @csrf
                    <button class="nd-btn nd-sm nd-primary">Выполнено</button>
                  </form>
                @endif
              </div>
            </div>
            @if($editingTaskId === (int) $task->id)
              @include('tasks._edit_form', [
                'task' => $task,
                'users' => $users,
                'canAssignToAll' => $canAssignToAll ?? false,
                'assignAllLabel' => $assignAllLabel ?? 'Всем',
                'cancelUrl' => $buildDealUrl(['edit_task' => null]).'#task-'.$task->id,
              ])
            @endif
          </div>
        @endforeach
        @if($deal->tasks->isEmpty())
          <div class="nd-faint nd-deal-small">Дел по сделке нет</div>
        @endif
        <details class="nd-deal-addtask" @if($errors->hasAny(['title', 'due_at', 'assigned_user_id'])) open @endif>
          <summary>+ Добавить дело</summary>
          <form method="POST" action="{{ route('tasks.store', $deal) }}" class="nd-deal-form">
            @csrf
            <div class="nd-field"><label>Что сделать</label><input name="title" placeholder="Напр.: связаться с клиентом" required></div>
            <div class="nd-deal-form-2">
              <div class="nd-field"><label>Когда напомнить</label><input name="due_at" type="datetime-local" required></div>
              <div class="nd-field">
                <label>Кому</label>
                <select name="assigned_user_id">
                  @if($canAssignToAll ?? false)
                    <option value="0">{{ $assignAllLabel ?? 'Всем' }}</option>
                  @endif
                  @foreach($users as $u)
                    <option value="{{ $u->id }}" @selected(($deal->responsible_user_id ?? auth()->id()) === $u->id)>{{ $u->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="nd-field"><label>Комментарий</label><textarea name="description" rows="2" placeholder="Необязательно"></textarea></div>
            <button class="nd-btn nd-sm nd-primary">Добавить дело</button>
          </form>
        </details>
      </div>

      <div class="nd-lbl">История</div>
      <div class="nd-tl nd-deal-tl">
        @forelse($deal->activities as $a)
          @php
            $feedItem = $prepareActivity($a);
          @endphp
          @continue($feedItem === null)
          @php
            extract($feedItem);
            $ndTypeLabel = $a->type === 'lead_form' && ($payload['provider'] ?? '') === 'hands' ? 'Заказ Лемана ПРО' : $a->type_label;
            $ndDot = match (true) {
                $a->type === 'call' => $callTypeKey === 'missed' ? 'var(--red)' : ($callTypeKey === 'out' ? 'var(--blue)' : 'var(--call)'),
                $a->type === 'stage_changed' => 'var(--blue)',
                $a->type === 'lead_form' => 'var(--lemana)',
                $a->type === 'voice_note' => 'var(--amber)',
                $a->type === 'deal_closed' => '#8a5cf6',
                str_starts_with((string) $a->type, 'message') => 'var(--site)',
                default => 'var(--faint)',
            };
          @endphp
          <div class="nd-tl-i" style="--dot:{{ $ndDot }}">
            <div class="nd-tl-head">
              <div class="nd-tlt">{{ $ndTypeLabel }}</div>
              <div class="nd-tl-time">{{ optional($a->created_at)->format('d.m.Y H:i') }}</div>
            </div>
            @if($a->type === 'stage_changed')
              <div class="nd-tlm">{{ $stageNameById($payload['from_stage_id'] ?? null) }} → <b>{{ $stageNameById($payload['to_stage_id'] ?? null) }}</b> · {{ $actorName !== '' ? $actorName : 'Система' }}</div>
            @elseif($actorName !== '')
              <div class="nd-tlm">{{ $actorName }}</div>
            @endif

            @if($a->type === 'call')
              <div class="nd-call">
                <div class="nd-call-top">
                  <span class="nd-tag nd-tone-{{ $ndPill($callTypeBadge) }}">{{ $callTypeLabel }}</span>
                  @if($callStatusMeta)
                    <span class="nd-tag nd-tone-{{ $ndPill($callStatusMeta['badge']) }}">{{ $callStatusMeta['label'] }}</span>
                  @endif
                  @if($hiddenCallEvents > 0)
                    <span class="nd-faint">объединено событий: {{ $callActivities->count() }}</span>
                  @endif
                  @if($callid)
                    <span class="nd-call-id">callid: {{ $callid }}</span>
                  @endif
                </div>
                @if($callFields !== [])
                  <div class="nd-call-grid">
                    @foreach($callFields as $field)
                      <div><div class="nd-k">{{ $field['label'] }}</div><div class="nd-v">{{ $field['value'] }}</div></div>
                    @endforeach
                  </div>
                @else
                  <div class="nd-faint">Детали звонка не переданы провайдером.</div>
                @endif
                @if(is_string($recUrl) && $recUrl !== '')
                  <audio controls preload="none" src="{{ $recUrl }}"></audio>
                  <div class="nd-call-links">
                    <a href="{{ $recUrl }}" target="_blank" rel="noopener">Открыть запись</a>
                    @if($recModel)
                      @php
                        $st = $recModel->transcript_status;
                        $transcriptMeta = $transcriptStatusMeta($st);
                      @endphp
                      @if($transcriptMeta)
                        <span class="nd-tag nd-tone-{{ $ndPill($transcriptMeta['badge']) }}">{{ $transcriptMeta['label'] }}</span>
                      @endif
                      @if($st === 'done' && $recModel->transcript_text)
                        <details class="nd-transcript">
                          <summary>Расшифровка</summary>
                          <div>{{ $recModel->transcript_text }}</div>
                        </details>
                      @elseif(in_array($st, ['none','failed'], true))
                        <form method="POST" action="{{ route('recordings.transcribe', $recModel) }}">
                          @csrf
                          <button class="nd-btn nd-sm">Расшифровать</button>
                        </form>
                        @if($st === 'failed' && $recModel->transcript_error)
                          <span class="nd-deal-err">Ошибка: {{ $recModel->transcript_error }}</span>
                        @endif
                      @endif
                    @endif
                  </div>
                @endif
              </div>
            @elseif($a->type === 'voice_note')
              @php
                $voiceUrl = '';
                if (is_string($payload['voice_url'] ?? null) && trim((string) $payload['voice_url']) !== '') {
                  $voiceUrl = (string) $payload['voice_url'];
                } elseif (is_string($payload['public_url'] ?? null) && trim((string) $payload['public_url']) !== '') {
                  $voiceUrl = (string) $payload['public_url'];
                }
              @endphp
              @if($activityBody !== '')
                <div class="nd-tl-body">{!! nl2br(e($activityBody)) !!}</div>
              @endif
              @if($voiceUrl !== '')
                <audio controls preload="none" src="{{ $voiceUrl }}"></audio>
                <div class="nd-call-links"><a href="{{ $voiceUrl }}" target="_blank" rel="noopener">Открыть файл</a></div>
              @endif
            @elseif($activityBody !== '' && $a->type !== 'stage_changed')
              <div class="nd-tl-body">{!! nl2br(e($activityBody)) !!}</div>
            @endif
          </div>
        @empty
          <div class="nd-faint">Пока нет событий</div>
        @endforelse
      </div>
    </div>

    <aside class="nd-m-aside">
      <div class="nd-al">Этап</div>
      <form method="POST" action="{{ route('deals.stage', $deal) }}" id="dealStageForm">
        @csrf
        <input type="hidden" name="stage_id" id="dealStageSelect" value="{{ $deal->stage_id }}">
        <div class="nd-stages-row">
          @foreach($stages as $s)
            <button type="button" @class(['nd-st-opt', 'nd-on' => (int) $deal->stage_id === (int) $s->id]) data-stage-id="{{ $s->id }}"><span class="nd-d" style="background:{{ $ndUi::stageDot($s->name, $loop->index) }}"></span>{{ $s->name }}</button>
          @endforeach
        </div>
        @if($ndNtStageId)
          {{-- потолки: на «Нецелевое» — только с категорией --}}
          <select name="non_target_category" class="nd-deal-select" id="dealNonTargetSelect"
            @if(! ((int) $deal->stage_id === $ndNtStageId && ! $deal->closed_at)) hidden @endif>
            <option value="">— почему нецелевая? —</option>
            @foreach($nonTargetCategories as $key => $label)
              <option value="{{ $key }}" @selected($deal->non_target_category === $key)>{{ $label }}</option>
            @endforeach
          </select>
        @endif
      </form>
      @error('non_target_category')
        <div class="nd-deal-err">{{ $message }}</div>
      @enderror
      @if($deal->closed_at)
        <div class="nd-deal-note">Перевод в рабочий этап переоткроет сделку.</div>
      @endif

      <div class="nd-al">Ответственный</div>
      <div class="nd-av2 nd-deal-person">
        @if($deal->responsible)
          {!! $ndUi::avatar($deal->responsible->name, 22) !!} {{ $deal->responsible->name }}
        @else
          <span class="nd-faint">не назначен</span>
        @endif
      </div>

      <div class="nd-al">Источник</div>
      <div class="nd-av2">{!! !empty($isSneakerSpace) ? \App\Support\Ui\Sneakers::srcChip($deal->manual_source) : $ndUi::sourceChip($ndSrc) !!}
        @if($deal->incoming_phone_source_display)<div class="nd-deal-srclabel">номер: {{ $deal->incoming_phone_source_display }}</div>@endif
      </div>

      <div class="nd-al">Звонков</div>
      <div class="nd-av2">{{ $callActivityGroups->count() }}</div>

      <div class="nd-al">Сделка</div>
      <div class="nd-deal-facts">
        <div><span>Категория</span><b>{{ $deal->product_category_label ?? '—' }}</b></div>
        <div><span>Сумма</span><b>{{ $deal->amount ? number_format($deal->amount, 0, ',', ' ').' ₽' : '—' }}</b></div>
        @if(empty($isSneakerSpace))
          <div><span>Помещение</span><b>{{ $deal->readiness_status ?? '—' }}</b></div>
        @endif
        @if($deal->closed_at)
          <div><span>Закрыта</span><b>{{ $deal->closed_at->format('d.m.Y H:i') }}</b></div>
          <div><span>Результат</span><b>{{ $dealClosedResult['short_label'] }}</b></div>
          @if($ndNtLabel)
            <div><span>Категория</span><b>{{ $ndNtLabel }}</b></div>
          @endif
          @if($deal->closed_reason)
            <div><span>Причина</span><b>{{ $deal->closed_reason }}</b></div>
          @endif
        @endif
      </div>
      @if(!$deal->is_ready)
        <div class="nd-deal-note nd-tone-amber">Рекомендуем заполнить: <b>{{ implode(', ', $ndMissing) }}</b>.</div>
      @endif

      <details class="nd-deal-edit" @if($errors->hasAny(['title', 'responsible_user_id', 'amount', 'product_category'])) open @endif>
        <summary>Изменить данные</summary>
        <form method="POST" action="{{ route('deals.update', $deal) }}" class="nd-deal-form">
          @csrf
          @method('PATCH')
          <div class="nd-field"><label>Название сделки</label><input name="title" value="{{ $deal->title }}" required></div>
          <div class="nd-field">
            <label>Ответственный</label>
            <select name="responsible_user_id" required>
              @foreach($users as $u)
                <option value="{{ $u->id }}" @selected($deal->responsible_user_id === $u->id)>{{ $u->name }}</option>
              @endforeach
            </select>
          </div>
          <div class="nd-deal-form-2">
            <div class="nd-field"><label>Сумма, ₽</label><input name="amount" type="number" step="0.01" min="0.01" value="{{ $deal->amount ?? '' }}"></div>
            <div class="nd-field">
              <label>Категория</label>
              <select name="product_category" required>
                @foreach(($productCategoryOptions ?? []) as $categoryKey => $categoryLabel)
                  <option value="{{ $categoryKey }}" @selected($deal->product_category === $categoryKey)>{{ $categoryLabel }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <button class="nd-btn nd-sm nd-primary">Сохранить</button>
        </form>
      </details>

      @unless($deal->closed_at)
        <div class="nd-al" id="ndClose">Закрыть сделку</div>
        <form method="POST" action="{{ route('deals.close', $deal) }}" class="nd-deal-form" id="ndCloseForm">
          @csrf
          <div class="nd-seg nd-deal-result">
            <label><input type="radio" name="result" value="won" @checked($ndCloseResult === 'won')>Успешно</label>
            <label><input type="radio" name="result" value="lost" @checked($ndCloseResult === 'lost')>Отказ</label>
            <label><input type="radio" name="result" value="extra_non_target" @checked($ndCloseResult === 'extra_non_target')>Доп. / нецелевая</label>
          </div>
          @if(!empty($nonTargetCategories))
            <select name="category" class="nd-deal-select" id="ndCloseCategory" required @if($ndCloseResult !== 'extra_non_target') hidden disabled @endif>
              <option value="">— почему нецелевая? —</option>
              @foreach($nonTargetCategories as $key => $label)
                <option value="{{ $key }}" @selected(old('category', $deal->non_target_category) === $key)>{{ $label }}</option>
              @endforeach
            </select>
          @endif
          @error('category')
            <div class="nd-deal-err">{{ $message }}</div>
          @enderror
          <input class="nd-deal-input" name="reason" maxlength="255" value="{{ old('reason') }}" placeholder="Комментарий / причина (необязательно)">
          <button class="nd-btn nd-sm nd-deal-closebtn" id="ndCloseBtn" data-tone="{{ $ndCloseResult }}">Закрыть сделку</button>
        </form>
      @endunless

      <div class="nd-al">Чаты</div>
      @forelse($dealConversations as $chat)
        <a class="nd-deal-chat" href="{{ $chat['url'] }}">
          <span class="nd-deal-chat-name">{!! $chat['source_icon_html'] !!} {{ $chat['lead_name'] }}</span>
          <span class="nd-deal-chat-body">{{ $chat['body'] }}</span>
          <span class="nd-deal-chat-meta">{{ $chat['source_label'] }}@if($chat['last_message_at']) · {{ $chat['last_message_at'] }}@endif
            @if(($chat['unread_count'] ?? 0) > 0)<span class="nd-cnt-badge nd-attn">{{ $chat['unread_count'] }}</span>@endif
          </span>
        </a>
      @empty
        <div class="nd-faint nd-deal-small">Пока нет связанных диалогов</div>
      @endforelse

      @if($canUseCeilingProjects)
        <div class="nd-al">Проект потолка</div>
        <div class="nd-deal-facts">
          @if($deal->ceilingProject)
            <div><span>Статус</span><b>{{ \App\Models\CeilingProject::statusOptions()[$deal->ceilingProject->status] ?? $deal->ceilingProject->status }}</b></div>
            <div><span>Помещений</span><b>{{ $deal->ceilingProject->rooms->count() }}</b></div>
            <div><span>Материал</span><b>{{ \App\Models\CeilingProject::materialOptions()[$deal->ceilingProject->canvas_material] ?? $deal->ceilingProject->canvas_material }}</b></div>
            @if($ceilingProjectSummary)
              <div><span>Смета</span><b>{{ number_format($ceilingProjectSummary['estimate']['grand_total'], 0, ',', ' ') }} ₽</b></div>
            @endif
            @if($deal->ceilingProject->last_calculated_at)
              <div><span>Пересчитано</span><b>{{ $deal->ceilingProject->last_calculated_at->format('d.m.Y H:i') }}</b></div>
            @endif
          @else
            <div class="nd-faint nd-deal-small">Проект ещё не заведён.</div>
          @endif
        </div>
      @endif
    </aside>
  </div>
</div>

<script>
  (() => {
    // этап: кнопки этапов и «Записать на замер»; «Нецелевое» — только с категорией (как прежний выпадающий список)
    const form = document.getElementById('dealStageForm');
    const stage = document.getElementById('dealStageSelect');
    const category = document.getElementById('dealNonTargetSelect');
    const ntId = @json($ndNtStageId ? (string) $ndNtStageId : null);
    const current = @json((string) $deal->stage_id);
    const closed = @json((bool) $deal->closed_at);
    document.querySelectorAll('[data-stage-id]').forEach((btn) => btn.addEventListener('click', () => {
      const id = btn.dataset.stageId;
      if (category && ntId && id === ntId) {
        category.hidden = false;
        category.value = '';
        category.focus();
        return;
      }
      if (id === current && !closed) return;
      stage.value = id;
      form.submit();
    }));
    category?.addEventListener('change', () => {
      if (!category.value) return;
      stage.value = ntId;
      form.submit();
    });

    // закрытие: результат → категория нецелевой и цвет кнопки; «Отказ» в действиях выбирает отказ
    const closeForm = document.getElementById('ndCloseForm');
    if (!closeForm) return;
    const closeCat = document.getElementById('ndCloseCategory');
    const closeBtn = document.getElementById('ndCloseBtn');
    const sync = () => {
      const result = closeForm.querySelector('input[name="result"]:checked')?.value || 'won';
      if (closeCat) { closeCat.hidden = result !== 'extra_non_target'; closeCat.disabled = result !== 'extra_non_target'; }
      closeBtn.dataset.tone = result;
      closeBtn.textContent = { won: 'Закрыть успешно', lost: 'Закрыть с отказом', extra_non_target: 'Закрыть как доп. / нецелевую' }[result];
    };
    closeForm.querySelectorAll('input[name="result"]').forEach((r) => r.addEventListener('change', sync));
    document.querySelector('[data-nd-close]')?.addEventListener('click', () => {
      const r = closeForm.querySelector('input[value="lost"]');
      if (r) { r.checked = true; sync(); }
      setTimeout(() => closeForm.querySelector('input[name="reason"]')?.focus(), 50);
    });
    sync();
  })();
</script>
