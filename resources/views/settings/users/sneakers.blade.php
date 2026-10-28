@extends('layouts.sneakers')
{{--
    «Пользователи» кроссовочного отдела — каркас «Нордика» (прототип: PAGES.users, снимок 15). Те же действия, что и раньше:
    добавить сотрудника (settings.users.store), сменить пароль (settings.users.password), отключить/включить (settings.users.toggle).
    Закупку и прибыль видят руководитель и владелец — это и показано в колонке «Доступ к ценам закупки».
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $roleColor = ['sneaker_owner' => '#121519', 'sneaker_head' => '#1fae55', 'sneaker_operator' => '#3f72ff', 'smm' => '#8a5cf6'];
    $monthName = mb_strtolower($month->translatedFormat('F'));
    $monthIn = ['январь' => 'январе', 'февраль' => 'феврале', 'март' => 'марте', 'апрель' => 'апреле', 'май' => 'мае', 'июнь' => 'июне', 'июль' => 'июле',
        'август' => 'августе', 'сентябрь' => 'сентябре', 'октябрь' => 'октябре', 'ноябрь' => 'ноябре', 'декабрь' => 'декабре'][$monthName] ?? $monthName;
    $active = $users->where('is_active', true)->count();
@endphp

@section('title', 'Пользователи')
@section('ns_sub', '— кроссовочный отдел · '.$active.' активных из '.$users->count())
@section('ns_no_search', '1')
@section('ns_no_sell', '1')
@section('ns_actions')
    <button class="ns-btn ns-primary" type="button" id="nsUserAdd">{!! $ui::icon('plus', 'ns-sm') !!}Добавить</button>
@endsection

@section('content')
    <div class="ns-panel" style="overflow:hidden">
        <table class="ns-tbl">
            <thead><tr>
                <th>Сотрудник</th><th>Роль</th><th class="ns-num">Продаж в {{ $monthIn }}</th><th>Доступ к ценам закупки</th><th>Статус</th><th></th>
            </tr></thead>
            <tbody>
            @foreach($users as $u)
                <tr>
                    <td><div style="display:flex;align-items:center;gap:10px">
                        <span class="ns-user-ava" style="background:{{ $roleColor[$u->role] ?? '#7b8494' }}">{{ $ui::initials($u->name) }}</span>
                        <div><div class="ns-tname">{{ $u->name }}</div><div style="font-size:11.5px;color:var(--muted)">{{ $u->email }}</div></div>
                    </div></td>
                    <td>{{ $ui::ROLE_LABELS[$u->role] ?? $u->role }}</td>
                    <td class="ns-num"><b>{{ (int) ($salesByUser[$u->id] ?? 0) }}</b></td>
                    <td>
                        @if(in_array($u->role, ['sneaker_owner', 'sneaker_head'], true))
                            <span class="ns-tag ns-ok">видит закуп</span>
                        @elseif($u->role === 'smm')
                            <span class="ns-tag">только SMM-контент</span>
                        @else
                            <span class="ns-tag">только продажа</span>
                        @endif
                    </td>
                    <td>
                        @if($u->is_active)
                            <span class="ns-stage-pill" style="color:var(--accent)"><span class="ns-d" style="background:var(--accent)"></span>активен</span>
                        @else
                            <span class="ns-stage-pill" style="color:var(--red)"><span class="ns-d" style="background:var(--red)"></span>отключён</span>
                        @endif
                    </td>
                    <td><div class="ns-row-act">
                        <button class="ns-btn ns-sm" type="button" data-ns-pw="{{ route('settings.users.password', $u) }}" data-name="{{ $u->name }}">{!! $ui::icon('key', 'ns-sm') !!}Пароль</button>
                        @if(auth()->id() !== $u->id)
                            <form method="POST" action="{{ route('settings.users.toggle', $u) }}" style="margin:0">
                                @csrf
                                <button class="ns-btn ns-sm @if($u->is_active) ns-danger @endif" type="submit">{{ $u->is_active ? 'Отключить' : 'Включить' }}</button>
                            </form>
                        @endif
                    </div></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="ns-note">Цены закупки и прибыль видят только владелец и руководитель — продавцу в окне продажи показывается только цена.</div>

    {{-- окно «Новый сотрудник» (открывает «Добавить»; после ошибки формы — само) --}}
    <template id="nsUserNew">
        <form method="POST" action="{{ route('settings.users.store') }}">
            @csrf
            <div class="ns-m-head"><div><div class="ns-mt">Новый сотрудник</div><div class="ns-msub">Передайте логин и пароль сотруднику — позже пароль можно сменить</div></div>
                <button class="ns-m-x" type="button" data-ns-close aria-label="Закрыть"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
            <div class="ns-m-body">
                @if($errors->any())
                    <div class="ns-flash ns-err">{!! $ui::icon('x', 'ns-sm') !!}<span>{{ $errors->first() }}</span></div>
                @endif
                <div class="ns-frow">
                    <div class="ns-field"><label>Имя</label><input name="first_name" value="{{ old('first_name') }}" required autocomplete="off"></div>
                    <div class="ns-field"><label>Фамилия</label><input name="last_name" value="{{ old('last_name') }}" required autocomplete="off"></div>
                </div>
                <div class="ns-frow">
                    <div class="ns-field"><label>Логин <i>— без пробелов</i></label><input name="login" value="{{ old('login') }}" required autocomplete="off" pattern="\S+"></div>
                    <div class="ns-field"><label>Пароль <i>— от 6 знаков</i></label><input name="password" type="password" required minlength="6" autocomplete="new-password"></div>
                </div>
                <div class="ns-field"><label>Роль</label>
                    <select name="role">
                        @foreach(['sneaker_operator' => 'Продавец — продажа, склад, приёмка (без закупочных цен)', 'sneaker_head' => 'Руководитель — всё, включая закупку и прибыль', 'sneaker_owner' => 'Владелец — только сводка', 'smm' => 'SMM-специалист — только статистика ВК'] as $value => $label)
                            <option value="{{ $value }}" @selected(old('role', 'sneaker_operator') === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="ns-check"><input type="checkbox" name="is_active" value="1" @checked(old('is_active', '1'))>Активный — может войти сразу</label>
            </div>
            <div class="ns-m-foot"><button class="ns-btn" type="button" data-ns-close>Отмена</button><button class="ns-btn ns-primary" type="submit">{!! $ui::icon('check', 'ns-sm') !!}Создать</button></div>
        </form>
    </template>
@endsection

@push('scripts')
<script>
(() => {
    const tpl = document.getElementById('nsUserNew');
    const open = () => { ns.openModal(tpl.innerHTML, 560)?.querySelector('input[name="first_name"]')?.focus(); };
    document.getElementById('nsUserAdd')?.addEventListener('click', open);
    @if($errors->any()) open(); @endif
    // «Пароль» — окошко у кнопки: новый пароль и «Сохранить» (settings.users.password)
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    document.querySelectorAll('[data-ns-pw]').forEach((b) => b.addEventListener('click', (e) => {
        e.stopPropagation();
        const p = ns.showPop(b, '<div class="ns-ph">Новый пароль · ' + ns.esc(b.dataset.name) + '</div><form class="ns-pw-form" method="POST" action="' + ns.esc(b.dataset.nsPw) + '">' +
            '<input type="hidden" name="_token" value="' + ns.esc(csrf) + '"><div class="ns-field"><input name="password" type="password" minlength="6" required placeholder="от 6 знаков" autocomplete="new-password"></div>' +
            '<button class="ns-btn ns-sm ns-primary" type="submit">Сохранить</button></form>', 260);
        p?.querySelector('input[name="password"]')?.focus();
    }));
})();
</script>
@endpush
