@extends('layouts.app')

@section('content')
@php
    $money = fn ($v) => number_format((float) $v, 0, ',', ' ');
    $todayPairs = collect($todayRows)->where('kind', 'sale')->sum('qty');
    $todaySum = collect($todayRows)->where('kind', 'sale')->sum('amount');
    $lastSync = $connection?->last_synced_at;
    $statusCls = ['done' => 'text-success', 'partial' => 'text-warning-emphasis', 'error' => 'text-danger', 'pending' => 'text-muted'];
    $waitingDays = $waiting->pluck('day')->all();
    $knownDays = $days->map(fn ($d) => $d->day->toDateString())->all();
    $waitingNew = $waiting->filter(fn ($w) => ! in_array($w['day'], $knownDays, true))->values();
@endphp
<style>
    .oc-stat{ background:var(--crm-surface-strong); border:1px solid var(--crm-border); border-radius:12px; padding:.6rem 1rem; min-width:160px; }
    .oc-stat .l{ font-size:.72rem; color:var(--crm-muted); }
    .oc-stat .v{ font-size:1.3rem; font-weight:700; }
    .oc-fix{ display:flex; flex-wrap:wrap; align-items:center; gap:.4rem .7rem; padding:.4rem 0; border-top:1px dashed var(--crm-border); }
    .oc-fix:first-child{ border-top:0; }
    .oc-fix .nm{ flex:1; min-width:220px; }
</style>

<div class="d-flex align-items-center justify-content-between mb-2 flex-wrap gap-2">
    <div>
        <h4 class="mb-0" style="letter-spacing:-.02em">🧾 1С: продажи белых пар</h4>
        <div class="text-muted small">каждый законченный день — один «Отчёт о розничных продажах» в 1С «Обувь»; серые пары в 1С не идут</div>
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
    <div class="oc-stat"><div class="l">Сегодня белых пар · уйдут завтра</div><div class="v">{{ $todayPairs }}@if($todaySum > 0) <span style="font-size:.9rem">· {{ $money($todaySum) }} ₽</span>@endif</div></div>
    <div class="oc-stat"><div class="l">Дней ждут выгрузки</div><div class="v {{ $waiting->isNotEmpty() ? 'text-warning-emphasis' : '' }}">{{ $waiting->count() }}</div></div>
</div>

@if($ambiguous->isNotEmpty())
    <div class="card shadow-sm mb-3 border-warning-subtle">
        <div class="card-header fw-semibold">Уточните: белая или серая пара · {{ $ambiguous->count() }}</div>
        <div class="card-body small">
            <div class="text-muted mb-1">В этих размерах есть и белые, и серые пары. Белые уйдут в отчёт 1С, серые — нет.</div>
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
            <div class="text-muted mb-1">В отчёте 1С наличные и безнал идут раздельно. Без отметки продажа уйдёт как наличные.</div>
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

<div class="card shadow-sm mb-3">
    <div class="card-header fw-semibold">Отчёты в 1С по дням</div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-sm mb-0 align-middle small">
                <thead>
                    <tr><th class="ps-3">День</th><th class="text-end">Пар</th><th class="text-end">Сумма</th><th>Статус</th><th>Документ 1С</th><th class="pe-3">Когда</th></tr>
                </thead>
                <tbody>
                @foreach($waitingNew as $w)
                    <tr>
                        <td class="ps-3">{{ \Carbon\Carbon::parse($w['day'])->format('d.m.Y') }}</td>
                        <td class="text-end">{{ collect($w['rows'])->where('kind', 'sale')->sum('qty') }}</td>
                        <td class="text-end">{{ $money(collect($w['rows'])->where('kind', 'sale')->sum('amount')) }} ₽</td>
                        <td class="text-muted">ждёт выгрузки</td>
                        <td>—</td>
                        <td class="pe-3 text-muted">—</td>
                    </tr>
                @endforeach
                @forelse($days as $d)
                    @php
                        $isWaiting = in_array($d->day->toDateString(), $waitingDays, true);
                    @endphp
                    <tr>
                        <td class="ps-3">{{ $d->day->format('d.m.Y') }}</td>
                        <td class="text-end">{{ $d->pairs }}</td>
                        <td class="text-end">{{ $money($d->amount) }} ₽</td>
                        <td class="{{ $statusCls[$d->status] ?? '' }}">
                            {{ \App\Models\OnecRetailDay::STATUS_LABELS[$d->status] ?? $d->status }}
                            @if($isWaiting && $d->status === 'done') <span class="text-muted">· день изменился, обновится</span>@endif
                            @if($d->status === 'error' && $d->error)<div class="text-danger">{{ \Illuminate\Support\Str::limit($d->error, 300) }}</div>@endif
                            @if(! empty($d->unmapped))
                                <div class="text-muted">без карточки с остатком в 1С:
                                    @foreach($d->unmapped as $u)
                                        @php
                                            $uReason = ! empty($u['reason']) ? ' ('.$u['reason'].')' : '';
                                            $uSep = $loop->last ? '' : ',';
                                        @endphp
                                        <a href="{{ route('deals.show', $u['deal_id']) }}">#{{ $u['deal_id'] }}</a>{{ $uReason }}{{ $uSep }}
                                    @endforeach
                                </div>
                            @endif
                        </td>
                        <td>{{ $d->onec_number ? 'Отчёт № '.$d->onec_number : '—' }}</td>
                        <td class="pe-3 text-muted">{{ $d->exported_at?->format('d.m H:i') ?? $d->attempted_at?->format('d.m H:i') ?? '—' }}</td>
                    </tr>
                @empty
                    @if($waiting->isEmpty())
                        <tr><td colspan="6" class="text-center text-muted py-4">Пока ничего не выгружалось: белых продаж за законченные дни ещё не было.</td></tr>
                    @endif
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>
@endsection
