{{--
    «Расторжения договоров» (потолки) — готовые итоги CRM БлагоДар по 1С (App\Services\Owner\ContractTerminations), только на
    странице владельца. Расторгнутый — все заказы договора в 1С «Отменен» с причиной «Расторжение». Нет итогов — слова, а не нули.
    Рисуется внутри owner/ceilings или отдельно (?part=terminations) — тогда помощники форматирования свои.
--}}
@php
    $n ??= fn ($v) => number_format((float) $v, 0, ',', ' ');
    $money ??= fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' ₽';
    $plural ??= function (int $k, string $one, string $few, string $many) {
        $k = abs($k) % 100;
        $k1 = $k % 10;

        return $k > 10 && $k < 20 ? $many : ($k1 > 1 && $k1 < 5 ? $few : ($k1 === 1 ? $one : $many));
    };
    $tmState = $terminations['state'] ?? 'off';
    $tm = $terminations['data'] ?? null;
    $tmT = $tm['totals'] ?? null;
    // возвратов в выгрузке 1С пока нет (27.11.2026) — CRM БлагоДар отдаёт null: плитки словами, столбцы денег не рисуем
    $tmPaidCols = $tm && collect($tm['items'])->contains(fn ($i) => $i['paid'] !== null || $i['refunded'] !== null);
    $tmApprox = $tm && collect($tm['items'])->contains(fn ($i) => $i['terminated_at'] && ! $i['date_known']);
@endphp
<div class="sec" id="terminations"><h2>Расторжения договоров</h2>
    <span class="note">по дню расторжения · из 1С через CRM БлагоДар@if($tm && $tm['updated']) · 1С на {{ $tm['updated']->format($tm['updated']->isToday() ? 'H:i' : 'd.m H:i') }}@endif
        @if($tm && ($tm['before_history'] ?? false)) · расторжения видны с {{ \Carbon\Carbon::parse(\App\Services\Owner\ContractTerminations::HISTORY_SINCE)->format('d.m.Y') }}@endif
        @if($tm && $tm['url']) · <a href="{{ $tm['url'] }}" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none">полный отчёт →</a>@endif</span>
</div>
@if($tmState !== 'ok')
    <div class="card empty-card {{ $tmState === 'error' ? 'bad' : '' }}">{{ $terminations['message'] }}</div>
