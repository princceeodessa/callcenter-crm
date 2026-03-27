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
                <th>Логин</th>
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
                    @php
                      $role = $u->role;
                      $badgeClass = match($role) {
                        'admin' => 'text-bg-primary',
                        'main_operator' => 'text-bg-warning',
                        'measurer' => 'text-bg-info',
                        'constructor' => 'text-bg-dark',
                        default => 'text-bg-secondary',
                      };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $role }}</span>
                  </td>
                  <td>
                    @if($u->is_active)
                      <span class="badge text-bg-success">Активен</span>
                    @else
                      <span class="badge text-bg-danger">Отключён</span>
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

        @if($errors->any())
          <div class="alert alert-danger py-2">
            {{ $errors->first() }}
          </div>
        @endif

        <form method="POST" action="{{ route('settings.users.store') }}">
          @csrf
          <div class="row g-2 mb-2">
            <div class="col-md-6">
              <label class="form-label">Имя</label>
              <input name="first_name" class="form-control" value="{{ old('first_name') }}" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Фамилия</label>
              <input name="last_name" class="form-control" value="{{ old('last_name') }}" required>
            </div>
          </div>
          <div class="mb-2">
            <label class="form-label">Логин</label>
            <input name="login" class="form-control" value="{{ old('login') }}" autocomplete="username" required>
            <div class="form-text">Без пробелов. Можно использовать буквы, цифры и символы вроде <code>.</code>, <code>_</code>, <code>-</code>.</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Пароль</label>
            <input name="password" type="password" class="form-control" required>
            <div class="form-text">Передайте пароль пользователю (потом можно сменить).</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Роль</label>
            <select name="role" class="form-select">
              <option value="operator" @selected(old('role', 'operator') === 'operator')>operator (колл-центр)</option>
              <option value="main_operator" @selected(old('role') === 'main_operator')>main_operator (руководитель)</option>
              <option value="measurer" @selected(old('role') === 'measurer')>measurer (замерщик)</option>
              <option value="constructor" @selected(old('role') === 'constructor')>constructor (проектирование)</option>
              <option value="admin" @selected(old('role') === 'admin')>admin</option>
            </select>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', '1')) id="isActive">
            <label class="form-check-label" for="isActive">Активный</label>
          </div>
          <button class="btn btn-primary w-100">Создать</button>
        </form>
      </div>
    </div>
  </div>
</div>
@endsection
