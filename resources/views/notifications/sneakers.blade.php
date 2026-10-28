@extends('layouts.sneakers')
{{-- Уведомления кроссовочного отдела — каркас «Нордика»: тот же список, что в окне колокольчика (прототип openNotif), страницей
     со всей историей. Маршруты прежние: notifications.read (all=1 — «Прочитать все»). --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
@endphp

@section('title', 'Уведомления')
@section('ns_sub', $unreadCount ? 'непрочитанных: '.$unreadCount : 'всё прочитано')
@section('ns_actions')
    @if($unreadCount && $notifications->isNotEmpty())
        <form method="POST" action="{{ route('notifications.read', ['notification' => $notifications->first(), 'all' => 1]) }}" style="margin:0">
            @csrf
            <button class="ns-btn" type="submit">{!! $ui::icon('check', 'ns-sm') !!}Прочитать все</button>
        </form>
    @endif
@endsection

@section('content')
<div class="ns-panel ns-ntf-page">
    @forelse($notifications as $n)
        @php
            $payload = is_array($n->payload ?? null) ? $n->payload : [];
            $dealId = $payload['deal_id'] ?? null;
            $contextUrl = trim((string) ($payload['context_url'] ?? ''));
            $url = $dealId ? route('deals.show', ['deal' => $dealId]) : ($contextUrl !== '' ? $contextUrl : null);
        @endphp
        <div @class(['ns-ni', 'ns-read' => $n->is_read])>
            <span class="ns-nd"></span>
            <div class="ns-ni-m">
                <b>{{ $n->title }}</b>
                @if($n->body)<p>{{ $n->body }}</p>@endif
                <small>{{ optional($n->created_at)->format('d.m.Y H:i') }}</small>
            </div>
            <div class="ns-ni-a">
                @if($url)<a class="ns-btn ns-sm" href="{{ $url }}">Открыть</a>@endif
                @unless($n->is_read)
                    <form method="POST" action="{{ route('notifications.read', $n) }}">
                        @csrf
                        <button class="ns-btn ns-sm" type="submit" title="Отметить прочитанным">{!! $ui::icon('check', 'ns-sm') !!}</button>
                    </form>
                @endunless
            </div>
        </div>
    @empty
        <div class="ns-empty">{!! $ui::icon('bell') !!}<b>Уведомлений пока нет</b>Здесь будут продажи, низкий остаток и напоминания по делам</div>
    @endforelse
</div>
@if($notifications->hasPages())
    <div class="ns-pager">
        @if($notifications->onFirstPage())<span class="ns-btn ns-sm ns-dis">← Новее</span>@else<a class="ns-btn ns-sm" href="{{ $notifications->previousPageUrl() }}">← Новее</a>@endif
        <span class="ns-muted">стр. {{ $notifications->currentPage() }} из {{ $notifications->lastPage() }}</span>
        @if($notifications->hasMorePages())<a class="ns-btn ns-sm" href="{{ $notifications->nextPageUrl() }}">Старее →</a>@else<span class="ns-btn ns-sm ns-dis">Старее →</span>@endif
    </div>
@endif
@endsection
