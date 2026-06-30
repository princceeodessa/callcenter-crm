@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="mb-0">Новая закупка</h4>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchases.kanban') }}">← К канбану</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('purchases.store') }}">
                @csrf
                @include('purchases._form_fields')
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-success">Создать</button>
                    <a class="btn btn-outline-secondary" href="{{ route('purchases.kanban') }}">Отмена</a>
                </div>
            </form>
        </div>
    </div>
@endsection
