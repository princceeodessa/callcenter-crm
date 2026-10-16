{{--
    «Прибыль договоров (потолки)» — готовые итоги CRM БлагоДар (App\Services\Owner\ContractProfit), только на странице владельца.
    Нет итогов — пишем словами, нули не рисуем. Помощники $money, $n, $pct, $plural — из owner/ceilings, а отдельно (?part=profit) — свои.
--}}
@php
    $n ??= fn ($v) => number_format((float) $v, 0, ',', ' ');
    $pct ??= fn ($v) => $v === null ? '—' : number_format((float) $v, 1, ',', ' ').'%';
    $money ??= fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' ₽';
    $plural ??= function (int $k, string $one, string $few, string $many) {
        $k = abs($k) % 100;
        $k1 = $k % 10;

        return $k > 10 && $k < 20 ? $many : ($k1 > 1 && $k1 < 5 ? $few : ($k1 === 1 ? $one : $many));
    };
    $pf = $profit['data'] ?? null;
    $pfT = $pf['totals'] ?? null;
    [$pfWord, $pfTone] = \App\Services\Owner\ContractProfit::verdict($pfT['margin'] ?? null);
    $pfKindsSum = $pf ? array_sum(array_map(fn ($k) => max(0, $k['sum']), $pf['kinds'])) : 0;
    $pfMargin = fn (?float $m) => $m === null ? '—' : $pct($m);
    $pfTag = function (?float $m) use ($pfMargin) {
        [$w, $tone] = \App\Services\Owner\ContractProfit::verdict($m);

        return $m === null ? '<span class="muted">—</span>' : '<span class="tag '.$tone.'">'.e($pfMargin($m).($w !== '' ? ' · '.$w : '')).'</span>';
    };
@endphp
<div class="sec" id="profit"><h2>Прибыль договоров</h2>
    <span class="note">по дню договора · итоги CRM БлагоДар@if($pf && $pf['updated']) · 1С на {{ $pf['updated']->format($pf['updated']->isToday() ? 'H:i' : 'd.m H:i') }}@endif</span>
</div>
@if(($profit['state'] ?? 'off') !== 'ok')
    <div class="card empty-card">{{ $profit['message'] }}</div>
