<?php

namespace App\Http\Controllers;

use App\Models\CeilingProject;
use App\Models\CeilingProjectRoom;
use App\Models\CeilingProjectRoomElement;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Measurement;
use App\Services\Ceiling\CeilingLightLinePanelSplitter;
use App\Services\Ceiling\CeilingProductionLayoutPlanner;
use App\Services\Ceiling\CeilingProjectCalculator;
use App\Services\Ceiling\CeilingSketchRecognitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

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

    public function roomPanels(
        Request $request,
        CeilingProject $project,
        CeilingProjectRoom $room,
        CeilingProjectCalculator $calculator,
        CeilingProductionLayoutPlanner $layoutPlanner
    ): View {
        $this->authorizeProject($request, $project);
        $this->authorizeRoom($project, $room);

        $project->loadMissing(['deal.contact', 'measurement']);
        $room->loadMissing('elements');

        $derivedPanels = $this->buildDerivedPanelsForRoom($room);
        if ($room->derived_panels !== $derivedPanels) {
            $room->forceFill(['derived_panels' => $derivedPanels])->save();
        }

        return view('ceiling-projects.panels', [
            'project' => $project,
            'room' => $room,
            'metrics' => $calculator->calculateRoom($room),
            'panels' => $derivedPanels,
            'layoutPlan' => $layoutPlanner->plan($room, $derivedPanels),
        ]);
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

    public function uploadSketchImage(
        Request $request,
        CeilingProject $project,
        CeilingSketchRecognitionService $recognitionService
    ): RedirectResponse
    {
        return $this->uploadSketchSheet($request, $project, $recognitionService);

        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'sketch_image' => ['required', 'image', 'max:10240'],
        ]);

        if ($project->sketch_image_path && $project->sketch_image_path !== $project->reference_image_path) {
            Storage::disk('public')->delete($project->sketch_image_path);
        }

        $path = $data['sketch_image']->store('ceiling-projects/'.$project->account_id, 'public');

        $project->forceFill([
            'sketch_image_path' => $path,
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        $statusMessage = 'Картинка проекта загружена.';
        $recognizedRoom = null;

        try {
            $result = $recognitionService->recognize($project, Storage::disk('public')->path($path));
            $this->saveRecognitionResult($project, $result);
            $recognizedRoom = $this->upsertRecognitionRoom($request, $project, $result);
            $statusMessage = $recognizedRoom
                ? 'Фото загружено, эскиз распознан и черновик комнаты обновлён.'
                : 'Фото загружено, эскиз распознан.';
        } catch (RuntimeException $exception) {
            $this->saveRecognitionResult($project, $this->buildRecognitionFailure($exception->getMessage()));
            $statusMessage = 'Фото загружено, но распознать эскиз не удалось: '.$exception->getMessage();
        }

        if ($this->projectRouteName($request) !== 'ceiling-projects.show') {
            return $this->redirectToProject($request, $project, $recognizedRoom)
                ->with('status', $statusMessage);
        }

        return $this->redirectToProject($request, $project, $recognizedRoom)
            ->with('status', $statusMessage);

        return redirect()
            ->route('ceiling-projects.show', array_filter([
                'project' => $project,
                'room' => $recognizedRoom?->id,
            ]))
            ->with('status', 'Картинка проекта загружена.');
    }

    public function uploadReferenceImage(Request $request, CeilingProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'reference_image' => ['required', 'image', 'max:10240'],
        ]);

        if (!$project->sketch_image_path && $this->hasLegacySharedSketchImage($project)) {
            $project->forceFill([
                'sketch_image_path' => $project->reference_image_path,
            ])->save();
        }

        if ($project->reference_image_path && $project->reference_image_path !== $project->sketch_image_path) {
            Storage::disk('public')->delete($project->reference_image_path);
        }

        $path = $data['reference_image']->store('ceiling-projects/'.$project->account_id, 'public');

        $project->forceFill([
            'reference_image_path' => $path,
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        return $this->redirectToProject($request, $project)
            ->with('status', 'Подложка для ручной обводки загружена.');
    }

    public function sketchImage(Request $request, CeilingProject $project)
    {
        $this->authorizeProject($request, $project);

        $path = $this->resolveSketchImagePath($project);

        if (!$path || !Storage::disk('public')->exists($path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($path));
    }

    public function referenceImage(Request $request, CeilingProject $project)
    {
        $this->authorizeProject($request, $project);

        if (!$project->reference_image_path || !Storage::disk('public')->exists($project->reference_image_path)) {
            abort(404);
        }

        return response()->file(Storage::disk('public')->path($project->reference_image_path));
    }

    public function recognizeSketch(
        Request $request,
        CeilingProject $project,
        CeilingSketchRecognitionService $recognitionService
    ): RedirectResponse {
        return $this->recognizeSketchCrop($request, $project, $recognitionService);

        $this->authorizeProject($request, $project);

        $sketchImagePath = $this->resolveSketchImagePath($project);

        if (!$sketchImagePath || !Storage::disk('public')->exists($sketchImagePath)) {
            return back()->withErrors([
                'sketch_image' => 'Сначала загрузите фото эскиза для распознавания.',
            ]);
        }

        try {
            $result = $recognitionService->recognize($project, Storage::disk('public')->path($sketchImagePath));
        } catch (RuntimeException $exception) {
            $this->saveRecognitionResult($project, $this->buildRecognitionFailure($exception->getMessage()));

            return $this->redirectToProject($request, $project)
                ->with('status', 'Не удалось распознать эскиз. Подробность показана в блоке распознавания.');
        }

        $this->saveRecognitionResult($project, $result);
        $room = $this->upsertRecognitionRoom($request, $project, $result);

        return $this->redirectToProject($request, $project, $room)
            ->with('status', $room ? 'Sketch recognized and room draft updated.' : 'Sketch recognized, but room geometry was not created.');

        return $this->redirectToProject($request, $project, $room)
            ->with('status', 'Эскиз распознан. Проверьте результат и примените черновик комнаты.');
    }

    public function applySketchRecognition(Request $request, CeilingProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $recognition = $this->loadRecognitionResult($project);
        if (!is_array($recognition)) {
            return back()->withErrors([
                'sketch_recognition' => 'Сначала выполните распознавание эскиза.',
            ]);
        }

        $draft = $recognition['room_draft'] ?? null;
        if (!is_array($draft) || !is_array($draft['shape_points'] ?? null) || count($draft['shape_points']) < 3) {
            return back()->withErrors([
                'sketch_recognition' => 'В распознавании нет готовой геометрии комнаты.',
            ]);
        }

        $room = $this->upsertRecognitionRoom($request, $project, $recognition);

        return $this->redirectToProject($request, $project, $room)
            ->with('status', $room ? 'Черновик комнаты обновлён из OCR.' : 'OCR сохранён, но геометрия комнаты не была создана.');

        return $this->redirectToProject($request, $project, $room)
            ->with('status', $room
                ? 'Р§РµСЂРЅРѕРІРёРє РєРѕРјРЅР°С‚С‹ РѕР±РЅРѕРІР»РµРЅ РёР· СЂР°СЃРїРѕР·РЅР°РІР°РЅРёСЏ СЌСЃРєРёР·Р°.'
                : 'Р Р°СЃРїРѕР·РЅР°РІР°РЅРёРµ СЃРѕС…СЂР°РЅРµРЅРѕ, РЅРѕ РєРѕРјРЅР°С‚Сѓ СЃРѕР±СЂР°С‚СЊ РЅРµ СѓРґР°Р»РѕСЃСЊ.');

        $room = $project->rooms()
            ->where('notes', 'like', '[sketch-recognition]%')
            ->orderByDesc('id')
            ->first();

        $payload = [
            'account_id' => $request->user()->account_id,
            'ceiling_project_id' => $project->id,
            'sort_order' => $room?->sort_order ?? ((int) $project->rooms()->max('sort_order') + 1),
            'name' => trim((string) ($draft['name'] ?? 'Черновик по эскизу')),
            'shape_type' => CeilingProjectRoom::SHAPE_POLYGON,
            'width_m' => $this->nullableFloat($draft['width_m'] ?? null),
            'length_m' => $this->nullableFloat($draft['length_m'] ?? null),
            'height_m' => $this->nullableFloat($draft['height_m'] ?? null),
            'corners_count' => count($draft['shape_points']),
            'manual_area_m2' => $this->nullableFloat($draft['manual_area_m2'] ?? null),
            'manual_perimeter_m' => $this->nullableFloat($draft['manual_perimeter_m'] ?? null),
            'shape_points' => collect($draft['shape_points'])
                ->map(fn ($point) => [
                    'x' => round((float) ($point['x'] ?? 0), 2),
                    'y' => round((float) ($point['y'] ?? 0), 2),
                ])
                ->values()
                ->all(),
            'spotlights_count' => 0,
            'chandelier_points_count' => 0,
            'pipes_count' => 0,
            'curtain_niches_count' => 0,
            'ventilation_holes_count' => 0,
            'notes' => $this->buildRecognitionRoomNotes($recognition),
        ];

        if ($room) {
            $room->update($payload);
        } else {
            $room = $project->rooms()->create($payload);
        }

        $project->forceFill([
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        return $this->redirectToProject($request, $project, $room)
            ->with('status', 'Черновик комнаты создан из распознавания эскиза.');
    }

    public function uploadSketchSheet(
        Request $request,
        CeilingProject $project,
        CeilingSketchRecognitionService $recognitionService
    ): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $data = $request->validate([
            'sketch_image' => ['required', 'image', 'max:10240'],
        ]);

        if ($project->sketch_image_path && $project->sketch_image_path !== $project->reference_image_path) {
            Storage::disk('public')->delete($project->sketch_image_path);
        }

        $path = $data['sketch_image']->store('ceiling-projects/'.$project->account_id, 'public');

        $project->forceFill([
            'sketch_image_path' => $path,
            'sketch_crop' => null,
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        $statusMessage = 'Лист замера загружен. Выделите одну комнату на фото и запустите OCR по выбранной области.';

        try {
            $inspection = $recognitionService->inspect($project, Storage::disk('public')->path($path));
            $this->saveRecognitionResult($project, $inspection);

            $candidatesCount = count($inspection['candidates'] ?? []);
            if ($candidatesCount > 0) {
                $statusMessage = 'Лист замера загружен. Найдены кандидаты комнат: '.$candidatesCount.'. Выберите нужную область и запустите OCR.';
            }
        } catch (RuntimeException $exception) {
            $this->saveRecognitionResult($project, $this->buildRecognitionState(
                false,
                'Автопоиск комнат не сработал. Область можно выделить вручную.',
                [
                    'stage' => 'inspect',
                    'warnings' => [$exception->getMessage()],
                    'candidates' => [],
                ],
            ));

            $statusMessage = 'Лист замера загружен, но автопоиск комнат не сработал. Область можно выделить вручную.';
        }

        return $this->redirectToProject($request, $project)
            ->with('status', $statusMessage);
    }

    public function saveSketchCrop(Request $request, CeilingProject $project): RedirectResponse
    {
        $this->authorizeProject($request, $project);

        $crop = $this->validateSketchCrop($request);

        $project->forceFill([
            'sketch_crop' => $crop,
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        return $this->redirectToProject($request, $project)
            ->with('status', $crop ? 'Область OCR сохранена.' : 'Область OCR очищена.');
    }

    public function recognizeSketchCrop(
        Request $request,
        CeilingProject $project,
        CeilingSketchRecognitionService $recognitionService
    ): RedirectResponse {
        $this->authorizeProject($request, $project);

        $sketchImagePath = $this->resolveSketchImagePath($project);

        if (!$sketchImagePath || !Storage::disk('public')->exists($sketchImagePath)) {
            return back()->withErrors([
                'sketch_image' => 'Сначала загрузите фото эскиза для распознавания.',
            ]);
        }

        $crop = $this->validateSketchCrop($request) ?? $this->normalizeSketchCrop($project->sketch_crop);

        if (!$crop) {
            return back()->withErrors([
                'sketch_crop' => 'Сначала выделите одну комнату на листе.',
            ]);
        }

        $project->forceFill([
            'sketch_crop' => $crop,
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        try {
            $result = $recognitionService->recognize(
                $project,
                Storage::disk('public')->path($sketchImagePath),
                $crop,
            );
        } catch (RuntimeException $exception) {
            $this->saveRecognitionResult($project, $this->buildRecognitionState(
                false,
                'Не удалось распознать выбранную область.',
                [
                    'stage' => 'recognize',
                    'crop' => $crop,
                    'warnings' => [$exception->getMessage()],
                ],
            ));

            return $this->redirectToProject($request, $project)
                ->with('status', 'Не удалось распознать выбранную область. Подробности показаны в блоке OCR.');
        }

        $this->saveRecognitionResult($project, $result);

        return $this->redirectToProject($request, $project)
            ->with('status', 'Область распознана. Проверьте черновик и при необходимости примените его к комнате.');
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

        $payload = $this->roomPayload($request, $project, $data, $nextSortOrder);
        $payload['derived_panels'] = $this->buildDerivedPanelsForRoom($payload);

        $room = $project->rooms()->create($payload);
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
        $payload = $this->roomPayload($request, $project, $data, $room->sort_order);
        if (($payload['shape_type'] ?? null) === CeilingProjectRoom::SHAPE_RECTANGLE) {
            $payload['shape_points'] = null;
            $payload['corners_count'] = 4;
        }
        $payload['derived_panels'] = $this->buildDerivedPanelsForRoom(array_merge(
            $room->only(['shape_points', 'light_line_shapes', 'production_settings']),
            $payload
        ));

        $room->update($payload);
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
            'feature_shapes_json' => ['nullable', 'string'],
            'light_line_shapes_json' => ['nullable', 'string'],
            'production_settings_json' => ['nullable', 'string'],
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

        $featureShapes = $this->normalizeFeatureShapes(
            json_decode((string) ($data['feature_shapes_json'] ?? '[]'), true)
        );
        $lightLineShapes = $this->normalizeLightLineShapes(
            json_decode((string) ($data['light_line_shapes_json'] ?? '[]'), true)
        );
        $productionSettings = $this->normalizeProductionSettings(
            json_decode((string) ($data['production_settings_json'] ?? '{}'), true)
        );
        $derivedPanels = $this->buildDerivedPanelsForRoom([
            'shape_type' => CeilingProjectRoom::SHAPE_POLYGON,
            'shape_points' => $normalized,
            'light_line_shapes' => $lightLineShapes,
            'production_settings' => $productionSettings,
        ]);

        $room->forceFill([
            'shape_type' => CeilingProjectRoom::SHAPE_POLYGON,
            'shape_points' => $normalized,
            'feature_shapes' => $featureShapes,
            'light_line_shapes' => $lightLineShapes,
            'derived_panels' => $derivedPanels,
            'production_settings' => $productionSettings,
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
        abort_unless(in_array($request->user()?->role, ['admin', 'constructor'], true), 403);
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

    private function buildDerivedPanelsForRoom(CeilingProjectRoom|array $room): array
    {
        $payload = $room instanceof CeilingProjectRoom ? $room->toArray() : $room;
        $points = $this->roomPolygonPoints($payload);
        if ($points === []) {
            return [];
        }

        $lightLineShapes = $this->normalizeLightLineShapes($payload['light_line_shapes'] ?? []);
        $productionSettings = $this->normalizeProductionSettings($payload['production_settings'] ?? []);

        return app(CeilingLightLinePanelSplitter::class)->split(
            $points,
            $lightLineShapes,
            $productionSettings
        );
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array{x: float, y: float}>
     */
    private function roomPolygonPoints(array $payload): array
    {
        $shapeType = (string) ($payload['shape_type'] ?? CeilingProjectRoom::SHAPE_RECTANGLE);
        $points = [];

        if ($shapeType !== CeilingProjectRoom::SHAPE_RECTANGLE && is_array($payload['shape_points'] ?? null)) {
            foreach ($payload['shape_points'] as $point) {
                if (!is_array($point) || !isset($point['x'], $point['y']) || !is_numeric($point['x']) || !is_numeric($point['y'])) {
                    continue;
                }

                $points[] = [
                    'x' => round((float) $point['x'], 2),
                    'y' => round((float) $point['y'], 2),
                ];
            }
        }

        if (count($points) >= 3) {
            return $points;
        }

        $width = isset($payload['width_m']) && is_numeric($payload['width_m']) ? round((float) $payload['width_m'], 2) : 0.0;
        $length = isset($payload['length_m']) && is_numeric($payload['length_m']) ? round((float) $payload['length_m'], 2) : 0.0;

        if ($width <= 0 || $length <= 0) {
            return [];
        }

        return [
            ['x' => 0.0, 'y' => 0.0],
            ['x' => $width, 'y' => 0.0],
            ['x' => $width, 'y' => $length],
            ['x' => 0.0, 'y' => $length],
        ];
    }

    private function normalizeFeatureShapes(mixed $shapes): array
    {
        if (!is_array($shapes)) {
            return [];
        }

        $normalized = [];

        foreach ($shapes as $index => $shape) {
            $item = $this->normalizeFeatureShape($shape, $index);
            if ($item === null) {
                continue;
            }

            $normalized[] = $item;
        }

        return array_values($normalized);
    }

    private function normalizeFeatureShape(mixed $shape, int $index = 0): ?array
    {
        if (!is_array($shape)) {
            return null;
        }

        $kind = trim((string) ($shape['kind'] ?? CeilingProjectRoom::FEATURE_CUTOUT));
        $figure = trim((string) ($shape['figure'] ?? CeilingProjectRoom::FEATURE_RECTANGLE));
        $allowedKinds = array_keys(CeilingProjectRoom::featureKindOptions());
        $allowedFigures = array_keys(CeilingProjectRoom::featureFigureOptions());

        if (!in_array($kind, $allowedKinds, true) || !in_array($figure, $allowedFigures, true)) {
            return null;
        }

        $shapePoints = [];
        if (is_array($shape['shape_points'] ?? null)) {
            foreach ($shape['shape_points'] as $point) {
                if (!is_array($point) || !isset($point['x'], $point['y']) || !is_numeric($point['x']) || !is_numeric($point['y'])) {
                    continue;
                }

                $shapePoints[] = [
                    'x' => round((float) $point['x'], 2),
                    'y' => round((float) $point['y'], 2),
                ];
            }
        }

        $x = isset($shape['x_m']) && is_numeric($shape['x_m']) ? round((float) $shape['x_m'], 2) : null;
        $y = isset($shape['y_m']) && is_numeric($shape['y_m']) ? round((float) $shape['y_m'], 2) : null;
        $width = isset($shape['width_m']) && is_numeric($shape['width_m']) ? round((float) $shape['width_m'], 2) : null;
        $height = isset($shape['height_m']) && is_numeric($shape['height_m']) ? round((float) $shape['height_m'], 2) : null;

        if (count($shapePoints) >= 3) {
            $minX = min(array_column($shapePoints, 'x'));
            $maxX = max(array_column($shapePoints, 'x'));
            $minY = min(array_column($shapePoints, 'y'));
            $maxY = max(array_column($shapePoints, 'y'));

            $x = $x ?? round($minX, 2);
            $y = $y ?? round($minY, 2);
            $width = $width ?? round($maxX - $minX, 2);
            $height = $height ?? round($maxY - $minY, 2);
        }

        if ($x === null || $y === null || $width === null || $height === null || $width <= 0 || $height <= 0) {
            return null;
        }

        $id = trim((string) ($shape['id'] ?? ''));
        if ($id === '') {
            $id = 'feature_'.($index + 1);
        }

        return [
            'id' => $id,
            'kind' => $kind,
            'figure' => $figure,
            'x_m' => $x,
            'y_m' => $y,
            'width_m' => $width,
            'height_m' => $height,
            'shape_points' => count($shapePoints) >= 3 ? $shapePoints : null,
            'source_segment_index' => isset($shape['source_segment_index']) && is_numeric($shape['source_segment_index']) ? (int) $shape['source_segment_index'] : null,
            'source_point_index' => isset($shape['source_point_index']) && is_numeric($shape['source_point_index']) ? (int) $shape['source_point_index'] : null,
            'offset_m' => isset($shape['offset_m']) && is_numeric($shape['offset_m']) ? round((float) $shape['offset_m'], 2) : null,
            'depth_m' => isset($shape['depth_m']) && is_numeric($shape['depth_m']) ? round((float) $shape['depth_m'], 2) : null,
            'radius_m' => isset($shape['radius_m']) && is_numeric($shape['radius_m']) ? round((float) $shape['radius_m'], 2) : null,
            'area_delta_m2' => isset($shape['area_delta_m2']) && is_numeric($shape['area_delta_m2']) ? round((float) $shape['area_delta_m2'], 4) : null,
            'perimeter_delta_m' => isset($shape['perimeter_delta_m']) && is_numeric($shape['perimeter_delta_m']) ? round((float) $shape['perimeter_delta_m'], 4) : null,
            'direction' => in_array(($shape['direction'] ?? null), ['inward', 'outward'], true) ? $shape['direction'] : null,
            'cut_line' => (bool) ($shape['cut_line'] ?? false),
            'label' => $this->trimNullable($shape['label'] ?? null),
        ];
    }

    private function normalizeLightLineShapes(mixed $shapes): array
    {
        if (!is_array($shapes)) {
            return [];
        }

        $normalized = [];

        foreach ($shapes as $index => $shape) {
            $item = $this->normalizeLightLineShape($shape, $index);
            if ($item === null) {
                continue;
            }

            $normalized[] = $item;
        }

        return array_values($normalized);
    }

    private function normalizeLightLineShape(mixed $shape, int $index = 0): ?array
    {
        if (!is_array($shape)) {
            return null;
        }

        $points = [];
        if (is_array($shape['points'] ?? null)) {
            foreach ($shape['points'] as $point) {
                if (!is_array($point) || !isset($point['x'], $point['y']) || !is_numeric($point['x']) || !is_numeric($point['y'])) {
                    continue;
                }

                $points[] = [
                    'x' => round((float) $point['x'], 2),
                    'y' => round((float) $point['y'], 2),
                ];
            }
        }

        if (count($points) < 2) {
            return null;
        }

        $width = isset($shape['width_m']) && is_numeric($shape['width_m']) ? round((float) $shape['width_m'], 3) : null;
        if ($width === null || $width <= 0) {
            $width = 0.05;
        }

        $id = trim((string) ($shape['id'] ?? ''));
        if ($id === '') {
            $id = 'light_line_'.($index + 1);
        }

        $template = trim((string) ($shape['template'] ?? 'custom'));
        if (!in_array($template, ['custom', 'rectangle', 'cross', 'circle'], true)) {
            $template = 'custom';
        }

        return [
            'id' => $id,
            'label' => $this->trimNullable($shape['label'] ?? null),
            'width_m' => $width,
            'closed' => (bool) ($shape['closed'] ?? false),
            'template' => $template,
            'points' => $points,
        ];
    }

    private function normalizeProductionSettings(mixed $settings): array
    {
        if (!is_array($settings)) {
            $settings = [];
        }

        $texture = trim((string) ($settings['texture'] ?? 'matte'));
        if (!in_array($texture, ['matte', 'satin', 'glossy', 'fabric', 'custom'], true)) {
            $texture = 'matte';
        }

        $harpoonType = trim((string) ($settings['harpoon_type'] ?? 'standard'));
        if (!in_array($harpoonType, ['standard', 'separate', 'none'], true)) {
            $harpoonType = 'standard';
        }

        $orientationMode = trim((string) ($settings['orientation_mode'] ?? 'parallel_segment'));
        if (!in_array($orientationMode, ['parallel_segment', 'perpendicular_segment', 'center_segment', 'center_room'], true)) {
            $orientationMode = 'parallel_segment';
        }

        return [
            'texture' => $texture,
            'roll_width_cm' => isset($settings['roll_width_cm']) && is_numeric($settings['roll_width_cm']) ? max(50, min(1000, (int) round((float) $settings['roll_width_cm']))) : 320,
            'harpoon_type' => $harpoonType,
            'same_roll_required' => (bool) ($settings['same_roll_required'] ?? false),
            'special_cutting' => (bool) ($settings['special_cutting'] ?? false),
            'seam_enabled' => (bool) ($settings['seam_enabled'] ?? false),
            'shrink_x_percent' => isset($settings['shrink_x_percent']) && is_numeric($settings['shrink_x_percent']) ? round((float) $settings['shrink_x_percent'], 2) : 7.0,
            'shrink_y_percent' => isset($settings['shrink_y_percent']) && is_numeric($settings['shrink_y_percent']) ? round((float) $settings['shrink_y_percent'], 2) : 7.0,
            'orientation_mode' => $orientationMode,
            'orientation_segment_index' => isset($settings['orientation_segment_index']) && is_numeric($settings['orientation_segment_index']) ? max(0, (int) $settings['orientation_segment_index']) : 0,
            'orientation_offset_m' => isset($settings['orientation_offset_m']) && is_numeric($settings['orientation_offset_m']) ? round((float) $settings['orientation_offset_m'], 2) : 0.0,
            'seam_offset_m' => isset($settings['seam_offset_m']) && is_numeric($settings['seam_offset_m']) ? round((float) $settings['seam_offset_m'], 2) : 0.0,
            'comment' => $this->trimNullable($settings['comment'] ?? null),
        ];
    }

    private function validateSketchCrop(Request $request, bool $allowEmpty = true): ?array
    {
        $data = $request->validate([
            'crop_x' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'crop_y' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'crop_width' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'crop_height' => ['nullable', 'numeric', 'min:0', 'max:1'],
        ]);

        $providedValues = array_filter([
            $data['crop_x'] ?? null,
            $data['crop_y'] ?? null,
            $data['crop_width'] ?? null,
            $data['crop_height'] ?? null,
        ], static fn ($value) => $value !== null && $value !== '');

        if ($providedValues === []) {
            if ($allowEmpty) {
                return null;
            }

            throw ValidationException::withMessages([
                'sketch_crop' => 'Сначала выделите одну комнату на листе.',
            ]);
        }

        if (
            !isset($data['crop_x'], $data['crop_y'], $data['crop_width'], $data['crop_height'])
            || $data['crop_x'] === ''
            || $data['crop_y'] === ''
            || $data['crop_width'] === ''
            || $data['crop_height'] === ''
        ) {
            throw ValidationException::withMessages([
                'sketch_crop' => 'Область OCR должна быть выделена целиком.',
            ]);
        }

        return $this->normalizeSketchCrop([
            'x' => $data['crop_x'],
            'y' => $data['crop_y'],
            'width' => $data['crop_width'],
            'height' => $data['crop_height'],
        ]);
    }

    private function normalizeSketchCrop(mixed $crop): ?array
    {
        if (!is_array($crop)) {
            return null;
        }

        $x = isset($crop['x']) && is_numeric($crop['x']) ? (float) $crop['x'] : null;
        $y = isset($crop['y']) && is_numeric($crop['y']) ? (float) $crop['y'] : null;
        $width = isset($crop['width']) && is_numeric($crop['width']) ? (float) $crop['width'] : null;
        $height = isset($crop['height']) && is_numeric($crop['height']) ? (float) $crop['height'] : null;

        if ($x === null || $y === null || $width === null || $height === null) {
            return null;
        }

        $x = max(0.0, min(0.98, $x));
        $y = max(0.0, min(0.98, $y));
        $width = max(0.0, min(1.0 - $x, $width));
        $height = max(0.0, min(1.0 - $y, $height));

        if ($width < 0.02 || $height < 0.02) {
            return null;
        }

        return [
            'x' => round($x, 5),
            'y' => round($y, 5),
            'width' => round($width, 5),
            'height' => round($height, 5),
        ];
    }

    private function buildRecognitionState(bool $success, string $message, array $extra = []): array
    {
        return array_merge([
            'success' => $success,
            'message' => trim($message) !== '' ? trim($message) : 'Не удалось распознать эскиз.',
            'warnings' => [],
            'shape' => ['type' => 'unknown'],
            'stage' => 'recognize',
            'candidates' => [],
        ], $extra);
    }

    private function buildShowViewData(Request $request, CeilingProject $project, CeilingProjectCalculator $calculator, string $viewMode): array
    {
        $project->load(['deal.contact', 'rooms', 'measurement']);
        $project->load('rooms.elements');

        $summary = $calculator->calculateProject($project);
        $recognition = $this->loadRecognitionResult($project);
        $selectedRoomId = (int) $request->query('room', 0);
        $selectedRoom = $selectedRoomId > 0
            ? $project->rooms->firstWhere('id', $selectedRoomId)
            : $project->rooms->first();

        return [
            'project' => $project,
            'summary' => $summary,
            'selectedRoom' => $selectedRoom,
            'viewMode' => $viewMode,
            'sketchRecognition' => $recognition,
            'sketchCrop' => $this->normalizeSketchCrop($project->sketch_crop)
                ?? $this->normalizeSketchCrop($recognition['crop'] ?? null),
            'availableDeals' => $this->availableDeals($request->user()->account_id),
            'measurements' => $this->availableMeasurements($request->user()->account_id),
            'statusOptions' => CeilingProject::statusOptions(),
            'materialOptions' => CeilingProject::materialOptions(),
            'textureOptions' => CeilingProject::textureOptions(),
            'shapeOptions' => CeilingProjectRoom::shapeOptions(),
            'featureKindOptions' => CeilingProjectRoom::featureKindOptions(),
            'featureFigureOptions' => CeilingProjectRoom::featureFigureOptions(),
            'elementTypeOptions' => CeilingProjectRoomElement::typeOptions(),
            'elementPlacementOptions' => CeilingProjectRoomElement::placementOptions(),
            'sketchImageUrl' => $this->resolveSketchImagePath($project)
                ? route('ceiling-projects.sketch-image.show', $project)
                : null,
            'sketchImageSharedWithReference' => !$project->sketch_image_path && $this->hasLegacySharedSketchImage($project),
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

    private function recognitionStoragePath(CeilingProject $project): string
    {
        return 'ceiling-projects/recognition/'.$project->account_id.'/project-'.$project->id.'.json';
    }

    private function resolveSketchImagePath(CeilingProject $project): ?string
    {
        $path = $project->sketch_image_path;

        if (!$path && $this->hasLegacySharedSketchImage($project)) {
            $path = $project->reference_image_path;
        }

        return is_string($path) && trim($path) !== '' ? $path : null;
    }

    private function hasLegacySharedSketchImage(CeilingProject $project): bool
    {
        if (!$project->reference_image_path || $project->sketch_image_path) {
            return false;
        }

        if (Storage::disk('local')->exists($this->recognitionStoragePath($project))) {
            return true;
        }

        $legacyPayload = $project->getAttribute('sketch_recognition');

        return is_array($legacyPayload) && $legacyPayload !== [];
    }

    private function saveRecognitionResult(CeilingProject $project, array $recognition): void
    {
        $payload = $recognition;
        $payload['recognized_at'] = now()->toIso8601String();

        Storage::disk('local')->put(
            $this->recognitionStoragePath($project),
            json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
        );

        $project->forceFill([
            'updated_by_user_id' => request()->user()?->id ?? $project->updated_by_user_id,
            'last_calculated_at' => now(),
        ])->save();
    }

    private function loadRecognitionResult(CeilingProject $project): ?array
    {
        $path = $this->recognitionStoragePath($project);

        if (!Storage::disk('local')->exists($path)) {
            $legacyPayload = $project->getAttribute('sketch_recognition');
            return is_array($legacyPayload) ? $legacyPayload : null;
        }

        $payload = json_decode((string) Storage::disk('local')->get($path), true);

        return is_array($payload) ? $payload : null;
    }

    private function buildRecognitionFailure(string $message): array
    {
        return [
            'success' => false,
            'message' => trim($message) !== '' ? trim($message) : 'Не удалось распознать эскиз.',
            'warnings' => [],
            'shape' => ['type' => 'unknown'],
        ];
    }

    private function upsertRecognitionRoom(Request $request, CeilingProject $project, array $recognition): ?CeilingProjectRoom
    {
        $draft = $recognition['room_draft'] ?? null;

        if (!is_array($draft) || !is_array($draft['shape_points'] ?? null) || count($draft['shape_points']) < 3) {
            return null;
        }

        $room = $this->resolveRecognitionTargetRoom($request, $project);

        $payload = [
            'account_id' => $request->user()->account_id,
            'ceiling_project_id' => $project->id,
            'sort_order' => $room?->sort_order ?? ((int) $project->rooms()->max('sort_order') + 1),
            'name' => trim((string) ($draft['name'] ?? $room?->name ?? 'Черновик по эскизу')),
            'shape_type' => CeilingProjectRoom::SHAPE_POLYGON,
            'width_m' => $this->nullableFloat($draft['width_m'] ?? null),
            'length_m' => $this->nullableFloat($draft['length_m'] ?? null),
            'height_m' => $this->nullableFloat($draft['height_m'] ?? $room?->height_m),
            'corners_count' => count($draft['shape_points']),
            'manual_area_m2' => $this->nullableFloat($draft['manual_area_m2'] ?? null),
            'manual_perimeter_m' => $this->nullableFloat($draft['manual_perimeter_m'] ?? null),
            'shape_points' => collect($draft['shape_points'])
                ->map(fn ($point) => [
                    'x' => round((float) ($point['x'] ?? 0), 2),
                    'y' => round((float) ($point['y'] ?? 0), 2),
                ])
                ->values()
                ->all(),
            'spotlights_count' => $room?->spotlights_count ?? 0,
            'chandelier_points_count' => $room?->chandelier_points_count ?? 0,
            'pipes_count' => $room?->pipes_count ?? 0,
            'curtain_niches_count' => $room?->curtain_niches_count ?? 0,
            'ventilation_holes_count' => $room?->ventilation_holes_count ?? 0,
            'notes' => $this->mergeRecognitionNotes($room?->notes, $recognition),
        ];

        if ($room) {
            $room->update($payload);
        } else {
            $room = $project->rooms()->create($payload);
        }

        $project->forceFill([
            'updated_by_user_id' => $request->user()->id,
            'last_calculated_at' => now(),
        ])->save();

        return $room->fresh();
    }

    private function resolveRecognitionTargetRoom(Request $request, CeilingProject $project): ?CeilingProjectRoom
    {
        $requestedRoomId = (int) $request->input('room', $request->query('room', 0));
        if ($requestedRoomId > 0) {
            $selectedRoom = $project->rooms()->whereKey($requestedRoomId)->first();
            if ($selectedRoom) {
                return $selectedRoom;
            }
        }

        $recognizedRoom = $project->rooms()
            ->where('notes', 'like', '[sketch-recognition]%')
            ->orderByDesc('id')
            ->first();

        if ($recognizedRoom) {
            return $recognizedRoom;
        }

        if ($project->rooms()->count() === 1) {
            return $project->rooms()->first();
        }

        return null;
    }

    private function mergeRecognitionNotes(?string $existingNotes, array $recognition): string
    {
        $recognitionNotes = $this->buildRecognitionRoomNotes($recognition);
        $existingNotes = trim((string) $existingNotes);

        if ($existingNotes === '') {
            return $recognitionNotes;
        }

        if (str_starts_with($existingNotes, '[sketch-recognition]')) {
            return $recognitionNotes;
        }

        return trim($existingNotes)."\n\n".$recognitionNotes;
    }

    private function buildRecognitionRoomNotes(array $recognition): string
    {
        $measurements = $recognition['measurements'] ?? [];
        $segments = collect($recognition['segments'] ?? [])
            ->filter(fn ($segment) => is_array($segment))
            ->map(function (array $segment) {
                $label = trim((string) ($segment['label'] ?? ''));
                $value = $segment['ocr_value_cm'] ?? $segment['resolved_value_cm'] ?? $segment['approx_value_cm'] ?? null;
                $suffix = isset($segment['ocr_value_cm'])
                    ? 'ocr'
                    : (isset($segment['resolved_value_cm']) ? 'mix' : 'draft');

                if ($label === '' || !is_numeric($value)) {
                    return null;
                }

                return sprintf('%s: %s см (%s)', $label, (string) (int) round((float) $value), $suffix);
            })
            ->filter()
            ->values();
        $rawText = trim((string) ($recognition['text'] ?? ''));
        $warnings = collect($recognition['warnings'] ?? [])
            ->filter(fn ($warning) => trim((string) $warning) !== '')
            ->map(fn ($warning) => '- '.trim((string) $warning))
            ->implode("\n");

        $lines = [
            '[sketch-recognition]',
            'OCR confidence: '.($recognition['confidence'] ?? 'n/a'),
        ];

        if (!empty($measurements['width_cm']) || !empty($measurements['length_cm'])) {
            $lines[] = 'Размеры: '
                .trim((string) ($measurements['width_cm'] ?? '—')).' x '
                .trim((string) ($measurements['length_cm'] ?? '—')).' см';
        }

        if (!empty($measurements['area_m2'])) {
            $lines[] = 'Площадь OCR: '.$measurements['area_m2'].' м2';
        }

        if (!empty($measurements['perimeter_m'])) {
            $lines[] = 'Периметр OCR: '.$measurements['perimeter_m'].' м';
        }

        if ($segments->isNotEmpty()) {
            $lines[] = 'Стороны OCR: '.$segments->implode(', ');
        }

        if ($rawText !== '') {
            $lines[] = 'OCR текст: '.$rawText;
        }

        if ($warnings !== '') {
            $lines[] = 'Предупреждения:'."\n".$warnings;
        }

        return implode("\n", $lines);
    }

    private function nullableFloat(mixed $value): ?float
    {
        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private function trimNullable(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}
