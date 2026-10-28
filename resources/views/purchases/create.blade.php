@extends('layouts.sneakers')
{{-- Новая закупка (полная форма; быстрое окно — на канбане закупок). Каркас «Нордика», только руководителю. --}}
@section('title', 'Новая закупка')
@section('ns_sub', 'закупки')
@section('ns_actions')
    <a class="ns-btn" href="{{ route('purchases.kanban') }}">← К закупкам</a>
@endsection

@section('content')
<div class="ns-panel ns-panel-pad ns-pf-panel">
    <form method="POST" action="{{ route('purchases.store') }}">
        @csrf
        @include('purchases._form_fields')
        <div class="ns-pf-foot"><a class="ns-btn" href="{{ route('purchases.kanban') }}">Отмена</a><button type="submit" class="ns-btn ns-primary">Создать</button></div>
    </form>
</div>
@endsection