@else
    @foreach($pf['stale'] as $warn)
        <div class="card empty-card bad">Данные могут быть устаревшими: {{ $warn }}</div>
    @endforeach
    <div class="kpis4 profit-kpis">
        <div class="card mini">
            <div class="lbl">Договоров</div>
            <div class="val num">{{ $n($pfT['contracts']) }}</div>
            <div class="sub">заключено за период</div>
        </div>
        <div class="card mini">
            <div class="lbl">Получим</div>
            <div class="val num">{{ $money($pfT['revenue']) }}</div>
            <div class="sub">сумма договоров</div>
        </div>
        <div class="card mini">
            <div class="lbl">Потратим</div>
            <div class="val num">{{ $money($pfT['costs']) }}</div>
            <div class="sub">монтаж, материалы, отделы, налоги</div>
        </div>
        <div class="card mini">
            <div class="lbl">Заработаем</div>
            <div class="val num {{ ($pfT['profit'] ?? 0) < 0 ? 'neg' : '' }}">{{ $money($pfT['profit']) }}</div>
            <div class="sub">@if($pfT['margin'] !== null)<span class="tag {{ $pfTone }}">маржа {{ $pfMargin($pfT['margin']) }} · {{ $pfWord }}</span>@endif</div>
            @if($pfT['incomplete'] > 0)
                <div class="warn">у {{ $n($pfT['incomplete']) }} {{ $plural($pfT['incomplete'], 'договора', 'договоров', 'договоров') }} в 1С не хватает цены или ставки — на деле прибыль меньше</div>
            @endif
        </div>
    </div>

    @if(count($pf['kinds']) > 0 && $pfKindsSum > 0)
        <div class="card profit-flow">
            <div class="pf-head"><b>Куда уходят деньги</b><span class="note">из {{ $money($pfT['costs']) }} расходов</span></div>
            <div class="pf-bar" role="img" aria-label="Расходы по статьям">
                @foreach($pf['kinds'] as $k)
                    @if($k['sum'] > 0)<span style="width:{{ round($k['sum'] / $pfKindsSum * 100, 2) }}%;background:{{ $k['color'] }}" title="{{ $k['label'] }} — {{ $money($k['sum']) }}"></span>@endif
                @endforeach
            </div>
            <div class="pf-list">
                @foreach($pf['kinds'] as $k)
                    <div class="pf-item"><span class="dot2" style="background:{{ $k['color'] }}"></span>{{ $k['label'] }}
                        <b class="num">{{ $money($k['sum']) }}</b><span class="muted num">{{ $pfKindsSum > 0 ? round(max(0, $k['sum']) / $pfKindsSum * 100) : 0 }}%</span></div>
                @endforeach
            </div>
            @if(count($pf['departments']) > 0)
                <div class="pf-deps">отделы: @foreach($pf['departments'] as $d){{ $d['title'] }} {{ $money($d['sum']) }}@if(! $loop->last) · @endif @endforeach</div>
            @endif
        </div>
    @endif

    <div class="profit-tables">
        @if(count($pf['by_measurer']) > 0)
            <div class="card tbl-card">
                <div class="tbl-head"><h2>По замерщикам</h2><span class="note">чьи замеры стали договорами</span></div>
                <div class="tbl-wrap"><table class="t">
                    <thead><tr><th>Замерщик</th><th class="num">Договоров</th><th class="num">Получим</th><th class="num">Заработаем</th><th>Маржа</th><th class="num" title="у стольких договоров в 1С не хватает цены или ставки — прибыль по ним на деле меньше">Без цены в 1С</th></tr></thead>
                    <tbody>
                    @foreach($pf['by_measurer'] as $r)
                        <tr>
                            <td>{{ $r['measurer'] }}</td>
                            <td class="num">{{ $n($r['contracts']) }}</td>
                            <td class="num">{{ $money($r['revenue']) }}</td>
                            <td class="num">{{ $money($r['profit']) }}</td>
                            <td>{!! $pfTag($r['margin']) !!}</td>
                            <td class="num {{ $r['incomplete'] > 0 ? 'pf-inc' : 'muted' }}">{{ $r['incomplete'] > 0 ? $n($r['incomplete']) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endif
        {{-- по месяцам — когда период шире месяца --}}
        @if(count($pf['by_month']) > 1)
            <div class="card tbl-card">
                <div class="tbl-head"><h2>По месяцам</h2></div>
                <div class="tbl-wrap"><table class="t">
                    <thead><tr><th>Месяц</th><th class="num">Договоров</th><th class="num">Получим</th><th class="num">Заработаем</th><th>Маржа</th><th class="num" title="у стольких договоров в 1С не хватает цены или ставки — прибыль по ним на деле меньше">Без цены в 1С</th></tr></thead>
                    <tbody>
                    @foreach($pf['by_month'] as $r)
                        <tr>
                            <td>{{ mb_convert_case($r['title'], MB_CASE_TITLE) }}</td>
                            <td class="num">{{ $n($r['contracts']) }}</td>
                            <td class="num">{{ $money($r['revenue']) }}</td>
                            <td class="num">{{ $money($r['profit']) }}</td>
                            <td>{!! $pfTag($r['margin']) !!}</td>
                            <td class="num {{ $r['incomplete'] > 0 ? 'pf-inc' : 'muted' }}">{{ $r['incomplete'] > 0 ? $n($r['incomplete']) : '—' }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            </div>
        @endif
    </div>

    @if($pf['url'])
        <div class="profit-more"><a class="export" href="{{ $pf['url'] }}" target="_blank" rel="noopener">Подробно в CRM БлагоДар
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg></a></div>
    @endif
@endif
