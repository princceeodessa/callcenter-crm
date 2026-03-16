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
        <button class="btn btn-primary">Создать</button>
        <a class="btn btn-outline-secondary" href="{{ route('deals.kanban') }}">Отмена</a>
      </div>
    </form>
  </div>
</div>
@endsection
