@extends('layouts.app')

@push('styles')
<style>
  .broadcast-category-btn.active {
    box-shadow: inset 0 0 0 1px rgba(255,255,255,.12), 0 10px 24px rgba(15,23,42,.18);
  }
  .broadcast-template-option {
    border: 1px solid rgba(15, 23, 42, .08);
    border-radius: 1rem;
    background: rgba(255,255,255,.75);
    padding: .9rem 1rem;
    text-align: left;
    transition: border-color .15s ease, transform .15s ease, box-shadow .15s ease;
  }
  .broadcast-template-option:hover {
    border-color: rgba(79, 70, 229, .45);
    transform: translateY(-1px);
  }
  .broadcast-template-option.active {
    border-color: rgba(79, 70, 229, .7);
    box-shadow: 0 14px 28px rgba(79, 70, 229, .12);
    background: rgba(79, 70, 229, .06);
  }
  .broadcast-template-title {
    font-weight: 700;
    margin-bottom: .35rem;
  }
  .broadcast-template-preview {
    color: #64748b;
    font-size: .9rem;
    line-height: 1.35;
  }
</style>
@endpush

@php
  $productCategoryOptions = $productCategoryOptions ?? [];
  $broadcastTemplates = $broadcastTemplates ?? [];
  $todayBroadcastCounts = $todayBroadcastCounts ?? [];
  $broadcastTargetModeOptions = $broadcastTargetModeOptions ?? [];
  $selectedBroadcastCategory = old('broadcast_category', ($productCategory ?? '') !== '' ? $productCategory : (array_key_first($broadcastTemplates) ?? array_key_first($productCategoryOptions)));
  $selectedBroadcastTemplate = old('broadcast_template_key', '');
  $selectedBroadcastTargetMode = old('broadcast_target_mode', array_key_first($broadcastTargetModeOptions) ?: 'primary');
  $selectedBroadcastText = old('broadcast_text', '');
  $broadcastTemplatesJson = $broadcastTemplates;
  $broadcastCountsJson = $todayBroadcastCounts;
  $broadcastReport = session('broadcast_report');
@endphp

@section('content')
<div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
  <h4 class="mb-0">Список сделок</h4>
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
    <button class="btn btn-sm btn-outline-primary">Найти</button>
  </form>
</div>

@if(($q ?? '') !== '' && ($status ?? 'open') === 'all')
  <div class="text-muted small mb-3">Поиск включает и завершённые сделки. Если нужен только открытый список, выберите статус вручную.</div>
@endif

