<?php

namespace App\Services\Ceiling;

use App\Models\CeilingProject;
use App\Models\CeilingProjectRoom;
use Illuminate\Support\Collection;

class CeilingProjectCalculator
{
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
        $elements = is_array($payload['elements'] ?? null) ? $payload['elements'] : [];
        $corners = max(4, (int) ($payload['corners_count'] ?? 4));

        $isRectangle = $shapeType === CeilingProjectRoom::SHAPE_RECTANGLE && $width > 0 && $length > 0;
        $polygonArea = $shapePoints !== [] ? $this->polygonArea($shapePoints) : 0.0;
        $polygonPerimeter = $shapePoints !== [] ? $this->polygonPerimeter($shapePoints) : 0.0;

        $area = $manualArea > 0
            ? $manualArea
            : ($isRectangle ? $width * $length : $polygonArea);
        $perimeter = $manualPerimeter > 0
            ? $manualPerimeter
            : ($isRectangle ? ($width + $length) * 2 : $polygonPerimeter);

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

    private function round(float $value): float
    {
        return round($value, 2);
    }
}
