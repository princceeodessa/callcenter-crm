@extends('layouts.sneakers')
{{--
    «Продажа» кроссовочного отдела — каркас «Нордика» (прототип: PAGES.sale, снимки 01, 02, 20, 21). Слева список моделей с
    плитками размеров, справа «Последние продажи» по дням; размер → лист продажи снизу → «Продать» (та же форма
    sale.quick.store, что и раньше: сделка, списание, уведомление руководителю, чек). Белые пары продаются через 1С —
    как прежде, плитка только с белыми помечена «1С», продать её здесь нельзя. Закупка и прибыль — только руководителю.
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $n = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $sgn = fn ($v) => ($v < 0 ? '−' : '+').number_format(abs((float) $v), 0, ',', ' ').' ₽';
    $plural = function (int $k, string $one, string $few, string $many) {
        $m10 = $k % 10; $m100 = $k % 100;
        return ($m10 === 1 && $m100 !== 11) ? $one : (($m10 >= 2 && $m10 <= 4 && ($m100 < 12 || $m100 > 14)) ? $few : $many);
    };
    $mon = ['января', 'февраля', 'марта', 'апреля', 'мая', 'июня', 'июля', 'августа', 'сентября', 'октября', 'ноября', 'декабря'];
    $dayName = function (\Carbon\Carbon $d) use ($mon) {
        $diff = (int) $d->copy()->startOfDay()->diffInDays(now()->startOfDay());
        return ($diff === 0 ? 'Сегодня · ' : ($diff === 1 ? 'Вчера · ' : '')).$d->day.' '.$mon[$d->month - 1];
    };
    $srcColor = ['Avito' => 'var(--av)', 'ВК' => 'var(--vk)', 'Instagram' => 'var(--ig)', 'Telegram' => 'var(--tg)', 'Сайт' => 'var(--site)',
        'Сарафан' => 'var(--lemana)', 'Магазин' => 'var(--accent)', 'Другое' => '#7b8494'];
    $noPhoto = '<svg class="ns-icon" viewBox="0 0 24 24" style="color:var(--faint)"><path d="M3 16h15.5a2.5 2.5 0 000-5H14l-2.2-3.3a1.5 1.5 0 00-1.3-.7H9L7.5 10 4 9.5 3 12z"/><path d="M3 16v2h18"/></svg>';
    $pimg = fn (?string $url, string $brand, string $cls = '') => '<div class="ns-pimg'.($cls ? ' '.$cls : '').'">'.($url
        ? '<img src="'.e($url).'" alt="" loading="lazy" data-b="'.e($brand).'">'
        : ($brand !== '' ? '<span class="ns-phb">'.e(mb_strtoupper(mb_substr($brand, 0, 2))).'</span>' : $noPhoto)).'</div>';
    $q = (string) request('q', '');
    $groups = $recentSales->groupBy(fn ($s) => $s['at']->toDateString());
    $todayKey = now()->toDateString();
    $allModels = count($products);
    // окно продажи (Открыть) — данные строк «Последних продаж»
    $saleJs = $recentSales->map(fn ($s) => [
        'id' => $s['id'], 'name' => $s['name'], 'size' => $s['size'], 'qty' => $s['qty'], 'amount' => $s['amount'], 'cost' => $s['cost'],
        'profit' => $s['profit'], 'source' => $s['source'], 'pay' => $s['payment'] ? (\App\Models\Deal::PAYMENT_METHODS[$s['payment']] ?? null) : null,
        'seller' => $s['seller'], 'when' => $s['at']->format('d.m.Y H:i'), 'image' => $s['image'],
        'receipt' => route('deals.receipt', $s['id']), 'show' => route('deals.show', $s['id']), 'return' => route('deals.return', $s['id']),
    ])->values();
@endphp

@section('title', 'Продажа')
@section('ns_sub', 'модель → размер → «Продать»')
@section('ns_no_search', '1')
@section('ns_no_sell', '1')

@section('content')
<div class="ns-sale-grid">
<div>
    @if($done)
        {{-- продажа оформлена — чек и сделка под рукой --}}
        <div class="ns-flash ns-ok ns-sold" role="status">{!! $ui::icon('check', 'ns-sm') !!}
            <div class="ns-sold-t"><b>Продано: {{ $done['name'] }} × {{ $done['qty'] }}@if($done['amount'] !== null) — {{ $rub($done['amount']) }}@endif · сделка #{{ $done['deal_id'] }}</b>
                @if(isset($done['profit']) && $done['profit'] !== null)<span>прибыль <b class="{{ $done['profit'] < 0 ? 'ns-dn' : 'ns-up' }}">{{ $sgn($done['profit']) }}</b>@if(! empty($done['margin'])) · маржа {{ $done['margin'] }}%@endif</span>@endif
                @if(! empty($done['low']))<span class="ns-sold-low">Этот размер закончился — сообщите руководителю.</span>@endif
            </div>
            <a class="ns-btn ns-sm" href="{{ route('deals.receipt', $done['deal_id']) }}" target="_blank" rel="noopener"><svg class="ns-icon ns-sm" viewBox="0 0 24 24"><path d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg>Чек</a>
            <a class="ns-btn ns-sm" href="{{ route('deals.show', $done['deal_id']) }}">Открыть сделку</a>
        </div>
    @endif
    @if(request()->filled('scan'))
        <div class="ns-flash ns-info" role="status">{!! $ui::icon('scan', 'ns-sm') !!}<span>Отсканировано: <b>{{ $q }}</b> — открыта продажа.</span>
            <a class="ns-btn ns-sm" href="{{ route('scan', ['code' => (string) request('scan'), 'info' => 1]) }}">Карточка товара</a></div>
    @endif

    {{-- подсказка «как продать» (можно скрыть — запомнится в браузере) --}}
    <div class="ns-ssteps" id="nsSteps" hidden>
        @foreach([['Найдите модель', 'поиск или сканер', 'Модель'], ['Нажмите размер', 'в строке модели', 'Размер'], ['Проверьте цену', 'и нажмите «Продать»', 'Продать']] as $i => [$t, $s, $short])
            @if($i)<span class="ns-ss-line"></span>@endif
            <div class="ns-ss"><i>{{ $i + 1 }}</i><span><b><span class="ns-ss-l">{{ $t }}</span><span class="ns-ss-s">{{ $short }}</span></b><small>{{ $s }}</small></span></div>
        @endforeach
        <button class="ns-ib" type="button" id="nsStepsHide" title="Скрыть подсказку">{!! $ui::icon('x') !!}</button>
    </div>

    <div class="ns-bigsearch">{!! $ui::icon('scan') !!}<input id="nsSq" placeholder="Название модели или артикул — можно сканером" value="{{ $q }}" autocomplete="off" data-scan="own" aria-label="Поиск модели или артикула"><span class="ns-scanok"><i></i>сканер готов</span></div>

    <div class="ns-brands" id="nsBrands">
        <button class="ns-chipf ns-on" type="button" data-b="all">Все <span class="ns-cn">{{ $n($allModels) }}</span></button>
        @foreach($brands as $brand => $cnt)
            <button class="ns-chipf" type="button" data-b="{{ $brand }}">{{ $brand }} <span class="ns-cn">{{ $n($cnt) }}</span></button>
        @endforeach
        @if($otherBrands > 0)
            <button class="ns-chipf" type="button" data-b="__other">Другие <span class="ns-cn">{{ $n($otherBrands) }}</span></button>
        @endif
        <span class="ns-vsp"></span>
        <button class="ns-chipf ns-on" type="button" id="nsOnlyStock"><span class="ns-sw"></span>Только в наличии</button>
    </div>

    <div class="ns-plist-h"><b id="nsPlCnt"></b><span class="ns-lgd"><span><i></i>2 пары и больше</span><span><i class="ns-a"></i>последняя пара</span><span><i class="ns-x"></i>нет в наличии</span></span></div>

    <div id="nsPlist">
        @foreach($products as $pi => $p)
            @php
                $brandLbl = $p['brand_key'];
            @endphp
            <div class="ns-prow" data-pid="{{ $pi }}" data-brand="{{ $p['brand_chip'] }}" data-stock="{{ $p['total_available'] }}" data-search="{{ mb_strtolower($p['name'].' '.$p['article'].' '.$p['brand']) }}">
                {!! $pimg($p['image_url'], $p['brand_key']) !!}
                <div class="ns-pinfo">
                    <div class="ns-pname">{{ $p['name'] }}</div>
                    <div class="ns-part">@if($p['article'] !== '')<span class="ns-mono">{{ $p['article'] }}</span>·@endif<span>{{ $brandLbl }}</span></div>
                    <div class="ns-pprice">@if($p['price'] !== null){{ $p['price_from'] ? 'от ' : '' }}{{ $rub($p['price']) }}@else<span class="ns-muted">цена не задана</span>@endif
                        <span @class(['ns-tag', 'ns-warn' => $p['total_available'] <= 2])>{{ $p['total_available'] }} {{ $plural($p['total_available'], 'пара', 'пары', 'пар') }}</span></div>
                </div>
                <div class="ns-sizes">
                    @foreach($p['sizes'] as $s)
                        @php
                            $av = max(0, $s['available']);
                            $onlyWhite = $s['white'] > 0 && $s['other'] <= 0;
                            $wTip = $s['white'] > 0 ? ($s['other'] > 0 ? 'Белых '.$s['white'].', серых '.$s['other'].' — здесь продаётся серая, белые — через 1С' : 'Белая пара — продаётся через 1С (чек на кассе)') : '';
                        @endphp
                        @if($av > 0)
                            <button type="button" @class(['ns-sz', 'ns-low' => $av === 1]) data-item="{{ $s['id'] }}" data-size="{{ $s['size'] }}" data-price="{{ $s['price'] ?? '' }}" data-avail="{{ $av }}" data-white="{{ $s['white'] }}" data-other="{{ $s['other'] }}" @if($s['cost'] !== null) data-cost="{{ $s['cost'] }}" @endif @if($wTip) title="{{ $wTip }}" @endif><b>{{ $s['size'] }}</b><small><i></i>{{ $av }} шт</small>@if($onlyWhite)<span class="ns-w1c">1С</span>@endif</button>
                        @else
                            <button type="button" class="ns-sz ns-off" disabled title="Нет в наличии"><b>{{ $s['size'] }}</b><small><i></i>нет</small></button>
                        @endif
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>
    <div class="ns-panel ns-empty" id="nsPEmpty" hidden>{!! $ui::icon('search') !!}<b>Ничего не нашлось</b>Проверьте артикул или снимите фильтр «Только в наличии» — Enter откроет поиск по коду</div>
    <div class="ns-list-note"></div>
</div>

<aside class="ns-recent ns-panel" id="nsRecent">
    <div class="ns-rc-h"><b>Последние продажи</b><span class="ns-tag">{{ $recentSales->count() }}</span>
        <div class="ns-seg" id="nsRcSeg"><button type="button" class="ns-on" data-m="all">Все</button><button type="button" data-m="today">Сегодня</button></div></div>
    <div class="ns-rc-sum">
        <div><span>Выручка сегодня</span><b>{{ $rub($todaySum['revenue']) }}</b></div>
        <div><span>Пар</span><b>{{ $todaySum['pairs'] }}</b></div>
        @if($seesCost)
            <div><span>Прибыль</span><b class="{{ $todaySum['profit'] < 0 ? 'ns-dn' : ($todaySum['profit'] > 0 ? 'ns-up' : '') }}">{{ $todaySum['profit'] ? $sgn($todaySum['profit']) : '0 ₽' }}</b></div>
        @else
            <div><span>Продаж</span><b>{{ $todaySum['count'] }}</b></div>
        @endif
    </div>
    <div class="ns-rc-list" id="nsRcList">
        @forelse($groups as $day => $list)
            <div class="ns-rc-group" data-today="{{ $day === $todayKey ? 1 : 0 }}">
                <div class="ns-rc-day">{{ $dayName($list->first()['at']) }}<b>{{ $rub($list->sum('amount')) }}</b></div>
                @foreach($list as $s)
                    <div class="ns-rrow" data-sale="{{ $s['id'] }}" title="Открыть сделку #{{ $s['id'] }}">
                        {!! $pimg($s['image'], $s['brand'], 'ns-xs') !!}
                        <div class="ns-rr-m"><div class="ns-rr-n">{{ $s['name'] }}</div>
                            <div class="ns-rr-s">{{ $s['at']->format('H:i') }}@if($s['size'] !== null)<span class="ns-rr-sz">р. {{ $s['size'] }}{{ $s['qty'] > 1 ? ' × '.$s['qty'] : '' }}</span>@endif
                                @if(! $s['amount'])<span class="ns-tag ns-warn">цена не указана</span>@endif</div></div>
                        <div class="ns-rr-r"><b>{{ $rub($s['amount']) }}</b>@if($s['profit'] !== null)<small class="{{ $s['profit'] < 0 ? 'ns-dn' : 'ns-up' }}">{{ $sgn($s['profit']) }}</small>@endif</div>
                        <div class="ns-rr-a">
                            <a class="ns-ib" title="Чек" href="{{ route('deals.receipt', $s['id']) }}" target="_blank" rel="noopener" data-print><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9V3h12v6M6 18H4a2 2 0 01-2-2v-5a2 2 0 012-2h16a2 2 0 012 2v5a2 2 0 01-2 2h-2"/><rect x="6" y="14" width="12" height="7" rx="1"/></svg></a>
                            <button class="ns-ib" type="button" title="Открыть сделку"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 3h6v6M10 14L21 3M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/></svg></button>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="ns-empty ns-sm">{!! $ui::icon('sell') !!}<b>Продаж пока нет</b>Выберите размер слева</div>
        @endforelse
        <div class="ns-empty ns-sm" id="nsRcNoToday" hidden>{!! $ui::icon('sell') !!}<b>Сегодня продаж ещё не было</b>Выберите размер слева</div>
        <a class="ns-rc-more" href="{{ route('sale.day') }}">Все продажи за день →</a>
    </div>
</aside>
</div>

{{-- лист продажи (снизу): размер выбран — проверить цену и «Продать» --}}
<form class="ns-sellbar" id="nsSellbar" method="POST" action="{{ route('sale.quick.store') }}" hidden>
    @csrf
    <input type="hidden" name="warehouse_item_id" id="nsSbItem">
    <input type="hidden" name="payment" id="nsSbPayV">
    <input type="hidden" name="source" id="nsSbSrcV">
    <div class="ns-sb-h"><span id="nsSbImg"></span>
        <div class="ns-sb-t"><b id="nsSbName"></b><span>Размер <b id="nsSbSize"></b> · в наличии <b id="nsSbAvail"></b> · <span class="ns-mono" id="nsSbArt"></span></span></div>
        <button class="ns-m-x" type="button" data-sb-close aria-label="Закрыть"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>
    <div class="ns-sb-b">
        <div class="ns-flash ns-warn" id="wNote" hidden>{!! $ui::icon('x', 'ns-sm') !!}<span>Белая пара продаётся через 1С (чек на кассе), а не здесь — продажа подтянется в CRM сама.</span></div>
        <div class="ns-flash ns-info" id="nsSbMixed" hidden><span></span></div>
        <div class="ns-sb-row">
            <div class="ns-field"><label>Кол-во</label><div class="ns-step"><button type="button" data-d="-1" aria-label="Меньше">−</button><input name="qty" id="nsSbQ" type="number" value="1" min="1"><button type="button" data-d="1" aria-label="Больше">+</button></div></div>
            <div class="ns-field"><label>Цена за пару, ₽</label><input name="price" id="nsSbP" type="number" step="0.01" min="0" inputmode="decimal"></div>
            <div class="ns-field"><label>Оплата</label><div class="ns-seg" id="nsSbPay">@foreach(\App\Models\Deal::PAYMENT_METHODS as $pv => $pl)<button type="button" data-v="{{ $pv }}">{{ $pl }}</button>@endforeach</div></div>
        </div>
        <div class="ns-field"><label>Откуда клиент</label><div class="ns-srcs" id="nsSbSrc">
            <button class="ns-chipf ns-sm ns-on" type="button" data-v="">Не важно</button>
            @foreach(\App\Models\Deal::SNEAKER_SOURCES as $so)<button class="ns-chipf ns-sm" type="button" data-v="{{ $so }}">{{ $so }}</button>@endforeach
        </div></div>
        <div class="ns-sb-row2">
            <div class="ns-field"><label>Имя клиента <i>— необязательно</i></label><input name="client_name" maxlength="255" placeholder="Например, Анна" autocomplete="off"></div>
            <div class="ns-field"><label>Телефон <i>— необязательно</i></label><input name="client_phone" maxlength="32" placeholder="+7 ___ ___-__-__" inputmode="tel" autocomplete="off"></div>
        </div>
    </div>
    <div class="ns-sb-f"><div class="ns-sb-sum" id="nsSbSum"></div><button class="ns-btn ns-big" type="button" data-sb-close>Отмена</button><button class="ns-btn ns-primary ns-big" type="submit" id="nsSbGo"></button></div>
</form>
@endsection

@push('scripts')
<script>
(() => {
    const SALES = @json($saleJs);
    const SRC_C = @json($srcColor);
    const fmt = (v) => Math.round(v).toLocaleString('ru-RU');
    const rub = (v) => fmt(v) + ' ₽';
    const sgn = (v) => (v < 0 ? '−' : '+') + fmt(Math.abs(v)) + ' ₽';
    const plural = (k, a, b, c) => { const m = k % 10, h = k % 100; return m === 1 && h !== 11 ? a : (m >= 2 && m <= 4 && (h < 12 || h > 14) ? b : c); };
    const $ = (id) => document.getElementById(id);
    const sq = $('nsSq'), rows = [...document.querySelectorAll('#nsPlist .ns-prow')];
    let brand = 'all', onlyStock = true, sel = null;

    // фото не загрузилось — две буквы бренда, как в прототипе
    const imgFallback = (root) => root.querySelectorAll('.ns-pimg img').forEach((img) => {
        const swap = () => { const b = (img.dataset.b || '?').slice(0, 2).toUpperCase(); img.parentNode.innerHTML = '<span class="ns-phb">' + ns.esc(b) + '</span>'; };
        if (img.complete && img.naturalWidth === 0) swap(); else img.addEventListener('error', swap, { once: true });
    });
    imgFallback(document);

    /* ---------- подсказка «как продать» ---------- */
    let tips = true;
    try { tips = localStorage.getItem('saleTips') !== 'off'; } catch (e) {}
    const steps = $('nsSteps'), topSub = document.querySelector('.ns-top .ns-sub');
    if (tips) { steps.hidden = false; if (topSub) topSub.hidden = true; }
    const updSteps = () => {
        if (steps.hidden) return;
        const cur = sel ? 3 : (sq.value.trim() ? 2 : 1);
        steps.querySelectorAll('.ns-ss').forEach((s, i) => {
            const n = i + 1;
            s.className = 'ns-ss' + (n < cur ? ' ns-done' : n === cur ? ' ns-cur' : '');
            s.querySelector('i').innerHTML = n < cur ? '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12l5 5 9-11"/></svg>' : n;
        });
        steps.querySelectorAll('.ns-ss-line').forEach((l, i) => l.classList.toggle('ns-done', i + 2 <= cur));
    };
    $('nsStepsHide').addEventListener('click', () => {
        try { localStorage.setItem('saleTips', 'off'); } catch (e) {}
        steps.hidden = true; if (topSub) topSub.hidden = false;
        ns.toast('Подсказка скрыта');
    });

    /* ---------- список моделей: поиск, бренды, «Только в наличии» ---------- */
    const visible = () => rows.filter((r) => !r.hidden);
    const filter = () => {
        const q = sq.value.trim().toLowerCase();
        rows.forEach((r) => {
            r.hidden = !((brand === 'all' || r.dataset.brand === brand) && (!onlyStock || +r.dataset.stock > 0) && (!q || r.dataset.search.includes(q)));
        });
        const k = visible().length;
        $('nsPlCnt').textContent = (q || brand !== 'all' ? 'Найдено ' : '') + k.toLocaleString('ru-RU') + ' ' + plural(k, 'модель', 'модели', 'моделей');
        $('nsPEmpty').hidden = k > 0;
        updSteps();
    };
    sq.addEventListener('input', filter);
    document.querySelectorAll('#nsBrands [data-b]').forEach((b) => b.addEventListener('click', () => {
        brand = b.dataset.b;
        document.querySelectorAll('#nsBrands [data-b]').forEach((x) => x.classList.toggle('ns-on', x === b));
        filter();
    }));
    $('nsOnlyStock').addEventListener('click', function () { onlyStock = !onlyStock; this.classList.toggle('ns-on', onlyStock); filter(); });

    /* ---------- лист продажи ---------- */
    const bar = $('nsSellbar'), qi = $('nsSbQ'), pi = $('nsSbP'), go = $('nsSbGo');
    const payBtns = [...bar.querySelectorAll('#nsSbPay button')], srcBtns = [...bar.querySelectorAll('#nsSbSrc button')];
    let tile = null;
    const onlyWhite = () => tile && +tile.dataset.white > 0 && +tile.dataset.other <= 0;
    const upd = () => {
        if (!tile) return;
        const max = +tile.dataset.avail;
        let k = Math.max(1, Math.min(max, parseInt(qi.value, 10) || 1));
        qi.value = k;
        const price = parseFloat(pi.value) || 0, total = price * k;
        let html = 'Итого <b>' + rub(total) + '</b>';
        if (tile.dataset.cost !== undefined && price > 0) {                 // прибыль — только у руководителя (закупка в разметке только у него)
            const prof = (price - parseFloat(tile.dataset.cost)) * k;
            html += '<br>прибыль <b class="' + (prof < 0 ? 'ns-dn' : 'ns-up') + '">' + sgn(prof) + '</b> · маржа ' + Math.round(prof / total * 100) + '%';
        } else if (!price) {
            html += '<br><span class="ns-tag ns-warn">укажите цену</span>';
        }
        $('nsSbSum').innerHTML = html;
        go.innerHTML = '<svg class="ns-icon ns-sm" viewBox="0 0 24 24"><path d="M5 12l5 5 9-11"/></svg>' + (price ? 'Продать за ' + rub(total) : 'Продать');
        go.disabled = onlyWhite();
    };
    const openSell = (t) => {
        const row = t.closest('.ns-prow');
        document.querySelectorAll('.ns-sz.ns-on').forEach((x) => x.classList.remove('ns-on'));
        document.querySelectorAll('.ns-prow.ns-sel').forEach((x) => x.classList.remove('ns-sel'));
        t.classList.add('ns-on'); row.classList.add('ns-sel');
        tile = t; sel = { item: t.dataset.item };
        $('nsSbItem').value = t.dataset.item;
        $('nsSbImg').innerHTML = row.querySelector('.ns-pimg').outerHTML;
        imgFallback($('nsSbImg'));
        $('nsSbName').textContent = row.querySelector('.ns-pname').textContent;
        $('nsSbSize').textContent = t.dataset.size;
        $('nsSbAvail').textContent = t.dataset.avail + ' шт';
        $('nsSbArt').textContent = row.querySelector('.ns-mono')?.textContent || '';
        qi.value = 1; qi.max = t.dataset.avail;
        pi.value = t.dataset.price || '';
        $('wNote').hidden = !onlyWhite();
        const mixed = $('nsSbMixed');
        mixed.hidden = !(+t.dataset.white > 0 && +t.dataset.other > 0);
        if (!mixed.hidden) mixed.querySelector('span').textContent = 'Белых ' + t.dataset.white + ', серых ' + t.dataset.other + ' — здесь продаётся серая, белые — через 1С.';
        bar.hidden = false;
        upd(); updSteps();
        setTimeout(() => { pi.focus(); pi.select(); }, 40);
    };
    const closeSell = () => {
        bar.hidden = true;
        document.querySelectorAll('.ns-sz.ns-on, .ns-prow.ns-sel').forEach((x) => x.classList.remove('ns-on', 'ns-sel'));
        tile = null; sel = null; updSteps();
    };
    document.querySelectorAll('#nsPlist .ns-sz:not(.ns-off)').forEach((t) => t.addEventListener('click', () => openSell(t)));
    bar.querySelectorAll('[data-sb-close]').forEach((b) => b.addEventListener('click', () => { closeSell(); sq.focus(); }));
    bar.querySelectorAll('.ns-step button').forEach((b) => b.addEventListener('click', () => { qi.value = (parseInt(qi.value, 10) || 1) + (+b.dataset.d); upd(); }));
    qi.addEventListener('input', upd); pi.addEventListener('input', upd);
    payBtns.forEach((b) => b.addEventListener('click', () => {
        const on = !b.classList.contains('ns-on');
        payBtns.forEach((x) => x.classList.toggle('ns-on', on && x === b));
        $('nsSbPayV').value = on ? b.dataset.v : '';
    }));
    srcBtns.forEach((b) => b.addEventListener('click', () => {
        srcBtns.forEach((x) => x.classList.toggle('ns-on', x === b));
        $('nsSbSrcV').value = b.dataset.v;
    }));
    bar.addEventListener('submit', (e) => {
        if (!$('nsSbItem').value) { e.preventDefault(); return; }
        if (onlyWhite()) { e.preventDefault(); ns.toast('Белая пара продаётся через 1С (чек на кассе), а не в CRM'); return; }
        if (!(parseFloat(pi.value) > 0)) { e.preventDefault(); ns.toast('Укажите цену за пару'); pi.focus(); return; }
        go.disabled = true;
    });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && !bar.hidden && !document.getElementById('nsScrim').classList.contains('ns-on')) closeSell(); });

    // сканер или Enter: одна модель — продажа первого размера в наличии; ничего — поиск по коду (ЧЗ, GTIN, раскладка)
    sq.addEventListener('keydown', (e) => {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        const v = visible();
        if (v.length === 0 && sq.value.trim().length >= 4) { location.href = @json(route('scan')) + '?code=' + encodeURIComponent(sq.value.trim()); return; }
        if (v.length === 1) { const t = v[0].querySelector('.ns-sz:not(.ns-off)'); if (t) openSell(t); }
    });

    /* ---------- «Последние продажи»: Все / Сегодня, окно продажи ---------- */
    document.querySelectorAll('#nsRcSeg [data-m]').forEach((b) => b.addEventListener('click', () => {
        const today = b.dataset.m === 'today';
        document.querySelectorAll('#nsRcSeg [data-m]').forEach((x) => x.classList.toggle('ns-on', x === b));
        const groups = [...document.querySelectorAll('.ns-rc-group')];
        groups.forEach((g) => { g.hidden = today && g.dataset.today !== '1'; });
        $('nsRcNoToday').hidden = !(today && groups.every((g) => g.hidden));
    }));
    const csrf = document.querySelector('meta[name="csrf-token"]').content;
    const kpi = (l, v, cls) => '<div class="ns-kpi"><div class="ns-kl">' + l + '</div><div class="ns-kv ' + (cls || '') + '">' + v + '</div></div>';
    const openSale = (id) => {
        const s = SALES.find((x) => x.id === id);
        if (!s) return;
        const img = document.querySelector('.ns-rrow[data-sale="' + id + '"] .ns-pimg')?.outerHTML || '';
        const src = s.source ? '<span class="ns-src" style="--src:' + (SRC_C[s.source] || '#7b8494') + '">' + ns.esc(s.source) + '</span>' : '<span class="ns-src" style="--src:#aab2bf">не указан</span>';
        const kpis = kpi('Сумма', rub(s.amount)) + (s.cost !== null ? kpi('Себестоимость', rub(s.cost)) : '') +
            (s.profit !== null ? kpi('Прибыль', sgn(s.profit), s.profit < 0 ? 'ns-dn' : 'ns-up') : '');
        ns.openModal('<div class="ns-m-head">' + img + '<div style="flex:1;min-width:0"><div class="ns-mt">Сделка #' + s.id + '</div><div class="ns-msub">' + ns.esc(s.when) + (s.seller ? ' · ' + ns.esc(s.seller) : '') + '</div></div>' +
            '<button class="ns-m-x" type="button" data-ns-close aria-label="Закрыть"><svg viewBox="0 0 24 24"><path d="M6 6l12 12M18 6L6 18"/></svg></button></div>' +
            '<div class="ns-m-body"><div class="ns-pname" style="font-size:15px">' + ns.esc(s.name) + '</div><div class="ns-part" style="margin-bottom:14px">' +
            (s.size ? 'Размер ' + ns.esc(s.size) + ' · ' : '') + s.qty + ' ' + plural(s.qty, 'пара', 'пары', 'пар') + (s.pay ? ' · ' + ns.esc(s.pay) : '') + '</div>' +
            '<div class="ns-kpis" style="grid-template-columns:repeat(' + (s.cost !== null ? 3 : 1) + ',1fr);margin:0">' + kpis + '</div>' +
            (s.amount ? '' : '<div class="ns-resline ns-warn" style="margin:14px 0 0">Цена продажи не указана — прибыль считается неверно. Исправьте сумму в сделке.</div>') +
            '<div style="margin-top:14px;display:flex;gap:8px;align-items:center">Источник: ' + src + '</div></div>' +
            '<div class="ns-m-foot"><form method="POST" action="' + ns.esc(s.return) + '" style="margin:0 auto 0 0" onsubmit="return confirm(\'Оформить возврат? Пары вернутся на склад, продажа отменится.\')">' +
            '<input type="hidden" name="_token" value="' + ns.esc(csrf) + '"><button class="ns-btn" type="submit"><svg class="ns-icon ns-sm" viewBox="0 0 24 24"><path d="M9 14L4 9l5-5"/><path d="M4 9h11a5 5 0 010 10h-3"/></svg>Оформить возврат</button></form>' +
            '<a class="ns-btn" href="' + ns.esc(s.receipt) + '" target="_blank" rel="noopener">Чек</a><a class="ns-btn" href="' + ns.esc(s.show) + '">Открыть сделку</a>' +
            '<button class="ns-btn ns-primary" type="button" data-ns-close>Готово</button></div>', 560);
    };
    document.querySelectorAll('.ns-rrow[data-sale]').forEach((r) => r.addEventListener('click', (e) => {
        if (e.target.closest('[data-print]')) return;      // «Чек» — ссылкой в новой вкладке
        openSale(+r.dataset.sale);
    }));

    if (new URLSearchParams(location.search).has('sold')) {
        const u = new URL(location.href); u.searchParams.delete('sold'); history.replaceState(null, '', u.pathname + u.search);
        @if($done) ns.toast(@json('Продано: '.$done['name'].' · сделка #'.$done['deal_id'])); @endif
    }

    /* ---------- старт: со скана (?q=, ?item=) — фильтр и сразу продажа размера ---------- */
    filter();
    const preItem = new URLSearchParams(location.search).get('item');
    const pre = preItem ? document.querySelector('#nsPlist .ns-sz:not(.ns-off)[data-item="' + CSS.escape(preItem) + '"]') : null;
    if (pre) { pre.closest('.ns-prow').hidden = false; pre.scrollIntoView({ block: 'center' }); openSell(pre); }
    else sq.focus();
})();
</script>
@endpush