@if($broadcastReport)
  <div class="alert alert-info shadow-sm">
    <div class="fw-semibold mb-1">Итог рассылки</div>
    <div class="small">
      Категория: <b>{{ $broadcastReport['category_label'] ?? '—' }}</b>
      • Режим: <b>{{ $broadcastReport['target_mode_label'] ?? '—' }}</b>
      • Дата: <b>{{ $broadcastReport['date_label'] ?? '—' }}</b>
      • Отправлено в чаты: <b>{{ $broadcastReport['sent_count'] ?? 0 }}</b>
      • Сделок затронуто: <b>{{ $broadcastReport['sent_deal_count'] ?? 0 }}</b>
      • Пропущено: <b>{{ $broadcastReport['skipped_count'] ?? 0 }}</b>
      • Ошибок: <b>{{ $broadcastReport['error_count'] ?? 0 }}</b>
    </div>
    <div class="small mt-1">
      VK: <b>{{ $broadcastReport['sent_by_channel']['vk'] ?? 0 }}</b>
      • Avito: <b>{{ $broadcastReport['sent_by_channel']['avito'] ?? 0 }}</b>
    </div>
    @if(!empty($broadcastReport['skipped_items'] ?? []))
      <div class="small mt-2">
        <div class="fw-semibold mb-1">Пропущено</div>
        <ul class="mb-0 ps-3">
          @foreach($broadcastReport['skipped_items'] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      </div>
    @endif
    @if(!empty($broadcastReport['error_items'] ?? []))
      <div class="small mt-2">
        <div class="fw-semibold mb-1">Ошибки</div>
        <ul class="mb-0 ps-3">
          @foreach($broadcastReport['error_items'] as $item)
            <li>{{ $item }}</li>
          @endforeach
        </ul>
      </div>
    @endif
  </div>
@endif

<div class="card shadow-sm mb-3">
  <div class="card-header d-flex align-items-center justify-content-between gap-2 flex-wrap">
    <div>
      <div class="fw-semibold">Рассылка по сделкам с делами на сегодня</div>
      <div class="text-muted small">Отправка идёт только в чаты VK и Avito по открытым сделкам выбранной категории, у которых есть открытое дело на сегодня.</div>
    </div>
    <div class="text-muted small" id="broadcastEligibleSummary">Получатели: —</div>
  </div>
  <div class="card-body">
    <form method="POST" action="{{ route('deals.broadcast-today') }}" id="broadcastTodayForm">
      @csrf
      <input type="hidden" name="broadcast_category" id="broadcastCategoryInput" value="{{ $selectedBroadcastCategory }}">
      <input type="hidden" name="broadcast_template_key" id="broadcastTemplateInput" value="{{ $selectedBroadcastTemplate }}">

      <div class="mb-3">
        <div class="small text-muted mb-2">Категория продукта</div>
        <div class="d-flex gap-2 flex-wrap" id="broadcastCategoryButtons">
          @foreach($productCategoryOptions as $categoryKey => $categoryLabel)
            <button
              type="button"
              class="btn btn-sm btn-outline-primary broadcast-category-btn {{ $selectedBroadcastCategory === $categoryKey ? 'active' : '' }}"
              data-broadcast-category="{{ $categoryKey }}"
              data-broadcast-label="{{ $categoryLabel }}"
            >
              {{ $categoryLabel }}
              <span class="badge text-bg-light ms-1">{{ (int) ($todayBroadcastCounts[$categoryKey] ?? 0) }}</span>
            </button>
          @endforeach
        </div>
      </div>

      <div class="mb-3">
        <div class="small text-muted mb-2">Режим отправки</div>
        <div class="d-flex gap-3 flex-wrap" id="broadcastTargetModeGroup">
          @foreach($broadcastTargetModeOptions as $modeKey => $modeLabel)
            <label class="form-check form-check-inline m-0">
              <input
                class="form-check-input"
                type="radio"
                name="broadcast_target_mode"
                value="{{ $modeKey }}"
                @checked($selectedBroadcastTargetMode === $modeKey)
              >
              <span class="form-check-label">{{ $modeLabel }}</span>
            </label>
          @endforeach
        </div>
      </div>

      <div class="mb-3">
        <div class="small text-muted mb-2">Шаблоны</div>
        <div class="row g-2" id="broadcastTemplateList"></div>
      </div>

      <div class="mb-3">
        <label for="broadcastText" class="form-label">Текст рассылки</label>
        <textarea
          id="broadcastText"
          name="broadcast_text"
          class="form-control"
          rows="8"
          placeholder="Выберите шаблон или введите свой текст"
          required
        >{{ $selectedBroadcastText }}</textarea>
        @error('broadcast_text')
          <div class="text-danger small mt-1">{{ $message }}</div>
        @enderror
      </div>

      <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap">
        <div class="text-muted small" id="broadcastCategoryNote">Выберите категорию и шаблон.</div>
        <button class="btn btn-primary" id="broadcastSubmitButton" type="submit">
          <i class="bi bi-send-fill me-1"></i>Отправить рассылку
        </button>
      </div>
    </form>
  </div>
</div>

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
                @php($badge = $deal->closed_result === 'won' ? 'success' : ($deal->closed_result === 'lost' ? 'danger' : 'secondary'))
                <span class="badge text-bg-{{ $badge }} ms-1">{{ $deal->closed_result ?? 'closed' }}</span>
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
@endsection

@push('scripts')
<script>
(() => {
  const form = document.getElementById('dealSearchForm');
  const status = document.getElementById('dealStatusFilter');
  const query = document.getElementById('dealSearchInput');
  const autoExpand = document.getElementById('dealAutoExpandSearch');
  if (form && status && query && autoExpand) {
    status.addEventListener('change', () => {
      autoExpand.value = '0';
    });

    form.addEventListener('submit', () => {
      const hasQuery = query.value.trim() !== '';
      if (hasQuery && autoExpand.value === '1' && status.value === 'open') {
        status.value = 'all';
      }
    });
  }

  const templates = @json($broadcastTemplatesJson);
  const counts = @json($broadcastCountsJson);
  const categoryButtons = Array.from(document.querySelectorAll('[data-broadcast-category]'));
  const categoryInput = document.getElementById('broadcastCategoryInput');
  const templateInput = document.getElementById('broadcastTemplateInput');
  const templateList = document.getElementById('broadcastTemplateList');
  const textArea = document.getElementById('broadcastText');
  const submitButton = document.getElementById('broadcastSubmitButton');
  const eligibleSummary = document.getElementById('broadcastEligibleSummary');
  const categoryNote = document.getElementById('broadcastCategoryNote');
  const targetModeInputs = Array.from(document.querySelectorAll('input[name="broadcast_target_mode"]'));

  if (!categoryButtons.length || !categoryInput || !templateInput || !templateList || !textArea || !submitButton) {
    return;
  }

  let selectedCategory = categoryInput.value || categoryButtons[0].dataset.broadcastCategory;
  let selectedTemplateKey = templateInput.value || '';

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;');
  }

  function categoryLabel(categoryKey) {
    const button = categoryButtons.find((item) => item.dataset.broadcastCategory === categoryKey);
    return button ? (button.dataset.broadcastLabel || categoryKey) : categoryKey;
  }

  function updateCategoryButtons() {
    categoryButtons.forEach((button) => {
      const active = button.dataset.broadcastCategory === selectedCategory;
      button.classList.toggle('active', active);
      button.classList.toggle('btn-primary', active);
      button.classList.toggle('btn-outline-primary', !active);
    });
  }

  function updateSummary() {
    const count = Number(counts[selectedCategory] || 0);
    const selectedTargetMode = targetModeInputs.find((input) => input.checked)?.value || 'primary';
    const modeHint = selectedTargetMode === 'all'
      ? 'Во все чаты сделки'
      : 'В один чат на сделку';
    eligibleSummary.textContent = 'Получатели: ' + count;
    categoryNote.textContent = count > 0
      ? 'Сегодня в категории «' + categoryLabel(selectedCategory) + '» доступно для отправки: ' + count + ' сделок с открытыми делами. Режим: ' + modeHint + '.'
      : 'На сегодня нет открытых сделок с делами и чатами VK/Avito в выбранной категории.';
    submitButton.disabled = count <= 0;
  }

  function renderTemplates() {
    const items = Array.isArray(templates[selectedCategory]) ? templates[selectedCategory] : [];
    if (!items.length) {
      templateList.innerHTML = '<div class="col-12"><div class="text-muted small">Для выбранной категории шаблонов пока нет.</div></div>';
      templateInput.value = '';
      return;
    }

    if (!items.some((item) => item.key === selectedTemplateKey)) {
      selectedTemplateKey = items[0].key;
      if (!textArea.value.trim()) {
        textArea.value = items[0].text || '';
      }
    }

    templateList.innerHTML = items.map((item) => {
      const active = item.key === selectedTemplateKey;
      return `
        <div class="col-lg-6">
          <button type="button" class="w-100 broadcast-template-option ${active ? 'active' : ''}" data-template-key="${escapeHtml(item.key)}">
            <div class="broadcast-template-title">${escapeHtml(item.title || 'Шаблон')}</div>
            <div class="broadcast-template-preview">${escapeHtml(item.preview || '')}</div>
          </button>
        </div>
      `;
    }).join('');

    templateInput.value = selectedTemplateKey;

    templateList.querySelectorAll('[data-template-key]').forEach((button) => {
      button.addEventListener('click', () => {
        const key = button.getAttribute('data-template-key') || '';
        const template = items.find((item) => item.key === key);
        selectedTemplateKey = key;
        templateInput.value = key;
        if (template) {
          textArea.value = template.text || '';
        }
        renderTemplates();
      });
    });
  }

  categoryButtons.forEach((button) => {
    button.addEventListener('click', () => {
      selectedCategory = button.dataset.broadcastCategory || '';
      selectedTemplateKey = '';
      categoryInput.value = selectedCategory;
      updateCategoryButtons();
      updateSummary();
      renderTemplates();
    });
  });

  targetModeInputs.forEach((input) => {
    input.addEventListener('change', updateSummary);
  });

  categoryInput.value = selectedCategory;
  updateCategoryButtons();
  updateSummary();
  renderTemplates();
})();
</script>
@endpush
