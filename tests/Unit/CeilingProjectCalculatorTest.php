<?php

namespace Tests\Unit;

use App\Models\CeilingProject;
use App\Models\CeilingProjectRoom;
use App\Models\CeilingProjectRoomElement;
use App\Services\Ceiling\CeilingProjectCalculator;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class CeilingProjectCalculatorTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();
    }

    public function test_it_calculates_rectangular_room_metrics(): void
    {
        $calculator = new CeilingProjectCalculator();

        $room = new CeilingProjectRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 3.2,
            'length_m' => 4.5,
            'height_m' => 2.7,
            'corners_count' => 4,
            'spotlights_count' => 6,
            'chandelier_points_count' => 1,
            'pipes_count' => 2,
            'curtain_niches_count' => 1,
            'ventilation_holes_count' => 1,
        ]);

        $metrics = $calculator->calculateRoom($room);

        $this->assertSame(14.4, $metrics['area_m2']);
        $this->assertSame(15.4, $metrics['perimeter_m']);
        $this->assertSame(41.58, $metrics['wall_area_m2']);
        $this->assertSame(7, $metrics['lighting_points_total']);
    }

    public function test_it_uses_manual_metrics_for_complex_room_shapes(): void
    {
        $calculator = new CeilingProjectCalculator();

        $metrics = $calculator->calculateRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_POLYGON,
            'manual_area_m2' => 18.75,
            'manual_perimeter_m' => 21.4,
            'corners_count' => 7,
        ]);

        $this->assertSame(18.75, $metrics['area_m2']);
        $this->assertSame(21.4, $metrics['perimeter_m']);
        $this->assertSame(7, $metrics['corners_count']);
    }

    public function test_it_aggregates_project_totals_and_reserve(): void
    {
        $calculator = new CeilingProjectCalculator();

        $project = new CeilingProject([
            'waste_percent' => 10,
            'extra_margin_m' => 0.4,
            'discount_percent' => 5,
            'canvas_price_per_m2' => 1000,
            'profile_price_per_m' => 100,
            'insert_price_per_m' => 10,
            'spotlight_price' => 200,
            'chandelier_price' => 500,
            'pipe_price' => 300,
            'curtain_niche_price' => 1000,
            'cornice_price_per_m' => 200,
            'ventilation_hole_price' => 150,
            'mounting_price_per_m2' => 400,
            'additional_cost' => 700,
        ]);

        $project->setRelation('rooms', new Collection([
            new CeilingProjectRoom([
                'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
                'width_m' => 4,
                'length_m' => 5,
                'height_m' => 2.8,
                'spotlights_count' => 4,
                'pipes_count' => 1,
            ]),
            new CeilingProjectRoom([
                'shape_type' => CeilingProjectRoom::SHAPE_POLYGON,
                'manual_area_m2' => 6.5,
                'manual_perimeter_m' => 11.2,
                'corners_count' => 6,
                'chandelier_points_count' => 1,
                'curtain_niches_count' => 1,
            ]),
        ]));

        $summary = $calculator->calculateProject($project);

        $this->assertSame(26.5, $summary['totals']['area_m2']);
        $this->assertSame(29.2, $summary['totals']['perimeter_m']);
        $this->assertSame(2.65, $summary['totals']['canvas_reserve_m2']);
        $this->assertSame(29.15, $summary['totals']['recommended_canvas_area_m2']);
        $this->assertSame(30.0, $summary['totals']['recommended_profile_m']);
        $this->assertSame(5, $summary['totals']['lighting_points_total']);
        $this->assertSame(4, $summary['totals']['spotlights_count']);
        $this->assertSame(1, $summary['totals']['chandelier_points_count']);
        $this->assertSame(1, $summary['totals']['pipes_count']);
        $this->assertSame(1, $summary['totals']['curtain_niches_count']);
        $this->assertSame(29150.0, $summary['estimate']['canvas_total']);
        $this->assertSame(3000.0, $summary['estimate']['profile_total']);
        $this->assertSame(292.0, $summary['estimate']['insert_total']);
        $this->assertSame(800.0, $summary['estimate']['spotlights_total']);
        $this->assertSame(500.0, $summary['estimate']['chandeliers_total']);
        $this->assertSame(300.0, $summary['estimate']['pipes_total']);
        $this->assertSame(1000.0, $summary['estimate']['curtain_niches_total']);
        $this->assertSame(10600.0, $summary['estimate']['mounting_total']);
        $this->assertSame(46342.0, $summary['estimate']['subtotal']);
        $this->assertSame(2317.1, $summary['estimate']['discount_amount']);
        $this->assertSame(44024.9, $summary['estimate']['grand_total']);
    }

    public function test_it_uses_room_elements_for_counts_and_cornice_length(): void
    {
        $calculator = new CeilingProjectCalculator();

        $project = new CeilingProject([
            'waste_percent' => 0,
            'extra_margin_m' => 0,
            'discount_percent' => 0,
            'canvas_price_per_m2' => 0,
            'profile_price_per_m' => 0,
            'insert_price_per_m' => 0,
            'spotlight_price' => 300,
            'chandelier_price' => 500,
            'pipe_price' => 200,
            'curtain_niche_price' => 1200,
            'cornice_price_per_m' => 150,
            'ventilation_hole_price' => 100,
            'mounting_price_per_m2' => 0,
            'additional_cost' => 0,
        ]);

        $room = new CeilingProjectRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 3,
            'length_m' => 4,
            'spotlights_count' => 99,
            'pipes_count' => 99,
        ]);

        $room->setRelation('elements', new Collection([
            new CeilingProjectRoomElement(['type' => CeilingProjectRoomElement::TYPE_SPOTLIGHT, 'quantity' => 4, 'x_m' => 1, 'y_m' => 1]),
            new CeilingProjectRoomElement(['type' => CeilingProjectRoomElement::TYPE_CHANDELIER, 'quantity' => 1, 'x_m' => 1.5, 'y_m' => 2]),
            new CeilingProjectRoomElement(['type' => CeilingProjectRoomElement::TYPE_PIPE, 'quantity' => 2]),
            new CeilingProjectRoomElement(['type' => CeilingProjectRoomElement::TYPE_CURTAIN_NICHE, 'quantity' => 1, 'length_m' => 2.8]),
            new CeilingProjectRoomElement(['type' => CeilingProjectRoomElement::TYPE_CORNICE, 'quantity' => 1, 'length_m' => 3.6]),
            new CeilingProjectRoomElement(['type' => CeilingProjectRoomElement::TYPE_VENTILATION, 'quantity' => 1]),
        ]));

        $project->setRelation('rooms', new Collection([$room]));

        $summary = $calculator->calculateProject($project);

        $this->assertSame(5, $summary['totals']['lighting_points_total']);
        $this->assertSame(4, $summary['totals']['spotlights_count']);
        $this->assertSame(1, $summary['totals']['chandelier_points_count']);
        $this->assertSame(2, $summary['totals']['pipes_count']);
        $this->assertSame(1, $summary['totals']['curtain_niches_count']);
        $this->assertSame(1, $summary['totals']['cornices_count']);
        $this->assertSame(3.6, $summary['totals']['cornice_length_m']);
        $this->assertSame(540.0, $summary['estimate']['cornices_total']);
        $this->assertSame(3940.0, $summary['estimate']['grand_total']);
    }

    public function test_it_applies_feature_shapes_to_room_area_and_perimeter(): void
    {
        $calculator = new CeilingProjectCalculator();

        $metrics = $calculator->calculateRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 4,
            'length_m' => 5,
            'feature_shapes' => [
                [
                    'kind' => CeilingProjectRoom::FEATURE_CUTOUT,
                    'figure' => CeilingProjectRoom::FEATURE_RECTANGLE,
                    'x_m' => 1,
                    'y_m' => 1,
                    'width_m' => 1,
                    'height_m' => 0.5,
                ],
                [
                    'kind' => CeilingProjectRoom::FEATURE_LEVEL,
                    'figure' => CeilingProjectRoom::FEATURE_CIRCLE,
                    'x_m' => 2,
                    'y_m' => 2,
                    'width_m' => 1,
                    'height_m' => 1,
                ],
            ],
        ]);

        $this->assertSame(20.29, $metrics['area_m2']);
        $this->assertSame(24.14, $metrics['perimeter_m']);
        $this->assertSame(2, $metrics['feature_shapes_count']);
        $this->assertSame(1, $metrics['feature_cutouts_count']);
        $this->assertSame(1, $metrics['feature_levels_count']);
    }

    public function test_it_uses_polygon_feature_shape_metrics_when_shape_points_are_present(): void
    {
        $calculator = new CeilingProjectCalculator();

        $metrics = $calculator->calculateRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 4,
            'length_m' => 4,
            'feature_shapes' => [
                [
                    'kind' => CeilingProjectRoom::FEATURE_CUTOUT,
                    'figure' => CeilingProjectRoom::FEATURE_RECTANGLE,
                    'shape_points' => [
                        ['x' => 1.0, 'y' => 1.0],
                        ['x' => 2.0, 'y' => 1.0],
                        ['x' => 2.0, 'y' => 2.0],
                        ['x' => 1.0, 'y' => 2.0],
                    ],
                ],
            ],
        ]);

        $this->assertSame(15.0, $metrics['area_m2']);
        $this->assertSame(20.0, $metrics['perimeter_m']);
        $this->assertSame(1, $metrics['feature_shapes_count']);
    }

    public function test_it_applies_feature_shape_override_deltas_for_curved_forms(): void
    {
        $calculator = new CeilingProjectCalculator();

        $metrics = $calculator->calculateRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 4,
            'length_m' => 4,
            'feature_shapes' => [
                [
                    'kind' => CeilingProjectRoom::FEATURE_LEVEL,
                    'figure' => CeilingProjectRoom::FEATURE_ARC,
                    'x_m' => 1,
                    'y_m' => 0,
                    'width_m' => 2,
                    'height_m' => 0.5,
                    'area_delta_m2' => 0.42,
                    'perimeter_delta_m' => 0.36,
                ],
                [
                    'kind' => CeilingProjectRoom::FEATURE_CUTOUT,
                    'figure' => CeilingProjectRoom::FEATURE_ROUNDED_CORNER,
                    'x_m' => 0,
                    'y_m' => 0,
                    'width_m' => 0.4,
                    'height_m' => 0.4,
                    'area_delta_m2' => -0.08,
                    'perimeter_delta_m' => -0.11,
                ],
            ],
        ]);

        $this->assertSame(16.34, $metrics['area_m2']);
        $this->assertSame(16.25, $metrics['perimeter_m']);
        $this->assertSame(2, $metrics['feature_shapes_count']);
        $this->assertSame(1, $metrics['feature_cutouts_count']);
        $this->assertSame(1, $metrics['feature_levels_count']);
    }

    public function test_it_counts_light_line_groups_segments_and_length(): void
    {
        $calculator = new CeilingProjectCalculator();

        $metrics = $calculator->calculateRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 4,
            'length_m' => 5,
            'light_line_shapes' => [
                [
                    'width_m' => 0.05,
                    'closed' => false,
                    'points' => [
                        ['x' => 1.0, 'y' => 1.0],
                        ['x' => 3.0, 'y' => 1.0],
                    ],
                ],
                [
                    'width_m' => 0.05,
                    'closed' => true,
                    'points' => [
                        ['x' => 1.0, 'y' => 2.0],
                        ['x' => 2.0, 'y' => 2.0],
                        ['x' => 2.0, 'y' => 3.0],
                        ['x' => 1.0, 'y' => 3.0],
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, $metrics['light_line_groups_count']);
        $this->assertSame(5, $metrics['light_line_segments_count']);
        $this->assertSame(6.0, $metrics['light_line_length_m']);
    }

    public function test_it_estimates_light_line_split_into_separate_panels(): void
    {
        $calculator = new CeilingProjectCalculator();

        $metrics = $calculator->calculateRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_RECTANGLE,
            'width_m' => 4,
            'length_m' => 4,
            'light_line_shapes' => [
                [
                    'width_m' => 0.08,
                    'closed' => false,
                    'points' => [
                        ['x' => 2.0, 'y' => 0.0],
                        ['x' => 2.0, 'y' => 4.0],
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, $metrics['light_line_panels_count']);
    }
}
