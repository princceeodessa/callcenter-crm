@extends('layouts.app')

@section('content')
<div class="row g-3">
  <div class="col-lg-7">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Пользователи</h5>

        <div class="table-responsive">
          <table class="table table-sm align-middle">
            <thead>
              <tr>
                <th>Имя</th>
                <th>Email</th>
                <th>Роль</th>
                <th>Статус</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              @foreach($users as $u)
                <tr>
                  <td>{{ $u->name }}</td>
                  <td>{{ $u->email }}</td>
                  <td>
                    @if($u->role === 'admin')
                      <span class="badge text-bg-primary">admin</span>
                    @else
                      <span class="badge text-bg-secondary">operator</span>
                    @endif
                  </td>
                  <td>
                    @if($u->is_active)
                      <span class="badge text-bg-success">active</span>
                    @else
                      <span class="badge text-bg-danger">disabled</span>
                    @endif
                  </td>
                  <td class="text-end">
                    @if(auth()->id() !== $u->id)
                      <form method="POST" action="{{ route('settings.users.toggle', $u) }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-warning">{{ $u->is_active ? 'Отключить' : 'Включить' }}</button>
                      </form>
                    @endif
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <p class="text-muted small mb-0">
          Пользователь видит данные CRM только внутри вашего аккаунта (workspace).
        </p>
      </div>
    </div>
  </div>

  <div class="col-lg-5">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Добавить пользователя</h5>

        <form method="POST" action="{{ route('settings.users.store') }}">
          @csrf
          <div class="mb-2">
            <label class="form-label">Имя</label>
            <input name="name" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Email (логин)</label>
            <input name="email" type="email" class="form-control" required>
          </div>
          <div class="mb-2">
            <label class="form-label">Пароль</label>
            <input name="password" type="password" class="form-control" required>
            <div class="form-text">Передайте пароль пользователю (потом можно сменить).</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Роль</label>
            <select name="role" class="form-select">
              <option value="operator" selected>operator</option>
              <option value="admin">admin</option>
            </select>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" checked id="isActive">
            <label class="form-check-label" for="isActive">Активный</label>
          </div>
          <button class="btn btn-primary w-100">Создать</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
