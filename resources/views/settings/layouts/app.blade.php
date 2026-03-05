<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('deals.kanban') }}">{{ config('app.name') }}</a>
        <div class="d-flex gap-2 flex-wrap">
            @auth
                @php($isPriv = in_array(auth()->user()?->role, ['admin','main_operator'], true))
                <a class="btn btn-sm btn-outline-light" href="{{ route('deals.kanban') }}">Канбан</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('deals.index') }}">Список</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('deals.closed') }}">Завершённые</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('chats.index') }}">Чаты</a>
                @if($isPriv)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.integrations.index') }}">Интеграции</a>
                    <a class="btn btn-sm btn-outline-light" href="{{ route('settings.users.index') }}">Пользователи</a>
                @endif
                <a class="btn btn-sm btn-warning position-relative" href="{{ route('notifications.index') }}" title="Уведомления" aria-label="Уведомления">
                    <i class="bi bi-bell-fill"></i>
                    <span id="navNotifBadge" class="position-absolute top-0 start-100 translate-middle badge rounded-pill text-bg-danger d-none">0</span>
                </a>
                @if($isPriv)
                    <a class="btn btn-sm btn-outline-light" href="{{ route('reports.monthly') }}">Отчёты</a>
                @endif
                <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}">+ Сделка</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning">Выйти</button>
                </form>
            @endauth
        </div>
    </div>
</nav>

@auth
  <div class="toast-container position-fixed bottom-0 end-0 p-3" id="toastContainer" style="z-index: 1080;"></div>
@endauth

<main class="container-fluid py-3">
    @if ($errors->any())
        <div class="alert alert-danger">
            <ul class="mb-0">
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif
    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @yield('content')
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

@auth
<script>
(() => {
    const badgeEl = document.getElementById('navNotifBadge');
    if (!badgeEl) return;

    const setBadge = (count) => {
        const c = Number(count || 0);
        if (c > 0) {
            badgeEl.textContent = String(c);
            badgeEl.classList.remove('d-none');
        } else {
            badgeEl.classList.add('d-none');
        }
    };

    const poll = async () => {
        try {
            const res = await fetch(`{{ route('notifications.poll') }}`, { headers: { 'Accept': 'application/json' } });
            if (!res.ok) return;
            const data = await res.json();
            setBadge(data.unread_count);
        } catch (e) {}
    };
    poll();
    setInterval(poll, 25000);
})();
</script>
@endauth
@stack('scripts')
</body>
</html>