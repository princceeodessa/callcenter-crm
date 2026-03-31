@extends('layouts.app')

@push('styles')
<style>
  .packet-hero { border: 1px solid rgba(15,23,42,.08); border-radius: 1.25rem; background: linear-gradient(135deg, rgba(255,255,255,.98), rgba(241,245,249,.94)); padding: 1.5rem; box-shadow: 0 18px 40px rgba(15,23,42,.08); }
  .packet-card { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: rgba(255,255,255,.98); padding: 1rem; }
  .packet-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 1rem; }
  .packet-metric { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: rgba(248,250,252,.96); padding: .9rem 1rem; }
  .packet-metric-label { font-size: .76rem; text-transform: uppercase; letter-spacing: .05em; color: #64748b; }
  .packet-metric-value { margin-top: .2rem; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
  .packet-room { border: 1px solid rgba(15,23,42,.08); border-radius: 1.25rem; background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(248,250,252,.94)); padding: 1rem; box-shadow: 0 14px 36px rgba(15,23,42,.08); }
  .packet-room-header { display: flex; justify-content: space-between; gap: 1rem; align-items: flex-start; flex-wrap: wrap; }
  .packet-room-section { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: rgba(255,255,255,.96); padding: .95rem; }
  .packet-chip { display: inline-flex; align-items: center; gap: .4rem; border: 1px solid rgba(15,23,42,.08); border-radius: 999px; padding: .28rem .65rem; background: rgba(248,250,252,.96); font-size: .84rem; }
  .packet-chip-dot { width: .65rem; height: .65rem; border-radius: 999px; background: #2563eb; display: inline-block; }
  .packet-panel { border: 1px solid rgba(15,23,42,.08); border-radius: 1rem; background: linear-gradient(180deg, rgba(255,255,255,.99), rgba(248,250,252,.94)); padding: .9rem; }
  .packet-layout-roll { position: relative; height: 68px; border-radius: .85rem; background: rgba(15,23,42,.08); overflow: hidden; border: 1px solid rgba(15,23,42,.08); }
  .packet-layout-strip { position: absolute; top: 0; bottom: 0; background: linear-gradient(180deg, rgba(37,99,235,.18), rgba(37,99,235,.34)); border-right: 1px dashed rgba(15,23,42,.18); display: flex; align-items: center; justify-content: center; font-size: .72rem; color: #0f172a; font-weight: 700; }
  .packet-layout-strip.is-last { border-right: none; }
  .packet-layout-seam { position: absolute; top: 6px; bottom: 6px; width: 2px; background: rgba(220,38,38,.75); }
  .packet-room + .packet-room { margin-top: 1rem; }
  .packet-page-break { break-before: page; page-break-before: always; }
  @media print {
    .navbar, .btn, .alert .btn-close { display: none !important; }
    .container-fluid { padding: 0 !important; }
    .packet-hero, .packet-card, .packet-room, .packet-room-section, .packet-panel, .packet-metric { box-shadow: none !important; }
    .packet-room { break-inside: avoid; }
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
  $formatMoney = fn ($value) => $formatDecimal($value, 'руб.');
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
      'light_line_split' => 'Световые линии',
      'seam_split' => 'Шов',
  ];
  $featureKindLabelMap = \App\Models\CeilingProjectRoom::featureKindOptions();
  $projectTitle = trim((string) ($project->title ?? '')) !== '' ? $clean($project->title) : ('Проект #'.$project->id);
@endphp

@section('content')
<div class="d-grid gap-3">
  <div class="packet-hero">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
      <div>
        <div class="text-uppercase text-muted small fw-semibold">Пакет для производства</div>
        <h1 class="h3 mb-2">{{ $projectTitle }}</h1>
        <div class="text-muted">
          Проект #{{ $project->id }}
          @if($project->deal)
            · Сделка #{{ $project->deal->id }}
          @endif
          @if($project->measurement?->scheduled_at)
            · Замер {{ $project->measurement->scheduled_at->format('d.m.Y H:i') }}
          @endif
        </div>
        @if($project->deal?->contact)
          <div class="small text-muted mt-2">
            Клиент: {{ $clean($project->deal->contact->name ?? 'Без имени') }}
            @if(trim((string) ($project->deal->contact->phone ?? '')) !== '')
              · {{ $clean($project->deal->contact->phone) }}
            @endif
          </div>
        @endif
      </div>
      <div class="d-flex gap-2 flex-wrap">
        <a href="{{ route('ceiling-projects.show', $project) }}" class="btn btn-outline-secondary">К проекту</a>
        <button type="button" class="btn btn-dark" onclick="window.print()">Печать пакета</button>
      </div>
    </div>
  </div>

  <div class="packet-grid">
    <div class="packet-metric">
      <div class="packet-metric-label">Комнаты</div>
      <div class="packet-metric-value">{{ $packetSummary['rooms_count'] }}</div>
    </div>
    <div class="packet-metric">
      <div class="packet-metric-label">Полотна</div>
      <div class="packet-metric-value">{{ $packetSummary['panels_count'] }}</div>
    </div>
    <div class="packet-metric">
      <div class="packet-metric-label">Полосы рулона</div>
      <div class="packet-metric-value">{{ $packetSummary['strips_count'] }}</div>
    </div>
    <div class="packet-metric">
      <div class="packet-metric-label">Шовные комплекты</div>
      <div class="packet-metric-value">{{ $packetSummary['seamed_panels_count'] }}</div>
    </div>
    <div class="packet-metric">
      <div class="packet-metric-label">Площадь полотен</div>
      <div class="packet-metric-value">{{ $formatDecimal($packetSummary['finished_area_m2'], 'м2') }}</div>
    </div>
    <div class="packet-metric">
      <div class="packet-metric-label">Расход рулона</div>
      <div class="packet-metric-value">{{ $formatDecimal($packetSummary['roll_length_total_m'], 'м') }}</div>
    </div>
    <div class="packet-metric">
      <div class="packet-metric-label">Комнаты с общим рулоном</div>
      <div class="packet-metric-value">{{ $packetSummary['same_roll_rooms_count'] }}</div>
    </div>
    <div class="packet-metric">
      <div class="packet-metric-label">Комнаты со спецраскроем</div>
      <div class="packet-metric-value">{{ $packetSummary['special_cutting_rooms_count'] }}</div>
    </div>
  </div>

  @if(!empty($packetSummary['warnings']))
    <div class="alert alert-warning mb-0">
      <div class="fw-semibold mb-2">Что проверить по проекту перед отправкой в производство</div>
      <ul class="mb-0 ps-3">
        @foreach($packetSummary['warnings'] as $warning)
          <li>{{ $clean($warning) }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  @foreach($roomPackets as $roomPacket)
    @php
      $room = $roomPacket['room'];
      $metrics = $roomPacket['metrics'];
      $layoutPlan = $roomPacket['layoutPlan'];
      $plannedPanels = $layoutPlan['panels'] ?? [];
      $layoutSummary = $layoutPlan['summary'] ?? [];
      $production = is_array($layoutPlan['settings'] ?? null) ? $layoutPlan['settings'] : [];
      $orientation = is_array($layoutPlan['orientation'] ?? null) ? $layoutPlan['orientation'] : [];
      $rollSequences = $layoutSummary['roll_sequences'] ?? [];
      $lightLineShapes = is_array($room->light_line_shapes) ? $room->light_line_shapes : [];
    @endphp
    <div class="packet-room {{ !$loop->first ? 'packet-page-break' : '' }}" id="room-{{ $room->id }}">
      <div class="packet-room-header mb-3">
        <div>
          <div class="text-uppercase text-muted small fw-semibold">Комната</div>
          <h2 class="h4 mb-1">{{ $clean($room->name) }}</h2>
          <div class="small text-muted">
            Площадь {{ $formatDecimal($metrics['area_m2'] ?? 0, 'м2') }}
            · Периметр {{ $formatCentimeters($metrics['perimeter_m'] ?? 0) }}
            · Высота {{ $formatCentimeters($room->height_m ?? 0) }}
          </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
          <a href="{{ route('ceiling-projects.show', ['project' => $project, 'room' => $room->id]) }}#geometry-editor" class="btn btn-outline-secondary btn-sm">Чертёж комнаты</a>
          <a href="{{ route('ceiling-projects.rooms.panels.show', [$project, $room]) }}" class="btn btn-outline-dark btn-sm">Экран полотен</a>
        </div>
      </div>

      <div class="packet-grid mb-3">
        <div class="packet-metric">
          <div class="packet-metric-label">Полотна комнаты</div>
          <div class="packet-metric-value">{{ $layoutSummary['panels_count'] ?? count($plannedPanels) }}</div>
        </div>
        <div class="packet-metric">
          <div class="packet-metric-label">Полосы</div>
          <div class="packet-metric-value">{{ $layoutSummary['strips_count'] ?? 0 }}</div>
        </div>
        <div class="packet-metric">
          <div class="packet-metric-label">Шовные комплекты</div>
          <div class="packet-metric-value">{{ $layoutSummary['seamed_panels_count'] ?? 0 }}</div>
        </div>
        <div class="packet-metric">
          <div class="packet-metric-label">Расход рулона</div>
          <div class="packet-metric-value">{{ $formatDecimal($layoutSummary['roll_length_total_m'] ?? 0, 'м') }}</div>
        </div>
      </div>

      @if(!empty($layoutSummary['warnings']))
        <div class="alert alert-warning mb-3">
          <div class="fw-semibold mb-2">Проверить по комнате</div>
          <ul class="mb-0 ps-3">
            @foreach($layoutSummary['warnings'] as $warning)
              <li>{{ $clean($warning) }}</li>
            @endforeach
          </ul>
        </div>
      @endif

      <div class="packet-room-section mb-3">
        <div class="fw-semibold mb-3">Производственные параметры</div>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <span class="packet-chip"><span class="packet-chip-dot"></span>{{ $textureLabelMap[$production['texture'] ?? 'matte'] ?? $clean($production['texture'] ?? 'Матовый') }}</span>
          <span class="packet-chip">Рулон {{ (int) ($production['roll_width_cm'] ?? 320) }} см</span>
          <span class="packet-chip">Гарпун {{ $harpoonLabelMap[$production['harpoon_type'] ?? 'standard'] ?? $clean($production['harpoon_type'] ?? 'Стандарт') }}</span>
          <span class="packet-chip">Усадка {{ $formatDecimal((float) ($production['shrink_x_percent'] ?? 7), '%') }} / {{ $formatDecimal((float) ($production['shrink_y_percent'] ?? 7), '%') }}</span>
          <span class="packet-chip">
            {{ $orientationLabelMap[$production['orientation_mode'] ?? 'parallel_segment'] ?? 'Параллельно стороне' }}
            @if(!empty($orientation['segment_label']))
              · {{ $clean($orientation['segment_label']) }}
            @endif
          </span>
          @if(!empty($production['same_roll_required']))
            <span class="packet-chip">Кроить из одного рулона</span>
          @endif
          @if(!empty($production['special_cutting']))
            <span class="packet-chip">Спецраскрой</span>
          @endif
          @if(!empty($production['seam_enabled']))
            <span class="packet-chip">Шов {{ $formatCentimeters((float) ($production['seam_offset_m'] ?? 0)) }}</span>
          @endif
          @if(trim((string) ($production['comment'] ?? '')) !== '')
            <span class="packet-chip">{{ $clean($production['comment']) }}</span>
          @endif
        </div>
        <div class="row g-2 small">
          <div class="col-md-3"><b>Угол раскроя:</b> {{ $formatDecimal((float) ($orientation['angle_deg'] ?? 0), '°') }}</div>
          <div class="col-md-3"><b>Смещение ориентации:</b> {{ $formatCentimeters((float) ($production['orientation_offset_m'] ?? 0)) }}</div>
          <div class="col-md-3"><b>Световых линий:</b> {{ count($lightLineShapes) }}</div>
          <div class="col-md-3"><b>Рулонных комплектов:</b> {{ count($rollSequences) }}</div>
        </div>
      </div>

      @if(!empty($rollSequences))
        <div class="packet-room-section mb-3">
          <div class="fw-semibold mb-3">Сценарии рулона</div>
          <div class="packet-grid">
            @foreach($rollSequences as $sequence)
              <div class="packet-metric">
                <div class="packet-metric-label">{{ $clean($sequence['label'] ?? ('Рулон '.($loop->iteration))) }}</div>
                <div class="packet-metric-value">{{ (int) ($sequence['panels_count'] ?? 0) }}</div>
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

      <div class="packet-room-section">
        <div class="fw-semibold mb-3">Полотна комнаты</div>
        @if(count($plannedPanels) === 0)
          <div class="text-muted">Полотна пока не сформированы. Сохраните геометрию комнаты и проверьте световые линии.</div>
        @else
          <div class="d-grid gap-3">
            @foreach($plannedPanels as $panel)
              @php
                $panelProduction = is_array($panel['production'] ?? null) ? $panel['production'] : $production;
                $panelSource = $sourceLabelMap[$panel['source'] ?? 'room'] ?? $clean($panel['source'] ?? 'Полотно');
                $panelFeatureKind = isset($panel['feature_kind']) ? ($featureKindLabelMap[$panel['feature_kind']] ?? $clean($panel['feature_kind'])) : null;
                $rollSequence = is_array($panel['roll_sequence'] ?? null) ? $panel['roll_sequence'] : null;
              @endphp
              <div class="packet-panel">
                <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-2">
                  <div>
                    <div class="fw-semibold">{{ $clean($panel['label']) }}</div>
                    <div class="small text-muted">
                      Готовая площадь {{ $formatDecimal((float) ($panel['finished_area_m2'] ?? 0), 'м2') }}
                      · Заготовка {{ $formatDecimal((float) ($panel['consumed_area_m2'] ?? 0), 'м2') }}
                    </div>
                  </div>
                  <div class="d-flex gap-2 flex-wrap">
                    <span class="packet-chip">
                      <span class="packet-chip-dot"></span>
                      {{ match($panel['layout_type'] ?? 'single') {
                        'seamed' => 'Со швом',
                        'multi_strip' => 'Несколько полос',
                        default => 'Одно полотно',
                      } }}
                    </span>
                    <span class="packet-chip">{{ $textureLabelMap[$panelProduction['texture'] ?? 'matte'] ?? $clean($panelProduction['texture'] ?? 'Матовый') }}</span>
                    <span class="packet-chip">{{ $clean($panelSource) }}</span>
                    @if($rollSequence)
                      <span class="packet-chip">{{ $clean($rollSequence['label'] ?? ('Рулон '.($rollSequence['index'] ?? ''))) }}</span>
                    @endif
                    @if(isset($panel['seam_part_index']))
                      <span class="packet-chip">Часть шва {{ $panel['seam_part_index'] }}</span>
                    @endif
                    @if($panelFeatureKind)
                      <span class="packet-chip">{{ $clean($panelFeatureKind) }}</span>
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

                <div class="packet-layout-roll mb-2">
                  @foreach($panel['strips'] ?? [] as $strip)
                    <div class="packet-layout-strip @if($loop->last) is-last @endif" style="left: {{ $strip['start_percent'] }}%; width: {{ $strip['size_percent'] }}%;">
                      {{ $strip['index'] }}
                    </div>
                    @if(!$loop->last)
                      <div class="packet-layout-seam" style="left: {{ $strip['start_percent'] + $strip['size_percent'] }}%;"></div>
                    @endif
                  @endforeach
                </div>
                <div class="d-flex flex-wrap gap-2 small mb-2">
                  @foreach($panel['strips'] ?? [] as $strip)
                    <span class="packet-chip">Полоса {{ $strip['index'] }} · {{ $formatCentimeters((float) $strip['width_m']) }} × {{ $formatCentimeters((float) $strip['length_m']) }}</span>
                  @endforeach
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
  @endforeach
</div>
@endsection
