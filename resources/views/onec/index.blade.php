@extends('layouts.app')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $lastSync = $connection?->last_synced_at;
    $statusCls = ['done' => 'text-success', 'waiting' => 'text-warning-emphasis', 'error' => 'text-danger', 'pending' => 'text-muted'];
    $doneCount = $docs->where('status', 'done')->count();
    $waitingCount = $docs->where('status', 'waiting')->count();
    $errorCount = $docs->where('status', 'error')->count();
@endphp
<style>
    .oc-stat{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:12px; padding:.6rem 1rem; min-width:150px; }
    .oc-stat .l{ font-size:.72rem; color:var(--crm-muted); }
    .oc-stat .v{ font-size:1.3rem; font-weight:700; }
    .oc-fix{ display:flex; flex-wrap:wrap; align-items:center; gap:.4rem .7rem; padding:.4rem 0; border-top:1px dashed var(--crm-border); }
    .oc-fix:first-child{ border-top:0; }
    .oc-fix .nm{ flex:1; min-width:220px; }
</style>

<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
    <div>
        <h4 class="mb-0" style="letter-spacing:-.02em">🧾 1С: продажи белых пар</h4>
        <div class="text-muted small">каждая продажа белой пары — свой «Отчёт о розничных продажах» в 1С «Обувь», возврат — отдельный; серые пары в 1С не идут</div>
    </div>
    <a class="btn btn-sm btn-outline-secondary" href="{{ route('warehouse.index') }}">← К складу</a>
</div>

<div class="d-flex gap-2 flex-wrap mb-3">
    <div class="oc-stat">
        <div class="l">Станция (компьютер с 1С)</div>
        <div class="v" style="font-size:1rem">
            @if(! $connection)
                <span class="text-muted">не подключена</span>
            @elseif($lastSync)
                на связи {{ $lastSync->format('d.m H:i') }}
            @else
                <span class="text-muted">ещё не выходила на связь</span>
            @endif
        </div>
    </div>
    <div class="oc-stat"><div class="l">Выгрузка с</div><div class="v" style="font-size:1rem">{{ $start ? \Carbon\Carbon::parse($start)->format('d.m.Y') : '—' }}</div></div>
    <div class="oc-stat"><div class="l">В очереди</div><div class="v {{ $queue->isNotEmpty() ? 'text-warning-emphasis' : '' }}">{{ $queue->count() }}</div></div>
    <div class="oc-stat"><div class="l">Ещё {{ \App\Services\Onec\SaleDocExport::COOLING_MINUTES }} мин можно поправить</div><div class="v">{{ $cooling }}</div></div>
    <div class="oc-stat"><div class="l">В 1С · ждут прихода · ошибки</div><div class="v" style="font-size:1rem">{{ $doneCount }} · {{ $waitingCount }} · <span class="{{ $errorCount ? 'text-danger' : '' }}">{{ $errorCount }}</span></div></div>
</div>

