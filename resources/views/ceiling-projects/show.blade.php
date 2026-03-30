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
  .feature-row { display: grid; grid-template-columns: auto 1fr auto; gap: .5rem; align-items: center; border: 1px solid transparent; border-radius: .85rem; padding: .45rem; transition: background .15s ease, border-color .15s ease, box-shadow .15s ease; }
  .feature-row:hover { background: rgba(248,250,252,.9); border-color: rgba(148,163,184,.28); }
  .feature-row.is-selected { background: rgba(237,233,254,.7); border-color: rgba(124,58,237,.3); box-shadow: inset 0 0 0 1px rgba(124,58,237,.12); }
  .feature-row-dot { width: .9rem; height: .9rem; border-radius: 999px; display: inline-block; }
  .feature-row-title { font-size: .82rem; font-weight: 700; color: #0f172a; }
  .feature-row-subtitle { font-size: .72rem; color: #64748b; }
  .tool-toggle.is-active { background: #0f172a; color: #fff; border-color: #0f172a; }
  .element-chip { display: inline-flex; align-items: center; gap: .35rem; border: 1px solid rgba(15,23,42,.08); border-radius: 999px; padding: .3rem .6rem; background: rgba(248,250,252,.95); font-size: .85rem; }
  .element-chip-dot { width: .6rem; height: .6rem; border-radius: 999px; display: inline-block; }
  .guide-card { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.96)); padding: 1rem; height: 100%; }
  .guide-step { display: grid; grid-template-columns: 1.6rem 1fr; gap: .65rem; align-items: start; }
  .guide-step-index { width: 1.6rem; height: 1.6rem; border-radius: 999px; background: #0f172a; color: #fff; font-size: .82rem; display: inline-flex; align-items: center; justify-content: center; font-weight: 700; }
  .workflow-preview { width: 100%; max-height: 260px; object-fit: contain; border-radius: 1rem; border: 1px solid rgba(15,23,42,.08); background: linear-gradient(180deg, rgba(248,250,252,.98), rgba(241,245,249,.92)); }
  .workflow-note { font-size: .92rem; color: #475569; line-height: 1.45; }
  .workflow-actions { display: flex; flex-wrap: wrap; gap: .5rem; }
  .sketch-stage { position: relative; display: inline-block; width: 100%; border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; overflow: hidden; background: linear-gradient(180deg, rgba(248,250,252,.98), rgba(241,245,249,.92)); }
  .sketch-stage img { width: 100%; max-height: 560px; object-fit: contain; display: block; user-select: none; -webkit-user-drag: none; }
  .sketch-stage-overlay { position: absolute; inset: 0; cursor: crosshair; }
  .sketch-candidate-box { position: absolute; border: 2px solid rgba(37,99,235,.75); background: rgba(37,99,235,.14); border-radius: .65rem; box-shadow: inset 0 0 0 1px rgba(255,255,255,.55); transition: transform .15s ease, background .15s ease, border-color .15s ease; }
  .sketch-candidate-box:hover { transform: scale(1.01); background: rgba(37,99,235,.2); }
  .sketch-candidate-box.is-selected { border-color: rgba(16,185,129,.95); background: rgba(16,185,129,.2); }
  .sketch-candidate-label { position: absolute; top: .35rem; left: .45rem; font-size: .72rem; font-weight: 700; color: #fff; background: rgba(15,23,42,.8); border-radius: 999px; padding: .15rem .45rem; pointer-events: none; }
  .sketch-crop-box { position: absolute; border: 2px solid rgba(239,68,68,.95); background: rgba(239,68,68,.13); border-radius: .75rem; box-shadow: 0 0 0 1px rgba(255,255,255,.7) inset; pointer-events: none; }
  .sketch-crop-box.is-active { border-color: rgba(16,185,129,.98); background: rgba(16,185,129,.16); }
  .sketch-crop-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: .75rem; }
  .sketch-candidate-pills { display: flex; flex-wrap: wrap; gap: .4rem; }
  .sketch-candidate-pill.is-selected { background: #0f172a; color: #fff; border-color: #0f172a; }
  .sketch-stage-toolbar { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
  .sketch-crop-status { font-size: .85rem; color: #475569; }
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
  $selectedRoomFeatureShapesPayload = [];
  $selectedRoomLightLineShapesPayload = [];
  $selectedRoomDerivedPanelsPayload = [];
  $selectedRoomProductionSettingsPayload = [];
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
      $selectedRoomFeatureShapesPayload = collect(is_array($selectedRoom->feature_shapes) ? $selectedRoom->feature_shapes : [])
          ->map(function ($shape, $index) {
              if (!is_array($shape)) {
                  return null;
              }

              return [
                  'id' => trim((string) ($shape['id'] ?? '')) !== '' ? (string) $shape['id'] : 'feature_'.($index + 1),
                  'kind' => (string) ($shape['kind'] ?? 'cutout'),
                  'figure' => (string) ($shape['figure'] ?? 'rectangle'),
                  'x_m' => isset($shape['x_m']) ? (float) $shape['x_m'] : 0.0,
                  'y_m' => isset($shape['y_m']) ? (float) $shape['y_m'] : 0.0,
                  'width_m' => isset($shape['width_m']) ? (float) $shape['width_m'] : 0.0,
                  'height_m' => isset($shape['height_m']) ? (float) $shape['height_m'] : 0.0,
                  'shape_points' => is_array($shape['shape_points'] ?? null) ? array_values($shape['shape_points']) : null,
                  'source_segment_index' => isset($shape['source_segment_index']) ? (int) $shape['source_segment_index'] : null,
                  'source_point_index' => isset($shape['source_point_index']) ? (int) $shape['source_point_index'] : null,
                  'cut_segment_index' => isset($shape['cut_segment_index']) ? (int) $shape['cut_segment_index'] : null,
                  'offset_m' => isset($shape['offset_m']) ? (float) $shape['offset_m'] : null,
                  'cut_offset_m' => isset($shape['cut_offset_m']) ? (float) $shape['cut_offset_m'] : null,
                  'depth_m' => isset($shape['depth_m']) ? (float) $shape['depth_m'] : null,
                  'radius_m' => isset($shape['radius_m']) ? (float) $shape['radius_m'] : null,
                  'area_delta_m2' => isset($shape['area_delta_m2']) ? (float) $shape['area_delta_m2'] : null,
                  'perimeter_delta_m' => isset($shape['perimeter_delta_m']) ? (float) $shape['perimeter_delta_m'] : null,
                  'direction' => $shape['direction'] ?? null,
                  'cut_line' => (bool) ($shape['cut_line'] ?? false),
                  'separate_panel' => (bool) ($shape['separate_panel'] ?? false),
                  'label' => $shape['label'] ?? null,
              ];
          })
          ->filter()
          ->values()
          ->all();
      $selectedRoomLightLineShapesPayload = collect(is_array($selectedRoom->light_line_shapes) ? $selectedRoom->light_line_shapes : [])
          ->map(function ($shape, $index) {
              if (!is_array($shape)) {
                  return null;
              }

              return [
                  'id' => trim((string) ($shape['id'] ?? '')) !== '' ? (string) $shape['id'] : 'light_line_'.($index + 1),
                  'label' => $shape['label'] ?? null,
                  'width_m' => isset($shape['width_m']) ? (float) $shape['width_m'] : 0.05,
                  'closed' => (bool) ($shape['closed'] ?? false),
                  'template' => $shape['template'] ?? 'custom',
                  'points' => is_array($shape['points'] ?? null) ? array_values($shape['points']) : [],
              ];
          })
          ->filter()
          ->values()
          ->all();
      $selectedRoomDerivedPanelsPayload = collect(is_array($selectedRoom->derived_panels) ? $selectedRoom->derived_panels : [])
          ->map(function ($panel, $index) {
              if (!is_array($panel)) {
                  return null;
              }

              return [
                  'id' => trim((string) ($panel['id'] ?? '')) !== '' ? (string) $panel['id'] : 'panel_'.($index + 1),
                  'label' => $panel['label'] ?? 'Полотно '.($index + 1),
                  'area_m2' => isset($panel['area_m2']) ? (float) $panel['area_m2'] : 0.0,
                  'cells_count' => isset($panel['cells_count']) ? (int) $panel['cells_count'] : 0,
                  'centroid' => is_array($panel['centroid'] ?? null) ? $panel['centroid'] : null,
                  'bounds' => is_array($panel['bounds'] ?? null) ? $panel['bounds'] : null,
                  'shape_points' => is_array($panel['shape_points'] ?? null) ? array_values($panel['shape_points']) : null,
                  'source' => isset($panel['source']) ? (string) $panel['source'] : null,
                  'source_shape_id' => isset($panel['source_shape_id']) ? (string) $panel['source_shape_id'] : null,
                  'feature_kind' => isset($panel['feature_kind']) ? (string) $panel['feature_kind'] : null,
                  'production' => is_array($panel['production'] ?? null) ? $panel['production'] : [],
              ];
          })
          ->filter()
          ->values()
          ->all();
      $selectedRoomProductionSettingsPayload = array_merge([
          'texture' => 'matte',
          'roll_width_cm' => 320,
          'harpoon_type' => 'standard',
          'same_roll_required' => false,
          'special_cutting' => false,
          'seam_enabled' => false,
          'shrink_x_percent' => 7,
          'shrink_y_percent' => 7,
          'orientation_mode' => 'parallel_segment',
          'orientation_segment_index' => 0,
          'orientation_offset_m' => 0,
          'seam_offset_m' => 0,
          'comment' => null,
      ], is_array($selectedRoom->production_settings) ? $selectedRoom->production_settings : []);
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
      $featureMaxX = collect($selectedRoomFeatureShapesPayload)->max(fn ($shape) => (float) (($shape['x_m'] ?? 0) + ($shape['width_m'] ?? 0))) ?: 0;
      $featureMaxY = collect($selectedRoomFeatureShapesPayload)->max(fn ($shape) => (float) (($shape['y_m'] ?? 0) + ($shape['height_m'] ?? 0))) ?: 0;
      $lightLineMaxX = collect($selectedRoomLightLineShapesPayload)
          ->flatMap(fn ($shape) => collect($shape['points'] ?? [])->pluck('x'))
          ->max() ?: 0;
      $lightLineMaxY = collect($selectedRoomLightLineShapesPayload)
          ->flatMap(fn ($shape) => collect($shape['points'] ?? [])->pluck('y'))
          ->max() ?: 0;
      $editorWidth = max(6.0, ceil(max($maxX + 1, $elementMaxX + 1, $featureMaxX + 1, $lightLineMaxX + 1, (float) ($selectedRoom->width_m ?? 0) + 1)));
      $editorHeight = max(4.0, ceil(max($maxY + 1, $elementMaxY + 1, $featureMaxY + 1, $lightLineMaxY + 1, (float) ($selectedRoom->length_m ?? 0) + 1)));
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
  $sketchSegments = collect($sketchRecognition['segments'] ?? [])
      ->filter(fn ($segment) => is_array($segment))
      ->values();
  $sketchWarnings = collect($sketchRecognition['warnings'] ?? [])->filter();
  $sketchStage = (string) ($sketchRecognition['stage'] ?? 'recognize');
  $sketchCandidates = collect($sketchRecognition['candidates'] ?? [])
      ->filter(fn ($candidate) => is_array($candidate))
      ->values();
  $sketchCrop = is_array($sketchCrop ?? null) ? $sketchCrop : null;
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
    <div class="col-xl col-md-4 col-sm-6"><div class="metric-card"><div class="metric-label">Полотно</div><div class="metric-value">{{ $formatDecimal($summary['totals']['recommended_canvas_area_m2'], 'м2') }}</div></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="metric-card"><div class="metric-label">Профиль</div><div class="metric-value">{{ $formatDecimal($summary['totals']['recommended_profile_m'], 'м') }}</div></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="metric-card"><div class="metric-label">Комнаты</div><div class="metric-value">{{ $summary['totals']['rooms_count'] }}</div></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="metric-card"><div class="metric-label">Полотна</div><div class="metric-value">{{ $summary['totals']['light_line_panels_count'] }}</div></div></div>
    <div class="col-xl col-md-4 col-sm-6"><div class="metric-card"><div class="metric-label">Смета</div><div class="metric-value">{{ $formatMoney($summary['estimate']['grand_total']) }}</div></div></div>
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
          @error('sketch_crop')
            <div class="alert alert-warning mt-3 mb-0">{{ $message }}</div>
          @enderror
          @error('sketch_recognition')
            <div class="alert alert-warning mt-3 mb-0">{{ $message }}</div>
          @enderror

          @if($sketchImageUrl)
            <div class="mt-3 border rounded p-3 bg-light-subtle">
              <div class="fw-semibold mb-2">2. Выберите одну комнату на листе</div>
              <div class="small text-muted mb-3">Можно кликнуть по автокандидату или просто протянуть мышкой свою область. OCR пойдёт только по выделенной зоне.</div>

              <div class="sketch-stage mb-3" id="sketchCropStage">
                <img src="{{ $sketchImageUrl }}" alt="Лист замера" id="sketchCropImage">
                <div class="sketch-stage-overlay" id="sketchCropOverlay"></div>
                <div class="sketch-crop-box {{ $sketchCrop ? 'is-active' : '' }}" id="sketchCropBox" hidden></div>
              </div>

              <div class="sketch-stage-toolbar mb-3">
                <button type="button" class="btn btn-sm btn-outline-secondary" id="sketchClearCropBtn">Сбросить область</button>
                <span class="sketch-crop-status" id="sketchCropStatus">
                  @if($sketchCrop)
                    Область выбрана.
                  @else
                    Область пока не выбрана.
                  @endif
                </span>
              </div>

              @if($sketchCandidates->isNotEmpty())
                <div class="mb-3">
                  <div class="small fw-semibold mb-2">Автокандидаты комнат</div>
                  <div class="sketch-candidate-pills" id="sketchCandidatePills">
                    @foreach($sketchCandidates as $candidate)
                      <button type="button" class="btn btn-sm btn-outline-secondary sketch-candidate-pill" data-candidate-index="{{ $loop->index }}">
                        Кандидат {{ $loop->iteration }}
                        @if(isset($candidate['score']))
                          · {{ number_format((float) $candidate['score'], 2, ',', ' ') }}
                        @endif
                      </button>
                    @endforeach
                  </div>
                </div>
              @endif

              <div class="workflow-actions">
                <form method="POST" action="{{ route('ceiling-projects.sketch-crop.update', $project) }}" id="sketchCropForm">
                  @csrf
                  <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                  @if($selectedRoom)
                    <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
                  @endif
                  <input type="hidden" name="crop_x" class="sketch-crop-input" value="{{ $sketchCrop['x'] ?? '' }}">
                  <input type="hidden" name="crop_y" class="sketch-crop-input" value="{{ $sketchCrop['y'] ?? '' }}">
                  <input type="hidden" name="crop_width" class="sketch-crop-input" value="{{ $sketchCrop['width'] ?? '' }}">
                  <input type="hidden" name="crop_height" class="sketch-crop-input" value="{{ $sketchCrop['height'] ?? '' }}">
                  <button class="btn btn-outline-secondary" id="saveSketchCropBtn" @disabled(!$sketchCrop)>Сохранить область OCR</button>
                </form>

                <form method="POST" action="{{ route('ceiling-projects.sketch-recognition', $project) }}" id="sketchRecognitionAreaForm">
                  @csrf
                  <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                  @if($selectedRoom)
                    <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
                  @endif
                  <input type="hidden" name="crop_x" class="sketch-crop-input" value="{{ $sketchCrop['x'] ?? '' }}">
                  <input type="hidden" name="crop_y" class="sketch-crop-input" value="{{ $sketchCrop['y'] ?? '' }}">
                  <input type="hidden" name="crop_width" class="sketch-crop-input" value="{{ $sketchCrop['width'] ?? '' }}">
                  <input type="hidden" name="crop_height" class="sketch-crop-input" value="{{ $sketchCrop['height'] ?? '' }}">
                  <button class="btn btn-primary" id="runSketchRecognitionBtn" @disabled(!$sketchCrop)>Распознать выбранную область</button>
                </form>
              </div>
            </div>
          @endif

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
                  @if($sketchSegments->isNotEmpty())
                    <div class="mt-3">
                      <div class="small fw-semibold mb-2">Стороны и размеры OCR</div>
                      <div class="row g-2">
                        @foreach($sketchSegments as $segment)
                          @php
                            $segmentOcrValue = isset($segment['ocr_value_cm']) && is_numeric($segment['ocr_value_cm']) ? (int) round((float) $segment['ocr_value_cm']) : null;
                            $segmentDraftValue = isset($segment['resolved_value_cm']) && is_numeric($segment['resolved_value_cm'])
                                ? (int) round((float) $segment['resolved_value_cm'])
                                : (isset($segment['approx_value_cm']) && is_numeric($segment['approx_value_cm']) ? (int) round((float) $segment['approx_value_cm']) : null);
                            $segmentConfidence = isset($segment['confidence']) && is_numeric($segment['confidence']) ? (float) $segment['confidence'] : null;
                          @endphp
                          <div class="col-md-6">
                            <div class="border rounded p-2 h-100 bg-white small">
                              <div class="d-flex justify-content-between align-items-center gap-2">
                                <div class="fw-semibold">{{ $segment['label'] ?? 'сторона' }}</div>
                                <span class="badge text-bg-light">{{ ($segment['orientation'] ?? 'horizontal') === 'vertical' ? 'вертикаль' : 'горизонталь' }}</span>
                              </div>
                              <div class="mt-2">
                                @if($segmentOcrValue !== null)
                                  <div><b>OCR:</b> {{ number_format($segmentOcrValue, 0, ',', ' ') }} см</div>
                                @endif
                                @if($segmentDraftValue !== null)
                                  <div class="text-muted"><b>Черновик:</b> {{ number_format($segmentDraftValue, 0, ',', ' ') }} см</div>
                                @endif
                                @if($segmentConfidence !== null)
                                  <div class="text-muted"><b>Уверенность:</b> {{ number_format($segmentConfidence, 2, ',', ' ') }}</div>
                                @endif
                              </div>
                            </div>
                          </div>
                        @endforeach
                      </div>
                    </div>
                  @endif
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
                @php($roomPanels = is_array($room->derived_panels) ? array_values($room->derived_panels) : [])
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
                    <div class="col-md-4"><b>Полотна:</b> {{ $metrics['light_line_panels_count'] }}</div>
                    <div class="col-md-4"><b>Ниши:</b> {{ $metrics['curtain_niches_count'] }}</div>
                    <div class="col-md-4"><b>Карнизы:</b> {{ $metrics['cornices_count'] }} / {{ $formatCentimeters($metrics['cornice_length_m']) }}</div>
                    <div class="col-md-4"><b>Трубы:</b> {{ $metrics['pipes_count'] }}</div>
                  </div>
                  @if(count($roomPanels) > 0)
                    <div class="mt-3 d-flex flex-wrap gap-2">
                      @foreach(array_slice($roomPanels, 0, 3) as $panel)
                        <span class="element-chip">
                          <span class="element-chip-dot" style="background:#059669"></span>
                          {{ $panel['label'] ?? ('Полотно '.($loop->iteration)) }} · {{ $formatDecimal((float) ($panel['area_m2'] ?? 0), 'м2') }}
                        </span>
                      @endforeach
                      @if(count($roomPanels) > 3)
                        <span class="element-chip">+{{ count($roomPanels) - 3 }}</span>
                      @endif
                    </div>
                  @endif
                  <div class="mt-3 d-flex justify-content-between gap-2 flex-wrap">
                    <div class="d-flex gap-2 flex-wrap">
                      <a href="{{ route('ceiling-projects.show', ['project' => $project, 'room' => $room->id]) }}#geometry-editor" class="btn btn-sm btn-outline-primary">Открыть чертеж комнаты</a>
                      <a href="{{ route('ceiling-projects.rooms.panels.show', [$project, $room]) }}" class="btn btn-sm btn-outline-dark">Полотна комнаты</a>
                    </div>
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
                  <form method="POST" action="{{ route('ceiling-projects.rooms.geometry.update', [$project, $selectedRoom]) }}" id="geometryEditorForm">
                    @csrf
                    @method('PATCH')
                    <input type="hidden" name="view_mode" value="{{ $viewMode }}">
                    <input type="hidden" name="room" value="{{ $selectedRoom->id }}">
                    <input type="hidden" name="shape_points_json" id="shapePointsInput" value='@json($selectedRoomPoints)'>
                    <input type="hidden" name="feature_shapes_json" id="featureShapesInput" value='@json($selectedRoomFeatureShapesPayload)'>
                    <input type="hidden" name="light_line_shapes_json" id="lightLineShapesInput" value='@json($selectedRoomLightLineShapesPayload)'>
                    <input type="hidden" name="production_settings_json" id="productionSettingsInput" value='@json($selectedRoomProductionSettingsPayload)'>
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
                    <div class="inspector-card mb-3">
                      <div class="inspector-kicker">Параметрические операции</div>
                      <div class="row g-2">
                        <div class="col-6">
                          <label class="form-label small mb-1">Новая точка X, см</label>
                          <input type="number" step="1" class="form-control form-control-sm" id="manualPointXInput">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Новая точка Y, см</label>
                          <input type="number" step="1" class="form-control form-control-sm" id="manualPointYInput">
                        </div>
                        <div class="col-7">
                          <label class="form-label small mb-1">Отступ от начала стороны, см</label>
                          <input type="number" step="1" min="0" class="form-control form-control-sm" id="insertPointOffsetInput">
                        </div>
                        <div class="col-5 d-flex align-items-end">
                          <button type="button" class="btn btn-sm btn-outline-dark w-100" id="insertPointAtOffsetBtn">Точка на стороне</button>
                        </div>
                        <div class="col-12">
                          <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="insertPointByCoordinatesBtn">Поставить точку по X/Y</button>
                        </div>
                        <div class="col-7">
                          <label class="form-label small mb-1">Сдвиг стены, см</label>
                          <input type="number" step="1" min="0" class="form-control form-control-sm" id="wallShiftOffsetInput" value="10">
                        </div>
                        <div class="col-5 d-flex align-items-end">
                          <button type="button" class="btn btn-sm btn-outline-dark w-100" id="applyWallShiftBtn">Создать сдвиг</button>
                        </div>
                        <div class="col-6">
                          <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="wallShiftInwardBtn">Сдвиг внутрь</button>
                        </div>
                        <div class="col-6">
                          <button type="button" class="btn btn-sm btn-outline-secondary w-100" id="wallShiftOutwardBtn">Уровень наружу</button>
                        </div>
                      </div>
                      <div class="small text-muted mt-2">Точку можно поставить по X/Y или по выбранной стороне от ее начала. Ручной режим двигает саму сторону, а кнопки выше создают независимый сдвиг или уровень как отдельную форму без изменения соседних стен.</div>
                    </div>
                    <div class="inspector-card mb-3">
                      <div class="inspector-kicker">Дополнительные формы</div>
                      <div class="row g-2">
                        <div class="col-6">
                          <label class="form-label small mb-1">Тип</label>
                          <select class="form-select form-select-sm" id="featureKindInput">
                            @foreach($featureKindOptions as $value => $label)
                              <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                          </select>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Фигура</label>
                          <select class="form-select form-select-sm" id="featureFigureInput">
                            @foreach($featureFigureOptions as $value => $label)
                              <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                          </select>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">X, см</label>
                          <input type="number" step="1" min="0" class="form-control form-control-sm" id="featureXInput">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Y, см</label>
                          <input type="number" step="1" min="0" class="form-control form-control-sm" id="featureYInput">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Ширина, см</label>
                          <input type="number" step="1" min="1" class="form-control form-control-sm" id="featureWidthInput" value="60">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Высота, см</label>
                          <input type="number" step="1" min="1" class="form-control form-control-sm" id="featureHeightInput" value="60">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Радиус, см</label>
                          <input type="number" step="1" min="1" class="form-control form-control-sm" id="featureRadiusInput" value="25">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Отступ по стороне, см</label>
                          <input type="number" step="1" min="0" class="form-control form-control-sm" id="featureWallOffsetInput" value="30">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Глубина, см</label>
                          <input type="number" step="1" min="1" class="form-control form-control-sm" id="featureDepthInput" value="40">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Направление</label>
                          <select class="form-select form-select-sm" id="featureDirectionInput">
                            <option value="inward">Внутрь</option>
                            <option value="outward">Наружу</option>
                          </select>
                        </div>
                        <div class="col-6 d-flex align-items-end">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="featureCutLineInput">
                            <label class="form-check-label small" for="featureCutLineInput">Разрез к вырезу</label>
                          </div>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Разрез от стороны</label>
                          <select class="form-select form-select-sm" id="featureCutSegmentInput"></select>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Отступ разреза, см</label>
                          <input type="number" step="1" min="0" class="form-control form-control-sm" id="featureCutOffsetInput" value="30">
                        </div>
                        <div class="col-6 d-flex align-items-end">
                          <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="featureSeparatePanelInput">
                            <label class="form-check-label small" for="featureSeparatePanelInput">Отдельное полотно</label>
                          </div>
                        </div>
                        <div class="col-12">
                          <label class="form-label small mb-1">Подпись</label>
                          <input type="text" class="form-control form-control-sm" id="featureLabelInput" placeholder="Напр.: внутренний вырез">
                        </div>
                        <div class="col-12 d-grid gap-2">
                          <button type="button" class="btn btn-sm btn-outline-dark" id="addFeatureShapeBtn">Добавить быструю форму</button>
                          <button type="button" class="btn btn-sm btn-outline-primary" id="addFeatureFromWallBtn">Построить от выбранной стороны</button>
                          <button type="button" class="btn btn-sm btn-outline-primary" id="roundCornerFeatureBtn">Скруглить угол</button>
                          <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="updateFeatureShapeBtn">Обновить выбранную</button>
                            <button type="button" class="btn btn-sm btn-outline-danger flex-fill" id="deleteFeatureShapeBtn">Удалить форму</button>
                          </div>
                        </div>
                      </div>
                      <div class="small text-muted mt-2">Быстрые формы подходят для внутреннего выреза, уровня, сдвига и отдельного полотна. Для сложной формы можно начать многоугольник, а для отдельного production-полотна включить флаг выше.</div>
                      <div class="inspector-stack mt-3" id="featureShapesList"></div>
                      <div class="d-grid gap-2 mt-3">
                        <button type="button" class="btn btn-sm btn-outline-primary" id="startPolygonFeatureBtn">Начать многоугольник</button>
                        <div class="d-flex gap-2">
                          <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="finishPolygonFeatureBtn">Завершить</button>
                          <button type="button" class="btn btn-sm btn-outline-danger flex-fill" id="cancelPolygonFeatureBtn">Сбросить</button>
                        </div>
                      </div>
                    </div>
                    <div class="inspector-card mb-3">
                      <div class="inspector-kicker">Световые линии</div>
                      <div class="row g-2">
                        <div class="col-6">
                          <label class="form-label small mb-1">Подпись</label>
                          <input type="text" class="form-control form-control-sm" id="lightLineLabelInput" placeholder="Напр.: центр">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Профиль, см</label>
                          <input type="number" step="1" min="1" class="form-control form-control-sm" id="lightLineWidthInput" value="5">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Шаблон</label>
                          <select class="form-select form-select-sm" id="lightLineTemplateInput">
                            <option value="circle">Круг</option>
                            <option value="custom">Произвольная</option>
                            <option value="rectangle">Прямоугольник</option>
                            <option value="cross">Перекрестие</option>
                            <option value="star">Звезда</option>
                          </select>
                        </div>
                        <div class="col-3">
                          <label class="form-label small mb-1">Ширина, см</label>
                          <input type="number" step="1" min="1" class="form-control form-control-sm" id="lightLineTemplateWidthInput" value="120">
                        </div>
                        <div class="col-3">
                          <label class="form-label small mb-1">Высота, см</label>
                          <input type="number" step="1" min="1" class="form-control form-control-sm" id="lightLineTemplateHeightInput" value="60">
                        </div>
                        <div class="col-12 d-grid gap-2">
                          <button type="button" class="btn btn-sm btn-outline-primary" id="startLightLineBtn">Начать линию</button>
                          <button type="button" class="btn btn-sm btn-outline-dark" id="addLightLineTemplateBtn">Добавить шаблон</button>
                          <div class="d-flex gap-2">
                            <button type="button" class="btn btn-sm btn-outline-secondary flex-fill" id="toggleLightLineClosedBtn">Замкнуть / разомкнуть</button>
                            <button type="button" class="btn btn-sm btn-outline-danger flex-fill" id="deleteLightLineBtn">Удалить конструкцию</button>
                          </div>
                        </div>
                      </div>
                      <div class="small text-muted mt-2">Световые линии рисуются как отдельные конструкции. Клик по холсту добавляет вершины, двойной клик завершает построение, а перетаскивание самой конструкции двигает её целиком.</div>
                      <div class="inspector-stack mt-3" id="lightLinesList"></div>
                    </div>
                    <div class="inspector-card mb-3">
                      <div class="inspector-kicker">Производство полотна</div>
                      <div class="row g-2">
                        <div class="col-6">
                          <label class="form-label small mb-1">Фактура</label>
                          <select class="form-select form-select-sm" id="productionTextureInput">
                            <option value="matte">Матовый</option>
                            <option value="satin">Сатин</option>
                            <option value="glossy">Глянцевый</option>
                            <option value="fabric">Ткань</option>
                            <option value="custom">Другое</option>
                          </select>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Ширина рулона, см</label>
                          <input type="number" step="1" min="50" class="form-control form-control-sm" id="productionRollWidthInput" value="320">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Гарпун</label>
                          <select class="form-select form-select-sm" id="productionHarpoonInput">
                            <option value="standard">Стандарт</option>
                            <option value="separate">Раздельный</option>
                            <option value="none">Без гарпуна</option>
                          </select>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Ориентация</label>
                          <select class="form-select form-select-sm" id="productionOrientationModeInput">
                            <option value="parallel_segment">Параллельно стороне</option>
                            <option value="perpendicular_segment">Перпендикулярно стороне</option>
                            <option value="center_segment">По центру стороны</option>
                            <option value="center_room">По центру помещения</option>
                          </select>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Сторона-ориентир</label>
                          <select class="form-select form-select-sm" id="productionOrientationSegmentInput"></select>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Смещение, см</label>
                          <input type="number" step="1" class="form-control form-control-sm" id="productionOrientationOffsetInput" value="0">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Усадка X, %</label>
                          <input type="number" step="0.1" class="form-control form-control-sm" id="productionShrinkXInput" value="7">
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Усадка Y, %</label>
                          <input type="number" step="0.1" class="form-control form-control-sm" id="productionShrinkYInput" value="7">
                        </div>
                        <div class="col-6">
                          <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="productionSameRollInput">
                            <label class="form-check-label small" for="productionSameRollInput">Кроить из одного рулона</label>
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="productionSpecialCuttingInput">
                            <label class="form-check-label small" for="productionSpecialCuttingInput">Спецраскрой</label>
                          </div>
                        </div>
                        <div class="col-6">
                          <div class="form-check mt-2">
                            <input class="form-check-input" type="checkbox" id="productionSeamEnabledInput">
                            <label class="form-check-label small" for="productionSeamEnabledInput">Шов</label>
                          </div>
                        </div>
                        <div class="col-6">
                          <label class="form-label small mb-1">Смещение шва, см</label>
                          <input type="number" step="1" class="form-control form-control-sm" id="productionSeamOffsetInput" value="0">
                        </div>
                        <div class="col-12">
                          <label class="form-label small mb-1">Комментарий для производства</label>
                          <textarea class="form-control form-control-sm" rows="3" id="productionCommentInput" placeholder="Напр.: два полотна со швом, спецраскрой, обязательно один рулон"></textarea>
                        </div>
                      </div>
                      <div class="small text-muted mt-2" id="productionSummaryText">Параметры полотна сохраняются вместе с геометрией комнаты.</div>
                    </div>
                    <div class="inspector-card mb-3">
                      <div class="inspector-kicker">Полотна от световых линий</div>
                      <div class="small text-muted mb-2" id="lightLinePanelsSummary">Предпросмотр появится после построения световых линий.</div>
                      <div class="inspector-stack" id="lightLinePanelsList"></div>
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
<script>
(() => {
  const stage = document.getElementById('sketchCropStage');
  const image = document.getElementById('sketchCropImage');
  const overlay = document.getElementById('sketchCropOverlay');
  const cropBox = document.getElementById('sketchCropBox');
  const clearCropBtn = document.getElementById('sketchClearCropBtn');
  const cropStatus = document.getElementById('sketchCropStatus');
  const saveCropBtn = document.getElementById('saveSketchCropBtn');
  const runRecognitionBtn = document.getElementById('runSketchRecognitionBtn');
  const candidatePills = document.getElementById('sketchCandidatePills');

  const uploadBtn = document.querySelector('form[action*="/sketch-image"] button');
  if (uploadBtn) {
    uploadBtn.textContent = 'Загрузить лист замера';
  }

  document.querySelectorAll('form[action*="/sketch-recognition"]').forEach((form) => {
    if (form.id === 'sketchRecognitionAreaForm' || form.action.includes('/apply')) {
      return;
    }

    form.style.display = 'none';
  });

  if (!stage || !image || !overlay || !cropBox) {
    return;
  }

  const legacyPreview = stage.closest('.card-body')?.querySelector('img.workflow-preview');
  if (legacyPreview) {
    legacyPreview.style.display = 'none';
  }

  const candidates = @json($sketchCandidates->all());
  let crop = @json($sketchCrop);
  let dragging = false;
  let dragStart = null;

  const normalizeCrop = (value) => {
    if (!value || typeof value !== 'object') {
      return null;
    }

    const x = Number(value.x);
    const y = Number(value.y);
    const width = Number(value.width);
    const height = Number(value.height);

    if (!Number.isFinite(x) || !Number.isFinite(y) || !Number.isFinite(width) || !Number.isFinite(height)) {
      return null;
    }

    const normalized = {
      x: Math.max(0, Math.min(0.98, x)),
      y: Math.max(0, Math.min(0.98, y)),
      width: Math.max(0, Math.min(1, width)),
      height: Math.max(0, Math.min(1, height)),
    };

    normalized.width = Math.min(normalized.width, 1 - normalized.x);
    normalized.height = Math.min(normalized.height, 1 - normalized.y);

    if (normalized.width < 0.02 || normalized.height < 0.02) {
      return null;
    }

    return normalized;
  };

  const syncInputs = () => {
    document.querySelectorAll('input.sketch-crop-input').forEach((input) => {
      const key = input.name.replace('crop_', '');
      input.value = crop ? String(crop[key] ?? '') : '';
    });
  };

  const syncButtons = () => {
    const disabled = !crop;
    if (saveCropBtn) {
      saveCropBtn.disabled = disabled;
    }
    if (runRecognitionBtn) {
      runRecognitionBtn.disabled = disabled;
    }
  };

  const syncCandidatePills = () => {
    if (!candidatePills) {
      return;
    }

    candidatePills.querySelectorAll('[data-candidate-index]').forEach((button) => {
      const index = Number(button.getAttribute('data-candidate-index'));
      const candidate = normalizeCrop(candidates[index]);
      const isSelected = crop && candidate
        && Math.abs(candidate.x - crop.x) < 0.0001
        && Math.abs(candidate.y - crop.y) < 0.0001
        && Math.abs(candidate.width - crop.width) < 0.0001
        && Math.abs(candidate.height - crop.height) < 0.0001;

      button.classList.toggle('is-selected', Boolean(isSelected));
    });
  };

  const renderCrop = () => {
    crop = normalizeCrop(crop);
    syncInputs();
    syncButtons();
    syncCandidatePills();

    if (!crop) {
      cropBox.hidden = true;
      cropBox.classList.remove('is-active');
      if (cropStatus) {
        cropStatus.textContent = 'Область пока не выбрана.';
      }
      return;
    }

    cropBox.hidden = false;
    cropBox.classList.add('is-active');
    cropBox.style.left = `${crop.x * 100}%`;
    cropBox.style.top = `${crop.y * 100}%`;
    cropBox.style.width = `${crop.width * 100}%`;
    cropBox.style.height = `${crop.height * 100}%`;

    if (cropStatus) {
      cropStatus.textContent = `Область: ${Math.round(crop.width * 100)}% x ${Math.round(crop.height * 100)}%, смещение ${Math.round(crop.x * 100)}% / ${Math.round(crop.y * 100)}%`;
    }
  };

  const renderCandidates = () => {
    overlay.querySelectorAll('.sketch-candidate-box').forEach((node) => node.remove());

    candidates.forEach((candidate, index) => {
      const normalized = normalizeCrop(candidate);
      if (!normalized) {
        return;
      }

      const box = document.createElement('button');
      box.type = 'button';
      box.className = 'sketch-candidate-box';
      box.style.left = `${normalized.x * 100}%`;
      box.style.top = `${normalized.y * 100}%`;
      box.style.width = `${normalized.width * 100}%`;
      box.style.height = `${normalized.height * 100}%`;
      box.innerHTML = `<span class="sketch-candidate-label">#${index + 1}</span>`;
      box.addEventListener('click', (event) => {
        event.preventDefault();
        crop = normalized;
        renderCrop();
      });
      overlay.appendChild(box);
    });
  };

  const pointerPosition = (event) => {
    const rect = overlay.getBoundingClientRect();
    return {
      x: Math.max(0, Math.min(1, (event.clientX - rect.left) / rect.width)),
      y: Math.max(0, Math.min(1, (event.clientY - rect.top) / rect.height)),
    };
  };

  const cropFromPoints = (from, to) => normalizeCrop({
    x: Math.min(from.x, to.x),
    y: Math.min(from.y, to.y),
    width: Math.abs(to.x - from.x),
    height: Math.abs(to.y - from.y),
  });

  overlay.addEventListener('pointerdown', (event) => {
    if (event.target.closest('.sketch-candidate-box')) {
      return;
    }

    dragStart = pointerPosition(event);
    crop = { x: dragStart.x, y: dragStart.y, width: 0, height: 0 };
    dragging = true;
    overlay.setPointerCapture?.(event.pointerId);
    renderCrop();
  });

  overlay.addEventListener('pointermove', (event) => {
    if (!dragging || !dragStart) {
      return;
    }

    crop = cropFromPoints(dragStart, pointerPosition(event));
    renderCrop();
  });

  overlay.addEventListener('pointerup', (event) => {
    if (!dragging || !dragStart) {
      return;
    }

    crop = cropFromPoints(dragStart, pointerPosition(event));
    dragging = false;
    dragStart = null;
    renderCrop();
  });

  clearCropBtn?.addEventListener('click', () => {
    crop = null;
    renderCrop();
  });

  candidatePills?.addEventListener('click', (event) => {
    const button = event.target.closest('[data-candidate-index]');
    if (!button) {
      return;
    }

    const candidate = normalizeCrop(candidates[Number(button.getAttribute('data-candidate-index'))]);
    if (!candidate) {
      return;
    }

    crop = candidate;
    renderCrop();
  });

  if (!crop && candidates.length === 1) {
    crop = normalizeCrop(candidates[0]);
  }

  renderCandidates();
  renderCrop();
})();
</script>
@if($selectedRoom)
<script>
(() => {
  const geometryStage = document.getElementById('geometryStage');
  const svg = document.getElementById('geometrySvg');
  const layer = document.getElementById('geometryLayer');
  const geometryEditorForm = document.getElementById('geometryEditorForm');
  const input = document.getElementById('shapePointsInput');
  const featureShapesInput = document.getElementById('featureShapesInput');
  const lightLineShapesInput = document.getElementById('lightLineShapesInput');
  const productionSettingsInput = document.getElementById('productionSettingsInput');
  const list = document.getElementById('pointsList');
  const segmentsList = document.getElementById('segmentsList');
  const anglesList = document.getElementById('anglesList');
  const featureShapesList = document.getElementById('featureShapesList');
  const lightLinesList = document.getElementById('lightLinesList');
  const lightLinePanelsList = document.getElementById('lightLinePanelsList');
  const lightLinePanelsSummary = document.getElementById('lightLinePanelsSummary');
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
  const manualPointXInput = document.getElementById('manualPointXInput');
  const manualPointYInput = document.getElementById('manualPointYInput');
  const insertPointOffsetInput = document.getElementById('insertPointOffsetInput');
  const insertPointAtOffsetBtn = document.getElementById('insertPointAtOffsetBtn');
  const insertPointByCoordinatesBtn = document.getElementById('insertPointByCoordinatesBtn');
  const wallShiftOffsetInput = document.getElementById('wallShiftOffsetInput');
  const applyWallShiftBtn = document.getElementById('applyWallShiftBtn');
  const wallShiftInwardBtn = document.getElementById('wallShiftInwardBtn');
  const wallShiftOutwardBtn = document.getElementById('wallShiftOutwardBtn');
  const featureKindInput = document.getElementById('featureKindInput');
  const featureFigureInput = document.getElementById('featureFigureInput');
  const featureXInput = document.getElementById('featureXInput');
  const featureYInput = document.getElementById('featureYInput');
  const featureWidthInput = document.getElementById('featureWidthInput');
  const featureHeightInput = document.getElementById('featureHeightInput');
  const featureRadiusInput = document.getElementById('featureRadiusInput');
  const featureWallOffsetInput = document.getElementById('featureWallOffsetInput');
  const featureDepthInput = document.getElementById('featureDepthInput');
  const featureDirectionInput = document.getElementById('featureDirectionInput');
  const featureCutLineInput = document.getElementById('featureCutLineInput');
  const featureCutSegmentInput = document.getElementById('featureCutSegmentInput');
  const featureCutOffsetInput = document.getElementById('featureCutOffsetInput');
  const featureSeparatePanelInput = document.getElementById('featureSeparatePanelInput');
  const featureLabelInput = document.getElementById('featureLabelInput');
  const addFeatureShapeBtn = document.getElementById('addFeatureShapeBtn');
  const addFeatureFromWallBtn = document.getElementById('addFeatureFromWallBtn');
  const roundCornerFeatureBtn = document.getElementById('roundCornerFeatureBtn');
  const startPolygonFeatureBtn = document.getElementById('startPolygonFeatureBtn');
  const finishPolygonFeatureBtn = document.getElementById('finishPolygonFeatureBtn');
  const cancelPolygonFeatureBtn = document.getElementById('cancelPolygonFeatureBtn');
  const updateFeatureShapeBtn = document.getElementById('updateFeatureShapeBtn');
  const deleteFeatureShapeBtn = document.getElementById('deleteFeatureShapeBtn');
  const lightLineLabelInput = document.getElementById('lightLineLabelInput');
  const lightLineWidthInput = document.getElementById('lightLineWidthInput');
  const lightLineTemplateInput = document.getElementById('lightLineTemplateInput');
  const lightLineTemplateWidthInput = document.getElementById('lightLineTemplateWidthInput');
  const lightLineTemplateHeightInput = document.getElementById('lightLineTemplateHeightInput');
  const startLightLineBtn = document.getElementById('startLightLineBtn');
  const addLightLineTemplateBtn = document.getElementById('addLightLineTemplateBtn');
  const toggleLightLineClosedBtn = document.getElementById('toggleLightLineClosedBtn');
  const deleteLightLineBtn = document.getElementById('deleteLightLineBtn');
  const productionTextureInput = document.getElementById('productionTextureInput');
  const productionRollWidthInput = document.getElementById('productionRollWidthInput');
  const productionHarpoonInput = document.getElementById('productionHarpoonInput');
  const productionOrientationModeInput = document.getElementById('productionOrientationModeInput');
  const productionOrientationSegmentInput = document.getElementById('productionOrientationSegmentInput');
  const productionOrientationOffsetInput = document.getElementById('productionOrientationOffsetInput');
  const productionShrinkXInput = document.getElementById('productionShrinkXInput');
  const productionShrinkYInput = document.getElementById('productionShrinkYInput');
  const productionSameRollInput = document.getElementById('productionSameRollInput');
  const productionSpecialCuttingInput = document.getElementById('productionSpecialCuttingInput');
  const productionSeamEnabledInput = document.getElementById('productionSeamEnabledInput');
  const productionSeamOffsetInput = document.getElementById('productionSeamOffsetInput');
  const productionCommentInput = document.getElementById('productionCommentInput');
  const productionSummaryText = document.getElementById('productionSummaryText');
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
  const initialFeatureShapes = @json($selectedRoomFeatureShapesPayload);
  const initialLightLineShapes = @json($selectedRoomLightLineShapesPayload);
  const initialDerivedPanels = @json($selectedRoomDerivedPanelsPayload);
  const initialProductionSettings = @json($selectedRoomProductionSettingsPayload);
  const elementColors = {
    spotlight: '#f59e0b',
    chandelier: '#06b6d4',
    pipe: '#6b7280',
    curtain_niche: '#16a34a',
    ventilation: '#2563eb',
    cornice: '#0f172a',
    custom: '#9333ea',
  };
  const featureKindLabels = @json($featureKindOptions);
  const featureFigureLabels = @json($featureFigureOptions);
  const featureKindColors = {
    cutout: '#dc2626',
    level: '#0891b2',
    shift: '#d97706',
  };
  const lightLineColor = '#f97316';
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

  const normalizeFeatureShape = (shape, index = 0) => {
    if (!shape || typeof shape !== 'object') return null;
    const shapePoints = Array.isArray(shape.shape_points)
      ? shape.shape_points
          .map((point) => ({
            x: Number(point?.x ?? NaN),
            y: Number(point?.y ?? NaN),
          }))
          .filter((point) => Number.isFinite(point.x) && Number.isFinite(point.y))
          .map((point) => ({ x: round(point.x), y: round(point.y) }))
      : [];
    let x = Number(shape.x_m ?? 0);
    let y = Number(shape.y_m ?? 0);
    let width = Number(shape.width_m ?? 0);
    let height = Number(shape.height_m ?? 0);

    if (shapePoints.length >= 3) {
      const xs = shapePoints.map((point) => point.x);
      const ys = shapePoints.map((point) => point.y);
      x = Math.min(...xs);
      y = Math.min(...ys);
      width = Math.max(...xs) - x;
      height = Math.max(...ys) - y;
    }

    if (!Number.isFinite(x) || !Number.isFinite(y) || !Number.isFinite(width) || !Number.isFinite(height) || width <= 0 || height <= 0) {
      return null;
    }

    return {
      id: String(shape.id ?? `feature_${index + 1}`),
      kind: String(shape.kind ?? 'cutout'),
      figure: String(shape.figure ?? 'rectangle'),
      x_m: round(x),
      y_m: round(y),
      width_m: round(width),
      height_m: round(height),
      shape_points: shapePoints.length >= 3 ? shapePoints : null,
      source_segment_index: Number.isInteger(shape.source_segment_index) ? shape.source_segment_index : (Number.isFinite(Number(shape.source_segment_index)) ? Number(shape.source_segment_index) : null),
      source_point_index: Number.isInteger(shape.source_point_index) ? shape.source_point_index : (Number.isFinite(Number(shape.source_point_index)) ? Number(shape.source_point_index) : null),
      cut_segment_index: Number.isInteger(shape.cut_segment_index) ? shape.cut_segment_index : (Number.isFinite(Number(shape.cut_segment_index)) ? Number(shape.cut_segment_index) : null),
      offset_m: Number.isFinite(Number(shape.offset_m)) ? round(Number(shape.offset_m)) : null,
      cut_offset_m: Number.isFinite(Number(shape.cut_offset_m)) ? round(Number(shape.cut_offset_m)) : null,
      depth_m: Number.isFinite(Number(shape.depth_m)) ? round(Number(shape.depth_m)) : null,
      radius_m: Number.isFinite(Number(shape.radius_m)) ? round(Number(shape.radius_m)) : null,
      area_delta_m2: Number.isFinite(Number(shape.area_delta_m2)) ? round(Number(shape.area_delta_m2)) : null,
      perimeter_delta_m: Number.isFinite(Number(shape.perimeter_delta_m)) ? round(Number(shape.perimeter_delta_m)) : null,
      direction: shape.direction === 'outward' ? 'outward' : 'inward',
      cut_line: Boolean(shape.cut_line),
      separate_panel: Boolean(shape.separate_panel),
      label: shape.label ? String(shape.label) : '',
    };
  };

  const featureShapes = (Array.isArray(initialFeatureShapes) ? initialFeatureShapes : [])
    .map((shape, index) => normalizeFeatureShape(shape, index))
    .filter(Boolean);
  const normalizeLightLineShape = (shape, index = 0) => {
    if (!shape || typeof shape !== 'object') return null;

    const points = Array.isArray(shape.points)
      ? shape.points
          .map((point) => ({
            x: Number(point?.x ?? NaN),
            y: Number(point?.y ?? NaN),
          }))
          .filter((point) => Number.isFinite(point.x) && Number.isFinite(point.y))
          .map((point) => ({ x: round(point.x), y: round(point.y) }))
      : [];

    if (points.length < 2) {
      return null;
    }

    return {
      id: String(shape.id ?? `light_line_${index + 1}`),
      label: shape.label ? String(shape.label) : '',
      width_m: Math.max(0.01, round(Number(shape.width_m ?? 0.05))),
      closed: Boolean(shape.closed),
      template: ['custom', 'rectangle', 'cross', 'circle', 'star'].includes(String(shape.template ?? 'custom')) ? String(shape.template ?? 'custom') : 'custom',
      points,
    };
  };
  const lightLineShapes = (Array.isArray(initialLightLineShapes) ? initialLightLineShapes : [])
    .map((shape, index) => normalizeLightLineShape(shape, index))
    .filter(Boolean);
  const normalizeDerivedPanel = (panel, index = 0) => {
    if (!panel || typeof panel !== 'object') return null;

    const area = Number(panel.area_m2 ?? 0);
    const centroid = panel.centroid && Number.isFinite(Number(panel.centroid.x)) && Number.isFinite(Number(panel.centroid.y))
      ? { x: round(Number(panel.centroid.x)), y: round(Number(panel.centroid.y)) }
      : null;
    const shapePoints = Array.isArray(panel.shape_points)
      ? panel.shape_points
          .map((point) => ({
            x: Number(point?.x ?? NaN),
            y: Number(point?.y ?? NaN),
          }))
          .filter((point) => Number.isFinite(point.x) && Number.isFinite(point.y))
          .map((point) => ({ x: round(point.x), y: round(point.y) }))
      : [];

    if (!Number.isFinite(area) || area <= 0) {
      return null;
    }

    return {
      id: String(panel.id ?? `panel_${index + 1}`),
      label: panel.label ? String(panel.label) : `Полотно ${index + 1}`,
      area_m2: round(area),
      cells_count: Number.isFinite(Number(panel.cells_count)) ? Number(panel.cells_count) : 0,
      centroid,
      bounds: panel.bounds && typeof panel.bounds === 'object' ? panel.bounds : null,
      shape_points: shapePoints.length >= 3 ? shapePoints : null,
      source: panel.source ? String(panel.source) : null,
      source_shape_id: panel.source_shape_id ? String(panel.source_shape_id) : null,
      feature_kind: panel.feature_kind ? String(panel.feature_kind) : null,
      seam_parent_id: panel.seam_parent_id ? String(panel.seam_parent_id) : null,
      seam_part_index: Number.isFinite(Number(panel.seam_part_index)) ? Number(panel.seam_part_index) : null,
      production: panel.production && typeof panel.production === 'object' ? panel.production : {},
    };
  };
  const persistedDerivedPanels = (Array.isArray(initialDerivedPanels) ? initialDerivedPanels : [])
    .map((panel, index) => normalizeDerivedPanel(panel, index))
    .filter(Boolean);
  const productionSettings = {
    texture: ['matte', 'satin', 'glossy', 'fabric', 'custom'].includes(String(initialProductionSettings?.texture ?? 'matte')) ? String(initialProductionSettings?.texture ?? 'matte') : 'matte',
    roll_width_cm: Math.max(50, Number(initialProductionSettings?.roll_width_cm ?? 320) || 320),
    harpoon_type: ['standard', 'separate', 'none'].includes(String(initialProductionSettings?.harpoon_type ?? 'standard')) ? String(initialProductionSettings?.harpoon_type ?? 'standard') : 'standard',
    same_roll_required: Boolean(initialProductionSettings?.same_roll_required),
    special_cutting: Boolean(initialProductionSettings?.special_cutting),
    seam_enabled: Boolean(initialProductionSettings?.seam_enabled),
    shrink_x_percent: Number.isFinite(Number(initialProductionSettings?.shrink_x_percent)) ? round(Number(initialProductionSettings?.shrink_x_percent)) : 7,
    shrink_y_percent: Number.isFinite(Number(initialProductionSettings?.shrink_y_percent)) ? round(Number(initialProductionSettings?.shrink_y_percent)) : 7,
    orientation_mode: ['parallel_segment', 'perpendicular_segment', 'center_segment', 'center_room'].includes(String(initialProductionSettings?.orientation_mode ?? 'parallel_segment')) ? String(initialProductionSettings?.orientation_mode ?? 'parallel_segment') : 'parallel_segment',
    orientation_segment_index: Number.isFinite(Number(initialProductionSettings?.orientation_segment_index)) ? Math.max(0, Number(initialProductionSettings?.orientation_segment_index)) : 0,
    orientation_offset_m: Number.isFinite(Number(initialProductionSettings?.orientation_offset_m)) ? round(Number(initialProductionSettings?.orientation_offset_m)) : 0,
    seam_offset_m: Number.isFinite(Number(initialProductionSettings?.seam_offset_m)) ? round(Number(initialProductionSettings?.seam_offset_m)) : 0,
    comment: typeof initialProductionSettings?.comment === 'string' ? initialProductionSettings.comment : '',
  };

  let activeMode = 'contour';
  let selectedSegmentIndex = 0;
  let selectedPointIndex = 0;
  let selectedFeatureIndex = featureShapes.length > 0 ? 0 : -1;
  let selectedLightLineIndex = lightLineShapes.length > 0 ? 0 : -1;
  let dragPointIndex = null;
  let dragSegmentState = null;
  let dragElementIndex = null;
  let dragFeatureState = null;
  let dragLightLinePointState = null;
  let dragLightLineShapeState = null;
  let featurePolygonDraft = null;
  let lightLineDraft = null;
  let lightLinePanelsPreview = persistedDerivedPanels;
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
  const viewportAllowance = Math.max(workspaceWidth, workspaceHeight) * 0.45;

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
  const cloneFeatureShapes = (items = featureShapes) => items.map((shape, index) => normalizeFeatureShape(shape, index)).filter(Boolean);
  const cloneLightLineShapes = (items = lightLineShapes) => items.map((shape, index) => normalizeLightLineShape(shape, index)).filter(Boolean);
  const captureState = () => ({
    points: clonePoints(),
    roomElements: cloneElements(),
    featureShapes: cloneFeatureShapes(),
    lightLineShapes: cloneLightLineShapes(),
    featurePolygonDraft: featurePolygonDraft ? clonePoints(featurePolygonDraft) : null,
    lightLineDraft: lightLineDraft ? clonePoints(lightLineDraft) : null,
    selectedSegmentIndex,
    selectedPointIndex,
    selectedFeatureIndex,
    selectedLightLineIndex,
    viewport: { ...viewport },
  });
  const restoreState = (state) => {
    points = clonePoints(state.points ?? []);
    const nextElements = cloneElements(state.roomElements ?? []);
    roomElements.splice(0, roomElements.length, ...nextElements);
    const nextFeatureShapes = cloneFeatureShapes(state.featureShapes ?? []);
    featureShapes.splice(0, featureShapes.length, ...nextFeatureShapes);
    const nextLightLineShapes = cloneLightLineShapes(state.lightLineShapes ?? []);
    lightLineShapes.splice(0, lightLineShapes.length, ...nextLightLineShapes);
    featurePolygonDraft = Array.isArray(state.featurePolygonDraft) && state.featurePolygonDraft.length > 0
      ? clonePoints(state.featurePolygonDraft)
      : null;
    lightLineDraft = Array.isArray(state.lightLineDraft) && state.lightLineDraft.length > 0
      ? clonePoints(state.lightLineDraft)
      : null;
    selectedSegmentIndex = Math.max(0, Math.min(Number(state.selectedSegmentIndex ?? 0), Math.max(points.length - 1, 0)));
    selectedPointIndex = Math.max(0, Math.min(Number(state.selectedPointIndex ?? 0), Math.max(points.length - 1, 0)));
    selectedFeatureIndex = nextFeatureShapes.length === 0
      ? -1
      : Math.max(0, Math.min(Number(state.selectedFeatureIndex ?? 0), nextFeatureShapes.length - 1));
    selectedLightLineIndex = nextLightLineShapes.length === 0
      ? -1
      : Math.max(0, Math.min(Number(state.selectedLightLineIndex ?? 0), nextLightLineShapes.length - 1));
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

    const minX = -viewportAllowance;
    const minY = -viewportAllowance;
    const maxX = workspaceWidth + viewportAllowance - normalized.width;
    const maxY = workspaceHeight + viewportAllowance - normalized.height;

    normalized.x = clamp(normalized.x, Math.min(minX, maxX), Math.max(minX, maxX));
    normalized.y = clamp(normalized.y, Math.min(minY, maxY), Math.max(minY, maxY));

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

  const fitViewport = (paddingMeters = 1.2) => {
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
    if (featureShapesInput) {
      featureShapesInput.value = JSON.stringify(featureShapes);
    }
    if (lightLineShapesInput) {
      lightLineShapesInput.value = JSON.stringify(lightLineShapes);
    }
    if (productionSettingsInput) {
      productionSettingsInput.value = JSON.stringify(productionSettings);
    }
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

  const getPolygonSignedArea = () => {
    if (!Array.isArray(points) || points.length < 3) return 0;
    let area = 0;
    points.forEach((point, index) => {
      const next = points[(index + 1) % points.length];
      area += (point.x * next.y) - (next.x * point.y);
    });
    return area / 2;
  };

  const getSegmentNormal = (segmentIndex, inward = true) => {
    const segment = getSegmentGeometry(segmentIndex);
    if (!segment || !segment.length) return null;

    const polygonIsCcw = getPolygonSignedArea() > 0;
    const leftNormal = { x: round(-segment.dy / segment.length), y: round(segment.dx / segment.length) };
    const rightNormal = { x: round(segment.dy / segment.length), y: round(-segment.dx / segment.length) };
    const inwardNormal = polygonIsCcw ? leftNormal : rightNormal;
    const outwardNormal = polygonIsCcw ? rightNormal : leftNormal;

    return inward ? inwardNormal : outwardNormal;
  };

  const getWallShiftValueMeters = () => {
    const value = centimetersToMeters(wallShiftOffsetInput?.value || 0);
    return value && value > 0 ? value : null;
  };

  const distanceBetweenPoints = (a, b) => Math.hypot((Number(b?.x ?? 0) - Number(a?.x ?? 0)), (Number(b?.y ?? 0) - Number(a?.y ?? 0)));

  const normalizeVector = (vector) => {
    const length = Math.hypot(Number(vector?.x ?? 0), Number(vector?.y ?? 0));
    if (!Number.isFinite(length) || length <= 0) {
      return null;
    }

    return {
      x: vector.x / length,
      y: vector.y / length,
    };
  };

  const polygonArea = (pointSet) => {
    if (!Array.isArray(pointSet) || pointSet.length < 3) return 0;
    let area = 0;
    pointSet.forEach((point, index) => {
      const next = pointSet[(index + 1) % pointSet.length];
      area += (Number(point.x ?? 0) * Number(next.y ?? 0)) - (Number(next.x ?? 0) * Number(point.y ?? 0));
    });
    return Math.abs(area / 2);
  };

  const isPointInsidePolygon = (point, polygonPoints = points) => {
    if (!Array.isArray(polygonPoints) || polygonPoints.length < 3) {
      return false;
    }

    let inside = false;
    for (let index = 0, previous = polygonPoints.length - 1; index < polygonPoints.length; previous = index, index += 1) {
      const current = polygonPoints[index];
      const previousPoint = polygonPoints[previous];
      const denominator = previousPoint.y - current.y;
      const safeDenominator = Math.abs(denominator) < 0.000001
        ? (denominator >= 0 ? 0.000001 : -0.000001)
        : denominator;
      const intersect = ((current.y > point.y) !== (previousPoint.y > point.y))
        && (point.x < (((previousPoint.x - current.x) * (point.y - current.y)) / safeDenominator) + current.x);
      if (intersect) {
        inside = !inside;
      }
    }

    return inside;
  };

  const polylineLength = (pointSet, closed = false) => {
    if (!Array.isArray(pointSet) || pointSet.length < 2) return 0;

    let total = 0;
    for (let index = 1; index < pointSet.length; index += 1) {
      total += distanceBetweenPoints(pointSet[index - 1], pointSet[index]);
    }

    if (closed && pointSet.length > 2) {
      total += distanceBetweenPoints(pointSet[pointSet.length - 1], pointSet[0]);
    }

    return total;
  };

  const distanceToPolyline = (point, pointSet, closed = false) => {
    if (!Array.isArray(pointSet) || pointSet.length < 2) {
      return Number.POSITIVE_INFINITY;
    }

    let best = Number.POSITIVE_INFINITY;
    for (let index = 0; index < pointSet.length - 1; index += 1) {
      best = Math.min(best, distanceToSegment(point, pointSet[index], pointSet[index + 1]));
    }

    if (closed && pointSet.length > 2) {
      best = Math.min(best, distanceToSegment(point, pointSet[pointSet.length - 1], pointSet[0]));
    }

    return best;
  };

  const featureAreaSign = (kind) => ['cutout', 'shift'].includes(kind) ? -1 : 1;

  const buildArcPolyline = (startPoint, endPoint, normal, depth, steps = 14) => {
    const pointsForArc = [];

    for (let step = 0; step <= steps; step += 1) {
      const t = step / steps;
      const baseX = startPoint.x + ((endPoint.x - startPoint.x) * t);
      const baseY = startPoint.y + ((endPoint.y - startPoint.y) * t);
      const bulge = Math.sin(Math.PI * t) * depth;
      pointsForArc.push({
        x: round(baseX + (normal.x * bulge)),
        y: round(baseY + (normal.y * bulge)),
      });
    }

    return pointsForArc;
  };

  const insertPointOnSegmentAtOffset = (segmentIndex, offsetMeters) => {
    const anchor = pointAlongSegment(segmentIndex, offsetMeters);
    if (!anchor) return;

    pushHistory();
    const insertIndex = anchor.segment.index + 1;
    points.splice(insertIndex, 0, {
      x: round(anchor.x),
      y: round(anchor.y),
    });
    reindexWallAttachmentsOnInsert(anchor.segment.index, round(offsetMeters));
    setSelectedSegment(anchor.segment.index);
    setSelectedPoint(insertIndex);
    setInspectorTab('points');
    render();
  };

  const insertPointByCoordinates = (xMeters, yMeters) => {
    if (!Number.isFinite(xMeters) || !Number.isFinite(yMeters)) return;

    pushHistory();
    const point = {
      x: round(clamp(xMeters, 0, workspaceWidth)),
      y: round(clamp(yMeters, 0, workspaceHeight)),
    };
    const insertionIndex = findInsertionIndex(point);
    const segmentIndex = Math.max(0, insertionIndex - 1);
    const segment = getSegmentGeometry(segmentIndex);
    points.splice(insertionIndex, 0, point);
    if (segment) {
      reindexWallAttachmentsOnInsert(segment.index, projectToSegment(point, segment).offset);
    }
    setSelectedSegment(segmentIndex);
    setSelectedPoint(insertionIndex);
    setInspectorTab('points');
    render();
  };

  const shiftSegmentByOffset = (segmentIndex, offsetMeters, inward = true) => {
    const segment = getSegmentGeometry(segmentIndex);
    const normal = getSegmentNormal(segmentIndex, inward);
    if (!segment || !normal || !Number.isFinite(offsetMeters) || offsetMeters <= 0) return;

    pushHistory();
    points[segment.index] = {
      x: round(clamp(segment.start.x + (normal.x * offsetMeters), 0, workspaceWidth)),
      y: round(clamp(segment.start.y + (normal.y * offsetMeters), 0, workspaceHeight)),
    };
    points[segment.nextIndex] = {
      x: round(clamp(segment.end.x + (normal.x * offsetMeters), 0, workspaceWidth)),
      y: round(clamp(segment.end.y + (normal.y * offsetMeters), 0, workspaceHeight)),
    };
    setSelectedSegment(segmentIndex);
    setSelectedPoint(segmentIndex);
    setInspectorTab('segments');
    render();
  };

  const buildWallOffsetFeature = (offsetMeters, inward = true) => {
    const segment = getSegmentGeometry(selectedSegmentIndex);
    const normal = getSegmentNormal(selectedSegmentIndex, inward);
    if (!segment || !normal || !Number.isFinite(offsetMeters) || offsetMeters <= 0) {
      return null;
    }

    const start = {
      x: round(segment.start.x),
      y: round(segment.start.y),
    };
    const end = {
      x: round(segment.end.x),
      y: round(segment.end.y),
    };
    const shiftedEnd = {
      x: round(clamp(segment.end.x + (normal.x * offsetMeters), 0, workspaceWidth)),
      y: round(clamp(segment.end.y + (normal.y * offsetMeters), 0, workspaceHeight)),
    };
    const shiftedStart = {
      x: round(clamp(segment.start.x + (normal.x * offsetMeters), 0, workspaceWidth)),
      y: round(clamp(segment.start.y + (normal.y * offsetMeters), 0, workspaceHeight)),
    };
    const kind = inward ? 'shift' : 'level';

    return normalizeFeatureShape({
      id: `feature_${Date.now()}`,
      kind,
      figure: 'rectangle',
      shape_points: [start, end, shiftedEnd, shiftedStart],
      x_m: Math.min(start.x, end.x, shiftedEnd.x, shiftedStart.x),
      y_m: Math.min(start.y, end.y, shiftedEnd.y, shiftedStart.y),
      width_m: Math.max(start.x, end.x, shiftedEnd.x, shiftedStart.x) - Math.min(start.x, end.x, shiftedEnd.x, shiftedStart.x),
      height_m: Math.max(start.y, end.y, shiftedEnd.y, shiftedStart.y) - Math.min(start.y, end.y, shiftedEnd.y, shiftedStart.y),
      source_segment_index: selectedSegmentIndex,
      offset_m: round(segment.length / 2),
      depth_m: round(offsetMeters),
      direction: inward ? 'inward' : 'outward',
      cut_line: false,
      separate_panel: false,
      label: featureLabelInput?.value ?? (inward ? `Сдвиг ${segmentLabel(selectedSegmentIndex)}` : `Уровень ${segmentLabel(selectedSegmentIndex)}`),
    });
  };

  const buildFeatureFromSelectedSegment = () => {
    const segment = getSegmentGeometry(selectedSegmentIndex);
    if (!segment) return null;

    const offset = centimetersToMeters(featureWallOffsetInput?.value);
    const span = centimetersToMeters(featureWidthInput?.value);
    const depth = centimetersToMeters(featureDepthInput?.value);
    const direction = featureDirectionInput?.value === 'outward' ? 'outward' : 'inward';
    const inward = direction === 'inward';
    const normal = getSegmentNormal(selectedSegmentIndex, inward);
    const kind = featureKindInput?.value ?? 'cutout';
    const cutLine = Boolean(featureCutLineInput?.checked) && kind === 'cutout';
    const selectedCutSegment = Number.isFinite(Number(featureCutSegmentInput?.value))
      ? Number(featureCutSegmentInput.value)
      : selectedSegmentIndex;
    const cutSegment = getSegmentGeometry(selectedCutSegment);
    const requestedCutOffset = centimetersToMeters(featureCutOffsetInput?.value);
    if (!normal || offset === null || span === null || depth === null || span <= 0 || depth <= 0) {
      return null;
    }

    const clampedOffset = clamp(offset, 0, segment.length);
    const clampedSpan = clamp(span, 0.05, Math.max(segment.length - clampedOffset, 0.05));
    const startAnchor = pointAlongSegment(selectedSegmentIndex, clampedOffset);
    const endAnchor = pointAlongSegment(selectedSegmentIndex, clampedOffset + clampedSpan);
    if (!startAnchor || !endAnchor) {
      return null;
    }

    const p1 = { x: startAnchor.x, y: startAnchor.y };
    const p2 = { x: endAnchor.x, y: endAnchor.y };
    const p3 = { x: round(p2.x + (normal.x * depth)), y: round(p2.y + (normal.y * depth)) };
    const p4 = { x: round(p1.x + (normal.x * depth)), y: round(p1.y + (normal.y * depth)) };
    const midBase = { x: round((p1.x + p2.x) / 2), y: round((p1.y + p2.y) / 2) };
    const center = { x: round((p1.x + p2.x + p3.x + p4.x) / 4), y: round((p1.y + p2.y + p3.y + p4.y) / 4) };

    const figure = featureFigureInput?.value ?? 'rectangle';
    let payload;
    let areaDelta = null;
    let perimeterDelta = null;

    if (figure === 'triangle') {
      payload = {
        shape_points: [p1, p2, { x: round(midBase.x + (normal.x * depth)), y: round(midBase.y + (normal.y * depth)) }],
      };
    } else if (figure === 'circle') {
      payload = {
        x_m: round(center.x - (clampedSpan / 2)),
        y_m: round(center.y - (depth / 2)),
        width_m: round(clampedSpan),
        height_m: round(depth),
      };
    } else if (figure === 'arc') {
      const arcPoints = buildArcPolyline(p1, p2, normal, depth);
      payload = {
        shape_points: arcPoints,
      };

      const area = polygonArea(arcPoints);
      const arcLength = polylineLength(arcPoints);
      const chordLength = distanceBetweenPoints(p1, p2);
      areaDelta = round(area * featureAreaSign(kind));
      perimeterDelta = round(arcLength - chordLength);
    } else {
      payload = {
        shape_points: [p1, p2, p3, p4],
      };
    }

    return normalizeFeatureShape({
      id: `feature_${Date.now()}`,
      kind,
      figure,
      ...payload,
      x_m: payload.x_m ?? Math.min(p1.x, p2.x, p3.x, p4.x),
      y_m: payload.y_m ?? Math.min(p1.y, p2.y, p3.y, p4.y),
      width_m: payload.width_m ?? (Math.max(p1.x, p2.x, p3.x, p4.x) - Math.min(p1.x, p2.x, p3.x, p4.x)),
      height_m: payload.height_m ?? (Math.max(p1.y, p2.y, p3.y, p4.y) - Math.min(p1.y, p2.y, p3.y, p4.y)),
      source_segment_index: selectedSegmentIndex,
      cut_segment_index: cutLine && cutSegment ? selectedCutSegment : null,
      offset_m: round(clampedOffset),
      cut_offset_m: cutLine
        ? round(clamp(
            requestedCutOffset === null ? clampedOffset : requestedCutOffset,
            0,
            cutSegment?.length ?? segment.length
          ))
        : null,
      depth_m: round(depth),
      area_delta_m2: areaDelta,
      perimeter_delta_m: perimeterDelta,
      direction,
      cut_line: cutLine,
      separate_panel: Boolean(featureSeparatePanelInput?.checked),
      label: featureLabelInput?.value ?? '',
    });
  };

  const buildRoundedCornerFeature = () => {
    if (!Array.isArray(points) || points.length < 3) return null;

    const cornerIndex = ((selectedPointIndex % points.length) + points.length) % points.length;
    const current = points[cornerIndex];
    const previous = points[(cornerIndex - 1 + points.length) % points.length];
    const next = points[(cornerIndex + 1) % points.length];
    const radius = centimetersToMeters(featureRadiusInput?.value);
    const kind = featureKindInput?.value ?? 'cutout';

    if (!current || !previous || !next || radius === null || radius <= 0) {
      return null;
    }

    const vectorToPrevious = normalizeVector({
      x: previous.x - current.x,
      y: previous.y - current.y,
    });
    const vectorToNext = normalizeVector({
      x: next.x - current.x,
      y: next.y - current.y,
    });

    if (!vectorToPrevious || !vectorToNext) {
      return null;
    }

    const rawDot = clamp((vectorToPrevious.x * vectorToNext.x) + (vectorToPrevious.y * vectorToNext.y), -1, 1);
    const angle = Math.acos(rawDot);
    if (!Number.isFinite(angle) || angle <= 0.2 || angle >= (Math.PI - 0.05)) {
      return null;
    }

    const tangentDistance = radius / Math.tan(angle / 2);
    const maxDistance = Math.min(distanceBetweenPoints(current, previous), distanceBetweenPoints(current, next)) - 0.02;
    if (!Number.isFinite(maxDistance) || maxDistance <= 0.02) {
      return null;
    }

    const safeDistance = clamp(tangentDistance, 0.03, maxDistance);
    const safeRadius = round(safeDistance * Math.tan(angle / 2));
    const bisector = normalizeVector({
      x: vectorToPrevious.x + vectorToNext.x,
      y: vectorToPrevious.y + vectorToNext.y,
    });

    if (!bisector) {
      return null;
    }

    const centerDistance = safeRadius / Math.sin(angle / 2);
    const tangentStart = {
      x: round(current.x + (vectorToPrevious.x * safeDistance)),
      y: round(current.y + (vectorToPrevious.y * safeDistance)),
    };
    const tangentEnd = {
      x: round(current.x + (vectorToNext.x * safeDistance)),
      y: round(current.y + (vectorToNext.y * safeDistance)),
    };
    const centerPoint = {
      x: round(current.x + (bisector.x * centerDistance)),
      y: round(current.y + (bisector.y * centerDistance)),
    };

    let startAngle = Math.atan2(tangentStart.y - centerPoint.y, tangentStart.x - centerPoint.x);
    let endAngle = Math.atan2(tangentEnd.y - centerPoint.y, tangentEnd.x - centerPoint.x);
    let delta = endAngle - startAngle;
    while (delta <= -Math.PI) delta += Math.PI * 2;
    while (delta > Math.PI) delta -= Math.PI * 2;

    const steps = 12;
    const arcPoints = [current, tangentStart];
    for (let step = 1; step < steps; step += 1) {
      const nextAngle = startAngle + ((delta * step) / steps);
      arcPoints.push({
        x: round(centerPoint.x + (Math.cos(nextAngle) * safeRadius)),
        y: round(centerPoint.y + (Math.sin(nextAngle) * safeRadius)),
      });
    }
    arcPoints.push(tangentEnd);

    const area = polygonArea(arcPoints);
    const curvePoints = arcPoints.slice(1);
    const curveLength = polylineLength(curvePoints);
    const removedStraight = distanceBetweenPoints(current, tangentStart) + distanceBetweenPoints(current, tangentEnd);

    return normalizeFeatureShape({
      id: `feature_${Date.now()}`,
      kind,
      figure: 'rounded_corner',
      shape_points: arcPoints,
      x_m: Math.min(...arcPoints.map((point) => point.x)),
      y_m: Math.min(...arcPoints.map((point) => point.y)),
      width_m: Math.max(...arcPoints.map((point) => point.x)) - Math.min(...arcPoints.map((point) => point.x)),
      height_m: Math.max(...arcPoints.map((point) => point.y)) - Math.min(...arcPoints.map((point) => point.y)),
      source_point_index: cornerIndex,
      radius_m: safeRadius,
      area_delta_m2: round(area * featureAreaSign(kind)),
      perimeter_delta_m: round(curveLength - removedStraight),
      direction: 'inward',
      cut_line: false,
      separate_panel: Boolean(featureSeparatePanelInput?.checked),
      label: featureLabelInput?.value ?? `Скругление ${pointLabel(cornerIndex)}`,
    });
  };

  const polygonDraftBounds = (pointSet) => {
    if (!Array.isArray(pointSet) || pointSet.length === 0) {
      return null;
    }

    return pointSet.reduce((carry, point) => ({
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

  const syncPolygonFeatureControls = () => {
    const isActive = Array.isArray(featurePolygonDraft);
    if (startPolygonFeatureBtn) startPolygonFeatureBtn.disabled = isActive;
    if (finishPolygonFeatureBtn) finishPolygonFeatureBtn.disabled = !isActive || featurePolygonDraft.length < 3;
    if (cancelPolygonFeatureBtn) cancelPolygonFeatureBtn.disabled = !isActive;
  };

  const startPolygonFeatureDraft = () => {
    featurePolygonDraft = [];
    if (featureFigureInput) featureFigureInput.value = 'polygon';
    setSelectedFeature(-1);
    syncPolygonFeatureControls();
    updateGeometryHint();
    render({ syncList: true, syncInput: false });
  };

  const cancelPolygonFeatureDraft = () => {
    featurePolygonDraft = null;
    syncPolygonFeatureControls();
    updateGeometryHint();
    render({ syncList: false, syncInput: false });
  };

  const finalizePolygonFeatureDraft = () => {
    if (!Array.isArray(featurePolygonDraft) || featurePolygonDraft.length < 3) {
      return;
    }

    const bounds = polygonDraftBounds(featurePolygonDraft);
    if (!bounds) {
      return;
    }

    const shape = normalizeFeatureShape({
      id: `feature_${Date.now()}`,
      kind: featureKindInput?.value ?? 'cutout',
      figure: 'polygon',
      shape_points: clonePoints(featurePolygonDraft),
      x_m: round(bounds.minX),
      y_m: round(bounds.minY),
      width_m: round(bounds.maxX - bounds.minX),
      height_m: round(bounds.maxY - bounds.minY),
      label: featureLabelInput?.value ?? '',
      cut_line: false,
      separate_panel: Boolean(featureSeparatePanelInput?.checked),
    });

    if (!shape) {
      return;
    }

    pushHistory();
    featureShapes.push(shape);
    featurePolygonDraft = null;
    setSelectedFeature(featureShapes.length - 1);
    syncPolygonFeatureControls();
    render();
  };

  const setSelectedLightLine = (index) => {
    selectedLightLineIndex = lightLineShapes.length === 0
      ? -1
      : Math.max(0, Math.min(index, lightLineShapes.length - 1));

    const current = selectedLightLineIndex >= 0 ? lightLineShapes[selectedLightLineIndex] : null;
    if (lightLineLabelInput) lightLineLabelInput.value = current?.label ?? '';
    if (lightLineWidthInput) lightLineWidthInput.value = current ? metersToCentimeters(current.width_m) : 5;
    if (toggleLightLineClosedBtn) {
      toggleLightLineClosedBtn.disabled = !current;
      toggleLightLineClosedBtn.textContent = current ? (current.closed ? 'Разомкнуть' : 'Замкнуть') : 'Замкнуть / разомкнуть';
    }
    if (deleteLightLineBtn) deleteLightLineBtn.disabled = !current;
  };

  const normalizeCanvasPoint = (point) => ({
    x: round(clamp(Number(point?.x ?? 0), 0, workspaceWidth)),
    y: round(clamp(Number(point?.y ?? 0), 0, workspaceHeight)),
  });

  const snapLightLinePoint = (rawPoint, anchorPoint = null) => {
    const point = normalizeCanvasPoint(rawPoint);
    if (!snapEnabled || !anchorPoint) {
      return point;
    }

    const dx = point.x - anchorPoint.x;
    const dy = point.y - anchorPoint.y;

    if (Math.abs(dx) >= Math.abs(dy) * 1.15) {
      return { x: point.x, y: anchorPoint.y };
    }

    if (Math.abs(dy) >= Math.abs(dx) * 1.15) {
      return { x: anchorPoint.x, y: point.y };
    }

    return point;
  };

  const lineBounds = (shape) => {
    const points = Array.isArray(shape?.points) ? shape.points : [];
    if (points.length === 0) {
      return null;
    }

    return points.reduce((carry, point) => ({
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

  const buildLightLineTemplate = () => {
    const template = lightLineTemplateInput?.value ?? 'custom';
    const width = centimetersToMeters(lightLineWidthInput?.value) ?? 0.05;
    const spanX = Math.max(0.1, centimetersToMeters(lightLineTemplateWidthInput?.value) ?? 1.2);
    const spanY = Math.max(0.1, centimetersToMeters(lightLineTemplateHeightInput?.value) ?? 0.6);
    const center = polygonCentroid() ?? { x: rectWidth / 2, y: rectHeight / 2 };
    const label = lightLineLabelInput?.value ?? '';

    if (template === 'cross') {
      const horizontal = normalizeLightLineShape({
        id: `light_line_${Date.now()}_a`,
        label: label !== '' ? `${label} A` : 'Перекрестие A',
        width_m: width,
        closed: false,
        template,
        points: [
          { x: round(center.x - (spanX / 2)), y: center.y },
          { x: round(center.x + (spanX / 2)), y: center.y },
        ],
      });
      const vertical = normalizeLightLineShape({
        id: `light_line_${Date.now()}_b`,
        label: label !== '' ? `${label} B` : 'Перекрестие B',
        width_m: width,
        closed: false,
        template,
        points: [
          { x: center.x, y: round(center.y - (spanY / 2)) },
          { x: center.x, y: round(center.y + (spanY / 2)) },
        ],
      });

      return [horizontal, vertical].filter(Boolean);
    }

    if (template === 'rectangle') {
      return [normalizeLightLineShape({
        id: `light_line_${Date.now()}`,
        label: label !== '' ? label : 'Прямоугольник',
        width_m: width,
        closed: true,
        template,
        points: [
          { x: round(center.x - (spanX / 2)), y: round(center.y - (spanY / 2)) },
          { x: round(center.x + (spanX / 2)), y: round(center.y - (spanY / 2)) },
          { x: round(center.x + (spanX / 2)), y: round(center.y + (spanY / 2)) },
          { x: round(center.x - (spanX / 2)), y: round(center.y + (spanY / 2)) },
        ],
      })].filter(Boolean);
    }

    if (template === 'circle') {
      const radiusX = spanX / 2;
      const radiusY = spanY / 2;
      const circlePoints = [];
      const steps = 20;

      for (let step = 0; step < steps; step += 1) {
        const angle = (Math.PI * 2 * step) / steps;
        circlePoints.push({
          x: round(center.x + (Math.cos(angle) * radiusX)),
          y: round(center.y + (Math.sin(angle) * radiusY)),
        });
      }

      return [normalizeLightLineShape({
        id: `light_line_${Date.now()}`,
        label: label !== '' ? label : 'Круг',
        width_m: width,
        closed: true,
        template,
        points: circlePoints,
      })].filter(Boolean);
    }

    if (template === 'star') {
      const outerRadius = Math.max(spanX, spanY) / 2;
      const innerRadius = outerRadius * 0.42;
      const starPoints = [];
      const steps = 10;

      for (let step = 0; step < steps; step += 1) {
        const angle = (-Math.PI / 2) + ((Math.PI * 2 * step) / steps);
        const radius = step % 2 === 0 ? outerRadius : innerRadius;
        starPoints.push({
          x: round(center.x + (Math.cos(angle) * radius)),
          y: round(center.y + (Math.sin(angle) * radius)),
        });
      }

      return [normalizeLightLineShape({
        id: `light_line_${Date.now()}`,
        label: label !== '' ? label : 'Звезда',
        width_m: width,
        closed: true,
        template,
        points: starPoints,
      })].filter(Boolean);
    }

    return [];
  };

  const updateSelectedLightLineFromInputs = () => {
    const current = selectedLightLineIndex >= 0 ? lightLineShapes[selectedLightLineIndex] : null;
    if (!current) {
      return;
    }

    current.label = lightLineLabelInput?.value ?? '';
    current.width_m = Math.max(0.01, centimetersToMeters(lightLineWidthInput?.value) ?? current.width_m ?? 0.05);
    current.closed = Boolean(current.closed);
    lightLineShapes[selectedLightLineIndex] = normalizeLightLineShape(current, selectedLightLineIndex) ?? current;
  };

  const deleteSelectedLightLine = () => {
    if (selectedLightLineIndex < 0 || !lightLineShapes[selectedLightLineIndex]) {
      return;
    }

    pushHistory();
    lightLineShapes.splice(selectedLightLineIndex, 1);
    setSelectedLightLine(Math.min(selectedLightLineIndex, lightLineShapes.length - 1));
    render();
  };

  const syncProductionSegmentOptions = () => {
    if (!productionOrientationSegmentInput) {
      return;
    }

    productionOrientationSegmentInput.innerHTML = '';
    points.forEach((point, index) => {
      const option = document.createElement('option');
      option.value = String(index);
      option.textContent = segmentLabel(index);
      productionOrientationSegmentInput.appendChild(option);
    });

    const fallbackIndex = points.length > 0 ? Math.min(Math.max(Number(productionSettings.orientation_segment_index ?? 0), 0), points.length - 1) : 0;
    productionSettings.orientation_segment_index = fallbackIndex;
    productionOrientationSegmentInput.value = String(fallbackIndex);
  };

  const syncProductionInputs = () => {
    if (productionTextureInput) productionTextureInput.value = productionSettings.texture;
    if (productionRollWidthInput) productionRollWidthInput.value = String(Math.round(Number(productionSettings.roll_width_cm ?? 320)));
    if (productionHarpoonInput) productionHarpoonInput.value = productionSettings.harpoon_type;
    if (productionOrientationModeInput) productionOrientationModeInput.value = productionSettings.orientation_mode;
    syncProductionSegmentOptions();
    if (productionOrientationOffsetInput) productionOrientationOffsetInput.value = String(metersToCentimeters(productionSettings.orientation_offset_m));
    if (productionShrinkXInput) productionShrinkXInput.value = String(Number(productionSettings.shrink_x_percent ?? 7));
    if (productionShrinkYInput) productionShrinkYInput.value = String(Number(productionSettings.shrink_y_percent ?? 7));
    if (productionSameRollInput) productionSameRollInput.checked = Boolean(productionSettings.same_roll_required);
    if (productionSpecialCuttingInput) productionSpecialCuttingInput.checked = Boolean(productionSettings.special_cutting);
    if (productionSeamEnabledInput) productionSeamEnabledInput.checked = Boolean(productionSettings.seam_enabled);
    if (productionSeamOffsetInput) productionSeamOffsetInput.value = String(metersToCentimeters(productionSettings.seam_offset_m));
    if (productionCommentInput) productionCommentInput.value = productionSettings.comment ?? '';
  };

  const updateProductionSettingsFromInputs = () => {
    productionSettings.texture = productionTextureInput?.value ?? productionSettings.texture;
    productionSettings.roll_width_cm = Math.max(50, Number(productionRollWidthInput?.value ?? productionSettings.roll_width_cm ?? 320) || 320);
    productionSettings.harpoon_type = productionHarpoonInput?.value ?? productionSettings.harpoon_type;
    productionSettings.orientation_mode = productionOrientationModeInput?.value ?? productionSettings.orientation_mode;
    productionSettings.orientation_segment_index = Math.max(0, Number(productionOrientationSegmentInput?.value ?? productionSettings.orientation_segment_index ?? 0) || 0);
    productionSettings.orientation_offset_m = centimetersToMeters(productionOrientationOffsetInput?.value ?? productionSettings.orientation_offset_m ?? 0) ?? 0;
    productionSettings.shrink_x_percent = round(Number(productionShrinkXInput?.value ?? productionSettings.shrink_x_percent ?? 7) || 0);
    productionSettings.shrink_y_percent = round(Number(productionShrinkYInput?.value ?? productionSettings.shrink_y_percent ?? 7) || 0);
    productionSettings.same_roll_required = Boolean(productionSameRollInput?.checked);
    productionSettings.special_cutting = Boolean(productionSpecialCuttingInput?.checked);
    productionSettings.seam_enabled = Boolean(productionSeamEnabledInput?.checked);
    productionSettings.seam_offset_m = centimetersToMeters(productionSeamOffsetInput?.value ?? productionSettings.seam_offset_m ?? 0) ?? 0;
    productionSettings.comment = productionCommentInput?.value ?? '';
  };

  const productionGuideGeometry = (offsetMeters = null) => {
    const bounds = geometryBounds();
    if (!Number.isFinite(bounds.minX) || points.length < 2) {
      return null;
    }

    const mode = productionSettings.orientation_mode ?? 'parallel_segment';
    const segmentIndex = Math.min(Math.max(Number(productionSettings.orientation_segment_index ?? 0), 0), Math.max(points.length - 1, 0));
    const segment = getSegmentGeometry(segmentIndex);
    const defaultOffset = Number(offsetMeters ?? productionSettings.orientation_offset_m ?? 0);
    const diagonal = Math.max(bounds.maxX - bounds.minX, bounds.maxY - bounds.minY, 1) * 2;
    const center = mode === 'center_room'
      ? {
          x: round((bounds.minX + bounds.maxX) / 2),
          y: round((bounds.minY + bounds.maxY) / 2),
        }
      : (segment
        ? {
            x: round((segment.start.x + segment.end.x) / 2),
            y: round((segment.start.y + segment.end.y) / 2),
          }
        : polygonCentroid());
    if (!center) {
      return null;
    }

    let direction = { x: 1, y: 0 };
    let normal = { x: 0, y: 1 };
    if (segment && mode !== 'center_room') {
      const segmentVector = normalizeVector({ x: segment.dx, y: segment.dy }) ?? { x: 1, y: 0 };
      const segmentNormal = normalizeVector({ x: -segment.dy, y: segment.dx }) ?? { x: 0, y: 1 };
      if (mode === 'perpendicular_segment') {
        direction = segmentNormal;
        normal = { x: -segmentVector.x, y: -segmentVector.y };
      } else {
        direction = segmentVector;
        normal = segmentNormal;
      }
    }

    const anchor = {
      x: round(center.x + (normal.x * defaultOffset)),
      y: round(center.y + (normal.y * defaultOffset)),
    };

    return {
      start: {
        x: round(anchor.x - (direction.x * diagonal)),
        y: round(anchor.y - (direction.y * diagonal)),
      },
      end: {
        x: round(anchor.x + (direction.x * diagonal)),
        y: round(anchor.y + (direction.y * diagonal)),
      },
      segmentIndex,
    };
  };

  const syncProductionSummary = () => {
    if (!productionSummaryText) {
      return;
    }

    const segment = points.length > 0 ? segmentLabel(Math.min(Math.max(Number(productionSettings.orientation_segment_index ?? 0), 0), points.length - 1)) : 'AB';
    const orientationLabelMap = {
      parallel_segment: 'параллельно',
      perpendicular_segment: 'перпендикулярно',
      center_segment: 'по центру стороны',
      center_room: 'по центру помещения',
    };
    const textureLabelMap = {
      matte: 'матовый',
      satin: 'сатин',
      glossy: 'глянец',
      fabric: 'ткань',
      custom: 'другое',
    };
    const seamText = productionSettings.seam_enabled
      ? `, шов ${metersToCentimeters(productionSettings.seam_offset_m)} см`
      : '';
    productionSummaryText.textContent = `Полотно: ${textureLabelMap[productionSettings.texture] ?? productionSettings.texture}, рулон ${productionSettings.roll_width_cm} см, гарпун ${productionSettings.harpoon_type}, усадка ${productionSettings.shrink_x_percent}%/${productionSettings.shrink_y_percent}%, ${orientationLabelMap[productionSettings.orientation_mode] ?? productionSettings.orientation_mode}${productionSettings.orientation_mode === 'center_room' ? '' : ` ${segment}`}, смещение ${metersToCentimeters(productionSettings.orientation_offset_m)} см${seamText}.`;
  };

  const panelBoundsFromPointSet = (pointSet) => {
    if (!Array.isArray(pointSet) || pointSet.length < 3) {
      return null;
    }

    return pointSet.reduce((carry, point) => ({
      min_x: Math.min(carry.min_x, Number(point.x ?? 0)),
      min_y: Math.min(carry.min_y, Number(point.y ?? 0)),
      max_x: Math.max(carry.max_x, Number(point.x ?? 0)),
      max_y: Math.max(carry.max_y, Number(point.y ?? 0)),
    }), {
      min_x: Number.POSITIVE_INFINITY,
      min_y: Number.POSITIVE_INFINITY,
      max_x: Number.NEGATIVE_INFINITY,
      max_y: Number.NEGATIVE_INFINITY,
    });
  };

  const centroidFromPointSet = (pointSet) => {
    if (!Array.isArray(pointSet) || pointSet.length === 0) {
      return null;
    }

    const sum = pointSet.reduce((carry, point) => ({
      x: carry.x + Number(point.x ?? 0),
      y: carry.y + Number(point.y ?? 0),
    }), { x: 0, y: 0 });

    return {
      x: round(sum.x / pointSet.length),
      y: round(sum.y / pointSet.length),
    };
  };

  const buildSeparateFeaturePanelsPreview = (offset = 0) => featureShapes
    .filter((shape) => shape?.separate_panel)
    .map((shape, index) => {
      const shapePoints = featureShapePoints(shape);
      const bounds = panelBoundsFromPointSet(shapePoints);
      const area = round(Math.abs(polygonArea(shapePoints)));
      if (!bounds || area <= 0) {
        return null;
      }

      return normalizeDerivedPanel({
        id: `panel_${offset + index + 1}`,
        label: shape.label && shape.label.trim() !== '' ? shape.label : `Полотно ${offset + index + 1}`,
        area_m2: area,
        cells_count: 0,
        centroid: centroidFromPointSet(shapePoints),
        bounds,
        shape_points: shapePoints,
        source: 'feature',
        source_shape_id: shape.id ?? null,
        feature_kind: shape.kind ?? null,
        production: { ...productionSettings },
      }, offset + index);
    })
    .filter(Boolean);

  const panelPointSet = (panel) => {
    if (Array.isArray(panel?.shape_points) && panel.shape_points.length >= 3) {
      return clonePoints(panel.shape_points);
    }

    if (panel?.bounds && Number.isFinite(Number(panel.bounds.min_x)) && Number.isFinite(Number(panel.bounds.min_y)) && Number.isFinite(Number(panel.bounds.max_x)) && Number.isFinite(Number(panel.bounds.max_y))) {
      return [
        { x: round(Number(panel.bounds.min_x)), y: round(Number(panel.bounds.min_y)) },
        { x: round(Number(panel.bounds.max_x)), y: round(Number(panel.bounds.min_y)) },
        { x: round(Number(panel.bounds.max_x)), y: round(Number(panel.bounds.max_y)) },
        { x: round(Number(panel.bounds.min_x)), y: round(Number(panel.bounds.max_y)) },
      ];
    }

    return [];
  };

  const uniquePolygonPreviewPoints = (pointSet) => {
    const result = [];
    pointSet.forEach((point) => {
      if (!point || !Number.isFinite(Number(point.x)) || !Number.isFinite(Number(point.y))) return;
      const normalizedPoint = { x: round(Number(point.x)), y: round(Number(point.y)) };
      const last = result[result.length - 1];
      if (last && Math.abs(last.x - normalizedPoint.x) < 0.0001 && Math.abs(last.y - normalizedPoint.y) < 0.0001) {
        return;
      }
      result.push(normalizedPoint);
    });

    if (result.length > 1) {
      const first = result[0];
      const last = result[result.length - 1];
      if (Math.abs(first.x - last.x) < 0.0001 && Math.abs(first.y - last.y) < 0.0001) {
        result.pop();
      }
    }

    return result.length >= 3 ? result : [];
  };

  const signedDistanceToGuide = (point, linePoint, normal) => ((Number(point.x) - Number(linePoint.x)) * Number(normal.x)) + ((Number(point.y) - Number(linePoint.y)) * Number(normal.y));

  const clipPolygonByGuide = (pointSet, linePoint, normal, keepPositive = true) => {
    if (!Array.isArray(pointSet) || pointSet.length < 3) {
      return [];
    }

    const clipped = [];
    for (let index = 0; index < pointSet.length; index += 1) {
      const current = pointSet[index];
      const next = pointSet[(index + 1) % pointSet.length];
      const currentDistance = signedDistanceToGuide(current, linePoint, normal);
      const nextDistance = signedDistanceToGuide(next, linePoint, normal);
      const currentInside = keepPositive ? currentDistance >= -0.0001 : currentDistance <= 0.0001;
      const nextInside = keepPositive ? nextDistance >= -0.0001 : nextDistance <= 0.0001;

      if (currentInside) {
        clipped.push({ x: round(Number(current.x)), y: round(Number(current.y)) });
      }

      if (currentInside !== nextInside) {
        const denominator = currentDistance - nextDistance;
        if (Math.abs(denominator) > 0.000001) {
          const ratio = currentDistance / denominator;
          clipped.push({
            x: round(Number(current.x) + ((Number(next.x) - Number(current.x)) * ratio)),
            y: round(Number(current.y) + ((Number(next.y) - Number(current.y)) * ratio)),
          });
        }
      }
    }

    return uniquePolygonPreviewPoints(clipped);
  };

  const splitPanelByProductionSeam = (panel, index = 0) => {
    if (!productionSettings.seam_enabled) {
      return [panel];
    }

    const seamGuide = productionGuideGeometry(Number(productionSettings.orientation_offset_m ?? 0) + Number(productionSettings.seam_offset_m ?? 0));
    if (!seamGuide) {
      return [panel];
    }

    const shapePoints = panelPointSet(panel);
    if (shapePoints.length < 3) {
      return [panel];
    }

    const direction = normalizeVector({
      x: Number(seamGuide.end.x) - Number(seamGuide.start.x),
      y: Number(seamGuide.end.y) - Number(seamGuide.start.y),
    });
    if (!direction) {
      return [panel];
    }

    const normal = normalizeVector({ x: -direction.y, y: direction.x });
    if (!normal) {
      return [panel];
    }

    const firstPart = clipPolygonByGuide(shapePoints, seamGuide.start, normal, true);
    const secondPart = clipPolygonByGuide(shapePoints, seamGuide.start, normal, false);
    const parts = [firstPart, secondPart].filter((part) => part.length >= 3 && polygonArea(part) > 0.01);

    if (parts.length !== 2) {
      return [panel];
    }

    return parts.map((part, partIndex) => normalizeDerivedPanel({
      ...panel,
      id: `${panel.id || `panel_${index + 1}`}_part_${partIndex + 1}`,
      label: `${panel.label || `Полотно ${index + 1}`} ${partIndex === 0 ? 'A' : 'B'}`,
      area_m2: round(polygonArea(part)),
      centroid: centroidFromPointSet(part),
      bounds: panelBoundsFromPointSet(part),
      shape_points: part,
      source: 'seam_split',
      seam_parent_id: panel.id || `panel_${index + 1}`,
      seam_part_index: partIndex + 1,
      production: { ...productionSettings, seam_enabled: false, seam_offset_m: 0 },
    }, index + partIndex)).filter(Boolean);
  };

  const applyProductionSeamToPanels = (panels) => {
    if (!Array.isArray(panels) || panels.length === 0) {
      return [];
    }

    return panels.flatMap((panel, index) => splitPanelByProductionSeam(panel, index)).filter(Boolean);
  };

  const estimateLightLinePanels = () => {
    if (!Array.isArray(points) || points.length < 3) {
      return [];
    }

    const featurePanels = buildSeparateFeaturePanelsPreview();
    const hasBlockingLines = lightLineShapes.some((shape) => Array.isArray(shape.points) && shape.points.length >= 2 && Number(shape.width_m ?? 0) > 0);
    if (!hasBlockingLines) {
      const roomBounds = panelBoundsFromPointSet(points);
      const roomPanel = normalizeDerivedPanel({
        id: 'panel_1',
        label: 'Полотно 1',
        area_m2: round(polygonArea(points)),
        cells_count: 0,
        centroid: polygonCentroid(),
        bounds: roomBounds,
        shape_points: clonePoints(points),
        source: 'room',
        production: { ...productionSettings },
      });

      return applyProductionSeamToPanels([roomPanel, ...buildSeparateFeaturePanelsPreview(roomPanel ? 1 : 0)].filter(Boolean));
    }

    const bounds = geometryBounds(points, [], [], []);
    if (!Number.isFinite(bounds.minX) || !Number.isFinite(bounds.minY)) {
      return [];
    }

    const step = 0.05;
    const cols = Math.max(1, Math.ceil((bounds.maxX - bounds.minX) / step));
    const rows = Math.max(1, Math.ceil((bounds.maxY - bounds.minY) / step));
    const grid = Array.from({ length: rows }, () => Array(cols).fill(0));
    const insideCell = (row, col) => row >= 0 && row < rows && col >= 0 && col < cols;
    const cellCenter = (row, col) => ({
      x: bounds.minX + ((col + 0.5) * step),
      y: bounds.minY + ((row + 0.5) * step),
    });

    for (let row = 0; row < rows; row += 1) {
      for (let col = 0; col < cols; col += 1) {
        const center = cellCenter(row, col);
        if (!isPointInsidePolygon(center, points)) {
          grid[row][col] = -1;
          continue;
        }

        const blockedByLine = lightLineShapes.some((shape) => {
          const halfWidth = Math.max(Number(shape.width_m ?? 0.05), step) / 2;
          return distanceToPolyline(center, shape.points ?? [], Boolean(shape.closed)) <= halfWidth;
        });

        if (blockedByLine) {
          grid[row][col] = -1;
        }
      }
    }

    const panels = [];
    let nextId = 1;
    for (let row = 0; row < rows; row += 1) {
      for (let col = 0; col < cols; col += 1) {
        if (grid[row][col] !== 0) {
          continue;
        }

        const queue = [[row, col]];
        grid[row][col] = nextId;
        const cells = [];
        while (queue.length > 0) {
          const [currentRow, currentCol] = queue.shift();
          cells.push([currentRow, currentCol]);

          [
            [currentRow - 1, currentCol],
            [currentRow + 1, currentCol],
            [currentRow, currentCol - 1],
            [currentRow, currentCol + 1],
          ].forEach(([nextRow, nextCol]) => {
            if (!insideCell(nextRow, nextCol) || grid[nextRow][nextCol] !== 0) {
              return;
            }
            grid[nextRow][nextCol] = nextId;
            queue.push([nextRow, nextCol]);
          });
        }

        const centroid = cells.reduce((carry, [cellRow, cellCol]) => {
          const center = cellCenter(cellRow, cellCol);
          return {
            x: carry.x + center.x,
            y: carry.y + center.y,
          };
        }, { x: 0, y: 0 });

        panels.push({
          id: `panel_${nextId}`,
          label: `Полотно ${nextId}`,
          area_m2: round(cells.length * step * step),
          cells_count: cells.length,
          centroid: cells.length > 0 ? {
            x: round(centroid.x / cells.length),
            y: round(centroid.y / cells.length),
          } : null,
          bounds: cells.length > 0 ? {
            min_x: round(bounds.minX + (Math.min(...cells.map((cell) => cell[1])) * step)),
            min_y: round(bounds.minY + (Math.min(...cells.map((cell) => cell[0])) * step)),
            max_x: round(bounds.minX + ((Math.max(...cells.map((cell) => cell[1])) + 1) * step)),
            max_y: round(bounds.minY + ((Math.max(...cells.map((cell) => cell[0])) + 1) * step)),
          } : null,
          shape_points: cells.length > 0 ? [
            { x: round(bounds.minX + (Math.min(...cells.map((cell) => cell[1])) * step)), y: round(bounds.minY + (Math.min(...cells.map((cell) => cell[0])) * step)) },
            { x: round(bounds.minX + ((Math.max(...cells.map((cell) => cell[1])) + 1) * step)), y: round(bounds.minY + (Math.min(...cells.map((cell) => cell[0])) * step)) },
            { x: round(bounds.minX + ((Math.max(...cells.map((cell) => cell[1])) + 1) * step)), y: round(bounds.minY + ((Math.max(...cells.map((cell) => cell[0])) + 1) * step)) },
            { x: round(bounds.minX + (Math.min(...cells.map((cell) => cell[1])) * step)), y: round(bounds.minY + ((Math.max(...cells.map((cell) => cell[0])) + 1) * step)) },
          ] : null,
          source: 'light_line_split',
          production: { ...productionSettings },
        });
        nextId += 1;
      }
    }

    const normalizedPanels = panels
      .filter((panel) => panel.area_m2 > 0.02)
      .sort((left, right) => right.area_m2 - left.area_m2)
      .map((panel, index) => normalizeDerivedPanel({
        ...panel,
        id: `panel_${index + 1}`,
        label: panel.label ?? `Полотно ${index + 1}`,
      }, index))
      .filter(Boolean);

    return applyProductionSeamToPanels([...normalizedPanels, ...buildSeparateFeaturePanelsPreview(normalizedPanels.length)].filter(Boolean));
  };

  const renderLightLinePanelsList = () => {
    if (!lightLinePanelsList || !lightLinePanelsSummary) {
      return;
    }

    lightLinePanelsList.innerHTML = '';
    const totalArea = lightLinePanelsPreview.reduce((sum, panel) => sum + Number(panel.area_m2 ?? 0), 0);

    if (lightLinePanelsPreview.length === 0) {
      lightLinePanelsSummary.textContent = 'Постройте световые линии, чтобы увидеть отдельные полотна.';
      const empty = document.createElement('div');
      empty.className = 'small text-muted';
      empty.textContent = 'Отдельные полотна пока не определены.';
      lightLinePanelsList.appendChild(empty);
      return;
    }

    lightLinePanelsSummary.textContent = `Предпросмотр: ${lightLinePanelsPreview.length} полотн, суммарно около ${String(round(totalArea)).replace('.', ',')} м2.`;
    lightLinePanelsPreview.forEach((panel, index) => {
      const sourceLabelMap = {
        room: 'контур',
        feature: 'отдельная форма',
        light_line_split: 'световые линии',
        seam_split: 'шов',
      };
      const sourceText = sourceLabelMap[panel.source] ? `${sourceLabelMap[panel.source]}` : 'полотно';
      const seamText = Number.isFinite(Number(panel.seam_part_index)) ? ` · часть ${panel.seam_part_index}` : '';
      const row = document.createElement('div');
      row.className = 'feature-row';
      row.innerHTML = `
        <span class="feature-row-dot" style="background:#059669"></span>
        <div>
          <div class="feature-row-title">${panel.label ? panel.label : `Полотно ${index + 1}`}</div>
          <div class="feature-row-subtitle">Площадь ≈ ${String(panel.area_m2).replace('.', ',')} м2 · ${sourceText}${seamText}</div>
        </div>
      `;
      lightLinePanelsList.appendChild(row);
    });
  };

  const startLightLineDraft = () => {
    lightLineDraft = [];
    setSelectedLightLine(-1);
    updateGeometryHint();
    render({ syncList: true, syncInput: false });
  };

  const finishLightLineDraft = () => {
    if (!Array.isArray(lightLineDraft) || lightLineDraft.length < 2) {
      return;
    }

    const shape = normalizeLightLineShape({
      id: `light_line_${Date.now()}`,
      label: lightLineLabelInput?.value ?? '',
      width_m: centimetersToMeters(lightLineWidthInput?.value) ?? 0.05,
      closed: false,
      template: 'custom',
      points: clonePoints(lightLineDraft),
    });

    if (!shape) {
      return;
    }

    pushHistory();
    lightLineShapes.push(shape);
    lightLineDraft = null;
    setSelectedLightLine(lightLineShapes.length - 1);
    render();
  };

  const cancelLightLineDraft = () => {
    lightLineDraft = null;
    updateGeometryHint();
    render({ syncList: false, syncInput: false });
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
    const requestedShift = getWallShiftValueMeters();
    const requestedShiftText = requestedShift ? ` Параметрический сдвиг: ${formatLength(requestedShift)}.` : '';
    const liveShiftText = dragSegmentState && Number.isFinite(dragSegmentState.currentOffsetMeters)
      ? ` Текущий сдвиг: ${formatLength(Math.abs(dragSegmentState.currentOffsetMeters))} ${dragSegmentState.currentOffsetMeters >= 0 ? 'внутрь' : 'наружу'}.`
      : '';

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

    if (Array.isArray(lightLineDraft)) {
      if (modePill) modePill.textContent = 'Режим: световые линии';
      geometryHint.textContent = `Световые линии: кликом добавляйте вершины конструкции. Уже точек: ${lightLineDraft.length}. Двойной клик завершает линию.${segmentText}`;
      return;
    }

    if (Array.isArray(featurePolygonDraft)) {
      if (modePill) modePill.textContent = 'Режим: многоугольник';
      geometryHint.textContent = `Многоугольник: кликом по полю добавляйте вершины внутреннего контура или второго полотна. Уже точек: ${featurePolygonDraft.length}. Завершение доступно после 3 точек.${segmentText}`;
      return;
    }

    if (activeMode === 'hand') {
      if (modePill) modePill.textContent = 'Режим: рука';
      geometryHint.textContent = 'Режим руки: перетаскивайте холст левой кнопкой мыши. Колесо меняет масштаб, Вписать возвращает комнату в кадр.';
      return;
    }

    if (activeMode === 'wall') {
      if (modePill) modePill.textContent = 'Режим: стена';
      geometryHint.textContent = `Режим стены: выберите сегмент и тяните его параллельно или задайте точный сдвиг в панели справа. Колесо мыши меняет масштаб.${segmentText}${requestedShiftText}${liveShiftText}`;
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

  const featureShapeBounds = (shape) => ({
    left: Number(shape.x_m ?? 0),
    top: Number(shape.y_m ?? 0),
    width: Number(shape.width_m ?? 0),
    height: Number(shape.height_m ?? 0),
    right: Number(shape.x_m ?? 0) + Number(shape.width_m ?? 0),
    bottom: Number(shape.y_m ?? 0) + Number(shape.height_m ?? 0),
  });

  const featureShapePoints = (shape) => {
    if (Array.isArray(shape.shape_points) && shape.shape_points.length >= 3) {
      return shape.shape_points.map((point) => ({
        x: round(Number(point.x ?? 0)),
        y: round(Number(point.y ?? 0)),
      }));
    }

    const bounds = featureShapeBounds(shape);
    if (shape.figure === 'triangle') {
      return [
        { x: round(bounds.left), y: round(bounds.bottom) },
        { x: round(bounds.left), y: round(bounds.top) },
        { x: round(bounds.right), y: round(bounds.bottom) },
      ];
    }

    return [
      { x: round(bounds.left), y: round(bounds.top) },
      { x: round(bounds.right), y: round(bounds.top) },
      { x: round(bounds.right), y: round(bounds.bottom) },
      { x: round(bounds.left), y: round(bounds.bottom) },
    ];
  };

  const closestPointOnSegment = (point, start, end) => {
    const dx = Number(end?.x ?? 0) - Number(start?.x ?? 0);
    const dy = Number(end?.y ?? 0) - Number(start?.y ?? 0);
    const denominator = (dx * dx) + (dy * dy);

    if (denominator <= 0.000001) {
      return {
        x: round(Number(start?.x ?? 0)),
        y: round(Number(start?.y ?? 0)),
      };
    }

    const t = clamp((((Number(point?.x ?? 0) - Number(start?.x ?? 0)) * dx) + ((Number(point?.y ?? 0) - Number(start?.y ?? 0)) * dy)) / denominator, 0, 1);

    return {
      x: round(Number(start?.x ?? 0) + (dx * t)),
      y: round(Number(start?.y ?? 0) + (dy * t)),
    };
  };

  const featureCutConnector = (shape) => {
    if (!shape?.cut_line) {
      return null;
    }

    const segmentIndex = Number.isInteger(shape.cut_segment_index)
      ? shape.cut_segment_index
      : shape.source_segment_index;
    if (!Number.isInteger(segmentIndex)) {
      return null;
    }

    const segment = getSegmentGeometry(segmentIndex);
    const shapePoints = featureShapePoints(shape);
    if (!segment || shapePoints.length < 2) {
      return null;
    }

    const baseOffset = Number.isFinite(Number(shape.cut_offset_m))
      ? Number(shape.cut_offset_m)
      : (Number.isFinite(Number(shape.offset_m))
      ? Number(shape.offset_m) + (Number(shape.width_m ?? 0) / 2)
      : (segment.length / 2));
    const basePoint = pointAlongSegment(segmentIndex, clamp(baseOffset, 0, segment.length));
    if (!basePoint) {
      return null;
    }

    let bestPoint = null;
    let bestDistance = Number.POSITIVE_INFINITY;
    shapePoints.forEach((point, index) => {
      const nextPoint = shapePoints[(index + 1) % shapePoints.length];
      const candidate = closestPointOnSegment(basePoint, point, nextPoint);
      const distance = distanceBetweenPoints(basePoint, candidate);
      if (distance < bestDistance) {
        bestDistance = distance;
        bestPoint = candidate;
      }
    });

    return bestPoint ? { start: basePoint, end: bestPoint } : null;
  };

  const translateFeatureShape = (shape, deltaX, deltaY) => {
    const nextX = round(clamp(Number(shape.x_m ?? 0) + deltaX, 0, workspaceWidth));
    const nextY = round(clamp(Number(shape.y_m ?? 0) + deltaY, 0, workspaceHeight));
    const nextShape = {
      ...shape,
      x_m: nextX,
      y_m: nextY,
    };

    if (Array.isArray(shape.shape_points) && shape.shape_points.length >= 3) {
      nextShape.shape_points = shape.shape_points.map((point) => ({
        x: round(clamp(Number(point.x ?? 0) + deltaX, 0, workspaceWidth)),
        y: round(clamp(Number(point.y ?? 0) + deltaY, 0, workspaceHeight)),
      }));
    }

    return normalizeFeatureShape(nextShape);
  };

  const setSelectedFeature = (index) => {
    selectedFeatureIndex = featureShapes.length === 0
      ? -1
      : Math.max(0, Math.min(index, featureShapes.length - 1));
  };

  const syncFeatureCutControls = (currentShape = null) => {
    const kind = currentShape?.kind ?? featureKindInput?.value ?? 'cutout';
    const cutEnabled = Boolean(currentShape?.cut_line ?? featureCutLineInput?.checked) && kind === 'cutout';
    const preferredSegmentIndex = Number.isInteger(currentShape?.cut_segment_index)
      ? currentShape.cut_segment_index
      : (Number.isInteger(currentShape?.source_segment_index) ? currentShape.source_segment_index : selectedSegmentIndex);

    if (featureCutSegmentInput) {
      const options = points.map((_, index) => {
        const segment = getSegmentGeometry(index);
        const lengthLabel = segment ? `${metersToCentimeters(segment.length)} см` : '0 см';
        return `<option value="${index}">${segmentLabel(index)} · ${lengthLabel}</option>`;
      }).join('');
      featureCutSegmentInput.innerHTML = options;
      const normalizedIndex = Math.max(0, Math.min(
        Number.isInteger(preferredSegmentIndex) ? preferredSegmentIndex : 0,
        Math.max(points.length - 1, 0),
      ));
      featureCutSegmentInput.value = String(normalizedIndex);
      featureCutSegmentInput.disabled = !cutEnabled || points.length < 2;
    }

    const segmentIndex = Number.isInteger(preferredSegmentIndex) ? preferredSegmentIndex : selectedSegmentIndex;
    const segment = getSegmentGeometry(segmentIndex);
    const cutOffsetMeters = Number.isFinite(Number(currentShape?.cut_offset_m))
      ? Number(currentShape.cut_offset_m)
      : (Number.isFinite(Number(currentShape?.offset_m)) ? Number(currentShape.offset_m) : 0);

    if (featureCutOffsetInput) {
      featureCutOffsetInput.value = metersToCentimeters(cutOffsetMeters);
      featureCutOffsetInput.disabled = !cutEnabled;
      if (segment) {
        featureCutOffsetInput.max = String(metersToCentimeters(segment.length));
      } else {
        featureCutOffsetInput.removeAttribute('max');
      }
    }
  };

  const syncSelectedFeatureInspector = () => {
    const currentShape = selectedFeatureIndex >= 0 ? featureShapes[selectedFeatureIndex] : null;
    const bounds = geometryBounds();
    const fallbackX = Number.isFinite(bounds.minX) && Number.isFinite(bounds.maxX)
      ? round((bounds.minX + bounds.maxX) / 2)
      : 1;
    const fallbackY = Number.isFinite(bounds.minY) && Number.isFinite(bounds.maxY)
      ? round((bounds.minY + bounds.maxY) / 2)
      : 1;

    if (featureKindInput) featureKindInput.value = currentShape?.kind ?? 'cutout';
    if (featureFigureInput) featureFigureInput.value = currentShape?.figure ?? 'rectangle';
    if (featureXInput) featureXInput.value = currentShape ? metersToCentimeters(currentShape.x_m) : metersToCentimeters(fallbackX);
    if (featureYInput) featureYInput.value = currentShape ? metersToCentimeters(currentShape.y_m) : metersToCentimeters(fallbackY);
    if (featureWidthInput) featureWidthInput.value = currentShape ? metersToCentimeters(currentShape.width_m) : 60;
    if (featureHeightInput) featureHeightInput.value = currentShape ? metersToCentimeters(currentShape.height_m) : 60;
    if (featureRadiusInput) featureRadiusInput.value = currentShape?.radius_m ? metersToCentimeters(currentShape.radius_m) : 25;
    if (featureWallOffsetInput) featureWallOffsetInput.value = currentShape?.offset_m ? metersToCentimeters(currentShape.offset_m) : 30;
    if (featureDepthInput) featureDepthInput.value = currentShape?.depth_m ? metersToCentimeters(currentShape.depth_m) : 40;
    if (featureDirectionInput) featureDirectionInput.value = currentShape?.direction === 'outward' ? 'outward' : 'inward';
    if (featureCutLineInput) featureCutLineInput.checked = Boolean(currentShape?.cut_line);
    if (featureSeparatePanelInput) featureSeparatePanelInput.checked = Boolean(currentShape?.separate_panel);
    if (featureLabelInput) featureLabelInput.value = currentShape?.label ?? '';
    syncFeatureCutControls(currentShape);
    if (updateFeatureShapeBtn) updateFeatureShapeBtn.disabled = !currentShape;
    if (deleteFeatureShapeBtn) deleteFeatureShapeBtn.disabled = !currentShape;
  };

  const buildFeatureShapeFromInputs = (base = {}) => {
    const x = centimetersToMeters(featureXInput?.value);
    const y = centimetersToMeters(featureYInput?.value);
    const width = centimetersToMeters(featureWidthInput?.value);
    const height = centimetersToMeters(featureHeightInput?.value);
    const kind = featureKindInput?.value ?? base.kind ?? 'cutout';
    const direction = featureDirectionInput?.value === 'outward' ? 'outward' : 'inward';
    const radius = centimetersToMeters(featureRadiusInput?.value);
    const cutLine = Boolean(featureCutLineInput?.checked) && kind === 'cutout';
    const cutSegmentIndex = Number.isFinite(Number(featureCutSegmentInput?.value))
      ? Number(featureCutSegmentInput.value)
      : (Number.isInteger(base.cut_segment_index) ? base.cut_segment_index : null);
    const cutSegment = Number.isInteger(cutSegmentIndex) ? getSegmentGeometry(cutSegmentIndex) : null;
    const requestedCutOffset = centimetersToMeters(featureCutOffsetInput?.value);

    if (x === null || y === null || width === null || height === null || width <= 0 || height <= 0) {
      return null;
    }

    return normalizeFeatureShape({
      id: base.id ?? `feature_${Date.now()}`,
      kind,
      figure: featureFigureInput?.value ?? base.figure ?? 'rectangle',
      x_m: clamp(x, 0, workspaceWidth),
      y_m: clamp(y, 0, workspaceHeight),
      width_m: Math.max(0.05, width),
      height_m: Math.max(0.05, height),
      shape_points: base.shape_points ?? null,
      source_point_index: base.source_point_index ?? null,
      source_segment_index: base.source_segment_index ?? null,
      offset_m: base.offset_m ?? null,
      cut_segment_index: cutLine ? cutSegmentIndex : null,
      cut_offset_m: cutLine
        ? round(clamp(
            requestedCutOffset === null
              ? (Number.isFinite(Number(base.cut_offset_m)) ? Number(base.cut_offset_m) : 0)
              : requestedCutOffset,
            0,
            cutSegment?.length ?? workspaceWidth
          ))
        : null,
      depth_m: base.depth_m ?? null,
      radius_m: radius ?? base.radius_m ?? null,
      area_delta_m2: base.area_delta_m2 ?? null,
      perimeter_delta_m: base.perimeter_delta_m ?? null,
      direction,
      cut_line: cutLine,
      separate_panel: Boolean(featureSeparatePanelInput?.checked),
      label: featureLabelInput?.value ?? base.label ?? '',
    });
  };

  const renderFeatureShapesList = () => {
    if (!featureShapesList) return;

    featureShapesList.innerHTML = '';

    if (featureShapes.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'small text-muted';
      empty.textContent = 'Пока нет дополнительных форм.';
      featureShapesList.appendChild(empty);
      return;
    }

    featureShapes.forEach((shape, index) => {
      const row = document.createElement('div');
      row.className = `feature-row${selectedFeatureIndex === index ? ' is-selected' : ''}`;
      const attachmentLabel = Number.isInteger(shape.source_point_index)
        ? `Угол ${pointLabel(shape.source_point_index)}`
        : (Number.isInteger(shape.source_segment_index) ? `Сторона ${segmentLabel(shape.source_segment_index)}` : `X ${metersToCentimeters(shape.x_m)} / Y ${metersToCentimeters(shape.y_m)}`);
      const sizeLabel = shape.figure === 'rounded_corner'
        ? `R ${metersToCentimeters(shape.radius_m ?? 0)} см`
        : `${metersToCentimeters(shape.width_m)}×${metersToCentimeters(shape.height_m)} см`;
      const offsetBits = [];
      if (Number.isFinite(Number(shape.offset_m))) {
        offsetBits.push(`отступ ${metersToCentimeters(shape.offset_m)} см`);
      }
      if (Number.isFinite(Number(shape.depth_m))) {
        offsetBits.push(`глубина ${metersToCentimeters(shape.depth_m)} см`);
      }
      if (shape.cut_line) {
        const cutBits = [];
        const cutSegmentIndex = Number.isInteger(shape.cut_segment_index) ? shape.cut_segment_index : shape.source_segment_index;
        if (Number.isInteger(cutSegmentIndex)) {
          cutBits.push(`разрез ${segmentLabel(cutSegmentIndex)}`);
        }
        if (Number.isFinite(Number(shape.cut_offset_m))) {
          cutBits.push(`${metersToCentimeters(shape.cut_offset_m)} см`);
        }
        offsetBits.push(cutBits.length > 0 ? cutBits.join(' · ') : 'есть разрез');
      }
      const modeLabel = shape.separate_panel ? 'Отдельное полотно' : attachmentLabel;
      const metaLabel = offsetBits.length > 0 ? `${modeLabel} · ${offsetBits.join(' · ')}` : modeLabel;
      row.innerHTML = `
        <span class="feature-row-dot" style="background:${featureKindColors[shape.kind] || '#7c3aed'}"></span>
        <div>
          <div class="feature-row-title">${shape.label && shape.label.trim() !== '' ? shape.label : (featureKindLabels[shape.kind] || shape.kind)} · ${featureFigureLabels[shape.figure] || shape.figure}</div>
          <div class="feature-row-subtitle">${metaLabel} · ${sizeLabel}</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger">x</button>
      `;

      row.addEventListener('click', (event) => {
        if (event.target.closest('button')) return;
        setSelectedFeature(index);
        render({ syncList: true, syncInput: false });
      });

      row.querySelector('button')?.addEventListener('click', () => {
        pushHistory();
        featureShapes.splice(index, 1);
        setSelectedFeature(Math.min(index, featureShapes.length - 1));
        render();
      });

      featureShapesList.appendChild(row);
    });
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

  const renderLightLinesList = () => {
    if (!lightLinesList) return;

    lightLinesList.innerHTML = '';
    if (lightLineShapes.length === 0) {
      const empty = document.createElement('div');
      empty.className = 'small text-muted';
      empty.textContent = 'Пока нет конструкций световых линий.';
      lightLinesList.appendChild(empty);
      return;
    }

    lightLineShapes.forEach((shape, index) => {
      const row = document.createElement('div');
      row.className = `feature-row${selectedLightLineIndex === index ? ' is-selected' : ''}`;
      const lengthMeters = polylineLength(shape.points, shape.closed);
      row.innerHTML = `
        <span class="feature-row-dot" style="background:${lightLineColor}"></span>
        <div>
          <div class="feature-row-title">${shape.label && shape.label.trim() !== '' ? shape.label : `Линия ${index + 1}`}</div>
          <div class="feature-row-subtitle">${shape.closed ? 'Замкнута' : 'Открыта'} · ${shape.points.length} тчк · ${metersToCentimeters(lengthMeters)} см · профиль ${metersToCentimeters(shape.width_m)} см</div>
        </div>
        <button type="button" class="btn btn-sm btn-outline-danger">x</button>
      `;
      row.addEventListener('click', (event) => {
        if (event.target.closest('button')) return;
        setSelectedLightLine(index);
        render({ syncList: true, syncInput: false });
      });
      row.querySelector('button')?.addEventListener('click', () => {
        pushHistory();
        lightLineShapes.splice(index, 1);
        setSelectedLightLine(Math.min(index, lightLineShapes.length - 1));
        render();
      });
      lightLinesList.appendChild(row);
    });
  };

  const renderList = () => {
    renderPointsList();
    renderSegmentsList();
    renderAnglesList();
    renderFeatureShapesList();
    renderLightLinesList();
    renderLightLinePanelsList();
    syncSelectedInspector();
    syncSelectedFeatureInspector();
    setSelectedLightLine(selectedLightLineIndex);
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
  const geometryBounds = (pointSet = points, elementSet = roomElements, shapeSet = featureShapes, lineSet = lightLineShapes) => {
    const freeElements = elementSet.filter((element) => (element.placement_mode ?? 'free') !== 'wall' && element.x_m !== null && element.y_m !== null);
    const shapePoints = shapeSet.flatMap((shape) => ([
      { x: Number(shape.x_m ?? 0), y: Number(shape.y_m ?? 0) },
      { x: Number(shape.x_m ?? 0) + Number(shape.width_m ?? 0), y: Number(shape.y_m ?? 0) + Number(shape.height_m ?? 0) },
    ]));
    const linePoints = lineSet.flatMap((shape) => Array.isArray(shape.points) ? shape.points.map((point) => ({ x: Number(point.x ?? 0), y: Number(point.y ?? 0) })) : []);
    return [...pointSet, ...freeElements.map((element) => ({ x: Number(element.x_m), y: Number(element.y_m) })), ...shapePoints, ...linePoints].reduce((carry, point) => ({
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
  const normalizeGeometry = (nextPoints, nextElements, nextFeatureShapes = featureShapes, nextLightLines = lightLineShapes) => {
    const bounds = geometryBounds(nextPoints, nextElements, nextFeatureShapes, nextLightLines);
    if (!Number.isFinite(bounds.minX) || !Number.isFinite(bounds.minY)) {
      return { points: nextPoints, elements: nextElements, featureShapes: nextFeatureShapes, lightLineShapes: nextLightLines };
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

    const normalizedFeatureShapes = nextFeatureShapes.map((shape, index) => normalizeFeatureShape({
      ...shape,
      x_m: round(clamp(Number(shape.x_m ?? 0) + shiftX, 0, workspaceWidth)),
      y_m: round(clamp(Number(shape.y_m ?? 0) + shiftY, 0, workspaceHeight)),
    }, index)).filter(Boolean);
    const normalizedLightLines = nextLightLines.map((shape, index) => normalizeLightLineShape({
      ...shape,
      points: (shape.points ?? []).map((point) => ({
        x: round(clamp(Number(point.x ?? 0) + shiftX, 0, workspaceWidth)),
        y: round(clamp(Number(point.y ?? 0) + shiftY, 0, workspaceHeight)),
      })),
    }, index)).filter(Boolean);

    return {
      points: normalizedPoints,
      elements: normalizedElements,
      featureShapes: normalizedFeatureShapes,
      lightLineShapes: normalizedLightLines,
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
    const nextFeatureShapes = cloneFeatureShapes().map((shape, index) => {
      const topLeft = transformPoint({
        x: Number(shape.x_m),
        y: Number(shape.y_m),
      });
      const bottomRight = transformPoint({
        x: Number(shape.x_m) + Number(shape.width_m),
        y: Number(shape.y_m) + Number(shape.height_m),
      });

      return normalizeFeatureShape({
        ...shape,
        x_m: Math.min(topLeft.x, bottomRight.x),
        y_m: Math.min(topLeft.y, bottomRight.y),
        width_m: Math.abs(bottomRight.x - topLeft.x),
        height_m: Math.abs(bottomRight.y - topLeft.y),
      }, index);
    }).filter(Boolean);
    const nextLightLines = cloneLightLineShapes().map((shape, index) => normalizeLightLineShape({
      ...shape,
      points: (shape.points ?? []).map(transformPoint),
    }, index)).filter(Boolean);

    const normalized = normalizeGeometry(nextPoints, nextElements, nextFeatureShapes, nextLightLines);
    points = normalized.points;
    roomElements.splice(0, roomElements.length, ...normalized.elements);
    featureShapes.splice(0, featureShapes.length, ...(normalized.featureShapes ?? []));
    lightLineShapes.splice(0, lightLineShapes.length, ...(normalized.lightLineShapes ?? []));
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
    if (selectedFeatureIndex >= featureShapes.length) {
      selectedFeatureIndex = featureShapes.length > 0 ? featureShapes.length - 1 : -1;
    }
    if (selectedLightLineIndex >= lightLineShapes.length) {
      selectedLightLineIndex = lightLineShapes.length > 0 ? lightLineShapes.length - 1 : -1;
    }

    if (syncInput) {
      writeInput();
    }
    if (syncList) {
      lightLinePanelsPreview = estimateLightLinePanels();
      renderList();
    }
    updateExistingPlacementFields();
    syncPolygonFeatureControls();
    syncProductionInputs();
    syncProductionSummary();
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
    const featureStrokeWidth = pixelsToWorld(2.5);
    const featureSelectedStrokeWidth = pixelsToWorld(4);
    const featureLabelSize = pixelsToWorld(12);
    const lightLineStrokeWidth = pixelsToWorld(2.5);
    const lightLineSelectedStrokeWidth = pixelsToWorld(4);
    const lightLineHitWidth = pixelsToWorld(28);
    const lightLineHandleRadius = pixelsToWorld(6);
    const lightLineSelectedHandleRadius = pixelsToWorld(8);
    const lightLineLabelSize = pixelsToWorld(11);

    const polygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
    polygon.setAttribute('points', points.map((point) => `${point.x},${point.y}`).join(' '));
    polygon.setAttribute('fill', 'rgba(37, 99, 235, 0.18)');
    polygon.setAttribute('stroke', '#2563eb');
    polygon.setAttribute('stroke-width', polygonStrokeWidth);
    polygon.dataset.kind = 'polygon';
    layer.appendChild(polygon);

    const productionGuide = productionGuideGeometry();
    if (productionGuide) {
      const guideLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
      guideLine.setAttribute('x1', productionGuide.start.x);
      guideLine.setAttribute('y1', productionGuide.start.y);
      guideLine.setAttribute('x2', productionGuide.end.x);
      guideLine.setAttribute('y2', productionGuide.end.y);
      guideLine.setAttribute('stroke', '#059669');
      guideLine.setAttribute('stroke-width', pixelsToWorld(2));
      guideLine.setAttribute('stroke-dasharray', `${pixelsToWorld(10)} ${pixelsToWorld(6)}`);
      guideLine.setAttribute('opacity', '0.75');
      guideLine.style.pointerEvents = 'none';
      layer.appendChild(guideLine);

      const guideLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      guideLabel.setAttribute('x', round((productionGuide.start.x + productionGuide.end.x) / 2));
      guideLabel.setAttribute('y', round((productionGuide.start.y + productionGuide.end.y) / 2 - pixelsToWorld(10)));
      guideLabel.setAttribute('fill', '#047857');
      guideLabel.setAttribute('font-size', labelFontSize);
      guideLabel.setAttribute('font-weight', '700');
      guideLabel.setAttribute('text-anchor', 'middle');
      guideLabel.setAttribute('paint-order', 'stroke');
      guideLabel.setAttribute('stroke', '#ffffff');
      guideLabel.setAttribute('stroke-width', labelStrokeWidth);
      guideLabel.style.pointerEvents = 'none';
      guideLabel.textContent = `Полотно ${segmentLabel(productionGuide.segmentIndex)} · ${metersToCentimeters(Number(productionSettings.orientation_offset_m ?? 0))} см`;
      layer.appendChild(guideLabel);

      if (productionSettings.seam_enabled) {
        const seamGuide = productionGuideGeometry(Number(productionSettings.orientation_offset_m ?? 0) + Number(productionSettings.seam_offset_m ?? 0));
        if (seamGuide) {
          const seamLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
          seamLine.setAttribute('x1', seamGuide.start.x);
          seamLine.setAttribute('y1', seamGuide.start.y);
          seamLine.setAttribute('x2', seamGuide.end.x);
          seamLine.setAttribute('y2', seamGuide.end.y);
          seamLine.setAttribute('stroke', '#7c3aed');
          seamLine.setAttribute('stroke-width', pixelsToWorld(2));
          seamLine.setAttribute('stroke-dasharray', `${pixelsToWorld(7)} ${pixelsToWorld(5)}`);
          seamLine.setAttribute('opacity', '0.85');
          seamLine.style.pointerEvents = 'none';
          layer.appendChild(seamLine);
        }
      }
    }

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
        const inwardNormal = getSegmentNormal(index, true);
        dragSegmentState = {
          index,
          startPointer: pointerToSvg(event.clientX, event.clientY),
          startA: { ...segment.start },
          startB: { ...segment.end },
          inwardNormal,
          currentOffsetMeters: 0,
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

    if (Array.isArray(featurePolygonDraft) && featurePolygonDraft.length > 0) {
      const draftPolyline = document.createElementNS('http://www.w3.org/2000/svg', featurePolygonDraft.length >= 3 ? 'polygon' : 'polyline');
      draftPolyline.setAttribute('points', featurePolygonDraft.map((point) => `${point.x},${point.y}`).join(' '));
      draftPolyline.setAttribute('fill', featurePolygonDraft.length >= 3 ? 'rgba(124, 58, 237, 0.10)' : 'transparent');
      draftPolyline.setAttribute('stroke', '#7c3aed');
      draftPolyline.setAttribute('stroke-width', featureStrokeWidth);
      draftPolyline.setAttribute('stroke-dasharray', `${pixelsToWorld(8)} ${pixelsToWorld(5)}`);
      draftPolyline.style.pointerEvents = 'none';
      layer.appendChild(draftPolyline);

      featurePolygonDraft.forEach((point, index) => {
        const draftHandle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        draftHandle.setAttribute('cx', point.x);
        draftHandle.setAttribute('cy', point.y);
        draftHandle.setAttribute('r', index === 0 ? selectedPointRadius : pointRadius);
        draftHandle.setAttribute('fill', index === 0 ? '#7c3aed' : '#a855f7');
        draftHandle.setAttribute('stroke', '#ffffff');
        draftHandle.setAttribute('stroke-width', labelStrokeWidth);
        draftHandle.style.pointerEvents = 'none';
        layer.appendChild(draftHandle);
      });
    }

    if (Array.isArray(lightLineDraft) && lightLineDraft.length > 0) {
      const draftLine = document.createElementNS('http://www.w3.org/2000/svg', lightLineDraft.length >= 3 ? 'polyline' : 'polyline');
      draftLine.setAttribute('points', lightLineDraft.map((point) => `${point.x},${point.y}`).join(' '));
      draftLine.setAttribute('fill', 'none');
      draftLine.setAttribute('stroke', lightLineColor);
      draftLine.setAttribute('stroke-width', Math.max(centimetersToMeters(lightLineWidthInput?.value) ?? 0.05, pixelsToWorld(6)));
      draftLine.setAttribute('stroke-linecap', 'round');
      draftLine.setAttribute('stroke-linejoin', 'round');
      draftLine.setAttribute('stroke-dasharray', `${pixelsToWorld(8)} ${pixelsToWorld(5)}`);
      draftLine.setAttribute('opacity', '0.35');
      draftLine.style.pointerEvents = 'none';
      layer.appendChild(draftLine);

      const draftCenter = document.createElementNS('http://www.w3.org/2000/svg', 'polyline');
      draftCenter.setAttribute('points', lightLineDraft.map((point) => `${point.x},${point.y}`).join(' '));
      draftCenter.setAttribute('fill', 'none');
      draftCenter.setAttribute('stroke', '#c2410c');
      draftCenter.setAttribute('stroke-width', lightLineSelectedStrokeWidth);
      draftCenter.setAttribute('stroke-linecap', 'round');
      draftCenter.setAttribute('stroke-linejoin', 'round');
      draftCenter.style.pointerEvents = 'none';
      layer.appendChild(draftCenter);

      lightLineDraft.forEach((point, index) => {
        const draftHandle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
        draftHandle.setAttribute('cx', point.x);
        draftHandle.setAttribute('cy', point.y);
        draftHandle.setAttribute('r', index === 0 ? lightLineSelectedHandleRadius : lightLineHandleRadius);
        draftHandle.setAttribute('fill', index === 0 ? '#c2410c' : '#f97316');
        draftHandle.setAttribute('stroke', '#ffffff');
        draftHandle.setAttribute('stroke-width', labelStrokeWidth);
        draftHandle.style.pointerEvents = 'none';
        layer.appendChild(draftHandle);
      });
    }

    lightLineShapes.forEach((shape, index) => {
      const isSelected = selectedLightLineIndex === index;
      const pointsAttr = shape.points.map((point) => `${point.x},${point.y}`).join(' ');
      const visibleWidth = Math.max(Number(shape.width_m ?? 0.05), pixelsToWorld(6));
      const centerWidth = isSelected ? lightLineSelectedStrokeWidth : lightLineStrokeWidth;
      const bounds = lineBounds(shape);
      if (!bounds) return;

      const body = document.createElementNS('http://www.w3.org/2000/svg', shape.closed ? 'polygon' : 'polyline');
      body.setAttribute('points', pointsAttr);
      body.setAttribute('fill', shape.closed ? `${lightLineColor}20` : 'none');
      body.setAttribute('stroke', lightLineColor);
      body.setAttribute('stroke-width', visibleWidth);
      body.setAttribute('stroke-linecap', 'round');
      body.setAttribute('stroke-linejoin', 'round');
      body.setAttribute('opacity', isSelected ? '0.38' : '0.28');
      body.style.pointerEvents = 'none';
      layer.appendChild(body);

      const center = document.createElementNS('http://www.w3.org/2000/svg', shape.closed ? 'polygon' : 'polyline');
      center.setAttribute('points', pointsAttr);
      center.setAttribute('fill', 'none');
      center.setAttribute('stroke', isSelected ? '#c2410c' : '#ea580c');
      center.setAttribute('stroke-width', centerWidth);
      center.setAttribute('stroke-linecap', 'round');
      center.setAttribute('stroke-linejoin', 'round');
      center.style.pointerEvents = 'none';
      layer.appendChild(center);

      const hit = document.createElementNS('http://www.w3.org/2000/svg', shape.closed ? 'polygon' : 'polyline');
      hit.setAttribute('points', pointsAttr);
      hit.setAttribute('fill', shape.closed ? 'transparent' : 'none');
      hit.setAttribute('stroke', 'transparent');
      hit.setAttribute('stroke-width', Math.max(visibleWidth, lightLineHitWidth));
      hit.setAttribute('stroke-linecap', 'round');
      hit.setAttribute('stroke-linejoin', 'round');
      hit.dataset.kind = 'light-line-shape';
      hit.dataset.lightLineIndex = String(index);
      hit.style.cursor = 'move';
      hit.addEventListener('pointerdown', (event) => {
        if (shouldStartPan(event)) {
          beginPan(event);
          return;
        }
        if (event.button !== 0) return;
        event.stopPropagation();
        pushHistory();
        setSelectedLightLine(index);
        dragLightLineShapeState = {
          index,
          startPointer: pointerToSvg(event.clientX, event.clientY),
          startShape: normalizeLightLineShape(shape, index),
        };
        render({ syncList: true, syncInput: false });
      });
      hit.addEventListener('click', (event) => {
        if (suppressCanvasClick) return;
        event.stopPropagation();
        setSelectedLightLine(index);
        render({ syncList: true, syncInput: false });
      });
      layer.appendChild(hit);

      if (isSelected) {
        shape.points.forEach((point, pointIndex) => {
          const pointHit = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
          pointHit.setAttribute('cx', point.x);
          pointHit.setAttribute('cy', point.y);
          pointHit.setAttribute('r', pointHitRadius);
          pointHit.setAttribute('fill', 'transparent');
          pointHit.dataset.kind = 'light-line-point';
          pointHit.dataset.lightLineIndex = String(index);
          pointHit.dataset.lightLinePointIndex = String(pointIndex);
          pointHit.style.cursor = 'grab';
          pointHit.addEventListener('pointerdown', (event) => {
            if (shouldStartPan(event)) {
              beginPan(event);
              return;
            }
            if (event.button !== 0) return;
            event.stopPropagation();
            pushHistory();
            setSelectedLightLine(index);
            dragLightLinePointState = {
              shapeIndex: index,
              pointIndex,
            };
            render({ syncList: true, syncInput: false });
          });
          pointHit.addEventListener('click', (event) => {
            event.stopPropagation();
            setSelectedLightLine(index);
            render({ syncList: true, syncInput: false });
          });
          layer.appendChild(pointHit);

          const pointHandle = document.createElementNS('http://www.w3.org/2000/svg', 'circle');
          pointHandle.setAttribute('cx', point.x);
          pointHandle.setAttribute('cy', point.y);
          pointHandle.setAttribute('r', pointIndex === 0 ? lightLineSelectedHandleRadius : lightLineHandleRadius);
          pointHandle.setAttribute('fill', pointIndex === 0 ? '#c2410c' : '#fb923c');
          pointHandle.setAttribute('stroke', '#ffffff');
          pointHandle.setAttribute('stroke-width', labelStrokeWidth);
          pointHandle.style.pointerEvents = 'none';
          layer.appendChild(pointHandle);
        });
      }

      const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      label.setAttribute('x', round((bounds.minX + bounds.maxX) / 2));
      label.setAttribute('y', round(bounds.minY - pixelsToWorld(10)));
      label.setAttribute('fill', isSelected ? '#c2410c' : '#9a3412');
      label.setAttribute('font-size', lightLineLabelSize);
      label.setAttribute('font-weight', '700');
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('paint-order', 'stroke');
      label.setAttribute('stroke', '#ffffff');
      label.setAttribute('stroke-width', labelStrokeWidth);
      label.style.pointerEvents = 'none';
      label.textContent = shape.label && shape.label.trim() !== ''
        ? `${shape.label} · ${metersToCentimeters(polylineLength(shape.points, shape.closed))} см`
        : `Световая линия ${index + 1}`;
      layer.appendChild(label);
    });

    featureShapes.forEach((shape, index) => {
      const bounds = featureShapeBounds(shape);
      const shapePoints = featureShapePoints(shape);
      const color = featureKindColors[shape.kind] || '#7c3aed';
      const isSelected = selectedFeatureIndex === index;
      const strokeWidth = isSelected ? featureSelectedStrokeWidth : featureStrokeWidth;

      const hit = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
      hit.setAttribute('x', bounds.left);
      hit.setAttribute('y', bounds.top);
      hit.setAttribute('width', bounds.width);
      hit.setAttribute('height', bounds.height);
      hit.setAttribute('fill', 'transparent');
      hit.setAttribute('stroke', 'transparent');
      hit.setAttribute('stroke-width', pixelsToWorld(10));
      hit.dataset.kind = 'feature-shape';
      hit.style.cursor = 'move';
      hit.addEventListener('pointerdown', (event) => {
        if (shouldStartPan(event)) {
          beginPan(event);
          return;
        }
        if (event.button !== 0) return;
        event.stopPropagation();
        pushHistory();
        setSelectedFeature(index);
        dragFeatureState = {
          index,
          startPointer: pointerToSvg(event.clientX, event.clientY),
          startShape: normalizeFeatureShape(shape, index),
        };
        render({ syncList: true, syncInput: false });
      });
      hit.addEventListener('click', (event) => {
        if (suppressCanvasClick) return;
        event.stopPropagation();
        setSelectedFeature(index);
        render({ syncList: true, syncInput: false });
      });
      layer.appendChild(hit);

      if (shape.cut_line) {
        const connector = featureCutConnector(shape);
        if (connector) {
          const cutLine = document.createElementNS('http://www.w3.org/2000/svg', 'line');
          cutLine.setAttribute('x1', connector.start.x);
          cutLine.setAttribute('y1', connector.start.y);
          cutLine.setAttribute('x2', connector.end.x);
          cutLine.setAttribute('y2', connector.end.y);
          cutLine.setAttribute('stroke', color);
          cutLine.setAttribute('stroke-width', pixelsToWorld(2));
          cutLine.setAttribute('stroke-dasharray', `${pixelsToWorld(6)} ${pixelsToWorld(4)}`);
          cutLine.style.pointerEvents = 'none';
          layer.appendChild(cutLine);

          const cutLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
          cutLabel.setAttribute('x', round((connector.start.x + connector.end.x) / 2));
          cutLabel.setAttribute('y', round((connector.start.y + connector.end.y) / 2) - pixelsToWorld(6));
          cutLabel.setAttribute('fill', color);
          cutLabel.setAttribute('font-size', pixelsToWorld(10));
          cutLabel.setAttribute('font-weight', '700');
          cutLabel.setAttribute('text-anchor', 'middle');
          cutLabel.setAttribute('paint-order', 'stroke');
          cutLabel.setAttribute('stroke', '#ffffff');
          cutLabel.setAttribute('stroke-width', labelStrokeWidth);
          cutLabel.style.pointerEvents = 'none';
          cutLabel.textContent = 'Разрез';
          layer.appendChild(cutLabel);
        }
      }

      if (Array.isArray(shape.shape_points) && shape.shape_points.length >= 3) {
        const polygonShape = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        polygonShape.setAttribute('points', shapePoints.map((point) => `${point.x},${point.y}`).join(' '));
        polygonShape.setAttribute('fill', `${color}22`);
        polygonShape.setAttribute('stroke', color);
        polygonShape.setAttribute('stroke-width', strokeWidth);
        polygonShape.style.pointerEvents = 'none';
        layer.appendChild(polygonShape);
      } else if (shape.figure === 'circle') {
        const ellipse = document.createElementNS('http://www.w3.org/2000/svg', 'ellipse');
        ellipse.setAttribute('cx', round(bounds.left + (bounds.width / 2)));
        ellipse.setAttribute('cy', round(bounds.top + (bounds.height / 2)));
        ellipse.setAttribute('rx', round(bounds.width / 2));
        ellipse.setAttribute('ry', round(bounds.height / 2));
        ellipse.setAttribute('fill', `${color}22`);
        ellipse.setAttribute('stroke', color);
        ellipse.setAttribute('stroke-width', strokeWidth);
        ellipse.style.pointerEvents = 'none';
        layer.appendChild(ellipse);
      } else if (shape.figure === 'triangle') {
        const triangle = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
        triangle.setAttribute('points', [
          `${bounds.left},${bounds.bottom}`,
          `${bounds.left},${bounds.top}`,
          `${bounds.right},${bounds.bottom}`,
        ].join(' '));
        triangle.setAttribute('fill', `${color}22`);
        triangle.setAttribute('stroke', color);
        triangle.setAttribute('stroke-width', strokeWidth);
        triangle.style.pointerEvents = 'none';
        layer.appendChild(triangle);
      } else {
        const rect = document.createElementNS('http://www.w3.org/2000/svg', 'rect');
        rect.setAttribute('x', bounds.left);
        rect.setAttribute('y', bounds.top);
        rect.setAttribute('width', bounds.width);
        rect.setAttribute('height', bounds.height);
        rect.setAttribute('fill', `${color}18`);
        rect.setAttribute('stroke', color);
        rect.setAttribute('stroke-width', strokeWidth);
        rect.style.pointerEvents = 'none';
        layer.appendChild(rect);
      }

      const label = document.createElementNS('http://www.w3.org/2000/svg', 'text');
      label.setAttribute('x', round(bounds.left + (bounds.width / 2)));
      label.setAttribute('y', round(bounds.top - pixelsToWorld(10)));
      label.setAttribute('fill', color);
      label.setAttribute('font-size', featureLabelSize);
      label.setAttribute('font-weight', '700');
      label.setAttribute('text-anchor', 'middle');
      label.setAttribute('paint-order', 'stroke');
      label.setAttribute('stroke', '#ffffff');
      label.setAttribute('stroke-width', labelStrokeWidth);
      label.style.pointerEvents = 'none';
      label.textContent = shape.label && shape.label.trim() !== ''
        ? shape.label
        : `${featureKindLabels[shape.kind] || shape.kind} · ${featureFigureLabels[shape.figure] || shape.figure}`;
      layer.appendChild(label);
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

    if (Array.isArray(lightLinePanelsPreview) && lightLinePanelsPreview.length > 1) {
      lightLinePanelsPreview.forEach((panel, index) => {
        if (Array.isArray(panel.shape_points) && panel.shape_points.length >= 3) {
          const panelPolygon = document.createElementNS('http://www.w3.org/2000/svg', 'polygon');
          panelPolygon.setAttribute('points', panel.shape_points.map((point) => `${point.x},${point.y}`).join(' '));
          panelPolygon.setAttribute('fill', panel.source === 'seam_split' ? 'rgba(124, 58, 237, 0.12)' : 'rgba(5, 150, 105, 0.08)');
          panelPolygon.setAttribute('stroke', panel.source === 'seam_split' ? '#7c3aed' : '#059669');
          panelPolygon.setAttribute('stroke-width', pixelsToWorld(panel.source === 'seam_split' ? 2.2 : 1.4));
          panelPolygon.setAttribute('stroke-dasharray', panel.source === 'seam_split' ? `${pixelsToWorld(8)} ${pixelsToWorld(5)}` : `${pixelsToWorld(4)} ${pixelsToWorld(4)}`);
          panelPolygon.setAttribute('opacity', panel.source === 'seam_split' ? '0.95' : '0.7');
          panelPolygon.style.pointerEvents = 'none';
          layer.appendChild(panelPolygon);
        }

        if (!panel?.centroid) return;
        const panelLabel = document.createElementNS('http://www.w3.org/2000/svg', 'text');
        panelLabel.setAttribute('x', panel.centroid.x);
        panelLabel.setAttribute('y', panel.centroid.y + pixelsToWorld(22));
        panelLabel.setAttribute('fill', panel.source === 'seam_split' ? '#6d28d9' : '#047857');
        panelLabel.setAttribute('font-size', pixelsToWorld(12));
        panelLabel.setAttribute('font-weight', '700');
        panelLabel.setAttribute('text-anchor', 'middle');
        panelLabel.setAttribute('paint-order', 'stroke');
        panelLabel.setAttribute('stroke', '#ffffff');
        panelLabel.setAttribute('stroke-width', labelStrokeWidth);
        panelLabel.style.pointerEvents = 'none';
        const panelMeta = Number.isFinite(Number(panel.seam_part_index)) ? ` · часть ${panel.seam_part_index}` : '';
        panelLabel.textContent = `${panel.label ? panel.label : `П${index + 1}`} · ${String(panel.area_m2).replace('.', ',')} м2${panelMeta}`;
        layer.appendChild(panelLabel);
      });
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
    if (targetKind === 'point-handle' || targetKind === 'element-marker' || targetKind === 'feature-shape' || targetKind === 'light-line-shape' || targetKind === 'light-line-point') return;
    if (tag !== 'svg' && tag !== 'rect' && tag !== 'image' && targetKind !== 'polygon') return;

    const point = pointerToSvg(event.clientX, event.clientY);
    if (activeMode === 'hand') {
      return;
    }
    if (Array.isArray(featurePolygonDraft)) {
      const shouldClose = featurePolygonDraft.length >= 3
        && distanceBetweenPoints(point, featurePolygonDraft[0]) <= pixelsToWorld(18);

      if (shouldClose) {
        finalizePolygonFeatureDraft();
      } else {
        featurePolygonDraft.push(point);
        syncPolygonFeatureControls();
        render({ syncList: false, syncInput: false });
      }
      return;
    }
    if (Array.isArray(lightLineDraft)) {
      if (event.detail > 1) {
        return;
      }
      const anchor = lightLineDraft.length > 0 ? lightLineDraft[lightLineDraft.length - 1] : null;
      lightLineDraft.push(snapLightLinePoint(point, anchor));
      render({ syncList: false, syncInput: false });
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

  svg.addEventListener('dblclick', (event) => {
    if (!Array.isArray(lightLineDraft) || lightLineDraft.length < 2) {
      return;
    }

    const tag = event.target.tagName.toLowerCase();
    const targetKind = event.target.dataset.kind || '';
    if (targetKind === 'point-handle' || targetKind === 'element-marker' || targetKind === 'feature-shape' || targetKind === 'light-line-shape' || targetKind === 'light-line-point') return;
    if (tag !== 'svg' && tag !== 'rect' && tag !== 'image' && targetKind !== 'polygon') return;

    event.preventDefault();
    event.stopPropagation();
    finishLightLineDraft();
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

      const baseDx = dragSegmentState.startB.x - dragSegmentState.startA.x;
      const baseDy = dragSegmentState.startB.y - dragSegmentState.startA.y;
      const baseLength = Math.hypot(baseDx, baseDy);
      const inwardNormal = dragSegmentState.inwardNormal ?? (baseLength > 0
        ? { x: round(-baseDy / baseLength), y: round(baseDx / baseLength) }
        : { x: 0, y: 0 });
      dragSegmentState.currentOffsetMeters = round((delta.x * inwardNormal.x) + (delta.y * inwardNormal.y));

      points[dragSegmentState.index] = {
        x: round(clamp(dragSegmentState.startA.x + delta.x, 0, workspaceWidth)),
        y: round(clamp(dragSegmentState.startA.y + delta.y, 0, workspaceHeight)),
      };
      points[(dragSegmentState.index + 1) % points.length] = {
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
      return;
    }

    if (dragFeatureState) {
      const nextPoint = pointerToSvg(event.clientX, event.clientY);
      const shape = featureShapes[dragFeatureState.index];
      const startShape = dragFeatureState.startShape;
      if (!shape || !startShape) return;

      const deltaX = nextPoint.x - dragFeatureState.startPointer.x;
      const deltaY = nextPoint.y - dragFeatureState.startPointer.y;
      const translated = translateFeatureShape(startShape, deltaX, deltaY);
      if (!translated) return;

      featureShapes[dragFeatureState.index] = translated;
      setSelectedFeature(dragFeatureState.index);
      scheduleRender({ syncList: false, syncInput: true });
      return;
    }

    if (dragLightLinePointState) {
      const shape = lightLineShapes[dragLightLinePointState.shapeIndex];
      if (!shape || !shape.points[dragLightLinePointState.pointIndex]) return;

      const previousPoint = dragLightLinePointState.pointIndex > 0
        ? shape.points[dragLightLinePointState.pointIndex - 1]
        : null;
      shape.points[dragLightLinePointState.pointIndex] = snapLightLinePoint(pointerToSvg(event.clientX, event.clientY), previousPoint);
      lightLineShapes[dragLightLinePointState.shapeIndex] = normalizeLightLineShape(shape, dragLightLinePointState.shapeIndex) ?? shape;
      setSelectedLightLine(dragLightLinePointState.shapeIndex);
      scheduleRender({ syncList: false, syncInput: true });
      return;
    }

    if (dragLightLineShapeState) {
      const nextPoint = pointerToSvg(event.clientX, event.clientY);
      const startShape = dragLightLineShapeState.startShape;
      const shape = lightLineShapes[dragLightLineShapeState.index];
      if (!shape || !startShape) return;

      const deltaX = nextPoint.x - dragLightLineShapeState.startPointer.x;
      const deltaY = nextPoint.y - dragLightLineShapeState.startPointer.y;
      lightLineShapes[dragLightLineShapeState.index] = normalizeLightLineShape({
        ...startShape,
        points: startShape.points.map((point) => normalizeCanvasPoint({
          x: point.x + deltaX,
          y: point.y + deltaY,
        })),
      }, dragLightLineShapeState.index) ?? shape;
      setSelectedLightLine(dragLightLineShapeState.index);
      scheduleRender({ syncList: false, syncInput: true });
    }
  });

  window.addEventListener('pointerup', () => {
    const shouldClearSuppression = panState !== null && suppressCanvasClick;
    dragPointIndex = null;
    dragSegmentState = null;
    dragElementIndex = null;
    dragFeatureState = null;
    dragLightLinePointState = null;
    dragLightLineShapeState = null;
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
  insertPointAtOffsetBtn?.addEventListener('click', () => {
    const segment = getSegmentGeometry(selectedSegmentIndex);
    const offset = centimetersToMeters(insertPointOffsetInput?.value);
    if (!segment || offset === null) return;
    setInspectorTab('points');
    insertPointOnSegmentAtOffset(selectedSegmentIndex, clamp(offset, 0, segment.length));
  });
  insertPointByCoordinatesBtn?.addEventListener('click', () => {
    const x = centimetersToMeters(manualPointXInput?.value);
    const y = centimetersToMeters(manualPointYInput?.value);
    if (x === null || y === null) return;
    setInspectorTab('points');
    insertPointByCoordinates(x, y);
  });
  const applyWallShift = (inward = true) => {
    const offset = getWallShiftValueMeters();
    if (!offset) return;
    const shape = buildWallOffsetFeature(offset, inward);
    if (!shape) return;
    pushHistory();
    featureShapes.push(shape);
    setSelectedFeature(featureShapes.length - 1);
    setInspectorTab('features');
    render();
  };
  applyWallShiftBtn?.addEventListener('click', () => applyWallShift(true));
  wallShiftInwardBtn?.addEventListener('click', () => applyWallShift(true));
  wallShiftOutwardBtn?.addEventListener('click', () => applyWallShift(false));
  addFeatureShapeBtn?.addEventListener('click', () => {
    if ((featureFigureInput?.value ?? 'rectangle') === 'polygon') {
      startPolygonFeatureDraft();
      return;
    }
    if ((featureFigureInput?.value ?? 'rectangle') === 'rounded_corner') {
      roundCornerFeatureBtn?.click();
      return;
    }
    if ((featureFigureInput?.value ?? 'rectangle') === 'arc') {
      addFeatureFromWallBtn?.click();
      return;
    }
    const shape = buildFeatureShapeFromInputs();
    if (!shape) return;
    pushHistory();
    featureShapes.push(shape);
    setSelectedFeature(featureShapes.length - 1);
    render();
  });
  addFeatureFromWallBtn?.addEventListener('click', () => {
    const shape = buildFeatureFromSelectedSegment();
    if (!shape) return;
    pushHistory();
    featureShapes.push(shape);
    setSelectedFeature(featureShapes.length - 1);
    setInspectorTab('segments');
    render();
  });
  roundCornerFeatureBtn?.addEventListener('click', () => {
    const shape = buildRoundedCornerFeature();
    if (!shape) return;
    pushHistory();
    featureShapes.push(shape);
    setSelectedFeature(featureShapes.length - 1);
    setInspectorTab('points');
    render();
  });
  startPolygonFeatureBtn?.addEventListener('click', startPolygonFeatureDraft);
  finishPolygonFeatureBtn?.addEventListener('click', finalizePolygonFeatureDraft);
  cancelPolygonFeatureBtn?.addEventListener('click', cancelPolygonFeatureDraft);
  updateFeatureShapeBtn?.addEventListener('click', () => {
    if (selectedFeatureIndex < 0 || !featureShapes[selectedFeatureIndex]) return;
    if (featureShapes[selectedFeatureIndex]?.figure === 'polygon') {
      const polygonKind = featureKindInput?.value ?? featureShapes[selectedFeatureIndex].kind;
      const polygonCutLine = Boolean(featureCutLineInput?.checked) && polygonKind === 'cutout';
      const polygonCutSegmentIndex = Number.isFinite(Number(featureCutSegmentInput?.value))
        ? Number(featureCutSegmentInput.value)
        : (Number.isInteger(featureShapes[selectedFeatureIndex]?.cut_segment_index) ? featureShapes[selectedFeatureIndex].cut_segment_index : null);
      const polygonCutSegment = Number.isInteger(polygonCutSegmentIndex) ? getSegmentGeometry(polygonCutSegmentIndex) : null;
      const polygonCutOffset = centimetersToMeters(featureCutOffsetInput?.value);
      const nextPolygonShape = normalizeFeatureShape({
        ...featureShapes[selectedFeatureIndex],
        kind: polygonKind,
        cut_line: polygonCutLine,
        cut_segment_index: polygonCutLine ? polygonCutSegmentIndex : null,
        cut_offset_m: polygonCutLine
          ? round(clamp(
              polygonCutOffset === null
                ? (Number(featureShapes[selectedFeatureIndex]?.cut_offset_m ?? 0) || 0)
                : polygonCutOffset,
              0,
              polygonCutSegment?.length ?? workspaceWidth
            ))
          : null,
        separate_panel: Boolean(featureSeparatePanelInput?.checked),
        label: featureLabelInput?.value ?? featureShapes[selectedFeatureIndex].label ?? '',
      }, selectedFeatureIndex);
      if (!nextPolygonShape) return;
      pushHistory();
      featureShapes[selectedFeatureIndex] = nextPolygonShape;
      render();
      return;
    }
    const shape = buildFeatureShapeFromInputs(featureShapes[selectedFeatureIndex]);
    if (!shape) return;
    pushHistory();
    featureShapes[selectedFeatureIndex] = shape;
    render();
  });
  deleteFeatureShapeBtn?.addEventListener('click', () => {
    if (selectedFeatureIndex < 0 || !featureShapes[selectedFeatureIndex]) return;
    pushHistory();
    featureShapes.splice(selectedFeatureIndex, 1);
    setSelectedFeature(Math.min(selectedFeatureIndex, featureShapes.length - 1));
    render();
  });
  featureKindInput?.addEventListener('change', () => syncFeatureCutControls(selectedFeatureIndex >= 0 ? featureShapes[selectedFeatureIndex] : null));
  featureCutLineInput?.addEventListener('change', () => syncFeatureCutControls(selectedFeatureIndex >= 0 ? featureShapes[selectedFeatureIndex] : null));
  featureCutSegmentInput?.addEventListener('change', () => syncFeatureCutControls(selectedFeatureIndex >= 0 ? featureShapes[selectedFeatureIndex] : null));
  startLightLineBtn?.addEventListener('click', startLightLineDraft);
  addLightLineTemplateBtn?.addEventListener('click', () => {
    const nextShapes = buildLightLineTemplate();
    if (nextShapes.length === 0) {
      return;
    }

    pushHistory();
    lightLineShapes.push(...nextShapes);
    setSelectedLightLine(lightLineShapes.length - 1);
    render();
  });
  toggleLightLineClosedBtn?.addEventListener('click', () => {
    if (selectedLightLineIndex < 0 || !lightLineShapes[selectedLightLineIndex]) {
      return;
    }

    pushHistory();
    lightLineShapes[selectedLightLineIndex].closed = !lightLineShapes[selectedLightLineIndex].closed;
    updateSelectedLightLineFromInputs();
    render();
  });
  deleteLightLineBtn?.addEventListener('click', deleteSelectedLightLine);
  lightLineLabelInput?.addEventListener('change', () => {
    if (selectedLightLineIndex < 0) return;
    updateSelectedLightLineFromInputs();
    render({ syncList: true, syncInput: true });
  });
  lightLineWidthInput?.addEventListener('change', () => {
    if (selectedLightLineIndex < 0) return;
    updateSelectedLightLineFromInputs();
    render({ syncList: true, syncInput: true });
  });
  [
    productionTextureInput,
    productionRollWidthInput,
    productionHarpoonInput,
    productionOrientationModeInput,
    productionOrientationSegmentInput,
    productionOrientationOffsetInput,
    productionShrinkXInput,
    productionShrinkYInput,
    productionSameRollInput,
    productionSpecialCuttingInput,
    productionSeamEnabledInput,
    productionSeamOffsetInput,
    productionCommentInput,
  ].filter(Boolean).forEach((inputElement) => {
    const eventName = inputElement instanceof HTMLSelectElement || (inputElement instanceof HTMLInputElement && inputElement.type === 'checkbox')
      ? 'change'
      : 'input';
    inputElement.addEventListener(eventName, () => {
      updateProductionSettingsFromInputs();
      render({ syncList: false, syncInput: true });
    });
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

  geometryEditorForm?.addEventListener('keydown', (event) => {
    if (event.key !== 'Enter') return;
    if (!(event.target instanceof HTMLElement)) return;
    if (event.target.tagName === 'TEXTAREA') return;
    if (event.target.tagName === 'BUTTON') return;

    event.preventDefault();

    if (event.target === selectedSegmentLengthInput || event.target === segmentStepInput) {
      applySegmentLengthBtn?.click();
      return;
    }
    if (event.target === selectedPointXInput || event.target === selectedPointYInput) {
      event.target.dispatchEvent(new Event('change', { bubbles: true }));
      return;
    }
    if (event.target === insertPointOffsetInput) {
      insertPointAtOffsetBtn?.click();
      return;
    }
    if (event.target === manualPointXInput || event.target === manualPointYInput) {
      insertPointByCoordinatesBtn?.click();
      return;
    }
    if (event.target === wallShiftOffsetInput) {
      applyWallShiftBtn?.click();
      return;
    }
    if ([
      featureKindInput,
      featureFigureInput,
      featureXInput,
      featureYInput,
      featureWidthInput,
      featureHeightInput,
      featureRadiusInput,
      featureWallOffsetInput,
      featureDepthInput,
      featureDirectionInput,
      featureCutSegmentInput,
      featureCutOffsetInput,
      featureLabelInput,
    ].includes(event.target)) {
      if (featureFigureInput?.value === 'polygon') {
        if (Array.isArray(featurePolygonDraft)) {
          finishPolygonFeatureBtn?.click();
        } else {
          startPolygonFeatureBtn?.click();
        }
        return;
      }
      if ([featureRadiusInput].includes(event.target) || (featureFigureInput?.value === 'rounded_corner' && [featureKindInput, featureFigureInput, featureLabelInput].includes(event.target))) {
        roundCornerFeatureBtn?.click();
      } else if ([featureWallOffsetInput, featureDepthInput, featureDirectionInput, featureCutSegmentInput, featureCutOffsetInput].includes(event.target) || featureFigureInput?.value === 'arc') {
        addFeatureFromWallBtn?.click();
      } else if (selectedFeatureIndex >= 0) {
        updateFeatureShapeBtn?.click();
      } else {
        addFeatureShapeBtn?.click();
      }
      return;
    }
    if ([
      lightLineLabelInput,
      lightLineWidthInput,
      lightLineTemplateInput,
      lightLineTemplateWidthInput,
      lightLineTemplateHeightInput,
    ].includes(event.target)) {
      if (Array.isArray(lightLineDraft)) {
        finishLightLineDraft();
      } else if (selectedLightLineIndex >= 0) {
        updateSelectedLightLineFromInputs();
        render();
      } else {
        addLightLineTemplateBtn?.click();
      }
      return;
    }
    if ([
      productionTextureInput,
      productionRollWidthInput,
      productionHarpoonInput,
      productionOrientationModeInput,
      productionOrientationSegmentInput,
      productionOrientationOffsetInput,
      productionShrinkXInput,
      productionShrinkYInput,
      productionSeamOffsetInput,
      productionCommentInput,
    ].includes(event.target)) {
      updateProductionSettingsFromInputs();
      render({ syncList: false, syncInput: true });
      return;
    }

    const segmentRow = event.target.closest('.segment-row');
    if (segmentRow) {
      segmentRow.querySelector('button')?.click();
    }
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
