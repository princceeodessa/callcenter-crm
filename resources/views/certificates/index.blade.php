@extends('layouts.sneakers')
{{-- «Сертификаты» (10.11.2026): остаток по номиналам, продажа сертификата, журнал. Продажа сертификата — деньги в кассе,
     но не выручка: выручка — когда сертификатом платят за пару (на «Продаже» оплата «Сертификат»). Учёт без номеров. --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $pay = \App\Support\Sales\GiftCertificates::moneyMethods();
    $me = auth()->user();
    $first = $nominals->firstWhere('stock', '>', 0) ?? $nominals->first();
@endphp

@section('title', 'Сертификаты')
@section('ns_sub', 'подарочные сертификаты: продажа и остаток')

@section('content')
@if($sold)
    <div class="ns-flash ns-ok ns-sold" role="status">{!! $ui::icon('check', 'ns-sm') !!}
        <div class="ns-sold-t"><b>Продан сертификат {{ $rub($sold->nominal) }}{{ $sold->qty > 1 ? ' × '.$sold->qty : '' }} — {{ $rub($sold->amount) }} · {{ mb_strtolower($pay[$sold->payment_method] ?? '') }}</b>
            <span>Отдайте клиенту сертификат. Расплатиться им можно на «Продаже» — оплата «Сертификат».</span></div>
    </div>
@endif

<div class="ns-kpis ns-cert-stock">
    @forelse($nominals as $c)
        <div @class(['ns-kpi', 'ns-cert-k', 'ns-dim' => $c->stock <= 0])>
            <div class="ns-kl">{!! $ui::icon('gift', 'ns-sm') !!} Сертификат</div>
            <div class="ns-kv">{{ $rub($c->nominal) }}</div>
            <div class="ns-kd">@if($c->stock > 0)в наличии <b>{{ $c->stock }} шт</b>@else<span class="ns-dn">закончились</span>@endif</div>
        </div>
    @empty
        <div class="ns-panel ns-empty ns-sm" style="grid-column:1/-1">{!! $ui::icon('gift') !!}<b>Номиналов пока нет</b>Руководитель добавляет их внизу страницы</div>
    @endforelse
</div>

@if($nominals->isNotEmpty())
<div class="ns-sec-h">Продать сертификат</div>
<form class="ns-panel ns-cert-sell" method="POST" action="{{ route('certificates.sell') }}" id="nsCertSell">
    @csrf
    <input type="hidden" name="nominal" id="nsCnV" value="{{ old('nominal', $first?->nominal) }}">
    <input type="hidden" name="payment" id="nsCpV" value="{{ old('payment') }}">
    <div class="ns-field"><label>Номинал</label><div class="ns-seg" id="nsCn">
        @foreach($nominals as $c)<button type="button" data-v="{{ $c->nominal }}" data-left="{{ $c->stock }}" @disabled($c->stock <= 0)>{{ $rub($c->nominal) }}</button>@endforeach
    </div></div>
    <div class="ns-sb-row">
        <div class="ns-field"><label>Кол-во</label><div class="ns-step"><button type="button" data-d="-1" aria-label="Меньше">−</button><input name="qty" id="nsCq" type="number" value="{{ old('qty', 1) }}" min="1" max="100"><button type="button" data-d="1" aria-label="Больше">+</button></div></div>
        <div class="ns-field"><label>Оплата</label><div class="ns-seg" id="nsCp">@foreach($pay as $pv => $pl)<button type="button" data-v="{{ $pv }}">{{ $pl }}</button>@endforeach</div></div>
    </div>
    <div class="ns-sb-row2">
        <div class="ns-field"><label>Имя клиента <i>— необязательно</i></label><input name="client_name" value="{{ old('client_name') }}" maxlength="255" autocomplete="off"></div>
        <div class="ns-field"><label>Телефон <i>— необязательно</i></label><input name="client_phone" value="{{ old('client_phone') }}" maxlength="32" inputmode="tel" autocomplete="off"></div>
    </div>
    <div class="ns-cert-go"><span class="ns-muted">Продажа сертификата — не выручка: выручка будет, когда им заплатят за пару.</span>
        <button class="ns-btn ns-primary ns-big" type="submit" id="nsCgo">Продать</button></div>
</form>
@endif

<div class="ns-sec-h">Итоги</div>
<div class="ns-kpis">
    <div class="ns-kpi"><div class="ns-kl">Продано сегодня</div><div class="ns-kv">{{ $rub($today['sold_amount']) }}</div><div class="ns-kd">{{ $today['sold_qty'] }} шт</div></div>
    <div class="ns-kpi"><div class="ns-kl">Продано за месяц</div><div class="ns-kv">{{ $rub($month['sold_amount']) }}</div><div class="ns-kd">{{ $month['sold_qty'] }} шт@foreach($month['by_nominal'] as $nom => $k) · {{ number_format($nom / 1000, 0) }}к × {{ $k }}@endforeach</div></div>
    <div class="ns-kpi"><div class="ns-kl">Оплатили сертификатами за месяц</div><div class="ns-kv">{{ $rub($month['redeemed']) }}</div><div class="ns-kd">{{ $month['redeemed_count'] }} продаж@if($month['burned'] > 0) · сгорело {{ $rub($month['burned']) }}@endif</div></div>
    <div class="ns-kpi"><div class="ns-kl">На руках у клиентов</div><div class="ns-kv">{{ $rub(max(0, $month['outstanding'])) }}</div><div class="ns-kd">проданы, ещё не потрачены</div></div>
</div>

<div class="ns-sec-h">Продажи сертификатов <span class="ns-sm">за 60 дней</span></div>
<div class="ns-panel" style="overflow:hidden;margin-bottom:18px">
    @if($sales->isEmpty())
        <div class="ns-empty ns-sm">{!! $ui::icon('gift') !!}<b>Сертификаты ещё не продавали</b>Продажа — в форме выше</div>
    @else
        <table class="ns-tbl">
            <thead><tr><th>Когда</th><th>Сертификат</th><th class="ns-num">Сумма</th><th>Оплата</th><th>Продавец</th><th>Клиент</th><th></th></tr></thead>
            <tbody>
            @foreach($sales as $s)
                <tr @class(['ns-dim' => $s->cancelled_at])>
                    <td class="ns-muted">{{ $s->sold_at->format('d.m.Y H:i') }}</td>
                    <td><b>{{ $rub($s->nominal) }}</b>{{ $s->qty > 1 ? ' × '.$s->qty : '' }}</td>
                    <td class="ns-num">{{ $rub($s->amount) }}</td>
                    <td>{{ $pay[$s->payment_method] ?? '—' }}</td>
                    <td class="ns-muted">{{ $s->user?->name ?? '—' }}</td>
                    <td class="ns-muted">{{ trim(($s->client_name ?? '').' '.($s->client_phone ?? '')) ?: '—' }}</td>
                    <td class="ns-num">
                        @if($s->cancelled_at)
                            <span class="ns-tag">отменена</span>
                        @elseif($isHead || ((int) $s->user_id === (int) $me->id && $s->sold_at->isToday()))
                            <form method="POST" action="{{ route('certificates.cancel', $s) }}" onsubmit="return confirm('Отменить продажу? Сертификаты вернутся в остаток.')">@csrf<button class="ns-btn ns-sm" type="submit">Отменить</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    @endif
</div>

@if($isHead)
<div class="ns-sec-h">Остаток и номиналы <span class="ns-sm">руководителю</span></div>
<div class="ns-panel ns-cert-adm">
    @foreach($nominals as $c)
        <form method="POST" action="{{ route('certificates.stock') }}" class="ns-cert-row">@csrf
            <input type="hidden" name="nominal" value="{{ $c->nominal }}">
            <b>{{ $rub($c->nominal) }}</b><label>в наличии <input type="number" name="stock" value="{{ $c->stock }}" min="0" max="100000"> шт</label>
            <button class="ns-btn ns-sm" type="submit">Сохранить</button>
        </form>
    @endforeach
    <form method="POST" action="{{ route('certificates.stock') }}" class="ns-cert-row">@csrf
        <b>Новый номинал</b><label><input type="number" name="nominal" min="100" step="100" placeholder="25000" required> ₽</label>
        <label>в наличии <input type="number" name="stock" value="0" min="0" max="100000"> шт</label>
        <button class="ns-btn ns-sm" type="submit">{!! $ui::icon('plus', 'ns-sm') !!}Добавить</button>
    </form>
</div>
@endif
@endsection

@push('scripts')
<script>
(() => {
    const form = document.getElementById('nsCertSell');
    if (!form) return;
    const nv = document.getElementById('nsCnV'), pv = document.getElementById('nsCpV'), q = document.getElementById('nsCq'), go = document.getElementById('nsCgo');
    const nBtns = [...form.querySelectorAll('#nsCn button')], pBtns = [...form.querySelectorAll('#nsCp button')];
    const rub = (v) => Math.round(v).toLocaleString('ru-RU') + ' ₽';
    const upd = () => {
        const b = nBtns.find((x) => x.dataset.v === nv.value);
        const left = b ? +b.dataset.left : 0;
        const k = Math.max(1, Math.min(left || 1, parseInt(q.value, 10) || 1));
        q.value = k; q.max = left || 1;
        nBtns.forEach((x) => x.classList.toggle('ns-on', x === b));
        pBtns.forEach((x) => x.classList.toggle('ns-on', x.dataset.v === pv.value));
        go.textContent = b ? 'Продать за ' + rub(+b.dataset.v * k) : 'Продать';
        go.disabled = !b || left <= 0;
    };
    nBtns.forEach((b) => b.addEventListener('click', () => { nv.value = b.dataset.v; upd(); }));
    pBtns.forEach((b) => b.addEventListener('click', () => { pv.value = b.dataset.v; upd(); }));
    form.querySelectorAll('.ns-step button').forEach((b) => b.addEventListener('click', () => { q.value = (parseInt(q.value, 10) || 1) + (+b.dataset.d); upd(); }));
    q.addEventListener('input', upd);
    form.addEventListener('submit', (e) => {
        if (!pv.value) { e.preventDefault(); ns.toast('Выберите, как клиент заплатил'); return; }
        go.disabled = true;
    });
    if (new URLSearchParams(location.search).has('sold')) history.replaceState(null, '', location.pathname);
    upd();
})();
</script>
@endpush
