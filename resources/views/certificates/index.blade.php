@extends('layouts.sneakers')
{{-- «Сертификаты» (10.11.2026): остаток по номиналам, продажа сертификата, журнал. Продажа сертификата — деньги в кассе,
     но не выручка: выручка — когда сертификатом платят за пару (на «Продаже» оплата «Сертификат»). Учёт без номеров.
     26.11.2026: обналичивание (клиент сдаёт сертификат — деньги из кассы, сертификат в остаток), сдача с сертификата
     и выручка/маржа пар, оплаченных сертификатами (маржа — руководителю). --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $pay = \App\Support\Sales\GiftCertificates::moneyMethods();
    $me = auth()->user();
    $first = $nominals->firstWhere('stock', '>', 0) ?? $nominals->first();
    // у двух форм одни имена полей: после ошибки старые значения — только той форме, что отправляли
    $isCo = old('_form') === 'cashout';
    $o = fn (string $k, $def = null, bool $co = false) => $isCo === $co ? old($k, $def) : $def;
    $coVerb = fn (?string $m) => \App\Support\Sales\GiftCertificates::changeWord($m);
@endphp

@section('title', 'Сертификаты')
@section('ns_sub', 'подарочные сертификаты: продажа и остаток')

@section('content')
@if($sold && $sold->isCashout())
    <div class="ns-flash ns-ok ns-sold" role="status">{!! $ui::icon('check', 'ns-sm') !!}
        <div class="ns-sold-t"><b>Обналичен сертификат {{ $rub($sold->nominal) }}{{ $sold->qty > 1 ? ' × '.$sold->qty : '' }} — выдано {{ $coVerb($sold->payment_method) }} {{ $rub($sold->amount) }}</b>
            <span>Заберите у клиента сертификат — он вернулся в остаток. Это не выручка и не расход: деньги за сертификат клиент платил раньше.</span></div>
    </div>
@elseif($sold)
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
    <input type="hidden" name="nominal" id="nsCnV" value="{{ $o('nominal', $first?->nominal) }}">
    <input type="hidden" name="payment" id="nsCpV" value="{{ $o('payment') }}">
    <div class="ns-field"><label>Номинал</label><div class="ns-seg" id="nsCn">
        @foreach($nominals as $c)<button type="button" data-v="{{ $c->nominal }}" data-left="{{ $c->stock }}" @disabled($c->stock <= 0)>{{ $rub($c->nominal) }}</button>@endforeach
    </div></div>
    <div class="ns-sb-row">
        <div class="ns-field"><label>Кол-во</label><div class="ns-step"><button type="button" data-d="-1" aria-label="Меньше">−</button><input name="qty" id="nsCq" type="number" value="{{ $o('qty', 1) }}" min="1" max="100"><button type="button" data-d="1" aria-label="Больше">+</button></div></div>
        <div class="ns-field"><label>Оплата</label><div class="ns-seg" id="nsCp">@foreach($pay as $pv => $pl)<button type="button" data-v="{{ $pv }}">{{ $pl }}</button>@endforeach</div></div>
    </div>
    <div class="ns-sb-row2">
        <div class="ns-field"><label>Имя клиента <i>— необязательно</i></label><input name="client_name" value="{{ $o('client_name') }}" maxlength="255" autocomplete="off"></div>
        <div class="ns-field"><label>Телефон <i>— необязательно</i></label><input name="client_phone" value="{{ $o('client_phone') }}" maxlength="32" inputmode="tel" autocomplete="off"></div>
    </div>
    <div class="ns-cert-go"><span class="ns-muted">Продажа сертификата — не выручка: выручка будет, когда им заплатят за пару.</span>
        <button class="ns-btn ns-primary ns-big" type="submit" id="nsCgo">Продать</button></div>
</form>

{{-- обналичивание (26.11.2026): клиент сдаёт сертификат и получает номинал деньгами --}}
<details class="ns-cert-co" @if($isCo) open @endif>
    <summary class="ns-sec-h">Обналичить сертификат <span class="ns-sm">клиент сдаёт сертификат и получает деньги</span></summary>
    <form class="ns-panel ns-cert-sell" method="POST" action="{{ route('certificates.cashout') }}" id="nsCertCo">
        @csrf
        <input type="hidden" name="_form" value="cashout">
        <input type="hidden" name="nominal" id="nsCoNV" value="{{ $o('nominal', $first?->nominal, true) }}">
        <input type="hidden" name="payment" id="nsCoPV" value="{{ $o('payment', 'cash', true) }}">
        <div class="ns-field"><label>Номинал сертификата</label><div class="ns-seg" id="nsCoN">
            @foreach($nominals as $c)<button type="button" data-v="{{ $c->nominal }}">{{ $rub($c->nominal) }}</button>@endforeach
        </div></div>
        <div class="ns-sb-row">
            <div class="ns-field"><label>Кол-во</label><div class="ns-step"><button type="button" data-d="-1" aria-label="Меньше">−</button><input name="qty" id="nsCoQ" type="number" value="{{ $o('qty', 1, true) }}" min="1" max="100"><button type="button" data-d="1" aria-label="Больше">+</button></div></div>
            <div class="ns-field"><label>Как выдали деньги</label><div class="ns-seg" id="nsCoP">@foreach($pay as $pv => $pl)<button type="button" data-v="{{ $pv }}">{{ $pl }}</button>@endforeach</div></div>
        </div>
        <div class="ns-sb-row2">
            <div class="ns-field"><label>Имя клиента <i>— необязательно</i></label><input name="client_name" value="{{ $o('client_name', null, true) }}" maxlength="255" autocomplete="off"></div>
            <div class="ns-field"><label>Телефон <i>— необязательно</i></label><input name="client_phone" value="{{ $o('client_phone', null, true) }}" maxlength="32" inputmode="tel" autocomplete="off"></div>
        </div>
        <div class="ns-cert-go"><span class="ns-muted">Деньги из кассы — не выручка и не расход: за сертификат клиент платил раньше. Сертификат вернётся в остаток. На руках у клиентов сейчас {{ $rub(max(0, $month['outstanding'])) }}.</span>
            <button class="ns-btn ns-big" type="submit" id="nsCoGo">Обналичить</button></div>
    </form>
</details>
@endif

<div class="ns-sec-h">Итоги</div>
<div class="ns-kpis">
    <div class="ns-kpi"><div class="ns-kl">Продано сегодня</div><div class="ns-kv">{{ $rub($today['sold_amount']) }}</div><div class="ns-kd">{{ $today['sold_qty'] }} шт</div></div>
    <div class="ns-kpi"><div class="ns-kl">Продано за месяц</div><div class="ns-kv">{{ $rub($month['sold_amount']) }}</div><div class="ns-kd">{{ $month['sold_qty'] }} шт@foreach($month['by_nominal'] as $nom => $k) · {{ number_format($nom / 1000, 0) }}к × {{ $k }}@endforeach</div></div>
    <div class="ns-kpi"><div class="ns-kl">Оплатили сертификатами за месяц</div><div class="ns-kv">{{ $rub($month['redeemed']) }}</div><div class="ns-kd">{{ $month['redeemed_count'] }} продаж@if($month['change'] > 0) · сдача {{ $rub($month['change']) }}@endif @if($month['burned'] > 0) · сгорело {{ $rub($month['burned']) }}@endif</div></div>
    <div class="ns-kpi"><div class="ns-kl">Обналичили за месяц</div><div class="ns-kv">{{ $rub($month['cashed_amount']) }}</div><div class="ns-kd">{{ $month['cashed_qty'] }} шт · деньги из кассы</div></div>
    @if($seesCost)
        <div class="ns-kpi"><div class="ns-kl">Пары за сертификаты за месяц</div><div class="ns-kv">{{ $rub($month['revenue']) }}</div>
            <div class="ns-kd">маржа <b class="{{ $month['profit'] < 0 ? 'ns-dn' : 'ns-up' }}">{{ $rub($month['profit']) }}</b>@if($month['revenue'] > 0 && $month['no_cost'] === 0) · {{ number_format($month['profit'] / $month['revenue'] * 100, 1, ',', ' ') }}%@endif @if($month['no_cost'] > 0) · без закупа {{ $month['no_cost'] }}@endif</div></div>
    @endif
    <div class="ns-kpi"><div class="ns-kl">На руках у клиентов</div><div class="ns-kv">{{ $rub(max(0, $month['outstanding'])) }}</div><div class="ns-kd">проданы, ещё не потрачены и не обналичены</div></div>
</div>

<div class="ns-sec-h">Журнал сертификатов <span class="ns-sm">продажи и обналичивания за 60 дней</span></div>
<div class="ns-panel" style="overflow:hidden;margin-bottom:18px">
    @if($sales->isEmpty())
        <div class="ns-empty ns-sm">{!! $ui::icon('gift') !!}<b>Сертификаты ещё не продавали и не обналичивали</b>Продажа — в форме выше</div>
    @else
        <table class="ns-tbl">
            <thead><tr><th>Когда</th><th>Операция</th><th>Сертификат</th><th class="ns-num">Касса</th><th>Как</th><th>Продавец</th><th>Клиент</th><th></th></tr></thead>
            <tbody>
            @foreach($sales as $s)
                <tr @class(['ns-dim' => $s->cancelled_at])>
                    <td class="ns-muted">{{ $s->sold_at->format('d.m.Y H:i') }}</td>
                    <td>@if($s->isCashout())<span class="ns-tag ns-bad">Обналичили</span>@else<span class="ns-tag">Продажа</span>@endif</td>
                    <td><b>{{ $rub($s->nominal) }}</b>{{ $s->qty > 1 ? ' × '.$s->qty : '' }}</td>
                    <td class="ns-num">{{ $s->isCashout() ? '−' : '+' }}{{ $rub($s->amount) }}</td>
                    <td>{{ $s->isCashout() ? 'выдали '.$coVerb($s->payment_method) : ($pay[$s->payment_method] ?? '—') }}</td>
                    <td class="ns-muted">{{ $s->user?->name ?? '—' }}</td>
                    <td class="ns-muted">{{ trim(($s->client_name ?? '').' '.($s->client_phone ?? '')) ?: '—' }}</td>
                    <td class="ns-num">
                        @if($s->cancelled_at)
                            <span class="ns-tag">отменено</span>
                        @elseif($isHead || ((int) $s->user_id === (int) $me->id && $s->sold_at->isToday()))
                            <form method="POST" action="{{ route('certificates.cancel', $s) }}" onsubmit="return confirm(@js($s->isCashout() ? 'Отменить обналичивание? Сертификат снова будет у клиента, из остатка он уйдёт.' : 'Отменить продажу? Сертификаты вернутся в остаток.'))">@csrf<button class="ns-btn ns-sm" type="submit">Отменить</button></form>
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

    // обналичивание: номинал × штук, как выдали деньги
    const co = document.getElementById('nsCertCo');
    if (!co) return;
    const cnv = document.getElementById('nsCoNV'), cpv = document.getElementById('nsCoPV'), cq = document.getElementById('nsCoQ'), cgo = document.getElementById('nsCoGo');
    const cnBtns = [...co.querySelectorAll('#nsCoN button')], cpBtns = [...co.querySelectorAll('#nsCoP button')];
    const cupd = () => {
        const k = Math.max(1, Math.min(100, parseInt(cq.value, 10) || 1));
        cq.value = k;
        cnBtns.forEach((x) => x.classList.toggle('ns-on', x.dataset.v === cnv.value));
        cpBtns.forEach((x) => x.classList.toggle('ns-on', x.dataset.v === cpv.value));
        cgo.textContent = cnv.value ? 'Обналичить — выдать ' + rub(+cnv.value * k) : 'Обналичить';
    };
    cnBtns.forEach((b) => b.addEventListener('click', () => { cnv.value = b.dataset.v; cupd(); }));
    cpBtns.forEach((b) => b.addEventListener('click', () => { cpv.value = b.dataset.v; cupd(); }));
    co.querySelectorAll('.ns-step button').forEach((b) => b.addEventListener('click', () => { cq.value = (parseInt(cq.value, 10) || 1) + (+b.dataset.d); cupd(); }));
    cq.addEventListener('input', cupd);
    co.addEventListener('submit', (e) => {
        if (!cnv.value || !cpv.value) { e.preventDefault(); ns.toast('Выберите номинал и как выдали деньги'); return; }
        if (!confirm('Выдать клиенту ' + rub(+cnv.value * (+cq.value || 1)) + ' за сертификат?')) { e.preventDefault(); return; }
        cgo.disabled = true;
    });
    cupd();
})();
</script>
@endpush
