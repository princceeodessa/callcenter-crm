<?php

namespace App\Http\Controllers;

use App\Models\NonClosure;
use App\Models\NonClosureSheetRowActivity;
use App\Models\NonClosureSheetRowState;
use App\Models\NonClosureWorkbook;
use App\Models\NonClosureWorkbookSheet;
use App\Models\NonClosureWorkspace;
use App\Models\Task;
use App\Models\User;
use App\Services\NonClosureImportService;
use App\Services\NonClosureWorkbookImportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class NonClosureController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $workspace = $this->workspaceForUser($request);
        $canManage = $this->canManageDocuments($user);
        $canContribute = $this->canContributeDocuments($user);
        $viewScope = $this->normalizeScope((string) $request->query('scope', $this->defaultScopeFor($user, $canManage)));
        $ownerFilterId = (int) ($request->query('owner_id') ?? 0);

        $activeUsers = $this->activeUsersForAccount($user);
        $workbooks = $this->resolveVisibleWorkbooks($workspace, $user, $canManage, $viewScope, $ownerFilterId);
        $ownerStats = $workbooks
            ->groupBy(fn (NonClosureWorkbook $workbook) => (int) ($workbook->owner_user_id ?: 0))
            ->map(fn (Collection $items) => $items->count())
            ->all();

        $selectedWorkbookId = (int) ($request->query('workbook') ?? 0);
        $selectedWorkbook = $workbooks->firstWhere('id', $selectedWorkbookId) ?: $workbooks->first();

        $sheets = collect();
        if ($selectedWorkbook) {
            $sheets = $this->resolveWorkbookSheets($selectedWorkbook, $user, $canManage);
        }

        return view('nonclosures.index', [
            'workspace' => $workspace,
            'activeUsers' => $activeUsers,
            'workbooks' => $workbooks,
            'selectedWorkbook' => $selectedWorkbook,
            'selectedWorkbookSummary' => $this->workbookSummaryForVisibleSheets($selectedWorkbook, $sheets),
            'sheets' => $sheets,
            'sheetCategories' => NonClosureWorkbookSheet::categoryOptions(),
            'canManageDocuments' => $canManage,
            'canContributeDocuments' => $canContribute,
            'viewScope' => $viewScope,
            'ownerFilterId' => $ownerFilterId,
            'ownerStats' => $ownerStats,
        ]);
    }

    public function showSheet(Request $request, NonClosureWorkbookSheet $sheet)
    {
        $user = $request->user();
        $workspace = $this->workspaceForUser($request);
        $canManage = $this->canManageDocuments($user);
        $activeUsers = $this->activeUsersForAccount($user);

        $sheet = $this->resolveSheetForView($sheet, $user, $canManage);
        $workbook = $sheet->workbook;

        if (!$workbook || (int) $workbook->workspace_id !== (int) $workspace->id) {
            abort(404);
        }

        $siblingSheets = $this->resolveWorkbookSheets($workbook, $user, $canManage);
        $sheetRows = collect($sheet->rows ?? [])->values();
        $sheetHeader = collect($sheet->header ?? [])->values();
        $sheetCategoryLabel = NonClosureWorkbookSheet::categoryOptions()[$sheet->category] ?? $sheet->category;
        $sheetSharedIds = $sheet->sharedUsers->pluck('id')->map(fn ($id) => (int) $id)->all();
        $backQuery = $this->catalogQueryParams($request, [
            'workbook' => $workbook->id,
        ]);

        $rowStates = $sheet->rowStates()
            ->with(['assignedTo:id,name', 'updatedBy:id,name'])
            ->get()
            ->keyBy('row_index');

        $rowActivities = $sheet->rowActivities()
            ->with('actor:id,name')
            ->limit(500)
            ->get()
            ->groupBy('row_index')
            ->map(fn (Collection $items) => $items->take(8)->values());

        $rowTaskStats = $this->buildSheetTaskStats($user, $sheet);

        return view('nonclosures.sheet', [
            'workspace' => $workspace,
            'workbook' => $workbook,
            'sheet' => $sheet,
            'sheetRows' => $sheetRows,
            'sheetHeader' => $sheetHeader,
            'sheetCategories' => NonClosureWorkbookSheet::categoryOptions(),
            'sheetCategoryLabel' => $sheetCategoryLabel,
            'siblingSheets' => $siblingSheets,
            'activeUsers' => $activeUsers,
            'selectedSheetSharedIds' => $sheetSharedIds,
            'canManageDocuments' => $canManage,
            'canContributeDocuments' => $this->canContributeDocuments($user),
            'backQuery' => $backQuery,
            'rowStatusOptions' => NonClosureSheetRowState::statusOptions(),
            'rowStatusToneMap' => NonClosureSheetRowState::statusToneMap(),
            'sheetRowStates' => $rowStates,
            'sheetRowActivities' => $rowActivities,
            'sheetRowTaskStats' => $rowTaskStats,
        ]);
    }

    public function updateRowState(Request $request, NonClosureWorkbookSheet $sheet, int $rowIndex)
    {
        $user = $request->user();
        $sheet = $this->resolveSheetForView($sheet, $user, $this->canManageDocuments($user));
        $row = $this->resolveSheetRow($sheet, $rowIndex);

        $allowedUserIds = $this->allowedUserIdsForAccount($user);
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(NonClosureSheetRowState::statusOptions()))],
            'comment' => ['nullable', 'string', 'max:5000'],
            'assigned_user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'row_values' => ['nullable', 'array'],
            'row_values.*' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $status = (string) ($data['status'] ?? NonClosureSheetRowState::STATUS_NEW);
        $comment = $this->nullIfBlank($data['comment'] ?? null);
        $assignedUserId = (int) ($data['assigned_user_id'] ?? 0);
        $assignedUserId = $assignedUserId > 0 ? $assignedUserId : null;
        $updatedRow = $this->normalizeEditedRow(
            $sheet,
            is_array($data['row_values'] ?? null) ? $data['row_values'] : [],
            $row
        );

        $state = NonClosureSheetRowState::query()->firstOrNew([
            'account_id' => $user->account_id,
            'workbook_sheet_id' => $sheet->id,
            'row_index' => $rowIndex,
        ]);

        $originalStatus = $state->exists ? (string) $state->status : NonClosureSheetRowState::STATUS_NEW;
        $originalComment = $state->exists ? $this->nullIfBlank($state->comment) : null;
        $originalAssignedUserId = $state->exists ? (int) ($state->assigned_user_id ?: 0) : 0;
        $rowChanged = $updatedRow !== $this->normalizeEditedRow($sheet, $row, $row);

        if (
            !$state->exists
            && $status === NonClosureSheetRowState::STATUS_NEW
            && $comment === null
            && $assignedUserId === null
            && !$rowChanged
        ) {
            return $this->redirectToSheetRow($sheet, $rowIndex, $request)
                ->with('status', 'Для строки пока не задано состояние.');
        }

        $state->fill([
            'status' => $status,
            'comment' => $comment,
            'assigned_user_id' => $assignedUserId,
            'updated_by_user_id' => $user->id,
            'meta' => [
                'row_preview' => $this->rowPreview($row),
            ],
        ]);

        $newAssignedUserId = (int) ($state->assigned_user_id ?: 0);
        $hasChanges = !$state->exists
            || $originalStatus !== $status
            || $originalComment !== $comment
            || $originalAssignedUserId !== $newAssignedUserId
            || $rowChanged;

        if (!$hasChanges) {
            return $this->redirectToSheetRow($sheet, $rowIndex, $request)
                ->with('status', 'Изменений по строке не было.');
        }

        DB::transaction(function () use (
            $state,
            $sheet,
            $rowIndex,
            $user,
            $originalStatus,
            $status,
            $originalComment,
            $comment,
            $originalAssignedUserId,
            $newAssignedUserId,
            $rowChanged,
            $row,
            $updatedRow
        ) {
            if ($rowChanged) {
                $rows = collect($sheet->rows ?? [])->values()->all();
                $rows[$rowIndex - 1] = $updatedRow;
                $this->persistSheetMatrix($sheet, (array) ($sheet->header ?? []), $rows);
                $this->syncDocumentTaskRowPreview($sheet, $rowIndex, $updatedRow);
            }

            $state->save();

            $labels = NonClosureSheetRowState::statusOptions();
            $changes = [];

            if ($originalStatus !== $status) {
                $changes[] = sprintf(
                    'Статус: %s → %s',
                    $labels[$originalStatus] ?? $originalStatus,
                    $labels[$status] ?? $status
                );
            }

            if ($originalAssignedUserId !== $newAssignedUserId) {
                $oldName = $originalAssignedUserId > 0
                    ? (User::query()->whereKey($originalAssignedUserId)->value('name') ?: 'не назначен')
                    : 'не назначен';
                $newName = $newAssignedUserId > 0
                    ? ($state->assignedTo?->name ?: User::query()->whereKey($newAssignedUserId)->value('name') ?: 'не назначен')
                    : 'не назначен';
                $changes[] = sprintf('Ответственный: %s → %s', $oldName, $newName);
            }

            if ($originalComment !== $comment) {
                $changes[] = $comment !== null ? 'Комментарий обновлён' : 'Комментарий очищен';
            }

            if ($rowChanged) {
                $changes[] = 'Данные строки обновлены';
            }

            NonClosureSheetRowActivity::create([
                'account_id' => $user->account_id,
                'workbook_sheet_id' => $sheet->id,
                'row_state_id' => $state->id,
                'row_index' => $rowIndex,
                'actor_user_id' => $user->id,
                'type' => NonClosureSheetRowActivity::TYPE_STATE_UPDATED,
                'body' => implode(' • ', $changes),
                'payload' => [
                    'status' => [
                        'old' => $originalStatus,
                        'new' => $status,
                    ],
                    'comment' => [
                        'old' => $originalComment,
                        'new' => $comment,
                    ],
                    'assigned_user_id' => [
                        'old' => $originalAssignedUserId ?: null,
                        'new' => $newAssignedUserId ?: null,
                    ],
                    'row_values' => $rowChanged ? [
                        'old' => $row,
                        'new' => $updatedRow,
                    ] : null,
                ],
            ]);
        });

        return $this->redirectToSheetRow($sheet, $rowIndex, $request)
            ->with('status', 'Состояние строки обновлено.');
    }

    public function storeRow(Request $request, NonClosureWorkbookSheet $sheet)
    {
        $user = $request->user();
        $sheet = $this->resolveSheetForView($sheet, $user, $this->canManageDocuments($user));

        $allowedUserIds = $this->allowedUserIdsForAccount($user);
        $data = $request->validate([
            'position' => ['nullable', 'integer', 'min:1'],
            'status' => ['nullable', Rule::in(array_keys(NonClosureSheetRowState::statusOptions()))],
            'comment' => ['nullable', 'string', 'max:5000'],
            'assigned_user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'row_values' => ['nullable', 'array'],
            'row_values.*' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $rows = collect($sheet->rows ?? [])->values()->all();
        $position = max(1, min((int) ($data['position'] ?? (count($rows) + 1)), count($rows) + 1));
        $newRow = $this->normalizeRowValuesForColumnCount(
            is_array($data['row_values'] ?? null) ? $data['row_values'] : [],
            max(1, count((array) ($sheet->header ?? [])), (int) $sheet->column_count)
        );

        $status = (string) ($data['status'] ?? NonClosureSheetRowState::STATUS_NEW);
        $comment = $this->nullIfBlank($data['comment'] ?? null);
        $assignedUserId = (int) ($data['assigned_user_id'] ?? 0);
        $assignedUserId = $assignedUserId > 0 ? $assignedUserId : null;

        DB::transaction(function () use (
            $sheet,
            $rows,
            $position,
            $newRow,
            $status,
            $comment,
            $assignedUserId,
            $user
        ) {
            $this->shiftSheetRowRelations($sheet, $position, 1);

            array_splice($rows, $position - 1, 0, [$newRow]);
            $this->persistSheetMatrix($sheet, (array) ($sheet->header ?? []), $rows);

            $state = null;
            if (
                $status !== NonClosureSheetRowState::STATUS_NEW
                || $comment !== null
                || $assignedUserId !== null
            ) {
                $state = NonClosureSheetRowState::create([
                    'account_id' => $user->account_id,
                    'workbook_sheet_id' => $sheet->id,
                    'row_index' => $position,
                    'status' => $status,
                    'comment' => $comment,
                    'assigned_user_id' => $assignedUserId,
                    'updated_by_user_id' => $user->id,
                    'meta' => [
                        'row_preview' => $this->rowPreview($newRow),
                    ],
                ]);
            }

            NonClosureSheetRowActivity::create([
                'account_id' => $user->account_id,
                'workbook_sheet_id' => $sheet->id,
                'row_state_id' => $state?->id,
                'row_index' => $position,
                'actor_user_id' => $user->id,
                'type' => 'row_created',
                'body' => 'Строка добавлена',
                'payload' => [
                    'row_values' => $newRow,
                    'row_preview' => $this->rowPreview($newRow),
                ],
            ]);
        });

        return $this->redirectToSheetRow($sheet, $position, $request)
            ->with('status', 'Строка добавлена.');
    }

    public function destroyRow(Request $request, NonClosureWorkbookSheet $sheet, int $rowIndex)
    {
        $user = $request->user();
        $sheet = $this->resolveSheetForView($sheet, $user, $this->canManageDocuments($user));
        $row = $this->resolveSheetRow($sheet, $rowIndex);
        $rows = collect($sheet->rows ?? [])->values()->all();

        DB::transaction(function () use ($sheet, $rowIndex, $row, $rows, $user) {
            $this->retireDeletedRowTasks($sheet, $rowIndex, $row);

            NonClosureSheetRowActivity::query()
                ->where('account_id', $sheet->account_id)
                ->where('workbook_sheet_id', $sheet->id)
                ->where('row_index', $rowIndex)
                ->delete();

            NonClosureSheetRowState::query()
                ->where('account_id', $sheet->account_id)
                ->where('workbook_sheet_id', $sheet->id)
                ->where('row_index', $rowIndex)
                ->delete();

            array_splice($rows, $rowIndex - 1, 1);
            $this->shiftSheetRowRelations($sheet, $rowIndex + 1, -1);
            $this->persistSheetMatrix($sheet, (array) ($sheet->header ?? []), $rows);

            NonClosureSheetRowActivity::create([
                'account_id' => $user->account_id,
                'workbook_sheet_id' => $sheet->id,
                'row_state_id' => null,
                'row_index' => 0,
                'actor_user_id' => $user->id,
                'type' => 'row_deleted',
                'body' => 'Строка удалена',
                'payload' => [
                    'deleted_row_index' => $rowIndex,
                    'row_preview' => $this->rowPreview($row),
                    'row_values' => $row,
                ],
            ]);
        });

        return redirect()
            ->route('nonclosures.sheets.show', array_merge(
                ['sheet' => $sheet->id],
                $this->catalogQueryParams($request, ['workbook' => $sheet->workbook_id])
            ))
            ->with('status', 'Строка удалена.');
    }

    public function storeColumn(Request $request, NonClosureWorkbookSheet $sheet)
    {
        $user = $request->user();
        $sheet = $this->resolveSheetForView($sheet, $user, $this->canManageDocuments($user));

        $data = $request->validate([
            'label' => ['nullable', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $header = array_values((array) ($sheet->header ?? []));
        $header[] = $this->normalizeColumnLabel($data['label'] ?? null, count($header));

        $rows = collect($sheet->rows ?? [])
            ->map(function ($row) {
                $cells = array_values((array) $row);
                $cells[] = '';

                return $cells;
            })
            ->values()
            ->all();

        $this->persistSheetMatrix($sheet, $header, $rows);

        return redirect()
            ->route('nonclosures.sheets.show', array_merge(
                ['sheet' => $sheet->id],
                $this->catalogQueryParams($request, ['workbook' => $sheet->workbook_id])
            ))
            ->with('status', 'Столбец добавлен.');
    }

    public function updateColumn(Request $request, NonClosureWorkbookSheet $sheet, int $columnIndex)
    {
        $user = $request->user();
        $sheet = $this->resolveSheetForView($sheet, $user, $this->canManageDocuments($user));

        $data = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $header = array_values((array) ($sheet->header ?? []));
        abort_if($columnIndex < 1 || $columnIndex > count($header), 422, 'Столбец таблицы не найден.');

        $header[$columnIndex - 1] = $this->normalizeColumnLabel($data['label'] ?? null, $columnIndex - 1);
        $this->persistSheetMatrix($sheet, $header, (array) ($sheet->rows ?? []));

        return redirect()
            ->route('nonclosures.sheets.show', array_merge(
                ['sheet' => $sheet->id],
                $this->catalogQueryParams($request, ['workbook' => $sheet->workbook_id])
            ))
            ->with('status', 'Название столбца обновлено.');
    }

    public function destroyColumn(Request $request, NonClosureWorkbookSheet $sheet, int $columnIndex)
    {
        $user = $request->user();
        $sheet = $this->resolveSheetForView($sheet, $user, $this->canManageDocuments($user));

        $header = array_values((array) ($sheet->header ?? []));
        abort_if($columnIndex < 1 || $columnIndex > count($header), 422, 'Столбец таблицы не найден.');
        abort_if(count($header) <= 1, 422, 'Нельзя удалить последний столбец таблицы.');

        array_splice($header, $columnIndex - 1, 1);
        $rows = collect($sheet->rows ?? [])
            ->map(function ($row) use ($columnIndex) {
                $cells = array_values((array) $row);
                array_splice($cells, $columnIndex - 1, 1);

                return $cells;
            })
            ->values()
            ->all();

        $this->persistSheetMatrix($sheet, $header, $rows);

        return redirect()
            ->route('nonclosures.sheets.show', array_merge(
                ['sheet' => $sheet->id],
                $this->catalogQueryParams($request, ['workbook' => $sheet->workbook_id])
            ))
            ->with('status', 'Столбец удалён.');
    }

    public function updateWorkspace(Request $request)
    {
        $this->ensureDocumentContributionAccess($request);

        $workspace = $this->workspaceForUser($request);
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
            'document_html' => ['nullable', 'string'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $workspace->fill([
            'title' => trim((string) $data['title']),
            'document_html' => trim((string) ($data['document_html'] ?? '')) ?: null,
            'updated_by_user_id' => (int) $request->user()->id,
        ]);
        $workspace->save();

        return redirect()
            ->route('nonclosures.index', $this->catalogQueryParams($request))
            ->with('status', 'Документ сохранён.');
    }

    public function importWorkbook(Request $request, NonClosureWorkbookImportService $service)
    {
        $this->ensureDocumentContributionAccess($request);

        $data = $request->validate([
            'title' => ['nullable', 'string', 'max:255'],
            'file' => ['required', 'file', 'max:20480', 'mimes:xlsx'],
        ]);

        $workspace = $this->workspaceForUser($request);
        $workbook = $service->importUploadedWorkbook(
            $request->file('file'),
            $workspace,
            $request->user(),
            $data['title'] ?? null
        );

        return redirect()->route('nonclosures.index', [
            'scope' => 'all',
            'workbook' => $workbook->id,
        ])->with('status', sprintf(
            'Книга "%s" импортирована: %d листов.',
            $workbook->title,
            (int) $workbook->sheets()->count()
        ));
    }

    public function updateWorkbookAccess(Request $request, NonClosureWorkbook $workbook)
    {
        $this->ensureDocumentManagementAccess($request);
        $this->authorizeWorkbook($request, $workbook);

        $allowedUserIds = $this->allowedUserIdsForAccount($request->user());
        $data = $request->validate([
            'owner_user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'redirect_sheet_id' => ['nullable', 'integer'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $workbook->owner_user_id = ($data['owner_user_id'] ?? null) ?: null;
        $workbook->save();

        $redirectSheetId = (int) ($data['redirect_sheet_id'] ?? 0);
        if ($redirectSheetId > 0) {
            $sheet = NonClosureWorkbookSheet::query()
                ->where('workbook_id', $workbook->id)
                ->whereKey($redirectSheetId)
                ->first();

            if ($sheet) {
                return redirect()
                    ->route('nonclosures.sheets.show', array_merge(
                        ['sheet' => $sheet->id],
                        $this->catalogQueryParams($request, ['workbook' => $workbook->id])
                    ))
                    ->with('status', 'Владелец книги обновлён.');
            }
        }

        return redirect()
            ->route('nonclosures.index', $this->catalogQueryParams($request, ['workbook' => $workbook->id]))
            ->with('status', 'Владелец книги обновлён.');
    }

    public function updateSheetAccess(Request $request, NonClosureWorkbookSheet $sheet)
    {
        $this->ensureDocumentManagementAccess($request);
        $this->authorizeSheet($request, $sheet);

        $allowedUserIds = $this->allowedUserIdsForAccount($request->user());
        $data = $request->validate([
            'owner_user_id' => ['nullable', 'integer', Rule::in($allowedUserIds)],
            'shared_user_ids' => ['nullable', 'array'],
            'shared_user_ids.*' => ['integer', Rule::in($allowedUserIds)],
            'redirect_to_sheet' => ['nullable', 'boolean'],
            'scope' => ['nullable', 'string'],
            'owner_id' => ['nullable', 'integer'],
            'workbook' => ['nullable', 'integer'],
        ]);

        $ownerUserId = ($data['owner_user_id'] ?? null) ?: null;
        $sharedUserIds = collect($data['shared_user_ids'] ?? [])
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->reject(fn ($id) => $id === (int) $ownerUserId)
            ->values();

        $sheet->owner_user_id = $ownerUserId;
        $sheet->save();
        $sheet->sharedUsers()->sync(
            $sharedUserIds->mapWithKeys(fn ($id) => [$id => ['can_edit' => true]])->all()
        );

        if ((bool) ($data['redirect_to_sheet'] ?? false)) {
            return redirect()
                ->route('nonclosures.sheets.show', array_merge(
                    ['sheet' => $sheet->id],
                    $this->catalogQueryParams($request, ['workbook' => $sheet->workbook_id])
                ))
                ->with('status', 'Доступ к таблице обновлён.');
        }

        return redirect()
            ->route('nonclosures.index', $this->catalogQueryParams($request, [
                'workbook' => $sheet->workbook_id,
            ]))
            ->with('status', 'Доступ к таблице обновлён.');
    }

    public function store(Request $request)
    {
        $data = $this->validated($request, true);
        $user = $request->user();

        NonClosure::create($this->payloadFromRequest($data, (int) $user->account_id, (int) $user->id, null));

        return back()->with('status', 'Запись добавлена.');
    }

    public function update(Request $request, NonClosure $nonclosure)
    {
        $this->authorizeRow($request, $nonclosure);
        $data = $this->validated($request, false);
        $user = $request->user();

        $nonclosure->fill($this->payloadFromRequest($data, (int) $user->account_id, null, (int) $user->id));
        $nonclosure->save();

        return back()->with('status', 'Запись обновлена.');
    }

    public function import(Request $request, NonClosureImportService $service)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
        ]);

        try {
            $result = $service->importFromXlsx(
                $request->file('file'),
                (int) $request->user()->account_id,
                (int) $request->user()->id
            );
        } catch (\Throwable $e) {
            return back()->withErrors([
                'file' => 'Не удалось импортировать старую таблицу незаключёнок: '.$e->getMessage(),
            ]);
        }

        return redirect()->route('nonclosures.index')->with('status', sprintf(
            'Импорт завершён: добавлено %d, обновлено %d, всего обработано %d.',
            $result['imported'],
            $result['updated'],
            $result['total']
        ));
    }

    private function activeUsersForAccount(User $user): Collection
    {
        return User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'role']);
    }

    private function allowedUserIdsForAccount(User $user): array
    {
        return User::query()
            ->where('account_id', $user->account_id)
            ->where('is_active', true)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();
    }

    private function resolveVisibleWorkbooks(
        NonClosureWorkspace $workspace,
        User $user,
        bool $canManage,
        string $viewScope,
        int $ownerFilterId
    ): Collection {
        $query = NonClosureWorkbook::query()
            ->where('workspace_id', $workspace->id)
            ->with(['owner:id,name', 'uploadedBy:id,name']);

        if ($canManage) {
            $query->withCount('sheets');
        } else {
            $query->withCount([
                'sheets' => function ($sheetQuery) use ($user) {
                    $sheetQuery->accessibleFor($user);
                },
            ]);
        }

        if (!$canManage) {
            $query->where(function ($inner) use ($user) {
                $inner->where('owner_user_id', $user->id)
                    ->orWhereHas('sheets', function ($sheetQuery) use ($user) {
                        $sheetQuery->accessibleFor($user);
                    });
            });
        }

        if ($ownerFilterId > 0) {
            $query->where('owner_user_id', $ownerFilterId);
        }

        if ($viewScope === 'my') {
            $query->where('owner_user_id', $user->id);
        } elseif ($viewScope === 'shared') {
            $query->whereHas('sheets', function ($sheetQuery) use ($user) {
                $sheetQuery->whereHas('sharedUsers', function ($sharedQuery) use ($user) {
                    $sharedQuery->where('users.id', $user->id);
                });
            });
        }

        return $query
            ->orderByDesc('imported_at')
            ->orderBy('title')
            ->get();
    }

    private function resolveWorkbookSheets(NonClosureWorkbook $workbook, User $user, bool $canManage): Collection
    {
        $query = NonClosureWorkbookSheet::query()
            ->where('workbook_id', $workbook->id)
            ->with(['owner:id,name', 'sharedUsers:id,name'])
            ->withCount('sharedUsers');

        if (!$canManage) {
            $query->accessibleFor($user);
        }

        return $query->orderBy('position')->orderBy('id')->get();
    }

    private function workbookSummaryForVisibleSheets(?NonClosureWorkbook $workbook, Collection $sheets): array
    {
        if (!$workbook) {
            return [
                'sheet_count' => 0,
                'row_count' => 0,
                'category_counts' => [],
                'source_name' => null,
            ];
        }

        return [
            'sheet_count' => $sheets->count(),
            'row_count' => (int) $sheets->sum('row_count'),
            'category_counts' => $sheets
                ->groupBy(fn (NonClosureWorkbookSheet $sheet) => $sheet->category)
                ->map(fn (Collection $items) => $items->count())
                ->all(),
            'source_name' => $workbook->source_name,
        ];
    }

    private function resolveSheetForView(NonClosureWorkbookSheet $sheet, User $user, bool $canManage): NonClosureWorkbookSheet
    {
        $query = NonClosureWorkbookSheet::query()
            ->whereKey($sheet->id)
            ->with([
                'owner:id,name',
                'sharedUsers:id,name',
                'workbook.owner:id,name',
                'workbook.uploadedBy:id,name',
            ])
            ->withCount('sharedUsers');

        if (!$canManage) {
            $query->accessibleFor($user);
        }

        return $query->firstOrFail();
    }

    private function workspaceForUser(Request $request): NonClosureWorkspace
    {
        $user = $request->user();

        $workspace = NonClosureWorkspace::query()->firstOrCreate(
            ['account_id' => $user->account_id],
            [
                'title' => 'Документы и таблицы',
                'document_html' => $this->defaultWorkspaceDocument(),
                'created_by_user_id' => $user->id,
                'updated_by_user_id' => $user->id,
            ]
        );

        if (!$workspace->document_html) {
            $workspace->document_html = $this->defaultWorkspaceDocument();
            $workspace->saveQuietly();
        }

        return $workspace;
    }

    private function defaultWorkspaceDocument(): string
    {
        return implode('', [
            '<h3>Документы и таблицы</h3>',
            '<p>Здесь можно вести общий документ, хранить импортированные книги Excel по листам и раздавать доступы пользователям.</p>',
            '<ul>',
            '<li>каждая книга хранится отдельно</li>',
            '<li>каждому листу можно назначить владельца</li>',
            '<li>доступ к таблицам можно выдавать точечно по сотрудникам</li>',
            '<li>по строкам можно ставить статусы, комментарии и задачи с напоминаниями</li>',
            '</ul>',
        ]);
    }

    private function normalizeScope(string $scope): string
    {
        return in_array($scope, ['my', 'shared', 'all'], true) ? $scope : 'all';
    }

    private function defaultScopeFor(User $user, bool $canManage): string
    {
        if ($canManage || $user->role === 'documents_operator') {
            return 'all';
        }

        return 'my';
    }

    private function canManageDocuments(User $user): bool
    {
        return in_array($user->role, ['admin', 'main_operator'], true);
    }

    private function canContributeDocuments(User $user): bool
    {
        return in_array($user->role, ['admin', 'main_operator', 'documents_operator'], true);
    }

    private function ensureDocumentManagementAccess(Request $request): void
    {
        if (!$this->canManageDocuments($request->user())) {
            abort(403);
        }
    }

    private function ensureDocumentContributionAccess(Request $request): void
    {
        if (!$this->canContributeDocuments($request->user())) {
            abort(403);
        }
    }

    private function authorizeWorkbook(Request $request, NonClosureWorkbook $workbook): void
    {
        if ((int) $workbook->account_id !== (int) $request->user()->account_id) {
            abort(404);
        }
    }

    private function authorizeSheet(Request $request, NonClosureWorkbookSheet $sheet): void
    {
        if ((int) $sheet->account_id !== (int) $request->user()->account_id) {
            abort(404);
        }
    }

    private function authorizeRow(Request $request, NonClosure $row): void
    {
        if ((int) $row->account_id !== (int) $request->user()->account_id) {
            abort(404);
        }
    }

    private function catalogQueryParams(Request $request, array $overrides = []): array
    {
        $params = array_merge([
            'scope' => $request->input('scope', $request->query('scope')),
            'owner_id' => $request->input('owner_id', $request->query('owner_id')),
            'workbook' => $request->input('workbook', $request->query('workbook')),
        ], $overrides);

        return array_filter($params, static fn ($value) => !is_null($value) && $value !== '');
    }

    private function validated(Request $request, bool $creating): array
    {
        return $request->validate([
            'entry_date' => [$creating ? 'required' : 'nullable', 'date'],
            'address' => [$creating ? 'required' : 'nullable', 'string', 'max:500'],
            'reason' => ['nullable', 'string'],
            'measurer_user_id' => ['nullable', 'integer'],
            'measurer_name' => ['nullable', 'string', 'max:120'],
            'responsible_user_id' => ['nullable', 'integer'],
            'responsible_name' => ['nullable', 'string', 'max:120'],
            'comment' => ['nullable', 'string'],
            'follow_up_date' => ['nullable', 'date'],
            'result_status' => ['nullable', Rule::in(['concluded', 'not_concluded'])],
            'special_calculation' => ['nullable', 'string'],
        ]);
    }

    private function payloadFromRequest(array $data, int $accountId, ?int $createdBy, ?int $updatedBy): array
    {
        $payload = [
            'account_id' => $accountId,
            'entry_date' => isset($data['entry_date']) && $data['entry_date'] !== null
                ? Carbon::parse($data['entry_date'])->startOfDay()
                : null,
            'address' => trim((string) ($data['address'] ?? '')),
            'reason' => $this->nullIfBlank($data['reason'] ?? null),
            'measurer_user_id' => $data['measurer_user_id'] ?: null,
            'measurer_name' => $this->nullIfBlank($data['measurer_name'] ?? null),
            'responsible_user_id' => $data['responsible_user_id'] ?: null,
            'responsible_name' => $this->nullIfBlank($data['responsible_name'] ?? null),
            'comment' => $this->nullIfBlank($data['comment'] ?? null),
            'follow_up_date' => isset($data['follow_up_date']) && $data['follow_up_date'] !== null && $data['follow_up_date'] !== ''
                ? Carbon::parse($data['follow_up_date'])->startOfDay()
                : null,
            'result_status' => $this->nullIfBlank($data['result_status'] ?? null),
            'special_calculation' => $this->nullIfBlank($data['special_calculation'] ?? null),
        ];

        $payload['unique_hash'] = sha1(implode('|', [
            $accountId,
            trim((string) ($payload['entry_date']?->format('Y-m-d') ?? '')),
            mb_strtolower(trim((string) $payload['address'])),
            mb_strtolower(trim((string) ($payload['measurer_name'] ?? ''))),
        ]));

        if ($createdBy !== null) {
            $payload['created_by_user_id'] = $createdBy;
        }

        if ($updatedBy !== null) {
            $payload['updated_by_user_id'] = $updatedBy;
        }

        return $payload;
    }

    private function nullIfBlank(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeColumnLabel(?string $label, int $index): string
    {
        $label = trim((string) $label);

        return $label !== '' ? $label : 'Колонка '.($index + 1);
    }

    private function normalizeRowValuesForColumnCount(array $values, int $columnCount): array
    {
        $normalized = [];

        for ($index = 0; $index < $columnCount; $index++) {
            $normalized[] = trim((string) ($values[$index] ?? ''));
        }

        return $normalized;
    }

    private function persistSheetMatrix(NonClosureWorkbookSheet $sheet, array $header, array $rows): void
    {
        $header = array_values($header);
        if (empty($header)) {
            $header = ['Колонка 1'];
        }

        $header = array_map(
            fn ($label, $index) => $this->normalizeColumnLabel($label, $index),
            $header,
            array_keys($header)
        );

        $columnCount = count($header);
        $rows = collect($rows)
            ->map(fn ($row) => $this->normalizeRowValuesForColumnCount((array) $row, $columnCount))
            ->values()
            ->all();

        $sheet->header = $header;
        $sheet->rows = $rows;
        $sheet->column_count = $columnCount;
        $sheet->row_count = count($rows);
        $sheet->preview_text = $this->buildSheetPreviewText($header, $rows);
        $sheet->save();
    }

    private function resolveSheetRow(NonClosureWorkbookSheet $sheet, int $rowIndex): array
    {
        if ($rowIndex < 1) {
            abort(422, 'Строка таблицы не найдена.');
        }

        $row = collect($sheet->rows ?? [])->values()->get($rowIndex - 1);
        if (!is_array($row)) {
            abort(422, 'Строка таблицы не найдена.');
        }

        return $row;
    }

    private function rowPreview(array $row): string
    {
        return collect($row)
            ->map(fn ($value) => trim((string) $value))
            ->filter()
            ->take(5)
            ->implode(' | ');
    }

    private function normalizeEditedRow(NonClosureWorkbookSheet $sheet, array $values, array $fallbackRow): array
    {
        $columnCount = max(
            count((array) ($sheet->header ?? [])),
            count($fallbackRow),
            count($values)
        );

        $normalized = [];
        for ($index = 0; $index < $columnCount; $index++) {
            $value = array_key_exists($index, $values)
                ? $values[$index]
                : ($fallbackRow[$index] ?? '');

            $normalized[] = trim((string) $value);
        }

        return $normalized;
    }

    private function shiftSheetRowRelations(NonClosureWorkbookSheet $sheet, int $fromRowIndex, int $delta): void
    {
        if ($delta === 0 || $fromRowIndex < 1) {
            return;
        }

        $states = NonClosureSheetRowState::query()
            ->where('account_id', $sheet->account_id)
            ->where('workbook_sheet_id', $sheet->id)
            ->where('row_index', '>=', $fromRowIndex)
            ->orderBy('row_index', $delta > 0 ? 'desc' : 'asc')
            ->get();

        foreach ($states as $state) {
            $state->row_index += $delta;
            $state->saveQuietly();
        }

        $activities = NonClosureSheetRowActivity::query()
            ->where('account_id', $sheet->account_id)
            ->where('workbook_sheet_id', $sheet->id)
            ->where('row_index', '>=', $fromRowIndex)
            ->orderBy('row_index', $delta > 0 ? 'desc' : 'asc')
            ->get();

        foreach ($activities as $activity) {
            $activity->row_index += $delta;
            $activity->saveQuietly();
        }

        $tasks = $this->documentSheetTasksQuery($sheet)->get();

        foreach ($tasks as $task) {
            $payload = is_array($task->external_payload ?? null) ? $task->external_payload : [];
            $rowIndex = (int) ($payload['row_index'] ?? 0);

            if ($rowIndex < $fromRowIndex) {
                continue;
            }

            $payload['row_index'] = max(1, $rowIndex + $delta);
            $payload['context_url'] = route('nonclosures.sheets.show', ['sheet' => $sheet->id]).'#row-'.$payload['row_index'];
            $task->external_payload = $payload;
            $task->saveQuietly();
        }
    }

    private function retireDeletedRowTasks(NonClosureWorkbookSheet $sheet, int $rowIndex, array $row): void
    {
        $tasks = $this->documentSheetTasksQuery($sheet)
            ->get()
            ->filter(function (Task $task) use ($rowIndex) {
                $payload = is_array($task->external_payload ?? null) ? $task->external_payload : [];

                return (int) ($payload['row_index'] ?? 0) === $rowIndex;
            });

        foreach ($tasks as $task) {
            $payload = is_array($task->external_payload ?? null) ? $task->external_payload : [];
            $payload['context_type'] = 'document_deleted_row';
            $payload['deleted_row_index'] = $rowIndex;
            $payload['row_index'] = 0;
            $payload['row_preview_deleted'] = $this->rowPreview($row);
            $payload['context_label'] = trim(implode(' → ', array_filter([
                (string) ($payload['workbook_title'] ?? ''),
                (string) ($payload['sheet_name'] ?? ''),
                'удалённая строка '.$rowIndex,
            ])));
            $payload['context_url'] = route('nonclosures.sheets.show', ['sheet' => $sheet->id]);
            $task->external_payload = $payload;
            $task->saveQuietly();
        }
    }

    private function syncDocumentTaskRowPreview(NonClosureWorkbookSheet $sheet, int $rowIndex, array $row): void
    {
        $tasks = $this->documentSheetTasksQuery($sheet)
            ->get()
            ->filter(function (Task $task) use ($rowIndex) {
                $payload = is_array($task->external_payload ?? null) ? $task->external_payload : [];

                return (int) ($payload['row_index'] ?? 0) === $rowIndex;
            });

        foreach ($tasks as $task) {
            $payload = is_array($task->external_payload ?? null) ? $task->external_payload : [];
            $payload['row_preview'] = $this->rowPreview($row);
            $task->external_payload = $payload;
            $task->saveQuietly();
        }
    }

    private function documentSheetTasksQuery(NonClosureWorkbookSheet $sheet)
    {
        return Task::query()
            ->where('account_id', $sheet->account_id)
            ->where('external_payload->context_type', 'document_sheet_row')
            ->where('external_payload->sheet_id', (int) $sheet->id);
    }

    private function buildSheetPreviewText(array $header, array $rows): string
    {
        $parts = [];

        foreach (array_slice($rows, 0, 5) as $row) {
            $cells = [];

            foreach ((array) $row as $index => $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $cells[] = (($header[$index] ?? null) ?: ('Колонка '.($index + 1))).': '.$value;
            }

            if (!empty($cells)) {
                $parts[] = implode(' | ', array_slice($cells, 0, 4));
            }
        }

        return implode(' || ', $parts);
    }

    private function redirectToSheetRow(NonClosureWorkbookSheet $sheet, int $rowIndex, Request $request)
    {
        $url = route('nonclosures.sheets.show', array_filter([
            'sheet' => $sheet->id,
            'scope' => $request->input('scope', $request->query('scope')),
            'owner_id' => $request->input('owner_id', $request->query('owner_id')),
            'workbook' => $request->input('workbook', $request->query('workbook')) ?: $sheet->workbook_id,
        ])).'#row-'.$rowIndex;

        return redirect()->to($url);
    }

    private function buildSheetTaskStats(User $user, NonClosureWorkbookSheet $sheet): array
    {
        $tasks = $this->documentSheetTasksQuery($sheet)
            ->where('account_id', $user->account_id)
            ->orderByDesc('due_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'status', 'due_at', 'assigned_user_id', 'external_payload']);

        $now = now();

        return $tasks
            ->groupBy(function (Task $task) {
                $payload = is_array($task->external_payload ?? null) ? $task->external_payload : [];

                return (int) ($payload['row_index'] ?? 0);
            })
            ->map(function (Collection $items) use ($now) {
                $items = $items->values();
                $openItems = $items->where('status', 'open')->values();
                $lastTask = $items->first();

                return [
                    'total' => $items->count(),
                    'open' => $openItems->count(),
                    'done' => $items->where('status', 'done')->count(),
                    'overdue' => $openItems->filter(
                        fn (Task $task) => $task->due_at && $task->due_at->lte($now)
                    )->count(),
                    'last_title' => $lastTask?->title,
                    'last_due_at' => optional($lastTask?->due_at)->format('d.m.Y H:i'),
                ];
            })
            ->all();
    }
}
