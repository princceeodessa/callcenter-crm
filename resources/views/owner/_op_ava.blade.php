{{-- Аватар сотрудника: инициалы на цвете от имени — как в дизайн-системе «БлагоДар» (та же палитра и формула цвета) --}}
@php
    $avaName = trim((string) ($name ?? ''));
    $avaParts = preg_split('/\s+/u', $avaName) ?: [];
    $avaInit = mb_strtoupper(mb_substr($avaParts[0] ?? '', 0, 1).mb_substr($avaParts[1] ?? '', 0, 1));
    $avaPalette = ['#7c6ff0', '#1fae63', '#f0894c', '#4aa3df', '#e0607a', '#36b0b0', '#9a6cf0', '#d9a62b', '#5b8def', '#e05c8f'];
    $avaHash = 0;
    foreach (mb_str_split($avaName) as $avaCh) {
        $avaHash = ($avaHash * 31 + mb_ord($avaCh)) & 0xFFFFFFFF;
    }
@endphp
<span class="op-cell"><span class="op-ava" style="background:{{ $avaPalette[$avaHash % count($avaPalette)] }}">{{ $avaInit !== '' ? $avaInit : '·' }}</span><span>{{ $avaName }}</span></span>
