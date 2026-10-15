{{--
    Вкладки сводки: «Потолки | SMM-контент | Кроссовки» (как в макете дизайнера). Потолки видит только владелец всех
    бизнесов (users.all_businesses); остальным — «Кроссовки | SMM-контент». SMM — того бизнеса, на котором стоим.
    $biz — ceilings | sneakers, $tab — summary | smm | day («Продажи за день» кроссовок).
--}}
@php
    $tabsUser = auth()->user();
    $tabsBoth = $tabsUser && $tabsUser->role === 'sneaker_owner' && $tabsUser->all_businesses;
    $tabsHouse = '<img class="emoji" src="https://cdn.jsdelivr.net/npm/emoji-datasource-apple@14.0.0/img/apple/64/1f3e0.png" alt="🏠">';
    $tabsShoe = '<img class="emoji" src="https://cdn.jsdelivr.net/npm/emoji-datasource-apple@14.0.0/img/apple/64/1f45f.png" alt="👟">';
@endphp
<div class="seg accent">
    @if($tabsBoth)
        <a href="{{ route('owner.ceilings') }}" class="{{ $biz === 'ceilings' && $tab === 'summary' ? 'active' : '' }}">{!! $tabsHouse !!} Потолки</a>
    @endif
    @if($biz === 'ceilings')
        <a href="{{ route('owner.smm', ['business' => 'ceilings']) }}" class="{{ $tab === 'smm' ? 'active' : '' }}">SMM-контент</a>
        <a href="{{ route('owner.dashboard') }}">{!! $tabsShoe !!} Кроссовки</a>
    @else
        <a href="{{ route('owner.dashboard') }}" class="{{ in_array($tab, ['summary', 'day'], true) ? 'active' : '' }}">{!! $tabsShoe !!} Кроссовки</a>
        <a href="{{ route('owner.smm', ['business' => 'sneakers']) }}" class="{{ $tab === 'smm' ? 'active' : '' }}">SMM-контент</a>
    @endif
</div>
