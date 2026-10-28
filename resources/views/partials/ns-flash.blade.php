{{-- Сообщения после действий — в каркасе кроссовок «Нордика» (layouts/sneakers): зелёная плашка «готово», красная — ошибки --}}
@if(session('status'))
    <div class="ns-flash ns-ok" role="status">{!! \App\Support\Ui\Sneakers::icon('check', 'ns-sm') !!}<span>{{ session('status') }}</span></div>
@endif
@if($errors->any())
    <div class="ns-flash ns-err" role="alert">{!! \App\Support\Ui\Sneakers::icon('x', 'ns-sm') !!}<span>@foreach($errors->all() as $e){{ $e }}@if(! $loop->last)<br>@endif @endforeach</span></div>
@endif
