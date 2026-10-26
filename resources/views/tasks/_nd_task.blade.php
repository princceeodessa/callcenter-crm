{{-- Дело одной строкой (Nordika, страница «Дела»): клик — сделка, справа срок, «Изменить» и галочка «Выполнено». --}}
@php
  $ndDue = $task->due_at;
  $ndDueCls = ! $ndDue ? '' : ($ndDue->isToday() ? 'nd-today' : ($ndDue->isPast() ? 'nd-over' : ''));   // сегодня — жёлтым, прошлые дни — красным
  $ndTone = $ndDueCls === 'nd-over' ? 'var(--red)' : ($ndDueCls === 'nd-today' ? 'var(--amber)' : 'var(--blue)');
  $ndDealUrl = $task->deal ? route('deals.show', $task->deal) : null;
@endphp
<div class="nd-tk" id="task-{{ $task->id }}" style="--tc:{{ $ndTone }}" @if($ndDealUrl) data-nd-href="{{ $ndDealUrl }}" @endif>
  <div class="nd-tki"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round">{!! \App\Support\Ui\Nordika::ICONS['tasks'] !!}</svg></div>
  <div class="nd-tkmid">
    <div class="nd-tkt">{{ $task->title }}</div>
    <div class="nd-tkm">
      @if($ndDealUrl)
        <a href="{{ $ndDealUrl }}">{{ $taskDealTitle($task) }}</a>
      @else
        <span>{{ $taskDealTitle($task) }}</span>
      @endif
      <span class="nd-tag">{{ $task->assignee_label }}</span>
    </div>
    @if($task->description)
      <div class="nd-tk-desc">{{ $task->description }}</div>
    @endif
  </div>
  <div class="nd-tkdue {{ $ndDueCls }}">{{ $ndDue ? ($ndDue->isToday() ? 'сегодня '.$ndDue->format('H:i') : $ndDue->format('d.m H:i')) : 'без срока' }}</div>
  <div class="nd-tk-acts">
    <a class="nd-btn nd-sm" href="{{ $buildTasksUrl(['edit_task' => $task->id]) }}#task-{{ $task->id }}">Изменить</a>
    <form method="POST" action="{{ route('tasks.complete', $task) }}">
      @csrf
      <button class="nd-tkchk" title="Выполнено" aria-label="Выполнено"><svg class="nd-icon nd-sm" viewBox="0 0 24 24" stroke-width="3"><path d="M5 12l5 5 9-11"/></svg></button>
    </form>
  </div>
</div>
@if($editingTaskId === (int) $task->id)
  <div class="nd-panel nd-panel-pad nd-tk-edit">
    @include('tasks._edit_form', [
      'task' => $task,
      'users' => $users,
      'canAssignToAll' => $canAssignToAll ?? false,
      'assignAllLabel' => $assignAllLabel,
      'cancelUrl' => $buildTasksUrl(['edit_task' => null]),
      'class' => 'row g-2',
    ])
  </div>
@endif
