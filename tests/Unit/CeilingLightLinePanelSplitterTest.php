<?php

namespace Tests\Unit;

use App\Services\Ceiling\CeilingLightLinePanelSplitter;
use PHPUnit\Framework\TestCase;

class CeilingLightLinePanelSplitterTest extends TestCase
{
    public function test_it_returns_single_panel_with_production_payload_without_light_lines(): void
    {
        $splitter = new CeilingLightLinePanelSplitter();

        $panels = $splitter->split([
            ['x' => 0.0, 'y' => 0.0],
            ['x' => 3.2, 'y' => 0.0],
            ['x' => 3.2, 'y' => 4.5],
            ['x' => 0.0, 'y' => 4.5],
        ], [], [
            'texture' => 'satin',
            'roll_width_cm' => 360,
            'harpoon_type' => 'separate',
            'shrink_x_percent' => 8,
            'shrink_y_percent' => 7,
            'orientation_mode' => 'center_room',
            'same_roll_required' => true,
        ]);

        $this->assertCount(1, $panels);
        $this->assertSame('Полотно 1', $panels[0]['label']);
        $this->assertSame(14.4, $panels[0]['area_m2']);
        $this->assertSame('satin', $panels[0]['production']['texture']);
        $this->assertSame(360, $panels[0]['production']['roll_width_cm']);
        $this->assertSame('separate', $panels[0]['production']['harpoon_type']);
        $this->assertTrue($panels[0]['production']['same_roll_required']);
    }

    public function test_it_splits_room_into_two_panels_by_light_line(): void
    {
        $splitter = new CeilingLightLinePanelSplitter();

        $panels = $splitter->split(
            [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 4.0, 'y' => 0.0],
                ['x' => 4.0, 'y' => 4.0],
                ['x' => 0.0, 'y' => 4.0],
            ],
            [[
                'width_m' => 0.08,
                'closed' => false,
                'points' => [
                    ['x' => 2.0, 'y' => 0.0],
                    ['x' => 2.0, 'y' => 4.0],
                ],
            ]],
        );

        $this->assertCount(2, $panels);
        $this->assertSame('Полотно 1', $panels[0]['label']);
        $this->assertSame('Полотно 2', $panels[1]['label']);
        $this->assertEqualsWithDelta(7.84, $panels[0]['area_m2'], 0.15);
        $this->assertEqualsWithDelta(7.84, $panels[1]['area_m2'], 0.15);
    }

    public function test_it_preserves_irregular_panel_contour_after_split(): void
    {
        $splitter = new CeilingLightLinePanelSplitter();

        $panels = $splitter->split(
            [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 5.0, 'y' => 0.0],
                ['x' => 5.0, 'y' => 2.0],
                ['x' => 2.0, 'y' => 2.0],
                ['x' => 2.0, 'y' => 5.0],
                ['x' => 0.0, 'y' => 5.0],
            ],
            [[
                'width_m' => 0.08,
                'closed' => false,
                'points' => [
                    ['x' => 1.0, 'y' => 0.0],
                    ['x' => 1.0, 'y' => 5.0],
                ],
            ]],
        );

        $this->assertCount(2, $panels);

        $complexPanels = array_values(array_filter($panels, static fn (array $panel) => count($panel['shape_points'] ?? []) > 4));
        $this->assertNotEmpty($complexPanels);
        $this->assertGreaterThanOrEqual(6, count($complexPanels[0]['shape_points']));
        $this->assertEqualsWithDelta(15.6, $panels[0]['area_m2'] + $panels[1]['area_m2'], 0.2);
    }
}
