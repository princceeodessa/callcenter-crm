@extends('layouts.sneakers')
{{--
    «1С» — продажи белых пар (кнопка на складе, только руководителю). Каркас «Нордика»; данные — OnecSalesController, блоки прежние:
    продажи из 1С (с 13.10.2026 белые пары продают в 1С «Обувь», CRM подтягивает их), затем старая выгрузка CRM → 1С (выключена).
--}}
@php
    $ui = \App\Support\Ui\Sneakers::class;
    $rub = fn ($v) => number_format((float) $v, 0, ',', ' ').' ₽';
    $lastSync = $connection?->last_synced_at;
    $statusCls = ['done' => 'ns-up', 'waiting' => 'ns-warn-t', 'error' => 'ns-dn', 'pending' => 'ns-muted'];
    $doneCount = $docs->where('status', 'done')->count();
    $waitingCount = $docs->where('status', 'waiting')->count();
    $errorCount = $docs->where('status', 'error')->count();
    $impCls = ['linked' => 'ns-ok', 'unlinked' => 'ns-warn', 'returned' => '', 'return_unmatched' => 'ns-bad', 'cancelled_in_1c' => 'ns-bad'];
    $impAttention = $imports->whereIn('status', ['unlinked', 'return_unmatched', 'cancelled_in_1c'])->count();
@endphp

@section('title', '1С: продажи белых пар')
@section('ns_sub', 'белые пары продают в 1С «Обувь» — CRM подтягивает чеки')

@section('content')
<div class="ns-kpis ns-k5">
    <div class="ns-kpi"><div class="ns-kl">Станция (компьютер с 1С)</div><div class="ns-kv ns-kv-sm">@if(! $connection)<span class="ns-muted">не подключена</span>@elseif($lastSync)на связи {{ $lastSync->format('d.m H:i') }}@else<span class="ns-muted">ещё не выходила на связь</span>@endif</div></div>
    <div class="ns-kpi"><div class="ns-kl">Выгрузка с</div><div class="ns-kv ns-kv-sm">{{ $start ? \Carbon\Carbon::parse($start)->format('d.m.Y') : '—' }}</div></div>
    <div @class(['ns-kpi', 'ns-warn' => $queue->isNotEmpty()])><div class="ns-kl">В очереди</div><div class="ns-kv">{{ $queue->count() }}</div></div>
    <div class="ns-kpi"><div class="ns-kl">Ещё {{ \App\Services\Onec\SaleDocExport::COOLING_MINUTES }} мин можно поправить</div><div class="ns-kv">{{ $cooling }}</div></div>
    <div @class(['ns-kpi', 'ns-warn' => $errorCount > 0])><div class="ns-kl">В 1С · ждут прихода · ошибки</div><div class="ns-kv ns-kv-sm">{{ $doneCount }} · {{ $waitingCount }} · <span @class(['ns-dn' => $errorCount])>{{ $errorCount }}</span></div></div>
</div>

