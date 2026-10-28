@extends('layouts.sneakers')
{{--
    «Дела» кроссовочного отдела — каркас «Нордика» (прототип: PAGES.tasks, снимок 12). Сотрудники чипами, блоки «На сегодня»,
    «Просроченные», «Ближайшие», «Готово» у каждого дела, окна «Новое дело» и «Изменить». Данные и маршруты — прежние
    (TaskController::index, tasks.page.store, tasks.update, tasks.complete); листание дней — focus_date.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $focus = \Carbon\Carbon::parse($focusDate);
    $params = fn (array $extra) => array_filter(array_merge(['q' => $search !== '' ? $search : null, 'assigned_user_id' => $assignedUserId ?: null, 'focus_date' => $isTodayFocusDate ? null : $focusDate], $extra), fn ($v) => $v !== null && $v !== '');
    $when = function ($task) {
        if (! $task->due_at) {
            return 'без срока';
        }
        $d = $task->due_at;
        return ($d->isToday() ? 'сегодня ' : ($d->isTomorrow() ? 'завтра ' : ($d->isYesterday() ? 'вчера ' : $d->format('d.m').' '))).$d->format('H:i');
    };
    $dealTitle = fn ($deal) => $deal ? (($deal->title_is_custom ? $deal->title : ($deal->contact?->name ?: $deal->title)) ?: 'Сделка #'.$deal->id) : null;
    $blocks = [
        [$isTodayFocusDate ? 'На сегодня' : 'На '.$focusDateLabel, $selectedTasks, $isTodayFocusDate ? 'На сегодня открытых дел нет' : 'На этот день открытых дел нет', false],
        ['Просроченные', $previousTasks, 'Просроченных дел нет', true],
        ['Ближайшие', $nextTasks, 'Ближайших дел нет', false],
    ];
    $editData = fn ($t) => ['id' => $t->id, 'title' => $t->title, 'description' => $t->description, 'due' => optional($t->due_at)->format('Y-m-d\TH:i'),
        'assigned' => $t->assigned_user_id === null ? 0 : (int) $t->assigned_user_id, 'url' => route('tasks.update', $t)];
@endphp

@section('title', 'Дела')
@section('ns_sub', ($isTodayFocusDate ? 'сегодня ' : 'на ').$focusDateLabel)
@section('ns_no_sell', '1')
@section('ns_actions')
    <a class="ns-btn ns-icon-only" href="{{ route('tasks.index', $params(['focus_date' => $focus->copy()->subDay()->toDateString()])) }}" title="Предыдущий день"><svg class="ns-icon" viewBox="0 0 24 24"><path d="M15 6l-6 6 6 6"/></svg></a>
    <a class="ns-btn ns-icon-only" href="{{ route('tasks.index', $params(['focus_date' => $focus->copy()->addDay()->toDateString()])) }}" title="Следующий день"><svg class="ns-icon" viewBox="0 0 24 24"><path d="M9 6l6 6-6 6"/></svg></a>
    @unless($isTodayFocusDate)<a class="ns-btn" href="{{ route('tasks.index', $params(['focus_date' => null])) }}">Сегодня</a>@endunless
    @if($canCreatePageTask)
        <button class="ns-btn ns-primary" type="button" id="ntNew">{!! $ui::icon('plus', 'ns-sm') !!}Новое дело</button>
    @endif
@endsection

@section('content')
<div class="ns-tasks">
    <div class="ns-brands">
        <a @class(['ns-chipf', 'ns-on' => ! $assignedUserId]) href="{{ route('tasks.index', $params(['assigned_user_id' => null])) }}">Все сотрудники</a>
        @if($canAssignToAll)<a @class(['ns-chipf', 'ns-on' => $assignedUserId === -1]) href="{{ route('tasks.index', $params(['assigned_user_id' => -1])) }}">Только «{{ $assignAllLabel }}»</a>@endif
        @foreach($users as $u)
            <a @class(['ns-chipf', 'ns-on' => $assignedUserId === (int) $u->id]) href="{{ route('tasks.index', $params(['assigned_user_id' => $u->id])) }}">{{ $u->name }}</a>
        @endforeach
        <form method="GET" action="{{ route('tasks.index') }}" class="ns-search ns-tasks-q">
            @if($assignedUserId)<input type="hidden" name="assigned_user_id" value="{{ $assignedUserId }}">@endif
            @unless($isTodayFocusDate)<input type="hidden" name="focus_date" value="{{ $focusDate }}">@endunless
            {!! $ui::icon('search', 'ns-sm') !!}<input type="search" name="q" value="{{ $search }}" placeholder="Дело, клиент, телефон — Enter" data-scan="own">
        </form>
    </div>

    @foreach($blocks as [$title, $list, $empty, $overdue])
        <div class="ns-panel ns-tblock">
            <div class="ns-panel-pad" style="padding-bottom:8px"><div class="ns-sec-h">{{ $title }} <span @class(['ns-tag', 'ns-bad' => $overdue && $list->isNotEmpty()])>{{ $list->count() }}</span></div></div>
            @forelse($list as $t)
                @php
                    $deal = $t->deal;
                    $dt = $dealTitle($deal);
                @endphp
                <div class="ns-rrow ns-task" id="task-{{ $t->id }}">
                    <div class="ns-rr-m">
                        <div class="ns-rr-n">{{ $t->title }}</div>
                        <div class="ns-rr-s"><span>{{ $t->assignee_label }}</span>·<span @class(['ns-dn' => $overdue])>{{ $when($t) }}</span>
                            @if($deal)·<a class="ns-link" href="{{ route('deals.show', $deal) }}">{{ $dt }}</a>@if($deal->stage) <span class="ns-muted">({{ $deal->stage->name }})</span>@endif @endif
                            @if($deal?->contact?->phone)·<span>{{ $deal->contact->phone }}</span>@endif
                        </div>
                        @if($t->description)<div class="ns-rr-d">{{ $t->description }}</div>@endif
                    </div>
                    <button class="ns-btn ns-sm" type="button" data-edit='@json($editData($t))'>Изменить</button>
                    <form method="POST" action="{{ route('tasks.complete', $t) }}">
                        @csrf
                        <button class="ns-btn ns-sm" type="submit">{!! $ui::icon('check', 'ns-sm') !!}Готово</button>
                    </form>
                </div>
            @empty
                <div class="ns-empty ns-sm" style="padding-top:8px"><b>{{ $empty }}</b></div>
            @endforelse
        </div>
    @endforeach
</div>

@php
    $assignOptions = ($canAssignToAll ? '<option value="0">'.e($assignAllLabel).'</option>' : '').collect($users)->map(fn ($u) => '<option value="'.$u->id.'">'.e($u->name).'</option>')->implode('');
@endphp
<template id="ntTpl">
    <form method="POST" action="{{ route('tasks.page.store') }}">
        @csrf
        <div class="ns-m-head"><div class="ns-mt">Новое дело</div><button class="ns-m-x" type="button" data-ns-close aria-label="Закрыть">{!! $ui::icon('x') !!}</button></div>
        <div class="ns-m-body">
            <div class="ns-field"><label>Название</label><input name="title" required maxlength="255" placeholder="Например, перезвонить по размеру 43"></div>
            <div class="ns-frow">
                <div class="ns-field"><label>Кому назначить</label><select name="assigned_user_id">{!! $assignOptions !!}</select></div>
                <div class="ns-field"><label>Когда напомнить</label><input name="due_at" type="datetime-local" required></div>
            </div>
            <div class="ns-field"><label>Сделка</label>
                <select name="deal_id" required>
                    <option value="">— выберите сделку —</option>
                    @foreach($deals as $d)
                        <option value="{{ $d->id }}">#{{ $d->id }} · {{ $dealTitle($d) }}@if($d->contact?->phone) · {{ $d->contact->phone }}@endif</option>
                    @endforeach
                </select>
            </div>
            <div class="ns-field"><label>Комментарий</label><textarea name="description" rows="3" placeholder="Необязательно"></textarea></div>
        </div>
        <div class="ns-m-foot"><button class="ns-btn" type="button" data-ns-close>Отмена</button><button class="ns-btn ns-primary" type="submit">Добавить дело</button></div>
    </form>
</template>
<template id="etTpl">
    <form method="POST" action="">
        @csrf
        <input type="hidden" name="_method" value="PATCH">
        <div class="ns-m-head"><div class="ns-mt">Изменить дело</div><button class="ns-m-x" type="button" data-ns-close aria-label="Закрыть">{!! $ui::icon('x') !!}</button></div>
        <div class="ns-m-body">
            <div class="ns-field"><label>Название</label><input name="edit_title" required maxlength="255"></div>
            <div class="ns-frow">
                <div class="ns-field"><label>Кому назначить</label><select name="edit_assigned_user_id">{!! $assignOptions !!}</select></div>
                <div class="ns-field"><label>Когда напомнить</label><input name="edit_due_at" type="datetime-local" required></div>
            </div>
            <div class="ns-field"><label>Комментарий</label><textarea name="edit_description" rows="3"></textarea></div>
        </div>
        <div class="ns-m-foot"><button class="ns-btn" type="button" data-ns-close>Отмена</button><button class="ns-btn ns-primary" type="submit">Сохранить</button></div>
    </form>
</template>
@endsection

@push('scripts')
<script>
(() => {
    const me = @json((int) auth()->id());
    const pad = (n) => String(n).padStart(2, '0');
    const inHour = () => { const d = new Date(Date.now() + 3600e3); return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate()) + 'T' + pad(d.getHours()) + ':00'; };
    document.getElementById('ntNew')?.addEventListener('click', () => {
        const m = ns.openModal(document.getElementById('ntTpl').innerHTML, 560);
        m.querySelector('[name="due_at"]').value = inHour();
        const who = m.querySelector('[name="assigned_user_id"]');
        if (who.querySelector('option[value="' + me + '"]')) who.value = String(me);
        m.querySelector('[name="title"]').focus();
    });
    document.querySelectorAll('[data-edit]').forEach((b) => b.addEventListener('click', () => {
        const t = JSON.parse(b.dataset.edit);
        const m = ns.openModal(document.getElementById('etTpl').innerHTML, 560), f = m.querySelector('form');
        f.action = t.url;
        f.edit_title.value = t.title || '';
        f.edit_description.value = t.description || '';
        f.edit_due_at.value = t.due || inHour();
        if (f.edit_assigned_user_id.querySelector('option[value="' + t.assigned + '"]')) f.edit_assigned_user_id.value = String(t.assigned);
        f.edit_title.focus();
    }));
})();
</script>
@endpush