@if($ambiguous->isNotEmpty())
    <div class="card shadow-sm mb-3 border-warning-subtle">
        <div class="card-header fw-semibold">Уточните: белая или серая пара · {{ $ambiguous->count() }}</div>
        <div class="card-body small">
            <div class="text-muted mb-1">В этих размерах есть и белые, и серые пары. Белые уйдут в 1С, серые — нет.</div>
            @foreach($ambiguous as $d)
                <div class="oc-fix">
                    <div class="nm"><a href="{{ route('deals.show', $d) }}">#{{ $d->id }}</a> · {{ $d->warehouseItem?->display_name ?? $d->title }} · {{ $d->stock_deducted_at?->format('d.m H:i') }}@if($d->amount) · {{ $money($d->amount) }} ₽@endif</div>
                    <form method="POST" action="{{ route('deals.sale-flags', $d) }}" class="m-0 d-flex gap-1">
                        @csrf
                        <button class="btn btn-sm btn-outline-primary" name="white" value="1">🤍 Белая</button>
                        <button class="btn btn-sm btn-outline-secondary" name="white" value="0">Серая</button>
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($noPayment->isNotEmpty())
    <div class="card shadow-sm mb-3 border-warning-subtle">
        <div class="card-header fw-semibold">Белые продажи без способа оплаты · {{ $noPayment->count() }}</div>
        <div class="card-body small">
            <div class="text-muted mb-1">В 1С наличные и безнал учитываются раздельно. Без отметки продажа уйдёт как наличные.</div>
            @foreach($noPayment as $d)
                <div class="oc-fix">
                    <div class="nm"><a href="{{ route('deals.show', $d) }}">#{{ $d->id }}</a> · {{ $d->warehouseItem?->display_name ?? $d->title }} · {{ $d->stock_deducted_at?->format('d.m H:i') }}@if($d->amount) · {{ $money($d->amount) }} ₽@endif</div>
                    <form method="POST" action="{{ route('deals.sale-flags', $d) }}" class="m-0 d-flex gap-1">
                        @csrf
                        @foreach(\App\Models\Deal::PAYMENT_METHODS as $pv => $pl)
                            <button class="btn btn-sm btn-outline-primary" name="payment" value="{{ $pv }}">{{ $pl }}</button>
                        @endforeach
                    </form>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($cancelled->isNotEmpty())
    <div class="card shadow-sm mb-3 border-danger-subtle">
        <div class="card-header fw-semibold">Есть в 1С, но в CRM уже не белая продажа · {{ $cancelled->count() }}</div>
        <div class="card-body small">
            <div class="text-muted mb-1">Продажу отменили или отметили серой после выгрузки. Документ в 1С пометьте на удаление вручную.</div>
            @foreach($cancelled as $c)
                <div class="oc-fix">
                    <div class="nm"><a href="{{ route('deals.show', $c->deal_id) }}">#{{ $c->deal_id }}</a> · {{ $c->deal?->warehouseItem?->display_name ?? $c->deal?->title }} · в 1С: отчёт № {{ $c->onec_number }}@if($c->onec_date) от {{ $c->onec_date->format('d.m.Y H:i') }}@endif</div>
                </div>
            @endforeach
        </div>
    </div>
@endif

@if($queue->isNotEmpty())
    <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">В очереди на выгрузку · {{ $queue->count() }}</div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm mb-0 align-middle small">
                    <tbody>
                    @foreach($queue as $q)
                        @php
                            $qd = $queueDeals[$q['deal_id']] ?? null;
                        @endphp
                        <tr>
                            <td class="ps-3">{{ \Carbon\Carbon::parse($q['date'])->format('d.m H:i') }}</td>
                            <td>{{ \App\Models\OnecSaleDoc::KIND_LABELS[$q['kind']] ?? $q['kind'] }}</td>
                            <td><a href="{{ route('deals.show', $q['deal_id']) }}">#{{ $q['deal_id'] }}</a> · {{ $qd?->warehouseItem?->display_name ?? $qd?->title }}</td>
                            <td class="text-end pe-3">{{ $money($q['row']['amount']) }} ₽</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endif

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Документы в 1С</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle small">
                <thead>
                    <tr><th class="ps-3">Когда</th><th>Что</th><th>Сделка · пара</th><th class="text-end">Сумма</th><th>Статус</th><th class="pe-3">Документ 1С</th></tr>
                </thead>
                <tbody>
                @forelse($docs as $d)
                    @php
                        $when = $d->onec_date ?? ($d->kind === 'return' ? $d->deal?->returned_at : $d->deal?->stock_deducted_at);
                    @endphp
                    <tr>
                        <td class="ps-3">{{ $when?->format('d.m H:i') ?? '—' }}</td>
                        <td>{{ \App\Models\OnecSaleDoc::KIND_LABELS[$d->kind] ?? $d->kind }}</td>
                        <td><a href="{{ route('deals.show', $d->deal_id) }}">#{{ $d->deal_id }}</a> · {{ $d->deal?->warehouseItem?->display_name ?? $d->deal?->title }}</td>
                        <td class="text-end">{{ $money($d->amount ?: $d->deal?->amount) }} ₽</td>
                        <td class="{{ $statusCls[$d->status] ?? '' }}">
                            {{ \App\Models\OnecSaleDoc::STATUS_LABELS[$d->status] ?? $d->status }}
                            @if($d->reason)<div class="{{ $d->status === 'error' ? 'text-danger' : 'text-muted' }}">{{ \Illuminate\Support\Str::limit($d->reason, 300) }}</div>@endif
                        </td>
                        <td class="pe-3">{{ $d->onec_number ? 'Отчёт № '.$d->onec_number : '—' }}@if($d->card_code)<div class="text-muted">карточка {{ $d->card_code }}</div>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-muted py-4">Пока ничего не выгружалось: белых продаж с начала выгрузки ещё не было.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