<div class="ns-panel" style="overflow:hidden">
    <div class="ns-panel-pad" style="padding-bottom:6px"><div class="ns-sec-h">Продажи из 1С <span class="ns-tag">{{ $imports->count() }}</span><span class="ns-grow"></span>@if($impAttention > 0)<span class="ns-tag ns-bad">разобрать вручную: {{ $impAttention }}</span>@endif</div></div>
    @if($imports->isEmpty())
        <div class="ns-empty ns-sm">{!! $ui::icon('doc1c') !!}<b>Из 1С пока ничего не пришло</b>Станция читает чеки 1С раз в 30 минут; продажа появится здесь, в «Продажах за день» и на складе</div>
    @else
        <div style="overflow-x:auto"><table class="ns-tbl">
            <thead><tr><th>Когда</th><th>Документ 1С</th><th>Карточка 1С</th><th class="ns-num">Сумма</th><th>Итог</th><th>Сделка</th></tr></thead>
            <tbody>
            @foreach($imports as $im)
                <tr>
                    <td class="ns-muted">{{ $im->onec_date->format('d.m H:i') }}</td>
                    <td style="white-space:nowrap">{{ \App\Models\OnecSaleImport::TYPE_LABELS[$im->doc_type] ?? $im->doc_type }} № {{ $im->onec_number }}</td>
                    <td>{{ $im->card_name }} @if($im->qty > 1)× {{ $im->qty }}@endif</td>
                    <td class="ns-num"><b>{{ $rub($im->amount) }}</b></td>
                    <td><span class="ns-tag {{ $impCls[$im->status] ?? '' }}">{{ \App\Models\OnecSaleImport::STATUS_LABELS[$im->status] ?? $im->status }}</span>@if($im->note)<div class="ns-muted" style="font-size:12px;margin-top:3px;white-space:normal">{{ $im->note }}</div>@endif</td>
                    <td>@if($im->deal)<a class="ns-link" href="{{ route('deals.show', $im->deal) }}">#{{ $im->deal_id }}</a>@else — @endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>

<div class="ns-note" style="margin:18px 0 10px">Ниже — выгрузка продаж из CRM в 1С, как было до 13.10.2026 (сейчас выключена).</div>

@if($ambiguous->isNotEmpty())
    <div class="ns-panel ns-panel-pad ns-oc-block">
        <div class="ns-sec-h">Уточните: белая или серая пара <span class="ns-tag ns-warn">{{ $ambiguous->count() }}</span></div>
        <div class="ns-note" style="margin:0 0 6px">В этих размерах есть и белые, и серые пары. Белые уйдут в 1С, серые — нет.</div>
        <div class="ns-hlist">
            @foreach($ambiguous as $d)
                <div class="ns-hr"><div class="ns-grow"><b><a class="ns-link" href="{{ route('deals.show', $d) }}">#{{ $d->id }}</a> · {{ $d->warehouseItem?->display_name ?? $d->title }}</b><small>{{ $d->stock_deducted_at?->format('d.m H:i') }}@if($d->amount) · {{ $rub($d->amount) }}@endif</small></div>
                    <form method="POST" action="{{ route('deals.sale-flags', $d) }}" class="ns-oc-acts">@csrf
                        <button class="ns-btn ns-sm" name="white" value="1">Белая</button><button class="ns-btn ns-sm" name="white" value="0">Серая</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($noPayment->isNotEmpty())
    <div class="ns-panel ns-panel-pad ns-oc-block">
        <div class="ns-sec-h">Белые продажи без способа оплаты <span class="ns-tag ns-warn">{{ $noPayment->count() }}</span></div>
        <div class="ns-note" style="margin:0 0 6px">В 1С наличные и безнал учитываются раздельно. Без отметки продажа уйдёт как наличные.</div>
        <div class="ns-hlist">
            @foreach($noPayment as $d)
                <div class="ns-hr"><div class="ns-grow"><b><a class="ns-link" href="{{ route('deals.show', $d) }}">#{{ $d->id }}</a> · {{ $d->warehouseItem?->display_name ?? $d->title }}</b><small>{{ $d->stock_deducted_at?->format('d.m H:i') }}@if($d->amount) · {{ $rub($d->amount) }}@endif</small></div>
                    <form method="POST" action="{{ route('deals.sale-flags', $d) }}" class="ns-oc-acts">@csrf
                        @foreach(\App\Models\Deal::PAYMENT_METHODS as $pv => $pl)<button class="ns-btn ns-sm" name="payment" value="{{ $pv }}">{{ $pl }}</button>@endforeach
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($cancelled->isNotEmpty())
    <div class="ns-panel ns-panel-pad ns-oc-block">
        <div class="ns-sec-h">Есть в 1С, но в CRM уже не белая продажа <span class="ns-tag ns-bad">{{ $cancelled->count() }}</span></div>
        <div class="ns-note" style="margin:0 0 6px">Продажу отменили или отметили серой после выгрузки. Документ в 1С пометьте на удаление вручную.</div>
        <div class="ns-hlist">
            @foreach($cancelled as $c)
                <div class="ns-hr"><div class="ns-grow"><b><a class="ns-link" href="{{ route('deals.show', $c->deal_id) }}">#{{ $c->deal_id }}</a> · {{ $c->deal?->warehouseItem?->display_name ?? $c->deal?->title }}</b><small>в 1С: отчёт № {{ $c->onec_number }}@if($c->onec_date) от {{ $c->onec_date->format('d.m.Y H:i') }}@endif</small></div></div>
            @endforeach
        </div>
    </div>
