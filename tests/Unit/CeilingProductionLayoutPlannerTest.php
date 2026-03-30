<?php

namespace Tests\Unit;

use App\Models\CeilingProjectRoom;
use App\Services\Ceiling\CeilingProductionLayoutPlanner;
use PHPUnit\Framework\TestCase;

class CeilingProductionLayoutPlannerTest extends TestCase
{
    public function test_it_plans_single_panel_with_roll_dimensions(): void
    {
        $planner = new CeilingProductionLayoutPlanner();

        $plan = $planner->plan(new CeilingProjectRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 4.0,
            'length_m' => 3.0,
            'production_settings' => [
                'roll_width_cm' => 360,
                'shrink_x_percent' => 7,
                'shrink_y_percent' => 7,
                'orientation_mode' => 'parallel_segment',
                'orientation_segment_index' => 0,
            ],
        ]), [[
            'id' => 'panel_1',
            'label' => 'Полотно 1',
            'area_m2' => 12.0,
            'bounds' => [
                'min_x' => 0.0,
                'min_y' => 0.0,
                'max_x' => 4.0,
                'max_y' => 3.0,
            ],
        ]]);

        $this->assertSame(1, $plan['summary']['panels_count']);
        $this->assertSame(1, $plan['summary']['strips_count']);
        $this->assertSame(0, $plan['summary']['seamed_panels_count']);
        $this->assertEqualsWithDelta(4.3, $plan['panels'][0]['cut_span_m']['length'], 0.02);
        $this->assertEqualsWithDelta(3.23, $plan['panels'][0]['cut_span_m']['width'], 0.02);
        $this->assertSame(1, $plan['panels'][0]['strips_count']);
        $this->assertSame('AB', $plan['panels'][0]['orientation']['segment_label']);
    }

    public function test_it_splits_panel_into_two_real_parts_when_seam_is_enabled(): void
    {
        $planner = new CeilingProductionLayoutPlanner();

        $plan = $planner->plan([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 3.5,
            'length_m' => 2.5,
            'production_settings' => [
                'roll_width_cm' => 500,
                'shrink_x_percent' => 7,
                'shrink_y_percent' => 7,
                'orientation_mode' => 'center_room',
                'seam_enabled' => true,
                'seam_offset_m' => 0.2,
            ],
        ], [[
            'id' => 'panel_1',
            'label' => 'Полотно 1',
            'area_m2' => 8.75,
            'shape_points' => [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 3.5, 'y' => 0.0],
                ['x' => 3.5, 'y' => 2.5],
                ['x' => 0.0, 'y' => 2.5],
            ],
            'bounds' => [
                'min_x' => 0.0,
                'min_y' => 0.0,
                'max_x' => 3.5,
                'max_y' => 2.5,
            ],
        ]]);

        $this->assertSame(2, $plan['summary']['panels_count']);
        $this->assertSame(2, $plan['summary']['strips_count']);
        $this->assertSame(1, $plan['summary']['seamed_panels_count']);
        $this->assertSame('Центр помещения', $plan['orientation']['segment_label']);
        $this->assertSame('panel_1', $plan['panels'][0]['seam_parent_id']);
        $this->assertSame('panel_1', $plan['panels'][1]['seam_parent_id']);
        $this->assertSame(1, $plan['panels'][0]['seam_part_index']);
        $this->assertSame(2, $plan['panels'][1]['seam_part_index']);
        $this->assertSame(1, $plan['panels'][0]['strips_count']);
        $this->assertSame(1, $plan['panels'][1]['strips_count']);
        $this->assertSame('single', $plan['panels'][0]['layout_type']);
        $this->assertSame('single', $plan['panels'][1]['layout_type']);
        $this->assertEqualsWithDelta(8.75, $plan['panels'][0]['finished_area_m2'] + $plan['panels'][1]['finished_area_m2'], 0.1);
    }
}