@else
    @if(! empty($terminationMonths))
        {{-- по каждому месяцу с 01.08.2026 — от выбранного периода не зависит; месяц — ссылка на период этого месяца --}}
        <div class="card tbl-card tm-months">
            <div class="tbl-head"><h2>По месяцам</h2><span class="note">по дню расторжения · нажмите месяц — ниже будут его договоры</span></div>
            <div class="tbl-wrap"><table class="t">
                <thead><tr><th>Месяц</th><th class="num">Расторгнуто</th><th class="num">На сумму</th><th class="num">Отменены без причины «Расторжение»</th></tr></thead>
                <tbody>
                @foreach($terminationMonths as $m)
                    <tr @class(['tm-cur' => ($fromValue ?? null) === $m['from']->toDateString() && ($toValue ?? null) === $m['to']->copy()->subDay()->toDateString()])>
                        <td><a href="{{ route('owner.ceilings', ['period' => 'custom', 'from' => $m['from']->toDateString(), 'to' => $m['to']->copy()->subDay()->toDateString()]) }}#terminations">{{ \Illuminate\Support\Str::ucfirst($m['from']->translatedFormat('F Y')) }}</a></td>
                        @if($m['ok'])
                            <td class="num"><b class="{{ $m['contracts'] > 0 ? 'tm-neg' : 'muted' }}">{{ $n($m['contracts']) }}</b>@if($m['approx']) <span class="muted" title="точного дня расторжения нет — месяц последнего заказа договора">≈</span>@endif</td>
                            <td class="num">{{ $m['contracts'] > 0 ? $money($m['amount']) : '—' }}</td>
                            <td class="num">{{ $m['unmarked'] > 0 ? $n($m['unmarked']) : '—' }}</td>
                        @else
                            <td class="num muted" colspan="3">данные не пришли</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            @if(collect($terminationMonths)->contains('approx', true))
                <p class="foot-note tm-foot">≈ — точного дня расторжения пока нет: договор стоит в месяце своего последнего заказа. Видны договоры с заказами с {{ \Carbon\Carbon::parse(\App\Services\Owner\ContractTerminations::HISTORY_SINCE)->format('d.m.Y') }}.</p>
            @endif
        </div>
    @endif
    @if($tmT['contracts'] === 0)
        <div class="card empty-card">За выбранный период расторжений нет.@if($tmT['unmarked'] > 0) Но {{ $n($tmT['unmarked']) }} {{ $plural($tmT['unmarked'], 'договор', 'договора', 'договоров') }} в 1С {{ $plural($tmT['unmarked'], 'отменён', 'отменены', 'отменены') }} целиком без причины «Расторжение».@endif</div>
    @else
        <div class="kpis4 tm-kpis">
            <div class="card mini">
                <div class="lbl">Расторгнуто</div>
                <div class="val num neg">{{ $n($tmT['contracts']) }}</div>
                <div class="sub">{{ $plural($tmT['contracts'], 'договор', 'договора', 'договоров') }} за период</div>
            </div>
            <div class="card mini">
                <div class="lbl">На сумму</div>
                <div class="val num">{{ $money($tmT['amount']) }}</div>
                <div class="sub">сумма договоров до отмены</div>
            </div>
            <div class="card mini">
                <div class="lbl">Вернули заказчикам</div>
                @if($tmT['refunded'] === null)
                    <div class="val muted">нет данных</div>
                    <div class="sub">возвратов в выгрузке 1С пока нет</div>
                @else
                    <div class="val num">{{ $money($tmT['refunded']) }}</div>
                    <div class="sub">@if($tmT['paid'] !== null)заплатили по ним {{ $money($tmT['paid']) }}@else оплаты в 1С не видно@endif</div>
                @endif
            </div>
            <div class="card mini">
                <div class="lbl">Осталось у нас</div>
                @if($tmT['kept'] === null)
                    <div class="val muted">нет данных</div>
                    <div class="sub">посчитаем, когда будут возвраты</div>
                @else
                    <div class="val num">{{ $money($tmT['kept']) }}</div>
                    <div class="sub">заплатили минус вернули</div>
                @endif
            </div>
        </div>
        @foreach($tm['stale'] as $warn)
            <p class="foot-note tm-note">{{ $warn }}</p>
        @endforeach
        @if($tmT['unmarked'] > 0)
            <p class="foot-note tm-note">Ещё {{ $n($tmT['unmarked']) }} {{ $plural($tmT['unmarked'], 'договор', 'договора', 'договоров') }} в 1С {{ $plural($tmT['unmarked'], 'отменён', 'отменены', 'отменены') }} целиком, но без причины «Расторжение» — в расторжения не {{ $plural($tmT['unmarked'], 'входит', 'входят', 'входят') }}.</p>
        @endif
        <div class="tm-grid">
            @if($tm['by_measurer'])
                <div class="card tbl-card">
                    <div class="tbl-head"><h2>По замерщикам</h2></div>
                    <div class="tbl-wrap"><table class="t">
                        <thead><tr><th>Замерщик</th><th class="num">Расторжений</th><th class="num">Сумма</th></tr></thead>
                        <tbody>
                        @foreach($tm['by_measurer'] as $m)
                            <tr><td>@include('owner._op_ava', ['name' => $m['measurer']])</td><td class="num"><b>{{ $n($m['contracts']) }}</b></td><td class="num">{{ $money($m['amount']) }}</td></tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </div>
            @endif
            <div class="card tbl-card">
                <div class="tbl-head"><h2>Договоры</h2><span class="note">свежие первыми</span></div>
                <div class="tbl-wrap tbl-scroll"><table class="t">
                    <thead><tr><th>Договор</th><th>Расторгнут</th><th class="num">Сумма</th>@if($tmPaidCols)<th class="num">Заплатили</th><th class="num">Вернули</th>@endif<th>Замерщик</th><th>Причина</th></tr></thead>
                    <tbody>
                    @foreach($tm['items'] as $i)
                        <tr>
                            <td>
                                @if($i['url'])<a href="{{ $i['url'] }}" target="_blank" rel="noopener" class="tm-link">№ {{ $i['number'] ?: '—' }}</a>@else<b>№ {{ $i['number'] ?: '—' }}</b>@endif
                                <div class="muted tm-sub">{{ $i['label'] }}@if($i['contract_date']) · от {{ $i['contract_date']->format('d.m.Y') }}@endif</div>
                            </td>
                            <td>@if($i['terminated_at']){{ $i['terminated_at']->format('d.m.Y') }}@if(! $i['date_known']) <span class="muted" title="точного дня в 1С нет">≈</span>@endif @else<span class="muted">—</span>@endif</td>
                            <td class="num">{{ $money($i['amount']) }}</td>
                            @if($tmPaidCols)
                                <td class="num">{{ $money($i['paid']) }}</td>
                                <td class="num">{{ $money($i['refunded']) }}</td>
                            @endif
                            <td>{{ $i['measurer'] ?: '—' }}</td>
                            <td class="wrap tm-reason">{{ $i['reason'] ?: '—' }}@if($i['comment'] !== '')<div class="muted tm-sub" title="{{ $i['comment'] }}">{{ \Illuminate\Support\Str::limit($i['comment'], 110) }}</div>@endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if($tmApprox)
                    <p class="foot-note tm-foot">≈ — точного дня расторжения в 1С нет: стоит день последнего заказа договора.</p>
                @endif
            </div>
        </div>
    @endif
@endif
