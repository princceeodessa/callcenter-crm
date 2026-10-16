@extends('layouts.app')

{{-- Сделки за числом отчёта отдельной страницей (без скриптов; на странице отчёта они открываются окном). --}}
@section('content')
<div class="mb-3"><a href="{{ $backUrl }}" class="btn btn-sm btn-outline-secondary">← к отчёту</a></div>
<div class="card shadow-sm">
  <div class="card-body">
    @include('reports._drill')
  </div>
</div>
@endsection
