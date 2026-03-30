<?php


namespace App\Services\Ceiling;


use App\Models\CeilingProjectRoom;


class CeilingProductionLayoutPlanner
{
/**
* @param  array<int, array<string, mixed>>  $panels
* @return array{
*   settings: array<string, mixed>,
*   orientation: array<string, mixed>,
*   panels: array<int, array<string, mixed>>,
*   summary: array<string, mixed>
* }
*/
public function plan(CeilingProjectRoom|array $room, array $panels): array
{
$payload = $room instanceof CeilingProjectRoom ? $room->toArray() : $room;
$polygon = $this->roomPolygon($payload);
$settings = $this->normalizeSettings($payload['production_settings'] ?? []);
$orientation = $this->resolveOrientationContext($polygon, $settings);


$plannedPanels = [];
foreach ($this->normalizePanels($panels) as $panel) {
$panelSettings = $this->normalizeSettings(array_merge(
$settings,
is_array($panel['production'] ?? null) ? $panel['production'] : []
));
$splitPanels = $this->splitPanelBySeam($panel, $panelSettings, $orientation);


if ($splitPanels !== []) {
foreach ($splitPanels as $splitPanel) {
$plannedPanels[] = $this->planPanel($splitPanel, array_merge($panelSettings, [
'seam_enabled' => false,
'seam_offset_m' => 0.0,
]), $orientation, count($plannedPanels));
}


continue;
}


$plannedPanels[] = $this->planPanel($panel, $panelSettings, $orientation, count($plannedPanels));
}


$seamedParents = [];
foreach ($plannedPanels as $panel) {
$seamParentId = $panel['seam_parent_id'] ?? null;
if (is_string($seamParentId) && $seamParentId !== '') {
$seamedParents[$seamParentId] = true;
continue;
}


if (($panel['layout_type'] ?? 'single') === 'seamed') {
$seamedParents[(string) ($panel['id'] ?? uniqid('panel_', true))] = true;
}
}


return [
'settings' => $settings,
'orientation' => $orientation,
'panels' => $plannedPanels,
'summary' => [
'panels_count' => count($plannedPanels),
'seamed_panels_count' => count($seamedParents),
'strips_count' => array_sum(array_map(fn (array $panel) => count($panel['strips'] ?? []), $plannedPanels)),
'finished_area_m2' => $this->round(array_sum(array_map(fn (array $panel) => (float) ($panel['finished_area_m2'] ?? 0), $plannedPanels))),
'consumed_area_m2' => $this->round(array_sum(array_map(fn (array $panel) => (float) ($panel['consumed_area_m2'] ?? 0), $plannedPanels))),
'stretch_reserve_m2' => $this->round(array_sum(array_map(fn (array $panel) => (float) ($panel['stretch_reserve_m2'] ?? 0), $plannedPanels))),
'roll_length_total_m' => $this->round(array_sum(array_map(fn (array $panel) => (float) ($panel['roll_length_total_m'] ?? 0), $plannedPanels))),
],
];
}


/**
* @param  array<string, mixed>  $panel
* @param  array<string, mixed>  $settings
* @param  array<string, mixed>  $orientation
* @return array<string, mixed>
*/
private function planPanel(array $panel, array $settings, array $orientation, int $index): array
{
$bounds = $panel['bounds'];
$shapePoints = is_array($panel['shape_points'] ?? null) && count($panel['shape_points']) >= 3
? $panel['shape_points']
: [
['x' => $bounds['min_x'], 'y' => $bounds['min_y']],
['x' => $bounds['max_x'], 'y' => $bounds['min_y']],
['x' => $bounds['max_x'], 'y' => $bounds['max_y']],
['x' => $bounds['min_x'], 'y' => $bounds['max_y']],
];


$uValues = [];
$vValues = [];
foreach ($shapePoints as $point) {
$uValues[] = $this->dot($point, $orientation['axis']);
$vValues[] = $this->dot($point, $orientation['normal']);
}


$finishedLength = max(0.01, max($uValues) - min($uValues));
$finishedWidth = max(0.01, max($vValues) - min($vValues));
$cutLength = $this->inflateByShrink($finishedLength, (float) $settings['shrink_x_percent']);
$cutWidth = $this->inflateByShrink($finishedWidth, (float) $settings['shrink_y_percent']);
$strips = $this->buildStrips($cutWidth, $cutLength, $settings);
$consumedArea = array_sum(array_map(fn (array $strip) => (float) $strip['area_m2'], $strips));
$finishedArea = (float) ($panel['area_m2'] ?? 0.0);
$orientationOffset = (float) ($settings['orientation_offset_m'] ?? 0.0);
$panelLabel = is_string($panel['label'] ?? null) && trim((string) $panel['label']) !== ''
? (string) $panel['label']
: 'Полотно '.($index + 1);


return [
'id' => (string) ($panel['id'] ?? 'panel_'.($index + 1)),
'label' => (string) ($panel['label'] ?? ('Полотно '.($index + 1))),
'finished_area_m2' => $this->round($finishedArea),
'finished_span_m' => [
'length' => $this->round($finishedLength),
'width' => $this->round($finishedWidth),
],
'cut_span_m' => [
'length' => $this->round($cutLength),
'width' => $this->round($cutWidth),
],
'roll_width_m' => $this->round((float) $settings['roll_width_cm'] / 100),
'consumed_area_m2' => $this->round($consumedArea),
'stretch_reserve_m2' => $this->round(max(0.0, $consumedArea - $finishedArea)),
'roll_length_total_m' => $this->round(array_sum(array_map(fn (array $strip) => (float) $strip['length_m'], $strips))),
'strips' => $strips,
'strips_count' => count($strips),
'seams_count' => max(0, count($strips) - 1),
'layout_type' => count($strips) > 1
? ((bool) ($settings['seam_enabled'] ?? false) ? 'seamed' : 'multi_strip')
: 'single',
'orientation' => [
'mode' => $settings['orientation_mode'],
'angle_deg' => $orientation['angle_deg'],
'segment_label' => $orientation['segment_label'],
'offset_m' => $this->round($orientationOffset),
],
'bounds' => $bounds,
'centroid' => $panel['centroid'] ?? null,
'shape_points' => $shapePoints,
'source' => $panel['source'] ?? null,
'source_shape_id' => $panel['source_shape_id'] ?? null,
'feature_kind' => $panel['feature_kind'] ?? null,
'label' => \App\Support\TextNormalizer::normalizeMojibake($panelLabel),
'seam_parent_id' => $panel['seam_parent_id'] ?? null,
'seam_part_index' => $panel['seam_part_index'] ?? null,
'production' => $panel['production'] ?? [],
];
}


/**
* @return array<int, array<string, mixed>>
*/
private function normalizePanels(array $panels): array
{
$normalized = [];


foreach ($panels as $index => $panel) {
if (!is_array($panel) || !is_array($panel['bounds'] ?? null)) {
continue;
}


$bounds = $panel['bounds'];
$minX = $this->numeric($bounds['min_x'] ?? null);
$minY = $this->numeric($bounds['min_y'] ?? null);
$maxX = $this->numeric($bounds['max_x'] ?? null);
$maxY = $this->numeric($bounds['max_y'] ?? null);


if ($minX === null || $minY === null || $maxX === null || $maxY === null || $maxX <= $minX || $maxY <= $minY) {
continue;
}


$panelLabel = is_string($panel['label'] ?? null) && trim((string) $panel['label']) !== ''
? (string) $panel['label']
: 'Полотно '.($index + 1);


$normalized[] = [
'id' => (string) ($panel['id'] ?? 'panel_'.($index + 1)),
'label' => (string) ($panel['label'] ?? ('Полотно '.($index + 1))),
'area_m2' => $this->round((float) ($panel['area_m2'] ?? 0.0)),
'bounds' => [
'min_x' => $this->round($minX),
'min_y' => $this->round($minY),
'max_x' => $this->round($maxX),
'max_y' => $this->round($maxY),
],
'centroid' => is_array($panel['centroid'] ?? null) ? $panel['centroid'] : null,
'shape_points' => $this->normalizePanelShapePoints($panel['shape_points'] ?? null),
'source' => isset($panel['source']) ? (string) $panel['source'] : null,
'source_shape_id' => isset($panel['source_shape_id']) ? (string) $panel['source_shape_id'] : null,
'feature_kind' => isset($panel['feature_kind']) ? (string) $panel['feature_kind'] : null,
'label' => \App\Support\TextNormalizer::normalizeMojibake($panelLabel),
'seam_parent_id' => isset($panel['seam_parent_id']) ? (string) $panel['seam_parent_id'] : null,
'seam_part_index' => isset($panel['seam_part_index']) ? (int) $panel['seam_part_index'] : null,
'production' => is_array($panel['production'] ?? null) ? $panel['production'] : [],
];
}


return $normalized;
}


/**
* @param  mixed  $points
* @return array<int, array{x: float, y: float}>|null
*/
private function normalizePanelShapePoints(mixed $points): ?array
{
if (!is_array($points)) {
return null;
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


return count($normalized) >= 3 ? $normalized : null;
}


/**
* @return array<string, mixed>
*/
private function normalizeSettings(mixed $settings): array
{
if (!is_array($settings)) {
$settings = [];
}


$orientationMode = (string) ($settings['orientation_mode'] ?? 'parallel_segment');
if (!in_array($orientationMode, ['parallel_segment', 'perpendicular_segment', 'center_segment', 'center_room'], true)) {
$orientationMode = 'parallel_segment';
}


return [
'texture' => (string) ($settings['texture'] ?? 'matte'),
'roll_width_cm' => max(50, (int) ($settings['roll_width_cm'] ?? 320)),
'harpoon_type' => (string) ($settings['harpoon_type'] ?? 'standard'),
'same_roll_required' => (bool) ($settings['same_roll_required'] ?? false),
'special_cutting' => (bool) ($settings['special_cutting'] ?? false),
'seam_enabled' => (bool) ($settings['seam_enabled'] ?? false),
'shrink_x_percent' => $this->round((float) ($settings['shrink_x_percent'] ?? 7.0)),
'shrink_y_percent' => $this->round((float) ($settings['shrink_y_percent'] ?? 7.0)),
'orientation_mode' => $orientationMode,
'orientation_segment_index' => max(0, (int) ($settings['orientation_segment_index'] ?? 0)),
'orientation_offset_m' => $this->round((float) ($settings['orientation_offset_m'] ?? 0.0)),
'seam_offset_m' => $this->round((float) ($settings['seam_offset_m'] ?? 0.0)),
'comment' => $this->trimNullable($settings['comment'] ?? null),
];
}


/**
* @param  array<int, array{x: float, y: float}>  $polygon
* @return array<string, mixed>
*/
private function resolveOrientation(array $polygon, array $settings): array
{
$count = count($polygon);
$mode = (string) $settings['orientation_mode'];


if ($count < 2 || $mode === 'center_room') {
return [
'axis' => ['x' => 1.0, 'y' => 0.0],
'normal' => ['x' => 0.0, 'y' => 1.0],
'angle_deg' => 0.0,
'segment_index' => null,
'segment_label' => 'Центр помещения',
];
}


$segmentIndex = min($count - 1, max(0, (int) $settings['orientation_segment_index']));
$start = $polygon[$segmentIndex];
$end = $polygon[($segmentIndex + 1) % $count];
$dx = $end['x'] - $start['x'];
$dy = $end['y'] - $start['y'];
$length = hypot($dx, $dy);


if ($length < 0.0001) {
return [
'axis' => ['x' => 1.0, 'y' => 0.0],
'normal' => ['x' => 0.0, 'y' => 1.0],
'angle_deg' => 0.0,
'segment_index' => $segmentIndex,
'segment_label' => $this->segmentLabel($segmentIndex, $count),
];
}


$axis = ['x' => $dx / $length, 'y' => $dy / $length];
if ($mode === 'perpendicular_segment') {
$axis = ['x' => -$axis['y'], 'y' => $axis['x']];
}


return [
'axis' => ['x' => $this->round($axis['x'], 6), 'y' => $this->round($axis['y'], 6)],
'normal' => ['x' => $this->round(-$axis['y'], 6), 'y' => $this->round($axis['x'], 6)],
'angle_deg' => $this->round(rad2deg(atan2($axis['y'], $axis['x']))),
'segment_index' => $segmentIndex,
'segment_label' => $mode === 'center_segment'
? 'Центр стороны '.$this->segmentLabel($segmentIndex, $count)
: $this->segmentLabel($segmentIndex, $count),
];
}


/**
* @param  array<int, array{x: float, y: float}>  $polygon
* @return array<string, mixed>
*/
private function resolveOrientationContext(array $polygon, array $settings): array
{
$context = $this->resolveOrientation($polygon, $settings);
$count = count($polygon);
$mode = (string) ($settings['orientation_mode'] ?? 'parallel_segment');
$segmentIndex = min(max((int) ($context['segment_index'] ?? 0), 0), max($count - 1, 0));


if ($count < 2 || $mode === 'center_room') {
$center = $this->polygonCentroid($polygon) ?? ['x' => 0.0, 'y' => 0.0];


return array_merge($context, [
'segment_label' => 'Центр помещения',
'anchor' => [
'x' => $this->round((float) ($center['x'] ?? 0.0)),
'y' => $this->round((float) ($center['y'] ?? 0.0)),
],
]);
}


$start = $polygon[$segmentIndex];
$end = $polygon[($segmentIndex + 1) % $count];


return array_merge($context, [
'segment_label' => $mode === 'center_segment'
? 'Центр стороны '.$this->segmentLabel($segmentIndex, $count)
: $this->segmentLabel($segmentIndex, $count),
'anchor' => [
'x' => $this->round(($start['x'] + $end['x']) / 2),
'y' => $this->round(($start['y'] + $end['y']) / 2),
],
]);
}


/**
* @param  array<string, mixed>  $panel
* @param  array<string, mixed>  $settings
* @param  array<string, mixed>  $orientation
* @return array<int, array<string, mixed>>
*/
private function splitPanelBySeam(array $panel, array $settings, array $orientation): array
{
if (!(bool) ($settings['seam_enabled'] ?? false)) {
return [];
}


$shapePoints = $this->panelShapePoints($panel);
if (count($shapePoints) < 3) {
return [];
}


$anchor = is_array($orientation['anchor'] ?? null) ? $orientation['anchor'] : null;
$normal = is_array($orientation['normal'] ?? null) ? $orientation['normal'] : null;
if ($anchor === null || $normal === null) {
return [];
}


$offset = (float) ($settings['orientation_offset_m'] ?? 0.0) + (float) ($settings['seam_offset_m'] ?? 0.0);
$linePoint = [
'x' => $this->round(((float) ($anchor['x'] ?? 0.0)) + (((float) ($normal['x'] ?? 0.0)) * $offset), 6),
'y' => $this->round(((float) ($anchor['y'] ?? 0.0)) + (((float) ($normal['y'] ?? 0.0)) * $offset), 6),
];


$parts = [
$this->clipPolygonByHalfPlane($shapePoints, $linePoint, $normal, true),
$this->clipPolygonByHalfPlane($shapePoints, $linePoint, $normal, false),
];


$baseLabel = is_string($panel['label'] ?? null) && trim((string) $panel['label']) !== ''
? (string) $panel['label']
: 'Полотно';
$splitPanels = [];


foreach ($parts as $partIndex => $partPoints) {
if (count($partPoints) < 3) {
continue;
}


$area = $this->polygonArea($partPoints);
$bounds = $this->boundsFromPoints($partPoints);
if ($area <= 0.01 || $bounds === null) {
continue;
}


$splitPanels[] = [
'id' => (string) ($panel['id'] ?? 'panel').'_part_'.($partIndex + 1),
'label' => $baseLabel.' '.($partIndex === 0 ? 'A' : 'B'),
'area_m2' => $this->round($area),
'bounds' => $bounds,
'centroid' => $this->polygonCentroid($partPoints),
'shape_points' => $partPoints,
'source' => 'seam_split',
'source_shape_id' => $panel['source_shape_id'] ?? null,
'feature_kind' => $panel['feature_kind'] ?? null,
'seam_parent_id' => (string) ($panel['id'] ?? 'panel'),
'seam_part_index' => $partIndex + 1,
'production' => $panel['production'] ?? [],
];
}


return count($splitPanels) === 2 ? $splitPanels : [];
}


/**
* @param  array<string, mixed>  $panel
* @return array<int, array{x: float, y: float}>
*/
private function panelShapePoints(array $panel): array
{
if (is_array($panel['shape_points'] ?? null) && count($panel['shape_points']) >= 3) {
return array_values($panel['shape_points']);
}


$bounds = $panel['bounds'] ?? null;
if (!is_array($bounds)) {
return [];
}


return [
['x' => (float) $bounds['min_x'], 'y' => (float) $bounds['min_y']],
['x' => (float) $bounds['max_x'], 'y' => (float) $bounds['min_y']],
['x' => (float) $bounds['max_x'], 'y' => (float) $bounds['max_y']],
['x' => (float) $bounds['min_x'], 'y' => (float) $bounds['max_y']],
];
}


/**
* @param  array<int, array{x: float, y: float}>  $polygon
* @param  array{x: float, y: float}  $linePoint
* @param  array{x: float, y: float}  $normal
* @return array<int, array{x: float, y: float}>
*/
private function clipPolygonByHalfPlane(array $polygon, array $linePoint, array $normal, bool $keepPositive): array
{
$result = [];
$count = count($polygon);
if ($count < 3) {
return [];
}


for ($index = 0; $index < $count; $index++) {
$current = $polygon[$index];
$next = $polygon[($index + 1) % $count];
$currentDistance = $this->signedDistanceToLine($current, $linePoint, $normal);
$nextDistance = $this->signedDistanceToLine($next, $linePoint, $normal);
$currentInside = $keepPositive ? $currentDistance >= -0.0001 : $currentDistance <= 0.0001;
$nextInside = $keepPositive ? $nextDistance >= -0.0001 : $nextDistance <= 0.0001;


if ($currentInside) {
$result[] = [
'x' => $this->round((float) $current['x']),
'y' => $this->round((float) $current['y']),
];
}


if ($currentInside xor $nextInside) {
$intersection = $this->lineIntersectionOnSegment($current, $next, $currentDistance, $nextDistance);
if ($intersection !== null) {
$result[] = $intersection;
}
}
}


return $this->uniquePolygonPoints($result);
}


/**
* @param  array{x: float, y: float}  $point
* @param  array{x: float, y: float}  $linePoint
* @param  array{x: float, y: float}  $normal
*/
private function signedDistanceToLine(array $point, array $linePoint, array $normal): float
{
return (($point['x'] - $linePoint['x']) * $normal['x']) + (($point['y'] - $linePoint['y']) * $normal['y']);
}


/**
* @param  array{x: float, y: float}  $start
* @param  array{x: float, y: float}  $end
* @return array{x: float, y: float}|null
*/
private function lineIntersectionOnSegment(array $start, array $end, float $startDistance, float $endDistance): ?array
{
$denominator = $startDistance - $endDistance;
if (abs($denominator) < 0.000001) {
return null;
}


$ratio = $startDistance / $denominator;


return [
'x' => $this->round($start['x'] + (($end['x'] - $start['x']) * $ratio)),
'y' => $this->round($start['y'] + (($end['y'] - $start['y']) * $ratio)),
];
}


/**
* @param  array<int, array{x: float, y: float}>  $points
* @return array<int, array{x: float, y: float}>
*/
private function uniquePolygonPoints(array $points): array
{
$unique = [];
foreach ($points as $point) {
$last = $unique[count($unique) - 1] ?? null;
if ($last && abs($last['x'] - $point['x']) < 0.0001 && abs($last['y'] - $point['y']) < 0.0001) {
continue;
}


$unique[] = [
'x' => $this->round((float) $point['x']),
'y' => $this->round((float) $point['y']),
];
}


if (count($unique) > 1) {
$first = $unique[0];
$last = $unique[count($unique) - 1];
if (abs($first['x'] - $last['x']) < 0.0001 && abs($first['y'] - $last['y']) < 0.0001) {
array_pop($unique);
}
}


return count($unique) >= 3 ? $unique : [];
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
* @param  array<int, array{x: float, y: float}>  $points
* @return array{x: float, y: float}|null
*/
private function polygonCentroid(array $points): ?array
{
if (count($points) < 3) {
return null;
}


$areaFactor = 0.0;
$centerX = 0.0;
$centerY = 0.0;
$count = count($points);


for ($index = 0; $index < $count; $index++) {
$next = ($index + 1) % $count;
$cross = ($points[$index]['x'] * $points[$next]['y']) - ($points[$next]['x'] * $points[$index]['y']);
$areaFactor += $cross;
$centerX += ($points[$index]['x'] + $points[$next]['x']) * $cross;
$centerY += ($points[$index]['y'] + $points[$next]['y']) * $cross;
}


if (abs($areaFactor) < 0.000001) {
return null;
}


return [
'x' => $this->round($centerX / (3 * $areaFactor)),
'y' => $this->round($centerY / (3 * $areaFactor)),
];
}


/**
* @param  array<string, mixed>  $settings
* @return array<int, array<string, mixed>>
*/
private function buildStrips(float $cutWidth, float $cutLength, array $settings): array
{
$rollWidth = max(0.5, (float) $settings['roll_width_cm'] / 100);
$stripCount = max(1, (int) ceil($cutWidth / $rollWidth));
if ((bool) $settings['seam_enabled'] && $stripCount < 2) {
$stripCount = 2;
}


$widths = [];
if ($stripCount === 1) {
$widths[] = $cutWidth;
} elseif ((bool) $settings['seam_enabled'] && $stripCount === 2) {
$offset = (float) ($settings['seam_offset_m'] ?? 0.0);
$minPart = min(0.15, $cutWidth / 3);
$split = max($minPart, min($cutWidth - $minPart, ($cutWidth / 2) + $offset));
$widths[] = $split;
$widths[] = $cutWidth - $split;
} else {
$remaining = $cutWidth;
while ($remaining > 0.0001) {
$part = min($rollWidth, $remaining);
$widths[] = $part;
$remaining -= $part;
}
}


$strips = [];
$cursor = 0.0;
foreach ($widths as $index => $width) {
$width = $this->round($width);
$strips[] = [
'index' => $index + 1,
'width_m' => $width,
'length_m' => $this->round($cutLength),
'area_m2' => $this->round($width * $cutLength),
'start_m' => $this->round($cursor),
'end_m' => $this->round($cursor + $width),
'start_percent' => $cutWidth > 0 ? $this->round(($cursor / $cutWidth) * 100) : 0.0,
'size_percent' => $cutWidth > 0 ? $this->round(($width / $cutWidth) * 100) : 100.0,
];
$cursor += $width;
}


return $strips;
}


/**
* @param  array<string, mixed>  $payload
* @return array<int, array{x: float, y: float}>
*/
private function roomPolygon(array $payload): array
{
$shapeType = (string) ($payload['shape_type'] ?? CeilingProjectRoom::SHAPE_RECTANGLE);
$points = [];


if ($shapeType !== CeilingProjectRoom::SHAPE_RECTANGLE && is_array($payload['shape_points'] ?? null)) {
foreach ($payload['shape_points'] as $point) {
if (!is_array($point) || !isset($point['x'], $point['y']) || !is_numeric($point['x']) || !is_numeric($point['y'])) {
continue;
}


$points[] = [
'x' => $this->round((float) $point['x']),
'y' => $this->round((float) $point['y']),
];
}
}


if (count($points) >= 3) {
return $points;
}


$width = $this->numeric($payload['width_m'] ?? null) ?? 0.0;
$length = $this->numeric($payload['length_m'] ?? null) ?? 0.0;


if ($width <= 0 || $length <= 0) {
return [];
}


return [
['x' => 0.0, 'y' => 0.0],
['x' => $this->round($width), 'y' => 0.0],
['x' => $this->round($width), 'y' => $this->round($length)],
['x' => 0.0, 'y' => $this->round($length)],
];
}


/**
* @param  array{x: float, y: float}  $point
* @param  array{x: float, y: float}  $vector
*/
private function dot(array $point, array $vector): float
{
return ($point['x'] * $vector['x']) + ($point['y'] * $vector['y']);
}

/**
* @param  array<int, array{x: float, y: float}>  $points
*/
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

private function inflateByShrink(float $finishedSize, float $shrinkPercent): float
{
$ratio = max(0.01, 1 - ($shrinkPercent / 100));


return $this->round($finishedSize / $ratio);
}


private function numeric(mixed $value): ?float
{
return is_numeric($value) ? (float) $value : null;
}


private function trimNullable(mixed $value): ?string
{
if ($value === null) {
return null;
}


$trimmed = trim((string) $value);


return $trimmed !== '' ? $trimmed : null;
}


private function round(float $value, int $precision = 2): float
{
return round($value, $precision);
}


private function segmentLabel(int $index, int $count): string
{
return $this->pointLetter($index).''.$this->pointLetter(($index + 1) % $count);
}


private function pointLetter(int $index): string
{
$index = max(0, $index);
$letter = '';
do {
$letter = chr(65 + ($index % 26)).$letter;
$index = intdiv($index, 26) - 1;
} while ($index >= 0);


return $letter;
}
}
