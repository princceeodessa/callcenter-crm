{{--
    «Работа главного по замерщикам» — готовые итоги CRM БлагоДар (App\Services\Owner\HeadWork), только на странице владельца.
    По каждому человеку крупно и словами: обзвонил, договоры в 1С после звонков, ждём / перезвонить / отказались (с причинами),
    сам съездил. Нет итогов — слова, а не нули. Рисуется внутри owner/ceilings или отдельно (?part=headwork) — помощники свои.
--}}
@php
    $n ??= fn ($v) => number_format((float) $v, 0, ',', ' ');
    $money ??= fn ($v) => $v === null ? '—' : number_format((float) $v, 0, ',', ' ').' ₽';
    $plural ??= function (int $k, string $one, string $few, string $many) {
        $k = abs($k) % 100;
        $k1 = $k % 10;

        return $k > 10 && $k < 20 ? $many : ($k1 > 1 && $k1 < 5 ? $few : ($k1 === 1 ? $one : $many));
    };
    $hwState = $headwork['state'] ?? 'off';
    $hw = $headwork['data'] ?? null;
    $hwUpdated = $hw ? collect($hw['updated'])->map(fn ($t, $label) => $label.' — '.$t->format($t->isToday() ? 'H:i' : 'd.m H:i'))->implode(', ') : '';
    $hwOut = \App\Services\Owner\HeadWork::OUTCOMES;
    $hwCalls = \App\Services\Owner\HeadWork::CALLS;
@endphp
<div class="sec" id="headwork"><h2>Работа главного по замерщикам</h2>
    <span class="note">по дню звонка · итоги CRM БлагоДар@if($hwUpdated !== '') · обновлено: {{ $hwUpdated }}@endif</span>
</div>
@if($hwState !== 'ok')
    <div class="card empty-card {{ $hwState === 'error' ? 'bad' : '' }}">{{ $headwork['message'] }}</div>
@else
    @foreach($hw['stale'] as $warn)
        <div class="card hw-stale">⚠ {{ $warn }}</div>
    @endforeach
    @if(! $hw['people'])
        <div class="card empty-card">За выбранный период главный по замерщикам не звонил.</div>
    @else
        <div class="hw-grid">
            @foreach($hw['people'] as $p)
                @php($c = $p['calls'])
                <div class="card hw-card">
                    <div class="hw-head">@include('owner._op_ava', ['name' => $p['user']])</div>
                    <div class="hw-line">Обзвонил <b>{{ $n($c['customers']) }}</b> {{ $plural($c['customers'], 'заказчика', 'заказчиков', 'заказчиков') }} · звонков {{ $n($c['total']) }}</div>
                    <div class="hw-main {{ $p['contracts'] > 0 ? 'good' : '' }}">
                        @if($p['contracts'] > 0)
                            После звонков договор в 1С: <b>{{ $n($p['contracts']) }}</b> на <b>{{ $money($p['contracts_sum']) }}</b>
                        @else
                            После звонков договоров в 1С пока нет
                        @endif
                    </div>
                    <div class="hw-rows">
                        <div><span>Ждём договор</span><b>{{ $n($p['waiting']) }}</b></div>
                        <div><span>Перезвонить</span><b>{{ $n($p['recall']) }}</b></div>
                        <div><span>Отказались</span><b>{{ $n($p['outcomes']['refused']) }}</b></div>
                        <div><span>Сам съездил</span><b>{{ $n($p['visits']['visits']) }}</b></div>
                    </div>
                    @if($p['reasons'])
                        <div class="hw-note"><b>Почему отказались:</b> {{ collect($p['reasons'])->map(fn ($r) => $r['reason'].' — '.$n($r['n']))->implode(' · ') }}</div>
                    @endif
                    @if($p['visits']['visits'] > 0)
                        <div class="hw-note"><b>Съездил сам:</b> {{ $n($p['visits']['visits']) }}, из них договор {{ $n($p['visits']['won']) }}@if($p['visits']['lost']) · отказ {{ $n($p['visits']['lost']) }}@endif @if($p['visits']['open']) · ещё решается {{ $n($p['visits']['open']) }}@endif @if($p['visits']['cancelled']) · отменено {{ $n($p['visits']['cancelled']) }}@endif</div>
                    @endif
                    <div class="hw-note muted">
                        Где сейчас заказчики:
                        {{ collect($hwOut)->filter(fn ($label, $k) => $p['outcomes'][$k] > 0)->map(fn ($label, $k) => $label.' — '.$n($p['outcomes'][$k]))->implode(' · ') ?: 'пока нигде' }}
                    </div>
                    <div class="hw-note muted">
                        Итоги звонков: {{ collect($hwCalls)->filter(fn ($label, $k) => $c[$k] > 0)->map(fn ($label, $k) => $label.' — '.$n($c[$k]))->implode(' · ') ?: '—' }}
                        @if($p['onec']['done'] + $p['onec']['pending'] + $p['onec']['failed'] > 0)
                            · в 1С комментарием: ушло {{ $n($p['onec']['done']) }}@if($p['onec']['pending']), ждут {{ $n($p['onec']['pending']) }}@endif @if($p['onec']['failed']), не ушло {{ $n($p['onec']['failed']) }}@endif
                        @endif
                    </div>
                    @if($p['days'])
                        <div class="hw-note muted">По дням: {{ collect($p['days'])->take(7)->map(fn ($d) => \Carbon\Carbon::parse($d['day'])->format('d.m').' — '.$n($d['customers']).' зак., '.$n($d['calls']).' зв.')->implode(' · ') }}</div>
                    @endif
                    @if($hw['url'])
                        <div class="profit-more"><a class="export" href="{{ $hw['url'] }}" target="_blank" rel="noopener">Подробно
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M7 17L17 7M9 7h8v8"/></svg></a></div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
@endif
