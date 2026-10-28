{{-- кроссовки (28.10.2026): страница в каркасе отдела (layouts.sneakers, ns_nd — Bootstrap цветами темы «Нордика») --}}
@extends(\App\Support\Ui\Sneakers::enabled() ? 'layouts.sneakers' : 'layouts.app')
@if(\App\Support\Ui\Sneakers::enabled())
  @section('ns_nd', '1')
  @section('title', 'Список сделок')
  @section('ns_sub', 'сделки')
@endif

@php
  $productCategoryOptions = $productCategoryOptions ?? [];
  // редизайн «Nordika» (этап 3) — у колл-центра потолков таблица как в прототипе; у кроссовок прежняя
  $nd = \App\Support\Ui\Nordika::enabled();
  $ndUi = \App\Support\Ui\Nordika::class;
  $ndFiltered = ($q ?? '') !== '' || ($source ?? '') !== '' || ($productCategory ?? '') !== '' || ($today ?? false) || ($status ?? 'open') !== 'open';
  $ndResult = [
      'won' => ['Успешно', 'var(--accent)'],
      'lost' => ['Отказ', 'var(--red)'],
      'extra_non_target' => ['Доп. / нецелевая', 'var(--amber)'],
  ];
@endphp

@if($nd)
  @section('nd_search')
    <div class="nd-search">
      <svg class="nd-icon nd-sm" viewBox="0 0 24 24"><circle cx="11" cy="11" r="7"/><path d="M21 21l-4-4"/></svg>
      <input type="search" form="dealSearchForm" name="q" value="{{ $q }}" placeholder="Имя, телефон, название сделки" id="dealSearchInput">
    </div>
  @endsection
  @section('nd_toolbar')
    <form method="GET" action="{{ route('deals.index') }}" id="dealSearchForm">
      <input type="hidden" name="auto_expand_search" value="1" id="dealAutoExpandSearch">
      <select class="nd-input" name="status" id="dealStatusFilter" aria-label="Статус">
        <option value="open" @selected(($status ?? 'open') === 'open')>Открытые</option>
        <option value="closed" @selected(($status ?? 'open') === 'closed')>Завершённые</option>
        <option value="all" @selected(($status ?? 'open') === 'all')>Все</option>
      </select>
      <select class="nd-input" name="source" aria-label="Источник">
        <option value="">Все источники</option>
        @foreach(($sourceOptions ?? []) as $sourceKey => $sourceLabel)
          <option value="{{ $sourceKey }}" @selected(($source ?? '') === $sourceKey)>{{ $sourceLabel }}</option>
        @endforeach
      </select>
      <select class="nd-input" name="product_category" aria-label="Категория">
        <option value="">Все категории</option>
        @foreach($productCategoryOptions as $categoryKey => $categoryLabel)
          <option value="{{ $categoryKey }}" @selected(($productCategory ?? '') === $categoryKey)>{{ $categoryLabel }}</option>
        @endforeach
      </select>
      <label class="nd-chipf"><input type="checkbox" name="today" value="1" id="dealTodayFilter" @checked($today ?? false)><span class="nd-sw"></span>Сегодня</label>
      <button class="nd-btn nd-sm nd-primary">Показать</button>
      @if($ndFiltered)
        <a class="nd-btn nd-sm" href="{{ route('deals.index') }}">Сбросить</a>
      @endif
    </form>
  @endsection
@endif

