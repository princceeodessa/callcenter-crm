@extends('layouts.app')

@php
    $roleLabels = [
        'admin' => 'admin',
        'main_operator' => 'main_operator (руководитель)',
        'operator' => 'operator (колл-центр)',
        'documents_operator' => 'documents_operator (документы)',
        'measurer' => 'measurer (замерщик)',
        'constructor' => 'constructor (проектировка)',
        'sneaker_head' => 'sneaker_head (рук. кроссовок)',
        'sneaker_operator' => 'sneaker_operator (кроссовки)',
        'sneaker_owner' => 'sneaker_owner (владелец — только сводка)',
        'smm' => 'smm (SMM-специалист — только статистика ВК обоих сообществ)',
    ];
    // редизайн «Nordika» (этап 3) — у колл-центра потолков сотрудники таблицей как в прототипе; у кроссовок прежняя
    $nd = \App\Support\Ui\Nordika::enabled();
    $ndRole = [
        'admin' => ['Администратор', 'var(--blue)'],
        'main_operator' => ['Руководитель', 'var(--amber)'],
        'operator' => ['Оператор', 'var(--accent)'],
        'documents_operator' => ['Документы', 'var(--site)'],
        'measurer' => ['Замерщик', 'var(--lemana)'],
        'constructor' => ['Проектировщик', 'var(--av)'],
        'smm' => ['SMM-специалист', 'var(--vk)'],
        'sneaker_owner' => ['Владелец', 'var(--muted)'],
    ];
@endphp

@if($nd)
  @section('nd_sub', '— '.$users->where('is_active', true)->count().' активных из '.$users->count())
@endif

