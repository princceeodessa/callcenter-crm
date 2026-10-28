{{-- кроссовки (28.10.2026): страница в каркасе отдела (layouts.sneakers, ns_nd — Bootstrap цветами темы «Нордика») --}}
@extends(\App\Support\Ui\Sneakers::enabled() ? 'layouts.sneakers' : 'layouts.app')
@if(\App\Support\Ui\Sneakers::enabled())
  @section('ns_nd', '1')
  @section('title', 'Завершённые сделки')
  @section('ns_sub', 'сделки')
@endif

@php
  // редизайн «Nordika» (этап 3) — у колл-центра потолков исходы плитками и таблица как в прототипе; у кроссовок прежняя
  $nd = \App\Support\Ui\Nordika::enabled();
  $ndUi = \App\Support\Ui\Nordika::class;
  $ndResult = [
      'won' => ['Успешно', 'var(--accent)'],
      'lost' => ['Отказ', 'var(--red)'],
      'extra_non_target' => ['Доп. / нецелевая', 'var(--amber)'],
  ];
  $ndPeriod = \Carbon\Carbon::parse($fromValue)->format('d.m.Y').($toValue !== $fromValue ? ' – '.\Carbon\Carbon::parse($toValue)->format('d.m.Y') : '');
@endphp

@if($nd)
  @section('nd_sub', '— завершённые')
  @section('nd_search')
    <div class="nd-search">
      <svg class="nd-icon nd-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
      <input type="search" form="closedFilterForm" name="q" value="{{ $q }}" placeholder="Имя, телефон, название сделки">
    </div>
  @endsection
  @section('nd_toolbar')
    <form method="GET" action="{{ route('deals.closed') }}" id="closedFilterForm">
      <input type="hidden" name="from" value="{{ $fromValue }}">
      <input type="hidden" name="to" value="{{ $toValue }}">
      @if($result !== 'all')<input type="hidden" name="result" value="{{ $result }}">@endif
      @if($category !== '')<input type="hidden" name="category" value="{{ $category }}">@endif
    </form>
    @include('partials.nd-datepick', ['mode' => 'range', 'action' => route('deals.closed'), 'params' => array_filter(['result' => $result !== 'all' ? $result : null, 'category' => $category ?: null, 'q' => $q ?: null]), 'from' => $fromValue, 'to' => $toValue])
    <div class="nd-seg" role="group" aria-label="Результат">
      @foreach(['all' => 'Все', 'won' => 'Успешно', 'lost' => 'Отказ', 'extra_non_target' => 'Доп. / нецелевые'] as $rk => $rl)
        <a @class(['nd-on' => $result === $rk]) href="{{ route('deals.closed', array_filter(['from' => $fromValue, 'to' => $toValue, 'q' => $q ?: null, 'result' => $rk !== 'all' ? $rk : null])) }}">{{ $rl }}</a>
      @endforeach
    </div>
    @if($q !== '' || $result !== 'all' || $category !== '')
      <a class="nd-btn nd-sm" href="{{ route('deals.closed', ['from' => $fromValue, 'to' => $toValue]) }}">Сбросить</a>
    @endif
  @endsection
@endif

