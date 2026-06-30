@extends('layouts.app')

@section('content')
    <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
        <h4 class="mb-0">{{ $purchase->title }}</h4>
        <a class="btn btn-sm btn-outline-secondary" href="{{ route('purchases.kanban') }}">← К канбану</a>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <form method="POST" action="{{ route('purchases.update', $purchase) }}">
                @csrf
                @method('PATCH')
                @include('purchases._form_fields', ['purchase' => $purchase])
                <div class="mt-3 d-flex gap-2">
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                    <a class="btn btn-outline-secondary" href="{{ route('purchases.kanban') }}">Назад</a>
                </div>
            </form>
            @unless($purchase->closed_at)
                <hr>
                <form method="POST" action="{{ route('purchases.close', $purchase) }}" onsubmit="return confirm('Закрыть закупку и убрать из канбана? Остаток на складе сохранится.')">
                    @csrf
                    <button class="btn btn-sm btn-outline-danger">Закрыть / Архив</button>
                    <span class="form-text ms-2">Уйдёт из канбана; то, что уже на складе — останется.</span>
                </form>
            @else
                <hr><div class="text-muted small">Закупка закрыта {{ $purchase->closed_at->format('d.m.Y H:i') }}.</div>
            @endunless
        </div>
    </div>
@endsection
