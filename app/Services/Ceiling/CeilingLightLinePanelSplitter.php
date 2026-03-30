<?php

namespace App\Services\Ceiling;

class CeilingLightLinePanelSplitter
{
    /**
     * @param  array<int, array{x: float|int, y: float|int}>  $polygon
     * @param  array<int, array{points?: array<int, array{x: float|int, y: float|int}>, width_m?: float|int|string|null, closed?: bool}>  $shapes
     * @param  array<string, mixed>  $productionSettings
     * @return array<int, array<string, mixed>>
     */
    public function split(array $polygon, array $shapes, array $productionSettings = []): array
    {
        $normalizedPolygon = $this->normalizePoints($polygon, 3);
        if (count($normalizedPolygon) < 3) {
            return [];
        }

        $normalizedShapes = $this->normalizeShapes($shapes);
        if ($normalizedShapes === []) {
            return [$this->buildSinglePanel($normalizedPolygon, $productionSettings)];
        }

        $xs = array_column($normalizedPolygon, 'x');
        $ys = array_column($normalizedPolygon, 'y');
        if ($xs === [] || $ys === []) {
            return [];
        }

        $minX = min($xs);
        $maxX = max($xs);
        $minY = min($ys);
        $maxY = max($ys);
        $step = 0.05;
        $cols = max(1, (int) ceil(($maxX - $minX) / $step));
        $rows = max(1, (int) ceil(($maxY - $minY) / $step));
        $grid = array_fill(0, $rows, array_fill(0, $cols, 0));

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $point = [
                    'x' => $minX + (($col + 0.5) * $step),
                    'y' => $minY + (($row + 0.5) * $step),
                ];

                if (!$this->pointInsidePolygon($point, $normalizedPolygon)) {
                    $grid[$row][$col] = -1;
                    continue;
                }

                foreach ($normalizedShapes as $shape) {
                    $halfWidth = max((float) $shape['width_m'], $step) / 2;
                    if ($this->distanceToPolyline($point, $shape['points'], (bool) $shape['closed']) <= $halfWidth) {
                        $grid[$row][$col] = -1;
                        break;
                    }
                }
            }
        }

        $panels = [];
        $components = 0;
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                if ($grid[$row][$col] !== 0) {
                    continue;
                }

                $components++;
                $queue = [[$row, $col]];
                $grid[$row][$col] = $components;
                $cells = [];

                while ($queue !== []) {
                    [$currentRow, $currentCol] = array_shift($queue);
                    $cells[] = [$currentRow, $currentCol];

                    foreach ([[-1, 0], [1, 0], [0, -1], [0, 1]] as [$rowOffset, $colOffset]) {
                        $nextRow = $currentRow + $rowOffset;
                        $nextCol = $currentCol + $colOffset;
                        if ($nextRow < 0 || $nextCol < 0 || $nextRow >= $rows || $nextCol >= $cols) {
                            continue;
                        }
                        if ($grid[$nextRow][$nextCol] !== 0) {
                            continue;
                        }

                        $grid[$nextRow][$nextCol] = $components;
                        $queue[] = [$nextRow, $nextCol];
                    }
                }

                $panels[] = $this->buildPanelFromCells($components, $cells, $minX, $minY, $step, $productionSettings);
            }
        }

        $panels = array_values(array_filter($panels, fn (array $panel) => (float) ($panel['area_m2'] ?? 0.0) > 0.02));
        if ($panels === []) {
            return [$this->buildSinglePanel($normalizedPolygon, $productionSettings)];
        }

        usort($panels, fn (array $left, array $right) => ($right['area_m2'] <=> $left['area_m2']));

        return array_map(function (array $panel, int $index) {
            $panel['id'] = 'panel_'.($index + 1);
            $panel['label'] = 'Полотно '.($index + 1);

            return $panel;
        }, $panels, array_keys($panels));
    }

    /**
     * @param  array<int, array{x: float|int, y: float|int}>  $polygon
     * @param  array<string, mixed>  $productionSettings
     * @return array<string, mixed>
     */
    private function buildSinglePanel(array $polygon, array $productionSettings): array
    {
        $area = $this->polygonArea($polygon);
        $centroid = $this->centroidFromPoints($polygon);
        $xs = array_column($polygon, 'x');
        $ys = array_column($polygon, 'y');

        return [
            'id' => 'panel_1',
            'label' => 'Полотно 1',
            'area_m2' => $this->round($area),
            'cells_count' => 0,
            'centroid' => $centroid,
            'bounds' => [
                'min_x' => $this->round(min($xs)),
                'min_y' => $this->round(min($ys)),
                'max_x' => $this->round(max($xs)),
                'max_y' => $this->round(max($ys)),
            ],
            'production' => $this->productionPayload($productionSettings),
        ];
    }

    /**
     * @param  array<int, array{0:int, 1:int}>  $cells
     * @param  array<string, mixed>  $productionSettings
     * @return array<string, mixed>
     */
    private function buildPanelFromCells(int $id, array $cells, float $minX, float $minY, float $step, array $productionSettings): array
    {
        $centroid = ['x' => 0.0, 'y' => 0.0];
        $minCol = PHP_INT_MAX;
        $maxCol = PHP_INT_MIN;
        $minRow = PHP_INT_MAX;
        $maxRow = PHP_INT_MIN;

        foreach ($cells as [$row, $col]) {
            $centerX = $minX + (($col + 0.5) * $step);
            $centerY = $minY + (($row + 0.5) * $step);
            $centroid['x'] += $centerX;
            $centroid['y'] += $centerY;
            $minCol = min($minCol, $col);
            $maxCol = max($maxCol, $col);
            $minRow = min($minRow, $row);
            $maxRow = max($maxRow, $row);
        }

        $count = max(1, count($cells));

        return [
            'id' => 'panel_'.$id,
            'label' => 'Полотно '.$id,
            'area_m2' => $this->round($count * $step * $step),
            'cells_count' => $count,
            'centroid' => [
                'x' => $this->round($centroid['x'] / $count),
                'y' => $this->round($centroid['y'] / $count),
            ],
            'bounds' => [
                'min_x' => $this->round($minX + ($minCol * $step)),
                'min_y' => $this->round($minY + ($minRow * $step)),
                'max_x' => $this->round($minX + (($maxCol + 1) * $step)),
                'max_y' => $this->round($minY + (($maxRow + 1) * $step)),
            ],
            'production' => $this->productionPayload($productionSettings),
        ];
    }

    /**
     * @param  array<string, mixed>  $productionSettings
     * @return array<string, mixed>
     */
    private function productionPayload(array $productionSettings): array
    {
        return [
            'texture' => (string) ($productionSettings['texture'] ?? 'matte'),
            'roll_width_cm' => (int) ($productionSettings['roll_width_cm'] ?? 320),
            'harpoon_type' => (string) ($productionSettings['harpoon_type'] ?? 'standard'),
            'same_roll_required' => (bool) ($productionSettings['same_roll_required'] ?? false),
            'special_cutting' => (bool) ($productionSettings['special_cutting'] ?? false),
            'seam_enabled' => (bool) ($productionSettings['seam_enabled'] ?? false),
            'shrink_x_percent' => $this->round((float) ($productionSettings['shrink_x_percent'] ?? 7.0)),
            'shrink_y_percent' => $this->round((float) ($productionSettings['shrink_y_percent'] ?? 7.0)),
            'orientation_mode' => (string) ($productionSettings['orientation_mode'] ?? 'parallel_segment'),
            'orientation_segment_index' => (int) ($productionSettings['orientation_segment_index'] ?? 0),
            'orientation_offset_m' => $this->round((float) ($productionSettings['orientation_offset_m'] ?? 0.0)),
            'seam_offset_m' => $this->round((float) ($productionSettings['seam_offset_m'] ?? 0.0)),
            'comment' => $this->trimNullable($productionSettings['comment'] ?? null),
        ];
    }

    /**
     * @param  array<int, mixed>  $shapes
     * @return array<int, array{points: array<int, array{x: float, y: float}>, width_m: float, closed: bool}>
     */
    private function normalizeShapes(array $shapes): array
    {
        $normalized = [];

        foreach ($shapes as $shape) {
            if (!is_array($shape)) {
                continue;
            }

            $points = $this->normalizePoints($shape['points'] ?? null, 2);
            if (count($points) < 2) {
                continue;
            }

            $normalized[] = [
                'points' => $points,
                'width_m' => max(0.01, (float) ($shape['width_m'] ?? 0.05)),
                'closed' => (bool) ($shape['closed'] ?? false),
            ];
        }

        return $normalized;
    }

    /**
     * @param  mixed  $points
     * @return array<int, array{x: float, y: float}>
     */
    private function normalizePoints(mixed $points, int $minimum): array
    {
        if (!is_array($points)) {
            return [];
        }

        $normalized = [];
        foreach ($points as $point) {
            if (!is_array($point) || !isset($point['x'], $point['y']) || !is_numeric($point['x']) || !is_numeric($point['y'])) {
                continue;
            }

            $normalized[] = [
                'x' => $this->round((float) $point['x']),
                'y' => $this->round((float) $point['y']),
            ];
        }

        return count($normalized) >= $minimum ? $normalized : [];
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $polygon
     */
    private function polygonArea(array $polygon): float
    {
        $sum = 0.0;
        $count = count($polygon);

        for ($index = 0; $index < $count; $index++) {
            $next = ($index + 1) % $count;
            $sum += ($polygon[$index]['x'] * $polygon[$next]['y']) - ($polygon[$next]['x'] * $polygon[$index]['y']);
        }

        return abs($sum) / 2;
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     * @return array{x: float, y: float}|null
     */
    private function centroidFromPoints(array $points): ?array
    {
        if ($points === []) {
            return null;
        }

        $sum = ['x' => 0.0, 'y' => 0.0];
        foreach ($points as $point) {
            $sum['x'] += $point['x'];
            $sum['y'] += $point['y'];
        }

        return [
            'x' => $this->round($sum['x'] / count($points)),
            'y' => $this->round($sum['y'] / count($points)),
        ];
    }

    /**
     * @param  array{x: float, y: float}  $point
     * @param  array<int, array{x: float, y: float}>  $polygon
     */
    private function pointInsidePolygon(array $point, array $polygon): bool
    {
        $inside = false;
        $count = count($polygon);

        for ($index = 0, $previous = $count - 1; $index < $count; $previous = $index, $index++) {
            $current = $polygon[$index];
            $previousPoint = $polygon[$previous];
            $denominator = $previousPoint['y'] - $current['y'];
            $safeDenominator = abs($denominator) < 0.000001
                ? ($denominator >= 0 ? 0.000001 : -0.000001)
                : $denominator;

            $intersect = (($current['y'] > $point['y']) !== ($previousPoint['y'] > $point['y']))
                && ($point['x'] < ((($previousPoint['x'] - $current['x']) * ($point['y'] - $current['y'])) / $safeDenominator) + $current['x']);

            if ($intersect) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    /**
     * @param  array{x: float, y: float}  $point
     * @param  array<int, array{x: float, y: float}>  $points
     */
    private function distanceToPolyline(array $point, array $points, bool $closed = false): float
    {
        $count = count($points);
        if ($count < 2) {
            return INF;
        }

        $best = INF;
        for ($index = 1; $index < $count; $index++) {
            $best = min($best, $this->distanceToSegment($point, $points[$index - 1], $points[$index]));
        }

        if ($closed && $count > 2) {
            $best = min($best, $this->distanceToSegment($point, $points[$count - 1], $points[0]));
        }

        return $best;
    }

    /**
     * @param  array{x: float, y: float}  $point
     * @param  array{x: float, y: float}  $start
     * @param  array{x: float, y: float}  $end
     */
    private function distanceToSegment(array $point, array $start, array $end): float
    {
        $dx = $end['x'] - $start['x'];
        $dy = $end['y'] - $start['y'];

        if ($dx === 0.0 && $dy === 0.0) {
            return sqrt((($point['x'] - $start['x']) ** 2) + (($point['y'] - $start['y']) ** 2));
        }

        $t = max(0.0, min(1.0, ((($point['x'] - $start['x']) * $dx) + (($point['y'] - $start['y']) * $dy)) / (($dx ** 2) + ($dy ** 2))));
        $projectionX = $start['x'] + ($t * $dx);
        $projectionY = $start['y'] + ($t * $dy);

        return sqrt((($point['x'] - $projectionX) ** 2) + (($point['y'] - $projectionY) ** 2));
    }

    private function round(float $value): float
    {
        return round($value, 2);
    }

    private function trimNullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }
}
