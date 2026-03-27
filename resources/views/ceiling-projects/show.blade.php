@extends('layouts.app')

@push('styles')
<style>
  .project-hero { border: 1px solid rgba(15,23,42,.08); border-radius: 1.25rem; background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(241,245,249,.92)); padding: 1.4rem 1.5rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
  .metric-card { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: rgba(255,255,255,.92); padding: 1rem; height: 100%; }
  .metric-label { font-size: .76rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
  .metric-value { font-size: 1.3rem; font-weight: 700; color: #0f172a; margin-top: .2rem; }
  .room-card { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: linear-gradient(180deg, rgba(255,255,255,.96), rgba(248,250,252,.92)); padding: 1rem; }
  .geometry-stage { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; overflow: hidden; background: linear-gradient(180deg, rgba(248,250,252,.98), rgba(241,245,249,.92)); }
  .geometry-toolbar { padding: .75rem 1rem; border-bottom: 1px solid rgba(15,23,42,.08); background: rgba(255,255,255,.75); }
  .geometry-svg { width: 100%; height: 520px; display: block; touch-action: none; }
  .geometry-toolbar-meta { gap: .5rem; }
  .geometry-toolbar-zoom { display: inline-flex; align-items: center; gap: .35rem; }
  .geometry-toolbar-tip { border-top: 1px solid rgba(15,23,42,.06); background: rgba(248,250,252,.78); }
  .geometry-stage.is-pan-ready .geometry-svg { cursor: grab; }
  .geometry-stage.is-panning .geometry-svg { cursor: grabbing; }
  .point-row { display: grid; grid-template-columns: minmax(0, 1.2fr) 1fr 1fr auto; gap: .5rem; align-items: center; border: 1px solid transparent; border-radius: .85rem; padding: .35rem; transition: background .15s ease, border-color .15s ease, box-shadow .15s ease; }
  .point-row:hover { background: rgba(248,250,252,.9); border-color: rgba(148,163,184,.28); }
  .point-row.is-selected { background: rgba(219,234,254,.52); border-color: rgba(37,99,235,.28); box-shadow: inset 0 0 0 1px rgba(37,99,235,.12); }
  .point-row-meta { display: flex; align-items: center; gap: .5rem; min-width: 0; }
  .point-row-letter { width: 1.55rem; height: 1.55rem; border-radius: 999px; background: #0f172a; color: #fff; font-size: .8rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex: 0 0 auto; }
  .point-row-title { font-size: .84rem; font-weight: 700; color: #0f172a; }
  .point-row-subtitle { font-size: .72rem; color: #64748b; }
  .inspector-quick-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
  .inspector-card { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: rgba(248,250,252,.94); padding: .85rem; }
  .inspector-kicker { font-size: .72rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; margin-bottom: .3rem; }
  .inspector-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
  .inspector-tabs { display: flex; gap: .35rem; flex-wrap: wrap; }
  .inspector-tab.is-active { background: #0f172a; color: #fff; border-color: #0f172a; }
  .inspector-panel { display: none; }
  .inspector-panel.is-active { display: block; }
  .inspector-stack { display: grid; gap: .65rem; }
  .segment-row { display: grid; grid-template-columns: auto 1fr auto; gap: .5rem; align-items: center; border: 1px solid transparent; border-radius: .85rem; padding: .45rem; transition: background .15s ease, border-color .15s ease, box-shadow .15s ease; }
  .segment-row:hover { background: rgba(248,250,252,.9); border-color: rgba(148,163,184,.28); }
  .segment-row.is-selected { background: rgba(254,226,226,.65); border-color: rgba(220,38,38,.3); box-shadow: inset 0 0 0 1px rgba(220,38,38,.12); }
  .segment-row-label { font-size: .82rem; font-weight: 700; color: #0f172a; min-width: 2.7rem; }
  .angle-row { display: flex; justify-content: space-between; gap: .75rem; align-items: center; border: 1px solid rgba(15,23,42,.08); border-radius: .85rem; padding: .45rem .6rem; background: rgba(248,250,252,.9); }
  .angle-row.is-selected { border-color: rgba(37,99,235,.28); background: rgba(219,234,254,.52); }
  .angle-row-label { font-size: .82rem; font-weight: 700; color: #0f172a; }
  .tool-toggle.is-active { background: #0f172a; color: #fff; border-color: #0f172a; }
  .element-chip { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid rgba(15,23,42,.08); border-radius: 999px; padding: .3rem .6rem; background: rgba(248,250,252,.95); font-size: .85rem; }
  .element-chip-dot { width: .6rem; height: .6rem; border-radius: 999px; display: inline-block; }
  .guide-card { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96)); padding: 1rem; height: 100%; }
  .guide-step { display: grid; grid-template-columns: 1.6rem 1fr; gap: .65rem; align-items: start; }
  .guide-step-index { width: 1.6rem; height: 1.6rem; border-radius: 999px; background: #0f172a; color: #fff; font-size: .82rem; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
  .workflow-preview { width: 100%; max-height: 260px; object-fit: contain; border-radius: 1rem; border: 1px solid rgba(15,23,42,.08); background: linear-gradient(180deg, rgba(248,250,252,.98), rgba(241,245,249,.92)); }
  .workflow-note { font-size: .92rem; color: #475569; line-height: 1.45; }
  .workflow-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
  .geometry-underlay-controls { display: inline-flex; align-items: center; gap: .5rem; }
  .geometry-underlay-range { width: 110px; }
  .project-page.is-drafting .project-metrics-row,
  .project-page.is-drafting .project-open-card,
  .project-page.is-drafting .project-sidebar { display: none; }
  .project-page.is-drafting .project-main { flex: 0 0 100%; max-width: 100%; }
  .project-main > .card { margin-bottom: 0 !important; }
  .project-page.is-drafting .project-main { gap: 1rem; }
  .project-page.is-drafting .project-main .project-geometry-card { order: 1; }
  .project-page.is-drafting .project-main .project-room-list-card { order: 2; }
  .project-page.is-drafting .project-main .project-room-create-card { order: 3; }
  .project-page.is-drafting .project-main .project-elements-card { order: 4; }
  .project-page.is-drafting .project-drawing-guide { display: none; }
  .project-page.is-drafting .geometry-stage { box-shadow: 0 24px 48px rgba(15,23,42,.12); }
  .project-page.is-drafting .geometry-toolbar { position: sticky; top: 0; z-index: 2; backdrop-filter: blur(10px); }
  .project-page.is-drafting .geometry-svg { height: calc(100vh - 255px); min-height: 760px; }
  .project-page.is-drafting .project-canvas-col { flex: 0 0 78%; max-width: 78%; }
  .project-page.is-drafting .project-points-col { flex: 0 0 22%; max-width: 22%; }
  .project-page.is-drafting .points-panel { position: sticky; top: 1rem; }
  .project-room-switch { min-width: min(100%, 440px); }
  .drafting-badge { display: inline-flex; align-items: center; gap: .45rem; padding: .45rem .7rem; border-radius: 999px; background: rgba(15,23,42,.08); color: #0f172a; font-size: .9rem; font-weight: 600; }
  @media (max-width: 1399.98px) {
    .project-page.is-drafting .project-canvas-col,
    .project-page.is-drafting .project-points-col { flex: 0 0 100%; max-width: 100%; }
    .project-page.is-drafting .geometry-svg { height: calc(100vh - 310px); min-height: 680px; }
    .project-page.is-drafting .points-panel { position: static; }
  }
  @media (max-width: 991.98px) {
    .project-page.is-drafting .geometry-svg { height: 70vh; min-height: 520px; }
  }
</style>
@endpush

@php
  $projectTitle = trim((string) ($project->title ?? '')) ?: ('Проектировка #'.$project->id);
  $formatDecimal = function ($value, $suffix = '') {
      $number = number_format((float) $value, 2, ',', ' ');
      $number = preg_replace('/,00$/', '', $number);
      return trim($number.' '.$suffix);
  };
  $formatCentimeters = function ($meters, $suffix = 'см') {
      $number = number_format((float) $meters * 100, 0, ',', ' ');
      return trim($number.' '.$suffix);
  };
  $formatMoney = fn ($value) => $formatDecimal($value, 'руб.');
  $defaultRates = \App\Models\CeilingProject::defaultEstimateRates();
  $measurementLabel = function ($measurement) {
      $date = $measurement->scheduled_at?->format('d.m.Y H:i') ?? 'Без даты';
      return trim($date.' '.trim((string) ($measurement->phone ?? '')).' '.trim((string) ($measurement->address ?? '')));
  };
  $priceFields = [
      ['canvas_price_per_m2', 'Полотно, руб/м2'],
      ['mounting_price_per_m2', 'Монтаж, руб/м2'],
      ['profile_price_per_m', 'Профиль, руб/м'],
      ['insert_price_per_m', 'Вставка, руб/м'],
      ['spotlight_price', 'Спот, руб/шт'],
      ['chandelier_price', 'Люстра, руб/шт'],
      ['pipe_price', 'Труба, руб/шт'],
      ['curtain_niche_price', 'Ниша, руб/шт'],
      ['cornice_price_per_m', 'Карниз, руб/м'],
      ['ventilation_hole_price', 'Вентиляция, руб/шт'],
      ['additional_cost', 'Доп. работы, руб'],
  ];
  $estimateRows = [
      ['Полотно', $formatDecimal($summary['totals']['recommended_canvas_area_m2'], 'м2').' x '.$formatMoney($summary['rates']['canvas_price_per_m2']), $summary['estimate']['canvas_total']],
      ['Профиль', $formatDecimal($summary['totals']['recommended_profile_m'], 'м').' x '.$formatMoney($summary['rates']['profile_price_per_m']), $summary['estimate']['profile_total']],
      ['Вставка', $formatDecimal($summary['totals']['recommended_insert_m'], 'м').' x '.$formatMoney($summary['rates']['insert_price_per_m']), $summary['estimate']['insert_total']],
      ['Свет', $summary['totals']['lighting_points_total'].' шт', $summary['estimate']['spotlights_total'] + $summary['estimate']['chandeliers_total']],
      ['Ниши', $summary['totals']['curtain_niches_count'].' шт x '.$formatMoney($summary['rates']['curtain_niche_price']), $summary['estimate']['curtain_niches_total']],
      ['Карнизы', $formatDecimal($summary['totals']['cornice_length_m'], 'м').' x '.$formatMoney($summary['rates']['cornice_price_per_m']), $summary['estimate']['cornices_total']],
      ['Доп. элементы', 'Трубы / вентиляция', $summary['estimate']['pipes_total'] + $summary['estimate']['ventilation_total']],
      ['Монтаж', $formatDecimal($summary['totals']['area_m2'], 'м2').' x '.$formatMoney($summary['rates']['mounting_price_per_m2']), $summary['estimate']['mounting_total']],
      ['Доп. работы', 'Фиксированная сумма', $summary['estimate']['additional_cost']],
  ];
  $selectedRoomPoints = [];
  $selectedRoomElements = collect();
  $selectedRoomElementsPayload = [];
  $editorWidth = 8.0;
  $editorHeight = 6.0;
  if ($selectedRoom) {
      $selectedRoomElements = $selectedRoom->elements ?? collect();
      $selectedRoomElementsPayload = $selectedRoomElements
          ->map(fn ($element) => [
              'id' => $element->id,
              'type' => $element->type,
              'label' => $element->label,
              'quantity' => (int) ($element->quantity ?? 1),
              'placement_mode' => $element->placement_mode ?? 'free',
              'segment_index' => $element->segment_index !== null ? (int) $element->segment_index : null,
              'offset_m' => $element->offset_m !== null ? (float) $element->offset_m : null,
              'x_m' => $element->x_m !== null ? (float) $element->x_m : null,
              'y_m' => $element->y_m !== null ? (float) $element->y_m : null,
              'length_m' => $element->length_m !== null ? (float) $element->length_m : null,
              'notes' => $element->notes,
          ])
          ->values()
          ->all();
      $selectedRoomPoints = is_array($selectedRoom->shape_points) ? array_values($selectedRoom->shape_points) : [];
      if (count($selectedRoomPoints) < 3) {
          $roomWidth = max(1, (float) ($selectedRoom->width_m ?? 4));
          $roomLength = max(1, (float) ($selectedRoom->length_m ?? 3));
          $selectedRoomPoints = [
              ['x' => 0, 'y' => 0],
              ['x' => $roomWidth, 'y' => 0],
              ['x' => $roomWidth, 'y' => $roomLength],
              ['x' => 0, 'y' => $roomLength],
          ];
      }
      $maxX = collect($selectedRoomPoints)->max(fn ($point) => (float) ($point['x'] ?? 0)) ?: 0;
      $maxY = collect($selectedRoomPoints)->max(fn ($point) => (float) ($point['y'] ?? 0)) ?: 0;
      $elementMaxX = collect($selectedRoomElementsPayload)->max(fn ($element) => (float) ($element['x_m'] ?? 0)) ?: 0;
      $elementMaxY = collect($selectedRoomElementsPayload)->max(fn ($element) => (float) ($element['y_m'] ?? 0)) ?: 0;
      $editorWidth = max(6.0, ceil(max($maxX + 1, $elementMaxX + 1, (float) ($selectedRoom->width_m ?? 0) + 1)));
      $editorHeight = max(4.0, ceil(max($maxY + 1, $elementMaxY + 1, (float) ($selectedRoom->length_m ?? 0) + 1)));
  }
  $elementBadgeClass = function (string $type): string {
      return match ($type) {
          'spotlight' => 'text-bg-warning',
          'chandelier' => 'text-bg-info',
          'pipe' => 'text-bg-secondary',
          'curtain_niche' => 'text-bg-success',
          'ventilation' => 'text-bg-primary',
          'cornice' => 'text-bg-dark',
          default => 'text-bg-light',
      };
  };
  $elementColor = function (string $type): string {
      return match ($type) {
          'spotlight' => '#f59e0b',
          'chandelier' => '#06b6d4',
          'pipe' => '#6b7280',
          'curtain_niche' => '#16a34a',
          'ventilation' => '#2563eb',
          'cornice' => '#0f172a',
          default => '#9333ea',
      };
  };
  $viewMode = $viewMode ?? 'standard';
  $isDraftingMode = $viewMode === 'drafting';
  $activeRoomParams = ['project' => $project];
  if ($selectedRoom) {
      $activeRoomParams['room'] = $selectedRoom->id;
  }
  $standardProjectUrl = route('ceiling-projects.show', $activeRoomParams);
  $draftingProjectUrl = route('ceiling-projects.drafting', $activeRoomParams);
  $sketchRecognition = is_array($sketchRecognition ?? null) ? $sketchRecognition : null;
  $sketchMeasurements = $sketchRecognition['measurements'] ?? [];
  $sketchRoomDraft = $sketchRecognition['room_draft'] ?? null;
  $sketchWarnings = collect($sketchRecognition['warnings'] ?? [])->filter();
  $sketchImageUrl = $sketchImageUrl ?? null;
  $sketchImageSharedWithReference = (bool) ($sketchImageSharedWithReference ?? false);
  $sketchRecognizedAt = isset($sketchRecognition['recognized_at'])
      ? \Illuminate\Support\Carbon::parse($sketchRecognition['recognized_at'])->format('d.m.Y H:i')
      : null;
@endphp

@section('content')
<div class="project-page d-grid gap-3 {{ $isDraftingMode ? 'is-drafting' : '' }}">
  <div class="project-hero">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <div class="text-uppercase small text-muted mb-2">Проектировка</div>
        <h3 class="mb-2">{{ $projectTitle }}</h3>
        <div class="text-muted">
          Проект #{{ $project->id }}
          @if($project->deal && auth()->user()?->role !== 'constructor')
            · Сделка <a href="{{ route('deals.show', $project->deal) }}" class="text-decoration-none">#{{ $project->deal->id }} {{ $project->deal->title }}</a>
          @elseif($project->deal)
            &middot; #{{ $project->deal->id }} {{ $project->deal->title }}
          @else
            · Пока без сделки
          @endif
        </div>
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('ceiling-projects.index') }}" class="btn btn-outline-secondary">Все проекты</a>
        @if($selectedRoom && !$isDraftingMode)
          <a href="{{ $draftingProjectUrl }}" class="btn btn-dark">Большой чертеж</a>
        @elseif($isDraftingMode)
          <a href="{{ $standardProjectUrl }}#geometry-editor" class="btn btn-outline-dark">Обычный режим</a>
        @endif
        @if($project->deal && auth()->user()?->role !== 'constructor')
          <a href="{{ route('deals.show', $project->deal) }}" class="btn btn-outline-primary">Открыть сделку</a>
        @endif
      </div>
    </div>
    @if($project->rooms->count() > 0)
      <form method="GET" action="{{ $isDraftingMode ? route('ceiling-projects.drafting', $project) : route('ceiling-projects.show', $project) }}" class="mt-3 d-flex gap-2 flex-wrap align-items-center project-room-switch">
        <select name="room" class="form-select">
          @foreach($project->rooms as $roomOption)
            <option value="{{ $roomOption->id }}" @selected((int) $selectedRoom?->id === (int) $roomOption->id)>{{ $roomOption->name }}</option>
          @endforeach
        </select>
        <button class="btn btn-outline-secondary">{{ $isDraftingMode ? 'Открыть комнату' : 'Выбрать комнату' }}</button>
      </form>
    @endif
  </div>

  <div class="row g-3 project-metrics-row">
    <div class="col-md-3 col-sm-6"><div class="metric-card"><div class="metric-label">Полотно</div><div class="metric-value">{{ $formatDecimal($summary['totals']['recommended_canvas_area_m2'], 'м2') }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="metric-card"><div class="metric-label">Профиль</div><div class="metric-value">{{ $formatDecimal($summary['totals']['recommended_profile_m'], 'м') }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="metric-card"><div class="metric-label">Комнаты</div><div class="metric-value">{{ $summary['totals']['rooms_count'] }}</div></div></div>
    <div class="col-md-3 col-sm-6"><div class="metric-card"><div class="metric-label">Смета</div><div class="metric-value">{{ $formatMoney($summary['estimate']['grand_total']) }}</div></div></div>
  </div>

  <div class="card shadow-sm project-open-card">
    <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
      <div>
        <div class="fw-semibold mb-1">Как открыть чертеж</div>
        <div class="small text-muted">1. Загрузите эскиз в блок распознавания, чтобы собрать черновик комнаты. 2. При необходимости отдельно загрузите подложку для ручной обводки. 3. Откройте комнату в чертеже и правьте геометрию уже без смешивания OCR и фонового фото.</div>
      </div>
      @if($selectedRoom && $isDraftingMode)
        <a href="{{ $standardProjectUrl }}#geometry-editor" class="btn btn-outline-dark">Вернуться к карточке</a>
      @endif
      @if($selectedRoom && !$isDraftingMode)
        <a href="#geometry-editor" class="btn btn-primary">Открыть чертеж: {{ $selectedRoom->name }}</a>
      @endif
    </div>
  </div>

  <div class="row g-3 project-workspace">
    <div class="col-xl-4 project-sidebar">
      <div class="card shadow-sm mb-3 project-settings-card">
        <div class="card-header fw-semibold">Параметры проекта</div>
        <div class="card-body">
          <form method="POST" action="{{ route('ceiling-projects.update', $project) }}" class="row g-3">
            @csrf
            <input type="hidden" name="view_mode" value="{{ $viewMode }}">
            @if($selectedRoom)
              <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
            @endif
            @method('PATCH')
            <div class="col-12"><label class="form-label">Название</label><input type="text" name="title" class="form-control" value="{{ old('title', $project->title) }}"></div>
            <div class="col-md-6">
              <label class="form-label">Статус</label>
              <select name="status" class="form-select">
                @foreach($statusOptions as $value => $label)
                  <option value="{{ $value }}" @selected(old('status', $project->status) === $value)>{{ $label }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6">
              <label class="form-label">Сделка</label>
              <select name="deal_id" class="form-select">
                <option value="">Не привязана</option>
                @foreach($availableDeals as $dealOption)
                  <option value="{{ $dealOption->id }}" @selected((int) old('deal_id', $project->deal_id) === (int) $dealOption->id)>#{{ $dealOption->id }} {{ $dealOption->title }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-12">
              <label class="form-label">Замер</label>
              <select name="measurement_id" class="form-select">
                <option value="">Без привязки</option>
                @foreach($measurements as $measurement)
                  <option value="{{ $measurement->id }}" @selected((int) old('measurement_id', $project->measurement_id) === (int) $measurement->id)>{{ $measurementLabel($measurement) }}</option>
                @endforeach
              </select>
            </div>
            <div class="col-md-6"><label class="form-label">Материал</label><select name="canvas_material" class="form-select">@foreach($materialOptions as $value => $label)<option value="{{ $value }}" @selected(old('canvas_material', $project->canvas_material) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-6"><label class="form-label">Фактура</label><select name="canvas_texture" class="form-select"><option value="">Не указана</option>@foreach($textureOptions as $value => $label)<option value="{{ $value }}" @selected(old('canvas_texture', $project->canvas_texture) === $value)>{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-4"><label class="form-label">Запас, %</label><input type="number" step="0.01" min="0" name="waste_percent" class="form-control" value="{{ old('waste_percent', $project->waste_percent ?? 12) }}"></div>
            <div class="col-md-4"><label class="form-label">Добор, м</label><input type="number" step="0.01" min="0" name="extra_margin_m" class="form-control" value="{{ old('extra_margin_m', $project->extra_margin_m ?? 0) }}"></div>
            <div class="col-md-4"><label class="form-label">Скидка, %</label><input type="number" step="0.01" min="0" name="discount_percent" class="form-control" value="{{ old('discount_percent', $project->discount_percent ?? 0) }}"></div>
            @foreach($priceFields as [$field, $label])
              <div class="col-md-6">
                <label class="form-label">{{ $label }}</label>
                <input type="number" step="0.01" min="0" name="{{ $field }}" class="form-control" value="{{ old($field, $project->{$field} ?? $defaultRates[$field]) }}">
              </div>
            @endforeach
            <div class="col-12"><label class="form-label">Заметки</label><textarea name="notes" rows="3" class="form-control">{{ old('notes', $project->notes) }}</textarea></div>
            <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary">Сохранить проект</button></div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">1. Эскиз для распознавания</div>
        <div class="card-body">
          @if($sketchImageUrl)
            <img src="{{ $sketchImageUrl }}" alt="Эскиз для распознавания" class="workflow-preview mb-3">
          @endif
          <div class="workflow-note mb-3">
            Этот файл идет только в OCR: по нему строится черновик комнаты и проверяются размеры.
            На канвасе он не показывается, пока вы отдельно не загрузите подложку для ручной обводки.
          </div>

          @if($sketchImageSharedWithReference)
            <div class="alert alert-secondary py-2 small">
              Сейчас OCR использует старое общее фото проекта. Чтобы разделить распознавание и обводку, загрузите эскиз сюда отдельно.
            </div>
          @endif

          <form method="POST" action="{{ route('ceiling-projects.sketch-image.upload', $project) }}" enctype="multipart/form-data" class="d-flex gap-2 flex-column">
            @csrf
            <input type="hidden" name="view_mode" value="{{ $viewMode }}">
            @if($selectedRoom)
              <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
            @endif
            <input type="file" name="sketch_image" class="form-control" accept="image/*" required>
            <div class="workflow-actions">
              <button class="btn btn-dark">Загрузить и распознать</button>
            </div>
          </form>

          @error('sketch_image')
            <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>
          @enderror
          @error('sketch_recognition')
            <div class="alert alert-warning mt-3 mb-0">{{ $message }}</div>
          @enderror

          @if($sketchImageUrl)
            <div class="workflow-actions mt-3">
              <form method="POST" action="{{ route('ceiling-projects.sketch-recognition', $project) }}">
                @csrf
                <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                @if($selectedRoom)
                  <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
                @endif
                <button class="btn btn-outline-secondary">Распознать повторно</button>
              </form>

              @if(is_array($sketchRoomDraft))
                <form method="POST" action="{{ route('ceiling-projects.sketch-recognition.apply', $project) }}">
                  @csrf
                  <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                  @if($selectedRoom)
                    <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
                  @endif
                  <button class="btn btn-outline-success">Применить как черновик комнаты</button>
                </form>
              @endif
            </div>
          @endif

          @if($sketchRecognition)
            <div class="border rounded p-3 mt-3 bg-light-subtle">
              <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
                <div>
                  <div class="fw-semibold">Последнее распознавание эскиза</div>
                  <div class="small text-muted">
                    @if($sketchRecognizedAt)
                      {{ $sketchRecognizedAt }}
                    @endif
                    @if(isset($sketchRecognition['confidence']))
                      · confidence: {{ number_format((float) $sketchRecognition['confidence'], 2, ',', ' ') }}
                    @endif
                  </div>
                </div>
                @if(($sketchRecognition['success'] ?? true) === false)
                  <span class="badge text-bg-danger">OCR error</span>
                @elseif(($sketchRecognition['shape']['type'] ?? null) === 'rectangle')
                  <span class="badge text-bg-success">Прямоугольник</span>
                @else
                  <span class="badge text-bg-secondary">Черновик</span>
                @endif
              </div>

              @if(($sketchRecognition['success'] ?? true) === false)
                <div class="alert alert-warning mt-3 mb-0">
                  <div class="fw-semibold mb-1">OCR не выполнился</div>
                  <div>{{ $sketchRecognition['message'] ?? 'Не удалось распознать эскиз.' }}</div>
                </div>
              @endif

              <div class="row g-2 small mt-1">
                @if(!empty($sketchMeasurements['width_cm']))
                  <div class="col-md-3"><b>Ширина:</b> {{ number_format((float) $sketchMeasurements['width_cm'], 0, ',', ' ') }} см</div>
                @endif
                @if(!empty($sketchMeasurements['length_cm']))
                  <div class="col-md-3"><b>Длина:</b> {{ number_format((float) $sketchMeasurements['length_cm'], 0, ',', ' ') }} см</div>
                @endif
                @if(!empty($sketchMeasurements['area_m2']))
                  <div class="col-md-3"><b>Площадь OCR:</b> {{ $formatDecimal($sketchMeasurements['area_m2'], 'м2') }}</div>
                @endif
                @if(!empty($sketchMeasurements['perimeter_m']))
                  <div class="col-md-3"><b>Периметр OCR:</b> {{ $formatDecimal($sketchMeasurements['perimeter_m'], 'м') }}</div>
                @endif
              </div>

              @if($sketchWarnings->isNotEmpty())
                <div class="mt-3 small text-warning-emphasis">
                  @foreach($sketchWarnings as $warning)
                    <div>• {{ $warning }}</div>
                  @endforeach
                </div>
              @endif

              @if(!empty($sketchRecognition['text']))
                <div class="mt-3">
                  <div class="small fw-semibold mb-1">OCR текст</div>
                  <pre class="small mb-0 p-2 border rounded bg-white" style="white-space: pre-wrap;">{{ $sketchRecognition['text'] }}</pre>
                </div>
              @endif
            </div>
          @endif
        </div>
      </div>

      <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold">2. Подложка для ручной обводки</div>
        <div class="card-body">
          @if($referenceImageUrl)
            <img src="{{ $referenceImageUrl }}" alt="Подложка для чертежа" class="workflow-preview mb-3">
          @endif
          <div class="workflow-note mb-3">
            Эта картинка показывается только под контуром комнаты в чертеже.
            Она не влияет на OCR и не перезаписывает распознанный черновик.
          </div>
          <form method="POST" action="{{ route('ceiling-projects.reference-image.upload', $project) }}" enctype="multipart/form-data" class="d-flex gap-2 flex-column">
            @csrf
            <input type="hidden" name="view_mode" value="{{ $viewMode }}">
            @if($selectedRoom)
              <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
            @endif
            <input type="file" name="reference_image" class="form-control" accept="image/*" required>
            <div class="workflow-actions">
              <button class="btn btn-outline-primary">Загрузить подложку</button>
            </div>
          </form>

          @error('reference_image')
            <div class="alert alert-danger mt-3 mb-0">{{ $message }}</div>
          @enderror

          @if(!$referenceImageUrl)
            <div class="small text-muted mt-3">Сейчас чертеж открыт без фоновой подложки. Можно сначала распознать эскиз, а потом отдельно загрузить удобное фото для ручной обводки.</div>
          @endif
        </div>
      </div>

      <div class="card shadow-sm">
        <div class="card-header fw-semibold">Смета проекта</div>
        <div class="card-body">
          @foreach($estimateRows as [$label, $meta, $value])
            <div class="d-flex justify-content-between align-items-start gap-3 {{ !$loop->first ? 'pt-2 mt-2 border-top' : '' }}">
              <div><div class="fw-semibold">{{ $label }}</div><div class="small text-muted">{{ $meta }}</div></div>
              <div class="fw-semibold">{{ $formatMoney($value) }}</div>
            </div>
          @endforeach
          <div class="d-flex justify-content-between align-items-start gap-3 pt-2 mt-2 border-top">
            <div><div class="fw-semibold">Итого</div><div class="small text-muted">@if($project->deal) Текущая сумма сделки: {{ $formatMoney($project->deal->amount ?? 0) }} @else Сделка не привязана @endif</div></div>
            <div class="fw-bold fs-5">{{ $formatMoney($summary['estimate']['grand_total']) }}</div>
          </div>
          <form method="POST" action="{{ route('ceiling-projects.apply-estimate', $project) }}" class="mt-3 d-flex justify-content-end">
            @csrf
            <button class="btn btn-success" @disabled(!$project->deal || $summary['estimate']['grand_total'] <= 0)>Перенести сумму в сделку</button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-xl-8 project-main d-flex flex-column gap-3">
      <div class="card shadow-sm mb-3 project-room-create-card">
        <div class="card-header fw-semibold">Добавить помещение</div>
        <div class="card-body">
          <form method="POST" action="{{ route('ceiling-projects.rooms.store', $project) }}" class="row g-3">
            @csrf
            <input type="hidden" name="view_mode" value="{{ $viewMode }}">
            <div class="col-md-4"><input type="text" name="name" class="form-control" placeholder="Название комнаты" required></div>
            <div class="col-md-2"><select name="shape_type" class="form-select">@foreach($shapeOptions as $value => $label)<option value="{{ $value }}">{{ $label }}</option>@endforeach</select></div>
            <div class="col-md-2"><input type="number" step="0.01" min="0" name="width_m" class="form-control" placeholder="Ширина, см"></div>
            <div class="col-md-2"><input type="number" step="0.01" min="0" name="length_m" class="form-control" placeholder="Длина, см"></div>
            <div class="col-md-2"><input type="number" step="0.01" min="0" name="height_m" class="form-control" placeholder="Высота, см"></div>
            <div class="col-md-3"><input type="number" step="0.01" min="0" name="manual_area_m2" class="form-control" placeholder="Ручная площадь"></div>
            <div class="col-md-3"><input type="number" step="0.01" min="0" name="manual_perimeter_m" class="form-control" placeholder="Ручной периметр, см"></div>
            <div class="col-md-2"><input type="number" min="0" name="spotlights_count" class="form-control" placeholder="Споты"></div>
            <div class="col-md-2"><input type="number" min="0" name="chandelier_points_count" class="form-control" placeholder="Люстры"></div>
            <div class="col-md-2"><input type="number" min="0" name="pipes_count" class="form-control" placeholder="Трубы"></div>
            <div class="col-12"><div class="small text-muted">Комнату можно создать с базовыми размерами, а потом перейти в редактор и обвести контур по фото замерщика.</div></div>
            <div class="col-12 d-flex justify-content-end"><button class="btn btn-outline-primary">Добавить комнату</button></div>
          </form>
        </div>
      </div>

      <div class="card shadow-sm mb-3 project-room-list-card">
        <div class="card-header fw-semibold">Комнаты</div>
        <div class="card-body">
          @if($project->rooms->count() === 0)
            <div class="text-muted">Пока нет ни одной комнаты.</div>
          @else
            <div class="d-grid gap-3">
              @foreach($summary['rooms'] as $roomData)
                @php($room = $roomData['model'])
                @php($metrics = $roomData['metrics'])
                <div class="room-card">
                  <form method="POST" action="{{ route('ceiling-projects.rooms.update', [$project, $room]) }}" class="row g-2">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                    <input type="hidden" name="room" value="{{ $room->id }}">
                    <div class="col-lg-3"><input type="text" name="name" class="form-control" value="{{ $room->name }}" required></div>
                    <div class="col-lg-2"><select name="shape_type" class="form-select">@foreach($shapeOptions as $value => $label)<option value="{{ $value }}" @selected($room->shape_type === $value)>{{ $label }}</option>@endforeach</select></div>
                    <div class="col-lg-2"><input type="number" step="0.01" min="0" name="width_m" class="form-control" value="{{ $room->width_m }}" placeholder="Ширина, см"></div>
                    <div class="col-lg-2"><input type="number" step="0.01" min="0" name="length_m" class="form-control" value="{{ $room->length_m }}" placeholder="Длина, см"></div>
                    <div class="col-lg-2"><input type="number" step="0.01" min="0" name="height_m" class="form-control" value="{{ $room->height_m }}" placeholder="Высота, см"></div>
                    <div class="col-lg-1"><button class="btn btn-primary w-100">OK</button></div>
                  </form>
                  <div class="row g-2 mt-2 small">
                    <div class="col-md-4"><b>Площадь:</b> {{ $formatDecimal($metrics['area_m2'], 'м2') }}</div>
                    <div class="col-md-4"><b>Периметр:</b> {{ $formatCentimeters($metrics['perimeter_m']) }}</div>
                    <div class="col-md-4"><b>Свет:</b> {{ $metrics['lighting_points_total'] }}</div>
                    <div class="col-md-4"><b>Ниши:</b> {{ $metrics['curtain_niches_count'] }}</div>
                    <div class="col-md-4"><b>Карнизы:</b> {{ $metrics['cornices_count'] }} / {{ $formatCentimeters($metrics['cornice_length_m']) }}</div>
                    <div class="col-md-4"><b>Трубы:</b> {{ $metrics['pipes_count'] }}</div>
                  </div>
                  <div class="mt-3 d-flex justify-content-between gap-2 flex-wrap">
                    <a href="{{ route('ceiling-projects.show', ['project' => $project, 'room' => $room->id]) }}#geometry-editor" class="btn btn-sm btn-outline-primary">Открыть чертеж комнаты</a>
                    <form method="POST" action="{{ route('ceiling-projects.rooms.destroy', [$project, $room]) }}" onsubmit="return confirm('Удалить комнату?');">
                      @csrf
                      @method('DELETE')
                      <button class="btn btn-sm btn-outline-danger">Удалить</button>
                    </form>
                  </div>
                </div>
              @endforeach
            </div>
          @endif
        </div>
      </div>

      <div class="card shadow-sm project-geometry-card" id="geometry-editor">
        <div class="card-header fw-semibold">Чертеж / Canvas комнаты</div>
        <div class="card-body">
          @if(!$selectedRoom)
            <div class="text-muted">Выберите комнату из списка выше, чтобы редактировать полигон и элементы.</div>
          @else
            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2 mb-3">
              <div>
                <div class="fw-semibold">{{ $selectedRoom->name }}</div>
                <div class="small text-muted">Контур редактируется по фото: клик по схеме вставляет точку в ближайшее ребро, точки можно таскать. В режиме элементов клик ставит координату нового элемента, а существующие маркеры можно перетаскивать.</div>
              </div>
              <div class="small text-muted">Область: {{ $formatCentimeters($editorWidth) }} × {{ $formatCentimeters($editorHeight) }}</div>
            </div>
            <div class="row g-3 mb-3 project-drawing-guide">
              <div class="col-lg-7">
                <div class="guide-card">
                  <div class="fw-semibold mb-3">Как пользоваться чертежом</div>
                  <div class="d-grid gap-2 small">
                    <div class="guide-step">
                      <span class="guide-step-index">1</span>
                      <div>Нажмите режим <b>Точка / угол</b>, затем кликом по схеме добавляйте вершины. Уже существующие черные точки можно тянуть мышкой.</div>
                    </div>
                    <div class="guide-step">
                      <span class="guide-step-index">2</span>
                      <div>Если нужно сдвинуть целую стену, выберите <b>Сдвиг стены</b>, кликните по нужному сегменту и потяните его. Красным подсвечивается выбранная стена.</div>
                    </div>
                    <div class="guide-step">
                      <span class="guide-step-index">3</span>
                      <div>Для спотов, труб и люстр включите <b>Поставить элемент</b> и кликните по схеме. Для ниш и карнизов выберите размещение <b>По стене</b>, потом кликните по нужной стене.</div>
                    </div>
                    <div class="guide-step">
                      <span class="guide-step-index">4</span>
                      <div>После правок нажмите <b>Сохранить геометрию</b>. Элементы сохраняются своей кнопкой в блоке справа/ниже.</div>
                    </div>
                  </div>
                </div>
              </div>
              <div class="col-lg-5">
                <div class="guide-card">
                  <div class="fw-semibold mb-3">Частые действия</div>
                  <div class="d-grid gap-2 small">
                    <div><b>Разрезать стену</b>: выберите сегмент и нажмите кнопку, чтобы вставить новую точку посередине.</div>
                    <div><b>Ортоснап</b>: держит точку или стену ближе к вертикали/горизонтали, когда нужно рисовать ровно.</div>
                    <div><b>Сбросить в прямоугольник</b>: возвращает комнату к базовой прямоугольной форме.</div>
                    <div><b>Размеры</b>: число над каждой стеной показывает ее текущую длину в сантиметрах.</div>
                  </div>
                </div>
              </div>
            </div>
            <div class="row g-3 project-geometry-layout">
              <div class="col-lg-8 project-canvas-col">
                <div class="geometry-stage" id="geometryStage">
                  <div class="geometry-toolbar d-flex justify-content-between align-items-center gap-2 flex-wrap">
                    <div class="d-flex gap-2 flex-wrap">
                      <button type="button" class="btn btn-sm btn-outline-secondary tool-toggle is-active" id="contourModeBtn">Точка / угол</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary tool-toggle" id="wallModeBtn">Сдвиг стены</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary tool-toggle" id="elementModeBtn">Поставить элемент</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary tool-toggle" id="handModeBtn">Рука</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="splitSegmentBtn">Разрезать стену</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="snapToggleBtn">Ортоснап: вкл</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="editorResetRect">Сбросить в прямоугольник</button>
                    </div>
                    <div class="d-flex gap-2 flex-wrap">
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="undoGeometryBtn">Отмена</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="redoGeometryBtn">Повтор</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="mirrorHorizontalBtn">Отразить X</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="mirrorVerticalBtn">Отразить Y</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="rotateLeftBtn">Повернуть -90°</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="rotateRightBtn">Повернуть +90°</button>
                    </div>
                    <div class="d-flex align-items-center flex-wrap geometry-toolbar-meta">
                      <div class="geometry-toolbar-zoom">
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomOutBtn">-</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomFitBtn">Весь чертеж</button>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="zoomInBtn">+</button>
                      </div>
                      @if($referenceImageUrl)
                        <div class="geometry-underlay-controls">
                          <button type="button" class="btn btn-sm btn-outline-secondary" id="backgroundToggleBtn">Подложка: вкл</button>
                          <input type="range" id="backgroundOpacityRange" class="form-range geometry-underlay-range" min="0" max="70" value="28">
                        </div>
                      @endif
                      <span class="badge text-bg-light" id="modePill">Режим: точка</span>
                      <span class="badge text-bg-light" id="segmentPill">Стена: 1</span>
                      <span class="badge text-bg-light" id="pointPill">Угол: 1</span>
                      <span class="badge text-bg-light" id="zoomPill">Масштаб: 100%</span>
                      <div class="small text-muted" id="geometryHint">Режим точки: клик добавляет точку в ближайшее ребро.</div>
                    </div>
                  </div>
                  <div class="geometry-toolbar-tip small text-muted px-3 py-2">
                    Колесо мыши меняет масштаб. Пробел + перетаскивание, режим руки или средняя кнопка мыши двигают чертеж. Горячие клавиши: Ctrl+Z, Ctrl+Y, H, V, W, E.
                  </div>
                  <svg id="geometrySvg" class="geometry-svg" viewBox="0 0 {{ $editorWidth }} {{ $editorHeight }}" data-width="{{ $editorWidth }}" data-height="{{ $editorHeight }}">
                    <defs>
                      <pattern id="gridPattern" width="1" height="1" patternUnits="userSpaceOnUse"><path d="M 1 0 L 0 0 0 1" fill="none" stroke="rgba(148,163,184,.35)" stroke-width="0.03"></path></pattern>
                    </defs>
                    <rect x="0" y="0" width="{{ $editorWidth }}" height="{{ $editorHeight }}" fill="url(#gridPattern)"></rect>
                    @if($referenceImageUrl)
                      <image id="geometryBackgroundImage" href="{{ $referenceImageUrl }}" x="0" y="0" width="{{ $editorWidth }}" height="{{ $editorHeight }}" preserveAspectRatio="none" opacity="0.28"></image>
                    @endif
                    <g id="geometryLayer"></g>
                  </svg>
                </div>
              </div>
              <div class="col-lg-4 project-points-col">
                <div class="border rounded p-3 points-panel">
                  <form method="POST" action="{{ route('ceiling-projects.rooms.geometry.update', [$project, $selectedRoom]) }}">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                    <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
                    <input type="hidden" name="shape_points_json" id="shapePointsInput" value='@json($selectedRoomPoints)'>
                    <div class="fw-semibold mb-3">Редактор геометрии</div>
                    <div class="inspector-quick-grid mb-3">
                      <div class="inspector-card">
                        <div class="inspector-kicker">Выбранная точка</div>
                        <div class="d-flex align-items-center gap-2 mb-2">
                          <span class="point-row-letter" id="selectedPointLetter">A</span>
                          <div class="small text-muted" id="selectedPointTitle">Угол A</div>
                        </div>
                        <div class="row g-2">
                          <div class="col-6">
                            <label class="form-label small mb-1">X, см</label>
                            <input type="number" step="1" min="0" class="form-control form-control-sm" id="selectedPointXInput">
                          </div>
                          <div class="col-6">
                            <label class="form-label small mb-1">Y, см</label>
                            <input type="number" step="1" min="0" class="form-control form-control-sm" id="selectedPointYInput">
                          </div>
                        </div>
                      </div>
                      <div class="inspector-card">
                        <div class="inspector-kicker">Выбранная сторона</div>
                        <div class="small fw-semibold mb-2" id="selectedSegmentTitle">Сторона AB</div>
                        <div class="row g-2">
                          <div class="col-8">
                            <label class="form-label small mb-1">Длина, см</label>
                            <input type="number" step="1" min="1" class="form-control form-control-sm" id="selectedSegmentLengthInput">
                          </div>
                          <div class="col-4">
                            <label class="form-label small mb-1">Угол</label>
                            <input type="text" class="form-control form-control-sm" id="selectedAngleInput" readonly>
                          </div>
                        </div>
                        <div class="row g-2 mt-1">
                          <div class="col-4">
                            <label class="form-label small mb-1">Шаг, см</label>
                            <input type="number" step="1" min="1" class="form-control form-control-sm" id="segmentStepInput" value="5">
                          </div>
                          <div class="col-8 d-flex gap-2 align-items-end">
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="decreaseSegmentLengthBtn">- шаг</button>
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="increaseSegmentLengthBtn">+ шаг</button>
                          </div>
                        </div>
                        <button type="button" class="btn btn-sm btn-outline-dark w-100 mt-2" id="applySegmentLengthBtn">Изменить длину</button>
                      </div>
                    </div>
                    <div class="inspector-actions mb-3">
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="prevSegmentBtn">Пред. сторона</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="nextSegmentBtn">След. сторона</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary" id="insertPointAfterBtn">Добавить после</button>
                      <button type="button" class="btn btn-sm btn-outline-danger" id="deletePointBtn">Удалить точку</button>
                    </div>
                    <div class="inspector-tabs mb-3">
                      <button type="button" class="btn btn-sm btn-outline-secondary inspector-tab is-active" id="pointsTabBtn">Точки</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary inspector-tab" id="segmentsTabBtn">Стороны</button>
                      <button type="button" class="btn btn-sm btn-outline-secondary inspector-tab" id="anglesTabBtn">Углы</button>
                    </div>
                    <div class="inspector-panel is-active mb-3" id="pointsInspectorPanel">
                      <div class="inspector-stack" id="pointsList"></div>
                    </div>
                    <div class="inspector-panel mb-3" id="segmentsInspectorPanel">
                      <div class="inspector-stack" id="segmentsList"></div>
                    </div>
                    <div class="inspector-panel mb-3" id="anglesInspectorPanel">
                      <div class="inspector-stack" id="anglesList"></div>
                    </div>
                    <div class="small text-muted mb-3">Логика как в EasyCeiling: выберите вершину или сторону на схеме, затем правьте ее точно в панели справа. Изменения элементов комнаты сохраняются отдельной кнопкой у нужного элемента.</div>
                    <button class="btn btn-primary w-100">Сохранить геометрию</button>
                  </form>
                </div>
              </div>
            </div>
          @endif
        </div>
      </div>

      @if($selectedRoom)
        <div class="card shadow-sm mt-3 project-elements-card">
          <div class="card-header fw-semibold">Элементы комнаты</div>
          <div class="card-body">
            <div class="d-flex gap-2 flex-wrap mb-3">
              @foreach($selectedRoomElements as $element)
                <span class="element-chip">
                  <span class="element-chip-dot" style="background: {{ $elementColor($element->type) }}"></span>
                  {{ $elementTypeOptions[$element->type] ?? $element->type }}{{ $element->quantity > 1 ? ' × '.$element->quantity : '' }}
                </span>
              @endforeach
            </div>
            <div class="row g-3">
              <div class="col-lg-5">
                <form method="POST" action="{{ route('ceiling-projects.rooms.elements.store', [$project, $selectedRoom]) }}" class="row g-3" id="newRoomElementForm">
                  @csrf
                  <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                  <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
                  <div class="col-md-6">
                    <label class="form-label">Тип</label>
                    <select name="type" class="form-select" id="newElementType">
                      @foreach($elementTypeOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Название</label>
                    <input type="text" name="label" class="form-control" id="newElementLabel" placeholder="Напр.: ниша у окна">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Кол-во</label>
                    <input type="number" min="1" name="quantity" class="form-control" value="1" id="newElementQuantity">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">X, см</label>
                    <input type="number" step="0.01" min="0" name="x_m" class="form-control" placeholder="0.00" id="newElementX">
                  </div>
                  <div class="col-md-4">
                    <label class="form-label">Y, см</label>
                    <input type="number" step="0.01" min="0" name="y_m" class="form-control" placeholder="0.00" id="newElementY">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Размещение</label>
                    <select name="placement_mode" class="form-select" id="newElementPlacementMode">
                      @foreach($elementPlacementOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                      @endforeach
                    </select>
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Стена</label>
                    <input type="number" min="0" name="segment_index" class="form-control" placeholder="№" id="newElementSegmentIndex">
                  </div>
                  <div class="col-md-3">
                    <label class="form-label">Смещение, см</label>
                    <input type="number" step="0.01" min="0" name="offset_m" class="form-control" placeholder="0.00" id="newElementOffset">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Длина, см</label>
                    <input type="number" step="0.01" min="0" name="length_m" class="form-control" placeholder="Для карниза/ниши" id="newElementLength">
                  </div>
                  <div class="col-md-6">
                    <label class="form-label">Заметка</label>
                    <input type="text" name="notes" class="form-control" placeholder="Комментарий" id="newElementNotes">
                  </div>
                  <div class="col-12 small text-muted">Для свободного элемента используйте координату на схеме. Для ниши или карниза выберите размещение «По стене» и кликните по нужному сегменту.</div>
                  <div class="col-12 d-flex justify-content-between gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-secondary" id="pickElementPointBtn">Взять координату со схемы</button>
                    <button class="btn btn-outline-primary">Добавить элемент</button>
                  </div>
                </form>
              </div>
              <div class="col-lg-7">
                @if($selectedRoomElements->count() === 0)
                  <div class="text-muted">Пока нет элементов. Добавьте точки света, карнизы, ниши, трубы, вентиляцию и прочее.</div>
                @else
                  <div class="d-grid gap-3">
                    @foreach($selectedRoomElements as $element)
                      <div class="border rounded p-3">
                        <form method="POST" action="{{ route('ceiling-projects.rooms.elements.update', [$project, $selectedRoom, $element]) }}" class="row g-2" data-element-form="{{ $element->id }}">
                          @csrf
                          @method('PATCH')
                          <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                          <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
                          <div class="col-md-4">
                            <select name="type" class="form-select">
                              @foreach($elementTypeOptions as $value => $label)
                                <option value="{{ $value }}" @selected($element->type === $value)>{{ $label }}</option>
                              @endforeach
                            </select>
                          </div>
                          <div class="col-md-4"><input type="text" name="label" class="form-control" value="{{ $element->label }}" placeholder="Название"></div>
                          <div class="col-md-4 d-flex align-items-center"><span class="badge {{ $elementBadgeClass($element->type) }}">{{ $elementTypeOptions[$element->type] ?? $element->type }}</span></div>
                          <div class="col-md-2"><input type="number" min="1" name="quantity" class="form-control" value="{{ $element->quantity }}" placeholder="Кол-во"></div>
                          <div class="col-md-3">
                            <select name="placement_mode" class="form-select" data-element-placement="{{ $element->id }}">
                              @foreach($elementPlacementOptions as $value => $label)
                                <option value="{{ $value }}" @selected(($element->placement_mode ?? 'free') === $value)>{{ $label }}</option>
                              @endforeach
                            </select>
                          </div>
                          <div class="col-md-2"><input type="number" step="0.01" min="0" name="x_m" class="form-control" value="{{ $element->x_m }}" placeholder="X, см" data-element-x="{{ $element->id }}"></div>
                          <div class="col-md-2"><input type="number" step="0.01" min="0" name="y_m" class="form-control" value="{{ $element->y_m }}" placeholder="Y, см" data-element-y="{{ $element->id }}"></div>
                          <div class="col-md-2"><input type="number" min="0" name="segment_index" class="form-control" value="{{ $element->segment_index }}" placeholder="Стена" data-element-segment="{{ $element->id }}"></div>
                          <div class="col-md-3"><input type="number" step="0.01" min="0" name="offset_m" class="form-control" value="{{ $element->offset_m }}" placeholder="Смещение, см" data-element-offset="{{ $element->id }}"></div>
                          <div class="col-md-3"><input type="number" step="0.01" min="0" name="length_m" class="form-control" value="{{ $element->length_m }}" placeholder="Длина, см"></div>
                          <div class="col-md-3"><input type="text" name="notes" class="form-control" value="{{ $element->notes }}" placeholder="Заметка"></div>
                          <div class="col-md-12 d-flex justify-content-between gap-2 flex-wrap">
                            <div class="small text-muted d-flex align-items-center">Маркеры на схеме можно перетаскивать, затем сохранить этот элемент.</div>
                            <button class="btn btn-sm btn-primary">Сохранить</button>
                          </div>
                        </form>
                        <form method="POST" action="{{ route('ceiling-projects.rooms.elements.destroy', [$project, $selectedRoom, $element]) }}" class="mt-2 d-flex justify-content-end" onsubmit="return confirm('Удалить элемент?');">
                          @csrf
                          @method('DELETE')
                          <button class="btn btn-sm btn-outline-danger">Удалить</button>
                        </form>
                      </div>
                    @endforeach
                  </div>
                @endif
              </div>
            </div>
          </div>
        </div>
      @endif
    </div>
  </div>
</div>
@endsection

@push('scripts')
@if($isDraftingMode)
<script>
(() => {
  document.querySelectorAll('form[action*="/ceiling-projects/"]').forEach((form) => {
    if (!form.querySelector('input[name="view_mode"]')) {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'view_mode';
      input.value = 'drafting';
      form.appendChild(input);
    }
  });

  document.querySelectorAll('a.btn-outline-primary[href*="#geometry-editor"]').forEach((link) => {
    try {
      const url = new URL(link.getAttribute('href'), window.location.origin);
      if (url.pathname === '/ceiling-projects/{{ $project->id }}') {
        url.pathname = '/ceiling-projects/{{ $project->id }}/drafting';
        url.hash = '';
        link.href = url.toString();
      }
    } catch (error) {
      console.warn('Failed to normalize drafting link', error);
    }
  });
})();
</script>
@endif
<script>
(() => {
  const centimeterFieldNames = new Set(['width_m', 'length_m', 'height_m', 'manual_perimeter_m', 'x_m', 'y_m', 'offset_m']);
  const toCentimeters = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? Math.round(parsed * 100) : '';
  };
  const toMeters = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? Math.round((parsed / 100) * 100) / 100 : '';
  };

  document.querySelectorAll('input[name]').forEach((input) => {
    if (!(input instanceof HTMLInputElement) || !centimeterFieldNames.has(input.name)) return;

    if (input.value !== '') {
      input.value = toCentimeters(input.value);
    }

    input.step = '1';
    input.dataset.centimeterField = '1';
  });

  document.querySelectorAll('form').forEach((form) => {
    form.addEventListener('submit', () => {
      form.querySelectorAll('input[data-centimeter-field="1"]').forEach((input) => {
        if (!(input instanceof HTMLInputElement) || input.value === '') return;
        input.value = toMeters(input.value);
      });
    });
  });
})();
</script>
@if($selectedRoom)
<script>
(() => {
  const geometryStage = document.getElementById('geometryStage');
  const svg = document.getElementById('geometrySvg');
  const layer = document.getElementById('geometryLayer');
  const input = document.getElementById('shapePointsInput');
  const list = document.getElementById('pointsList');
  const segmentsList = document.getElementById('segmentsList');
  const anglesList = document.getElementById('anglesList');
  const resetRectBtn = document.getElementById('editorResetRect');
  const contourModeBtn = document.getElementById('contourModeBtn');
  const wallModeBtn = document.getElementById('wallModeBtn');
  const elementModeBtn = document.getElementById('elementModeBtn');
  const handModeBtn = document.getElementById('handModeBtn');
  const splitSegmentBtn = document.getElementById('splitSegmentBtn');
  const snapToggleBtn = document.getElementById('snapToggleBtn');
  const undoGeometryBtn = document.getElementById('undoGeometryBtn');
  const redoGeometryBtn = document.getElementById('redoGeometryBtn');
  const mirrorHorizontalBtn = document.getElementById('mirrorHorizontalBtn');
  const mirrorVerticalBtn = document.getElementById('mirrorVerticalBtn');
  const rotateLeftBtn = document.getElementById('rotateLeftBtn');
  const rotateRightBtn = document.getElementById('rotateRightBtn');
  const zoomOutBtn = document.getElementById('zoomOutBtn');
  const zoomFitBtn = document.getElementById('zoomFitBtn');
  const zoomInBtn = document.getElementById('zoomInBtn');
  const backgroundImage = document.getElementById('geometryBackgroundImage');
  const backgroundToggleBtn = document.getElementById('backgroundToggleBtn');
  const backgroundOpacityRange = document.getElementById('backgroundOpacityRange');
  const modePill = document.getElementById('modePill');
  const segmentPill = document.getElementById('segmentPill');
  const pointPill = document.getElementById('pointPill');
  const zoomPill = document.getElementById('zoomPill');
  const geometryHint = document.getElementById('geometryHint');
  const pickElementPointBtn = document.getElementById('pickElementPointBtn');
  const newElementType = document.getElementById('newElementType');
  const newElementPlacementMode = document.getElementById('newElementPlacementMode');
  const newElementSegmentIndex = document.getElementById('newElementSegmentIndex');
  const newElementOffset = document.getElementById('newElementOffset');
  const newElementLength = document.getElementById('newElementLength');
  const newElementX = document.getElementById('newElementX');
  const newElementY = document.getElementById('newElementY');
  const pointsTabBtn = document.getElementById('pointsTabBtn');
  const segmentsTabBtn = document.getElementById('segmentsTabBtn');
  const anglesTabBtn = document.getElementById('anglesTabBtn');
  const pointsInspectorPanel = document.getElementById('pointsInspectorPanel');
  const segmentsInspectorPanel = document.getElementById('segmentsInspectorPanel');
  const anglesInspectorPanel = document.getElementById('anglesInspectorPanel');
  const insertPointAfterBtn = document.getElementById('insertPointAfterBtn');
  const deletePointBtn = document.getElementById('deletePointBtn');
  const selectedPointLetter = document.getElementById('selectedPointLetter');
  const selectedPointTitle = document.getElementById('selectedPointTitle');
  const selectedPointXInput = document.getElementById('selectedPointXInput');
  const selectedPointYInput = document.getElementById('selectedPointYInput');
  const selectedSegmentTitle = document.getElementById('selectedSegmentTitle');
  const selectedSegmentLengthInput = document.getElementById('selectedSegmentLengthInput');
  const selectedAngleInput = document.getElementById('selectedAngleInput');
  const segmentStepInput = document.getElementById('segmentStepInput');
  const decreaseSegmentLengthBtn = document.getElementById('decreaseSegmentLengthBtn');
  const increaseSegmentLengthBtn = document.getElementById('increaseSegmentLengthBtn');
  const applySegmentLengthBtn = document.getElementById('applySegmentLengthBtn');
  const prevSegmentBtn = document.getElementById('prevSegmentBtn');
  const nextSegmentBtn = document.getElementById('nextSegmentBtn');
  if (!svg || !layer || !input || !list) return;

  const workspaceWidth = Number(svg.dataset.width || 8);
  const workspaceHeight = Number(svg.dataset.height || 6);
  const rectWidth = Math.max(1, Number({{ json_encode((float) ($selectedRoom->width_m ?? 4)) }}));
  const rectHeight = Math.max(1, Number({{ json_encode((float) ($selectedRoom->length_m ?? 3)) }}));
  const baseRect = [
    { x: 0, y: 0 },
    { x: rectWidth, y: 0 },
    { x: rectWidth, y: rectHeight },
    { x: 0, y: rectHeight },
  ];
  const roomElements = @json($selectedRoomElementsPayload);
  const elementColors = {
    spotlight: '#f59e0b',
    chandelier: '#06b6d4',
    pipe: '#6b7280',
    curtain_niche: '#16a34a',
    ventilation: '#2563eb',
    cornice: '#0f172a',
    custom: '#9333ea',
  };
  const elementLabels = @json($elementTypeOptions);

  let points;
  try {
    points = JSON.parse(input.value);
  } catch (error) {
    points = baseRect;
  }
  if (!Array.isArray(points) || points.length < 3) {
    points = baseRect.map((point) => ({ ...point }));
  }

  let activeMode = 'contour';
  let selectedSegmentIndex = 0;
  let selectedPointIndex = 0;
  let dragPointIndex = null;
  let dragSegmentState = null;
  let dragElementIndex = null;
  let panState = null;
  let suppressCanvasClick = false;
  let isSpacePressed = false;
  let snapEnabled = true;
  let renderFrame = null;
  let scheduledRenderOptions = { syncList: false, syncInput: false };
  let backgroundVisible = !!backgroundImage;
  let inspectorTab = 'points';
  let undoStack = [];
  let redoStack = [];
  let viewport = {
    x: 0,
    y: 0,
    width: workspaceWidth,
    height: workspaceHeight,
  };

  const clamp = (value, min, max) => Math.min(max, Math.max(min, value));
  const round = (value) => Math.round(value * 100) / 100;
  const toNumber = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? parsed : null;
  };
  const metersToCentimeters = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? Math.round(parsed * 100) : '';
  };
  const centimetersToMeters = (value) => {
    const parsed = Number(value);
    return Number.isFinite(parsed) ? round(parsed / 100) : null;
  };
  const clonePoints = (items = points) => items.map((point) => ({
    x: round(Number(point.x ?? 0)),
    y: round(Number(point.y ?? 0)),
  }));
  const cloneElements = (items = roomElements) => items.map((element) => ({
    ...element,
    x_m: element.x_m === null || element.x_m === undefined ? null : round(Number(element.x_m)),
    y_m: element.y_m === null || element.y_m === undefined ? null : round(Number(element.y_m)),
    offset_m: element.offset_m === null || element.offset_m === undefined ? null : round(Number(element.offset_m)),
    length_m: element.length_m === null || element.length_m === undefined ? null : round(Number(element.length_m)),
  }));
  const captureState = () => ({
    points: clonePoints(),
    roomElements: cloneElements(),
    selectedSegmentIndex,
    selectedPointIndex,
    viewport: { ...viewport },
  });
  const restoreState = (state) => {
    points = clonePoints(state.points ?? []);
    const nextElements = cloneElements(state.roomElements ?? []);
    roomElements.splice(0, roomElements.length, ...nextElements);
    selectedSegmentIndex = Math.max(0, Math.min(Number(state.selectedSegmentIndex ?? 0), Math.max(points.length - 1, 0)));
    selectedPointIndex = Math.max(0, Math.min(Number(state.selectedPointIndex ?? 0), Math.max(points.length - 1, 0)));
    if (state.viewport) {
      viewport = { ...viewport, ...state.viewport };
    }
    syncAllElementForms();
    updateExistingPlacementFields();
  };
  const refreshHistoryButtons = () => {
    if (undoGeometryBtn) undoGeometryBtn.disabled = undoStack.length === 0;
    if (redoGeometryBtn) redoGeometryBtn.disabled = redoStack.length === 0;
  };
  const pushHistory = () => {
    undoStack.push(captureState());
    if (undoStack.length > 80) {
      undoStack = undoStack.slice(-80);
    }
    redoStack = [];
    refreshHistoryButtons();
  };
  const undoGeometry = () => {
    if (undoStack.length === 0) return;
    const previous = undoStack.pop();
    redoStack.push(captureState());
    restoreState(previous);
    refreshHistoryButtons();
    render({ syncList: true, syncInput: true });
  };
  const redoGeometry = () => {
    if (redoStack.length === 0) return;
    const next = redoStack.pop();
    undoStack.push(captureState());
    restoreState(next);
    refreshHistoryButtons();
    render({ syncList: true, syncInput: true });
  };
  const formatLength = (value) => `${metersToCentimeters(value)} см`;
  const pointLabel = (index) => {
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const normalized = ((index % points.length) + points.length) % points.length;
    const base = alphabet[normalized % alphabet.length];
    const cycle = Math.floor(normalized / alphabet.length);
    return cycle === 0 ? base : `${base}${cycle + 1}`;
  };
  const segmentLabel = (index) => `${pointLabel(index)}${pointLabel(index + 1)}`;

  const pointerToSvg = (clientX, clientY) => {
    const point = svg.createSVGPoint();
    point.x = clientX;
    point.y = clientY;
    const transformed = point.matrixTransform(svg.getScreenCTM().inverse());
    return {
      x: round(clamp(transformed.x, 0, workspaceWidth)),
      y: round(clamp(transformed.y, 0, workspaceHeight)),
    };
  };

  const pixelsToWorld = (pixels) => {
    const rect = svg.getBoundingClientRect();
    const ratioX = viewport.width / Math.max(rect.width, 1);
    const ratioY = viewport.height / Math.max(rect.height, 1);
    return Math.max(0.01, round(pixels * Math.max(ratioX, ratioY)));
  };

  const clampViewport = (nextViewport) => {
    const minWidth = Math.max(workspaceWidth * 0.12, 1.2);
    const minHeight = Math.max(workspaceHeight * 0.12, 1.2);
    const normalized = {
      x: nextViewport.x,
      y: nextViewport.y,
      width: clamp(nextViewport.width, minWidth, workspaceWidth),
      height: clamp(nextViewport.height, minHeight, workspaceHeight),
    };

    normalized.x = normalized.width >= workspaceWidth
      ? 0
      : clamp(normalized.x, 0, workspaceWidth - normalized.width);
    normalized.y = normalized.height >= workspaceHeight
      ? 0
      : clamp(normalized.y, 0, workspaceHeight - normalized.height);

    return normalized;
  };

  const applyViewport = (nextViewport) => {
    viewport = clampViewport(nextViewport);
    svg.setAttribute('viewBox', `${round(viewport.x)} ${round(viewport.y)} ${round(viewport.width)} ${round(viewport.height)}`);

    if (zoomPill) {
      const zoomPercent = Math.max(1, Math.round((workspaceWidth / viewport.width) * 100));
      zoomPill.textContent = `Масштаб: ${zoomPercent}%`;
    }
  };

  const syncBackgroundState = () => {
    if (!backgroundImage) return;

    const opacity = Number(backgroundOpacityRange?.value ?? 28) / 100;
    backgroundImage.setAttribute('opacity', backgroundVisible ? `${opacity}` : '0');

    if (backgroundToggleBtn) {
      backgroundToggleBtn.textContent = `Подложка: ${backgroundVisible ? 'вкл' : 'выкл'}`;
    }

    if (backgroundOpacityRange) {
      backgroundOpacityRange.disabled = !backgroundVisible;
    }
  };

  const fitViewport = (paddingMeters = 0.8) => {
    const bounds = points.reduce((carry, point) => ({
      minX: Math.min(carry.minX, point.x),
      minY: Math.min(carry.minY, point.y),
      maxX: Math.max(carry.maxX, point.x),
      maxY: Math.max(carry.maxY, point.y),
    }), {
      minX: Number.POSITIVE_INFINITY,
      minY: Number.POSITIVE_INFINITY,
      maxX: Number.NEGATIVE_INFINITY,
      maxY: Number.NEGATIVE_INFINITY,
    });

    if (!Number.isFinite(bounds.minX) || !Number.isFinite(bounds.minY)) {
      applyViewport({ x: 0, y: 0, width: workspaceWidth, height: workspaceHeight });
      return;
    }

    const rect = svg.getBoundingClientRect();
    const aspectRatio = rect.width > 0 && rect.height > 0 ? rect.width / rect.height : (workspaceWidth / workspaceHeight);
    let nextWidth = Math.max(bounds.maxX - bounds.minX + (paddingMeters * 2), 1.4);
    let nextHeight = Math.max(bounds.maxY - bounds.minY + (paddingMeters * 2), 1.4);

    if ((nextWidth / nextHeight) > aspectRatio) {
      nextHeight = nextWidth / aspectRatio;
    } else {
      nextWidth = nextHeight * aspectRatio;
    }

    applyViewport({
      x: bounds.minX - ((nextWidth - (bounds.maxX - bounds.minX)) / 2),
      y: bounds.minY - ((nextHeight - (bounds.maxY - bounds.minY)) / 2),
      width: nextWidth,
      height: nextHeight,
    });
  };

  const zoomViewport = (factor, anchorClientX = null, anchorClientY = null) => {
    const anchor = anchorClientX === null || anchorClientY === null
      ? { x: viewport.x + (viewport.width / 2), y: viewport.y + (viewport.height / 2) }
      : pointerToSvg(anchorClientX, anchorClientY);

    const nextWidth = viewport.width / factor;
    const nextHeight = viewport.height / factor;
    const ratioX = (anchor.x - viewport.x) / Math.max(viewport.width, 0.001);
    const ratioY = (anchor.y - viewport.y) / Math.max(viewport.height, 0.001);

    applyViewport({
      x: anchor.x - (nextWidth * ratioX),
      y: anchor.y - (nextHeight * ratioY),
      width: nextWidth,
      height: nextHeight,
    });
  };

  const writeInput = () => {
    input.value = JSON.stringify(points);
  };

  const scheduleRender = ({ syncList = true, syncInput = true } = {}) => {
    scheduledRenderOptions = {
      syncList: scheduledRenderOptions.syncList || syncList,
      syncInput: scheduledRenderOptions.syncInput || syncInput,
    };

    if (renderFrame !== null) return;

    renderFrame = window.requestAnimationFrame(() => {
      const nextOptions = scheduledRenderOptions;
      scheduledRenderOptions = { syncList: false, syncInput: false };
      renderFrame = null;
      render(nextOptions);
    });
  };

  const getSegmentGeometry = (index) => {
    if (!Array.isArray(points) || points.length < 2) return null;
    const normalizedIndex = ((index % points.length) + points.length) % points.length;
    const nextIndex = (normalizedIndex + 1) % points.length;
    const start = points[normalizedIndex];
    const end = points[nextIndex];
    const dx = end.x - start.x;
    const dy = end.y - start.y;
    const length = Math.hypot(dx, dy);

    return {
      index: normalizedIndex,
      nextIndex,
      start,
      end,
      dx,
      dy,
      length,
      directionX: length > 0 ? dx / length : 1,
      directionY: length > 0 ? dy / length : 0,
    };
  };

  const getPointAngle = (index) => {
    if (!Array.isArray(points) || points.length < 3) return null;

    const current = points[((index % points.length) + points.length) % points.length];
    const previous = points[(index - 1 + points.length) % points.length];
    const next = points[(index + 1) % points.length];
    const a = { x: previous.x - current.x, y: previous.y - current.y };
    const b = { x: next.x - current.x, y: next.y - current.y };
    const lengthA = Math.hypot(a.x, a.y);
    const lengthB = Math.hypot(b.x, b.y);
    if (!lengthA || !lengthB) return null;

    const cosine = clamp(((a.x * b.x) + (a.y * b.y)) / (lengthA * lengthB), -1, 1);
    return Math.round((Math.acos(cosine) * (180 / Math.PI)) * 10) / 10;
  };

  const setSegmentLength = (index, nextLength) => {
    const segment = getSegmentGeometry(index);
    if (!segment || !Number.isFinite(nextLength) || nextLength <= 0) return;

    const safeLength = clamp(round(nextLength), 0.1, Math.max(workspaceWidth, workspaceHeight) * 2);
    const nextPoint = {
      x: clamp(round(segment.start.x + (segment.directionX * safeLength)), 0, workspaceWidth),
      y: clamp(round(segment.start.y + (segment.directionY * safeLength)), 0, workspaceHeight),
    };

    points[segment.nextIndex] = nextPoint;
  };

  const setInspectorTab = (tab) => {
    inspectorTab = tab;
    pointsTabBtn?.classList.toggle('is-active', tab === 'points');
    segmentsTabBtn?.classList.toggle('is-active', tab === 'segments');
    anglesTabBtn?.classList.toggle('is-active', tab === 'angles');
    pointsInspectorPanel?.classList.toggle('is-active', tab === 'points');
    segmentsInspectorPanel?.classList.toggle('is-active', tab === 'segments');
    anglesInspectorPanel?.classList.toggle('is-active', tab === 'angles');
  };

  const projectToSegment = (point, segment) => {
    const denominator = (segment.dx ** 2) + (segment.dy ** 2);
    if (denominator === 0) {
      return { x: segment.start.x, y: segment.start.y, offset: 0 };
    }

    const t = clamp((((point.x - segment.start.x) * segment.dx) + ((point.y - segment.start.y) * segment.dy)) / denominator, 0, 1);

    return {
      x: round(segment.start.x + (t * segment.dx)),
      y: round(segment.start.y + (t * segment.dy)),
      offset: round(t * segment.length),
    };
  };

  const pointAlongSegment = (segmentIndex, offset) => {
    const segment = getSegmentGeometry(segmentIndex);
    if (!segment) return null;

    const safeOffset = clamp(offset, 0, segment.length);
    const ratio = segment.length > 0 ? safeOffset / segment.length : 0;

    return {
      x: round(segment.start.x + (segment.dx * ratio)),
      y: round(segment.start.y + (segment.dy * ratio)),
      segment,
    };
  };

  const syncElementFormCoordinates = (elementId, point) => {
    const xInput = document.querySelector(`[data-element-x="${elementId}"]`);
    const yInput = document.querySelector(`[data-element-y="${elementId}"]`);
    if (xInput) xInput.value = metersToCentimeters(point.x);
    if (yInput) yInput.value = metersToCentimeters(point.y);
  };

  const syncElementFormAttachment = (elementId, element) => {
    const placementInput = document.querySelector(`[data-element-placement="${elementId}"]`);
    const segmentInput = document.querySelector(`[data-element-segment="${elementId}"]`);
    const offsetInput = document.querySelector(`[data-element-offset="${elementId}"]`);
    if (placementInput) placementInput.value = element.placement_mode ?? 'free';
    if (segmentInput) segmentInput.value = element.segment_index ?? '';
    if (offsetInput) offsetInput.value = element.offset_m === null || element.offset_m === undefined ? '' : metersToCentimeters(element.offset_m);
  };
  const syncAllElementForms = () => {
    roomElements.forEach((element) => {
      if ((element.placement_mode ?? 'free') === 'wall') {
        syncElementFormAttachment(element.id, element);
      } else if (element.x_m !== null && element.y_m !== null) {
        syncElementFormCoordinates(element.id, { x: element.x_m, y: element.y_m });
      }
    });
  };

  const assignNewElementToSegment = (segmentIndex, point) => {
    const segment = getSegmentGeometry(segmentIndex);
    if (!segment || !newElementSegmentIndex || !newElementOffset) return;

    const projection = projectToSegment(point, segment);
    newElementPlacementMode.value = 'wall';
    newElementSegmentIndex.value = segment.index;
    newElementOffset.value = metersToCentimeters(projection.offset);
    if (newElementX) newElementX.value = '';
    if (newElementY) newElementY.value = '';

    if (newElementLength && !newElementLength.value && (newElementType?.value === 'cornice' || newElementType?.value === 'curtain_niche')) {
      newElementLength.value = metersToCentimeters(Math.max(segment.length - projection.offset, 0));
    }
  };

  const resolveElementGeometry = (element) => {
    if ((element.placement_mode ?? 'free') === 'wall' && Number.isInteger(element.segment_index)) {
      const anchor = pointAlongSegment(element.segment_index, Number(element.offset_m ?? 0));
      if (!anchor) return null;

      const geometry = {
        x: anchor.x,
        y: anchor.y,
        placement: 'wall',
        segmentIndex: anchor.segment.index,
      };

      if (Number(element.length_m || 0) > 0) {
        const endPoint = pointAlongSegment(anchor.segment.index, Number(element.offset_m ?? 0) + Number(element.length_m));
        if (endPoint) {
          geometry.endX = endPoint.x;
          geometry.endY = endPoint.y;
        }
      }

      return geometry;
    }

    if (element.x_m === null || element.y_m === null) {
      return null;
    }

    return {
      x: element.x_m,
      y: element.y_m,
      placement: 'free',
      segmentIndex: null,
    };
  };

  const applyPointSnap = (point, index) => {
    if (!snapEnabled || points.length < 2) return point;

    const previous = points[(index - 1 + points.length) % points.length];
    const next = points[(index + 1) % points.length];
    const snapped = { ...point };
    const threshold = 0.18;

    [previous, next].forEach((candidate) => {
      if (Math.abs(snapped.x - candidate.x) <= threshold) {
        snapped.x = candidate.x;
      }
      if (Math.abs(snapped.y - candidate.y) <= threshold) {
        snapped.y = candidate.y;
      }
    });

    snapped.x = round(clamp(snapped.x, 0, workspaceWidth));
    snapped.y = round(clamp(snapped.y, 0, workspaceHeight));

    return snapped;
  };

  const updateGeometryHint = () => {
    const segment = getSegmentGeometry(selectedSegmentIndex);
    const segmentText = segment ? ` Сторона ${segmentLabel(segment.index)}: ${formatLength(segment.length)}.` : '';

    if (segmentPill) {
      segmentPill.textContent = segment ? `Сторона: ${segmentLabel(segment.index)} (${formatLength(segment.length)})` : 'Сторона: —';
    }

    if (pointPill) {
      pointPill.textContent = points[selectedPointIndex] ? `Угол: ${pointLabel(selectedPointIndex)}` : 'Угол: —';
    }

    if (geometryStage) {
      geometryStage.classList.toggle('is-pan-ready', isSpacePressed || panState !== null);
      geometryStage.classList.toggle('is-panning', panState !== null);
    }

    if (svg) {
      svg.style.cursor = panState
        ? 'grabbing'
        : ((isSpacePressed || activeMode === 'hand') ? 'grab' : (activeMode === 'element' ? 'crosshair' : 'default'));
    }

    if (!geometryHint) return;

    if (activeMode === 'hand') {
      if (modePill) modePill.textContent = 'Режим: рука';
      geometryHint.textContent = 'Режим руки: перетаскивайте холст левой кнопкой мыши. Колесо меняет масштаб, Вписать возвращает комнату в кадр.';
      return;
    }

    if (activeMode === 'wall') {
      if (modePill) modePill.textContent = 'Режим: стена';
      geometryHint.textContent = `Режим стены: выберите сегмент и тяните его параллельно. Колесо мыши меняет масштаб.${segmentText}`;
      return;
    }

    if (activeMode === 'element') {
      if (modePill) modePill.textContent = 'Режим: элемент';
      const placement = newElementPlacementMode?.value === 'wall'
        ? 'Клик по стене привяжет элемент к сегменту.'
        : 'Клик по полю подставляет X/Y для свободного элемента.';
      geometryHint.textContent = `Режим элемента: ${placement} Пробел + drag двигают поле.${segmentText}`;
      return;
    }

    if (modePill) modePill.textContent = 'Режим: точка';
    geometryHint.textContent = `Режим точки: тяните угол, а клик по полю добавляет вершину в ближайшее ребро. Пробел + drag двигают поле.${segmentText}`;
  };

  const setMode = (mode) => {
    activeMode = mode;
    contourModeBtn?.classList.toggle('is-active', mode === 'contour');
    wallModeBtn?.classList.toggle('is-active', mode === 'wall');
    elementModeBtn?.classList.toggle('is-active', mode === 'element');
    handModeBtn?.classList.toggle('is-active', mode === 'hand');
    updateGeometryHint();
  };

  const setSelectedSegment = (index) => {
    if (!Array.isArray(points) || points.length < 2) {
      selectedSegmentIndex = 0;
      return;
    }

    selectedSegmentIndex = ((index % points.length) + points.length) % points.length;
    selectedPointIndex = selectedSegmentIndex;
    updateGeometryHint();
  };

  const setSelectedPoint = (index) => {
    if (!Array.isArray(points) || points.length === 0) {
      selectedPointIndex = 0;
      return;
    }

    selectedPointIndex = ((index % points.length) + points.length) % points.length;
    updateGeometryHint();
  };

  const isTypingTarget = (target) => {
    if (!(target instanceof HTMLElement)) return false;
    return target.isContentEditable || ['INPUT', 'TEXTAREA', 'SELECT', 'BUTTON'].includes(target.tagName);
  };

  const shouldStartPan = (event) => event.button === 1 || (event.button === 0 && (isSpacePressed || activeMode === 'hand'));

  const beginPan = (event) => {
    const rect = svg.getBoundingClientRect();
    panState = {
      clientX: event.clientX,
      clientY: event.clientY,
      startViewport: { ...viewport },
      rectWidth: Math.max(rect.width, 1),
      rectHeight: Math.max(rect.height, 1),
    };
    updateGeometryHint();
    event.stopPropagation();
    event.preventDefault();
  };

  const getSegmentStepMeters = () => {
    const nextStep = centimetersToMeters(segmentStepInput?.value || 0);
    return nextStep && nextStep > 0 ? nextStep : 0.05;
  };

  const updateNewElementPlacementFields = () => {
    const isWallPlacement = newElementPlacementMode?.value === 'wall';

    if (newElementX) newElementX.disabled = isWallPlacement;
    if (newElementY) newElementY.disabled = isWallPlacement;
    if (newElementSegmentIndex) newElementSegmentIndex.disabled = !isWallPlacement;
    if (newElementOffset) newElementOffset.disabled = !isWallPlacement;
  };

  const updateExistingPlacementFields = () => {
    document.querySelectorAll('[data-element-placement]').forEach((placementInput) => {
      const elementId = placementInput.getAttribute('data-element-placement');
      const isWallPlacement = placementInput.value === 'wall';
      const xInput = document.querySelector(`[data-element-x="${elementId}"]`);
      const yInput = document.querySelector(`[data-element-y="${elementId}"]`);
      const segmentInput = document.querySelector(`[data-element-segment="${elementId}"]`);
      const offsetInput = document.querySelector(`[data-element-offset="${elementId}"]`);

      if (xInput) xInput.disabled = isWallPlacement;
      if (yInput) yInput.disabled = isWallPlacement;
      if (segmentInput) segmentInput.disabled = !isWallPlacement;
      if (offsetInput) offsetInput.disabled = !isWallPlacement;
    });
  };

  const renderListLegacy = () => {
    list.innerHTML = '';
    points.forEach((point, index) => {
      const row = document.createElement('div');
      row.className = `point-row${selectedPointIndex === index ? ' is-selected' : ''}`;
      row.innerHTML = `
        <input type="number" step="1" min="0" max="${metersToCentimeters(workspaceWidth)}" class="form-control form-control-sm" value="${metersToCentimeters(point.x)}">
        <input type="number" step="1" min="0" max="${metersToCentimeters(workspaceHeight)}" class="form-control form-control-sm" value="${metersToCentimeters(point.y)}">
        <button type="button" class="btn btn-sm btn-outline-danger" ${points.length <= 3 ? 'disabled' : ''}>×</button>
      `;

      const [xInput, yInput, removeBtn] = row.querySelectorAll('input, button');
      row.addEventListener('click', (event) => {
        if (event.target.closest('button')) return;
        setSelectedPoint(index);
        render({ syncList: true, syncInput: false });
      });
      xInput.addEventListener('input', () => {
        setSelectedPoint(index);
        points[index].x = clamp(centimetersToMeters(xInput.value || 0) ?? 0, 0, workspaceWidth);
        render();
      });
      yInput.addEventListener('input', () => {
        setSelectedPoint(index);
        points[index].y = clamp(centimetersToMeters(yInput.value || 0) ?? 0, 0, workspaceHeight);
        render();
      });
      removeBtn.addEventListener('click', () => {
        if (points.length <= 3) return;
        points.splice(index, 1);
        selectedPointIndex = Math.max(0, Math.min(selectedPointIndex, points.length - 1));
        render();
      });
      list.appendChild(row);
    });
  };

  const syncSelectedInspector = () => {
    const currentPoint = points[selectedPointIndex];
    const currentSegment = getSegmentGeometry(selectedSegmentIndex);
    const angle = getPointAngle(selectedPointIndex);

    if (selectedPointLetter) selectedPointLetter.textContent = pointLabel(selectedPointIndex);
    if (selectedPointTitle) selectedPointTitle.textContent = `Угол ${pointLabel(selectedPointIndex)}`;
    if (selectedPointXInput && currentPoint) selectedPointXInput.value = metersToCentimeters(currentPoint.x);
    if (selectedPointYInput && currentPoint) selectedPointYInput.value = metersToCentimeters(currentPoint.y);
    if (selectedSegmentTitle) selectedSegmentTitle.textContent = currentSegment ? `Сторона ${segmentLabel(selectedSegmentIndex)}` : 'Сторона —';
    if (selectedSegmentLengthInput) selectedSegmentLengthInput.value = currentSegment ? metersToCentimeters(currentSegment.length) : '';
    if (selectedAngleInput) selectedAngleInput.value = angle === null ? '—' : `${String(angle).replace('.', ',')}°`;
    if (deletePointBtn) deletePointBtn.disabled = points.length <= 3;
  };

  const renderPointsList = () => {
    list.innerHTML = '';
    points.forEach((point, index) => {
      const row = document.createElement('div');
      row.className = `point-row${selectedPointIndex === index ? ' is-selected' : ''}`;
      row.innerHTML = `
        <div class="point-row-meta">
          <span class="point-row-letter">${pointLabel(index)}</span>
          <div>
            <div class="point-row-title">Угол ${pointLabel(index)}</div>
            <div class="point-row-subtitle">X/Y в сантиметрах</div>
          </div>
        </div>
        <input type="number" step="1" min="0" max="${metersToCentimeters(workspaceWidth)}" class="form-control form-control-sm" value="${metersToCentimeters(point.x)}">
        <input type="number" step="1" min="0" max="${metersToCentimeters(workspaceHeight)}" class="form-control form-control-sm" value="${metersToCentimeters(point.y)}">
        <button type="button" class="btn btn-sm btn-outline-danger" ${points.length <= 3 ? 'disabled' : ''}>x</button>
      `;

      const inputs = row.querySelectorAll('input');
      const xInput = inputs[0];
      const yInput = inputs[1];
      const removeBtn = row.querySelector('button');
      row.addEventListener('click', (event) => {
        if (event.target.closest('button')) return;
        setSelectedPoint(index);
        setInspectorTab('points');
        render({ syncList: true, syncInput: false });
      });
      xInput?.addEventListener('input', () => {
        setSelectedPoint(index);
        pushHistory();
        points[index].x = clamp(centimetersToMeters(xInput.value || 0) ?? 0, 0, workspaceWidth);
        render();
      });
      yInput?.addEventListener('input', () => {
        setSelectedPoint(index);
        pushHistory();
        points[index].y = clamp(centimetersToMeters(yInput.value || 0) ?? 0, 0, workspaceHeight);
        render();
      });
      removeBtn?.addEventListener('click', () => {
        if (points.length <= 3) return;
        pushHistory();
        points.splice(index, 1);
        selectedPointIndex = Math.max(0, Math.min(selectedPointIndex, points.length - 1));
        setSelectedSegment(selectedPointIndex);
        render();
      });
      list.appendChild(row);
    });
  };

  const renderSegmentsList = () => {
    if (!segmentsList) return;
    segmentsList.innerHTML = '';

    points.forEach((_, index) => {
      const segment = getSegmentGeometry(index);
      if (!segment) return;

      const row = document.createElement('div');
      row.className = `segment-row${selectedSegmentIndex === index ? ' is-selected' : ''}`;
      row.innerHTML = `
        <div class="segment-row-label">${segmentLabel(index)}</div>
        <input type="number" step="1" min="1" class="form-control form-control-sm" value="${metersToCentimeters(segment.length)}">
        <button type="button" class="btn btn-sm btn-outline-dark">OK</button>
      `;

      const lengthInput = row.querySelector('input');
      const applyBtn = row.querySelector('button');
      row.addEventListener('click', (event) => {
        if (event.target.closest('button') || event.target.closest('input')) return;
        setSelectedSegment(index);
        setSelectedPoint(index);
        setInspectorTab('segments');
        render({ syncList: true, syncInput: false });
      });
      const applyLength = () => {
        const nextLength = centimetersToMeters(lengthInput?.value);
        if (nextLength === null) return;
        setSelectedSegment(index);
        setSelectedPoint(index);
        pushHistory();
        setSegmentLength(index, nextLength);
        render();
      };
      applyBtn?.addEventListener('click', applyLength);
      lengthInput?.addEventListener('change', applyLength);
      segmentsList.appendChild(row);
    });
  };

  const renderAnglesList = () => {
    if (!anglesList) return;
    anglesList.innerHTML = '';

    points.forEach((_, index) => {
      const angle = getPointAngle(index);
      const row = document.createElement('div');
      row.className = `angle-row${selectedPointIndex === index ? ' is-selected' : ''}`;
      row.innerHTML = `
        <span class="angle-row-label">Угол ${pointLabel(index)}</span>
        <span>${angle === null ? '—' : `${String(angle).replace('.', ',')}°`}</span>
      `;
      row.addEventListener('click', () => {
        setSelectedPoint(index);
        setSelectedSegment(index);
        setInspectorTab('angles');
        render({ syncList: true, syncInput: false });
      });
      anglesList.appendChild(row);
    });
  };

  const renderList = () => {
    renderPointsList();
    renderSegmentsList();
    renderAnglesList();
    syncSelectedInspector();
  };

  const distanceToSegment = (point, start, end) => {
    const dx = end.x - start.x;
    const dy = end.y - start.y;
    if (dx === 0 && dy === 0) {
      return Math.hypot(point.x - start.x, point.y - start.y);
    }
    const t = clamp((((point.x - start.x) * dx) + ((point.y - start.y) * dy)) / ((dx * dx) + (dy * dy)), 0, 1);
    const projectionX = start.x + (t * dx);
    const projectionY = start.y + (t * dy);
    return Math.hypot(point.x - projectionX, point.y - projectionY);
  };

  const findInsertionIndex = (point) => {
    let bestIndex = points.length - 1;
    let bestDistance = Number.POSITIVE_INFINITY;
    points.forEach((start, index) => {
      const end = points[(index + 1) % points.length];
      const distance = distanceToSegment(point, start, end);
      if (distance < bestDistance) {
        bestDistance = distance;
        bestIndex = index;
      }
    });
    return bestIndex + 1;
  };

  const polygonCentroid = () => {
    if (!Array.isArray(points) || points.length === 0) return null;
    const sum = points.reduce((carry, point) => ({
      x: carry.x + point.x,
      y: carry.y + point.y,
    }), { x: 0, y: 0 });

    return {
      x: round(sum.x / points.length),
      y: round(sum.y / points.length),
    };
  };
  const geometryBounds = (pointSet = points, elementSet = roomElements) => {
    const freeElements = elementSet.filter((element) => (element.placement_mode ?? 'free') !== 'wall' && element.x_m !== null && element.y_m !== null);
    return [...pointSet, ...freeElements.map((element) => ({ x: Number(element.x_m), y: Number(element.y_m) }))].reduce((carry, point) => ({
      minX: Math.min(carry.minX, point.x),
      minY: Math.min(carry.minY, point.y),
      maxX: Math.max(carry.maxX, point.x),
      maxY: Math.max(carry.maxY, point.y),
    }), {
      minX: Number.POSITIVE_INFINITY,
      minY: Number.POSITIVE_INFINITY,
      maxX: Number.NEGATIVE_INFINITY,
      maxY: Number.NEGATIVE_INFINITY,
    });
  };
  const normalizeGeometry = (nextPoints, nextElements) => {
    const bounds = geometryBounds(nextPoints, nextElements);
    if (!Number.isFinite(bounds.minX) || !Number.isFinite(bounds.minY)) {
      return { points: nextPoints, elements: nextElements };
    }

    const padding = 0.35;
    let shiftX = 0;
    let shiftY = 0;

    if (bounds.minX < padding) shiftX += padding - bounds.minX;
    if (bounds.minY < padding) shiftY += padding - bounds.minY;
    if ((bounds.maxX + shiftX) > (workspaceWidth - padding)) shiftX -= (bounds.maxX + shiftX) - (workspaceWidth - padding);
    if ((bounds.maxY + shiftY) > (workspaceHeight - padding)) shiftY -= (bounds.maxY + shiftY) - (workspaceHeight - padding);

    const normalizedPoints = nextPoints.map((point) => ({
      x: round(clamp(point.x + shiftX, 0, workspaceWidth)),
      y: round(clamp(point.y + shiftY, 0, workspaceHeight)),
    }));

    const normalizedElements = nextElements.map((element) => {
      if ((element.placement_mode ?? 'free') === 'wall' || element.x_m === null || element.y_m === null) {
        return element;
      }

      return {
        ...element,
        x_m: round(clamp(Number(element.x_m) + shiftX, 0, workspaceWidth)),
        y_m: round(clamp(Number(element.y_m) + shiftY, 0, workspaceHeight)),
      };
    });

    return {
      points: normalizedPoints,
      elements: normalizedElements,
    };
  };
  const transformGeometry = (transformPoint) => {
    pushHistory();

    const nextPoints = clonePoints().map(transformPoint);
    const nextElements = cloneElements().map((element) => {
      if ((element.placement_mode ?? 'free') === 'wall' || element.x_m === null || element.y_m === null) {
        return element;
      }

      const transformed = transformPoint({
        x: Number(element.x_m),
        y: Number(element.y_m),
      });

      return {
        ...element,
        x_m: transformed.x,
        y_m: transformed.y,
      };
    });

    const normalized = normalizeGeometry(nextPoints, nextElements);
    points = normalized.points;
    roomElements.splice(0, roomElements.length, ...normalized.elements);
    syncAllElementForms();
    fitViewport();
    render({ syncList: true, syncInput: true });
  };

  const reindexWallAttachmentsOnInsert = (segmentIndex, insertedOffset) => {
    roomElements.forEach((element) => {
      if ((element.placement_mode ?? 'free') !== 'wall' || !Number.isInteger(element.segment_index)) {
        return;
      }

      if (element.segment_index > segmentIndex) {
        element.segment_index += 1;
        syncElementFormAttachment(element.id, element);
        return;
      }

      if (element.segment_index === segmentIndex && Number(element.offset_m ?? 0) > insertedOffset) {
        element.segment_index += 1;
        element.offset_m = round(Number(element.offset_m ?? 0) - insertedOffset);
        syncElementFormAttachment(element.id, element);
      }
    });
  };

  const render = ({ syncList = true, syncInput = true } = {}) => {
    if (selectedSegmentIndex >= points.length) {
      selectedSegmentIndex = 0;
    }
    if (selectedPointIndex >= points.length) {
      selectedPointIndex = 0;
    }

    if (syncInput) {
      writeInput();
    }
    if (syncList) {
      renderList();
    }
    updateExistingPlacementFields();
    updateGeometryHint();
    applyViewport(viewport);
    layer.innerHTML = '';

    const polygonStrokeWidth = pixelsToWorld(2);
    const segmentStrokeWidth = pixelsToWorld(3);
    const selectedSegmentStrokeWidth = pixelsToWorld(5);
    const segmentHitWidth = pixelsToWorld(22);
    const pointRadius = pixelsToWorld(7);
    const selectedPointRadius = pixelsToWorld(10);
    const pointHitRadius = pixelsToWorld(18);
    const labelOffset = pixelsToWorld(18);
    const labelFontSize = pixelsToWorld(13);
    const labelStrokeWidth = pixelsToWorld(3);
    const markerRadius = pixelsToWorld(8);
    const corniceMarkerRadius = pixelsToWorld(6);
    const markerHitRadius = pixelsToWorld(18);
    const roomLabelSize = pixelsToWorld(16);

    const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
    polygon.setAttribute('points', points.map((point) => `${point.x},${point.y}`).join(' '));
    polygon.setAttribute('fill', 'rgba(37, 99, 235, 0.18)');
    polygon.setAttribute('stroke', '#2563eb');
    polygon.setAttribute('stroke-width', polygonStrokeWidth);
    polygon.dataset.kind = 'polygon';
    layer.appendChild(polygon);

    points.forEach((point, index) => {
      const segment = getSegmentGeometry(index);
      if (!segment) return;

      const segmentHit = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      segmentHit.setAttribute('x1', segment.start.x);
      segmentHit.setAttribute('y1', segment.start.y);
      segmentHit.setAttribute('x2', segment.end.x);
      segmentHit.setAttribute('y2', segment.end.y);
      segmentHit.setAttribute('stroke', 'transparent');
      segmentHit.setAttribute('stroke-width', segmentHitWidth);
      segmentHit.setAttribute('stroke-linecap', 'round');
      segmentHit.dataset.kind = 'segment';
      segmentHit.dataset.segmentIndex = index;
      segmentHit.style.cursor = activeMode === 'wall' ? 'move' : (activeMode === 'element' && newElementPlacementMode?.value === 'wall' ? 'crosshair' : 'pointer');

      segmentHit.addEventListener('pointerdown', (event) => {
        if (shouldStartPan(event)) {
          beginPan(event);
          return;
        }

        if (event.button !== 0) return;
        event.stopPropagation();
        setSelectedSegment(index);
        setInspectorTab('segments');

        if (activeMode !== 'wall') {
          render({ syncList: true, syncInput: false });
          return;
        }

        pushHistory();
        dragSegmentState = {
          index,
          startPointer: pointerToSvg(event.clientX, event.clientY),
          startA: { ...segment.start },
          startB: { ...segment.end },
        };
        render({ syncList: true, syncInput: false });
      });

      segmentHit.addEventListener('click', (event) => {
        if (suppressCanvasClick) return;
        event.stopPropagation();
        if (activeMode === 'hand') return;
        setSelectedSegment(index);
        setInspectorTab(activeMode === 'element' ? inspectorTab : 'segments');

        if (activeMode === 'element' && newElementPlacementMode?.value === 'wall') {
          assignNewElementToSegment(index, pointerToSvg(event.clientX, event.clientY));
        }

        render({ syncList: true, syncInput: false });
      });

      layer.appendChild(segmentHit);

      const segmentLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      segmentLine.setAttribute('x1', segment.start.x);
      segmentLine.setAttribute('y1', segment.start.y);
      segmentLine.setAttribute('x2', segment.end.x);
      segmentLine.setAttribute('y2', segment.end.y);
      segmentLine.setAttribute('stroke', selectedSegmentIndex === index ? '#dc2626' : '#1d4ed8');
      segmentLine.setAttribute('stroke-width', selectedSegmentIndex === index ? selectedSegmentStrokeWidth : segmentStrokeWidth);
      segmentLine.setAttribute('stroke-linecap', 'round');
      segmentLine.style.pointerEvents = 'none';

      layer.appendChild(segmentLine);

      const midX = round((segment.start.x + segment.end.x) / 2);
      const midY = round((segment.start.y + segment.end.y) / 2);
      const normalX = segment.length > 0 ? (-segment.dy / segment.length) * labelOffset : 0;
      const normalY = segment.length > 0 ? (segment.dx / segment.length) * labelOffset : -labelOffset;

      const sizeLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      sizeLabel.setAttribute('x', round(midX + normalX));
      sizeLabel.setAttribute('y', round(midY + normalY));
      sizeLabel.setAttribute('fill', selectedSegmentIndex === index ? '#b91c1c' : '#0f172a');
      sizeLabel.setAttribute('font-size', labelFontSize);
      sizeLabel.setAttribute('font-weight', selectedSegmentIndex === index ? '700' : '600');
      sizeLabel.setAttribute('text-anchor', 'middle');
      sizeLabel.setAttribute('paint-order', 'stroke');
      sizeLabel.setAttribute('stroke', '#ffffff');
      sizeLabel.setAttribute('stroke-width', labelStrokeWidth);
      sizeLabel.style.pointerEvents = 'none';
      sizeLabel.textContent = `${segmentLabel(index)} ${metersToCentimeters(segment.length)} см`;
      layer.appendChild(sizeLabel);
    });

    points.forEach((point, index) => {
      const hitHandle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      hitHandle.setAttribute('cx', point.x);
      hitHandle.setAttribute('cy', point.y);
      hitHandle.setAttribute('r', pointHitRadius);
      hitHandle.setAttribute('fill', 'transparent');
      hitHandle.dataset.kind = 'point-handle';
      hitHandle.style.cursor = 'grab';

      hitHandle.addEventListener('pointerdown', (event) => {
        if (shouldStartPan(event)) {
          beginPan(event);
          return;
        }

        if (event.button !== 0) return;
        event.stopPropagation();
        setSelectedPoint(index);
        setInspectorTab('points');
        pushHistory();
        dragPointIndex = index;
      });

      hitHandle.addEventListener('dblclick', (event) => {
        event.stopPropagation();
        if (points.length <= 3) return;
        pushHistory();
        points.splice(index, 1);
        selectedPointIndex = Math.max(0, Math.min(selectedPointIndex, points.length - 1));
        render();
      });

      layer.appendChild(hitHandle);

      const handle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      handle.setAttribute('cx', point.x);
      handle.setAttribute('cy', point.y);
      handle.setAttribute('r', selectedPointIndex === index ? selectedPointRadius : pointRadius);
      handle.setAttribute('fill', selectedPointIndex === index ? '#dc2626' : '#0f172a');
      handle.setAttribute('stroke', '#ffffff');
      handle.setAttribute('stroke-width', labelStrokeWidth);
      handle.style.pointerEvents = 'none';

      layer.appendChild(handle);

      const pointText = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      pointText.setAttribute('x', round(point.x + labelOffset));
      pointText.setAttribute('y', round(point.y - labelOffset));
      pointText.setAttribute('fill', selectedPointIndex === index ? '#b91c1c' : '#0f172a');
      pointText.setAttribute('font-size', labelFontSize);
      pointText.setAttribute('font-weight', '700');
      pointText.setAttribute('paint-order', 'stroke');
      pointText.setAttribute('stroke', '#ffffff');
      pointText.setAttribute('stroke-width', labelStrokeWidth);
      pointText.style.pointerEvents = 'none';
      pointText.textContent = pointLabel(index);
      layer.appendChild(pointText);
    });

    roomElements.forEach((element, index) => {
      const geometry = resolveElementGeometry(element);
      if (!geometry) return;

      const color = elementColors[element.type] || elementColors.custom;

      if ((element.type === 'cornice' || element.type === 'curtain_niche') && Number(element.length_m || 0) > 0 && geometry.endX !== undefined && geometry.endY !== undefined) {
        const line = document.createElementNS('http://www.w3.org/2000/svg', 'line');
        line.setAttribute('x1', geometry.x);
        line.setAttribute('y1', geometry.y);
        line.setAttribute('x2', geometry.endX);
        line.setAttribute('y2', geometry.endY);
        line.setAttribute('stroke', color);
        line.setAttribute('stroke-width', pixelsToWorld(4));
        line.setAttribute('stroke-linecap', 'round');
        line.setAttribute('opacity', '0.85');
        line.dataset.kind = 'element-line';
        layer.appendChild(line);
      }

      const markerHit = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      markerHit.setAttribute('cx', geometry.x);
      markerHit.setAttribute('cy', geometry.y);
      markerHit.setAttribute('r', markerHitRadius);
      markerHit.setAttribute('fill', 'transparent');
      markerHit.dataset.kind = 'element-marker';
      markerHit.style.cursor = 'grab';

      markerHit.addEventListener('pointerdown', (event) => {
        if (shouldStartPan(event)) {
          beginPan(event);
          return;
        }

        if (event.button !== 0) return;
        event.stopPropagation();
        pushHistory();
        dragElementIndex = index;
      });

      layer.appendChild(markerHit);

      const marker = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
      marker.setAttribute('cx', geometry.x);
      marker.setAttribute('cy', geometry.y);
      marker.setAttribute('r', element.type === 'cornice' ? corniceMarkerRadius : markerRadius);
      marker.setAttribute('fill', color);
      marker.setAttribute('stroke', '#ffffff');
      marker.setAttribute('stroke-width', labelStrokeWidth);
      marker.style.pointerEvents = 'none';

      layer.appendChild(marker);

      const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      label.setAttribute('x', round(geometry.x + pixelsToWorld(12)));
      label.setAttribute('y', round(geometry.y - pixelsToWorld(14)));
      label.setAttribute('fill', color);
      label.setAttribute('font-size', labelFontSize);
      label.setAttribute('font-weight', '600');
      label.setAttribute('paint-order', 'stroke');
      label.setAttribute('stroke', '#ffffff');
      label.setAttribute('stroke-width', labelStrokeWidth);
      label.style.pointerEvents = 'none';
      const labelText = element.label && element.label.trim() !== '' ? element.label : (elementLabels[element.type] || element.type);
      label.textContent = element.quantity > 1 ? `${labelText} × ${element.quantity}` : labelText;
      layer.appendChild(label);
    });

    const centroid = polygonCentroid();
    if (centroid) {
      const roomLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      roomLabel.setAttribute('x', centroid.x);
      roomLabel.setAttribute('y', centroid.y);
      roomLabel.setAttribute('fill', '#0f172a');
      roomLabel.setAttribute('font-size', roomLabelSize);
      roomLabel.setAttribute('font-weight', '700');
      roomLabel.setAttribute('text-anchor', 'middle');
      roomLabel.setAttribute('paint-order', 'stroke');
      roomLabel.setAttribute('stroke', '#ffffff');
      roomLabel.setAttribute('stroke-width', labelStrokeWidth);
      roomLabel.style.pointerEvents = 'none';
      roomLabel.textContent = '{{ addslashes($selectedRoom->name) }}';
      layer.appendChild(roomLabel);
    }
  };

  svg.addEventListener('mousedown', (event) => {
    if (event.button === 1) {
      event.preventDefault();
    }
  });

  svg.addEventListener('pointerdown', (event) => {
    if (!shouldStartPan(event)) return;

    const tag = event.target.tagName.toLowerCase();
    const targetKind = event.target.dataset.kind || '';
    if (tag === 'svg' || tag === 'rect' || tag === 'image' || targetKind === 'polygon') {
      beginPan(event);
    }
  });

  svg.addEventListener('wheel', (event) => {
    event.preventDefault();
    zoomViewport(event.deltaY > 0 ? 1 / 1.12 : 1.12, event.clientX, event.clientY);
    render({ syncList: false, syncInput: false });
  }, { passive: false });

  svg.addEventListener('click', (event) => {
    if (suppressCanvasClick) {
      suppressCanvasClick = false;
      return;
    }

    const tag = event.target.tagName.toLowerCase();
    const targetKind = event.target.dataset.kind || '';
    if (targetKind === 'point-handle' || targetKind === 'element-marker') return;
    if (tag !== 'svg' && tag !== 'rect' && tag !== 'image' && targetKind !== 'polygon') return;

    const point = pointerToSvg(event.clientX, event.clientY);
    if (activeMode === 'hand') {
      return;
    }
      if (activeMode === 'element') {
        if (newElementPlacementMode?.value === 'wall') {
          assignNewElementToSegment(selectedSegmentIndex, point);
        } else {
        if (newElementX) newElementX.value = metersToCentimeters(point.x);
        if (newElementY) newElementY.value = metersToCentimeters(point.y);
        if (newElementSegmentIndex) newElementSegmentIndex.value = '';
        if (newElementOffset) newElementOffset.value = '';
      }
      return;
    }

    if (activeMode === 'contour') {
      pushHistory();
      const segmentIndex = Math.max(0, findInsertionIndex(point) - 1);
      const segment = getSegmentGeometry(segmentIndex);
      const insertionIndex = findInsertionIndex(point);
      const insertedPoint = applyPointSnap(point, insertionIndex);
      points.splice(insertionIndex, 0, insertedPoint);
      if (segment) {
        reindexWallAttachmentsOnInsert(segment.index, projectToSegment(insertedPoint, segment).offset);
      }
      setSelectedSegment(Math.max(0, insertionIndex - 1));
      setSelectedPoint(insertionIndex);
      render();
    }
  });

  window.addEventListener('pointermove', (event) => {
    if (panState) {
      const deltaX = event.clientX - panState.clientX;
      const deltaY = event.clientY - panState.clientY;

      if (Math.abs(deltaX) > 3 || Math.abs(deltaY) > 3) {
        suppressCanvasClick = true;
      }

      applyViewport({
        x: panState.startViewport.x - ((deltaX / panState.rectWidth) * panState.startViewport.width),
        y: panState.startViewport.y - ((deltaY / panState.rectHeight) * panState.startViewport.height),
        width: panState.startViewport.width,
        height: panState.startViewport.height,
      });
      scheduleRender({ syncList: false, syncInput: false });
      return;
    }

    if (dragPointIndex !== null) {
      setSelectedPoint(dragPointIndex);
      points[dragPointIndex] = applyPointSnap(pointerToSvg(event.clientX, event.clientY), dragPointIndex);
      scheduleRender({ syncList: false, syncInput: true });
      return;
    }

    if (dragSegmentState) {
      const pointer = pointerToSvg(event.clientX, event.clientY);
      let delta = {
        x: pointer.x - dragSegmentState.startPointer.x,
        y: pointer.y - dragSegmentState.startPointer.y,
      };

      const segment = getSegmentGeometry(dragSegmentState.index);
      if (snapEnabled && segment) {
        delta = Math.abs(segment.dx) >= Math.abs(segment.dy)
          ? { x: 0, y: delta.y }
          : { x: delta.x, y: 0 };
      }

      const currentSegment = getSegmentGeometry(dragSegmentState.index);
      if (!currentSegment) return;

      points[currentSegment.index] = {
        x: round(clamp(dragSegmentState.startA.x + delta.x, 0, workspaceWidth)),
        y: round(clamp(dragSegmentState.startA.y + delta.y, 0, workspaceHeight)),
      };
      points[currentSegment.nextIndex] = {
        x: round(clamp(dragSegmentState.startB.x + delta.x, 0, workspaceWidth)),
        y: round(clamp(dragSegmentState.startB.y + delta.y, 0, workspaceHeight)),
      };
      scheduleRender({ syncList: false, syncInput: true });
      return;
    }

    if (dragElementIndex !== null) {
      const nextPoint = pointerToSvg(event.clientX, event.clientY);
      const element = roomElements[dragElementIndex];

      if ((element.placement_mode ?? 'free') === 'wall' && Number.isInteger(element.segment_index)) {
        const segment = getSegmentGeometry(element.segment_index);
        if (!segment) return;
        const projection = projectToSegment(nextPoint, segment);
        element.offset_m = projection.offset;
        syncElementFormAttachment(element.id, element);
      } else {
        element.x_m = nextPoint.x;
        element.y_m = nextPoint.y;
        syncElementFormCoordinates(element.id, nextPoint);
      }
      scheduleRender({ syncList: false, syncInput: false });
    }
  });

  window.addEventListener('pointerup', () => {
    const shouldClearSuppression = panState !== null && suppressCanvasClick;
    dragPointIndex = null;
    dragSegmentState = null;
    dragElementIndex = null;
    panState = null;
    if (renderFrame !== null) {
      window.cancelAnimationFrame(renderFrame);
      renderFrame = null;
      scheduledRenderOptions = { syncList: false, syncInput: false };
    }
    if (shouldClearSuppression) {
      window.setTimeout(() => {
        suppressCanvasClick = false;
      }, 0);
    }
    render({ syncList: true, syncInput: true });
  });

  contourModeBtn?.addEventListener('click', () => setMode('contour'));
  wallModeBtn?.addEventListener('click', () => setMode('wall'));
  elementModeBtn?.addEventListener('click', () => setMode('element'));
  handModeBtn?.addEventListener('click', () => setMode('hand'));
  pickElementPointBtn?.addEventListener('click', () => setMode('element'));
  zoomOutBtn?.addEventListener('click', () => {
    zoomViewport(1 / 1.15);
    render({ syncList: false, syncInput: false });
  });
  zoomInBtn?.addEventListener('click', () => {
    zoomViewport(1.15);
    render({ syncList: false, syncInput: false });
  });
  zoomFitBtn?.addEventListener('click', () => {
    fitViewport();
    render({ syncList: false, syncInput: false });
  });
  backgroundToggleBtn?.addEventListener('click', () => {
    backgroundVisible = !backgroundVisible;
    syncBackgroundState();
  });
  backgroundOpacityRange?.addEventListener('input', syncBackgroundState);
  pointsTabBtn?.addEventListener('click', () => setInspectorTab('points'));
  segmentsTabBtn?.addEventListener('click', () => setInspectorTab('segments'));
  anglesTabBtn?.addEventListener('click', () => setInspectorTab('angles'));
  selectedPointXInput?.addEventListener('change', () => {
    const currentPoint = points[selectedPointIndex];
    if (!currentPoint) return;
    pushHistory();
    currentPoint.x = clamp(centimetersToMeters(selectedPointXInput.value || 0) ?? 0, 0, workspaceWidth);
    setInspectorTab('points');
    render();
  });
  selectedPointYInput?.addEventListener('change', () => {
    const currentPoint = points[selectedPointIndex];
    if (!currentPoint) return;
    pushHistory();
    currentPoint.y = clamp(centimetersToMeters(selectedPointYInput.value || 0) ?? 0, 0, workspaceHeight);
    setInspectorTab('points');
    render();
  });
  applySegmentLengthBtn?.addEventListener('click', () => {
    const nextLength = centimetersToMeters(selectedSegmentLengthInput?.value);
    if (nextLength === null) return;
    setInspectorTab('segments');
    pushHistory();
    setSegmentLength(selectedSegmentIndex, nextLength);
    render();
  });
  decreaseSegmentLengthBtn?.addEventListener('click', () => {
    const segment = getSegmentGeometry(selectedSegmentIndex);
    if (!segment) return;
    const nextLength = Math.max(0.1, round(segment.length - getSegmentStepMeters()));
    setInspectorTab('segments');
    pushHistory();
    setSegmentLength(selectedSegmentIndex, nextLength);
    render();
  });
  increaseSegmentLengthBtn?.addEventListener('click', () => {
    const segment = getSegmentGeometry(selectedSegmentIndex);
    if (!segment) return;
    const nextLength = round(segment.length + getSegmentStepMeters());
    setInspectorTab('segments');
    pushHistory();
    setSegmentLength(selectedSegmentIndex, nextLength);
    render();
  });
  prevSegmentBtn?.addEventListener('click', () => {
    setSelectedSegment(selectedSegmentIndex - 1);
    setSelectedPoint(selectedSegmentIndex);
    setInspectorTab('segments');
    render({ syncList: true, syncInput: false });
  });
  nextSegmentBtn?.addEventListener('click', () => {
    setSelectedSegment(selectedSegmentIndex + 1);
    setSelectedPoint(selectedSegmentIndex);
    setInspectorTab('segments');
    render({ syncList: true, syncInput: false });
  });
  insertPointAfterBtn?.addEventListener('click', () => {
    const segment = getSegmentGeometry(selectedSegmentIndex);
    if (!segment) return;
    pushHistory();
    points.splice(segment.index + 1, 0, {
      x: round((segment.start.x + segment.end.x) / 2),
      y: round((segment.start.y + segment.end.y) / 2),
    });
    reindexWallAttachmentsOnInsert(segment.index, round(segment.length / 2));
    setSelectedSegment(segment.index + 1);
    setSelectedPoint(segment.index + 1);
    setInspectorTab('points');
    render();
  });
  deletePointBtn?.addEventListener('click', () => {
    if (points.length <= 3) return;
    pushHistory();
    points.splice(selectedPointIndex, 1);
    selectedPointIndex = Math.max(0, Math.min(selectedPointIndex, points.length - 1));
    setSelectedSegment(selectedPointIndex);
    render();
  });

  splitSegmentBtn?.addEventListener('click', () => {
    const segment = getSegmentGeometry(selectedSegmentIndex);
    if (!segment) return;

    pushHistory();
    points.splice(segment.index + 1, 0, {
      x: round((segment.start.x + segment.end.x) / 2),
      y: round((segment.start.y + segment.end.y) / 2),
    });
    reindexWallAttachmentsOnInsert(segment.index, round(segment.length / 2));
    setSelectedSegment(segment.index + 1);
    setSelectedPoint(segment.index + 1);
    render();
  });

  snapToggleBtn?.addEventListener('click', () => {
    snapEnabled = !snapEnabled;
    snapToggleBtn.textContent = `Ортоснап: ${snapEnabled ? 'вкл' : 'выкл'}`;
  });

  newElementPlacementMode?.addEventListener('change', () => {
    updateNewElementPlacementFields();
    if (newElementPlacementMode.value === 'wall') {
      setMode('element');
    }
    updateGeometryHint();
  });

  document.querySelectorAll('[data-element-placement]').forEach((placementInput) => {
    placementInput.addEventListener('change', updateExistingPlacementFields);
  });

  resetRectBtn?.addEventListener('click', () => {
    pushHistory();
    points = baseRect.map((point) => ({ ...point }));
    setSelectedSegment(0);
    setSelectedPoint(0);
    fitViewport();
    render();
  });
  undoGeometryBtn?.addEventListener('click', undoGeometry);
  redoGeometryBtn?.addEventListener('click', redoGeometry);
  mirrorHorizontalBtn?.addEventListener('click', () => {
    const bounds = geometryBounds();
    if (!Number.isFinite(bounds.minX)) return;
    const centerX = (bounds.minX + bounds.maxX) / 2;
    transformGeometry((point) => ({
      x: round(centerX - (point.x - centerX)),
      y: round(point.y),
    }));
  });
  mirrorVerticalBtn?.addEventListener('click', () => {
    const bounds = geometryBounds();
    if (!Number.isFinite(bounds.minY)) return;
    const centerY = (bounds.minY + bounds.maxY) / 2;
    transformGeometry((point) => ({
      x: round(point.x),
      y: round(centerY - (point.y - centerY)),
    }));
  });
  rotateLeftBtn?.addEventListener('click', () => {
    const bounds = geometryBounds();
    if (!Number.isFinite(bounds.minX)) return;
    const centerX = (bounds.minX + bounds.maxX) / 2;
    const centerY = (bounds.minY + bounds.maxY) / 2;
    transformGeometry((point) => ({
      x: round(centerX - (point.y - centerY)),
      y: round(centerY + (point.x - centerX)),
    }));
  });
  rotateRightBtn?.addEventListener('click', () => {
    const bounds = geometryBounds();
    if (!Number.isFinite(bounds.minX)) return;
    const centerX = (bounds.minX + bounds.maxX) / 2;
    const centerY = (bounds.minY + bounds.maxY) / 2;
    transformGeometry((point) => ({
      x: round(centerX + (point.y - centerY)),
      y: round(centerY - (point.x - centerX)),
    }));
  });

  window.addEventListener('keydown', (event) => {
    if ((event.ctrlKey || event.metaKey) && event.code === 'KeyZ' && !event.shiftKey) {
      event.preventDefault();
      undoGeometry();
      return;
    }
    if ((event.ctrlKey || event.metaKey) && (event.code === 'KeyY' || (event.code === 'KeyZ' && event.shiftKey))) {
      event.preventDefault();
      redoGeometry();
      return;
    }
    if (!isTypingTarget(event.target)) {
      if (event.code === 'KeyH') {
        setMode('hand');
        return;
      }
      if (event.code === 'KeyV') {
        setMode('contour');
        return;
      }
      if (event.code === 'KeyW') {
        setMode('wall');
        return;
      }
      if (event.code === 'KeyE') {
        setMode('element');
        return;
      }
    }
    if (event.code !== 'Space' || isTypingTarget(event.target)) return;
    if (!isSpacePressed) {
      isSpacePressed = true;
      updateGeometryHint();
    }
    event.preventDefault();
  });

  window.addEventListener('keyup', (event) => {
    if (event.code !== 'Space') return;
    isSpacePressed = false;
    updateGeometryHint();
  });

  updateNewElementPlacementFields();
  updateExistingPlacementFields();
  syncBackgroundState();
  refreshHistoryButtons();
  setMode('contour');
  setSelectedSegment(0);
  setSelectedPoint(0);
  fitViewport();
  render();
})();
</script>
@endif
@endpush
