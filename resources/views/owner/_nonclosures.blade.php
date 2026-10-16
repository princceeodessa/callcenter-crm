{{--
    Незаключённые договоры по замерщикам — готовая сводка CRM БлагоДар (App\Services\Owner\NonClosureSummary).
    Рисуется внутри owner/ceilings или отдельно (?part=nonclosures) — тогда помощники форматирования свои.
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
    $ncState = $nonclosures['state'];
    $ncData = $nonclosures['data'] ?? null;
    $ncUpdated = $ncData ? collect($ncData['updated'])->map(fn ($t, $k) => ($k === 'kc_sheet' ? 'таблица КЦ' : ($k === 'onec' ? '1С' : $k)).' — '.$t->format($t->isToday() ? 'H:i' : 'd.m H:i'))->implode(', ') : '';
@endphp
    {{-- Незаключённые договоры --}}
    <div class="sec" id="nonclosures"><h2>Незаключённые договоры по замерщикам</h2>
        <span class="note">@if($ncState === 'ok')сверка таблицы КЦ с 1С@if($ncUpdated !== '') · обновлено: {{ $ncUpdated }}@endif @if($ncData['url']) · <a href="{{ $ncData['url'] }}" target="_blank" rel="noopener" style="color:var(--accent);text-decoration:none">полный отчёт →</a>@endif @endif</span>
    </div>
    @if($ncState !== 'ok')
        <div class="card empty-card {{ $ncState === 'error' ? 'bad' : '' }}">{{ $nonclosures['message'] }}</div>
    @else
        @foreach($ncData['stale'] as $warn)
            <div class="card empty-card bad" style="margin-bottom:12px">⚠ {{ $warn }}</div>
        @endforeach
        <div class="nc-grid">
            @foreach($ncData['blocks'] as $blk)
                <div class="card tbl-card">
                    <div class="tbl-head"><h2>{{ $blk['title'] }}</h2></div>
                    <div class="tbl-wrap"><table class="t">
                        <thead><tr><th>Замерщик</th><th class="num">Замеров</th><th class="num">Незаключ.</th><th class="num">%</th></tr></thead>
                        <tbody>
                        @foreach($blk['rows'] as $r)
                            <tr><td>@include('owner._op_ava', ['name' => $r['measurer']])</td><td class="num">{{ $n($r['measurements']) }}</td><td class="num">{{ $n($r['not_concluded']) }}</td><td class="num">{{ $pct($r['percent']) }}</td></tr>
                        @endforeach
                        <tr class="grp"><td>Итого</td><td class="num">{{ $n($blk['total']['measurements']) }}</td><td class="num">{{ $n($blk['total']['not_concluded']) }}</td><td class="num">{{ $pct($blk['total']['percent']) }}</td></tr>
                        </tbody>
                    </table></div>
                </div>
            @endforeach
        </div>
        @if($ncData['discrepancies'])
            <p class="foot-note" style="margin-top:-14px">Строк, где таблица КЦ спорит с 1С: {{ $n($ncData['discrepancies']) }} — подробно в полном отчёте.</p>
        @endif
    @endif
