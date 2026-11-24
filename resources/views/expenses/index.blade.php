@extends('layouts.sneakers')
{{-- «Расходы» (24.11.2026): сотрудник вносит трату магазина — сумма, на что, категория, чем оплачено, фото чека.
     Продавец видит свои траты; руководитель и владелец — все, итоги по категориям и сотрудникам, «к возврату». --}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $cats = \App\Support\Sales\ShopExpenses::CATEGORIES;
    $paid = \App\Support\Sales\ShopExpenses::PAID_FROM;
    $rub = fn ($v) => number_format((float) $v, (float) $v == floor((float) $v) ? 0 : 2, ',', ' ').' ₽';
    $me = auth()->user();
    $pFrom = \Carbon\Carbon::parse($fromValue);
    $pTo = \Carbon\Carbon::parse($toValue);
    $periodLabel = $owedOnly ? 'все невозвращённые' : ($pFrom->isSameDay($pTo) ? $pFrom->format('d.m.Y') : $pFrom->format('d.m').' — '.$pTo->format('d.m.Y'));
    $filters = array_filter(['period' => $period, 'from' => $period === 'custom' ? $fromValue : null, 'to' => $period === 'custom' ? $toValue : null,
        'category' => $category, 'user' => $who]);
    $maxCat = max(1, (float) collect($summary['by_category'])->max());
    $tr = fn (int $n) => $n.' '.(($n % 10 === 1 && $n % 100 !== 11) ? 'трата' : (($n % 10 >= 2 && $n % 10 <= 4 && ($n % 100 < 12 || $n % 100 > 14)) ? 'траты' : 'трат'));
@endphp

@section('title', 'Расходы')
@section('ns_sub', $seesAll ? 'траты магазина — все сотрудники · '.$periodLabel : 'мои траты для магазина · '.$periodLabel)
@section('ns_no_search', '1')
@section('ns_actions')
    <form method="GET" action="{{ route('expenses.index') }}" class="ns-rep-form ns-exp-filter" id="nsExpF">
        <select name="period" class="ns-select" aria-label="Период" id="nsExpP">
            @foreach($periods as $k => $l)<option value="{{ $k }}" @selected($period === $k)>{{ $l }}</option>@endforeach
            <option value="custom" @selected($period === 'custom')>Свои даты…</option>
        </select>
        <span class="ns-exp-dates" @if($period !== 'custom') hidden @endif>
            <input type="date" name="from" value="{{ $fromValue }}" max="{{ now()->toDateString() }}" class="ns-select" aria-label="С">
            <input type="date" name="to" value="{{ $toValue }}" max="{{ now()->toDateString() }}" class="ns-select" aria-label="По">
        </span>
        <select name="category" class="ns-select" aria-label="Категория">
            <option value="">Все категории</option>
            @foreach($cats as $k => $l)<option value="{{ $k }}" @selected($category === $k)>{{ $l }}</option>@endforeach
        </select>
        @if($seesAll)
            <select name="user" class="ns-select" aria-label="Сотрудник">
                <option value="">Все сотрудники</option>
                @foreach($people as $p)<option value="{{ $p->id }}" @selected($who === $p->id)>{{ $p->name }}</option>@endforeach
            </select>
        @endif
        <button class="ns-btn" type="submit">Показать</button>
    </form>
@endsection

@section('content')
@if($added)
    <div class="ns-flash ns-ok ns-sold" role="status">{!! $ui::icon('check', 'ns-sm') !!}
        <div class="ns-sold-t"><b>Расход внесён: {{ $rub($added->amount) }} — {{ $added->description }}</b>
            <span>{{ $cats[$added->category] ?? '' }} · {{ mb_strtolower($paid[$added->paid_from] ?? '') }}@if(! $seesAll) · руководитель увидит его в «Расходах»@endif</span></div>
    </div>
@endif

<div class="ns-sec-h">Добавить расход</div>
<form class="ns-panel ns-exp-form" method="POST" action="{{ route('expenses.store') }}" enctype="multipart/form-data" id="nsExpAdd">
    @csrf
    <input type="hidden" name="paid_from" id="nsExpPaid" value="{{ old('paid_from') }}">
    <div class="ns-exp-row">
        <div class="ns-field"><label>Сумма, ₽</label><input name="amount" type="number" min="1" step="0.01" inputmode="decimal" value="{{ old('amount') }}" required placeholder="0"></div>
        <div class="ns-field ns-exp-what"><label>На что потратили</label><input name="description" value="{{ old('description') }}" maxlength="255" required placeholder="Пакеты 100 шт, такси до склада, аренда за ноябрь…" autocomplete="off"></div>
        <div class="ns-field"><label>Дата</label><input name="spent_on" type="date" value="{{ old('spent_on', now()->toDateString()) }}" max="{{ now()->toDateString() }}" required></div>
    </div>
    <div class="ns-exp-row ns-exp-row2">
        <div class="ns-field"><label>Категория</label>
            <select name="category" required>
                <option value="" disabled @selected(! old('category'))>Выберите…</option>
                @foreach($cats as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach
            </select>
        </div>
        <div class="ns-field"><label>Фото чека <i>— необязательно</i></label><input type="file" name="receipt" accept="image/*,application/pdf"></div>
    </div>
    <div class="ns-field"><label>Чем заплатили</label><div class="ns-seg ns-exp-paid" id="nsExpPaidSeg">
        @foreach($paid as $k => $l)<button type="button" data-v="{{ $k }}" @class(['ns-on' => old('paid_from') === $k])>{{ $l }}</button>@endforeach
    </div></div>
    <div class="ns-cert-go"><span class="ns-muted">«Свои деньги — вернуть»: руководитель увидит сумму к возврату и отметит, когда вернёт.</span>
        <button class="ns-btn ns-primary ns-big" type="submit" id="nsExpGo">Внести расход</button></div>
</form>

<div class="ns-sec-h">Итоги <span class="ns-sm">{{ $periodLabel }}</span></div>
<div class="ns-kpis">
    @if($seesAll)
        <div class="ns-kpi"><div class="ns-kl">Расходы за период</div><div class="ns-kv">{{ $rub($summary['total']) }}</div><div class="ns-kd">{{ $tr($summary['count']) }}</div></div>
        <div class="ns-kpi"><div class="ns-kl">Из кассы</div><div class="ns-kv">{{ $rub($summary['by_paid']['cash'] ?? 0) }}</div><div class="ns-kd">наличные взяли из кассы</div></div>
        <div class="ns-kpi"><div class="ns-kl">Карта / счёт магазина</div><div class="ns-kv">{{ $rub($summary['by_paid']['card'] ?? 0) }}</div><div class="ns-kd">своими деньгами — {{ $rub($summary['by_paid']['own'] ?? 0) }}</div></div>
        <a class="ns-kpi ns-exp-owed" href="{{ route('expenses.index', ['owed' => 1]) }}"><div class="ns-kl">К возврату сотрудникам</div><div @class(['ns-kv', 'ns-dn' => $summary['owed'] > 0])>{{ $rub($summary['owed']) }}</div><div class="ns-kd">{{ $summary['owed_count'] > 0 ? $tr($summary['owed_count']).' своими деньгами — показать' : 'всем всё вернули' }}</div></a>
    @else
        <div class="ns-kpi"><div class="ns-kl">Мои расходы</div><div class="ns-kv">{{ $rub($summary['total']) }}</div><div class="ns-kd">{{ $tr($summary['count']) }}</div></div>
        <div class="ns-kpi"><div class="ns-kl">Мне должны вернуть</div><div @class(['ns-kv', 'ns-dn' => $summary['owed'] > 0])>{{ $rub($summary['owed']) }}</div><div class="ns-kd">{{ $summary['owed_count'] > 0 ? $tr($summary['owed_count']).' своими деньгами' : 'долгов нет' }}</div></div>
    @endif
</div>

@if($seesAll && $summary['count'] > 0)
<div class="ns-grid2 ns-exp-grid">
    <div class="ns-panel">
        <div class="ns-panel-pad"><b>По категориям</b></div>
        <table class="ns-tbl">
            <tbody>
            @foreach($summary['by_category'] as $k => $sum)
                <tr><td><a href="{{ route('expenses.index', array_merge($filters, ['category' => $k])) }}">{{ $cats[$k] ?? $k }}</a></td>
                    <td class="ns-exp-barc"><span class="ns-exp-bar" style="width:{{ max(3, round($sum / $maxCat * 100)) }}%"></span></td>
                    <td class="ns-num"><b>{{ $rub($sum) }}</b></td>
                    <td class="ns-num ns-muted">{{ $summary['total'] > 0 ? round($sum / $summary['total'] * 100) : 0 }}%</td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div class="ns-panel">
        <div class="ns-panel-pad"><b>Кто внёс</b></div>
        <table class="ns-tbl">
            <tbody>
            @foreach($byUser as $u)
                <tr><td><a href="{{ route('expenses.index', array_merge($filters, ['user' => $u['id']])) }}">{{ $u['name'] }}</a></td>
                    <td class="ns-num ns-muted">{{ $tr($u['count']) }}</td>
                    <td class="ns-num"><b>{{ $rub($u['total']) }}</b></td></tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif

<div class="ns-sec-h">{{ $owedOnly ? 'К возврату сотрудникам' : ($seesAll ? 'Все траты' : 'Мои траты') }}
    @if($category || $who || $owedOnly)<a class="ns-sm" href="{{ route('expenses.index', ['period' => $period] + ($period === 'custom' ? ['from' => $fromValue, 'to' => $toValue] : [])) }}">сбросить фильтр</a>@endif</div>
<div class="ns-panel" style="overflow:auto;margin-bottom:18px">
    @if($list->isEmpty())
        <div class="ns-empty ns-sm">{!! $ui::icon('wallet') !!}<b>{{ $owedOnly ? 'Возвращать никому ничего не нужно' : 'Трат за этот период нет' }}</b>{{ $owedOnly ? '' : 'Новый расход — в форме выше' }}</div>
    @else
        <table class="ns-tbl ns-exp-tbl">
            <thead><tr><th>Дата</th><th class="ns-num">Сумма</th><th>На что</th><th>Категория</th><th>Чем заплатили</th>@if($seesAll)<th>Внёс</th>@endif<th>Чек</th><th></th></tr></thead>
            <tbody>
            @foreach($list as $e)
                <tr>
                    <td class="ns-muted" title="внесено {{ $e->created_at?->format('d.m.Y H:i') }}">{{ $e->spent_on->format('d.m.Y') }}</td>
                    <td class="ns-num"><b>{{ $rub($e->amount) }}</b></td>
                    <td class="ns-exp-d">{{ $e->description }}</td>
                    <td class="ns-muted">{{ $cats[$e->category] ?? $e->category }}</td>
                    <td>
                        @if($e->paid_from === 'own')
                            @if($e->reimbursed_at)
                                <span class="ns-tag ns-ok" title="{{ $e->reimburser?->name }}">свои · вернули {{ $e->reimbursed_at->format('d.m') }}</span>
                            @else
                                <span class="ns-tag ns-warn">свои · вернуть</span>
                            @endif
                            @if($manages)
                                <form method="POST" action="{{ route('expenses.reimburse', $e) }}" class="ns-exp-inl">@csrf
                                    @if($e->reimbursed_at)<input type="hidden" name="undo" value="1"><button class="ns-btn ns-sm" type="submit" title="Снять отметку">↺</button>
                                    @else<button class="ns-btn ns-sm" type="submit">Вернули</button>@endif
                                </form>
                            @endif
                        @else
                            {{ $paid[$e->paid_from] ?? $e->paid_from }}
                        @endif
                    </td>
                    @if($seesAll)<td class="ns-muted">{{ $e->user?->name ?? '—' }}</td>@endif
                    <td>@if($e->receipt_path)<a href="{{ route('expenses.receipt', $e) }}" target="_blank" rel="noopener">{!! $ui::icon('cam', 'ns-sm') !!} чек</a>@else<span class="ns-muted">—</span>@endif</td>
                    <td class="ns-num">
                        @if(\App\Support\Sales\ShopExpenses::canDelete($me, $e))
                            <form method="POST" action="{{ route('expenses.destroy', $e) }}" onsubmit="return confirm('Удалить трату {{ $rub($e->amount) }} — «{{ addslashes($e->description) }}»?')">@csrf @method('DELETE')
                                <button class="ns-btn ns-sm ns-icon-only" type="submit" title="Удалить" aria-label="Удалить">{!! $ui::icon('x', 'ns-sm') !!}</button></form>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
            <tfoot><tr><td>Итого</td><td class="ns-num"><b>{{ $rub($list->sum('amount')) }}</b></td><td colspan="{{ $seesAll ? 6 : 5 }}" class="ns-muted">{{ $tr($list->count()) }}{{ $list->count() >= 500 ? ' (показаны первые 500)' : '' }}</td></tr></tfoot>
        </table>
    @endif
</div>
@endsection

@push('scripts')
<script>
(() => {
    const f = document.getElementById('nsExpF'), p = document.getElementById('nsExpP');
    if (f && p) p.addEventListener('change', () => {
        const custom = p.value === 'custom';
        f.querySelector('.ns-exp-dates').hidden = !custom;
        if (!custom) f.submit();
    });
    const form = document.getElementById('nsExpAdd');
    if (!form) return;
    const pv = document.getElementById('nsExpPaid'), btns = [...form.querySelectorAll('#nsExpPaidSeg button')];
    btns.forEach((b) => b.addEventListener('click', () => { pv.value = b.dataset.v; btns.forEach((x) => x.classList.toggle('ns-on', x === b)); }));
    form.addEventListener('submit', (e) => {
        if (!pv.value) { e.preventDefault(); ns.toast('Выберите, чем заплатили'); return; }
        document.getElementById('nsExpGo').disabled = true;
    });
    if (new URLSearchParams(location.search).has('added')) history.replaceState(null, '', location.pathname);
})();
</script>
@endpush
