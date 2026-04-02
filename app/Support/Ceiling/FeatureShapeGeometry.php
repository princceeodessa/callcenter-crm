<?php

namespace App\Support\Ceiling;

use App\Models\CeilingProjectRoom;

class FeatureShapeGeometry
{
    /**
     * @param  array<string, mixed>  $shape
     * @param  array<int, array{x: float|int, y: float|int}>  $roomPolygon
     * @return array<int, array{x: float, y: float}>
     */
    public function points(array $shape, array $roomPolygon = []): array
    {
        $shapePoints = $this->normalizePoints($shape['shape_points'] ?? null, 3);
        if ($shapePoints !== []) {
            return $shapePoints;
        }

        return match ((string) ($shape['figure'] ?? CeilingProjectRoom::FEATURE_RECTANGLE)) {
            CeilingProjectRoom::FEATURE_TRIANGLE => $this->trianglePoints($shape),
            CeilingProjectRoom::FEATURE_CIRCLE => $this->circlePoints($shape),
            CeilingProjectRoom::FEATURE_ARC => $this->arcPoints($shape, $roomPolygon) ?: $this->rectanglePoints($shape),
            CeilingProjectRoom::FEATURE_ROUNDED_CORNER => $this->roundedCornerPoints($shape, $roomPolygon) ?: $this->rectanglePoints($shape),
            default => $this->rectanglePoints($shape),
        };
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<int, array{x: float|int, y: float|int}>  $roomPolygon
     * @return array{points: array<int, array{x: float, y: float}>, area_m2: float, perimeter_m: float}
     */
    public function metrics(array $shape, array $roomPolygon = []): array
    {
        $figure = (string) ($shape['figure'] ?? CeilingProjectRoom::FEATURE_RECTANGLE);
        $shapePoints = $this->normalizePoints($shape['shape_points'] ?? null, 3);
        if ($shapePoints !== []) {
            return $this->metricsFromPoints($figure, $shapePoints);
        }

        if ($figure === CeilingProjectRoom::FEATURE_ARC) {
            $arcPoints = $this->arcPoints($shape, $roomPolygon);
            if ($arcPoints !== []) {
                return $this->metricsFromPoints($figure, $arcPoints);
            }
        }

        if ($figure === CeilingProjectRoom::FEATURE_ROUNDED_CORNER) {
            $roundedCornerPoints = $this->roundedCornerPoints($shape, $roomPolygon);
            if ($roundedCornerPoints !== []) {
                return $this->metricsFromPoints($figure, $roundedCornerPoints);
            }
        }

        $width = max(0.0, (float) ($this->toFloat($shape['width_m'] ?? null) ?? 0.0));
        $height = max(0.0, (float) ($this->toFloat($shape['height_m'] ?? null) ?? 0.0));

        return match ($figure) {
            CeilingProjectRoom::FEATURE_CIRCLE => [
                'points' => [],
                'area_m2' => max(0.0, pi() * ((min($width, $height) / 2) ** 2)),
                'perimeter_m' => max(0.0, 2 * pi() * (min($width, $height) / 2)),
            ],
            CeilingProjectRoom::FEATURE_TRIANGLE => [
                'points' => [],
                'area_m2' => max(0.0, ($width * $height) / 2),
                'perimeter_m' => max(0.0, $width + $height + sqrt(($width ** 2) + ($height ** 2))),
            ],
            default => [
                'points' => [],
                'area_m2' => max(0.0, $width * $height),
                'perimeter_m' => max(0.0, ($width * 2) + ($height * 2)),
            ],
        };
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<int, array{x: float|int, y: float|int}>  $roomPolygon
     * @return array{min_x: float, min_y: float, max_x: float, max_y: float}|null
     */
    public function bounds(array $shape, array $roomPolygon = []): ?array
    {
        $points = $this->points($shape, $roomPolygon);
        if (count($points) >= 3) {
            return [
                'min_x' => $this->roundValue((float) min(array_column($points, 'x'))),
                'min_y' => $this->roundValue((float) min(array_column($points, 'y'))),
                'max_x' => $this->roundValue((float) max(array_column($points, 'x'))),
                'max_y' => $this->roundValue((float) max(array_column($points, 'y'))),
            ];
        }

        $x = $this->toFloat($shape['x_m'] ?? null);
        $y = $this->toFloat($shape['y_m'] ?? null);
        $width = $this->toFloat($shape['width_m'] ?? null);
        $height = $this->toFloat($shape['height_m'] ?? null);

        if ($x === null || $y === null || $width === null || $height === null || $width <= 0 || $height <= 0) {
            return null;
        }

        return [
            'min_x' => $this->roundValue($x),
            'min_y' => $this->roundValue($y),
            'max_x' => $this->roundValue($x + $width),
            'max_y' => $this->roundValue($y + $height),
        ];
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     * @return array{points: array<int, array{x: float, y: float}>, area_m2: float, perimeter_m: float}
     */
    private function metricsFromPoints(string $figure, array $points): array
    {
        return [
            'points' => $points,
            'area_m2' => $this->polygonArea($points),
            'perimeter_m' => match ($figure) {
                CeilingProjectRoom::FEATURE_ARC => $this->arcPerimeterDelta($points),
                CeilingProjectRoom::FEATURE_ROUNDED_CORNER => $this->roundedCornerPerimeterDelta($points),
                default => $this->polygonPerimeter($points),
            },
        ];
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<int, array{x: float, y: float}>
     */
    private function rectanglePoints(array $shape): array
    {
        $x = $this->toFloat($shape['x_m'] ?? null);
        $y = $this->toFloat($shape['y_m'] ?? null);
        $width = $this->toFloat($shape['width_m'] ?? null);
        $height = $this->toFloat($shape['height_m'] ?? null);

        if ($x === null || $y === null || $width === null || $height === null || $width <= 0 || $height <= 0) {
            return [];
        }

        return [
            ['x' => $this->roundValue($x), 'y' => $this->roundValue($y)],
            ['x' => $this->roundValue($x + $width), 'y' => $this->roundValue($y)],
            ['x' => $this->roundValue($x + $width), 'y' => $this->roundValue($y + $height)],
            ['x' => $this->roundValue($x), 'y' => $this->roundValue($y + $height)],
        ];
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<int, array{x: float, y: float}>
     */
    private function trianglePoints(array $shape): array
    {
        $x = $this->toFloat($shape['x_m'] ?? null);
        $y = $this->toFloat($shape['y_m'] ?? null);
        $width = $this->toFloat($shape['width_m'] ?? null);
        $height = $this->toFloat($shape['height_m'] ?? null);

        if ($x === null || $y === null || $width === null || $height === null || $width <= 0 || $height <= 0) {
            return [];
        }

        return [
            ['x' => $this->roundValue($x), 'y' => $this->roundValue($y + $height)],
            ['x' => $this->roundValue($x), 'y' => $this->roundValue($y)],
            ['x' => $this->roundValue($x + $width), 'y' => $this->roundValue($y + $height)],
        ];
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<int, array{x: float, y: float}>
     */
    private function circlePoints(array $shape): array
    {
        $x = $this->toFloat($shape['x_m'] ?? null);
        $y = $this->toFloat($shape['y_m'] ?? null);
        $width = $this->toFloat($shape['width_m'] ?? null);
        $height = $this->toFloat($shape['height_m'] ?? null);

        if ($x === null || $y === null || $width === null || $height === null || $width <= 0 || $height <= 0) {
            return [];
        }

        $centerX = $x + ($width / 2);
        $centerY = $y + ($height / 2);
        $radiusX = $width / 2;
        $radiusY = $height / 2;
        $points = [];

        for ($index = 0; $index < 20; $index++) {
            $angle = (2 * pi() * $index) / 20;
            $points[] = [
                'x' => $this->roundValue($centerX + (cos($angle) * $radiusX)),
                'y' => $this->roundValue($centerY + (sin($angle) * $radiusY)),
            ];
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<int, array{x: float|int, y: float|int}>  $roomPolygon
     * @return array<int, array{x: float, y: float}>
     */
    private function arcPoints(array $shape, array $roomPolygon): array
    {
        $roomPolygon = $this->normalizePoints($roomPolygon, 3);
        $segmentIndex = is_numeric($shape['source_segment_index'] ?? null) ? (int) $shape['source_segment_index'] : null;
        $depth = $this->toFloat($shape['depth_m'] ?? null);

        if ($roomPolygon === [] || $segmentIndex === null || $segmentIndex < 0 || $segmentIndex >= count($roomPolygon) || $depth === null || $depth <= 0) {
            return [];
        }

        $segmentStart = $roomPolygon[$segmentIndex];
        $segmentEnd = $roomPolygon[($segmentIndex + 1) % count($roomPolygon)];
        $segmentLength = $this->distanceBetweenPoints($segmentStart, $segmentEnd);
        if ($segmentLength <= 0.0001) {
            return [];
        }

        $offset = max(0.0, min($segmentLength, (float) ($this->toFloat($shape['offset_m'] ?? null) ?? 0.0)));
        $span = $this->resolveArcSpan($shape, $segmentStart, $segmentEnd, $depth);
        if ($span === null || $span <= 0.02) {
            return [];
        }

        $endOffset = max($offset + 0.02, min($segmentLength, $offset + $span));
        if (($endOffset - $offset) <= 0.02) {
            return [];
        }

        $normal = $this->segmentNormal($roomPolygon, $segmentIndex, (($shape['direction'] ?? 'inward') !== 'outward'));
        if ($normal === null) {
            return [];
        }

        $startPoint = $this->pointAlongSegment($segmentStart, $segmentEnd, $offset);
        $endPoint = $this->pointAlongSegment($segmentStart, $segmentEnd, $endOffset);
        $points = [];
        $steps = 14;

        for ($step = 0; $step <= $steps; $step++) {
            $t = $step / $steps;
            $baseX = $startPoint['x'] + (($endPoint['x'] - $startPoint['x']) * $t);
            $baseY = $startPoint['y'] + (($endPoint['y'] - $startPoint['y']) * $t);
            $bulge = sin(pi() * $t) * $depth;
            $points[] = [
                'x' => $this->roundValue($baseX + ($normal['x'] * $bulge)),
                'y' => $this->roundValue($baseY + ($normal['y'] * $bulge)),
            ];
        }

        return $points;
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<int, array{x: float|int, y: float|int}>  $roomPolygon
     * @return array<int, array{x: float, y: float}>
     */
    private function roundedCornerPoints(array $shape, array $roomPolygon): array
    {
        $roomPolygon = $this->normalizePoints($roomPolygon, 3);
        $cornerIndex = is_numeric($shape['source_point_index'] ?? null) ? (int) $shape['source_point_index'] : null;
        $radius = $this->toFloat($shape['radius_m'] ?? null);

        if ($roomPolygon === [] || $cornerIndex === null || $cornerIndex < 0 || $cornerIndex >= count($roomPolygon) || $radius === null || $radius <= 0) {
            return [];
        }

        $current = $roomPolygon[$cornerIndex];
        $previous = $roomPolygon[($cornerIndex - 1 + count($roomPolygon)) % count($roomPolygon)];
        $next = $roomPolygon[($cornerIndex + 1) % count($roomPolygon)];
        $vectorToPrevious = $this->normalizeVector([
            'x' => $previous['x'] - $current['x'],
            'y' => $previous['y'] - $current['y'],
        ]);
        $vectorToNext = $this->normalizeVector([
            'x' => $next['x'] - $current['x'],
            'y' => $next['y'] - $current['y'],
        ]);

        if ($vectorToPrevious === null || $vectorToNext === null) {
            return [];
        }

        $rawDot = $this->clamp(
            ($vectorToPrevious['x'] * $vectorToNext['x']) + ($vectorToPrevious['y'] * $vectorToNext['y']),
            -1.0,
            1.0
        );
        $angle = acos($rawDot);
        if (!is_finite($angle) || $angle <= 0.2 || $angle >= (pi() - 0.05)) {
            return [];
        }

        $tangentDistance = $radius / tan($angle / 2);
        $maxDistance = min(
            $this->distanceBetweenPoints($current, $previous),
            $this->distanceBetweenPoints($current, $next)
        ) - 0.02;
        if (!is_finite($maxDistance) || $maxDistance <= 0.02) {
            return [];
        }

        $safeDistance = $this->clamp($tangentDistance, 0.03, $maxDistance);
        $safeRadius = $this->roundValue($safeDistance * tan($angle / 2));
        $bisector = $this->normalizeVector([
            'x' => $vectorToPrevious['x'] + $vectorToNext['x'],
            'y' => $vectorToPrevious['y'] + $vectorToNext['y'],
        ]);

        if ($bisector === null) {
            return [];
        }

        $centerDistance = $safeRadius / sin($angle / 2);
        $tangentStart = [
            'x' => $this->roundValue($current['x'] + ($vectorToPrevious['x'] * $safeDistance)),
            'y' => $this->roundValue($current['y'] + ($vectorToPrevious['y'] * $safeDistance)),
        ];
        $tangentEnd = [
            'x' => $this->roundValue($current['x'] + ($vectorToNext['x'] * $safeDistance)),
            'y' => $this->roundValue($current['y'] + ($vectorToNext['y'] * $safeDistance)),
        ];
        $centerPoint = [
            'x' => $this->roundValue($current['x'] + ($bisector['x'] * $centerDistance)),
            'y' => $this->roundValue($current['y'] + ($bisector['y'] * $centerDistance)),
        ];

        $startAngle = atan2($tangentStart['y'] - $centerPoint['y'], $tangentStart['x'] - $centerPoint['x']);
        $endAngle = atan2($tangentEnd['y'] - $centerPoint['y'], $tangentEnd['x'] - $centerPoint['x']);
        $delta = $endAngle - $startAngle;
        while ($delta <= -pi()) {
            $delta += 2 * pi();
        }
        while ($delta > pi()) {
            $delta -= 2 * pi();
        }

        $points = [$current, $tangentStart];
        $steps = 12;
        for ($step = 1; $step < $steps; $step++) {
            $nextAngle = $startAngle + (($delta * $step) / $steps);
            $points[] = [
                'x' => $this->roundValue($centerPoint['x'] + (cos($nextAngle) * $safeRadius)),
                'y' => $this->roundValue($centerPoint['y'] + (sin($nextAngle) * $safeRadius)),
            ];
        }
        $points[] = $tangentEnd;

        return $points;
    }

    /**
     * @param  array<string, mixed>  $shape
     */
    private function resolveArcSpan(array $shape, array $segmentStart, array $segmentEnd, float $depth): ?float
    {
        $explicitSpan = $this->toFloat($shape['span_m'] ?? null);
        if ($explicitSpan !== null && $explicitSpan > 0.02) {
            return $explicitSpan;
        }

        $width = $this->toFloat($shape['width_m'] ?? null);
        $height = $this->toFloat($shape['height_m'] ?? null);
        if ($width === null || $height === null || $width <= 0 || $height <= 0) {
            return null;
        }

        $segmentVector = $this->normalizeVector([
            'x' => $segmentEnd['x'] - $segmentStart['x'],
            'y' => $segmentEnd['y'] - $segmentStart['y'],
        ]);
        if ($segmentVector === null) {
            return null;
        }

        $absX = abs($segmentVector['x']);
        $absY = abs($segmentVector['y']);
        $candidates = [];

        if ($absX > 0.0001) {
            $candidate = ($width - ($absY * $depth)) / $absX;
            if (is_finite($candidate) && $candidate > 0.02) {
                $candidates[] = $candidate;
            }
        }

        if ($absY > 0.0001) {
            $candidate = ($height - ($absX * $depth)) / $absY;
            if (is_finite($candidate) && $candidate > 0.02) {
                $candidates[] = $candidate;
            }
        }

        if ($candidates !== []) {
            return array_sum($candidates) / count($candidates);
        }

        return max($width, $height);
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function arcPerimeterDelta(array $points): float
    {
        if (count($points) < 2) {
            return 0.0;
        }

        return $this->polylineLength($points) - $this->distanceBetweenPoints($points[0], $points[count($points) - 1]);
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function roundedCornerPerimeterDelta(array $points): float
    {
        if (count($points) < 3) {
            return 0.0;
        }

        $current = $points[0];
        $tangentStart = $points[1];
        $tangentEnd = $points[count($points) - 1];

        return $this->polylineLength(array_slice($points, 1))
            - $this->distanceBetweenPoints($current, $tangentStart)
            - $this->distanceBetweenPoints($current, $tangentEnd);
    }

    /**
     * @param  array<int, array{x: float|int, y: float|int}>  $points
     * @return array<int, array{x: float, y: float}>
     */
    private function normalizePoints(mixed $points, int $minimum): array
    {
        if (!is_array($points)) {
            return [];
        }

        $normalized = [];
        foreach ($points as $point) {
            if (!is_array($point) || !is_numeric($point['x'] ?? null) || !is_numeric($point['y'] ?? null)) {
                continue;
            }

            $normalized[] = [
                'x' => $this->roundValue((float) $point['x']),
                'y' => $this->roundValue((float) $point['y']),
            ];
        }

        return count($normalized) >= $minimum ? array_values($normalized) : [];
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function polygonArea(array $points): float
    {
        if (count($points) < 3) {
            return 0.0;
        }

        $sum = 0.0;
        $count = count($points);
        for ($index = 0; $index < $count; $index++) {
            $next = ($index + 1) % $count;
            $sum += ($points[$index]['x'] * $points[$next]['y']) - ($points[$next]['x'] * $points[$index]['y']);
        }

        return abs($sum / 2);
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function signedPolygonArea(array $points): float
    {
        if (count($points) < 3) {
            return 0.0;
        }

        $sum = 0.0;
        $count = count($points);
        for ($index = 0; $index < $count; $index++) {
            $next = ($index + 1) % $count;
            $sum += ($points[$index]['x'] * $points[$next]['y']) - ($points[$next]['x'] * $points[$index]['y']);
        }

        return $sum / 2;
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function polygonPerimeter(array $points): float
    {
        return $this->polylineLength($points, true);
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function polylineLength(array $points, bool $closed = false): float
    {
        if (count($points) < 2) {
            return 0.0;
        }

        $total = 0.0;
        for ($index = 1; $index < count($points); $index++) {
            $total += $this->distanceBetweenPoints($points[$index - 1], $points[$index]);
        }

        if ($closed && count($points) > 2) {
            $total += $this->distanceBetweenPoints($points[count($points) - 1], $points[0]);
        }

        return $total;
    }

    /**
     * @param  array{x: float, y: float}  $start
     * @param  array{x: float, y: float}  $end
     */
    private function pointAlongSegment(array $start, array $end, float $distance): array
    {
        $segmentLength = $this->distanceBetweenPoints($start, $end);
        if ($segmentLength <= 0.0001) {
            return [
                'x' => $this->roundValue($start['x']),
                'y' => $this->roundValue($start['y']),
            ];
        }

        $ratio = $distance / $segmentLength;

        return [
            'x' => $this->roundValue($start['x'] + (($end['x'] - $start['x']) * $ratio)),
            'y' => $this->roundValue($start['y'] + (($end['y'] - $start['y']) * $ratio)),
        ];
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $roomPolygon
     * @return array{x: float, y: float}|null
     */
    private function segmentNormal(array $roomPolygon, int $segmentIndex, bool $inward = true): ?array
    {
        if ($segmentIndex < 0 || $segmentIndex >= count($roomPolygon)) {
            return null;
        }

        $start = $roomPolygon[$segmentIndex];
        $end = $roomPolygon[($segmentIndex + 1) % count($roomPolygon)];
        $dx = $end['x'] - $start['x'];
        $dy = $end['y'] - $start['y'];
        $length = sqrt(($dx ** 2) + ($dy ** 2));

        if ($length <= 0.0001) {
            return null;
        }

        $leftNormal = ['x' => -$dy / $length, 'y' => $dx / $length];
        $rightNormal = ['x' => $dy / $length, 'y' => -$dx / $length];
        $polygonIsCcw = $this->signedPolygonArea($roomPolygon) > 0;
        $normal = $inward
            ? ($polygonIsCcw ? $leftNormal : $rightNormal)
            : ($polygonIsCcw ? $rightNormal : $leftNormal);

        return [
            'x' => $normal['x'],
            'y' => $normal['y'],
        ];
    }

    /**
     * @param  array<string, mixed>  $vector
     * @return array{x: float, y: float}|null
     */
    private function normalizeVector(array $vector): ?array
    {
        $x = $this->toFloat($vector['x'] ?? null);
        $y = $this->toFloat($vector['y'] ?? null);
        $length = sqrt((($x ?? 0.0) ** 2) + (($y ?? 0.0) ** 2));

        if ($x === null || $y === null || $length <= 0.0001) {
            return null;
        }

        return [
            'x' => $x / $length,
            'y' => $y / $length,
        ];
    }

    /**
     * @param  array{x: float, y: float}  $start
     * @param  array{x: float, y: float}  $end
     */
    private function distanceBetweenPoints(array $start, array $end): float
    {
        return sqrt((($end['x'] - $start['x']) ** 2) + (($end['y'] - $start['y']) ** 2));
    }

    private function clamp(float $value, float $min, float $max): float
    {
        return min($max, max($min, $value));
    }

    private function toFloat(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private function roundValue(float $value, int $precision = 2): float
    {
        return round($value, $precision);
    }
}
