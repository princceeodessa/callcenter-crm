<form method="POST" action="{{ route('tasks.update', $task) }}" class="{{ $class ?? 'row g-2 mt-3 pt-3 border-top' }}">
    @csrf
    @method('PATCH')
    @php
        $isDocumentsTaskView = (bool) ($isDocumentsTaskView ?? ((string) auth()->user()?->role === 'documents_operator'));
        $defaultAssignedId = (int) old('edit_assigned_user_id', (int) ($task->assigned_user_id ?? auth()->id()));
    @endphp

    <div class="col-12">
        <label class="form-label small mb-1">Название</label>
        <input
            name="edit_title"
            class="form-control form-control-sm"
            value="{{ old('edit_title', $task->title) }}"
            required
        >
    </div>

    <div class="col-12">
        <label class="form-label small mb-1">Комментарий</label>
        <textarea
            name="edit_description"
            class="form-control form-control-sm"
            rows="3"
            placeholder="Комментарий (необязательно)"
        >{{ old('edit_description', $task->description) }}</textarea>
    </div>

    <div class="col-12 col-xl-6">
        <label class="form-label small mb-1">Когда напомнить</label>
        <input
            name="edit_due_at"
            type="datetime-local"
            class="form-control form-control-sm"
            value="{{ old('edit_due_at', optional($task->due_at)->format('Y-m-d\\TH:i')) }}"
            required
        >
    </div>

    <div class="col-12 col-xl-6">
        <label class="form-label small mb-1">Кому назначить</label>
        @if($isDocumentsTaskView)
            <input type="hidden" name="edit_assigned_user_id" value="{{ auth()->id() }}">
            <div class="form-control form-control-sm bg-body-secondary" aria-readonly="true">{{ auth()->user()?->name }} (только мои дела)</div>
        @else
            <select name="edit_assigned_user_id" class="form-select form-select-sm">
                @if($canAssignToAll ?? false)
                    <option value="0" @selected((string) old('edit_assigned_user_id', (string) ($task->assigned_user_id ?? 0)) === '0')>{{ $assignAllLabel ?? 'Всем' }}</option>
                @endif
                @foreach($users as $worker)
                    <option value="{{ $worker->id }}" @selected($defaultAssignedId === (int) $worker->id)>{{ $worker->name }}</option>
                @endforeach
            </select>
        @endif
    </div>

    <div class="col-12 d-flex gap-2 flex-wrap">
        <button class="btn btn-sm btn-primary">Сохранить</button>
        <a class="btn btn-sm btn-outline-secondary" href="{{ $cancelUrl }}">Отмена</a>
    </div>
</form>
