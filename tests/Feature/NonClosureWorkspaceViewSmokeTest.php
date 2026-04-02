<?php

namespace Tests\Feature;

use App\Models\NonClosureSheetRowState;
use App\Models\NonClosureWorkbook;
use App\Models\NonClosureWorkbookSheet;
use App\Models\NonClosureWorkspace;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class NonClosureWorkspaceViewSmokeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        View::share('errors', new ViewErrorBag());
        auth()->setUser($this->makeUser(1, 'Админ', 'admin'));
    }

    public function test_documents_catalog_view_renders_book_cards_and_table_links(): void
    {
        $owner = $this->makeUser(2, 'Наталья Савченко', 'main_operator');

        $workspace = tap(new NonClosureWorkspace(), function (NonClosureWorkspace $workspace) {
            $workspace->forceFill([
                'id' => 11,
                'account_id' => 1,
                'title' => 'Документы и таблицы',
                'document_html' => '<p>Общий документ по контрагентам.</p>',
            ]);
        });

        $workbook = tap(new NonClosureWorkbook(), function (NonClosureWorkbook $workbook) {
            $workbook->forceFill([
                'id' => 21,
                'account_id' => 1,
                'workspace_id' => 11,
                'title' => 'Контрагенты Мажор',
                'source_name' => 'Копия Контрагенты Мажор .xlsx',
                'owner_user_id' => 2,
                'summary' => ['category_counts' => ['directory' => 4, 'sales' => 8], 'row_count' => 577],
                'sheets_count' => 12,
                'imported_at' => now(),
            ]);
        });
        $workbook->setRelation('owner', $owner);

        $sheet = tap(new NonClosureWorkbookSheet(), function (NonClosureWorkbookSheet $sheet) {
            $sheet->forceFill([
                'id' => 31,
                'account_id' => 1,
                'workbook_id' => 21,
                'name' => 'Список ОПТ',
                'category' => NonClosureWorkbookSheet::CATEGORY_DIRECTORY,
                'owner_user_id' => 2,
                'row_count' => 2,
                'column_count' => 3,
                'header_row_index' => 1,
                'preview_text' => 'Контрагент: ООО Мажор | Телефон: +7 (900) 000-00-01',
                'shared_users_count' => 1,
            ]);
        });
        $sheet->setRelation('owner', $owner);
        $sheet->setRelation('sharedUsers', new Collection([$owner]));

        $html = view('nonclosures.index', [
            'workspace' => $workspace,
            'activeUsers' => new Collection([$owner]),
            'workbooks' => new Collection([$workbook]),
            'selectedWorkbook' => $workbook,
            'selectedWorkbookSummary' => [
                'sheet_count' => 1,
                'row_count' => 2,
                'category_counts' => ['directory' => 1],
            ],
            'sheets' => new Collection([$sheet]),
            'sheetCategories' => NonClosureWorkbookSheet::categoryOptions(),
            'canManageDocuments' => true,
            'canContributeDocuments' => true,
            'viewScope' => 'all',
            'ownerFilterId' => 0,
            'ownerStats' => [2 => 1],
        ])->render();

        $this->assertStringContainsString('Документы и таблицы', $html);
        $this->assertStringContainsString('Контрагенты Мажор', $html);
        $this->assertStringContainsString('Таблицы книги', $html);
        $this->assertStringContainsString('Список ОПТ', $html);
        $this->assertStringContainsString('Открыть таблицу', $html);
        $this->assertStringContainsString('Каталог книг и таблиц', $html);
    }

    public function test_sheet_view_renders_full_table_with_row_workspace(): void
    {
        $owner = $this->makeUser(2, 'Наталья Савченко', 'main_operator');
        $shared = $this->makeUser(3, 'Юлия Керенцева', 'operator');

        $workspace = tap(new NonClosureWorkspace(), function (NonClosureWorkspace $workspace) {
            $workspace->forceFill([
                'id' => 11,
                'account_id' => 1,
                'title' => 'Документы и таблицы',
            ]);
        });

        $workbook = tap(new NonClosureWorkbook(), function (NonClosureWorkbook $workbook) {
            $workbook->forceFill([
                'id' => 21,
                'account_id' => 1,
                'workspace_id' => 11,
                'title' => 'Контрагенты Мажор',
                'owner_user_id' => 2,
                'source_name' => 'Копия Контрагенты Мажор .xlsx',
            ]);
        });
        $workbook->setRelation('owner', $owner);

        $sheet = tap(new NonClosureWorkbookSheet(), function (NonClosureWorkbookSheet $sheet) {
            $sheet->forceFill([
                'id' => 31,
                'account_id' => 1,
                'workbook_id' => 21,
                'name' => 'Список ОПТ',
                'category' => NonClosureWorkbookSheet::CATEGORY_DIRECTORY,
                'owner_user_id' => 2,
                'row_count' => 2,
                'column_count' => 3,
                'header_row_index' => 1,
                'header' => ['Контрагент', 'Телефон', 'Город'],
                'rows' => [
                    ['ООО Мажор', '+7 (900) 000-00-01', 'Ижевск'],
                    ['ООО Регион', '+7 (900) 000-00-02', 'Тюмень'],
                ],
                'shared_users_count' => 1,
            ]);
        });
        $sheet->setRelation('owner', $owner);
        $sheet->setRelation('sharedUsers', new Collection([$shared]));
        $sheet->setRelation('workbook', $workbook);

        $rowState = tap(new NonClosureSheetRowState(), function (NonClosureSheetRowState $state) use ($shared) {
            $state->forceFill([
                'id' => 41,
                'account_id' => 1,
                'workbook_sheet_id' => 31,
                'row_index' => 1,
                'status' => NonClosureSheetRowState::STATUS_CALL,
                'comment' => 'Позвонить по строке',
                'assigned_user_id' => 3,
            ]);
            $state->setRelation('assignedTo', $shared);
        });

        $activity = (object) [
            'type' => 'state_updated',
            'body' => 'Статус: Новая → Позвонить',
            'actor' => $shared,
            'created_at' => now(),
        ];

        $html = view('nonclosures.sheet', [
            'workspace' => $workspace,
            'workbook' => $workbook,
            'sheet' => $sheet,
            'sheetRows' => collect($sheet->rows),
            'sheetHeader' => collect($sheet->header),
            'sheetCategories' => NonClosureWorkbookSheet::categoryOptions(),
            'sheetCategoryLabel' => 'Справочник',
            'siblingSheets' => new Collection([$sheet]),
            'activeUsers' => new Collection([$owner, $shared]),
            'selectedSheetSharedIds' => [3],
            'canManageDocuments' => true,
            'canContributeDocuments' => true,
            'backQuery' => ['scope' => 'all', 'workbook' => 21],
            'rowStatusOptions' => NonClosureSheetRowState::statusOptions(),
            'rowStatusToneMap' => NonClosureSheetRowState::statusToneMap(),
            'sheetRowStates' => collect([1 => $rowState]),
            'sheetRowActivities' => collect([1 => collect([$activity])]),
            'sheetRowTaskStats' => [
                1 => ['total' => 2, 'open' => 1, 'done' => 1, 'overdue' => 0, 'last_title' => 'Связаться', 'last_due_at' => now()->format('d.m.Y H:i')],
            ],
        ])->render();

        $this->assertStringContainsString('Полноформатная таблица', $html);
        $this->assertStringContainsString('Назад к каталогу', $html);
        $this->assertStringContainsString('ООО Мажор', $html);
        $this->assertStringContainsString('Список ОПТ', $html);
        $this->assertStringContainsString('Сохранить доступ', $html);
        $this->assertStringContainsString('Владелец таблицы', $html);
        $this->assertStringContainsString('Статус', $html);
        $this->assertStringContainsString('Задача', $html);
        $this->assertStringContainsString('Значения строки', $html);
    }

    private function makeUser(int $id, string $name, string $role): User
    {
        return tap(new User(), function (User $user) use ($id, $name, $role) {
            $user->forceFill([
                'id' => $id,
                'account_id' => 1,
                'name' => $name,
                'role' => $role,
                'is_active' => true,
                'email' => 'user'.$id.'@example.com',
            ]);
        });
    }
}
