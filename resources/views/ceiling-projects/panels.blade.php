@extends('layouts.app')

@push('styles')
<style>
  .panels-hero { border: 1px solid rgba(15,23,42,.08); border-radius: 1.25rem; background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(241,245,249,.92)); padding: 1.4rem 1.5rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
  .panels-card { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: rgba(255,255,255,.96); padding: 1rem; }
  .panels-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
  .panels-metric { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: rgba(248,250,252,.96); padding: .9rem 1rem; }
  .panels-metric-label { font-size: .76rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
  .panels-metric-value { margin-top: .2rem; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
  .panel-row { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.94)); padding: 1rem; }
  .panel-chip { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid rgba(15,23,42,.08); border-radius: 999px; padding: .28rem .65rem; background: rgba(248,250,252,.95); font-size: .84rem; }
  .panel-chip-dot { width: .65rem; height: .65rem; border-radius: 999px; background: #059669; display: inline-block; }
  .layout-preview { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: linear-gradient(180deg, rgba(248,250,252,.98), rgba(241,245,249,.92)); padding: .9rem; }
  .layout-roll { position: relative; height: 84px; border-radius: .85rem; background: rgba(15,23,42,.08); overflow: hidden; border: 1px solid rgba(15,23,42,.08); }
  .layout-strip { position: absolute; top: 0; bottom: 0; background: linear-gradient(180deg, rgba(37,99,235,.18), rgba(37,99,235,.34)); border-right: 1px dashed rgba(15,23,42,.18); display: flex; align-items: center; justify-content: center; font-size: .72rem; color: #0f172a; font-weight: 700; }
  .layout-strip.is-last { border-right: none; }
  .layout-seam { position: absolute; top: 6px; bottom: 6px; width: 2px; background: rgba(220,38,38,.75); }
  .layout-preview-note { font-size: .82rem; color: #64748b; }
  @media print {
    .navbar, .btn, .alert { display: none !important; }
    .container-fluid { padding: 0 !important; }
    .panels-hero, .panels-card, .panel-row, .panels-metric, .layout-preview { box-shadow: none !important; }
  }
</style>
@endpush

@php
  $clean = fn ($value) => \App\Support\TextNormalizer::normalizeMojibake((string) $value);
  $formatDecimal = function ($value, $suffix = '') {
      $number = number_format((float) $value, 2, ',', ' ');
      $number = preg_replace('/,00$/', '', $number);
      return trim($number.' '.$suffix);
  };
  $formatCentimeters = function ($meters, $suffix = 'см') {
      $number = number_format((float) $meters * 100, 0, ',', ' ');
      return trim($number.' '.$suffix);
  };
  $textureLabelMap = [
      'matte' => 'Матовый',
      'satin' => 'Сатиновый',
      'glossy' => 'Глянцевый',
      'fabric' => 'Тканевый',
      'custom' => 'Особый',
  ];
  $harpoonLabelMap = [
      'standard' => 'Стандарт',
      'separate' => 'Раздельный',
      'none' => 'Без гарпуна',
  ];
  $orientationLabelMap = [
      'parallel_segment' => 'Параллельно стороне',
      'perpendicular_segment' => 'Перпендикулярно стороне',
      'center_segment' => 'По центру стороны',
      'center_room' => 'По центру помещения',
  ];
  $sourceLabelMap = [
      'room' => 'Основной контур',
      'feature' => 'Отдельная форма',
      'light_line_split' => 'Разделение световыми линиями',
      'seam_split' => 'Разделение швом',
  ];
  $featureKindLabelMap = \App\Models\CeilingProjectRoom::featureKindOptions();
  $lightLineShapes = is_array($room->light_line_shapes) ? $room->light_line_shapes : [];
  $production = $layoutPlan['settings'] ?? (is_array($room->production_settings) ? $room->production_settings : []);
  $layoutSummary = $layoutPlan['summary'] ?? [];
  $orientation = $layoutPlan['orientation'] ?? [];
  $plannedPanels = $layoutPlan['panels'] ?? [];
  $rollSequences = $layoutSummary['roll_sequences'] ?? [];
@endphp

@section('content')
<div class="d-grid gap-3">
  <div class="panels-hero d-flex justify-content-between align-items-start gap-3 flex-wrap">
    <div>
      <div class="text-uppercase text-muted small fw-semibold">Полотна комнаты</div>
      <h1 class="h3 mb-2">{{ $clean($room->name) }}</h1>
      <div class="text-muted">
        Проект: {{ trim((string) ($project->title ?? '')) !== '' ? $clean($project->title) : ('Проект #'.$project->id) }}
        @if($project->deal)
          · Сделка #{{ $project->deal->id }}
        @endif
      </div>
    </div>
    <div class="d-flex gap-2 flex-wrap">
      <a href="{{ route('ceiling-projects.show', ['project' => $project, 'room' => $room->id]) }}#geometry-editor" class="btn btn-outline-secondary">К комнате</a>
      <a href="{{ route('ceiling-projects.production.show', $project) }}" class="btn btn-outline-dark">Пакет проекта</a>
      <button type="button" class="btn btn-dark" onclick="window.print()">Печать</button>
    </div>
  </div>

  <div class="panels-grid">
    <div class="panels-metric">
      <div class="panels-metric-label">Площадь комнаты</div>
      <div class="panels-metric-value">{{ $formatDecimal($metrics['area_m2'], 'м2') }}</div>
    </div>
    <div class="panels-metric">
      <div class="panels-metric-label">Полотна</div>
      <div class="panels-metric-value">{{ count($plannedPanels) }}</div>
    </div>
    <div class="panels-metric">
      <div class="panels-metric-label">Полосы рулона</div>
      <div class="panels-metric-value">{{ $layoutSummary['strips_count'] ?? 0 }}</div>
    </div>
    <div class="panels-metric">
      <div class="panels-metric-label">Шовные комплекты</div>
      <div class="panels-metric-value">{{ $layoutSummary['seamed_panels_count'] ?? 0 }}</div>
    </div>
    <div class="panels-metric">
      <div class="panels-metric-label">Расход рулона</div>
      <div class="panels-metric-value">{{ $formatDecimal($layoutSummary['consumed_area_m2'] ?? 0, 'м2') }}</div>
    </div>
    <div class="panels-metric">
      <div class="panels-metric-label">Запас на усадку</div>
      <div class="panels-metric-value">{{ $formatDecimal($layoutSummary['stretch_reserve_m2'] ?? 0, 'м2') }}</div>
    </div>
    <div class="panels-metric">
      <div class="panels-metric-label">Сценарии рулона</div>
      <div class="panels-metric-value">{{ $layoutSummary['roll_sequences_count'] ?? 0 }}</div>
    </div>
  </div>

  @if(!empty($layoutSummary['warnings']))
    <div class="alert alert-warning mb-0">
      <div class="fw-semibold mb-2">Что проверить перед отдачей в производство</div>
      <ul class="mb-0 ps-3">
        @foreach($layoutSummary['warnings'] as $warning)
          <li>{{ $clean($warning) }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @if(!empty($rollSequences))
    <div class="panels-card">
      <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
        <div>
          <div class="fw-semibold">Сценарии рулона</div>
          <div class="small text-muted">Группы полотен, которые должны идти как один рулонный комплект или отдельные последовательности раскроя.</div>
        </div>
      </div>
      <div class="panels-grid">
        @foreach($rollSequences as $sequence)
          <div class="panels-metric">
            <div class="panels-metric-label">{{ $clean($sequence['label'] ?? ('Рулон '.($loop->iteration))) }}</div>
            <div class="panels-metric-value">{{ (int) ($sequence['panels_count'] ?? 0) }}</div>
            <div class="small text-muted mt-2">
              Полотен: {{ (int) ($sequence['panels_count'] ?? 0) }}
              · Полос: {{ (int) ($sequence['strips_count'] ?? 0) }}
              · Длина: {{ $formatDecimal((float) ($sequence['roll_length_total_m'] ?? 0), 'м') }}
            </div>
            <div class="small text-muted mt-2">{{ $clean(implode(', ', $sequence['panel_labels'] ?? [])) }}</div>
          </div>
        @endforeach
      </div>
    </div>
  @endif

  <div class="panels-card">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
      <div>
        <div class="fw-semibold">Производственные параметры</div>
        <div class="small text-muted">Общие настройки раскроя, которые влияют на все полотна комнаты.</div>
      </div>
    </div>
    <div class="d-flex flex-wrap gap-2 mb-3">
      <span class="panel-chip"><span class="panel-chip-dot"></span>{{ $textureLabelMap[$production['texture'] ?? 'matte'] ?? $clean($production['texture'] ?? 'Матовый') }}</span>
      <span class="panel-chip">Рулон {{ (int) ($production['roll_width_cm'] ?? 320) }} см</span>
      <span class="panel-chip">Гарпун {{ $harpoonLabelMap[$production['harpoon_type'] ?? 'standard'] ?? $clean($production['harpoon_type'] ?? 'Стандарт') }}</span>
      <span class="panel-chip">Усадка {{ $formatDecimal((float) ($production['shrink_x_percent'] ?? 7), '%') }} / {{ $formatDecimal((float) ($production['shrink_y_percent'] ?? 7), '%') }}</span>
      <span class="panel-chip">
        {{ $orientationLabelMap[$production['orientation_mode'] ?? 'parallel_segment'] ?? 'Параллельно стороне' }}
        @if(!empty($orientation['segment_label']))
          · {{ $clean($orientation['segment_label']) }}
        @endif
      </span>
      @if(!empty($production['same_roll_required']))
        <span class="panel-chip">Кроить из одного рулона</span>
      @endif
      @if(!empty($production['special_cutting']))
        <span class="panel-chip">Спецраскрой</span>
      @endif
      @if(!empty($production['seam_enabled']))
        <span class="panel-chip">Шов {{ $formatCentimeters((float) ($production['seam_offset_m'] ?? 0)) }}</span>
      @endif
      @if(trim((string) ($production['comment'] ?? '')) !== '')
        <span class="panel-chip">{{ $clean($production['comment']) }}</span>
      @endif
    </div>
    <div class="row g-2 small">
      <div class="col-md-4"><b>Угол раскроя:</b> {{ $formatDecimal((float) ($orientation['angle_deg'] ?? 0), '°') }}</div>
      <div class="col-md-4"><b>Смещение ориентации:</b> {{ $formatCentimeters((float) ($production['orientation_offset_m'] ?? 0)) }}</div>
      <div class="col-md-4"><b>Световых линий:</b> {{ count($lightLineShapes) }}</div>
    </div>
  </div>

  <div class="panels-card">
    <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
      <div>
        <div class="fw-semibold">Раскрой по полотнам</div>
        <div class="small text-muted">Для каждого полотна показаны готовый размер, заготовка после усадки, полосы рулона и происхождение панели.</div>
      </div>
    </div>

    @if(count($plannedPanels) === 0)
      <div class="text-muted">Полотна ещё не сформированы. Сохраните геометрию комнаты или добавьте световые линии.</div>
    @else
      <div class="d-grid gap-3">
        @foreach($plannedPanels as $panel)
          @php($panelProduction = is_array($panel['production'] ?? null) ? $panel['production'] : $production)
          @php($panelSource = $sourceLabelMap[$panel['source'] ?? 'room'] ?? $clean($panel['source'] ?? 'Полотно'))
          @php($panelFeatureKind = isset($panel['feature_kind']) ? ($featureKindLabelMap[$panel['feature_kind']] ?? $clean($panel['feature_kind'])) : null)
          @php($rollSequence = is_array($panel['roll_sequence'] ?? null) ? $panel['roll_sequence'] : null)
          <div class="panel-row">
            <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
              <div>
                <div class="fw-semibold">{{ $clean($panel['label']) }}</div>
                <div class="small text-muted">
                  Готовая площадь: {{ $formatDecimal((float) ($panel['finished_area_m2'] ?? 0), 'м2') }}
                  · Заготовка: {{ $formatDecimal((float) ($panel['consumed_area_m2'] ?? 0), 'м2') }}
                </div>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <span class="panel-chip">
                  <span class="panel-chip-dot"></span>
                  {{ match($panel['layout_type'] ?? 'single') {
                    'seamed' => 'Со швом',
                    'multi_strip' => 'Несколько полос',
                    default => 'Одно полотно',
                  } }}
                </span>
                <span class="panel-chip">{{ $textureLabelMap[$panelProduction['texture'] ?? 'matte'] ?? $clean($panelProduction['texture'] ?? 'Матовый') }}</span>
                <span class="panel-chip">{{ $clean($panelSource) }}</span>
                @if($rollSequence)
                  <span class="panel-chip">{{ $clean($rollSequence['label'] ?? ('Рулон '.($rollSequence['index'] ?? ''))) }}</span>
                @endif
                @if(isset($panel['seam_part_index']))
                  <span class="panel-chip">Часть шва {{ $panel['seam_part_index'] }}</span>
                @endif
                @if($panelFeatureKind)
                  <span class="panel-chip">{{ $clean($panelFeatureKind) }}</span>
                @endif
              </div>
            </div>

            <div class="row g-2 small mb-3">
              <div class="col-md-3"><b>Готовый размер:</b> {{ $formatCentimeters((float) ($panel['finished_span_m']['length'] ?? 0)) }} × {{ $formatCentimeters((float) ($panel['finished_span_m']['width'] ?? 0)) }}</div>
              <div class="col-md-3"><b>Заготовка:</b> {{ $formatCentimeters((float) ($panel['cut_span_m']['length'] ?? 0)) }} × {{ $formatCentimeters((float) ($panel['cut_span_m']['width'] ?? 0)) }}</div>
              <div class="col-md-3"><b>Полос:</b> {{ (int) ($panel['strips_count'] ?? 0) }}</div>
              <div class="col-md-3"><b>Швов:</b> {{ (int) ($panel['seams_count'] ?? 0) }}</div>
              <div class="col-md-3"><b>Рулон:</b> {{ $formatCentimeters((float) ($panel['roll_width_m'] ?? 0)) }}</div>
              <div class="col-md-3"><b>Длина расхода:</b> {{ $formatDecimal((float) ($panel['roll_length_total_m'] ?? 0), 'м') }}</div>
              <div class="col-md-3"><b>Ориентация:</b> {{ $orientationLabelMap[$panel['orientation']['mode'] ?? 'parallel_segment'] ?? 'Параллельно стороне' }}</div>
              <div class="col-md-3"><b>Опорная сторона:</b> {{ $clean($panel['orientation']['segment_label'] ?? 'Центр помещения') }}</div>
            </div>

            <div class="layout-preview mb-3">
              <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
                <div class="fw-semibold small">Схема полос рулона</div>
                <div class="layout-preview-note">
                  Направление раскроя: {{ $formatDecimal((float) ($panel['orientation']['angle_deg'] ?? 0), '°') }}
                  · Смещение: {{ $formatCentimeters((float) ($panel['orientation']['offset_m'] ?? 0)) }}
                </div>
              </div>
              <div class="layout-roll">
                @foreach($panel['strips'] as $strip)
                  <div
                    class="layout-strip @if($loop->last) is-last @endif"
                    style="left: {{ $strip['start_percent'] }}%; width: {{ $strip['size_percent'] }}%;"
                    title="Полоса {{ $strip['index'] }}: {{ $formatCentimeters((float) $strip['width_m']) }} × {{ $formatCentimeters((float) $strip['length_m']) }}"
                  >
                    {{ $strip['index'] }}
                  </div>
                  @if(!$loop->last)
                    <div class="layout-seam" style="left: {{ $strip['start_percent'] + $strip['size_percent'] }}%;"></div>
                  @endif
                @endforeach
              </div>
              <div class="d-flex flex-wrap gap-2 mt-2">
                @foreach($panel['strips'] as $strip)
                  <span class="panel-chip">Полоса {{ $strip['index'] }} · {{ $formatCentimeters((float) $strip['width_m']) }} × {{ $formatCentimeters((float) $strip['length_m']) }}</span>
                @endforeach
              </div>
            </div>

            <div class="row g-2 small">
              <div class="col-md-4"><b>Запас на усадку:</b> {{ $formatDecimal((float) ($panel['stretch_reserve_m2'] ?? 0), 'м2') }}</div>
              @if(is_array($panel['bounds'] ?? null))
                <div class="col-md-4"><b>Габариты области:</b> {{ $formatCentimeters((float) (($panel['bounds']['max_x'] ?? 0) - ($panel['bounds']['min_x'] ?? 0))) }} × {{ $formatCentimeters((float) (($panel['bounds']['max_y'] ?? 0) - ($panel['bounds']['min_y'] ?? 0))) }}</div>
              @endif
              @if(is_array($panel['centroid'] ?? null))
                <div class="col-md-4"><b>Центр:</b> X {{ $formatCentimeters((float) ($panel['centroid']['x'] ?? 0)) }}, Y {{ $formatCentimeters((float) ($panel['centroid']['y'] ?? 0)) }}</div>
              @endif
              @if(trim((string) ($panelProduction['comment'] ?? '')) !== '')
                <div class="col-12"><b>Комментарий:</b> {{ $clean($panelProduction['comment']) }}</div>
              @endif
            </div>
          </div>
        @endforeach
      </div>
    @endif
  </div>
</div>
@endsection
