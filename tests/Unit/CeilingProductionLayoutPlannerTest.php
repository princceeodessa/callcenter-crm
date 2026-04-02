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
        $this->assertSame(1, $plan['summary']['roll_sequences_count']);
        $this->assertSame('Рулон 1', $plan['summary']['roll_sequences'][0]['label']);
        $this->assertEqualsWithDelta(4.3, $plan['panels'][0]['cut_span_m']['length'], 0.02);
        $this->assertEqualsWithDelta(3.23, $plan['panels'][0]['cut_span_m']['width'], 0.02);
        $this->assertSame(1, $plan['panels'][0]['strips_count']);
        $this->assertSame('AB', $plan['panels'][0]['orientation']['segment_label']);
        $this->assertSame(1, $plan['panels'][0]['roll_sequence']['index']);
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
        $this->assertSame(1, $plan['summary']['roll_sequences_count']);
        $this->assertSame('Центр помещения', $plan['orientation']['segment_label']);
        $this->assertSame('panel_1', $plan['panels'][0]['seam_parent_id']);
        $this->assertSame('panel_1', $plan['panels'][1]['seam_parent_id']);
        $this->assertSame(1, $plan['panels'][0]['seam_part_index']);
        $this->assertSame(2, $plan['panels'][1]['seam_part_index']);
        $this->assertSame(1, $plan['panels'][0]['strips_count']);
        $this->assertSame(1, $plan['panels'][1]['strips_count']);
        $this->assertSame('single', $plan['panels'][0]['layout_type']);
        $this->assertSame('single', $plan['panels'][1]['layout_type']);
        $this->assertSame('Рулон 1', $plan['panels'][0]['roll_sequence']['label']);
        $this->assertSame('Рулон 1', $plan['panels'][1]['roll_sequence']['label']);
        $this->assertEqualsWithDelta(8.75, $plan['panels'][0]['finished_area_m2'] + $plan['panels'][1]['finished_area_m2'], 0.1);
    }

    public function test_it_builds_roll_warnings_for_same_roll_and_multi_strip(): void
    {
        $planner = new CeilingProductionLayoutPlanner();

        $plan = $planner->plan([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 5.2,
            'length_m' => 3.4,
            'production_settings' => [
                'roll_width_cm' => 180,
                'shrink_x_percent' => 7,
                'shrink_y_percent' => 7,
                'orientation_mode' => 'parallel_segment',
                'orientation_segment_index' => 0,
                'same_roll_required' => true,
                'special_cutting' => true,
            ],
        ], [
            [
                'id' => 'panel_1',
                'label' => 'Полотно 1',
                'area_m2' => 8.84,
                'bounds' => [
                    'min_x' => 0.0,
                    'min_y' => 0.0,
                    'max_x' => 2.6,
                    'max_y' => 3.4,
                ],
            ],
            [
                'id' => 'panel_2',
                'label' => 'Полотно 2',
                'area_m2' => 8.84,
                'bounds' => [
                    'min_x' => 2.6,
                    'min_y' => 0.0,
                    'max_x' => 5.2,
                    'max_y' => 3.4,
                ],
            ],
        ]);

        $this->assertSame(1, $plan['summary']['roll_sequences_count']);
        $this->assertSame('Общий рулон', $plan['summary']['roll_sequences'][0]['label']);
        $this->assertNotEmpty($plan['summary']['warnings']);
        $warnings = implode(' ', $plan['summary']['warnings']);
        $this->assertStringContainsString('несколько полос', $warnings);
        $this->assertStringContainsString('одного рулона', $warnings);
        $this->assertStringContainsString('спецраскрой', $warnings);
        $this->assertSame('Общий рулон', $plan['panels'][0]['roll_sequence']['label']);
        $this->assertSame('Общий рулон', $plan['panels'][1]['roll_sequence']['label']);
    }

    public function test_it_blocks_multi_strip_layout_without_seam_or_special_cutting(): void
    {
        $planner = new CeilingProductionLayoutPlanner();

        $plan = $planner->plan([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 4.0,
            'length_m' => 3.0,
            'production_settings' => [
                'roll_width_cm' => 120,
                'shrink_x_percent' => 0,
                'shrink_y_percent' => 0,
                'orientation_mode' => 'parallel_segment',
                'orientation_segment_index' => 0,
                'same_roll_required' => false,
                'special_cutting' => false,
                'seam_enabled' => false,
            ],
        ], [[
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

        $this->assertSame('blocked', $plan['summary']['status']);
        $this->assertSame(1, $plan['summary']['errors_count']);
        $this->assertSame('blocked', $plan['panels'][0]['status']);
        $this->assertStringContainsString('невыполнима', implode(' ', array_map(
            static fn (array $issue) => (string) ($issue['message'] ?? ''),
            $plan['summary']['issues']
        )));
    }

    public function test_it_blocks_same_roll_sequence_when_material_settings_differ(): void
    {
        $planner = new CeilingProductionLayoutPlanner();

        $plan = $planner->plan([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 5.2,
            'length_m' => 3.4,
            'production_settings' => [
                'roll_width_cm' => 400,
                'texture' => 'matte',
                'shrink_x_percent' => 7,
                'shrink_y_percent' => 7,
                'orientation_mode' => 'parallel_segment',
                'orientation_segment_index' => 0,
                'same_roll_required' => true,
            ],
        ], [
            [
                'id' => 'panel_1',
                'label' => 'Полотно 1',
                'area_m2' => 8.84,
                'bounds' => [
                    'min_x' => 0.0,
                    'min_y' => 0.0,
                    'max_x' => 2.6,
                    'max_y' => 3.4,
                ],
            ],
            [
                'id' => 'panel_2',
                'label' => 'Полотно 2',
                'area_m2' => 8.84,
                'bounds' => [
                    'min_x' => 2.6,
                    'min_y' => 0.0,
                    'max_x' => 5.2,
                    'max_y' => 3.4,
                ],
                'production' => [
                    'texture' => 'glossy',
                ],
            ],
        ]);

        $this->assertSame('blocked', $plan['summary']['status']);
        $this->assertSame(1, $plan['summary']['errors_count']);
        $this->assertSame('blocked', $plan['summary']['roll_sequences'][0]['status']);
        $this->assertStringContainsString('один рулон', implode(' ', array_map(
            static fn (array $issue) => (string) ($issue['message'] ?? ''),
            $plan['summary']['issues']
        )));
    }

    public function test_it_blocks_sequence_when_roll_length_limit_is_exceeded_with_reserve(): void
    {
        $planner = new CeilingProductionLayoutPlanner();

        $plan = $planner->plan([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 5.0,
            'length_m' => 3.0,
            'production_settings' => [
                'roll_width_cm' => 320,
                'shrink_x_percent' => 0,
                'shrink_y_percent' => 0,
                'orientation_mode' => 'parallel_segment',
                'orientation_segment_index' => 0,
                'same_roll_required' => true,
                'max_roll_length_m' => 3.1,
                'roll_reserve_percent' => 10,
            ],
        ], [[
            'id' => 'panel_1',
            'label' => 'Полотно 1',
            'area_m2' => 15.0,
            'bounds' => [
                'min_x' => 0.0,
                'min_y' => 0.0,
                'max_x' => 5.0,
                'max_y' => 3.0,
            ],
        ]]);

        $this->assertSame('blocked', $plan['summary']['status']);
        $this->assertSame(1, $plan['summary']['errors_count']);
        $this->assertEqualsWithDelta(5.5, $plan['summary']['required_roll_length_total_m'], 0.02);
        $this->assertSame('blocked', $plan['summary']['roll_sequences'][0]['status']);
        $this->assertStringContainsString('длина рулона', implode(' ', array_map(
            static fn (array $issue) => (string) ($issue['message'] ?? ''),
            $plan['summary']['issues']
        )));
    }
}
