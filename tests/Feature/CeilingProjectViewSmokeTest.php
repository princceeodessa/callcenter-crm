<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\CeilingProject;
use App\Models\CeilingProjectRoom;
use App\Models\CeilingProjectRoomElement;
use App\Models\User;
use App\Services\Ceiling\CeilingProductionLayoutPlanner;
use App\Services\Ceiling\CeilingProjectCalculator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class CeilingProjectViewSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::share('errors', new ViewErrorBag());
    }

    public function test_project_show_view_renders_workspace_and_production_links(): void
    {
        $context = $this->buildProjectContext();

        $html = view('ceiling-projects.show', [
            'project' => $context['project'],
            'summary' => $context['summary'],
            'selectedRoom' => $context['selectedRoom'],
            'viewMode' => 'standard',
            'sketchRecognition' => null,
            'sketchCrop' => null,
            'availableDeals' => collect(),
            'measurements' => collect(),
            'statusOptions' => CeilingProject::statusOptions(),
            'materialOptions' => CeilingProject::materialOptions(),
            'textureOptions' => CeilingProject::textureOptions(),
            'shapeOptions' => CeilingProjectRoom::shapeOptions(),
            'featureKindOptions' => CeilingProjectRoom::featureKindOptions(),
            'featureFigureOptions' => CeilingProjectRoom::featureFigureOptions(),
            'elementTypeOptions' => CeilingProjectRoomElement::typeOptions(),
            'elementPlacementOptions' => CeilingProjectRoomElement::placementOptions(),
            'sketchImageUrl' => null,
            'sketchImageSharedWithReference' => false,
            'referenceImageUrl' => null,
        ])->render();

        $this->assertStringContainsString('Пакет производства', $html);
        $this->assertStringContainsString('Открыть чертеж комнаты', $html);
        $this->assertStringContainsString('Полотна комнаты', $html);
        $this->assertStringContainsString('Редактор геометрии', $html);
        $this->assertStringNotContainsString('РџР', $html);
    }

    public function test_room_panels_view_renders_clean_production_summary(): void
    {
        $context = $this->buildProjectContext();
        $roomPacket = $context['roomPackets'][0];

        $html = view('ceiling-projects.panels', [
            'project' => $context['project'],
            'room' => $roomPacket['room'],
            'metrics' => $roomPacket['metrics'],
            'panels' => $roomPacket['panels'],
            'layoutPlan' => $roomPacket['layoutPlan'],
        ])->render();

        $this->assertStringContainsString('Полотна комнаты', $html);
        $this->assertStringContainsString('Пакет проекта', $html);
        $this->assertStringContainsString('Сценарии рулона', $html);
        $this->assertStringContainsString('Производственные параметры', $html);
        $this->assertStringNotContainsString('РџР', $html);
    }

    public function test_project_production_packet_view_renders_all_rooms_and_summary(): void
    {
        $context = $this->buildProjectContext();

        $html = view('ceiling-projects.production-packet', [
            'project' => $context['project'],
            'summary' => $context['summary'],
            'roomPackets' => $context['roomPackets'],
            'packetSummary' => $context['packetSummary'],
        ])->render();

        $this->assertStringContainsString('Пакет для производства', $html);
        $this->assertStringContainsString('Гостиная', $html);
        $this->assertStringContainsString('Кухня', $html);
        $this->assertStringContainsString('Что проверить по проекту', $html);
        $this->assertStringContainsString('Комнаты со спецраскроем', $html);
        $this->assertStringNotContainsString('РџР', $html);
    }

    /**
     * @return array{
     *   project: CeilingProject,
     *   selectedRoom: CeilingProjectRoom,
     *   summary: array<string, mixed>,
     *   roomPackets: array<int, array{room: CeilingProjectRoom, metrics: array<string, mixed>, panels: array<int, array<string, mixed>>, layoutPlan: array<string, mixed>}>,
     *   packetSummary: array<string, mixed>
     * }
     */
    private function buildProjectContext(): array
    {
        $user = $this->actingAsAdmin();
        $accountId = (int) $user->account_id;

        $roomOne = $this->makeRoom(
            id: 201,
            accountId: $accountId,
            projectId: 101,
            name: 'Гостиная',
            points: [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 4.2, 'y' => 0.0],
                ['x' => 4.2, 'y' => 3.0],
                ['x' => 0.0, 'y' => 3.0],
            ],
            productionSettings: [
                'texture' => 'matte',
                'roll_width_cm' => 320,
                'harpoon_type' => 'standard',
                'same_roll_required' => false,
                'special_cutting' => false,
                'seam_enabled' => false,
                'shrink_x_percent' => 7,
                'shrink_y_percent' => 7,
                'orientation_mode' => 'parallel_segment',
                'orientation_segment_index' => 0,
                'orientation_offset_m' => 0.1,
                'seam_offset_m' => 0.0,
                'comment' => 'Основная гостиная',
            ],
            derivedPanels: [[
                'id' => 'room1_panel_1',
                'label' => 'Полотно 1',
                'area_m2' => 12.6,
                'bounds' => ['min_x' => 0.0, 'min_y' => 0.0, 'max_x' => 4.2, 'max_y' => 3.0],
                'centroid' => ['x' => 2.1, 'y' => 1.5],
                'shape_points' => [
                    ['x' => 0.0, 'y' => 0.0],
                    ['x' => 4.2, 'y' => 0.0],
                    ['x' => 4.2, 'y' => 3.0],
                    ['x' => 0.0, 'y' => 3.0],
                ],
                'holes' => [],
                'source' => 'room',
                'production' => [
                    'texture' => 'matte',
                    'roll_width_cm' => 320,
                    'harpoon_type' => 'standard',
                    'same_roll_required' => false,
                    'special_cutting' => false,
                    'seam_enabled' => false,
                    'shrink_x_percent' => 7,
                    'shrink_y_percent' => 7,
                    'orientation_mode' => 'parallel_segment',
                    'orientation_segment_index' => 0,
                    'orientation_offset_m' => 0.1,
                    'seam_offset_m' => 0.0,
                    'comment' => 'Основная гостиная',
                ],
            ]],
            elements: [
                $this->makeElement(301, $accountId, 201, CeilingProjectRoomElement::TYPE_SPOTLIGHT, 'Спот', 2, 1.2, 1.0),
                $this->makeElement(302, $accountId, 201, CeilingProjectRoomElement::TYPE_CORNICE, 'Карниз', 1, null, null, 3.0),
            ],
        );

        $roomTwo = $this->makeRoom(
            id: 202,
            accountId: $accountId,
            projectId: 101,
            name: 'Кухня',
            points: [
                ['x' => 0.0, 'y' => 0.0],
                ['x' => 4.8, 'y' => 0.0],
                ['x' => 4.8, 'y' => 1.4],
                ['x' => 2.9, 'y' => 1.4],
                ['x' => 2.9, 'y' => 4.0],
                ['x' => 0.0, 'y' => 4.0],
            ],
            productionSettings: [
                'texture' => 'satin',
                'roll_width_cm' => 180,
                'harpoon_type' => 'separate',
                'same_roll_required' => true,
                'special_cutting' => true,
                'seam_enabled' => true,
                'shrink_x_percent' => 7,
                'shrink_y_percent' => 7,
                'orientation_mode' => 'parallel_segment',
                'orientation_segment_index' => 1,
                'orientation_offset_m' => 0.15,
                'seam_offset_m' => 0.2,
                'comment' => 'Спецраскрой из одного рулона',
            ],
            derivedPanels: [[
                'id' => 'room2_panel_1',
                'label' => 'Полотно кухни',
                'area_m2' => 13.46,
                'bounds' => ['min_x' => 0.0, 'min_y' => 0.0, 'max_x' => 4.8, 'max_y' => 4.0],
                'centroid' => ['x' => 2.2, 'y' => 2.0],
                'shape_points' => [
                    ['x' => 0.0, 'y' => 0.0],
                    ['x' => 4.8, 'y' => 0.0],
                    ['x' => 4.8, 'y' => 1.4],
                    ['x' => 2.9, 'y' => 1.4],
                    ['x' => 2.9, 'y' => 4.0],
                    ['x' => 0.0, 'y' => 4.0],
                ],
                'holes' => [],
                'source' => 'room',
                'production' => [
                    'texture' => 'satin',
                    'roll_width_cm' => 180,
                    'harpoon_type' => 'separate',
                    'same_roll_required' => true,
                    'special_cutting' => true,
                    'seam_enabled' => true,
                    'shrink_x_percent' => 7,
                    'shrink_y_percent' => 7,
                    'orientation_mode' => 'parallel_segment',
                    'orientation_segment_index' => 1,
                    'orientation_offset_m' => 0.15,
                    'seam_offset_m' => 0.2,
                    'comment' => 'Спецраскрой из одного рулона',
                ],
            ]],
            elements: [
                $this->makeElement(303, $accountId, 202, CeilingProjectRoomElement::TYPE_CURTAIN_NICHE, 'Ниша', 1, 1.5, 0.6),
            ],
        );

        $project = new CeilingProject([
            'account_id' => $accountId,
            'title' => 'Тестовый проект Easy Ceiling',
            'status' => CeilingProject::STATUS_IN_PROGRESS,
            'canvas_material' => 'pvc',
            'canvas_texture' => 'matte',
            'canvas_color' => 'Белый',
            'mounting_system' => 'Стандарт',
            'waste_percent' => 12,
            'extra_margin_m' => 0.1,
            'discount_percent' => 0,
            'canvas_price_per_m2' => 950,
            'profile_price_per_m' => 180,
            'insert_price_per_m' => 45,
            'spotlight_price' => 350,
            'chandelier_price' => 550,
            'pipe_price' => 250,
            'curtain_niche_price' => 1400,
            'cornice_price_per_m' => 280,
            'ventilation_hole_price' => 250,
            'mounting_price_per_m2' => 450,
            'additional_cost' => 500,
            'notes' => 'Проверка экранов проектировки',
        ]);
        $project->id = 101;
        $project->exists = true;
        $project->setRelation('rooms', new Collection([$roomOne, $roomTwo]));
        $project->setRelation('deal', null);
        $project->setRelation('measurement', null);

        /** @var CeilingProjectCalculator $calculator */
        $calculator = app(CeilingProjectCalculator::class);
        /** @var CeilingProductionLayoutPlanner $planner */
        $planner = app(CeilingProductionLayoutPlanner::class);

        $summary = $calculator->calculateProject($project);
        $roomPackets = collect([$roomOne, $roomTwo])->map(function (CeilingProjectRoom $room) use ($calculator, $planner) {
            $panels = is_array($room->derived_panels) ? array_values($room->derived_panels) : [];

            return [
                'room' => $room,
                'metrics' => $calculator->calculateRoom($room),
                'panels' => $panels,
                'layoutPlan' => $planner->plan($room, $panels),
            ];
        })->all();

        return [
            'project' => $project,
            'selectedRoom' => $roomOne,
            'summary' => $summary,
            'roomPackets' => $roomPackets,
            'packetSummary' => $this->buildPacketSummary($roomPackets),
        ];
    }

    /**
     * @param  array<int, array{room: CeilingProjectRoom, metrics: array<string, mixed>, panels: array<int, array<string, mixed>>, layoutPlan: array<string, mixed>}>  $roomPackets
     * @return array<string, mixed>
     */
    private function buildPacketSummary(array $roomPackets): array
    {
        $summary = [
            'rooms_count' => count($roomPackets),
            'panels_count' => 0,
            'roll_sequences_count' => 0,
            'strips_count' => 0,
            'seamed_panels_count' => 0,
            'finished_area_m2' => 0.0,
            'consumed_area_m2' => 0.0,
            'stretch_reserve_m2' => 0.0,
            'roll_length_total_m' => 0.0,
            'same_roll_rooms_count' => 0,
            'special_cutting_rooms_count' => 0,
            'seam_rooms_count' => 0,
            'warnings' => [],
        ];

        foreach ($roomPackets as $packet) {
            $room = $packet['room'];
            $layoutSummary = $packet['layoutPlan']['summary'] ?? [];
            $layoutSettings = $packet['layoutPlan']['settings'] ?? [];

            $summary['panels_count'] += (int) ($layoutSummary['panels_count'] ?? 0);
            $summary['roll_sequences_count'] += (int) ($layoutSummary['roll_sequences_count'] ?? 0);
            $summary['strips_count'] += (int) ($layoutSummary['strips_count'] ?? 0);
            $summary['seamed_panels_count'] += (int) ($layoutSummary['seamed_panels_count'] ?? 0);
            $summary['finished_area_m2'] += (float) ($layoutSummary['finished_area_m2'] ?? 0.0);
            $summary['consumed_area_m2'] += (float) ($layoutSummary['consumed_area_m2'] ?? 0.0);
            $summary['stretch_reserve_m2'] += (float) ($layoutSummary['stretch_reserve_m2'] ?? 0.0);
            $summary['roll_length_total_m'] += (float) ($layoutSummary['roll_length_total_m'] ?? 0.0);
            $summary['same_roll_rooms_count'] += !empty($layoutSettings['same_roll_required']) ? 1 : 0;
            $summary['special_cutting_rooms_count'] += !empty($layoutSettings['special_cutting']) ? 1 : 0;
            $summary['seam_rooms_count'] += !empty($layoutSettings['seam_enabled']) ? 1 : 0;

            foreach ((array) ($layoutSummary['warnings'] ?? []) as $warning) {
                $summary['warnings'][] = $room->name.': '.$warning;
            }
        }

        $summary['finished_area_m2'] = round($summary['finished_area_m2'], 2);
        $summary['consumed_area_m2'] = round($summary['consumed_area_m2'], 2);
        $summary['stretch_reserve_m2'] = round($summary['stretch_reserve_m2'], 2);
        $summary['roll_length_total_m'] = round($summary['roll_length_total_m'], 2);
        $summary['warnings'] = array_values(array_unique($summary['warnings']));

        return $summary;
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     * @param  array<string, mixed>  $productionSettings
     * @param  array<int, array<string, mixed>>  $derivedPanels
     * @param  array<int, CeilingProjectRoomElement>  $elements
     */
    private function makeRoom(
        int $id,
        int $accountId,
        int $projectId,
        string $name,
        array $points,
        array $productionSettings,
        array $derivedPanels,
        array $elements = [],
    ): CeilingProjectRoom {
        $bounds = $this->boundsFromPoints($points);

        $room = new CeilingProjectRoom([
            'account_id' => $accountId,
            'ceiling_project_id' => $projectId,
            'sort_order' => $id,
            'name' => $name,
            'shape_type' => CeilingProjectRoom::SHAPE_POLYGON,
            'width_m' => round($bounds['max_x'] - $bounds['min_x'], 2),
            'length_m' => round($bounds['max_y'] - $bounds['min_y'], 2),
            'height_m' => 2.7,
            'corners_count' => count($points),
            'shape_points' => $points,
            'feature_shapes' => [],
            'light_line_shapes' => [],
            'derived_panels' => $derivedPanels,
            'production_settings' => $productionSettings,
            'spotlights_count' => 2,
            'chandelier_points_count' => 0,
            'pipes_count' => 0,
            'curtain_niches_count' => 1,
            'ventilation_holes_count' => 0,
            'notes' => 'Smoke test room',
        ]);
        $room->id = $id;
        $room->exists = true;
        $room->setRelation('elements', new Collection($elements));

        return $room;
    }

    private function makeElement(
        int $id,
        int $accountId,
        int $roomId,
        string $type,
        string $label,
        int $quantity,
        ?float $x,
        ?float $y,
        ?float $length = null,
    ): CeilingProjectRoomElement {
        $element = new CeilingProjectRoomElement([
            'account_id' => $accountId,
            'ceiling_project_room_id' => $roomId,
            'sort_order' => $id,
            'type' => $type,
            'label' => $label,
            'quantity' => $quantity,
            'placement_mode' => $x === null || $y === null ? CeilingProjectRoomElement::PLACEMENT_WALL : CeilingProjectRoomElement::PLACEMENT_FREE,
            'segment_index' => $x === null || $y === null ? 0 : null,
            'offset_m' => $x === null || $y === null ? 0.5 : null,
            'x_m' => $x,
            'y_m' => $y,
            'length_m' => $length,
            'notes' => null,
        ]);
        $element->id = $id;
        $element->exists = true;

        return $element;
    }

    /**
     * @param  array<int, array{x: float, y: float}>  $points
     * @return array{min_x: float, min_y: float, max_x: float, max_y: float}
     */
    private function boundsFromPoints(array $points): array
    {
        return [
            'min_x' => min(array_column($points, 'x')),
            'min_y' => min(array_column($points, 'y')),
            'max_x' => max(array_column($points, 'x')),
            'max_y' => max(array_column($points, 'y')),
        ];
    }

    private function actingAsAdmin(): User
    {
        $account = new Account(['name' => 'Тестовый аккаунт']);
        $account->id = 1;
        $account->exists = true;

        $user = new User([
            'account_id' => $account->id,
            'name' => 'Admin',
            'email' => 'admin@example.test',
            'password' => 'secret',
            'role' => 'admin',
            'is_active' => true,
        ]);
        $user->id = 1;
        $user->exists = true;
        $user->setRelation('account', $account);

        $this->be($user);

        return $user;
    }
}
