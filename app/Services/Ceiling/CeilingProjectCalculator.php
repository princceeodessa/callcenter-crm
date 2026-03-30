<?php

namespace App\Services\Ceiling;

use App\Models\CeilingProject;
use App\Models\CeilingProjectRoom;
use Illuminate\Support\Collection;

class CeilingProjectCalculator
{
    public function __construct(
        private readonly ?CeilingLightLinePanelSplitter $panelSplitter = null,
    ) {
    }

    public function calculateRoom(CeilingProjectRoom|array $room): array
    {
        $payload = $room instanceof CeilingProjectRoom ? $room->toArray() : $room;

        $shapeType = (string) ($payload['shape_type'] ?? CeilingProjectRoom::SHAPE_RECTANGLE);
        $width = $this->toFloat($payload['width_m'] ?? null);
        $length = $this->toFloat($payload['length_m'] ?? null);
        $height = $this->toFloat($payload['height_m'] ?? null);
        $manualArea = $this->toFloat($payload['manual_area_m2'] ?? null);
        $manualPerimeter = $this->toFloat($payload['manual_perimeter_m'] ?? null);
        $shapePoints = $this->normalizePoints($payload['shape_points'] ?? null);
        $featureShapes = $this->normalizeFeatureShapes($payload['feature_shapes'] ?? null);
        $lightLineShapes = $this->normalizeLightLineShapes($payload['light_line_shapes'] ?? null);
        $elements = is_array($payload['elements'] ?? null) ? $payload['elements'] : [];
        $corners = max(4, (int) ($payload['corners_count'] ?? 4));

        $isRectangle = $shapeType === CeilingProjectRoom::SHAPE_RECTANGLE && $width > 0 && $length > 0;
        $polygonArea = $shapePoints !== [] ? $this->polygonArea($shapePoints) : 0.0;
        $polygonPerimeter = $shapePoints !== [] ? $this->polygonPerimeter($shapePoints) : 0.0;

        $baseArea = $manualArea > 0
            ? $manualArea
            : ($isRectangle ? $width * $length : $polygonArea);
        $basePerimeter = $manualPerimeter > 0
            ? $manualPerimeter
            : ($isRectangle ? ($width + $length) * 2 : $polygonPerimeter);
        $featureMetrics = $this->calculateFeatureShapes($featureShapes);
        $lightLineMetrics = $this->calculateLightLineShapes($lightLineShapes);
        $lightLinePanelsCount = count(($this->panelSplitter ?? new CeilingLightLinePanelSplitter())->split(
            $shapePoints !== []
                ? $shapePoints
                : ($isRectangle ? [
                    ['x' => 0.0, 'y' => 0.0],
                    ['x' => $width, 'y' => 0.0],
                    ['x' => $width, 'y' => $length],
                    ['x' => 0.0, 'y' => $length],
                ] : []),
            $lightLineShapes,
            is_array($payload['production_settings'] ?? null) ? $payload['production_settings'] : []
        ));

        $area = max(0, $baseArea + $featureMetrics['area_delta_m2']);
        $perimeter = max(0, $basePerimeter + $featureMetrics['perimeter_delta_m']);

        $spotlights = $this->sumElements($elements, 'spotlight');
        $chandeliers = $this->sumElements($elements, 'chandelier');
        $pipes = $this->sumElements($elements, 'pipe');
        $curtainNiches = $this->sumElements($elements, 'curtain_niche');
        $ventilationHoles = $this->sumElements($elements, 'ventilation');
        $cornices = $this->sumElements($elements, 'cornice');
        $corniceLength = $this->sumElementLengths($elements, 'cornice');

        if ($elements === []) {
            $spotlights = max(0, (int) ($payload['spotlights_count'] ?? 0));
            $chandeliers = max(0, (int) ($payload['chandelier_points_count'] ?? 0));
            $pipes = max(0, (int) ($payload['pipes_count'] ?? 0));
            $curtainNiches = max(0, (int) ($payload['curtain_niches_count'] ?? 0));
            $ventilationHoles = max(0, (int) ($payload['ventilation_holes_count'] ?? 0));
            $cornices = 0;
            $corniceLength = 0.0;
        }
        $corners = $shapePoints !== [] ? max(4, count($shapePoints)) : $corners;

        return [
            'shape_type' => $shapeType,
            'width_m' => $width,
            'length_m' => $length,
            'height_m' => $height,
            'area_m2' => $this->round($area),
            'perimeter_m' => $this->round($perimeter),
            'corners_count' => $corners,
            'profile_running_m' => $this->round($perimeter),
            'insert_running_m' => $this->round($perimeter),
            'spotlights_count' => $spotlights,
            'chandelier_points_count' => $chandeliers,
            'pipes_count' => $pipes,
            'curtain_niches_count' => $curtainNiches,
            'ventilation_holes_count' => $ventilationHoles,
            'feature_shapes_count' => count($featureShapes),
            'feature_cutouts_count' => $featureMetrics['cutouts_count'],
            'feature_levels_count' => $featureMetrics['levels_count'],
            'feature_shifts_count' => $featureMetrics['shifts_count'],
            'light_line_groups_count' => $lightLineMetrics['groups_count'],
            'light_line_segments_count' => $lightLineMetrics['segments_count'],
            'light_line_length_m' => $lightLineMetrics['length_m'],
            'light_line_panels_count' => $lightLinePanelsCount,
            'cornices_count' => $cornices,
            'cornice_length_m' => $this->round($corniceLength),
            'lighting_points_total' => $spotlights + $chandeliers,
            'wall_area_m2' => $height > 0 && $perimeter > 0 ? $this->round($height * $perimeter) : 0.0,
        ];
    }