@section('content')
@if($nd)
  @if($today ?? false)
    <div class="nd-tb-note mb-2">Показаны сделки, появившиеся или изменённые сегодня.</div>
  @endif
  @if(($q ?? '') !== '' && ($status ?? 'open') === 'all')
    <div class="nd-tb-note mb-2">Поиск включает и завершённые сделки — для одних открытых выберите «Открытые».</div>
  @endif
  <div class="nd-panel">
    <div class="nd-tbl-wrap">
      <table class="nd-tbl">
        <thead>
          <tr><th>Клиент</th><th>Телефон</th><th>Источник</th><th>Этап</th><th>Категория</th><th>Ответственный</th><th>Создана</th></tr>
        </thead>
        <tbody>
          @forelse ($deals as $deal)
            @php
              $leadName = $deal->lead_display_name ?? 'Без имени';
              $dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title);
              $attn = (int) ($deal->client_attention_count ?? 0);
              $stageName = (string) ($deal->stage?->name ?? '—');
              $closed = $deal->closed_at ? ($ndResult[$deal->closed_result] ?? ['Закрыта', 'var(--muted)']) : null;
            @endphp
            <tr data-nd-href="{{ route('deals.show', $deal) }}">
              <td>
                <a class="nd-tname" href="{{ route('deals.show', $deal) }}">{{ $dealTitle }}</a>
                @if($attn > 0)<span class="nd-cnt-badge nd-attn" title="Новых сообщений">{{ $attn }}</span>@endif
                @if($leadName && $leadName !== $dealTitle)<div class="nd-sub2">{{ $leadName }}</div>@endif
                @if($deal->has_script_deviation)<span class="nd-tag nd-tone-red">отклонения</span>@endif
                @if(!$deal->is_ready)<span class="nd-tag nd-tone-amber">не заполнено</span>@endif
              </td>
              <td class="nd-nowrap">{{ $ndUi::phone($deal->contact?->phone) ?? '—' }}</td>
              <td>
                {!! $ndUi::sourceChip($ndUi::sourceKey($deal)) !!}
                @if($deal->incoming_phone_source_display)<div class="nd-sub2">{{ $deal->incoming_phone_source_display }}</div>@endif
              </td>
              <td>
                @if($closed)
                  <span class="nd-stage-pill" style="color:{{ $closed[1] }}"><span class="nd-d" style="background:{{ $closed[1] }}"></span>{{ $closed[0] }}</span>
                @else
                  <span class="nd-stage-pill" title="{{ $stageName }}"><span class="nd-d" style="background:{{ $ndUi::stageDot($stageName, 0) }}"></span>{{ trim(explode('/', $stageName)[0]) }}</span>
                @endif
              </td>
              <td>{{ $deal->product_category_label ?? '—' }}</td>
              <td>@if($deal->responsible)<span class="nd-person">{!! $ndUi::avatar($deal->responsible->name) !!}{{ $ndUi::firstName($deal->responsible->name) }}</span>@else<span class="nd-faint">—</span>@endif</td>
              <td class="nd-nowrap nd-faint">{{ optional($deal->created_at)->format('d.m.Y H:i') }}</td>
            </tr>
          @empty
            <tr><td colspan="7" class="nd-faint">Сделок не найдено</td></tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>
  {{ $deals->links('pagination.nordika') }}
@else
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  @unless(\App\Support\Ui\Sneakers::enabled())<h4 class="mb-0">Список сделок</h4>@endunless
  <form class="d-flex gap-2 flex-wrap" method="GET" action="{{ route('deals.index') }}" id="dealSearchForm">
    <input type="hidden" name="auto_expand_search" value="1" id="dealAutoExpandSearch">
    <select class="form-select form-select-sm" name="status" style="width: 170px;" id="dealStatusFilter">
      <option value="open" @selected(($status ?? 'open') === 'open')>Открытые</option>
      <option value="closed" @selected(($status ?? 'open') === 'closed')>Завершённые</option>
      <option value="all" @selected(($status ?? 'open') === 'all')>Все</option>
    </select>
    <select class="form-select form-select-sm" name="source" style="width: 240px;">
      <option value="">Все источники</option>
      @foreach(($sourceOptions ?? []) as $sourceKey => $sourceLabel)
        <option value="{{ $sourceKey }}" @selected(($source ?? '') === $sourceKey)>{{ $sourceLabel }}</option>
      @endforeach
    </select>
    <select class="form-select form-select-sm" name="product_category" style="width: 220px;">
      <option value="">Все категории</option>
      @foreach($productCategoryOptions as $categoryKey => $categoryLabel)
        <option value="{{ $categoryKey }}" @selected(($productCategory ?? '') === $categoryKey)>{{ $categoryLabel }}</option>
      @endforeach
    </select>
    <input class="form-control form-control-sm" name="q" value="{{ $q }}" placeholder="поиск: имя, телефон, заголовок" id="dealSearchInput">
    <div class="form-check form-check-inline d-flex align-items-center mb-0">
      <input class="form-check-input" type="checkbox" name="today" value="1" id="dealTodayFilter" @checked($today ?? false)>
      <label class="form-check-label small ms-1" for="dealTodayFilter">Сегодня</label>
    </div>
    <button class="btn btn-sm btn-outline-primary">Найти</button>
  </form>
</div>