@endif

@if($queue->isNotEmpty())
    <div class="ns-panel ns-oc-block" style="overflow:hidden">
        <div class="ns-panel-pad" style="padding-bottom:6px"><div class="ns-sec-h">В очереди на выгрузку <span class="ns-tag">{{ $queue->count() }}</span></div></div>
        <table class="ns-tbl"><tbody>
            @foreach($queue as $q)
                @php
                    $qd = $queueDeals[$q['deal_id']] ?? null;
                @endphp
                <tr><td class="ns-muted">{{ \Carbon\Carbon::parse($q['date'])->format('d.m H:i') }}</td><td>{{ \App\Models\OnecSaleDoc::KIND_LABELS[$q['kind']] ?? $q['kind'] }}</td>
                    <td><a class="ns-link" href="{{ route('deals.show', $q['deal_id']) }}">#{{ $q['deal_id'] }}</a> · {{ $qd?->warehouseItem?->display_name ?? $qd?->title }}</td><td class="ns-num">{{ $rub($q['row']['amount']) }}</td></tr>
            @endforeach
        </tbody></table>
    </div>
@endif

<div class="ns-panel" style="overflow:hidden">
    <div class="ns-panel-pad" style="padding-bottom:6px"><div class="ns-sec-h">Документы в 1С</div></div>
    @if($docs->isEmpty())
        <div class="ns-empty ns-sm"><b>Пока ничего не выгружалось</b>Белых продаж с начала выгрузки ещё не было</div>
    @else
        <div style="overflow-x:auto"><table class="ns-tbl">
            <thead><tr><th>Когда</th><th>Что</th><th>Сделка · пара</th><th class="ns-num">Сумма</th><th>Статус</th><th>Документ 1С</th></tr></thead>
            <tbody>
            @foreach($docs as $d)
                @php
                    $when = $d->onec_date ?? ($d->kind === 'return' ? $d->deal?->returned_at : $d->deal?->stock_deducted_at);
                @endphp
                <tr>
                    <td class="ns-muted">{{ $when?->format('d.m H:i') ?? '—' }}</td>
                    <td>{{ \App\Models\OnecSaleDoc::KIND_LABELS[$d->kind] ?? $d->kind }}</td>
                    <td><a class="ns-link" href="{{ route('deals.show', $d->deal_id) }}">#{{ $d->deal_id }}</a> · {{ $d->deal?->warehouseItem?->display_name ?? $d->deal?->title }}</td>
                    <td class="ns-num">{{ $rub($d->amount ?: $d->deal?->amount) }}</td>
                    <td class="{{ $statusCls[$d->status] ?? '' }}" style="white-space:normal">{{ \App\Models\OnecSaleDoc::STATUS_LABELS[$d->status] ?? $d->status }}
                        @if($d->reason)<div @class(['ns-dn' => $d->status === 'error', 'ns-muted' => $d->status !== 'error']) style="font-size:12px">{{ \Illuminate\Support\Str::limit($d->reason, 300) }}</div>@endif</td>
                    <td>{{ $d->onec_number ? 'Отчёт № '.$d->onec_number : '—' }}@if($d->card_code)<div class="ns-muted" style="font-size:12px">карточка {{ $d->card_code }}</div>@endif</td>
                </tr>
            @endforeach
            </tbody>
        </table></div>
    @endif
</div>
@endsection
