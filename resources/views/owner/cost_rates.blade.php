@extends('layouts.owner')

{{--
    Ставки для строк договоров без цены (App\Services\Owner\ContractProfitFill): в 1С у работы нет ставки монтажникам
    или у товара — цены закупки, CRM БлагоДар их расход не считает. Здесь руководство ставит свою ставку — ₽ за единицу
    строки или % от её суммы, — и прибыль договоров на сводке досчитывается по ней; пусто — по средней доле.
--}}
@php
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $q = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ' '), '0'), ',');
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $sharePct = fn ($v) => number_format((float) $v * 100, 1, ',', ' ').'%';
    $rows = $positions['rows'];
    $shares = $positions['shares'];
    $at = $positions['at'];
    $since = $positions['since'] ? \Carbon\Carbon::parse($positions['since']) : null;
    $back = route('owner.ceilings', $periodQuery);
    $rated = collect($rows)->whereNotNull('rate')->count();
    $periodCost = collect($rows)->sum('period_cost');
    $inputValue = fn ($r) => $r['rate'] ? rtrim(rtrim(number_format((float) $r['rate']['value'], 2, ',', ''), '0'), ',') : '';
@endphp

@section('title', 'Ставки для строк без цены')
@section('subtitle')Потолки · прибыль договоров · строки, расход которых в 1С не посчитан@endsection

@section('nav')
    <a href="{{ $back }}"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg> Сводка</a>
    <a href="{{ $back }}#profit"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 1v22M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg> Прибыль договоров</a>
    <a href="#top" class="active"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 7h16M4 12h10M4 17h7"/></svg> Ставки без цены</a>
@endsection

@section('content')
<div id="top"></div>
@if(session('status'))
    <div class="card empty-card rates-ok">{{ session('status') }}</div>
@endif

<div class="card rates-intro">
    <p>В 1С у этих работ нет ставки монтажникам, у товаров — цены закупки, и CRM БлагоДар считает их расход нулём: прибыль там завышена.
        Поставьте ставку — <b>₽ за единицу</b> строки (за м², п.м., штуку) или <b>% от суммы строки</b>, — и сводка досчитает прибыль по ней.
        Пока ставки нет, расход берётся средней долей: работы — {{ $sharePct($shares['work'] ?? 0) }} цены, товары — {{ $sharePct($shares['goods'] ?? 0) }}
        (так в среднем выходит у строк, где расход посчитан).</p>
    <p class="muted">В самой CRM БлагоДар и в 1С ничего не меняется: когда ставку заведут там, строка уйдёт из этого списка.
        @if($at)Строки из БлагоДар на {{ $at->format($at->isToday() ? 'H:i' : 'd.m.Y H:i') }}@if($since), неполные договоры с {{ $since->format('d.m.Y') }}@endif.@endif</p>
</div>

@if(! $at)
    <div class="card empty-card">Строк из CRM БлагоДар пока нет — выгрузка ещё не собрана.</div>
@elseif(count($rows) === 0)
    <div class="card empty-card">У всех договоров расход посчитан — ставить нечего.</div>
@else
    <form method="POST" action="{{ route('owner.ceilings.rates') }}" id="ratesForm">
        @csrf
        @foreach($periodQuery as $k => $v)
            <input type="hidden" name="{{ $k }}" value="{{ $v }}">
        @endforeach
        <input type="hidden" name="items" id="ratesItems" value="[]">
        <div class="card tbl-card">
            <div class="tbl-head"><h2>Строки без цены</h2>
                <span class="note">{{ $n(count($rows)) }} позиций · своя ставка у {{ $n($rated) }} · за {{ $periodLabel }} досчитано {{ $money($periodCost) }}</span></div>
            <div class="tbl-wrap"><table class="t rates-t">
                <thead><tr>
                    <th>Позиция</th>
                    <th class="num" title="договоров с этой строкой: за выбранный период / всего в списке">Договоров</th>
                    <th class="num">Кол-во</th>
                    <th class="num">Сумма строк</th>
                    <th class="num" title="сколько расхода досчитано по этой позиции за всё в списке">Расход</th>
                    <th>Ставка</th>
                </tr></thead>
                <tbody>
                @foreach($rows as $r)
                    <tr data-kind="{{ $r['kind'] }}" data-name="{{ $r['name'] }}" data-unit="{{ $r['unit'] }}" data-value="{{ $inputValue($r) }}" data-mode="{{ $r['rate']['mode'] ?? 'unit' }}">
                        <td class="rates-name"><span class="tag {{ $r['kind'] === 'goods' ? 'amber' : 'gray' }}">{{ $r['kind'] === 'goods' ? 'товар' : 'работа' }}</span> {{ $r['name'] }}
                            @if($r['contracts'] === 0)<span class="muted">· в 1С уже посчитана</span>@endif</td>
                        <td class="num">{{ $r['period_contracts'] }} / {{ $r['contracts'] }}</td>
                        <td class="num">{{ $r['qty'] > 0 ? $q($r['qty']).' '.$r['unit'] : '—' }}</td>
                        <td class="num">{{ $r['sum'] > 0 ? $money($r['sum']) : '—' }}</td>
                        <td class="num">{{ $r['sum'] > 0 ? $money($r['cost']) : '—' }}<small class="muted">{{ $r['rate'] ? 'ставка' : ($r['sum'] > 0 ? 'доля '.$sharePct($r['share']) : '') }}</small></td>
                        <td class="rates-in">
                            <input type="text" inputmode="decimal" value="{{ $inputValue($r) }}" placeholder="—" aria-label="Ставка: {{ $r['name'] }}">
                            <select aria-label="Как считать">
                                <option value="unit" @selected(($r['rate']['mode'] ?? 'unit') === 'unit')>₽ за {{ $r['unit'] !== '' ? mb_strtolower($r['unit']) : 'ед.' }}</option>
                                <option value="share" @selected(($r['rate']['mode'] ?? '') === 'share')>% от суммы</option>
                            </select>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </div>
        <div class="rates-bar"><span class="note" id="ratesDirty">Изменений нет</span><button type="submit" class="export" id="ratesSave" disabled>Сохранить ставки</button></div>
    </form>
@endif
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('ratesForm');
    if (!form) return;
    var save = document.getElementById('ratesSave'), dirty = document.getElementById('ratesDirty');
    var rows = [].slice.call(form.querySelectorAll('tbody tr'));
    var changed = function () {
        return rows.filter(function (tr) {
            var v = tr.querySelector('input').value.trim(), m = tr.querySelector('select').value;
            return v !== tr.dataset.value || (v !== '' && m !== tr.dataset.mode);
        });
    };
    var mark = function () {
        var c = changed();
        rows.forEach(function (tr) { tr.classList.toggle('rates-dirty', c.indexOf(tr) >= 0); });
        save.disabled = c.length === 0;
        dirty.textContent = c.length ? 'Изменено строк: ' + c.length : 'Изменений нет';
    };
    form.addEventListener('input', mark);
    form.addEventListener('change', mark);
    form.addEventListener('submit', function () {
        document.getElementById('ratesItems').value = JSON.stringify(changed().map(function (tr) {
            return { kind: tr.dataset.kind, name: tr.dataset.name, unit: tr.dataset.unit, value: tr.querySelector('input').value.trim(), mode: tr.querySelector('select').value };
        }));
    });
    window.addEventListener('beforeunload', function (e) { if (changed().length && !form.dataset.sending) { e.preventDefault(); e.returnValue = ''; } });
    form.addEventListener('submit', function () { form.dataset.sending = '1'; });
})();
</script>
@endpush
