<form method="POST" action="{{ route('tasks.update', $task) }}" class="{{ $class ?? 'row g-2 mt-3 pt-3 border-top' }}">
    @csrf
    @method('PATCH')

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
        <select name="edit_assigned_user_id" class="form-select form-select-sm">
            <option value="0" @selected((string) old('edit_assigned_user_id', (string) ($task->assigned_user_id ?? 0)) === '0')>Всем</option>
            @foreach($users as $worker)
                <option value="{{ $worker->id }}" @selected((int) old('edit_assigned_user_id', $task->assigned_user_id ?? 0) === (int) $worker->id)>{{ $worker->name }}</option>
            @endforeach
        </select>
    </div>

    <div class="col-12 d-flex gap-2 flex-wrap">
        <button class="btn btn-sm btn-primary">Сохранить</button>
        <a class="btn btn-sm btn-outline-secondary" href="{{ $cancelUrl }}">Отмена</a>
    </div>
</form>
