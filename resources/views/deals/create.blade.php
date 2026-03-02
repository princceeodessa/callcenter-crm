@extends('layouts.app')

@section('content')
<h4 class="mb-3">Новая сделка (ручной ввод)</h4>

<div class="card shadow-sm">
  <div class="card-body">
    <form method="POST" action="{{ route('deals.store') }}" class="row g-3">
      @csrf
      <div class="col-md-6">
        <label class="form-label">Заголовок</label>
        <input name="title" class="form-control" placeholder="Напр.: Руслан Горбунов — ВК" required>
      </div>
      <div class="col-md-3">
        <label class="form-label">Имя клиента</label>
        <input name="contact_name" class="form-control" placeholder="Без имени">
      </div>
      <div class="col-md-3">
        <label class="form-label">Телефон</label>
        <input name="contact_phone" class="form-control" placeholder="+7 ...">
      </div>
      <div class="col-md-4">
        <label class="form-label">Стадия</label>
        <select name="stage_id" class="form-select" required>
          @foreach($stages as $s)
            <option value="{{ $s->id }}">{{ $s->name }}</option>
          @endforeach
        </select>
      </div>
      <div class="col-12">
        <button class="btn btn-primary">Создать</button>
        <a class="btn btn-outline-secondary" href="{{ route('deals.kanban') }}">Отмена</a>
      </div>
    </form>
  </div>
</div>
@endsection
