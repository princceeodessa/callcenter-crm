@extends('layouts.sneakers')
{{-- Карточка закупки: правка полей, закрытие (архив). Каркас «Нордика», только руководителю. --}}
@section('title', $purchase->title)
@section('ns_sub', 'закупка #'.$purchase->id.' · '.($purchase->stage?->name ?? '—'))
@section('ns_actions')
    <a class="ns-btn" href="{{ route('purchases.kanban') }}">← К закупкам</a>
@endsection

@section('content')
<div class="ns-panel ns-panel-pad ns-pf-panel">
    <div class="ns-pf-tags">
        @if($purchase->is_white)<span class="ns-tag ns-ok">белая</span>@else<span class="ns-tag">серая</span>@endif
        @if($purchase->stocked_at)<span class="ns-tag ns-ok">на складе с {{ $purchase->stocked_at->format('d.m.Y H:i') }}</span>@else<span class="ns-tag ns-warn">ещё не на складе</span>@endif
        @if($purchase->closed_at)<span class="ns-tag">закрыта {{ $purchase->closed_at->format('d.m.Y H:i') }}</span>@endif
        <span class="ns-muted">создана {{ optional($purchase->created_at)->format('d.m.Y H:i') }}</span>
    </div>
    <form method="POST" action="{{ route('purchases.update', $purchase) }}">
        @csrf
        @method('PATCH')
        @include('purchases._form_fields', ['purchase' => $purchase])
        <div class="ns-pf-foot"><a class="ns-btn" href="{{ route('purchases.kanban') }}">Назад</a><button type="submit" class="ns-btn ns-primary">Сохранить</button></div>
    </form>
</div>
@unless($purchase->closed_at)
    <div class="ns-panel ns-panel-pad ns-pf-panel" style="margin-top:14px">
        <form method="POST" action="{{ route('purchases.close', $purchase) }}" onsubmit="return confirm('Закрыть закупку и убрать из канбана? Остаток на складе сохранится.')" class="ns-pf-close">
            @csrf
            <div><b>Закрыть / в архив</b><div class="ns-muted" style="font-size:12.5px;margin-top:2px">Уйдёт из канбана; то, что уже на складе, останется.</div></div>
            <button class="ns-btn ns-danger" type="submit">Закрыть закупку</button>
        </form>
    </div>
@endunless
@endsection
