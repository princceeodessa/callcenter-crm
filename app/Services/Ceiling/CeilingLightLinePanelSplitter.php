<?php

namespace App\Services\Ceiling;

use App\Models\CeilingProjectRoom;
use App\Support\Ceiling\FeatureShapeGeometry;

class CeilingLightLinePanelSplitter
{
    /**
     * @param  array<int, array{x: float|int, y: float|int}>  $polygon
     * @param  array<int, mixed>  $shapes
     * @param  array<string, mixed>  $productionSettings
     * @param  array<int, mixed>  $featureShapes
     * @return array<int, array<string, mixed>>
     */
    public function split(array $polygon, array $shapes = [], array $productionSettings = [], array $featureShapes = []): array
    {
        $polygon = $this->normalizePoints($polygon, 3);
        if ($polygon === []) {
            return [];
        }

        $shapes = $this->normalizeShapes($shapes);
        $featureBlockers = $this->normalizeFeatureBlockers($featureShapes, $polygon);
        if ($shapes === [] && $featureBlockers === []) {
            return [$this->buildSinglePanel($polygon, $productionSettings)];
        }

        $xs = array_column($polygon, 'x');
        $ys = array_column($polygon, 'y');
        $minX = (float) min($xs);
        $maxX = (float) max($xs);
        $minY = (float) min($ys);
        $maxY = (float) max($ys);
        $step = 0.05;
        $cols = max(1, (int) ceil(($maxX - $minX) / $step));
        $rows = max(1, (int) ceil(($maxY - $minY) / $step));
        $grid = array_fill(0, $rows, array_fill(0, $cols, 0));

        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                $center = [
                    'x' => $minX + (($col + 0.5) * $step),
                    'y' => $minY + (($row + 0.5) * $step),
                ];

                if (!$this->pointInsidePolygon($center, $polygon)) {
                    $grid[$row][$col] = -1;
                    continue;
                }

                $blocked = false;
                foreach ($shapes as $shape) {
                    $halfWidth = max((float) ($shape['width_m'] ?? 0.05), $step) / 2;
                    if ($this->distanceToPolyline($center, $shape['points'], (bool) ($shape['closed'] ?? false)) <= $halfWidth) {
                        $blocked = true;
                        break;
                    }
                }

                if (!$blocked) {
                    foreach ($featureBlockers as $blocker) {
                        if ($this->pointInsidePolygon($center, $blocker['polygon'])) {
                            $blocked = true;
                            break;
                        }

                        if (
                            !$blocked
                            && is_array($blocker['connector'] ?? null)
                            && $this->distanceToPolyline($center, $blocker['connector']['points'], false) <= ((float) ($blocker['connector']['width_m'] ?? 0.02) / 2)
                        ) {
                            $blocked = true;
                            break;
                        }
                    }
                }

                if ($blocked) {
                    $grid[$row][$col] = -1;
                }
            }
        }

        $panels = [];
        $nextId = 1;
        for ($row = 0; $row < $rows; $row++) {
            for ($col = 0; $col < $cols; $col++) {
                if ($grid[$row][$col] !== 0) {
                    continue;
                }

                $queue = [[$row, $col]];
                $grid[$row][$col] = $nextId;
                $cells = [];

                while ($queue !== []) {
                    [$currentRow, $currentCol] = array_shift($queue);
                    $cells[] = [$currentRow, $currentCol];

                    foreach ([
                        [$currentRow - 1, $currentCol],
                        [$currentRow + 1, $currentCol],
                        [$currentRow, $currentCol - 1],
                        [$currentRow, $currentCol + 1],
                    ] as [$nextRow, $nextCol]) {
                        if ($nextRow < 0 || $nextRow >= $rows || $nextCol < 0 || $nextCol >= $cols || $grid[$nextRow][$nextCol] !== 0) {
                            continue;
                        }

                        $grid[$nextRow][$nextCol] = $nextId;
                        $queue[] = [$nextRow, $nextCol];
                    }
                }

                $panel = $this->buildPanelFromCells($nextId, $cells, $minX, $minY, $step, $productionSettings);
                if ((float) ($panel['area_m2'] ?? 0) > 0.02) {
                    $panels[] = $panel;
                }
                $nextId++;
            }
        }

        if ($panels === []) {
            return [$this->buildSinglePanel($polygon, $productionSettings)];
        }

        usort($panels, static fn (array $left, array $right) => (float) ($right['area_m2'] ?? 0) <=> (float) ($left['area_m2'] ?? 0));

        return array_values(array_map(function (array $panel, int $index) {
            $panel['id'] = 'panel_'.($index + 1);
            $panel['label'] = 'Полотно '.($index + 1);
            return $panel;
        }, $panels, array_keys($panels)));
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
            'shape_points' => $polygon,
            'bounds' => [
                'min_x' => $this->round(min($xs)),
                'min_y' => $this->round(min($ys)),
                'max_x' => $this->round(max($xs)),
                'max_y' => $this->round(max($ys)),
            ],
            'source' => 'room',
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
        $contours = $this->tracePanelContours($cells, $minX, $minY, $step);
        $shapePoints = $contours['outer'] ?? [
            ['x' => $this->round($minX + ($minCol * $step)), 'y' => $this->round($minY + ($minRow * $step))],
            ['x' => $this->round($minX + (($maxCol + 1) * $step)), 'y' => $this->round($minY + ($minRow * $step))],
            ['x' => $this->round($minX + (($maxCol + 1) * $step)), 'y' => $this->round($minY + (($maxRow + 1) * $step))],
            ['x' => $this->round($minX + ($minCol * $step)), 'y' => $this->round($minY + (($maxRow + 1) * $step))],
        ];
        $bounds = $this->boundsFromPoints($shapePoints) ?? [
            'min_x' => $this->round($minX + ($minCol * $step)),
            'min_y' => $this->round($minY + ($minRow * $step)),
            'max_x' => $this->round($minX + (($maxCol + 1) * $step)),
            'max_y' => $this->round($minY + (($maxRow + 1) * $step)),
        ];

        return [
            'id' => 'panel_'.$id,
            'label' => 'Полотно '.$id,
            'area_m2' => $this->round($count * $step * $step),
            'cells_count' => $count,
            'centroid' => [
                'x' => $this->round($centroid['x'] / $count),
                'y' => $this->round($centroid['y'] / $count),
            ],
            'shape_points' => $shapePoints,
            'holes' => $contours['holes'] ?? [],
            'bounds' => $bounds,
            'source' => 'light_line_split',
            'production' => $this->productionPayload($productionSettings),
        ];
    }

    /**
     * @param  array<int, array{0:int, 1:int}>  $cells
     * @return array{outer: array<int, array{x: float, y: float}>|null, holes: array<int, array<int, array{x: float, y: float}>>}
     */
    private function tracePanelContours(array $cells, float $minX, float $minY, float $step): array
    {
        $edges = $this->buildBoundaryEdges($cells);
        if ($edges === []) {
            return ['outer' => null, 'holes' => []];
        }

        $loops = $this->traceBoundaryLoops($edges);
        if ($loops === []) {
            return ['outer' => null, 'holes' => []];
        }

        $worldLoops = [];
        foreach ($loops as $loop) {
            $simplifiedLoop = $this->simplifyGridLoop($loop);
            if (count($simplifiedLoop) < 3) {
                continue;
            }

            $points = array_map(function (array $vertex) use ($minX, $minY, $step) {
                return [
                    'x' => $this->round($minX + ($vertex['x'] * $step)),
                    'y' => $this->round($minY + ($vertex['y'] * $step)),
                ];
            }, $simplifiedLoop);

            if (count($points) >= 3) {
                $worldLoops[] = $points;
            }
        }

        if ($worldLoops === []) {
            return ['outer' => null, 'holes' => []];
        }

        usort($worldLoops, fn (array $left, array $right) => $this->polygonArea($right) <=> $this->polygonArea($left));

        return [
            'outer' => $worldLoops[0],
            'holes' => array_values(array_slice($worldLoops, 1)),
        ];
    }

    /**
     * @param  array<int, array{0:int, 1:int}>  $cells
     * @return array<int, array{0: array{x: int, y: int}, 1: array{x: int, y: int}}>
     */
    private function buildBoundaryEdges(array $cells): array
    {
        $filled = [];
        foreach ($cells as [$row, $col]) {
            $filled[$row.':'.$col] = true;
        }

        $edges = [];
        foreach ($cells as [$row, $col]) {
            if (!isset($filled[($row - 1).':'.$col])) {
                $edges[] = [['x' => $col, 'y' => $row], ['x' => $col + 1, 'y' => $row]];
            }

            if (!isset($filled[$row.':'.($col + 1)])) {
                $edges[] = [['x' => $col + 1, 'y' => $row], ['x' => $col + 1, 'y' => $row + 1]];
            }

            if (!isset($filled[($row + 1).':'.$col])) {
                $edges[] = [['x' => $col + 1, 'y' => $row + 1], ['x' => $col, 'y' => $row + 1]];
            }

            if (!isset($filled[$row.':'.($col - 1)])) {
                $edges[] = [['x' => $col, 'y' => $row + 1], ['x' => $col, 'y' => $row]];
            }
        }

        return $edges;
    }

    /**
     * @param  array<int, array{0: array{x: int, y: int}, 1: array{x: int, y: int}}>  $edges
     * @return array<int, array<int, array{x: int, y: int}>>
     */
    private function traceBoundaryLoops(array $edges): array
    {
        $outgoing = [];
        foreach ($edges as [$start, $end]) {
            $outgoing[$this->gridVertexKey($start)][] = $end;
        }

        $visited = [];
        $loops = [];
        $maxIterations = max(20, count($edges) * 4);

        foreach ($edges as [$start, $end]) {
            $edgeKey = $this->gridEdgeKey($start, $end);
            if (isset($visited[$edgeKey])) {
                continue;
            }

            $loop = [$start];
            $currentStart = $start;
            $currentEnd = $end;
            $iterations = 0;

            while ($iterations < $maxIterations) {
                $visited[$this->gridEdgeKey($currentStart, $currentEnd)] = true;
                $loop[] = $currentEnd;

                if ($this->sameGridVertex($currentEnd, $start)) {
                    break;
                }

                $candidates = array_values(array_filter(
                    $outgoing[$this->gridVertexKey($currentEnd)] ?? [],
                    fn (array $candidate) => !isset($visited[$this->gridEdgeKey($currentEnd, $candidate)])
                ));

                if ($candidates === []) {
                    break;
                }

                $nextEnd = count($candidates) === 1
                    ? $candidates[0]
                    : $this->selectNextBoundaryVertex($currentStart, $currentEnd, $candidates);

                $currentStart = $currentEnd;
                $currentEnd = $nextEnd;
                $iterations++;
            }

            if (count($loop) >= 4 && $this->sameGridVertex($loop[0], $loop[count($loop) - 1])) {
                $loops[] = $loop;
            }
        }

        return $loops;
    }

    /**
     * @param  array{x: int, y: int}  $previous
     * @param  array{x: int, y: int}  $current
     * @param  array<int, array{x: int, y: int}>  $candidates
     * @return array{x: int, y: int}
     */
    private function selectNextBoundaryVertex(array $previous, array $current, array $candidates): array
    {
        $incoming = [
            'x' => $current['x'] - $previous['x'],
            'y' => $current['y'] - $previous['y'],
        ];

        usort($candidates, function (array $left, array $right) use ($current, $incoming) {
            $leftPriority = $this->boundaryTurnPriority($incoming, [
                'x' => $left['x'] - $current['x'],
                'y' => $left['y'] - $current['y'],
            ]);
            $rightPriority = $this->boundaryTurnPriority($incoming, [
                'x' => $right['x'] - $current['x'],
                'y' => $right['y'] - $current['y'],
            ]);

            return $leftPriority <=> $rightPriority;
        });

        return $candidates[0];
    }

    /**
     * @param  array{x: int, y: int}  $incoming
     * @param  array{x: int, y: int}  $outgoing
     */
    private function boundaryTurnPriority(array $incoming, array $outgoing): int
    {
        $incomingIndex = $this->boundaryDirectionIndex($incoming);
        $outgoingIndex = $this->boundaryDirectionIndex($outgoing);
        $turn = ($outgoingIndex - $incomingIndex + 4) % 4;

        return match ($turn) {
            1 => 0,
            0 => 1,
            3 => 2,
            default => 3,
        };
    }

    /**
     * @param  array{x: int, y: int}  $vector
     */
    private function boundaryDirectionIndex(array $vector): int
    {
        if (abs($vector['x']) >= abs($vector['y'])) {
            return $vector['x'] >= 0 ? 0 : 2;
        }

        return $vector['y'] >= 0 ? 1 : 3;
    }

    /**
     * @param  array<int, array{x: int, y: int}>  $loop
     * @return array<int, array{x: int, y: int}>
     */
    private function simplifyGridLoop(array $loop): array
    {
        $normalized = [];
        foreach ($loop as $vertex) {
            if ($normalized !== [] && $this->sameGridVertex($normalized[count($normalized) - 1], $vertex)) {
                continue;
            }

            $normalized[] = $vertex;
        }

        if (count($normalized) > 1 && $this->sameGridVertex($normalized[0], $normalized[count($normalized) - 1])) {
            array_pop($normalized);
        }

        if (count($normalized) < 3) {
            return $normalized;
        }

        $simplified = [];
        $count = count($normalized);
        for ($index = 0; $index < $count; $index++) {
            $previous = $normalized[($index - 1 + $count) % $count];
            $current = $normalized[$index];
            $next = $normalized[($index + 1) % $count];

            $isCollinear = ($previous['x'] === $current['x'] && $current['x'] === $next['x'])
                || ($previous['y'] === $current['y'] && $current['y'] === $next['y']);

            if ($isCollinear) {
                continue;
            }

            $simplified[] = $current;
        }

        return count($simplified) >= 3 ? $simplified : $normalized;
    }

    /**
     * @param  array{x: int, y: int}  $vertex
     */
    private function gridVertexKey(array $vertex): string
    {
        return $vertex['x'].':'.$vertex['y'];
    }

    /**
     * @param  array{x: int, y: int}  $start
     * @param  array{x: int, y: int}  $end
     */
    private function gridEdgeKey(array $start, array $end): string
    {
        return $this->gridVertexKey($start).'->'.$this->gridVertexKey($end);
    }

    /**
     * @param  array{x: int, y: int}  $left
     * @param  array{x: int, y: int}  $right
     */
    private function sameGridVertex(array $left, array $right): bool
    {
        return $left['x'] === $right['x'] && $left['y'] === $right['y'];
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
     * @param  array<int, array{x: float, y: float}>  $roomPolygon
     * @return array<int, array{polygon: array<int, array{x: float, y: float}>, connector: array{points: array<int, array{x: float, y: float}>, width_m: float}|null}>
     */
    private function normalizeFeatureBlockers(array $shapes, array $roomPolygon): array
    {
        $normalized = [];

        foreach ($shapes as $shape) {
            if (!$this->featureBlocksPanels($shape)) {
                continue;
            }

            $shapePoints = $this->featureShapePoints(is_array($shape) ? $shape : [], $roomPolygon);
            if (count($shapePoints) < 3) {
                continue;
            }

            $normalized[] = [
                'polygon' => $shapePoints,
                'connector' => $this->featureCutConnector(is_array($shape) ? $shape : [], $roomPolygon, $shapePoints),
            ];
        }

        return $normalized;
    }

    private function featureBlocksPanels(mixed $shape): bool
    {
        if (!is_array($shape)) {
            return false;
        }

        return !((bool) ($shape['separate_panel'] ?? false))
            && in_array((string) ($shape['kind'] ?? ''), [
                CeilingProjectRoom::FEATURE_CUTOUT,
                CeilingProjectRoom::FEATURE_SHIFT,
            ], true);
    }

    /**
     * @param  array<string, mixed>  $shape
     * @return array<int, array{x: float, y: float}>
     */
    private function featureShapePoints(array $shape, array $roomPolygon = []): array
    {
        return app(FeatureShapeGeometry::class)->points($shape, $roomPolygon);
    }

    /**
     * @param  array<string, mixed>  $shape
     * @param  array<int, array{x: float, y: float}>  $roomPolygon
     * @param  array<int, array{x: float, y: float}>  $shapePoints
     * @return array{points: array<int, array{x: float, y: float}>, width_m: float}|null
     */
    private function featureCutConnector(array $shape, array $roomPolygon, array $shapePoints): ?array
    {
        if (!($shape['cut_line'] ?? false) || count($roomPolygon) < 2 || count($shapePoints) < 2) {
            return null;
        }

        $segmentIndex = is_numeric($shape['cut_segment_index'] ?? null)
            ? (int) $shape['cut_segment_index']
            : (is_numeric($shape['source_segment_index'] ?? null) ? (int) $shape['source_segment_index'] : null);
        if ($segmentIndex === null || $segmentIndex < 0 || $segmentIndex >= count($roomPolygon)) {
            return null;
        }

        $segmentStart = $roomPolygon[$segmentIndex];
        $segmentEnd = $roomPolygon[($segmentIndex + 1) % count($roomPolygon)];
        $segmentLength = $this->distanceBetweenPoints($segmentStart, $segmentEnd);
        if ($segmentLength <= 0.0001) {
            return null;
        }

        $shapeSpan = is_numeric($shape['span_m'] ?? null)
            ? (float) $shape['span_m']
            : (float) ($shape['width_m'] ?? 0);
        $baseOffset = is_numeric($shape['cut_offset_m'] ?? null)
            ? (float) $shape['cut_offset_m']
            : (is_numeric($shape['offset_m'] ?? null)
                ? (float) $shape['offset_m'] + ($shapeSpan / 2)
                : ($segmentLength / 2));

        $basePoint = $this->pointAlongSegment($segmentStart, $segmentEnd, max(0.0, min($segmentLength, $baseOffset)));

        $bestPoint = null;
        $bestDistance = INF;
        $count = count($shapePoints);
        for ($index = 0; $index < $count; $index++) {
            $candidate = $this->closestPointOnSegment($basePoint, $shapePoints[$index], $shapePoints[($index + 1) % $count]);
            $distance = $this->distanceBetweenPoints($basePoint, $candidate);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestPoint = $candidate;
            }
        }

        if ($bestPoint === null) {
            return null;
        }

        return [
            'points' => [$basePoint, $bestPoint],
            'width_m' => 0.02,
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
     * @param  array<int, array{x: float, y: float}>  $points
     * @return array{min_x: float, min_y: float, max_x: float, max_y: float}|null
     */
    private function boundsFromPoints(array $points): ?array
    {
        if (count($points) < 3) {
            return null;
        }

        $xs = array_column($points, 'x');
        $ys = array_column($points, 'y');

        return [
            'min_x' => $this->round((float) min($xs)),
            'min_y' => $this->round((float) min($ys)),
            'max_x' => $this->round((float) max($xs)),
            'max_y' => $this->round((float) max($ys)),
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

    /**
     * @param  array{x: float, y: float}  $start
     * @param  array{x: float, y: float}  $end
     * @return array{x: float, y: float}
     */
    private function pointAlongSegment(array $start, array $end, float $distance): array
    {
        $segmentLength = $this->distanceBetweenPoints($start, $end);
        if ($segmentLength <= 0.0001) {
            return [
                'x' => $this->round($start['x']),
                'y' => $this->round($start['y']),
            ];
        }

        $ratio = max(0.0, min(1.0, $distance / $segmentLength));

        return [
            'x' => $this->round($start['x'] + (($end['x'] - $start['x']) * $ratio)),
            'y' => $this->round($start['y'] + (($end['y'] - $start['y']) * $ratio)),
        ];
    }

    /**
     * @param  array{x: float, y: float}  $point
     * @param  array{x: float, y: float}  $start
     * @param  array{x: float, y: float}  $end
     * @return array{x: float, y: float}
     */
    private function closestPointOnSegment(array $point, array $start, array $end): array
    {
        $dx = $end['x'] - $start['x'];
        $dy = $end['y'] - $start['y'];
        $denominator = ($dx ** 2) + ($dy ** 2);

        if ($denominator <= 0.000001) {
            return [
                'x' => $this->round($start['x']),
                'y' => $this->round($start['y']),
            ];
        }

        $t = max(0.0, min(1.0, ((($point['x'] - $start['x']) * $dx) + (($point['y'] - $start['y']) * $dy)) / $denominator));

        return [
            'x' => $this->round($start['x'] + ($dx * $t)),
            'y' => $this->round($start['y'] + ($dy * $t)),
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