@section('content')
@if($nd)
  <details class="nd-panel nd-tk-new" @if($errors->any()) open @endif>
    <summary><svg class="nd-icon nd-sm" viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg>Добавить сотрудника</summary>
    <div class="nd-panel-body">
      @if($errors->any())
        <div class="nd-deal-err mb-2">{{ $errors->first() }}</div>
      @endif
      <form method="POST" action="{{ route('settings.users.store') }}" class="row g-2 align-items-end">
        @csrf
        <div class="col-6 col-lg-2"><label class="form-label">Имя</label><input name="first_name" class="form-control" value="{{ old('first_name') }}" required></div>
        <div class="col-6 col-lg-2"><label class="form-label">Фамилия</label><input name="last_name" class="form-control" value="{{ old('last_name') }}" required></div>
        <div class="col-6 col-lg-2"><label class="form-label">Логин</label><input name="login" class="form-control" value="{{ old('login') }}" autocomplete="username" placeholder="без пробелов" required></div>
        <div class="col-6 col-lg-2"><label class="form-label">Пароль</label><input name="password" type="password" class="form-control" autocomplete="new-password" required></div>
        <div class="col-12 col-lg-2">
          <label class="form-label">Роль</label>
          <select name="role" class="form-select">
            <option value="operator" @selected(old('role', 'operator') === 'operator')>Оператор</option>
            <option value="main_operator" @selected(old('role') === 'main_operator')>Руководитель</option>
            <option value="documents_operator" @selected(old('role') === 'documents_operator')>Документы</option>
            <option value="measurer" @selected(old('role') === 'measurer')>Замерщик</option>
            <option value="constructor" @selected(old('role') === 'constructor')>Проектировщик</option>
            <option value="admin" @selected(old('role') === 'admin')>Администратор</option>
            <option value="smm" @selected(old('role') === 'smm')>SMM-специалист (только статистика ВК)</option>
          </select>
        </div>
        <div class="col-12 col-lg-2 d-flex align-items-center gap-3">
          <label class="form-check m-0"><input class="form-check-input" type="checkbox" name="is_active" value="1" @checked(old('is_active', '1'))> <span class="form-check-label">Активный</span></label>
          <button class="nd-btn nd-sm nd-primary">Создать</button>
        </div>
      </form>
      <div class="nd-tb-note mt-2">Пароль передайте сотруднику — позже его можно сменить. Роль «Документы» открывает только раздел документов.</div>
    </div>
  </details>

  <div class="nd-panel">
    <div class="nd-tbl-wrap">
      <table class="nd-tbl nd-users">
        <thead><tr><th>Сотрудник</th><th>Роль</th><th>Статус</th><th class="text-end">Действия</th></tr></thead>
        <tbody>
          @foreach($users as $u)
            @php
              $ndR = $ndRole[$u->role] ?? [$roleLabels[$u->role] ?? $u->role, 'var(--muted)'];
            @endphp
            <tr @class(['nd-off-row' => ! $u->is_active])>
              <td>
                <div class="nd-person">
                  {!! \App\Support\Ui\Nordika::avatar($u->name, 34) !!}
                  <div><div class="nd-tname">{{ $u->name }}</div><div class="nd-sub2">{{ $u->email }}</div></div>
                </div>
              </td>
              <td><span class="nd-stage-pill" style="color:{{ $ndR[1] }}"><span class="nd-d" style="background:{{ $ndR[1] }}"></span>{{ $ndR[0] }}</span></td>
              <td>
                @if($u->is_active)
                  <span class="nd-tag nd-tone-green">активен</span>
                @else
                  <span class="nd-tag nd-tone-red">отключён</span>
                @endif
                @if($u->hide_in_reports)
                  <span class="nd-tag" title="Не показывается в «Отчётах»">не в отчётах</span>
                @endif
              </td>
              <td>
                <div class="nd-user-acts">
                  <form method="POST" action="{{ route('settings.users.password', $u) }}" class="nd-user-pass">
                    @csrf
                    <input type="password" name="password" class="nd-input" placeholder="новый пароль" minlength="6" autocomplete="new-password" required aria-label="Новый пароль для {{ $u->name }}">
                    <button class="nd-btn nd-sm">Пароль</button>
                  </form>
                  @if(in_array($u->role, ['admin', 'main_operator', 'operator'], true))
                    {{-- строки «Отчётов» колл-центра: технические учётки и руководителя можно убрать из статистики --}}
                    <form method="POST" action="{{ route('settings.users.toggle', $u) }}">
                      @csrf
                      <input type="hidden" name="what" value="reports">
                      <button class="nd-btn nd-sm" title="Показывать ли сотрудника в «Отчётах»">{{ $u->hide_in_reports ? 'Показать в отчётах' : 'Скрыть из отчётов' }}</button>
                    </form>
                  @endif
                  @if(auth()->id() !== $u->id)
                    <form method="POST" action="{{ route('settings.users.toggle', $u) }}">
                      @csrf
                      <button class="nd-btn nd-sm {{ $u->is_active ? 'nd-danger' : '' }}">{{ $u->is_active ? 'Отключить' : 'Включить' }}</button>
                    </form>
                  @endif
                </div>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@else
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
                      $badgeClass = match($u->role) {
                        'admin' => 'text-bg-primary',
                        'main_operator' => 'text-bg-warning',
                        'operator' => 'text-bg-secondary',
                        'documents_operator' => 'text-bg-success',
                        'measurer' => 'text-bg-info',
                        'constructor' => 'text-bg-dark',
                        default => 'text-bg-secondary',
                      };
                    @endphp
                    <span class="badge {{ $badgeClass }}">{{ $roleLabels[$u->role] ?? $u->role }}</span>
                  </td>
                  <td>
                    @if($u->is_active)
                      <span class="badge text-bg-success">Активен</span>
                    @else
                      <span class="badge text-bg-danger">Отключён</span>
                    @endif
                    @if($u->hide_in_reports)
                      <span class="badge text-bg-light border" title="Не показывается в «Отчётах»">не в отчётах</span>
                    @endif
                  </td>
                  <td class="text-end">
                    <div class="d-flex gap-1 justify-content-end align-items-center flex-wrap">
                      <form method="POST" action="{{ route('settings.users.password', $u) }}" class="d-flex gap-1">
                        @csrf
                        <input type="password" name="password" class="form-control form-control-sm" placeholder="новый пароль" minlength="6" style="width:140px" required>
                        <button class="btn btn-sm btn-outline-secondary" title="Сменить пароль">Пароль</button>
                      </form>
                      @if(in_array($u->role, ['admin', 'main_operator', 'operator'], true))
                        {{-- строки «Отчётов» колл-центра: технические учётки и руководителя можно убрать из статистики --}}
                        <form method="POST" action="{{ route('settings.users.toggle', $u) }}">
                          @csrf
                          <input type="hidden" name="what" value="reports">
                          <button class="btn btn-sm btn-outline-secondary" title="Показывать ли сотрудника в «Отчётах»">{{ $u->hide_in_reports ? 'Показать в отчётах' : 'Скрыть из отчётов' }}</button>
                        </form>
                      @endif
                      @if(auth()->id() !== $u->id)
                        <form method="POST" action="{{ route('settings.users.toggle', $u) }}">
                          @csrf
                          <button class="btn btn-sm btn-outline-warning">{{ $u->is_active ? 'Отключить' : 'Включить' }}</button>
                        </form>
                      @endif
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>

        <p class="text-muted small mb-0">
          Пользователи работают только внутри своего workspace. Роль
          <code>documents_operator</code> открывает только раздел документов и делает его стартовой страницей.
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
            <div class="form-text">Передайте пароль пользователю. Позже его можно сменить.</div>
          </div>
          <div class="mb-2">
            <label class="form-label">Роль</label>
            <select name="role" class="form-select">
              @if(!empty($isSneakerSpace))
                <option value="sneaker_operator" @selected(old('role', 'sneaker_operator') === 'sneaker_operator')>{{ $roleLabels['sneaker_operator'] }}</option>
                <option value="sneaker_head" @selected(old('role') === 'sneaker_head')>{{ $roleLabels['sneaker_head'] }}</option>
                <option value="sneaker_owner" @selected(old('role') === 'sneaker_owner')>{{ $roleLabels['sneaker_owner'] }}</option>
                <option value="smm" @selected(old('role') === 'smm')>{{ $roleLabels['smm'] }}</option>
              @else
                <option value="operator" @selected(old('role', 'operator') === 'operator')>{{ $roleLabels['operator'] }}</option>
                <option value="main_operator" @selected(old('role') === 'main_operator')>{{ $roleLabels['main_operator'] }}</option>
                <option value="documents_operator" @selected(old('role') === 'documents_operator')>{{ $roleLabels['documents_operator'] }}</option>
                <option value="measurer" @selected(old('role') === 'measurer')>{{ $roleLabels['measurer'] }}</option>
                <option value="constructor" @selected(old('role') === 'constructor')>{{ $roleLabels['constructor'] }}</option>
                <option value="admin" @selected(old('role') === 'admin')>{{ $roleLabels['admin'] }}</option>
                <option value="smm" @selected(old('role') === 'smm')>{{ $roleLabels['smm'] }}</option>
              @endif
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
@endif
@endsection
