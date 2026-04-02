@extends('layouts.app')

@php
    use Illuminate\Support\Str;
@endphp

@push('styles')
<style>
  .sheet-page{display:flex;flex-direction:column;gap:1rem;padding-bottom:2rem}
  .sheet-surface{border:1px solid var(--crm-border);border-radius:1.25rem;background:var(--crm-surface-strong);box-shadow:var(--crm-shadow)}
  .sheet-panel{padding:1.1rem 1.15rem}
  .sheet-muted{color:var(--crm-muted)}
  .sheet-inline{display:flex;flex-wrap:wrap;gap:.5rem}
  .sheet-badge{display:inline-flex;align-items:center;gap:.35rem;padding:.32rem .72rem;border-radius:999px;background:rgba(79,70,229,.10);color:var(--crm-accent);font-size:.74rem;font-weight:700}
  .sheet-badge.status-slate{background:rgba(100,116,139,.12);color:#475569}
  .sheet-badge.status-blue{background:rgba(37,99,235,.12);color:#1d4ed8}
  .sheet-badge.status-orange{background:rgba(234,88,12,.12);color:#c2410c}
  .sheet-badge.status-violet{background:rgba(124,58,237,.12);color:#7c3aed}
  .sheet-badge.status-amber{background:rgba(217,119,6,.14);color:#b45309}
  .sheet-badge.status-green{background:rgba(22,163,74,.12);color:#15803d}
  .sheet-badge.status-neutral{background:rgba(15,23,42,.08);color:#475569}
  .sheet-select-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.28rem .62rem;border-radius:999px;font-size:.76rem;font-weight:700;border:1px solid transparent}
  .sheet-select-pill.tone-neutral{background:rgba(15,23,42,.08);color:#475569;border-color:rgba(148,163,184,.24)}
  .sheet-select-pill.tone-blue{background:rgba(37,99,235,.12);color:#1d4ed8;border-color:rgba(37,99,235,.18)}
  .sheet-select-pill.tone-green{background:rgba(22,163,74,.12);color:#15803d;border-color:rgba(22,163,74,.18)}
  .sheet-select-pill.tone-amber{background:rgba(217,119,6,.14);color:#b45309;border-color:rgba(217,119,6,.18)}
  .sheet-select-pill.tone-orange{background:rgba(234,88,12,.12);color:#c2410c;border-color:rgba(234,88,12,.18)}
  .sheet-select-pill.tone-red{background:rgba(220,38,38,.12);color:#b91c1c;border-color:rgba(220,38,38,.18)}
  .sheet-select-pill.tone-violet{background:rgba(124,58,237,.12);color:#7c3aed;border-color:rgba(124,58,237,.18)}
  .sheet-select-pill.tone-slate{background:rgba(100,116,139,.12);color:#475569;border-color:rgba(100,116,139,.18)}
  .sheet-hero{display:flex;flex-direction:column;gap:1rem}
  .sheet-hero-top{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}
  .sheet-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}
  .sheet-stat{padding:1rem;border:1px solid var(--crm-border);border-radius:1rem;background:linear-gradient(180deg,rgba(255,255,255,.75),rgba(255,255,255,.52))}
  .sheet-stat-value{font-size:1.15rem;font-weight:700;line-height:1.15}
  .sheet-stat-actions{display:flex;justify-content:space-between;align-items:center;gap:.8rem;flex-wrap:wrap}
  .sheet-adaptive-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:.8rem}
  .sheet-adaptive-card{padding:1rem;border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.48)}
  .sheet-adaptive-items{display:flex;flex-wrap:wrap;gap:.45rem}
  .sheet-custom-card{display:flex;flex-direction:column;gap:.75rem}
  .sheet-custom-value{font-size:2rem;font-weight:800;line-height:1;color:var(--crm-text)}
  .sheet-custom-meta{display:flex;flex-wrap:wrap;gap:.45rem}
  .sheet-strip{display:flex;gap:.8rem;overflow:auto;padding-bottom:.15rem}
  .sheet-mini-card{min-width:240px;display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start;padding:.9rem 1rem;border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.55);text-decoration:none;color:inherit;transition:.16s ease}
  .sheet-mini-card:hover{color:inherit;border-color:color-mix(in srgb,var(--crm-accent) 35%, var(--crm-border))}
  .sheet-mini-card.active{border-color:color-mix(in srgb,var(--crm-accent) 50%, var(--crm-border));box-shadow:0 12px 24px rgba(79,70,229,.12)}
  .sheet-collapse{border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.5)}
  .sheet-collapse summary{cursor:pointer;list-style:none;padding:.95rem 1rem;font-weight:700;display:flex;justify-content:space-between;gap:1rem;align-items:center}
  .sheet-collapse summary::-webkit-details-marker{display:none}
  .sheet-collapse[open] summary{border-bottom:1px solid var(--crm-border)}
  .sheet-collapse-body{padding:1rem}
  .sheet-access-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:1rem}
  .sheet-access-box{padding:1rem;border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.5)}
  .sheet-toolbar{display:flex;justify-content:space-between;gap:1rem;align-items:flex-start;flex-wrap:wrap}
  .sheet-controls{display:flex;flex-wrap:wrap;gap:.6rem;align-items:center}
  .sheet-search{min-width:280px}
  .sheet-toggle{display:inline-flex;align-items:center;gap:.45rem;padding:.4rem .75rem;border:1px solid var(--crm-border);border-radius:999px;background:rgba(255,255,255,.45);font-size:.86rem}
  .sheet-table-wrap{max-height:72vh;overflow:auto;border:1px solid var(--crm-border);border-radius:1rem;background:var(--crm-surface-strong)}
  .sheet-table{width:max-content;min-width:100%;margin:0;border-collapse:separate;border-spacing:0}
  .sheet-table thead th{position:sticky;top:0;background:var(--crm-surface-strong);z-index:4;box-shadow:inset 0 -1px 0 var(--crm-border)}
  .sheet-table tbody tr:nth-child(odd) td,.sheet-table tbody tr:nth-child(odd) th{background:rgba(148,163,184,.04)}
  .sheet-table tbody tr:hover td,.sheet-table tbody tr:hover th{background:rgba(79,70,229,.06)}
  .sheet-row-number{position:sticky;left:0;z-index:3;min-width:154px;max-width:154px;width:154px;background:var(--crm-surface-strong);box-shadow:inset -1px 0 0 var(--crm-border)}
  .sheet-table thead .sheet-row-number{z-index:6}
  .sheet-row-tone-slate .sheet-row-number{box-shadow:inset 4px 0 0 #64748b,inset -1px 0 0 var(--crm-border)}
  .sheet-row-tone-blue .sheet-row-number{box-shadow:inset 4px 0 0 #2563eb,inset -1px 0 0 var(--crm-border)}
  .sheet-row-tone-orange .sheet-row-number{box-shadow:inset 4px 0 0 #ea580c,inset -1px 0 0 var(--crm-border)}
  .sheet-row-tone-violet .sheet-row-number{box-shadow:inset 4px 0 0 #7c3aed,inset -1px 0 0 var(--crm-border)}
  .sheet-row-tone-amber .sheet-row-number{box-shadow:inset 4px 0 0 #d97706,inset -1px 0 0 var(--crm-border)}
  .sheet-row-tone-green .sheet-row-number{box-shadow:inset 4px 0 0 #16a34a,inset -1px 0 0 var(--crm-border)}
  .sheet-row-tone-neutral .sheet-row-number{box-shadow:inset 4px 0 0 #334155,inset -1px 0 0 var(--crm-border)}
  .sheet-row-meta{display:flex;flex-direction:column;align-items:flex-start;gap:.28rem}
  .sheet-row-actions{display:flex;flex-wrap:wrap;gap:.35rem}
  .sheet-row-action{padding:.08rem .38rem;border-radius:999px;font-size:.68rem;line-height:1.15}
  .sheet-row-task-counter{font-size:.7rem;padding:.16rem .4rem;border-radius:999px;background:rgba(15,23,42,.06)}
  .sheet-cell{padding:.68rem .8rem;vertical-align:top;border-color:var(--crm-border);white-space:normal;overflow-wrap:anywhere;word-break:break-word;line-height:1.35}
  .sheet-cell[data-row-cell]{cursor:pointer}
  .sheet-cell[data-row-cell]:focus-visible{outline:2px solid color-mix(in srgb,var(--crm-accent) 70%, #fff);outline-offset:-2px}
  .sheet-nowrap .sheet-cell{white-space:nowrap}
  .sheet-compact .sheet-cell{padding:.48rem .62rem;font-size:.92rem}
  .sheet-cell-empty{color:var(--crm-muted)}
  .sheet-cell-value{display:block;min-height:1.25rem;white-space:inherit}
  .sheet-inline-select{min-width:160px;max-width:100%;border-radius:.75rem;border:1px solid var(--crm-border);background:rgba(255,255,255,.72);font-size:.82rem;padding:.32rem 2rem .32rem .72rem;line-height:1.25}
  .sheet-inline-select:focus{border-color:color-mix(in srgb,var(--crm-accent) 40%, var(--crm-border));box-shadow:0 0 0 .18rem rgba(79,70,229,.12)}
  .sheet-inline-select.is-saving{opacity:.7}
  .sheet-inline-select.is-error{border-color:#dc2626;box-shadow:0 0 0 .18rem rgba(220,38,38,.12)}
  .sheet-col-id{min-width:90px;max-width:120px}
  .sheet-col-name{min-width:280px;max-width:420px}
  .sheet-col-person{min-width:200px;max-width:260px}
  .sheet-col-region{min-width:240px;max-width:360px}
  .sheet-col-city{min-width:150px;max-width:220px}
  .sheet-col-phone{min-width:190px;max-width:240px}
  .sheet-col-address{min-width:320px;max-width:520px}
  .sheet-col-note{min-width:300px;max-width:560px}
  .sheet-col-boolean{min-width:110px;max-width:140px}
  .sheet-col-default{min-width:180px;max-width:320px}
  .sheet-task-context{padding:.85rem 1rem;border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.55)}
  .sheet-status-quick{display:flex;flex-wrap:wrap;gap:.45rem}
  .sheet-history{display:flex;flex-direction:column;gap:.75rem;max-height:280px;overflow:auto}
  .sheet-history-item{padding:.75rem .85rem;border:1px solid var(--crm-border);border-radius:.9rem;background:rgba(255,255,255,.45)}
  .sheet-history-meta{display:flex;justify-content:space-between;gap:.75rem;flex-wrap:wrap}
  .sheet-task-pills{display:flex;flex-wrap:wrap;gap:.45rem}
  .sheet-pill{display:inline-flex;align-items:center;gap:.35rem;padding:.24rem .6rem;border-radius:999px;background:rgba(15,23,42,.06);font-size:.75rem}
  .sheet-editor-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}
  .sheet-editor-field{display:flex;flex-direction:column;gap:.35rem}
  .sheet-column-list{display:flex;flex-direction:column;gap:.75rem}
  .sheet-column-row{display:grid;grid-template-columns:minmax(0,1fr) auto;gap:.85rem;align-items:start;padding:1rem;border:1px solid var(--crm-border);border-radius:.9rem;background:rgba(255,255,255,.45)}
  .sheet-column-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem;align-items:start}
  .sheet-column-actions{display:flex;flex-direction:column;gap:.55rem}
  .sheet-column-options{grid-column:1 / -1}
  .sheet-metric-list{display:flex;flex-direction:column;gap:.75rem}
  .sheet-metric-row{display:grid;grid-template-columns:1.15fr 1fr 1fr 1fr auto;gap:.75rem;align-items:end;padding:1rem;border:1px solid var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.45)}
  .sheet-metric-empty{padding:1rem;border:1px dashed var(--crm-border);border-radius:1rem;background:rgba(255,255,255,.3)}
  #sheetRowModal .modal-dialog{margin:.75rem auto}
  #sheetRowModal .modal-content{max-height:calc(100dvh - 1.5rem)}
  #sheetRowModal .modal-body{overflow-y:auto;min-height:0;max-height:calc(100dvh - 210px)}
  @media (max-width:1199px){.sheet-stats{grid-template-columns:repeat(2,minmax(0,1fr))}.sheet-access-grid{grid-template-columns:1fr}}
  @media (max-width:991px){.sheet-editor-grid{grid-template-columns:1fr}.sheet-metric-row{grid-template-columns:1fr 1fr}}
  @media (max-width:767px){.sheet-stats{grid-template-columns:1fr}.sheet-search{min-width:unset;width:100%}.sheet-row-number{min-width:136px;max-width:136px;width:136px}.sheet-column-row{grid-template-columns:1fr}.sheet-metric-row{grid-template-columns:1fr}}
</style>
@endpush

@section('content')
@php
    $visibleRows = $sheetRows->count();
    $defaultTaskDueAt = now()->addHour()->format('Y-m-d\TH:i');
    $sheetColumnTypeOptions = $sheetColumnTypeOptions ?? \App\Models\NonClosureWorkbookSheet::columnTypeOptions();
    $sheetMetricOperatorOptions = $sheetMetricOperatorOptions ?? [];
    $sheetOptionToneOptions = $sheetOptionToneOptions ?? \App\Models\NonClosureWorkbookSheet::optionToneOptions();
    $adaptiveStats = collect($sheetAdaptiveStats ?? [])->values();
    $customMetricDefinitions = collect($sheetCustomMetrics ?? [])->values();
    $customMetricCards = collect($sheetCustomMetricCards ?? [])->values();
    $columnMeta = collect($sheetColumnMeta ?? [])->values()->map(function ($column, $index) {
        $label = trim((string) ($column['label'] ?? ''));
        $normalized = mb_strtolower($label);
        $class = 'sheet-col-default';
        $type = (string) ($column['type'] ?? 'text');

        if ($index === 0 || str_contains($normalized, 'id') || $normalized === 'aa') {
            $class = 'sheet-col-id';
        } elseif (str_contains($normalized, 'наименование') || str_contains($normalized, 'название')) {
            $class = 'sheet-col-name';
        } elseif (str_contains($normalized, 'менедж') || str_contains($normalized, 'руковод') || str_contains($normalized, 'снабж')) {
            $class = 'sheet-col-person';
        } elseif (str_contains($normalized, 'республика') || str_contains($normalized, 'область') || str_contains($normalized, 'регион')) {
            $class = 'sheet-col-region';
        } elseif (str_contains($normalized, 'город')) {
            $class = 'sheet-col-city';
        } elseif (str_contains($normalized, 'тел') || str_contains($normalized, 'whatsapp') || str_contains($normalized, 'tg')) {
            $class = 'sheet-col-phone';
        } elseif (str_contains($normalized, 'адрес')) {
            $class = 'sheet-col-address';
        } elseif (str_contains($normalized, 'коммент')) {
            $class = 'sheet-col-note';
        } elseif (str_contains($normalized, 'да/нет')) {
            $class = 'sheet-col-boolean';
        }

        if ($type === \App\Models\NonClosureWorkbookSheet::COLUMN_TYPE_SELECT) {
            $class = 'sheet-col-note';
        } elseif ($type === \App\Models\NonClosureWorkbookSheet::COLUMN_TYPE_DATE) {
            $class = 'sheet-col-city';
        } elseif ($type === \App\Models\NonClosureWorkbookSheet::COLUMN_TYPE_NUMBER) {
            $class = 'sheet-col-id';
        }

        $options = collect($column['options'] ?? [])
            ->mapWithKeys(fn ($option) => [mb_strtolower((string) ($option['value'] ?? '')) => [
                'label' => (string) ($option['label'] ?? $option['value'] ?? ''),
                'tone' => (string) ($option['tone'] ?? 'neutral'),
            ]])
            ->all();

        return [
            'label' => $label !== '' ? $label : ('Колонка '.($index + 1)),
            'class' => $class,
            'type' => $type,
            'options' => $column['options'] ?? [],
            'options_index' => $options,
            'options_text' => (string) ($column['options_text'] ?? ''),
            'summary_enabled' => (bool) ($column['summary_enabled'] ?? false),
        ];
    })->values();

    $rowStatePayload = collect($sheetRowStates)->mapWithKeys(function ($state, $rowIndex) {
        return [(int) $rowIndex => [
            'status' => $state->status,
            'status_label' => $state->status_label,
            'status_tone' => $state->status_tone,
            'comment' => $state->comment,
            'assigned_user_id' => $state->assigned_user_id,
            'assigned_user_name' => $state->assignedTo?->name,
            'updated_by_name' => $state->updatedBy?->name,
            'updated_at' => optional($state->updated_at)->format('d.m.Y H:i'),
        ]];
    })->all();

    $rowActivityPayload = collect($sheetRowActivities)->mapWithKeys(function ($items, $rowIndex) {
        return [(int) $rowIndex => collect($items)->map(function ($item) {
            return [
                'type' => $item->type,
                'body' => $item->body,
                'actor' => $item->actor?->name,
                'created_at' => optional($item->created_at)->format('d.m.Y H:i'),
            ];
        })->values()->all()];
    })->all();

    $rowValuePayload = collect($sheetRows)->values()->map(function ($row) {
        return collect((array) $row)->map(fn ($value) => (string) $value)->values()->all();
    })->all();
    $columnDefinitionsPayload = $columnMeta->map(function ($column) {
        return [
            'label' => $column['label'],
            'type' => $column['type'],
            'options' => $column['options'],
        ];
    })->values()->all();
    $metricDefinitionsPayload = $customMetricDefinitions->values()->all();
@endphp

<div class="sheet-page">
  <div class="sheet-surface sheet-panel">
    <div class="sheet-hero">
      <div class="sheet-hero-top">
        <div>
          <div class="sheet-inline mb-2">
            <a class="btn btn-outline-secondary btn-sm" href="{{ route('nonclosures.index', $backQuery) }}"><i class="bi bi-arrow-left"></i> Назад к каталогу</a>
            <span class="sheet-badge"><i class="bi bi-table"></i> Таблица</span>
            <span class="sheet-badge">{{ $sheetCategoryLabel }}</span>
          </div>
          <h2 class="mb-1">{{ $sheet->name }}</h2>
          <div class="sheet-muted">{{ $workbook->title }} • {{ $sheet->row_count }} строк • {{ $sheet->column_count }} колонок</div>
        </div>
        <div class="sheet-inline">
          <span class="sheet-badge">Владелец: {{ $sheet->owner?->name ?? 'Не назначен' }}</span>
          <span class="sheet-badge">Книга: {{ $workbook->title }}</span>
        </div>
      </div>
      <div class="sheet-stats">
        <div class="sheet-stat"><div class="sheet-muted small mb-2">Строки</div><div class="sheet-stat-value">{{ $sheet->row_count }}</div></div>
        <div class="sheet-stat"><div class="sheet-muted small mb-2">Колонки</div><div class="sheet-stat-value">{{ $sheet->column_count }}</div></div>
        <div class="sheet-stat"><div class="sheet-muted small mb-2">Строк со статусом</div><div class="sheet-stat-value">{{ collect($sheetRowStates)->count() }}</div></div>
        <div class="sheet-stat"><div class="sheet-muted small mb-2">Открытых задач</div><div class="sheet-stat-value">{{ collect($sheetRowTaskStats)->sum('open') }}</div></div>
      </div>
      <div>
        <div class="sheet-stat-actions mb-2">
          <div>
            <div class="sheet-muted small">Пользовательская аналитика</div>
            <div class="sheet-muted small">Собирайте свои показатели по колонкам и условиям: кто работает с вами, кто не закупался 14 дней и т.д.</div>
          </div>
          <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#sheetMetricsModal">
            <i class="bi bi-sliders"></i> Настроить показатели
          </button>
        </div>
        @if($customMetricCards->isNotEmpty())
          <div class="sheet-adaptive-grid">
            @foreach($customMetricCards as $metricCard)
              <div class="sheet-adaptive-card sheet-custom-card">
                <div class="d-flex justify-content-between align-items-start gap-2 flex-wrap">
                  <div>
                    <div class="fw-semibold">{{ $metricCard['label'] }}</div>
                    <div class="sheet-muted small mt-1">{{ $metricCard['description'] }}</div>
                  </div>
                  <span class="sheet-select-pill tone-{{ $metricCard['tone'] ?? 'blue' }}">{{ $metricCard['column_label'] }}</span>
                </div>
                <div class="sheet-custom-value">{{ $metricCard['value'] }}</div>
                <div class="sheet-custom-meta">
                  <span class="sheet-pill">Доля: {{ $metricCard['percent'] }}%</span>
                  <span class="sheet-pill">Из {{ $metricCard['total'] }}</span>
                </div>
              </div>
            @endforeach
          </div>
        @else
          <div class="sheet-metric-empty">
            <div class="fw-semibold mb-1">Показатели ещё не настроены</div>
            <div class="sheet-muted small">Добавьте свои правила, чтобы видеть наверху, сколько клиентов работает с вами, кто давно не закупался, сколько строк пустые и любые другие простые метрики.</div>
          </div>
        @endif
      </div>
      @if($adaptiveStats->isNotEmpty())
        <div>
          <div class="sheet-muted small mb-2">Автоматические показатели по типизированным колонкам</div>
          <div class="sheet-adaptive-grid">
            @foreach($adaptiveStats as $stat)
              <div class="sheet-adaptive-card">
                <div class="fw-semibold mb-1">{{ $stat['label'] }}</div>
                <div class="sheet-muted small mb-3">{{ $stat['description'] }}</div>
                <div class="sheet-adaptive-items">
                  @foreach($stat['items'] as $item)
                    <span class="sheet-select-pill tone-{{ $item['tone'] ?? 'neutral' }}">{{ $item['label'] }}: {{ $item['value'] }}</span>
                  @endforeach
                </div>
              </div>
            @endforeach
          </div>
        </div>
      @endif
    </div>
  </div>

  <div class="sheet-surface sheet-panel">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
      <div>
        <h4 class="mb-1">Листы этой книги</h4>
        <div class="sheet-muted small">Быстрое переключение между листами без возврата в каталог.</div>
      </div>
      <span class="sheet-badge">{{ $siblingSheets->count() }} листов</span>
    </div>
    <div class="sheet-strip">
      @foreach($siblingSheets as $siblingSheet)
        <a href="{{ route('nonclosures.sheets.show', array_merge(['sheet' => $siblingSheet->id], $backQuery)) }}" class="sheet-mini-card {{ $siblingSheet->id === $sheet->id ? 'active' : '' }}">
          <div>
            <div class="fw-semibold">{{ $siblingSheet->name }}</div>
            <div class="small sheet-muted mt-1">{{ $sheetCategories[$siblingSheet->category] ?? $siblingSheet->category }}</div>
          </div>
          <span class="sheet-badge">{{ $siblingSheet->row_count }}</span>
        </a>
      @endforeach
    </div>
  </div>

  <details class="sheet-collapse">
    <summary><span>Доступ и владельцы</span><span class="sheet-muted small">Блок можно свернуть, если он мешает работе с таблицей</span></summary>
    <div class="sheet-collapse-body">
      <div class="sheet-access-grid">
        @if($canManageDocuments)
          <form method="POST" action="{{ route('nonclosures.workbooks.access.update', $workbook) }}" class="sheet-access-box d-flex flex-column gap-3">
            @csrf
            @method('PATCH')
            <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
            <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
            <input type="hidden" name="workbook" value="{{ $workbook->id }}">
            <input type="hidden" name="redirect_sheet_id" value="{{ $sheet->id }}">
            <div>
              <label class="form-label">Владелец книги</label>
              <select class="form-select" name="owner_user_id">
                <option value="">Не назначен</option>
                @foreach($activeUsers as $activeUser)
                  <option value="{{ $activeUser->id }}" @selected((int) $workbook->owner_user_id === (int) $activeUser->id)>{{ $activeUser->name }} • {{ $activeUser->role }}</option>
                @endforeach
              </select>
            </div>
            <button type="submit" class="btn btn-outline-primary align-self-start">Сохранить владельца книги</button>
          </form>

          <form method="POST" action="{{ route('nonclosures.sheets.access.update', $sheet) }}" class="sheet-access-box d-flex flex-column gap-3">
            @csrf
            @method('PATCH')
            <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
            <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
            <input type="hidden" name="workbook" value="{{ $workbook->id }}">
            <input type="hidden" name="redirect_to_sheet" value="1">
            <div>
              <label class="form-label">Владелец таблицы</label>
              <select class="form-select" name="owner_user_id">
                <option value="">Не назначен</option>
                @foreach($activeUsers as $activeUser)
                  <option value="{{ $activeUser->id }}" @selected((int) $sheet->owner_user_id === (int) $activeUser->id)>{{ $activeUser->name }} • {{ $activeUser->role }}</option>
                @endforeach
              </select>
            </div>
            <div>
              <div class="form-label mb-2">Доступ к таблице</div>
              <div class="d-flex flex-column gap-2">
                @foreach($activeUsers as $activeUser)
                  @continue((int) $sheet->owner_user_id === (int) $activeUser->id)
                  <label class="d-flex align-items-center gap-2">
                    <input type="checkbox" class="form-check-input mt-0" name="shared_user_ids[]" value="{{ $activeUser->id }}" @checked(in_array((int) $activeUser->id, $selectedSheetSharedIds, true))>
                    <span>{{ $activeUser->name }}</span>
                    <span class="sheet-muted small">{{ $activeUser->role }}</span>
                  </label>
                @endforeach
              </div>
            </div>
            <button type="submit" class="btn btn-primary align-self-start">Сохранить доступ</button>
          </form>
        @else
          <div class="sheet-access-box">
            <div class="small sheet-muted mb-2">Владелец книги</div>
            <div class="fw-semibold mb-3">{{ $workbook->owner?->name ?? 'Не назначен' }}</div>
            <div class="small sheet-muted mb-2">Владелец таблицы</div>
            <div class="fw-semibold mb-3">{{ $sheet->owner?->name ?? 'Не назначен' }}</div>
            <div class="small sheet-muted mb-2">Дополнительный доступ</div>
            <div class="fw-semibold">{{ $sheet->sharedUsers->pluck('name')->implode(', ') ?: 'Дополнительных доступов нет' }}</div>
          </div>
        @endif
      </div>
    </div>
  </details>

  <div class="sheet-surface sheet-panel">
    <div class="sheet-toolbar mb-3">
      <div>
        <h4 class="mb-1">Полноформатная таблица</h4>
        <div class="sheet-muted small">Двойной клик по ячейке открывает редактирование нужного поля. Можно редактировать строки, добавлять новые, управлять колонками и сразу ставить задачи по строкам. Закреплён только служебный столбец слева, чтобы текст больше не налезал.</div>
      </div>
      <div class="sheet-controls">
        <input id="sheetSearch" type="search" class="form-control sheet-search" placeholder="Поиск по таблице">
        <select id="sheetStatusFilter" class="form-select">
          <option value="">Все статусы</option>
          <option value="none">Без статуса</option>
          @foreach($rowStatusOptions as $statusValue => $statusLabel)
            <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
          @endforeach
        </select>
        <label class="sheet-toggle"><input type="checkbox" class="form-check-input mt-0" id="sheetWrapToggle" checked> Переносить текст</label>
        <label class="sheet-toggle"><input type="checkbox" class="form-check-input mt-0" id="sheetCompactToggle"> Компактный режим</label>
        <button type="button" class="btn btn-primary btn-sm" id="sheetAddRowButton"><i class="bi bi-plus-circle"></i> Добавить строку</button>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#sheetColumnsModal"><i class="bi bi-layout-three-columns"></i> Столбцы</button>
        <span class="sheet-badge">Видно строк: <span id="sheetVisibleRows">{{ $visibleRows }}</span></span>
      </div>
    </div>

    <div class="sheet-table-wrap">
      <table class="table sheet-table" id="sheetTable">
        <thead>
          <tr>
            <th class="sheet-row-number">Строка</th>
            @foreach($columnMeta as $meta)
              <th class="{{ $meta['class'] }}">{{ $meta['label'] }}</th>
            @endforeach
          </tr>
        </thead>
        <tbody>
          @forelse($sheetRows as $rowIndex => $row)
            @php
              $rowNumber = $rowIndex + 1;
              $rowState = $sheetRowStates[$rowNumber] ?? null;
              $rowTone = $rowState?->status_tone ?? 'neutral';
              $rowStatusValue = $rowState?->status ?? 'none';
              $rowStatusLabel = $rowState?->status_label ?? 'Без статуса';
              $taskStat = $sheetRowTaskStats[$rowNumber] ?? ['open' => 0];
              $rowSummary = collect((array) $row)->filter(fn ($value) => trim((string) $value) !== '')->take(5)->implode(' | ');
              $rowSubject = trim((string) (((array) $row)[1] ?? ((array) $row)[0] ?? ''));
              $rowTaskTitle = 'Связаться: '.($rowSubject !== '' ? Str::limit($rowSubject, 120, '') : ('строка '.$rowNumber));
              $rowSearchText = mb_strtolower(implode(' ', array_filter([
                  $rowSummary,
                  $rowState?->comment,
                  $rowState?->assignedTo?->name,
                  $rowStatusLabel,
              ])));
            @endphp
            <tr id="row-{{ $rowNumber }}" class="sheet-row-tone-{{ $rowTone }}" data-row-text="{{ $rowSearchText }}" data-row-status="{{ $rowStatusValue }}">
              <th scope="row" class="sheet-row-number">
                <div class="sheet-row-meta">
                  <div class="fw-semibold">#{{ $rowNumber }}</div>
                  <span class="sheet-badge status-{{ $rowTone }}">{{ $rowStatusLabel }}</span>
                  @if(($taskStat['open'] ?? 0) > 0)
                    <span class="sheet-row-task-counter">{{ $taskStat['open'] }} откр.</span>
                  @endif
                  @if(!empty($rowState?->comment))
                    <div class="small sheet-muted">Есть комментарий</div>
                  @endif
                  <div class="sheet-row-actions">
                    <button type="button" class="btn btn-sm btn-outline-secondary sheet-row-action" data-row-work data-row-index="{{ $rowNumber }}">Строка</button>
                    <button type="button" class="btn btn-sm btn-outline-primary sheet-row-action" data-row-task data-row-index="{{ $rowNumber }}" data-row-summary="{{ $rowSummary }}" data-row-title="{{ $rowTaskTitle }}">Задача</button>
                    <button type="button" class="btn btn-sm btn-outline-success sheet-row-action" data-row-insert data-row-index="{{ $rowNumber }}">Ниже</button>
                  </div>
                </div>
              </th>
              @foreach($columnMeta as $metaIndex => $meta)
                @php
                  $cellValue = trim((string) (((array) $row)[$metaIndex] ?? ''));
                  $selectOption = $meta['type'] === \App\Models\NonClosureWorkbookSheet::COLUMN_TYPE_SELECT
                    ? ($meta['options_index'][mb_strtolower($cellValue)] ?? null)
                    : null;
                @endphp
                <td
                  class="sheet-cell {{ $meta['class'] }} {{ $cellValue === '' ? 'sheet-cell-empty' : '' }}"
                  title="{{ $cellValue !== '' ? $cellValue : 'Пусто' }}"
                  data-row-cell
                  data-row-index="{{ $rowNumber }}"
                  data-col-index="{{ $metaIndex }}"
                  tabindex="0"
                >
                  @if($meta['type'] === \App\Models\NonClosureWorkbookSheet::COLUMN_TYPE_SELECT)
                    <select class="sheet-inline-select" data-inline-select data-row-index="{{ $rowNumber }}" data-col-index="{{ $metaIndex }}" aria-label="{{ $meta['label'] }}">
                      <option value="">Не выбрано</option>
                      @foreach($meta['options'] as $option)
                        <option value="{{ $option['value'] }}" @selected((string) $option['value'] === $cellValue)>{{ $option['label'] }}</option>
                      @endforeach
                      @if($cellValue !== '' && !$selectOption)
                        <option value="{{ $cellValue }}" selected>{{ $cellValue }}</option>
                      @endif
                    </select>
                  @elseif($cellValue === '')
                    <div class="sheet-cell-value">—</div>
                  @else
                    <div class="sheet-cell-value">{{ $cellValue }}</div>
                  @endif
                </td>
              @endforeach
            </tr>
          @empty
            <tr>
              <td colspan="{{ max(1, $sheetHeader->count() + 1) }}" class="text-center py-4 text-muted">В таблице пока нет строк.</td>
            </tr>
          @endforelse
        </tbody>
      </table>
    </div>
  </div>

  <div class="modal fade" id="sheetRowModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1" id="sheetRowModalTitle">Строка таблицы</h5>
            <div class="sheet-muted small" id="sheetRowLabel">Строка</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <form method="POST" id="sheetRowForm">
          @csrf
          <input type="hidden" name="_method" id="sheetRowMethod" value="PATCH">
          <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
          <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
          <input type="hidden" name="workbook" value="{{ $workbook->id }}">
          <input type="hidden" name="position" id="sheetRowPosition" value="">
          <div class="modal-body d-flex flex-column gap-3">
            <div class="sheet-task-context">
              <div class="small sheet-muted mb-2">Контекст строки</div>
              <div class="fw-semibold" id="sheetRowSummary">—</div>
            </div>
            <div class="sheet-editor-grid">
              <div>
                <label class="form-label">Статус</label>
                <select class="form-select" name="status" id="sheetRowStatus">
                  @foreach($rowStatusOptions as $statusValue => $statusLabel)
                    <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="form-label">Ответственный</label>
                <select class="form-select" name="assigned_user_id" id="sheetRowAssignedUser">
                  <option value="0">Не назначен</option>
                  @foreach($activeUsers as $activeUser)
                    <option value="{{ $activeUser->id }}">{{ $activeUser->name }}</option>
                  @endforeach
                </select>
              </div>
            </div>
            <div class="sheet-status-quick">
              @foreach($rowStatusOptions as $statusValue => $statusLabel)
                <button type="button" class="btn btn-sm btn-outline-secondary" data-status-quick="{{ $statusValue }}">{{ $statusLabel }}</button>
              @endforeach
            </div>
            <div class="sheet-editor-grid">
              <div>
                <label class="form-label">Последнее изменение</label>
                <input type="text" class="form-control" id="sheetRowUpdatedAt" value="—" readonly>
              </div>
              <div>
                <label class="form-label">Комментарий по строке</label>
                <textarea class="form-control" name="comment" id="sheetRowComment" rows="4" placeholder="Например: перезвонить завтра, добить до ответа, уточнить условия"></textarea>
              </div>
            </div>
            <div>
              <h6 class="mb-0">Значения строки</h6>
              <div class="sheet-muted small mb-2">Ячейки можно редактировать прямо здесь.</div>
              <div class="sheet-editor-grid" id="sheetRowValuesEditor"></div>
            </div>
            <div>
              <div class="sheet-task-pills mb-3" id="sheetRowTaskStats"></div>
              <h6 class="mb-0">История строки</h6>
              <div class="sheet-muted small mb-2">Изменения статуса, комментариев, строковых данных и созданные задачи.</div>
              <div class="sheet-history" id="sheetRowHistory"></div>
            </div>
          </div>
          <div class="modal-footer justify-content-between">
            <button type="button" class="btn btn-outline-danger d-none" id="sheetRowDeleteButton">Удалить строку</button>
            <div class="d-flex gap-2 ms-auto">
              <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
              <button type="submit" class="btn btn-primary">Сохранить строку</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="sheetTaskModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1">Напоминание по строке</h5>
            <div class="sheet-muted small" id="sheetTaskRowLabel">Строка</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <form method="POST" action="{{ route('nonclosures.sheets.tasks.store', $sheet) }}">
          @csrf
          <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
          <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
          <input type="hidden" name="workbook" value="{{ $workbook->id }}">
          <input type="hidden" name="row_index" id="sheetTaskRowIndex" value="">
          <div class="modal-body d-flex flex-column gap-3">
            <div class="sheet-task-context">
              <div class="small sheet-muted mb-2">Строка</div>
              <div class="fw-semibold mb-1" id="sheetTaskRowSummary">—</div>
            </div>
            <div>
              <label class="form-label">Название задачи</label>
              <input type="text" class="form-control" name="title" id="sheetTaskTitle" required>
            </div>
            <div class="sheet-editor-grid">
              <div>
                <label class="form-label">Кому назначить</label>
                <select class="form-select" name="assigned_user_id">
                  <option value="0">Всем</option>
                  @foreach($activeUsers as $activeUser)
                    <option value="{{ $activeUser->id }}">{{ $activeUser->name }}</option>
                  @endforeach
                </select>
              </div>
              <div>
                <label class="form-label">Когда напомнить</label>
                <input type="datetime-local" class="form-control" name="due_at" value="{{ $defaultTaskDueAt }}">
              </div>
            </div>
            <div>
              <label class="form-label">Комментарий</label>
              <textarea class="form-control" name="description" id="sheetTaskDescription" rows="4" placeholder="Например: позвонить, добить, уточнить условия"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
            <button type="submit" class="btn btn-primary">Создать напоминание</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <div class="modal fade" id="sheetColumnsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1">Колонки таблицы</h5>
            <div class="sheet-muted small">Можно добавлять, переименовывать и удалять колонки.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <div class="modal-body d-flex flex-column gap-3">
          <form method="POST" action="{{ route('nonclosures.sheets.columns.store', $sheet) }}" class="sheet-access-box d-flex flex-column gap-3">
            @csrf
            <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
            <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
            <input type="hidden" name="workbook" value="{{ $workbook->id }}">
            <div class="sheet-column-form">
              <div>
                <label class="form-label">Новая колонка</label>
                <input type="text" class="form-control" name="label" placeholder="Например: Статус сотрудничества">
              </div>
              <div>
                <label class="form-label">Тип поля</label>
                <select class="form-select" name="type" data-column-type-select>
                  @foreach($sheetColumnTypeOptions as $typeValue => $typeLabel)
                    <option value="{{ $typeValue }}">{{ $typeLabel }}</option>
                  @endforeach
                </select>
              </div>
              <div class="sheet-column-options d-none" data-column-options-block>
                <label class="form-label">Варианты выбора</label>
                <textarea class="form-control" name="options_text" rows="4" placeholder="Работаем с нами|green&#10;Не работает с нами|red&#10;На паузе|amber"></textarea>
                <div class="sheet-muted small mt-2">Каждый вариант с новой строки. Можно добавить цвет через `|green`, `|red`, `|amber`, `|blue`.</div>
              </div>
              <div class="d-flex align-items-center gap-2">
                <input type="hidden" name="summary_enabled" value="0">
                <input type="checkbox" class="form-check-input mt-0" id="columnSummaryCreate" name="summary_enabled" value="1" checked>
                <label class="form-check-label" for="columnSummaryCreate">Показывать в сводке сверху</label>
              </div>
            </div>
            <button type="submit" class="btn btn-primary align-self-start">Добавить колонку</button>
          </form>

          <div class="sheet-column-list">
            @foreach($columnMeta as $index => $meta)
              <div class="sheet-column-row">
                <form method="POST" action="{{ route('nonclosures.sheets.columns.update', ['sheet' => $sheet->id, 'columnIndex' => $index + 1]) }}" class="sheet-column-form" id="sheetColumnForm{{ $index }}">
                  @csrf
                  @method('PATCH')
                  <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
                  <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
                  <input type="hidden" name="workbook" value="{{ $workbook->id }}">
                  <div>
                    <label class="form-label">Название</label>
                    <input type="text" class="form-control" name="label" value="{{ $meta['label'] }}">
                  </div>
                  <div>
                    <label class="form-label">Тип</label>
                    <select class="form-select" name="type" data-column-type-select>
                      @foreach($sheetColumnTypeOptions as $typeValue => $typeLabel)
                        <option value="{{ $typeValue }}" @selected($meta['type'] === $typeValue)>{{ $typeLabel }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="sheet-column-options {{ $meta['type'] === \App\Models\NonClosureWorkbookSheet::COLUMN_TYPE_SELECT ? '' : 'd-none' }}" data-column-options-block>
                    <label class="form-label">Варианты выбора</label>
                    <textarea class="form-control" name="options_text" rows="4" placeholder="Работаем с нами|green&#10;Не работает с нами|red">{{ $meta['options_text'] }}</textarea>
                  </div>
                  <div class="d-flex align-items-center gap-2">
                    <input type="hidden" name="summary_enabled" value="0">
                    <input type="checkbox" class="form-check-input mt-0" id="columnSummary{{ $index }}" name="summary_enabled" value="1" @checked($meta['summary_enabled'])>
                    <label class="form-check-label" for="columnSummary{{ $index }}">Показывать в сводке</label>
                  </div>
                </form>
                <div class="sheet-column-actions">
                  <span class="sheet-badge">{{ $index + 1 }}</span>
                  <button type="submit" form="sheetColumnForm{{ $index }}" class="btn btn-outline-primary btn-sm">Сохранить</button>
                  <form method="POST" action="{{ route('nonclosures.sheets.columns.destroy', ['sheet' => $sheet->id, 'columnIndex' => $index + 1]) }}" onsubmit="return confirm('Удалить колонку «{{ addslashes($meta['label']) }}»?');">
                    @csrf
                    @method('DELETE')
                    <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
                    <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
                    <input type="hidden" name="workbook" value="{{ $workbook->id }}">
                    <button type="submit" class="btn btn-outline-danger btn-sm" @disabled($sheetHeader->count() <= 1)>Удалить</button>
                  </form>
                </div>
              </div>
            @endforeach
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="sheetMetricsModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title mb-1">Пользовательская аналитика</h5>
            <div class="sheet-muted small">Добавляйте свои карточки по условиям: по выбору, дате, числу или заполненности поля.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Закрыть"></button>
        </div>
        <form method="POST" action="{{ route('nonclosures.sheets.metrics.update', $sheet) }}">
          @csrf
          @method('PATCH')
          <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
          <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
          <input type="hidden" name="workbook" value="{{ $workbook->id }}">
          <div class="modal-body d-flex flex-column gap-3">
            <div class="d-flex justify-content-between align-items-center gap-3 flex-wrap">
              <div class="sheet-muted small">Например: “Работаем с нами”, “Последняя закупка старше 14 дней”, “Пустой менеджер”.</div>
              <button type="button" class="btn btn-outline-primary btn-sm" id="sheetMetricAddButton">
                <i class="bi bi-plus-circle"></i> Добавить показатель
              </button>
            </div>
            <div class="sheet-metric-list" id="sheetMetricsEditor"></div>
            <div class="sheet-metric-empty d-none" id="sheetMetricsEmpty">
              <div class="fw-semibold mb-1">Показателей пока нет</div>
              <div class="sheet-muted small">Нажмите “Добавить показатель” и соберите свои карточки под этот лист.</div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Отмена</button>
            <button type="submit" class="btn btn-primary">Сохранить показатели</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <form method="POST" id="sheetRowDeleteForm" class="d-none">
    @csrf
    @method('DELETE')
    <input type="hidden" name="scope" value="{{ $backQuery['scope'] ?? '' }}">
    <input type="hidden" name="owner_id" value="{{ $backQuery['owner_id'] ?? '' }}">
    <input type="hidden" name="workbook" value="{{ $workbook->id }}">
  </form>
</div>
@endsection

@push('scripts')
<script>
(() => {
  const searchInput = document.getElementById('sheetSearch');
  const statusFilter = document.getElementById('sheetStatusFilter');
  const table = document.getElementById('sheetTable');
  const visibleCounter = document.getElementById('sheetVisibleRows');
  const compactToggle = document.getElementById('sheetCompactToggle');
  const wrapToggle = document.getElementById('sheetWrapToggle');
  const rows = Array.from(document.querySelectorAll('#sheetTable tbody tr[data-row-text]'));
  const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || @json(csrf_token());
  const scopeValue = @json($backQuery['scope'] ?? '');
  const ownerIdValue = @json($backQuery['owner_id'] ?? '');
  const workbookValue = @json($workbook->id);
  const rowStoreRoute = @json(route('nonclosures.sheets.rows.store', $sheet));
  const rowUpdateRouteTemplate = @json(route('nonclosures.sheets.rows.update', ['sheet' => $sheet->id, 'rowIndex' => '__ROW__']));
  const rowDestroyRouteTemplate = @json(route('nonclosures.sheets.rows.destroy', ['sheet' => $sheet->id, 'rowIndex' => '__ROW__']));
  const rowStates = @json($rowStatePayload);
  const rowActivities = @json($rowActivityPayload);
  const rowTaskStats = @json($sheetRowTaskStats);
  const rowValues = @json($rowValuePayload);
  const columnDefinitions = @json($columnDefinitionsPayload);
  const metricDefinitions = @json($metricDefinitionsPayload);
  const metricOperatorOptions = @json($sheetMetricOperatorOptions);
  const metricToneOptions = @json($sheetOptionToneOptions);
  const headers = columnDefinitions.map((column) => column.label);
  const rowModalElement = document.getElementById('sheetRowModal');
  const rowForm = document.getElementById('sheetRowForm');
  const rowMethodInput = document.getElementById('sheetRowMethod');
  const rowPositionInput = document.getElementById('sheetRowPosition');
  const rowModalTitle = document.getElementById('sheetRowModalTitle');
  const rowLabel = document.getElementById('sheetRowLabel');
  const rowSummary = document.getElementById('sheetRowSummary');
  const rowStatusSelect = document.getElementById('sheetRowStatus');
  const rowAssignedUser = document.getElementById('sheetRowAssignedUser');
  const rowComment = document.getElementById('sheetRowComment');
  const rowUpdatedAt = document.getElementById('sheetRowUpdatedAt');
  const rowHistory = document.getElementById('sheetRowHistory');
  const rowTaskStatsBox = document.getElementById('sheetRowTaskStats');
  const rowValuesEditor = document.getElementById('sheetRowValuesEditor');
  const deleteRowButton = document.getElementById('sheetRowDeleteButton');
  const deleteRowForm = document.getElementById('sheetRowDeleteForm');
  const taskModalElement = document.getElementById('sheetTaskModal');
  const taskRowIndexInput = document.getElementById('sheetTaskRowIndex');
  const taskRowLabel = document.getElementById('sheetTaskRowLabel');
  const taskRowSummary = document.getElementById('sheetTaskRowSummary');
  const taskTitleInput = document.getElementById('sheetTaskTitle');
  const taskDescriptionInput = document.getElementById('sheetTaskDescription');
  const addRowButton = document.getElementById('sheetAddRowButton');
  const statusQuickButtons = Array.from(document.querySelectorAll('[data-status-quick]'));
  const rowButtons = Array.from(document.querySelectorAll('[data-row-work]'));
  const taskButtons = Array.from(document.querySelectorAll('[data-row-task]'));
  const insertButtons = Array.from(document.querySelectorAll('[data-row-insert]'));
  const cellButtons = Array.from(document.querySelectorAll('[data-row-cell]'));
  const inlineSelects = Array.from(document.querySelectorAll('[data-inline-select]'));
  const columnTypeSelects = Array.from(document.querySelectorAll('[data-column-type-select]'));
  const metricsEditor = document.getElementById('sheetMetricsEditor');
  const metricsEmpty = document.getElementById('sheetMetricsEmpty');
  const metricAddButton = document.getElementById('sheetMetricAddButton');
  if (!searchInput || !table || !visibleCounter) return;

  const rowModal = rowModalElement ? new bootstrap.Modal(rowModalElement) : null;
  const taskModal = taskModalElement ? new bootstrap.Modal(taskModalElement) : null;
  let pendingFocusColumnIndex = null;
  const metricState = Array.isArray(metricDefinitions) ? metricDefinitions.map((metric) => ({ ...metric })) : [];
  const modeStorageKey = 'documents-sheet-view-modes';
  const restoreModes = () => {
    try {
      const payload = JSON.parse(window.localStorage.getItem(modeStorageKey) || '{}');
      compactToggle.checked = Boolean(payload.compact);
      wrapToggle.checked = payload.wrap !== false;
    } catch (error) {
      compactToggle.checked = false;
      wrapToggle.checked = true;
    }
  };
  const persistModes = () => window.localStorage.setItem(modeStorageKey, JSON.stringify({ compact: Boolean(compactToggle.checked), wrap: Boolean(wrapToggle.checked) }));
  const applyModes = () => {
    table.classList.toggle('sheet-compact', Boolean(compactToggle.checked));
    table.classList.toggle('sheet-nowrap', !Boolean(wrapToggle.checked));
    persistModes();
  };
  const applyFilter = () => {
    const needle = searchInput.value.trim().toLowerCase();
    const statusNeedle = statusFilter?.value || '';
    let visible = 0;
    rows.forEach((row) => {
      const haystack = row.dataset.rowText || '';
      const rowStatus = row.dataset.rowStatus || 'none';
      const match = (!needle || haystack.includes(needle)) && (!statusNeedle || rowStatus === statusNeedle);
      row.classList.toggle('d-none', !match);
      if (match) visible += 1;
    });
    visibleCounter.textContent = String(visible);
  };
  const renderTaskStats = (rowIndex) => {
    if (!rowTaskStatsBox) return;
    const stats = rowTaskStats[String(rowIndex)] || rowTaskStats[rowIndex] || null;
    const pills = [];
    if (stats) {
      pills.push(`<span class="sheet-pill">Всего задач: ${stats.total ?? 0}</span>`);
      pills.push(`<span class="sheet-pill">Открыто: ${stats.open ?? 0}</span>`);
      if ((stats.overdue ?? 0) > 0) pills.push(`<span class="sheet-pill">Просрочено: ${stats.overdue}</span>`);
      if (stats.last_due_at) pills.push(`<span class="sheet-pill">Последний срок: ${stats.last_due_at}</span>`);
      if (stats.last_title) pills.push(`<span class="sheet-pill">${stats.last_title}</span>`);
    }
    rowTaskStatsBox.innerHTML = pills.length ? pills.join('') : '<span class="sheet-pill">По строке задач пока нет</span>';
  };
  const renderHistory = (rowIndex) => {
    if (!rowHistory) return;
    const items = rowActivities[String(rowIndex)] || rowActivities[rowIndex] || [];
    if (!items.length) {
      rowHistory.innerHTML = '<div class="sheet-history-item"><div class="sheet-muted">История по строке пока пустая.</div></div>';
      return;
    }
    rowHistory.innerHTML = items.map((item) => {
      const actor = item.actor ? `<span>${item.actor}</span>` : '<span>Система</span>';
      const createdAt = item.created_at ? `<span>${item.created_at}</span>` : '';
      return `<div class="sheet-history-item"><div class="sheet-history-meta small sheet-muted mb-2">${actor}${createdAt}</div><div>${item.body || 'Изменение по строке'}</div></div>`;
    }).join('');
  };
  const syncQuickStatusButtons = () => {
    const current = rowStatusSelect?.value || '';
    statusQuickButtons.forEach((button) => {
      button.classList.toggle('btn-primary', button.dataset.statusQuick === current);
      button.classList.toggle('btn-outline-secondary', button.dataset.statusQuick !== current);
    });
  };
  const escapeHtml = (value) => String(value ?? '').replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  const shouldUseTextareaField = (column) => {
    const label = String(column?.label || '').toLowerCase();
    return ['коммент', 'адрес', 'примеч', 'опис', 'услов', 'источник'].some((marker) => label.includes(marker));
  };
  const renderColumnEditorMode = (select) => {
    const container = select?.closest('form, .sheet-column-form, .sheet-column-row') || select?.parentElement;
    if (!container) return;
    const optionsBlock = container.querySelector('[data-column-options-block]');
    if (!optionsBlock) return;
    optionsBlock.classList.toggle('d-none', select.value !== 'select');
  };
  const renderRowValueField = (column, value, index) => {
    const safeLabel = escapeHtml(column?.label || `Колонка ${index + 1}`);
    const safeValue = String(value ?? '');
    const type = column?.type || 'text';

    if (type === 'select') {
      const options = Array.isArray(column?.options) ? column.options : [];
      const optionMarkup = ['<option value=""></option>'].concat(options.map((option) => {
        const optionValue = String(option?.value ?? '');
        const optionLabel = String(option?.label ?? optionValue);
        const selected = optionValue === safeValue ? ' selected' : '';

        return `<option value="${escapeHtml(optionValue)}"${selected}>${escapeHtml(optionLabel)}</option>`;
      })).join('');

      return `<label class="sheet-editor-field"><span class="form-label mb-0">${safeLabel}</span><select class="form-select form-select-sm" name="row_values[${index}]">${optionMarkup}</select></label>`;
    }

    if (type === 'date') {
      return `<label class="sheet-editor-field"><span class="form-label mb-0">${safeLabel}</span><input type="date" class="form-control form-control-sm" name="row_values[${index}]" value="${escapeHtml(safeValue)}"></label>`;
    }

    if (type === 'number') {
      return `<label class="sheet-editor-field"><span class="form-label mb-0">${safeLabel}</span><input type="number" step="any" class="form-control form-control-sm" name="row_values[${index}]" value="${escapeHtml(safeValue)}"></label>`;
    }

    if (shouldUseTextareaField(column)) {
      return `<label class="sheet-editor-field"><span class="form-label mb-0">${safeLabel}</span><textarea class="form-control form-control-sm" rows="2" name="row_values[${index}]">${escapeHtml(safeValue)}</textarea></label>`;
    }

    return `<label class="sheet-editor-field"><span class="form-label mb-0">${safeLabel}</span><input type="text" class="form-control form-control-sm" name="row_values[${index}]" value="${escapeHtml(safeValue)}"></label>`;
  };
  const renderRowValueInputs = (values) => {
    if (!rowValuesEditor) return;
    rowValuesEditor.innerHTML = columnDefinitions.map((column, index) => {
      const value = Array.isArray(values) ? (values[index] ?? '') : '';
      return renderRowValueField(column, value, index);
    }).join('');
  };
  const focusRowValueField = (columnIndex) => {
    if (!rowValuesEditor || columnIndex === null || Number.isNaN(Number(columnIndex))) return;
    const fields = Array.from(rowValuesEditor.querySelectorAll('[name^="row_values["]'));
    const target = fields[Number(columnIndex)] || null;
    if (!target) return;
    target.focus({ preventScroll: false });
    target.select?.();
    target.scrollIntoView({ block: 'center', behavior: 'smooth' });
  };
  const buildRowSummary = (values) => {
    const cells = Array.isArray(values) ? values : [];
    const summary = cells.map((value) => String(value || '').trim()).filter(Boolean).slice(0, 5).join(' | ');
    return summary !== '' ? summary : 'В строке пока нет значений.';
  };
  const buildRowTaskTitle = (values, rowIndex) => {
    const subject = String(values?.[1] ?? values?.[0] ?? '').trim();
    return `Связаться: ${subject !== '' ? subject : `строка ${rowIndex}`}`;
  };
  const refreshRowDatasets = (rowIndex) => {
    const row = document.getElementById(`row-${rowIndex}`);
    if (!row) return;

    const values = rowValues[rowIndex - 1] || [];
    const state = rowStates[String(rowIndex)] || rowStates[rowIndex] || null;
    const summary = buildRowSummary(values);
    const subject = String(values?.[1] ?? values?.[0] ?? '').trim();
    row.dataset.rowText = [summary, state?.comment || '', state?.assigned_user_name || '', state?.status_label || '']
      .join(' ')
      .trim()
      .toLowerCase();

    const taskButton = row.querySelector('[data-row-task]');
    if (taskButton) {
      taskButton.setAttribute('data-row-summary', summary);
      taskButton.setAttribute('data-row-title', buildRowTaskTitle(values, rowIndex));
    }

    const workButton = row.querySelector('[data-row-work]');
    if (workButton) {
      workButton.setAttribute('data-row-summary', summary);
      workButton.setAttribute('data-row-title', subject);
    }
  };
  const buildRowUpdatePayload = (rowIndex) => {
    const params = new URLSearchParams();
    params.set('_token', csrfToken);
    params.set('_method', 'PATCH');
    if (scopeValue !== '') params.set('scope', scopeValue);
    if (String(ownerIdValue) !== '') params.set('owner_id', String(ownerIdValue));
    params.set('workbook', String(workbookValue));
    (rowValues[rowIndex - 1] || []).forEach((value, index) => {
      params.append(`row_values[${index}]`, String(value ?? ''));
    });

    return params;
  };
  const persistInlineSelect = async (select) => {
    const rowIndex = Number(select.getAttribute('data-row-index') || 0);
    const columnIndex = Number(select.getAttribute('data-col-index') || 0);
    const cell = select.closest('.sheet-cell');
    if (!rowIndex || Number.isNaN(columnIndex)) return;

    const previousValue = String(rowValues[rowIndex - 1]?.[columnIndex] ?? '');
    const nextValue = String(select.value ?? '');
    if (previousValue === nextValue) return;

    rowValues[rowIndex - 1][columnIndex] = nextValue;
    select.classList.remove('is-error');
    select.classList.add('is-saving');

    try {
      const response = await fetch(rowUpdateRouteTemplate.replace('__ROW__', String(rowIndex)), {
        method: 'POST',
        headers: {
          'X-CSRF-TOKEN': csrfToken,
          'X-Requested-With': 'XMLHttpRequest',
          'Accept': 'text/html,application/json',
        },
        credentials: 'same-origin',
        body: buildRowUpdatePayload(rowIndex),
      });

      if (!response.ok) {
        throw new Error(`HTTP ${response.status}`);
      }

      cell?.classList.toggle('sheet-cell-empty', nextValue === '');
      refreshRowDatasets(rowIndex);
      applyFilter();
      select.classList.remove('is-saving');
    } catch (error) {
      rowValues[rowIndex - 1][columnIndex] = previousValue;
      select.value = previousValue;
      cell?.classList.toggle('sheet-cell-empty', previousValue === '');
      select.classList.remove('is-saving');
      select.classList.add('is-error');
      window.setTimeout(() => select.classList.remove('is-error'), 1800);
    }
  };
  const metricValueControl = (metric, index) => {
    const columnIndex = Number(metric?.column_index ?? 0);
    const column = columnDefinitions[columnIndex] || null;
    const operator = String(metric?.operator || 'equals');
    const currentValue = String(metric?.value ?? '');

    if (operator === 'filled' || operator === 'empty') {
      return `
        <input type="hidden" name="metrics[${index}][value]" value="">
        <input type="text" class="form-control form-control-sm" value="Значение не требуется" disabled>
      `;
    }

    if (column?.type === 'select' && (operator === 'equals' || operator === 'not_equals')) {
      const optionMarkup = ['<option value=""></option>']
        .concat((column.options || []).map((option) => {
          const optionValue = String(option?.value ?? '');
          const optionLabel = String(option?.label ?? optionValue);
          const selected = optionValue === currentValue ? ' selected' : '';
          return `<option value="${escapeHtml(optionValue)}"${selected}>${escapeHtml(optionLabel)}</option>`;
        }))
        .join('');

      return `<select class="form-select form-select-sm" name="metrics[${index}][value]" data-metric-value>${optionMarkup}</select>`;
    }

    if (column?.type === 'date' && (operator === 'date_older_than_days' || operator === 'date_newer_than_days')) {
      return `<input type="number" min="0" step="1" class="form-control form-control-sm" name="metrics[${index}][value]" value="${escapeHtml(currentValue)}" placeholder="14" data-metric-value>`;
    }

    if (column?.type === 'number' && (operator === 'number_gte' || operator === 'number_lte')) {
      return `<input type="number" step="any" class="form-control form-control-sm" name="metrics[${index}][value]" value="${escapeHtml(currentValue)}" data-metric-value>`;
    }

    return `<input type="text" class="form-control form-control-sm" name="metrics[${index}][value]" value="${escapeHtml(currentValue)}" data-metric-value placeholder="Значение">`;
  };
  const metricRowMarkup = (metric, index) => {
    const operatorOptions = Object.entries(metricOperatorOptions)
      .map(([value, label]) => `<option value="${escapeHtml(value)}"${String(metric?.operator || 'equals') === value ? ' selected' : ''}>${escapeHtml(label)}</option>`)
      .join('');
    const toneOptions = Object.entries(metricToneOptions)
      .map(([value, label]) => `<option value="${escapeHtml(value)}"${String(metric?.tone || 'blue') === value ? ' selected' : ''}>${escapeHtml(label)}</option>`)
      .join('');
    const columnOptions = columnDefinitions
      .map((column, columnIndex) => `<option value="${columnIndex}"${Number(metric?.column_index ?? 0) === columnIndex ? ' selected' : ''}>${escapeHtml(column.label)}</option>`)
      .join('');

    return `
      <div class="sheet-metric-row" data-metric-row data-metric-index="${index}">
        <div>
          <label class="form-label">Название карточки</label>
          <input type="text" class="form-control form-control-sm" name="metrics[${index}][label]" value="${escapeHtml(metric?.label || '')}" placeholder="Например: Работают с нами" data-metric-label>
        </div>
        <div>
          <label class="form-label">Колонка</label>
          <select class="form-select form-select-sm" name="metrics[${index}][column_index]" data-metric-column>${columnOptions}</select>
        </div>
        <div>
          <label class="form-label">Условие</label>
          <select class="form-select form-select-sm" name="metrics[${index}][operator]" data-metric-operator>${operatorOptions}</select>
        </div>
        <div>
          <label class="form-label">Значение / порог</label>
          ${metricValueControl(metric, index)}
        </div>
        <div class="d-flex gap-2 align-items-end">
          <div class="flex-grow-1">
            <label class="form-label">Цвет</label>
            <select class="form-select form-select-sm" name="metrics[${index}][tone]" data-metric-tone>${toneOptions}</select>
          </div>
          <button type="button" class="btn btn-outline-danger btn-sm" data-metric-remove>&times;</button>
        </div>
      </div>
    `;
  };
  const readMetricRowsFromDom = () => {
    if (!metricsEditor) return [];
    return Array.from(metricsEditor.querySelectorAll('[data-metric-row]')).map((row) => ({
      label: row.querySelector('[data-metric-label]')?.value || '',
      column_index: Number(row.querySelector('[data-metric-column]')?.value || 0),
      operator: row.querySelector('[data-metric-operator]')?.value || 'equals',
      value: row.querySelector('[data-metric-value]')?.value || '',
      tone: row.querySelector('[data-metric-tone]')?.value || 'blue',
    }));
  };
  const renderMetricsEditor = () => {
    if (!metricsEditor || !metricsEmpty) return;
    metricsEditor.innerHTML = metricState.map((metric, index) => metricRowMarkup(metric, index)).join('');
    metricsEmpty.classList.toggle('d-none', metricState.length > 0);
  };
  const syncMetricStateFromDom = () => {
    metricState.splice(0, metricState.length, ...readMetricRowsFromDom());
  };
  const openRowEditor = (rowIndex, focusColumnIndex = null) => {
    if (!rowModal || !rowForm || !rowMethodInput || !rowPositionInput) return;
    const numericRowIndex = Number(rowIndex);
    const state = rowStates[String(numericRowIndex)] || rowStates[numericRowIndex] || null;
    const values = rowValues[numericRowIndex - 1] || [];
    rowForm.action = rowUpdateRouteTemplate.replace('__ROW__', String(numericRowIndex));
    rowMethodInput.disabled = false;
    rowMethodInput.value = 'PATCH';
    rowPositionInput.value = String(numericRowIndex);
    rowModalTitle.textContent = 'Редактировать строку';
    rowLabel.textContent = `Строка ${numericRowIndex}`;
    rowSummary.textContent = buildRowSummary(values);
    rowStatusSelect.value = state?.status || 'new';
    rowAssignedUser.value = state?.assigned_user_id ? String(state.assigned_user_id) : '0';
    rowComment.value = state?.comment || '';
    rowUpdatedAt.value = state?.updated_at || '—';
    renderTaskStats(numericRowIndex);
    renderHistory(numericRowIndex);
    renderRowValueInputs(values);
    deleteRowButton.classList.remove('d-none');
    deleteRowButton.onclick = () => {
      deleteRowForm.action = rowDestroyRouteTemplate.replace('__ROW__', String(numericRowIndex));
      deleteRowForm.submit();
    };
    syncQuickStatusButtons();
    pendingFocusColumnIndex = focusColumnIndex;
    rowModal.show();
  };
  const openNewRowEditor = (position, focusColumnIndex = 0) => {
    if (!rowModal || !rowForm || !rowMethodInput || !rowPositionInput) return;
    const numericPosition = Number(position || (rows.length + 1));
    const values = headers.map(() => '');
    rowForm.action = rowStoreRoute;
    rowMethodInput.disabled = true;
    rowPositionInput.value = String(numericPosition);
    rowModalTitle.textContent = 'Новая строка';
    rowLabel.textContent = numericPosition > rows.length ? 'Новая строка в конце таблицы' : `Новая строка перед позицией ${numericPosition}`;
    rowSummary.textContent = 'Заполните нужные ячейки и при необходимости сразу задайте статус.';
    rowStatusSelect.value = 'new';
    rowAssignedUser.value = '0';
    rowComment.value = '';
    rowUpdatedAt.value = '—';
    rowTaskStatsBox.innerHTML = '<span class="sheet-pill">Для новой строки задачи ещё не созданы</span>';
    rowHistory.innerHTML = '<div class="sheet-history-item"><div class="sheet-muted">История появится после первого сохранения.</div></div>';
    renderRowValueInputs(values);
    deleteRowButton.classList.add('d-none');
    deleteRowButton.onclick = null;
    syncQuickStatusButtons();
    pendingFocusColumnIndex = focusColumnIndex;
    rowModal.show();
  };
  const openTaskEditor = (rowIndex, summary, title) => {
    if (!taskModal || !taskRowIndexInput || !taskRowLabel || !taskRowSummary || !taskTitleInput || !taskDescriptionInput) return;
    taskRowIndexInput.value = String(rowIndex);
    taskRowLabel.textContent = `Строка ${rowIndex}`;
    taskRowSummary.textContent = summary || 'Контекст строки будет добавлен автоматически.';
    taskTitleInput.value = title || `Напоминание по строке ${rowIndex}`;
    taskDescriptionInput.value = summary ? `Проверить строку таблицы.\nКонтекст: ${summary}` : 'Проверить строку таблицы.';
    taskModal.show();
  };
  searchInput.addEventListener('input', applyFilter);
  statusFilter?.addEventListener('change', applyFilter);
  compactToggle?.addEventListener('change', applyModes);
  wrapToggle?.addEventListener('change', applyModes);
  rowStatusSelect?.addEventListener('change', syncQuickStatusButtons);
  addRowButton?.addEventListener('click', () => openNewRowEditor(rows.length + 1));
  rowButtons.forEach((button) => button.addEventListener('click', () => openRowEditor(button.getAttribute('data-row-index') || '')));
  insertButtons.forEach((button) => button.addEventListener('click', () => openNewRowEditor(Number(button.getAttribute('data-row-index') || rows.length) + 1)));
  taskButtons.forEach((button) => button.addEventListener('click', () => openTaskEditor(button.getAttribute('data-row-index') || '', button.getAttribute('data-row-summary') || '', button.getAttribute('data-row-title') || '')));
  cellButtons.forEach((cell) => {
    const rowIndex = cell.getAttribute('data-row-index') || '';
    const colIndex = Number(cell.getAttribute('data-col-index') || 0);
    if (cell.querySelector('[data-inline-select]')) return;
    cell.addEventListener('dblclick', () => openRowEditor(rowIndex, colIndex));
    cell.addEventListener('keydown', (event) => {
      if (event.target?.closest('[data-inline-select]')) return;
      if (event.key !== 'Enter' && event.key !== ' ') return;
      event.preventDefault();
      openRowEditor(rowIndex, colIndex);
    });
  });
  inlineSelects.forEach((select) => {
    select.addEventListener('change', () => persistInlineSelect(select));
    select.addEventListener('click', (event) => event.stopPropagation());
    select.addEventListener('dblclick', (event) => event.stopPropagation());
  });
  statusQuickButtons.forEach((button) => button.addEventListener('click', () => {
    rowStatusSelect.value = button.dataset.statusQuick || 'new';
    syncQuickStatusButtons();
  }));
  rowModalElement?.addEventListener('shown.bs.modal', () => {
    if (pendingFocusColumnIndex !== null) {
      focusRowValueField(pendingFocusColumnIndex);
      pendingFocusColumnIndex = null;
      return;
    }

    rowComment?.focus();
  });
  columnTypeSelects.forEach((select) => {
    renderColumnEditorMode(select);
    select.addEventListener('change', () => renderColumnEditorMode(select));
  });
  metricAddButton?.addEventListener('click', () => {
    syncMetricStateFromDom();
    metricState.push({
      label: '',
      column_index: 0,
      operator: 'equals',
      value: '',
      tone: 'blue',
    });
    renderMetricsEditor();
  });
  metricsEditor?.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-metric-remove]');
    if (!removeButton) return;
    syncMetricStateFromDom();
    const row = removeButton.closest('[data-metric-row]');
    const index = Number(row?.getAttribute('data-metric-index') || -1);
    if (index < 0) return;
    metricState.splice(index, 1);
    renderMetricsEditor();
  });
  metricsEditor?.addEventListener('change', (event) => {
    const target = event.target;
    if (!target.closest('[data-metric-row]')) return;
    syncMetricStateFromDom();
    renderMetricsEditor();
  });
  restoreModes();
  applyModes();
  renderMetricsEditor();
  rows.forEach((row) => {
    const rowIndex = Number((row.id || '').replace('row-', ''));
    if (!Number.isNaN(rowIndex) && rowIndex > 0) {
      refreshRowDatasets(rowIndex);
    }
  });
  applyFilter();
})();
</script>
@endpush
