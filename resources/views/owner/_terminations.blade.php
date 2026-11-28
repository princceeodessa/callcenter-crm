{{--
    «Расторжения договоров» (потолки) — готовые итоги CRM БлагоДар по 1С (App\Services\Owner\ContractTerminations), только на
    странице владельца. Расторгнутый — все заказы договора в 1С «Отменен» с причиной «Расторжение» или «Отказ» (владелец 28.11.2026),
    день — последняя отмена заказа по истории версий 1С. Нет итогов — слова, а не нули.
    Сверху — каждый месяц года (месяц раскрывает свои договоры), ниже — выбранный период; «Подробно» — страница за весь год.
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
    $tmMonths = $terminationMonths ?? [];
    // с этого месяца расторжения видны: весь 2026 год (пока CRM БлагоДар не выкатила дни отмены из 1С — с 01.08.2026)
    $tmSince = $tmMonths ? end($tmMonths)['from'] : \Carbon\Carbon::parse(\App\Services\Owner\ContractTerminations::HISTORY_SINCE);
    $tmPartial = $tmSince->gt(\Carbon\Carbon::parse(\App\Services\Owner\ContractTerminations::HISTORY_SINCE));
    $tmOk = collect($tmMonths)->where('ok', true);
    $tmSum = ['contracts' => $tmOk->sum('contracts'), 'amount' => $tmOk->sum('amount'), 'unmarked' => $tmOk->sum('unmarked'), 'refusals' => $tmOk->sum('refusals')];
    $tmReport = route('owner.ceilings', ['report' => 'terminations'] + request()->only(['period', 'from', 'to']));
    $tmHead = '<thead><tr><th>Договор</th><th>Договор от</th><th>Расторгнут</th><th class="num" title="сколько дней прошло от договора до расторжения">Через</th><th class="num">Сумма</th>'
        .($tmPaidCols ? '<th class="num">Заплатили</th><th class="num">Вернули</th>' : '').'<th>Замерщик</th><th>Причина в 1С</th></tr></thead>';
@endphp
<div class="sec" id="terminations"><h2>Расторжения договоров</h2>
    <span class="note">по дню отмены в 1С · из 1С через CRM БлагоДар@if($tm && $tm['updated']) · 1С на {{ $tm['updated']->format($tm['updated']->isToday() ? 'H:i' : 'd.m H:i') }}@endif
        @if($tm && ($fromValue ?? '') !== '' && $fromValue < $tmSince->toDateString()) · расторжения видны с {{ $tmSince->format('d.m.Y') }}@endif
        @if($tm && $tm['url']) · <a href="{{ $tm['url'] }}" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none">полный отчёт →</a>@endif</span>
</div>
@if($tmState !== 'ok')
    <div class="card empty-card {{ $tmState === 'error' ? 'bad' : '' }}">{{ $terminations['message'] }}</div>
@else
    @if($tmMonths)
        {{-- каждый месяц года — от выбранного периода не зависит; месяц раскрывает свои договоры прямо здесь --}}
        <div class="card tbl-card tm-months">
            <div class="tbl-head"><h2>По месяцам</h2><span class="note">нажмите месяц — откроются его договоры</span>
                <a class="tm-more" href="{{ $tmReport }}">Подробно за год →</a></div>
            <div class="tbl-wrap"><table class="t tm-mt">
                <thead><tr><th>Месяц</th><th class="num">Расторгнуто</th><th class="num">из них «Отказ»</th><th class="num">На сумму</th>
                    <th class="num" title="отменены в 1С целиком, но причина не «Расторжение» и не «Отказ» — в расторжения не входят">Отменены по другой причине</th></tr></thead>
                <tbody>
                @foreach($tmMonths as $m)
                    @php $tmCan = $m['ok'] && count($m['items']) > 0; @endphp
                    <tr @class(['tm-mrow', 'tm-can' => $tmCan, 'tm-cur' => ($fromValue ?? null) === $m['from']->toDateString() && ($toValue ?? null) === $m['to']->copy()->subDay()->toDateString()])
                        @if($tmCan) data-tm-toggle="{{ $m['key'] }}" tabindex="0" role="button" aria-expanded="false" @endif>
                        <td>@if($tmCan)<span class="tm-chev" aria-hidden="true"></span>@endif{{ \Illuminate\Support\Str::ucfirst($m['from']->translatedFormat('F Y')) }}</td>
                        @if($m['ok'])
                            <td class="num"><b class="{{ $m['contracts'] > 0 ? 'tm-neg' : 'muted' }}">{{ $n($m['contracts']) }}</b>@if($m['approx']) <span class="muted" title="у части договоров точного дня отмены нет — день последнего заказа">≈</span>@endif</td>
                            <td class="num">{{ $m['refusals'] > 0 ? $n($m['refusals']) : '—' }}</td>
                            <td class="num">{{ $m['contracts'] > 0 ? $money($m['amount']) : '—' }}</td>
                            <td class="num">{{ $m['unmarked'] > 0 ? $n($m['unmarked']) : '—' }}</td>
                        @else
                            <td class="num muted" colspan="4">данные не пришли</td>
                        @endif
                    </tr>
                    @if($tmCan)
                        <tr class="tm-mdet" data-tm-det="{{ $m['key'] }}" hidden><td colspan="5"><div class="tm-det">
                            <div class="tbl-wrap"><table class="t tm-dt">{!! $tmHead !!}<tbody>
                                @foreach($m['items'] as $i)
                                    @include('owner._tm_contract')
                                @endforeach
                            </tbody></table></div>
                            <a class="tm-period" href="{{ route('owner.ceilings', ['period' => 'custom', 'from' => $m['from']->toDateString(), 'to' => $m['to']->copy()->subDay()->toDateString()]) }}#terminations">сводка за этот месяц →</a>
                        </div></td></tr>
                    @endif
                @endforeach
                </tbody>
                <tfoot><tr class="tm-total">
                    <td>{{ $tmSince->month === 1 && $tmSince->year === now()->year ? 'Итого за '.$tmSince->year : 'Итого с '.$tmSince->format('d.m.Y') }}</td>
                    <td class="num"><b class="{{ $tmSum['contracts'] > 0 ? 'tm-neg' : '' }}">{{ $n($tmSum['contracts']) }}</b></td>
                    <td class="num">{{ $tmSum['refusals'] > 0 ? $n($tmSum['refusals']) : '—' }}</td>
                    <td class="num">{{ $tmSum['contracts'] > 0 ? $money($tmSum['amount']) : '—' }}</td>
                    <td class="num">{{ $tmSum['unmarked'] > 0 ? $n($tmSum['unmarked']) : '—' }}</td>
                </tr></tfoot>
            </table></div>
            @if($tmPartial)
                <p class="foot-note tm-foot">CRM БлагоДар пока отдаёт расторжения только по договорам с заказами с {{ $tmSince->format('d.m.Y') }} и с примерным днём (≈ — день последнего заказа договора). Весь год с настоящими днями отмены появится здесь сам, когда они обновятся.</p>
            @elseif($tmOk->contains('approx', true))
                <p class="foot-note tm-foot">≈ — у части договоров точного дня отмены в 1С нет: стоит день последнего заказа договора.</p>
            @endif
        </div>
    @endif
    @if($tmT['contracts'] === 0)
        <div class="card empty-card">За выбранный период расторжений нет.@if($tmT['unmarked'] > 0) Но {{ $n($tmT['unmarked']) }} {{ $plural($tmT['unmarked'], 'договор', 'договора', 'договоров') }} в 1С {{ $plural($tmT['unmarked'], 'отменён', 'отменены', 'отменены') }} целиком по другой причине.@endif</div>
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
            <p class="foot-note tm-note">Ещё {{ $n($tmT['unmarked']) }} {{ $plural($tmT['unmarked'], 'договор', 'договора', 'договоров') }} в 1С {{ $plural($tmT['unmarked'], 'отменён', 'отменены', 'отменены') }} целиком по другой причине (не «Расторжение» и не «Отказ») — в расторжения не {{ $plural($tmT['unmarked'], 'входит', 'входят', 'входят') }}.</p>
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
                <div class="tbl-wrap tbl-scroll"><table class="t">{!! $tmHead !!}
                    <tbody>
                    @foreach($tm['items'] as $i)
                        @include('owner._tm_contract')
                    @endforeach
                    </tbody>
                </table></div>
                @if($tmApprox)
                    <p class="foot-note tm-foot">≈ — точного дня отмены в 1С нет: стоит день последнего заказа договора.</p>
                @endif
            </div>
        </div>
    @endif
@endif