@section('content')
@if($nd)
  @if(!empty($outcomeColumns))
    @php
      $ndCount = fn ($k) => (int) ($outcomeCounts[$k] ?? 0);
      $ndTotal = array_sum(array_map('intval', $outcomeCounts));
      $ndBooked = $ndCount('booked');
      $ndLost = $ndCount('lost');
      $ndNon = max(0, $ndTotal - $ndBooked - $ndLost);
      $ndPct = fn ($n) => $ndTotal > 0 ? round($n * 100 / $ndTotal) : 0;
      $ndLink = fn ($k) => route('deals.closed', array_filter(['from' => $fromValue, 'to' => $toValue, 'q' => $q ?: null, 'category' => $category === $k ? null : $k]));
      // нецелевые: «без причины» — отдельной плашкой, причины — плитками; полоски сравнивают причины между собой
      $ndNoneN = $ndCount('none');
      $ndReasons = collect($outcomeColumns)->except(['booked', 'lost', 'none'])
          ->map(fn ($label, $k) => ['key' => $k, 'label' => $label, 'n' => $ndCount($k)])
          ->sortByDesc('n')->values();
      $ndCatTotal = (int) $ndReasons->sum('n');
      $ndMaxReason = max(1, (int) $ndReasons->max('n'));
      $ndPctNon = fn ($n) => $ndNon > 0 ? round($n * 100 / $ndNon) : 0;
      // целевые: сколько из них записали на замер; закрытий в среднем за день периода
      $ndTarget = $ndBooked + $ndLost;
      $ndConv = $ndTarget > 0 ? round($ndBooked * 100 / $ndTarget) : null;
      $ndDays = max(1, \Carbon\Carbon::parse($fromValue)->diffInDays(\Carbon\Carbon::parse($toValue)) + 1);
      $ndPerDay = round($ndTotal / $ndDays, 1);
    @endphp
    <div class="nd-closed-sum">
      <div class="nd-panel nd-closed-total">
        <div class="nd-closed-h">
          <div><div class="nd-kl">Исходы закрытых за {{ $ndPeriod }}</div><div class="nd-closed-n">{{ $ndTotal }} <small>закрыто</small></div></div>
          <div class="nd-closed-legend">
            <span><i style="background:var(--accent)"></i>Записали на замер <b>{{ $ndBooked }}</b> · {{ $ndPct($ndBooked) }}%</span>
            <span><i style="background:var(--red)"></i>Целевые отказы <b>{{ $ndLost }}</b> · {{ $ndPct($ndLost) }}%</span>
            <span><i style="background:var(--amber)"></i>Нецелевые <b>{{ $ndNon }}</b> · {{ $ndPct($ndNon) }}%</span>
          </div>
        </div>
        <div class="nd-closed-bar" role="img" aria-label="Записали {{ $ndPct($ndBooked) }}%, отказы {{ $ndPct($ndLost) }}%, нецелевые {{ $ndPct($ndNon) }}%">
          <span style="width:{{ $ndPct($ndBooked) }}%;background:var(--accent)"></span><span style="width:{{ $ndPct($ndLost) }}%;background:var(--red)"></span><span style="width:{{ $ndPct($ndNon) }}%;background:var(--amber)"></span>
        </div>
        <div class="nd-closed-kpis">
          <div><span>Записали из целевых</span><b style="color:var(--accent)">{{ $ndConv !== null ? $ndConv.'%' : '—' }}</b><small>{{ $ndBooked }} из {{ $ndTarget }}</small></div>
          <div><span>Целевых обращений</span><b>{{ $ndTarget }}</b><small>{{ $ndPct($ndTarget) }}% закрытых</small></div>
          <div><span>Закрывали в день</span><b>{{ str_replace('.', ',', (string) $ndPerDay) }}</b><small>в среднем за {{ $ndDays }} {{ trans_choice('день|дня|дней', $ndDays, [], 'ru') }}</small></div>
        </div>
        <div class="nd-closed-target">
          <a href="{{ $ndLink('booked') }}" @class(['nd-closed-big', 'nd-on' => $category === 'booked']) style="--c:var(--accent)">
            <span class="nd-kl">{{ $outcomeColumns['booked'] ?? 'Целевой записанный' }}</span><b>{{ $ndBooked }}</b><small>{{ $ndPct($ndBooked) }}% закрытых · записали на замер</small>
          </a>
          <a href="{{ $ndLink('lost') }}" @class(['nd-closed-big', 'nd-on' => $category === 'lost']) style="--c:var(--red)">
            <span class="nd-kl">{{ $outcomeColumns['lost'] ?? 'Целевой отказ' }}</span><b>{{ $ndLost }}</b><small>{{ $ndPct($ndLost) }}% закрытых</small>
          </a>
        </div>
      </div>
      <div class="nd-panel nd-closed-reasons">
        <div class="nd-closed-rh"><b>Нецелевые — почему</b><span><b>{{ $ndNon }}</b> · {{ $ndPct($ndNon) }}% закрытых</span></div>
        @if($ndNoneN > 0)
          <a href="{{ $ndLink('none') }}" @class(['nd-reason-none', 'nd-on' => $category === 'none'])>
            <span class="nd-reason-none-ic"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 9v4M12 17h.01M10.3 3.9L2.4 17.6A2 2 0 004.1 20.6h15.8a2 2 0 001.7-3L13.7 3.9a2 2 0 00-3.4 0z"/></svg></span>
            <span class="nd-reason-none-t"><b>{{ $ndNoneN }} · {{ $outcomeColumns['none'] ?? 'Нецелевые без категории' }}</b><small>{{ $ndPctNon($ndNoneN) }}% нецелевых без причины — закрыты до категорий или без неё</small></span>
            <span class="nd-reason-go">Показать →</span>
          </a>
        @endif
        <div class="nd-reason-sub">С причиной — <b>{{ $ndCatTotal }}</b>@if($ndNon > 0) · {{ $ndPctNon($ndCatTotal) }}% нецелевых@endif<span>нажмите — покажем сделки</span></div>
        <div class="nd-reason-grid">
          @foreach($ndReasons as $r)
            <a href="{{ $ndLink($r['key']) }}" @class(['nd-reason2', 'nd-on' => $category === $r['key'], 'nd-zero' => $r['n'] === 0]) title="{{ $r['label'] }}: {{ $r['n'] }}{{ $ndCatTotal > 0 ? ' — '.round($r['n'] * 100 / $ndCatTotal).'% с причиной' : '' }}">
              <span class="nd-reason2-top"><span class="nd-reason2-l">{{ $r['label'] }}</span><b>{{ $r['n'] }}</b>@if($ndCatTotal > 0 && $r['n'] > 0)<small>{{ round($r['n'] * 100 / $ndCatTotal) }}%</small>@endif</span>
              <span class="nd-reason-bar"><i style="width:{{ round($r['n'] * 100 / $ndMaxReason) }}%"></i></span>
            </a>
          @endforeach
        </div>
      </div>
    </div>
  @endif

  <div class="nd-sec-row">
    <h2 class="nd-sec-h">
      @if($category !== '' && isset($outcomeColumns[$category]))
        {{ $outcomeColumns[$category] }}
        <a class="nd-tag nd-closed-clear" href="{{ route('deals.closed', array_filter(['from' => $fromValue, 'to' => $toValue, 'q' => $q ?: null, 'result' => $result !== 'all' ? $result : null])) }}">× снять фильтр</a>
      @else
        Сделки
      @endif
    </h2>
    <span class="nd-sec-n">{{ number_format($deals->total(), 0, ',', ' ') }} шт.</span>
  </div>
  <div class="nd-panel">
    <div class="nd-tbl-wrap">
      <table class="nd-tbl">
        <thead>
          <tr><th>#</th><th>Клиент</th><th>Источник</th><th>Результат</th><th>Ответственный</th><th>Закрыта</th></tr>
        </thead>
        <tbody>
          @forelse ($deals as $deal)
            @php
              $leadName = $deal->lead_display_name ?? 'Без имени';
              $dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title);
              $res = $ndResult[$deal->closed_result] ?? ['Закрыта', 'var(--muted)'];
              $ntLabel = \App\Support\Deals\NonTargetCategory::label($deal->non_target_category);
            @endphp
            <tr data-nd-href="{{ route('deals.show', $deal) }}">
              <td class="nd-faint nd-nowrap">#{{ $deal->id }}</td>
              <td>
                <a class="nd-tname" href="{{ route('deals.show', $deal) }}">{{ $dealTitle }}</a>
                <div class="nd-sub2">{{ $leadName !== $dealTitle ? $leadName.' · ' : '' }}{{ $ndUi::phone($deal->contact?->phone) ?? 'без телефона' }}</div>
              </td>
              <td>{!! $ndUi::sourceChip($ndUi::sourceKey($deal)) !!}</td>
              <td>
                <span class="nd-stage-pill" style="color:{{ $res[1] }}"><span class="nd-d" style="background:{{ $res[1] }}"></span>{{ $res[0] }}</span>
                @if($ntLabel)<div class="nd-sub2">{{ $ntLabel }}</div>@endif
                @if($deal->closed_reason)<div class="nd-sub2 nd-closed-reason">{{ $deal->closed_reason }}</div>@endif
              </td>
              <td>@if($deal->responsible)<span class="nd-person">{!! $ndUi::avatar($deal->responsible->name) !!}{{ $ndUi::firstName($deal->responsible->name) }}</span>@else<span class="nd-faint">—</span>@endif</td>
              <td class="nd-nowrap nd-faint">{{ optional($deal->closed_at)->format('d.m.Y H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="6" class="nd-faint">Нет закрытых сделок за выбранный период</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  {{ $deals->links('pagination.nordika') }}
@else
<style>
  .nt-strip{ display:grid; grid-template-columns:repeat(auto-fit, minmax(118px, 1fr)); gap:.5rem; }
  .nt-cell{ border:1px solid var(--bs-border-color); border-radius:10px; padding:.45rem .65rem; text-decoration:none; color:inherit; min-width:0; }
  .nt-cell:hover{ border-color:#93c5fd; }
  .nt-cell.on{ border-color:#2563eb; background:rgba(37,99,235,.08); }
  .nt-cell .l{ font-size:.72rem; line-height:1.2; color:var(--bs-secondary-color); }
  .nt-cell .v{ font-size:1.3rem; font-weight:700; }
</style>
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  @unless(\App\Support\Ui\Sneakers::enabled())<h4 class="mb-0">Завершённые сделки</h4>@endunless
  <form class="d-flex gap-2 flex-wrap" method="GET" action="{{ route('deals.closed') }}">
    <div class="d-flex align-items-center gap-1">
      <span class="text-muted small">с</span>
      <input type="date" name="from" class="form-control form-control-sm" value="{{ $fromValue }}" style="width: 150px;">
      <span class="text-muted small">по</span>
      <input type="date" name="to" class="form-control form-control-sm" value="{{ $toValue }}" style="width: 150px;">
    </div>
    <select class="form-select form-select-sm" name="result" style="width: 170px;">
      <option value="all" @selected($result === 'all')>Все</option>
      <option value="won" @selected($result === 'won')>Успешно</option>
      <option value="lost" @selected($result === 'lost')>Отказ</option>
      <option value="extra_non_target" @selected($result === 'extra_non_target')>Доп.Работы / Не целевой</option>
    </select>
    @if(!empty($outcomeColumns))
      <select class="form-select form-select-sm" name="category" style="width: 230px;">
        <option value="">Все исходы</option>
        @foreach($outcomeColumns as $key => $label)
          <option value="{{ $key }}" @selected($category === $key)>{{ $label }}</option>
        @endforeach
      </select>
    @endif
    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="поиск: имя, телефон, заголовок">
    <button class="btn btn-sm btn-outline-primary">Показать</button>
  </form>
</div>

@if(!empty($outcomeColumns))
  {{-- потолки: исходы закрытых за месяц — целевые и нецелевые по категориям; колонка — фильтр списка --}}
  <div class="card shadow-sm mb-3">
    <div class="card-body py-2">
      <div class="d-flex justify-content-between flex-wrap gap-2 mb-2">
        <span class="fw-semibold">Исходы закрытых за {{ \Carbon\Carbon::parse($fromValue)->format('d.m.Y') }}{{ $toValue !== $fromValue ? ' – '.\Carbon\Carbon::parse($toValue)->format('d.m.Y') : '' }}</span>
        <span class="text-muted small">нажмите на колонку — покажем эти сделки</span>
      </div>
      <div class="nt-strip">
        @foreach($outcomeColumns as $key => $label)
          @continue($key === 'none' && ($outcomeCounts['none'] ?? 0) === 0)
          <a href="{{ route('deals.closed', array_filter(['from' => $fromValue, 'to' => $toValue, 'q' => $q, 'category' => $category === $key ? null : $key])) }}" class="nt-cell {{ $category === $key ? 'on' : '' }}">
            <div class="l">{{ $label }}</div>
            <div class="v">{{ $outcomeCounts[$key] ?? 0 }}</div>
          </a>
        @endforeach
      </div>
    </div>
  </div>
@endif

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Сделка</th>
          <th>Результат</th>
          <th>Источник</th>
          <th>Клиент</th>
          <th>Закрыта</th>
        </tr>
      </thead>
      <tbody>
        @forelse ($deals as $deal)
          @php($leadName = $deal->lead_display_name ?? 'Без имени')
          @php($dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title))
          <tr class="{{ $deal->lead_source_surface_class }}">
            <td>
              <a href="{{ route('deals.show', $deal) }}" class="fw-semibold text-decoration-none">{{ $dealTitle }}</a>
              <span class="text-muted small ms-1">#{{ $deal->id }}</span>
              @if($deal->closed_reason)
                <div class="text-muted small">Причина: {{ $deal->closed_reason }}</div>
              @endif
            </td>
            <td>
              @php($badge = $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : ($deal->closed_result === 'extra_non_target' ? 'warning' : 'secondary')))
              <span class="badge text-bg-{{ $badge }}">
                {{ $deal->closed_result === 'won' ? 'Успешно' : ($deal->closed_result === 'lost' ? 'Отказ' : ($deal->closed_result === 'extra_non_target' ? 'Доп.Работы / Не целевой' : 'Закрыта')) }}
              </span>
              @if(\App\Support\Deals\NonTargetCategory::label($deal->non_target_category))
                <div class="small text-muted mt-1">{{ \App\Support\Deals\NonTargetCategory::label($deal->non_target_category) }}</div>
              @endif
            </td>
            <td>
              @if($deal->lead_source_chat_url)
                <a href="{{ $deal->lead_source_chat_url }}" class="{{ $deal->lead_source_badge_class }} text-decoration-none" target="_blank" rel="noopener">{{ $deal->lead_source_label }}</a>
              @else
                <span class="{{ $deal->lead_source_badge_class }}">{{ $deal->lead_source_label }}</span>
              @endif
            </td>
            <td>
              <div class="fw-semibold">{{ $leadName }}</div>
              @if($deal->contact?->phone)
                <div class="text-muted small">{{ $deal->contact->phone }}</div>
              @endif
            </td>
            <td class="text-muted small">{{ optional($deal->closed_at)->format('d.m.Y H:i') }}</td>
          </tr>
        @empty
          <tr><td colspan="5" class="text-muted small">Нет данных за выбранный период</td></tr>
        @endforelse
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">
  {{ $deals->links() }}
</div>
@endif
@endsection