@if($today ?? false)
  <div class="text-muted small mb-3">Показаны сделки, появившиеся или изменённые сегодня.</div>
@endif

@if(($q ?? '') !== '' && ($status ?? 'open') === 'all')
  <div class="text-muted small mb-3">Поиск включает и завершённые сделки. Если нужен только открытый список, выберите статус вручную.</div>
@endif

<div class="card shadow-sm">
  <div class="table-responsive">
    <table class="table table-sm align-middle mb-0">
      <thead>
        <tr>
          <th>Сделка</th>
          <th>Источник</th>
          <th>Категория</th>
          <th>Клиент</th>
          <th>Ответственный</th>
          <th>Создано</th>
        </tr>
      </thead>
      <tbody>
        @foreach ($deals as $deal)
          @php($leadName = $deal->lead_display_name ?? 'Без имени')
          @php($dealTitle = $deal->title_is_custom ? $deal->title : ($deal->lead_display_name ?: $deal->title))
          @php($clientAttentionCount = (int) ($deal->client_attention_count ?? 0))
          <tr class="{{ $deal->lead_source_surface_class }}">
            <td>
              <div class="d-flex align-items-center gap-2 flex-wrap">
                {!! $deal->lead_source_icon_html !!}
                <a href="{{ route('deals.show', $deal) }}" class="fw-semibold text-decoration-none">{{ $dealTitle }}</a>
                @if($clientAttentionCount > 0)
                  <span class="badge text-bg-success">{{ $clientAttentionCount }}</span>
                @endif
              </div>
              @if($deal->has_script_deviation) <span class="badge text-bg-danger ms-1">отклонения</span> @endif
              @if(!$deal->is_ready) <span class="badge text-bg-warning ms-1">не заполнено</span> @endif
              @if($deal->closed_at)
                @php($badge = $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : ($deal->closed_result === 'extra_non_target' ? 'warning' : 'secondary')))
                <span class="badge text-bg-{{ $badge }} ms-1">
                  {{ $deal->closed_result === 'won' ? 'Успешно' : ($deal->closed_result === 'lost' ? 'Отказ' : ($deal->closed_result === 'extra_non_target' ? 'Доп.Работы / Не целевой' : 'Закрыта')) }}
                </span>
              @endif
              <div class="text-muted small mt-1">{{ $deal->stage?->name }}</div>
            </td>
            <td>
              @if($deal->lead_source_chat_url)
                <a href="{{ $deal->lead_source_chat_url }}" class="{{ $deal->lead_source_badge_class }} text-decoration-none" target="_blank" rel="noopener">{{ $deal->lead_source_label }}</a>
              @else
                <span class="{{ $deal->lead_source_badge_class }}">{{ $deal->lead_source_label }}</span>
              @endif
              @if($deal->incoming_phone_source_display)
                <div class="text-muted small mt-1">{{ $deal->incoming_phone_source_display }}</div>
              @endif
            </td>
            <td>
              @if($deal->product_category_label)
                <span class="badge {{ $deal->product_category_badge_class }}">{{ $deal->product_category_label }}</span>
              @else
                <span class="text-muted small">—</span>
              @endif
            </td>
            <td>
              <div class="fw-semibold">{{ $leadName }}</div>
              @if($deal->contact?->phone)
                <div class="text-muted small">{{ $deal->contact->phone }}</div>
              @endif
            </td>
            <td>{{ $deal->responsible?->name ?? '—' }}</td>
            <td class="text-muted small">{{ optional($deal->created_at)->format('d.m.Y H:i') }}</td>
          </tr>
        @endforeach
      </tbody>
    </table>
  </div>
</div>

<div class="mt-3">
  {{ $deals->links() }}
</div>
@endif
@endsection

@push('scripts')
<script>
(() => {
  const form = document.getElementById('dealSearchForm');
  const status = document.getElementById('dealStatusFilter');
  const query = document.getElementById('dealSearchInput');
  const autoExpand = document.getElementById('dealAutoExpandSearch');

  if (!form || !status || !query || !autoExpand) {
    return;
  }

  status.addEventListener('change', () => {
    autoExpand.value = '0';
  });

  form.addEventListener('submit', () => {
    const hasQuery = query.value.trim() !== '';
    if (hasQuery && autoExpand.value === '1' && status.value === 'open') {
      status.value = 'all';
    }
  });
})();
</script>
@endpush
