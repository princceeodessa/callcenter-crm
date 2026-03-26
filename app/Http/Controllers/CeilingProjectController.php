<?php

namespace App\Http\Controllers;

use App\Models\CeilingProject;
use App\Models\CeilingProjectRoom;
use App\Models\CeilingProjectRoomElement;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Measurement;
use App\Services\Ceiling\CeilingProjectCalculator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CeilingProjectController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeAdmin($request);

        $q = trim((string) $request->query('q', ''));

        $projects = CeilingProject::query()
            ->with(['deal.contact', 'measurement'])
            ->withCount('rooms')
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($inner) use ($q) {
                    $inner
                        ->where('title', 'like', '%'.$q.'%')
                        ->orWhereHas('deal', function ($dealQuery) use ($q) {
                            $dealQuery
                                ->where('title', 'like', '%'.$q.'%')
                                ->orWhereHas('contact', function ($contactQuery) use ($q) {
                                    $contactQuery
                                        ->where('name', 'like', '%'.$q.'%')
                                        ->orWhere('phone', 'like', '%'.$q.'%');
                                });
                        });
                });
            })
            ->orderByDesc('updated_at')
            ->paginate(20)
            ->withQueryString();

        $deals = $this->availableDeals($request->user()->account_id);

        return view('ceiling-projects.index', [
            'projects' => $projects,
            'deals' => $deals,
            'q' => $q,
            'statusOptions' => CeilingProject::statusOptions(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorizeAdmin($request);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'deal_id' => ['nullable', 'integer'],
        ]);

        $dealId = $this->resolveDealId($request->user()->account_id, $data['deal_id'] ?? null);
        $this->ensureDealLinkIsAvailable($request->user()->account_id, $dealId);

        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            $title = 'Проектировка '.now()->format('d.m.Y H:i');
        }

        $project = CeilingProject::create([
            'account_id' => $request->user()->account_id,
            'deal_id' => $dealId,
            'created_by_user_id' => $request->user()->id,
            'updated_by_user_id' => $request->user()->id,
            'title' => $title,
            'status' => CeilingProject::STATUS_DRAFT,
            'canvas_material' => 'pvc',
            'waste_percent' => 12,
            'extra_margin_m' => 0,
            'discount_percent' => 0,
            ...CeilingProject::defaultEstimateRates(),
            'last_calculated_at' => now(),
        ]);

        return redirect()
            ->route('ceiling-projects.show', $project)
            ->with('status', 'Проект создан.');
    }

    public function showForDeal(Request $request, Deal $deal): RedirectResponse
    {
        $this->authorizeAdmin($request);
        abort_unless($deal->account_id === $request->user()->account_id, 403);

        $project = CeilingProject::query()->firstOrCreate(
            [
                'account_id' => $request->user()->account_id,
                'deal_id' => $deal->id,
            ],
            [
                'created_by_user_id' => $request->user()->id,
                'updated_by_user_id' => $request->user()->id,
                'title' => trim((string) $deal->title) !== '' ? $deal->title : 'Проект для сделки #'.$deal->id,
                'status' => CeilingProject::STATUS_DRAFT,
                'canvas_material' => 'pvc',
                'waste_percent' => 12,
                'extra_margin_m' => 0,
                'discount_percent' => 0,
                ...CeilingProject::defaultEstimateRates(),
                'last_calculated_at' => now(),
            ]
        );

        return redirect()->route('ceiling-projects.show', $project);
    }

    public function show(Request $request, CeilingProject $project, CeilingProjectCalculator $calculator): View
    {
        $this->authorizeProject($request, $project);

        return view('ceiling-projects.show', $this->buildShowViewData($request, $project, $calculator, 'standard'));
    }

    public function drafting(Request $request, CeilingProject $project, CeilingProjectCalculator $calculator): View
    {
        $this->authorizeProject($request, $project);

        return view('ceiling-projects.show', $this->buildShowViewData($request, $project, $calculator, 'drafting'));
    }

    public function update(Request $request, CeilingProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'deal_id' => ['nullable', 'integer'],
            'status' => ['required', 'string', 'in:draft,in_progress,ready'],
            'measurement_id' => ['nullable', 'integer'],
            'canvas_material' => ['required', 'string', 'max:32'],
            'canvas_texture' => ['nullable', 'string', 'max:32'],
            'canvas_color' => ['nullable', 'string', 'max:100'],
            'mounting_system' => ['nullable', 'string', 'max:64'],
            'waste_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'extra_margin_m' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'discount_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'canvas_price_per_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'profile_price_per_m' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'insert_price_per_m' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'spotlight_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'chandelier_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'pipe_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'curtain_niche_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'cornice_price_per_m' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'ventilation_hole_price' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'mounting_price_per_m2' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'additional_cost' => ['nullable', 'numeric', 'min:0', 'max:1000000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);

        $rates = CeilingProject::defaultEstimateRates();
        $dealId = $this->resolveDealId($request->user()->account_id, $data['deal_id'] ?? null);
        $this->ensureDealLinkIsAvailable($request->user()->account_id, $dealId, $project->id);

        $project->fill([
            'deal_id' => $dealId,
            'measurement_id' => $this->resolveMeasurementId($request->user()->account_id, $data['measurement_id'] ?? null),
            'title' => $this->trimNullable($data['title'] ?? null),
            'status' => $data['status'],
            'canvas_material' => $data['canvas_material'],
            'canvas_texture' => $this->trimNullable($data['canvas_texture'] ?? null),
            'canvas_color' => $this->trimNullable($data['canvas_color'] ?? null),
            'mounting_system' => $this->trimNullable($data['mounting_system'] ?? null),
            'waste_percent' => $data['waste_percent'] ?? 12,
            'extra_margin_m' => $data['extra_margin_m'] ?? 0,
            'discount_percent' => $data['discount_percent'] ?? 0,
            'canvas_price_per_m2' => $data['canvas_price_per_m2'] ?? $rates['canvas_price_per_m2'],
            'profile_price_per_m' => $data['profile_price_per_m'] ?? $rates['profile_price_per_m'],
            'insert_price_per_m' => $data['insert_price_per_m'] ?? $rates['insert_price_per_m'],
            'spotlight_price' => $data['spotlight_price'] ?? $rates['spotlight_price'],
            'chandelier_price' => $data['chandelier_price'] ?? $rates['chandelier_price'],
            'pipe_price' => $data['pipe_price'] ?? $rates['pipe_price'],
            'curtain_niche_price' => $data['curtain_niche_price'] ?? $rates['curtain_niche_price'],
            'cornice_price_per_m' => $data['cornice_price_per_m'] ?? $rates['cornice_price_per_m'],
            'ventilation_hole_price' => $data['ventilation_hole_price'] ?? $rates['ventilation_hole_price'],
            'mounting_price_per_m2' => $data['mounting_price_per_m2'] ?? $rates['mounting_price_per_m2'],
            'additional_cost' => $data['additional_cost'] ?? $rates['additional_cost'],
            'notes' => $this->trimNullable($data['notes'] ?? null),
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project)
                ->with('status', 'Project saved.');
        }

        return redirect()
            ->route('ceiling-projects.show', $project)
            ->with('status', 'Проект сохранен.');
    }

    public function uploadReferenceImage(Request $request, CeilingProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'reference_image' => ['required', 'image', 'max:10240'],
        ]);

        if ($project->reference_image_path) {
            Storage::disk('public')->delete($project->reference_image_path);
        }

        $path = $data['reference_image']->store('ceiling-projects/'.$project->account_id, 'public');

        $project->forceFill([
            'reference_image_path' => $path,
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project)
                ->with('status', 'Reference image uploaded.');
        }

        return redirect()
            ->route('ceiling-projects.show', $project)
            ->with('status', 'Картинка проекта загружена.');
    }

    public function referenceImage(Request $request, CeilingProject $project)
    {
        $this->authorizeProject($request, $project);

        if (!$project->reference_image_path || !Storage::disk('public')->exists($project->reference_image_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($project->reference_image_path));
    }

    public function applyEstimate(Request $request, CeilingProject $project, CeilingProjectCalculator $calculator): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $project->load('deal', 'rooms');

        if (!$project->deal) {
            return back()->withErrors([
                'deal_id' => 'Сначала привяжите сделку к проекту.',
            ]);
        }

        $summary = $calculator->calculateProject($project);
        $grandTotal = (float) ($summary['estimate']['grand_total'] ?? 0);

        if ($grandTotal <= 0) {
            return back()->withErrors([
                'estimate' => 'Смета пока равна нулю. Заполните прайс проекта и геометрию.',
            ]);
        }

        $deal = $project->deal;
        $beforeAmount = $deal->amount;

        $deal->forceFill([
            'amount' => $grandTotal,
            'currency' => 'RUB',
        ])->save();

        $project->forceFill([
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        DealActivity::create([
            'account_id' => $request->user()->account_id,
            'deal_id' => $deal->id,
            'author_user_id' => $request->user()->id,
            'type' => 'deal_updated',
            'body' => 'Сумма сделки обновлена из проектировки потолка.',
            'payload' => [
                'source' => 'ceiling_project',
                'ceiling_project_id' => $project->id,
                'before' => ['amount' => $beforeAmount],
                'after' => ['amount' => $deal->amount],
            ],
        ]);

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project)
                ->with('status', 'Estimate transferred to deal.');
        }

        return redirect()
            ->route('ceiling-projects.show', $project)
            ->with('status', 'Итоговая сумма перенесена в сделку.');
    }

    public function storeRoom(Request $request, CeilingProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $data = $this->validateRoom($request);
        $nextSortOrder = (int) $project->rooms()->max('sort_order') + 1;

        $room = $project->rooms()->create($this->roomPayload($request, $project, $data, $nextSortOrder));
        $project->forceFill(['last_calculated_at' => now(), 'updated_by_user_id' => $request->user()->id])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project, $room)
                ->with('status', 'Room added.');
        }

        return redirect()
            ->route('ceiling-projects.show', ['project' => $project, 'room' => $room->id])
            ->with('status', 'Помещение добавлено.');
    }

    public function updateRoom(Request $request, CeilingProject $project, CeilingProjectRoom $room): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeRoom($project, $room);

        $data = $this->validateRoom($request);
        $room->update($this->roomPayload($request, $project, $data, $room->sort_order));
        $project->forceFill(['last_calculated_at' => now(), 'updated_by_user_id' => $request->user()->id])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project, $room)
                ->with('status', 'Room updated.');
        }

        return redirect()
            ->route('ceiling-projects.show', ['project' => $project, 'room' => $room->id])
            ->with('status', 'Помещение обновлено.');
    }

    public function updateRoomGeometry(Request $request, CeilingProject $project, CeilingProjectRoom $room): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeRoom($project, $room);

        $data = $request->validate([
            'shape_points_json' => ['required', 'string'],
        ]);

        $points = json_decode($data['shape_points_json'], true);
        if (!is_array($points)) {
            return back()->withErrors(['shape_points_json' => 'Не удалось разобрать геометрию комнаты.']);
        }

        $normalized = [];
        foreach ($points as $point) {
            if (!is_array($point) || !isset($point['x'], $point['y'])) {
                continue;
            }

            $normalized[] = [
                'x' => round((float) $point['x'], 2),
                'y' => round((float) $point['y'], 2),
            ];
        }

        if (count($normalized) < 3) {
            return back()->withErrors(['shape_points_json' => 'Нужно минимум 3 точки для полигона.']);
        }

        $room->forceFill([
            'shape_type' => CeilingProjectRoom::SHAPE_POLYGON,
            'shape_points' => $normalized,
            'corners_count' => count($normalized),
        ])->save();

        $project->forceFill(['last_calculated_at' => now(), 'updated_by_user_id' => $request->user()->id])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project, $room)
                ->with('status', 'Room geometry saved.');
        }

        return redirect()
            ->route('ceiling-projects.show', ['project' => $project, 'room' => $room->id])
            ->with('status', 'Геометрия комнаты сохранена.');
    }

    public function destroyRoom(Request $request, CeilingProject $project, CeilingProjectRoom $room): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeRoom($project, $room);

        $room->delete();
        $project->forceFill(['last_calculated_at' => now(), 'updated_by_user_id' => $request->user()->id])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project)
                ->with('status', 'Room deleted.');
        }

        return redirect()
            ->route('ceiling-projects.show', $project)
            ->with('status', 'Помещение удалено.');
    }

    public function storeRoomElement(Request $request, CeilingProject $project, CeilingProjectRoom $room): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeRoom($project, $room);

        $data = $this->validateElement($request);
        $sortOrder = (int) $room->elements()->max('sort_order') + 1;

        $room->elements()->create($this->elementPayload($request, $room, $data, $sortOrder));
        $project->forceFill(['last_calculated_at' => now(), 'updated_by_user_id' => $request->user()->id])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project, $room)
                ->with('status', 'Element added.');
        }

        return redirect()
            ->route('ceiling-projects.show', ['project' => $project, 'room' => $room->id])
            ->with('status', 'Элемент добавлен.');
    }

    public function updateRoomElement(Request $request, CeilingProject $project, CeilingProjectRoom $room, CeilingProjectRoomElement $element): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeRoom($project, $room);
        $this->authorizeElement($room, $element);

        $data = $this->validateElement($request);
        $element->update($this->elementPayload($request, $room, $data, $element->sort_order));
        $project->forceFill(['last_calculated_at' => now(), 'updated_by_user_id' => $request->user()->id])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project, $room)
                ->with('status', 'Element updated.');
        }

        return redirect()
            ->route('ceiling-projects.show', ['project' => $project, 'room' => $room->id])
            ->with('status', 'Элемент обновлен.');
    }

    public function destroyRoomElement(Request $request, CeilingProject $project, CeilingProjectRoom $room, CeilingProjectRoomElement $element): RedirectResponse
    {
        $this->authorizeProject($request, $project);
        $this->authorizeRoom($project, $room);
        $this->authorizeElement($room, $element);

        $element->delete();
        $project->forceFill(['last_calculated_at' => now(), 'updated_by_user_id' => $request->user()->id])->save();

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project, $room)
                ->with('status', 'Element deleted.');
        }

        return redirect()
            ->route('ceiling-projects.show', ['project' => $project, 'room' => $room->id])
            ->with('status', 'Элемент удален.');
    }

    private function authorizeAdmin(Request $request): void
    {
        abort_unless($request->user()?->role === 'admin', 403);
    }

    private function authorizeProject(Request $request, CeilingProject $project): void
    {
        $this->authorizeAdmin($request);
        abort_unless($project->account_id === $request->user()->account_id, 404);
    }

    private function authorizeRoom(CeilingProject $project, CeilingProjectRoom $room): void
    {
        abort_unless((int) $room->ceiling_project_id === (int) $project->id, 404);
    }

    private function authorizeElement(CeilingProjectRoom $room, CeilingProjectRoomElement $element): void
    {
        abort_unless((int) $element->ceiling_project_room_id === (int) $room->id, 404);
    }

    private function validateRoom(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'shape_type' => ['required', 'string', 'in:rectangle,polygon'],
            'width_m' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'length_m' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'height_m' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'corners_count' => ['nullable', 'integer', 'min:4', 'max:100'],
            'manual_area_m2' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'manual_perimeter_m' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'spotlights_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'chandelier_points_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'pipes_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'curtain_niches_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'ventilation_holes_count' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:10000'],
        ]);
    }

    private function roomPayload(Request $request, CeilingProject $project, array $data, int $sortOrder): array
    {
        return [
            'account_id' => $request->user()->account_id,
            'ceiling_project_id' => $project->id,
            'sort_order' => $sortOrder,
            'name' => trim((string) $data['name']),
            'shape_type' => $data['shape_type'],
            'width_m' => $data['width_m'] ?? null,
            'length_m' => $data['length_m'] ?? null,
            'height_m' => $data['height_m'] ?? null,
            'corners_count' => $data['corners_count'] ?? 4,
            'manual_area_m2' => $data['manual_area_m2'] ?? null,
            'manual_perimeter_m' => $data['manual_perimeter_m'] ?? null,
            'spotlights_count' => $data['spotlights_count'] ?? 0,
            'chandelier_points_count' => $data['chandelier_points_count'] ?? 0,
            'pipes_count' => $data['pipes_count'] ?? 0,
            'curtain_niches_count' => $data['curtain_niches_count'] ?? 0,
            'ventilation_holes_count' => $data['ventilation_holes_count'] ?? 0,
            'notes' => $this->trimNullable($data['notes'] ?? null),
        ];
    }

    private function validateElement(Request $request): array
    {
        return $request->validate([
            'type' => ['required', 'string', 'in:spotlight,chandelier,pipe,curtain_niche,ventilation,cornice,custom'],
            'label' => ['nullable', 'string', 'max:255'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'placement_mode' => ['nullable', 'string', 'in:free,wall'],
            'segment_index' => ['nullable', 'integer', 'min:0', 'max:1000', 'required_if:placement_mode,wall'],
            'offset_m' => ['nullable', 'numeric', 'min:0', 'max:10000', 'required_if:placement_mode,wall'],
            'x_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'y_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'length_m' => ['nullable', 'numeric', 'min:0', 'max:10000'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }

    private function elementPayload(Request $request, CeilingProjectRoom $room, array $data, int $sortOrder): array
    {
        $placementMode = $data['placement_mode'] ?? CeilingProjectRoomElement::PLACEMENT_FREE;
        $isWallPlacement = $placementMode === CeilingProjectRoomElement::PLACEMENT_WALL;

        return [
            'account_id' => $request->user()->account_id,
            'ceiling_project_room_id' => $room->id,
            'sort_order' => $sortOrder,
            'type' => $data['type'],
            'label' => $this->trimNullable($data['label'] ?? null),
            'quantity' => $data['quantity'] ?? 1,
            'placement_mode' => $placementMode,
            'segment_index' => $isWallPlacement ? ($data['segment_index'] ?? null) : null,
            'offset_m' => $isWallPlacement ? ($data['offset_m'] ?? null) : null,
            'x_m' => $isWallPlacement ? null : ($data['x_m'] ?? null),
            'y_m' => $isWallPlacement ? null : ($data['y_m'] ?? null),
            'length_m' => $data['length_m'] ?? null,
            'notes' => $this->trimNullable($data['notes'] ?? null),
        ];
    }

    private function buildShowViewData(Request $request, CeilingProject $project, CeilingProjectCalculator $calculator, string $viewMode): array
    {
        $project->load(['deal.contact', 'rooms', 'measurement']);
        $project->load('rooms.elements');

        $summary = $calculator->calculateProject($project);
        $selectedRoomId = (int) $request->query('room', 0);
        $selectedRoom = $selectedRoomId > 0
            ? $project->rooms->firstWhere('id', $selectedRoomId)
            : $project->rooms->first();

        return [
            'project' => $project,
            'summary' => $summary,
            'selectedRoom' => $selectedRoom,
            'viewMode' => $viewMode,
            'availableDeals' => $this->availableDeals($request->user()->account_id),
            'measurements' => $this->availableMeasurements($request->user()->account_id),
            'statusOptions' => CeilingProject::statusOptions(),
            'materialOptions' => CeilingProject::materialOptions(),
            'textureOptions' => CeilingProject::textureOptions(),
            'shapeOptions' => CeilingProjectRoom::shapeOptions(),
            'elementTypeOptions' => CeilingProjectRoomElement::typeOptions(),
            'elementPlacementOptions' => CeilingProjectRoomElement::placementOptions(),
            'referenceImageUrl' => $project->reference_image_path
                ? route('ceiling-projects.reference-image.show', $project)
                : null,
        ];
    }

    private function redirectToProject(Request $request, CeilingProject $project, ?CeilingProjectRoom $room = null): RedirectResponse
    {
        $parameters = ['project' => $project];

        if ($room) {
            $parameters['room'] = $room->id;
        } else {
            $requestedRoomId = (int) $request->input('room', $request->query('room', 0));

            if ($requestedRoomId > 0) {
                $parameters['room'] = $requestedRoomId;
            }
        }

        return redirect()->route($this->projectRouteName($request), $parameters);
    }

    private function projectRouteName(Request $request): string
    {
        $viewMode = trim((string) $request->input('view_mode', $request->query('view_mode', 'standard')));

        return $viewMode === 'drafting'
            ? 'ceiling-projects.drafting'
            : 'ceiling-projects.show';
    }

    private function resolveDealId(int $accountId, mixed $dealId): ?int
    {
        if (!$dealId) {
            return null;
        }

        return Deal::query()
            ->where('account_id', $accountId)
            ->whereKey((int) $dealId)
            ->value('id');
    }

    private function resolveMeasurementId(int $accountId, mixed $measurementId): ?int
    {
        if (!$measurementId) {
            return null;
        }

        return Measurement::query()
            ->where('account_id', $accountId)
            ->whereKey((int) $measurementId)
            ->value('id');
    }

    private function ensureDealLinkIsAvailable(int $accountId, ?int $dealId, ?int $ignoreProjectId = null): void
    {
        if ($dealId === null) {
            return;
        }

        $exists = CeilingProject::query()
            ->where('account_id', $accountId)
            ->where('deal_id', $dealId)
            ->when($ignoreProjectId !== null, fn ($query) => $query->whereKeyNot($ignoreProjectId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages([
                'deal_id' => 'Эта сделка уже привязана к другой проектировке.',
            ]);
        }
    }

    private function availableDeals(int $accountId)
    {
        return Deal::query()
            ->where('account_id', $accountId)
            ->with('contact')
            ->orderByDesc('updated_at')
            ->limit(200)
            ->get(['id', 'account_id', 'title', 'contact_id', 'closed_at']);
    }

    private function availableMeasurements(int $accountId)
    {
        return Measurement::query()
            ->where('account_id', $accountId)
            ->orderByDesc('scheduled_at')
            ->limit(100)
            ->get();
    }

    private function trimNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