    public function calculateProject(CeilingProject $project): array
    {
        /** @var Collection<int, CeilingProjectRoom> $rooms */
        $rooms = $project->relationLoaded('rooms')
            ? $project->rooms
            : $project->rooms()->with('elements')->get();

        if ($rooms instanceof \Illuminate\Database\Eloquent\Collection) {
            $rooms->loadMissing('elements');
        }

        $calculatedRooms = $rooms->map(function (CeilingProjectRoom $room) {
            return [
                'model' => $room,
                'metrics' => $this->calculateRoom($room),
            ];
        });

        $wastePercent = $this->toFloat($project->waste_percent);
        $extraMargin = $this->toFloat($project->extra_margin_m);
        $discountPercent = $this->toFloat($project->discount_percent);

        $totalArea = $calculatedRooms->sum(fn (array $room) => $room['metrics']['area_m2']);
        $totalPerimeter = $calculatedRooms->sum(fn (array $room) => $room['metrics']['perimeter_m']);
        $totalWallArea = $calculatedRooms->sum(fn (array $room) => $room['metrics']['wall_area_m2']);
        $roomsCount = $calculatedRooms->count();
        $spotlightsCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['spotlights_count']);
        $chandelierPointsCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['chandelier_points_count']);
        $pipesCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['pipes_count']);
        $curtainNichesCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['curtain_niches_count']);
        $ventilationHolesCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['ventilation_holes_count']);
        $featureShapesCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['feature_shapes_count']);
        $featureCutoutsCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['feature_cutouts_count']);
        $featureLevelsCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['feature_levels_count']);
        $featureShiftsCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['feature_shifts_count']);
        $lightLineGroupsCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['light_line_groups_count']);
        $lightLineSegmentsCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['light_line_segments_count']);
        $lightLineLength = (float) $calculatedRooms->sum(fn (array $room) => $room['metrics']['light_line_length_m']);
        $lightLinePanelsCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['light_line_panels_count']);
        $cornicesCount = (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['cornices_count']);
        $corniceLength = (float) $calculatedRooms->sum(fn (array $room) => $room['metrics']['cornice_length_m']);

        $canvasReserve = $totalArea * ($wastePercent / 100);
        $recommendedCanvasArea = $totalArea + $canvasReserve;
        $recommendedProfile = $totalPerimeter + ($roomsCount > 0 ? $extraMargin * $roomsCount : 0);
        $recommendedInsert = $totalPerimeter;

        $canvasRate = $this->toFloat($project->canvas_price_per_m2);
        $profileRate = $this->toFloat($project->profile_price_per_m);
        $insertRate = $this->toFloat($project->insert_price_per_m);
        $spotlightRate = $this->toFloat($project->spotlight_price);
        $chandelierRate = $this->toFloat($project->chandelier_price);
        $pipeRate = $this->toFloat($project->pipe_price);
        $curtainNicheRate = $this->toFloat($project->curtain_niche_price);
        $corniceRate = $this->toFloat($project->cornice_price_per_m);
        $ventilationRate = $this->toFloat($project->ventilation_hole_price);
        $mountingRate = $this->toFloat($project->mounting_price_per_m2);
        $additionalCost = $this->toFloat($project->additional_cost);

        $canvasTotal = $recommendedCanvasArea * $canvasRate;
        $profileTotal = $recommendedProfile * $profileRate;
        $insertTotal = $recommendedInsert * $insertRate;
        $spotlightsTotal = $spotlightsCount * $spotlightRate;
        $chandeliersTotal = $chandelierPointsCount * $chandelierRate;
        $pipesTotal = $pipesCount * $pipeRate;
        $curtainNichesTotal = $curtainNichesCount * $curtainNicheRate;
        $cornicesTotal = $corniceLength * $corniceRate;
        $ventilationTotal = $ventilationHolesCount * $ventilationRate;
        $mountingTotal = $totalArea * $mountingRate;
        $subtotal = $canvasTotal
            + $profileTotal
            + $insertTotal
            + $spotlightsTotal
            + $chandeliersTotal
            + $pipesTotal
            + $curtainNichesTotal
            + $cornicesTotal
            + $ventilationTotal
            + $mountingTotal
            + $additionalCost;
        $discountAmount = $subtotal * ($discountPercent / 100);
        $grandTotal = max(0, $subtotal - $discountAmount);

        return [
            'rooms' => $calculatedRooms,
            'totals' => [
                'rooms_count' => $roomsCount,
                'area_m2' => $this->round($totalArea),
                'perimeter_m' => $this->round($totalPerimeter),
                'wall_area_m2' => $this->round($totalWallArea),
                'canvas_reserve_m2' => $this->round($canvasReserve),
                'recommended_canvas_area_m2' => $this->round($recommendedCanvasArea),
                'recommended_profile_m' => $this->round($recommendedProfile),
                'recommended_insert_m' => $this->round($recommendedInsert),
                'spotlights_count' => $spotlightsCount,
                'chandelier_points_count' => $chandelierPointsCount,
                'lighting_points_total' => $spotlightsCount + $chandelierPointsCount,
                'pipes_count' => $pipesCount,
                'curtain_niches_count' => $curtainNichesCount,
                'ventilation_holes_count' => $ventilationHolesCount,
                'feature_shapes_count' => $featureShapesCount,
                'feature_cutouts_count' => $featureCutoutsCount,
                'feature_levels_count' => $featureLevelsCount,
                'feature_shifts_count' => $featureShiftsCount,
                'light_line_groups_count' => $lightLineGroupsCount,
                'light_line_segments_count' => $lightLineSegmentsCount,
                'light_line_length_m' => $this->round($lightLineLength),
                'light_line_panels_count' => $lightLinePanelsCount,
                'cornices_count' => $cornicesCount,
                'cornice_length_m' => $this->round($corniceLength),
                'corners_count' => (int) $calculatedRooms->sum(fn (array $room) => $room['metrics']['corners_count']),
                'waste_percent' => $this->round($wastePercent),
                'extra_margin_m' => $this->round($extraMargin),
                'discount_percent' => $this->round($discountPercent),
            ],
            'rates' => [
                'canvas_price_per_m2' => $this->round($canvasRate),
                'profile_price_per_m' => $this->round($profileRate),
                'insert_price_per_m' => $this->round($insertRate),
                'spotlight_price' => $this->round($spotlightRate),
                'chandelier_price' => $this->round($chandelierRate),
                'pipe_price' => $this->round($pipeRate),
                'curtain_niche_price' => $this->round($curtainNicheRate),
                'cornice_price_per_m' => $this->round($corniceRate),
                'ventilation_hole_price' => $this->round($ventilationRate),
                'mounting_price_per_m2' => $this->round($mountingRate),
                'additional_cost' => $this->round($additionalCost),
            ],
            'estimate' => [
                'canvas_total' => $this->round($canvasTotal),
                'profile_total' => $this->round($profileTotal),
                'insert_total' => $this->round($insertTotal),
                'spotlights_total' => $this->round($spotlightsTotal),
                'chandeliers_total' => $this->round($chandeliersTotal),
                'pipes_total' => $this->round($pipesTotal),
                'curtain_niches_total' => $this->round($curtainNichesTotal),
                'cornices_total' => $this->round($cornicesTotal),
                'ventilation_total' => $this->round($ventilationTotal),
                'mounting_total' => $this->round($mountingTotal),
                'additional_cost' => $this->round($additionalCost),
                'subtotal' => $this->round($subtotal),
                'discount_amount' => $this->round($discountAmount),
                'grand_total' => $this->round($grandTotal),
            ],
        ];
    }

    private function toFloat(mixed $value): float
    {
        if ($value === null || $value === '') {
            return 0.0;
        }

        return (float) $value;
    }

    private function normalizePoints(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $points = [];

        foreach ($value as $point) {
            if (!is_array($point)) {
                continue;
            }

            $x = $this->toFloat($point['x'] ?? null);
            $y = $this->toFloat($point['y'] ?? null);

            $points[] = ['x' => $x, 'y' => $y];
        }

        return count($points) >= 3 ? $points : [];
    }

    private function normalizeFeatureShapes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $shapes = [];

        foreach ($value as $shape) {
            if (!is_array($shape)) {
                continue;
            }

            $kind = (string) ($shape['kind'] ?? '');
            $figure = (string) ($shape['figure'] ?? '');
            $x = $this->toFloat($shape['x_m'] ?? null);
            $y = $this->toFloat($shape['y_m'] ?? null);
            $width = $this->toFloat($shape['width_m'] ?? null);
            $height = $this->toFloat($shape['height_m'] ?? null);
            $shapePoints = $this->normalizePoints($shape['shape_points'] ?? null);

            if (
                !in_array($kind, [
                    CeilingProjectRoom::FEATURE_CUTOUT,
                    CeilingProjectRoom::FEATURE_LEVEL,
                    CeilingProjectRoom::FEATURE_SHIFT,
                ], true)
                || !in_array($figure, array_keys(CeilingProjectRoom::featureFigureOptions()), true)
                || (
                    $shapePoints === []
                    && ($x < 0 || $y < 0 || $width <= 0 || $height <= 0)
                )
            ) {
                continue;
            }

            $shapes[] = [
                'kind' => $kind,
                'figure' => $figure,
                'x_m' => $x,
                'y_m' => $y,
                'width_m' => $width,
                'height_m' => $height,
                'shape_points' => $shapePoints !== [] ? $shapePoints : null,
                'area_delta_m2' => is_numeric($shape['area_delta_m2'] ?? null) ? $this->round((float) $shape['area_delta_m2']) : null,
                'perimeter_delta_m' => is_numeric($shape['perimeter_delta_m'] ?? null) ? $this->round((float) $shape['perimeter_delta_m']) : null,
            ];
        }

        return $shapes;
    }

    private function normalizeLightLineShapes(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $shapes = [];

        foreach ($value as $shape) {
            if (!is_array($shape)) {
                continue;
            }

            $points = $this->normalizeLightLinePoints($shape['points'] ?? null);
            if (count($points) < 2) {
                continue;
            }

            $shapes[] = [
                'points' => $points,
                'closed' => (bool) ($shape['closed'] ?? false),
                'width_m' => max(0.01, $this->toFloat($shape['width_m'] ?? null)),
            ];
        }

        return $shapes;
    }

    private function calculateFeatureShapes(array $shapes): array
    {
        $areaDelta = 0.0;
        $perimeterDelta = 0.0;
        $cutouts = 0;
        $levels = 0;
        $shifts = 0;

        foreach ($shapes as $shape) {
            $metrics = $this->featureShapeMetrics($shape);
            $areaOverride = $this->toFloat($shape['area_delta_m2'] ?? null);
            $perimeterOverride = $this->toFloat($shape['perimeter_delta_m'] ?? null);

            if ($areaOverride !== 0.0 || is_numeric($shape['area_delta_m2'] ?? null)) {
                $areaDelta += $areaOverride;
            } else {
                $sign = in_array($shape['kind'], [
                    CeilingProjectRoom::FEATURE_CUTOUT,
                    CeilingProjectRoom::FEATURE_SHIFT,
                ], true) ? -1 : 1;

                $areaDelta += $metrics['area_m2'] * $sign;
            }

            if ($perimeterOverride !== 0.0 || is_numeric($shape['perimeter_delta_m'] ?? null)) {
                $perimeterDelta += $perimeterOverride;
            } else {
                $perimeterDelta += $metrics['perimeter_m'];
            }

            if ($shape['kind'] === CeilingProjectRoom::FEATURE_CUTOUT) {
                $cutouts++;
            } elseif ($shape['kind'] === CeilingProjectRoom::FEATURE_LEVEL) {
                $levels++;
            } elseif ($shape['kind'] === CeilingProjectRoom::FEATURE_SHIFT) {
                $shifts++;
            }
        }

        return [
            'area_delta_m2' => $this->round($areaDelta),
            'perimeter_delta_m' => $this->round($perimeterDelta),
            'cutouts_count' => $cutouts,
            'levels_count' => $levels,
            'shifts_count' => $shifts,
        ];
    }

    private function calculateLightLineShapes(array $shapes): array
    {
        $groupsCount = count($shapes);
        $segmentsCount = 0;
        $length = 0.0;

        foreach ($shapes as $shape) {
            $points = $shape['points'] ?? [];
            $count = count($points);
            if ($count < 2) {
                continue;
            }

            $segmentsCount += max(0, $count - 1) + ((bool) ($shape['closed'] ?? false) ? 1 : 0);
            $length += $this->polylineLength($points, (bool) ($shape['closed'] ?? false));
        }

        return [
            'groups_count' => $groupsCount,
            'segments_count' => $segmentsCount,
            'length_m' => $this->round($length),
        ];
    }

    private function featureShapeMetrics(array $shape): array
    {
        $shapePoints = $this->normalizePoints($shape['shape_points'] ?? null);
        if ($shapePoints !== []) {
            return [
                'area_m2' => $this->polygonArea($shapePoints),
                'perimeter_m' => $this->polygonPerimeter($shapePoints),
            ];
        }

        $width = $this->toFloat($shape['width_m'] ?? null);
        $height = $this->toFloat($shape['height_m'] ?? null);

        return match ((string) ($shape['figure'] ?? CeilingProjectRoom::FEATURE_RECTANGLE)) {
            CeilingProjectRoom::FEATURE_CIRCLE => $this->circleMetrics(min($width, $height)),
            CeilingProjectRoom::FEATURE_TRIANGLE => $this->triangleMetrics($width, $height),
            default => $this->rectangleMetrics($width, $height),
        };
    }

    private function rectangleMetrics(float $width, float $height): array
    {
        return [
            'area_m2' => max(0.0, $width * $height),
            'perimeter_m' => max(0.0, ($width * 2) + ($height * 2)),
        ];
    }

    private function triangleMetrics(float $width, float $height): array
    {
        return [
            'area_m2' => max(0.0, ($width * $height) / 2),
            'perimeter_m' => max(0.0, $width + $height + sqrt(($width ** 2) + ($height ** 2))),
        ];
    }

    private function circleMetrics(float $diameter): array
    {
        $radius = max(0.0, $diameter / 2);

        return [
            'area_m2' => max(0.0, pi() * ($radius ** 2)),
            'perimeter_m' => max(0.0, 2 * pi() * $radius),
        ];
    }

    private function sumElements(array $elements, string $type): int
    {
        $sum = 0;

        foreach ($elements as $element) {
            if (!is_array($element) || ($element['type'] ?? null) !== $type) {
                continue;
            }

            $sum += max(0, (int) ($element['quantity'] ?? 1));
        }

        return $sum;
    }

    private function sumElementLengths(array $elements, string $type): float
    {
        $sum = 0.0;

        foreach ($elements as $element) {
            if (!is_array($element) || ($element['type'] ?? null) !== $type) {
                continue;
            }

            $sum += $this->toFloat($element['length_m'] ?? null);
        }

        return $sum;
    }

    private function polygonArea(array $points): float
    {
        $sum = 0.0;
        $count = count($points);

        for ($index = 0; $index < $count; $index++) {
            $next = ($index + 1) % $count;
            $sum += ($points[$index]['x'] * $points[$next]['y']) - ($points[$next]['x'] * $points[$index]['y']);
        }

        return abs($sum) / 2;
    }

    private function polygonPerimeter(array $points): float
    {
        $sum = 0.0;
        $count = count($points);

        for ($index = 0; $index < $count; $index++) {
            $next = ($index + 1) % $count;
            $dx = $points[$next]['x'] - $points[$index]['x'];
            $dy = $points[$next]['y'] - $points[$index]['y'];
            $sum += sqrt(($dx ** 2) + ($dy ** 2));
        }

        return $sum;
    }

    private function normalizeLightLinePoints(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $points = [];

        foreach ($value as $point) {
            if (!is_array($point)) {
                continue;
            }

            $x = $this->toFloat($point['x'] ?? null);
            $y = $this->toFloat($point['y'] ?? null);
            $points[] = ['x' => $x, 'y' => $y];
        }

        return count($points) >= 2 ? $points : [];
    }

    private function polylineLength(array $points, bool $closed = false): float
    {
        $sum = 0.0;
        $count = count($points);
        if ($count < 2) {
            return 0.0;
        }

        for ($index = 1; $index < $count; $index++) {
            $dx = $points[$index]['x'] - $points[$index - 1]['x'];
            $dy = $points[$index]['y'] - $points[$index - 1]['y'];
            $sum += sqrt(($dx ** 2) + ($dy ** 2));
        }

        if ($closed && $count > 2) {
            $dx = $points[0]['x'] - $points[$count - 1]['x'];
            $dy = $points[0]['y'] - $points[$count - 1]['y'];
            $sum += sqrt(($dx ** 2) + ($dy ** 2));
        }

        return $sum;
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
