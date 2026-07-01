<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <title>Чек · Сделка #{{ $deal->id }}</title>
    <style>
        body{ font-family: ui-monospace, "SFMono-Regular", Menlo, Consolas, monospace; padding:1rem; background:#f4f4f4; }
        .receipt{
            background:#fff; max-width:78mm; margin:0 auto; padding:6mm 4mm;
            border:1px solid #ddd; border-radius:6px;
            font-size:10pt; line-height:1.35;
        }
        .receipt h1{ font-size:12pt; text-align:center; margin:0 0 2mm; }
        .receipt .company{ text-align:center; font-size:9pt; margin-bottom:2mm; color:#333; }
        .receipt hr{ border:0; border-top:1px dashed #999; margin:2mm 0; }
        .receipt .row{ display:flex; justify-content:space-between; }
        .receipt .strong{ font-weight:800; font-size:12pt; }
        .receipt table{ width:100%; border-collapse:collapse; font-size:9.5pt; }
        .receipt td{ padding:1px 0; vertical-align:top; }
        .receipt .r{ text-align:right; }
        .receipt .footnote{ text-align:center; margin-top:3mm; font-size:8pt; color:#666; }
        .actions{ text-align:center; margin-bottom:8mm; }
        .actions button{ padding:.6rem 1.4rem; font-size:12pt; border-radius:6px; border:0; background:#4f46e5; color:#fff; cursor:pointer; }
        @media print{ body{ padding:0; background:#fff; } .actions{ display:none; } .receipt{ border:0; border-radius:0; } }
    </style>
</head>
<body>
    <div class="actions">
        <button onclick="window.print()">🖨️ Распечатать</button>
        <a href="{{ route('deals.show', $deal) }}" style="margin-left:.6rem;">Закрыть</a>
    </div>

    <div class="receipt">
        <h1>ТОВАРНЫЙ ЧЕК</h1>
        <div class="company">
            {{ $company['name'] ?? '—' }}<br>
            @if(!empty($company['inn']))ИНН {{ $company['inn'] }}<br>@endif
            @if(!empty($company['address'])){{ $company['address'] }}<br>@endif
        </div>
        <hr>

        <div class="row"><span>Сделка</span><span>#{{ $deal->id }}</span></div>
        <div class="row"><span>Дата</span><span>{{ ($deal->stock_deducted_at ?: $deal->closed_at ?: $deal->updated_at)->format('d.m.Y H:i') }}</span></div>
        @if($deal->contact)<div class="row"><span>Покупатель</span><span>{{ $deal->contact->name ?: '—' }}</span></div>@endif
        @if($deal->responsible)<div class="row"><span>Продавец</span><span>{{ $deal->responsible->name }}</span></div>@endif
        <hr>

        <table>
            <tr>
                <td>{{ $deal->warehouseItem?->display_name ?? $deal->title }}</td>
                <td class="r">{{ (int) $deal->sold_quantity }} × {{ $deal->sold_quantity ? number_format((float) $deal->amount / max(1, (int) $deal->sold_quantity), 2, ',', ' ') : '—' }} ₽</td>
            </tr>
        </table>
        <hr>
        <div class="row strong">
            <span>ИТОГО</span>
            <span>{{ number_format((float) ($deal->amount ?? 0), 2, ',', ' ') }} ₽</span>
        </div>
        @if($deal->manual_source)
            <div class="row"><span>Источник</span><span>{{ $deal->manual_source }}</span></div>
        @endif

        <div class="footnote">
            Не является фискальным чеком.<br>
            Служит подтверждением факта покупки.
        </div>
    </div>
</body>
</html>
