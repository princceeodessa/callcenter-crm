@extends('layouts.app')

@section('content')
<div class="row justify-content-center">
  <div class="col-md-4">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="mb-3">Вход</h5>
        <form method="POST" action="{{ route('login.perform') }}">
          @csrf
          <div class="mb-3">
            <label class="form-label">Логин</label>
            <input name="login" value="{{ old('login') }}" class="form-control" autocomplete="username" required>
          </div>
          <div class="mb-3">
            <label class="form-label">Пароль</label>
            <input name="password" type="password" class="form-control" autocomplete="current-password" required>
          </div>
          <button class="btn btn-primary w-100">Войти</button>
        </form>
      </div>
    </div>
    <p class="text-muted small mt-3"></p>
  </div>
</div>
@endsection