{{-- Дело карточкой, как сделка на канбане (Nordika, «Дела»): срок, кому, что сделать, сделка; клик — сделка в окне. --}}
@php
  $ndDue = $task->due_at;
  $ndDueCls = ! $ndDue ? '' : ($ndDue->isToday() ? 'nd-today' : ($ndDue->isPast() ? 'nd-over' : ''));   // сегодня — жёлтым, прошлые дни — красным
  $ndTone = $ndDueCls === 'nd-over' ? 'var(--red)' : ($ndDueCls === 'nd-today' ? 'var(--amber)' : 'var(--blue)');
  $ndDealUrl = $task->deal ? route('deals.show', $task->deal) : null;
  $ndDealTitle = $task->deal ? trim((string) ($task->deal->title ?: 'Сделка #'.$task->deal->id)) : 'Сделка не найдена';
  $ndClient = trim((string) ($task->deal?->contact?->name ?? ''));
  $ndPhone = \App\Support\Ui\Nordika::phone($task->deal?->contact?->phone);
  $ndWho = (string) $task->assignee_label;
@endphp
<div class="nd-card nd-tcard" id="task-{{ $task->id }}" style="--src:{{ $ndTone }}" @if($ndDealUrl) data-nd-href="{{ $ndDealUrl }}" @endif>
  <div class="nd-c-top">
    <span class="nd-tdue {{ $ndDueCls }}"><svg class="nd-icon" viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/></svg>{{ $ndDue ? ($ndDue->isToday() ? 'сегодня '.$ndDue->format('H:i') : ($ndDue->isTomorrow() ? 'завтра '.$ndDue->format('H:i') : $ndDue->format('d.m H:i'))) : 'без срока' }}</span>
    <span class="nd-cid nd-twho" title="Кому">{!! \App\Support\Ui\Nordika::avatar($ndWho, 18) !!}{{ \Illuminate\Support\Str::limit($ndWho, 22) }}</span>
  </div>
  <div class="nd-c-title">{{ $task->title }}</div>
  @if($task->description)
    <div class="nd-tdesc">{{ $task->description }}</div>
  @endif
  <div class="nd-tdeal">
    <svg class="nd-icon" viewBox="0 0 24 24">{!! \App\Support\Ui\Nordika::ICONS['kanban'] !!}</svg>
    <span>
      @if($ndDealUrl)<a href="{{ $ndDealUrl }}">{{ $ndDealTitle }}</a>@else{{ $ndDealTitle }}@endif
      @if($ndClient !== '' || $ndPhone)<small>{{ trim($ndClient.($ndClient !== '' && $ndPhone ? ' · ' : '').($ndPhone ?? '')) }}</small>@endif
    </span>
  </div>
  <div class="nd-c-foot nd-tfoot">
    <a class="nd-btn nd-sm" href="{{ $buildTasksUrl(['edit_task' => $task->id]) }}">Изменить</a>
    <form method="POST" action="{{ route('tasks.complete', $task) }}">
      @csrf
      <button class="nd-btn nd-sm nd-tdone" title="Отметить выполненным"><svg class="nd-icon nd-sm" viewBox="0 0 24 24" stroke-width="2.6"><path d="M5 12l5 5 9-11"/></svg>Выполнено</button>
    </form>
  </div>
</div>
