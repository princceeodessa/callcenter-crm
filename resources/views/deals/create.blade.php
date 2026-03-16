@extends('layouts.app')

@section('content')
<h4 class="mb-3">Новая сделка (ручной ввод)</h4>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="POST" action="{{ route('deals.store') }}" class="row g-3">
      @csrf
      <div class="col-md-6">
        <label class="form-label">Заголовок</label>
        <input
          name="title"
          class="form-control"
          placeholder="Напр.: Руслан Горбунов - ВК"
          value="{{ old('title') }}"
          required
        >
      </div>
      <div class="col-md-3">
        <label class="form-label">Ответственный</label>
        <select name="responsible_user_id" class="form-select" required>
          @foreach($users as $u)
            <option value="{{ $u->id }}" @selected((int) old('responsible_user_id', auth()->id()) === $u->id)>{{ $u->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-3">
        <label class="form-label">Сумма (RUB)</label>
        <input
          name="amount"
          type="number"
          step="0.01"
          min="0.01"
          class="form-control"
          placeholder="0"
          value="{{ old('amount') }}"
          required
        >
      </div>
      <div class="col-md-3">
        <label class="form-label">Имя клиента</label>
        <input name="contact_name" class="form-control" placeholder="Без имени" value="{{ old('contact_name') }}">
      </div>
      <div class="col-md-3">
        <label class="form-label">Телефон</label>
        <input name="contact_phone" class="form-control" placeholder="+7 ..." value="{{ old('contact_phone') }}">
      </div>
      <div class="col-md-4">
        <label class="form-label">Стадия</label>
        <select name="stage_id" class="form-select" required>
          @foreach($stages as $s)
            <option value="{{ $s->id }}" @selected((int) old('stage_id') === $s->id)>{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-md-2">
        <label class="form-label">Дата сделки</label>
        <input
          name="deal_date"
          type="date"
          class="form-control"
          value="{{ old('deal_date', now()->toDateString()) }}"
          required
        >
      </div>
      <div class="col-12">
        <label class="form-label">Комментарий</label>
        <textarea
          name="comment"
          class="form-control"
          rows="3"
          placeholder="Комментарий к сделке (необязательно)"
        >{{ old('comment') }}</textarea>
      </div>
      <div class="col-12">
        <div class="border rounded p-3 bg-light">
          <div class="form-check form-switch mb-3">
            <input
              class="form-check-input"
              type="checkbox"
              role="switch"
              id="createTaskSwitch"
              name="create_task"
              value="1"
              @checked(old('create_task'))
            >
            <label class="form-check-label fw-semibold" for="createTaskSwitch">Сразу добавить дело по этой сделке</label>
          </div>
          <div class="text-muted small mb-3">Если включить, после создания сделки сразу появится открытое дело.</div>
          <div class="row g-3">
            <div class="col-md-6">
              <label class="form-label">Название дела</label>
              <input
                name="task_title"
                class="form-control"
                placeholder="Напр.: перезвонить клиенту"
                value="{{ old('task_title') }}"
              >
            </div>
            <div class="col-md-3">
              <label class="form-label">Когда напомнить</label>
              <input
                name="task_due_at"
                type="datetime-local"
                class="form-control"
                value="{{ old('task_due_at', old('create_task') ? now()->addHour()->format('Y-m-d\\TH:i') : '') }}"
              >
            </div>
            <div class="col-md-3">
              <label class="form-label">Кому назначить</label>
              <select name="task_assigned_user_id" class="form-select">
                <option value="" @selected(old('task_assigned_user_id', '') === '')>Ответственному по сделке</option>
                <option value="0" @selected((string) old('task_assigned_user_id') === '0')>Всем</option>
                @foreach($users as $u)
                  <option value="{{ $u->id }}" @selected((int) old('task_assigned_user_id') === $u->id)>{{ $u->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Комментарий к делу</label>
              <textarea
                name="task_description"
                class="form-control"
                rows="3"
                placeholder="Что нужно сделать по этой сделке"
              >{{ old('task_description') }}</textarea>
            </div>
          </div>
        </div>
      </div>
      <div class="col-12">
        <button class="btn btn-primary">Создать</button>
        <a class="btn btn-outline-secondary" href="{{ route('deals.kanban') }}">Отмена</a>
      </div>
    </form>
  </div>
</div>
@endsection
