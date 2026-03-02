<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    @stack('styles')
</head>
<body class="bg-light">
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="{{ route('deals.kanban') }}">{{ config('app.name') }}</a>
        <div class="d-flex gap-2 flex-wrap">
            @auth
                <a class="btn btn-sm btn-outline-light" href="{{ route('deals.kanban') }}">Канбан</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('deals.index') }}">Список</a>
                <a class="btn btn-sm btn-outline-light" href="{{ route('settings.integrations.index') }}">Интеграции</a>
                <a class="btn btn-sm btn-success" href="{{ route('deals.create') }}">+ Сделка</a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="btn btn-sm btn-outline-warning">Выйти</button>
                </form>
            @endauth
        </div>
    </div>
</nav>

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
@stack('scripts')
</body>
</html>