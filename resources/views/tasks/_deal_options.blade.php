{{--
    Варианты «Сделка» в окне «Новое дело»: открытые и закрытые за 30 дней (TaskController::index). Если есть закрытые —
    двумя группами. $label — fn ($deal): string; data-s — строка для поиска (tasks._deal_filter_js).
--}}
@php
    [$dealsOpen, $dealsClosed] = $deals->partition(fn ($d) => $d->closed_at === null);
    $dealGroups = $dealsClosed->isEmpty() ? ['' => $dealsOpen] : array_filter(['Открытые' => $dealsOpen, 'Закрытые за 30 дней' => $dealsClosed], fn ($g) => $g->isNotEmpty());
    $dealSearch = fn ($d) => mb_strtolower($label($d).' #'.$d->id.' '.substr(preg_replace('/\D+/', '', (string) ($d->contact?->phone ?? '')), -10));
    $dealSelected = (int) old('deal_id');
@endphp
<option value="">{{ $placeholder ?? 'Выберите сделку' }}</option>
@foreach($dealGroups as $groupLabel => $groupDeals)
    @if($groupLabel !== '')<optgroup label="{{ $groupLabel }}">@endif
    @foreach($groupDeals as $d)
        <option value="{{ $d->id }}" data-s="{{ $dealSearch($d) }}" @selected($dealSelected === (int) $d->id)>{{ $label($d) }}@if($d->closed_at) · закрыта {{ $d->closed_at->format('d.m') }}@endif</option>
    @endforeach
    @if($groupLabel !== '')</optgroup>@endif
@endforeach
