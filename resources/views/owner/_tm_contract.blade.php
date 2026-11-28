{{--
    Строка расторгнутого договора (App\Services\Owner\ContractTerminations): договор и база 1С, день договора, день расторжения,
    через сколько дней, сумма, замерщик, причина. Нужны $i, $money, $plural; $tmPaidCols — столбцы «Заплатили / Вернули».
    data-* — для фильтров страницы «Расторжения подробно».
--}}
<tr data-kind="{{ $i['kind'] }}" data-measurer="{{ $i['measurer'] !== '' ? $i['measurer'] : '—' }}" data-base="{{ $i['label'] }}" data-amount="{{ (float) $i['amount'] }}"@isset($i['month']) data-month="{{ $i['month'] }}"@endisset>
    <td>@if($i['url'])<a href="{{ $i['url'] }}" target="_blank" rel="noopener" class="tm-link" title="Открыть договор в CRM БлагоДар">№ {{ $i['number'] ?: '—' }}</a>@else<b>№ {{ $i['number'] ?: '—' }}</b>@endif
        <div class="muted tm-sub">{{ $i['label'] }}</div></td>
    <td data-v="{{ $i['contract_date']?->timestamp ?? 0 }}">{{ $i['contract_date']?->format('d.m.Y') ?? '—' }}</td>
    <td data-v="{{ $i['terminated_at']?->timestamp ?? 0 }}">@if($i['terminated_at'])<b>{{ $i['terminated_at']->format('d.m.Y') }}</b>@if(! $i['date_known']) <span class="muted" title="точного дня в 1С нет">≈</span>@endif @else<span class="muted">—</span>@endif</td>
    <td class="num" data-v="{{ $i['days'] ?? -1 }}">@if($i['days'] === null)<span class="muted">—</span>@else{{ $i['days'] }} {{ $plural($i['days'], 'день', 'дня', 'дней') }}@endif</td>
    <td class="num">{{ $money($i['amount']) }}</td>
    @if($tmPaidCols ?? false)
        <td class="num">{{ $money($i['paid']) }}</td>
        <td class="num">{{ $money($i['refunded']) }}</td>
    @endif
    <td>{{ $i['measurer'] !== '' ? $i['measurer'] : 'не определён' }}</td>
    <td><span class="tag {{ $i['kind'] === 'Отказ' ? 'amber' : 'red' }}">{{ $i['reason'] !== '' ? $i['reason'] : $i['kind'] }}</span></td>
</tr>
