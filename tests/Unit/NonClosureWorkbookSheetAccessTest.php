<?php

namespace Tests\Unit;

use App\Models\NonClosureWorkbookSheet;
use App\Models\User;
use Tests\TestCase;

class NonClosureWorkbookSheetAccessTest extends TestCase
{
    public function test_access_scope_includes_workbook_owner_visibility(): void
    {
        $user = new User();
        $user->forceFill([
            'id' => 77,
            'account_id' => 1,
            'role' => 'documents_operator',
            'name' => 'Документы',
            'email' => 'documents@example.com',
        ]);
        $user->exists = true;

        $query = NonClosureWorkbookSheet::query()->accessibleFor($user);
        $sql = $query->toSql();
        $bindings = $query->getBindings();

        $this->assertStringContainsString('non_closure_workbooks', $sql);
        $this->assertStringContainsString('non_closure_workbook_sheet_user', $sql);
        $this->assertGreaterThanOrEqual(3, count(array_filter($bindings, fn ($value) => (int) $value === 77)));
    }
}
