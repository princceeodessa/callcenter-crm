<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Locks in the existing warehouse.import.run behaviour (same-article-collapse,
 * brand/model split) BEFORE it gets refactored into a shared support class for
 * reuse by the purchases delivery import — this must keep passing unchanged.
 */
class WarehouseImportRegressionTest extends TestCase
{
    use DatabaseTransactions;

    public function test_xlsx_import_collapses_same_article_across_differently_worded_rows(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();

        $file = new UploadedFile(
            base_path('tests/Fixtures/warehouse-import-sample.xlsx'),
            'warehouse-import-sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $response = $this->actingAs($user)->post(route('warehouse.import.run'), [
            'xlsx' => $file,
            'mode' => 'set',
        ]);

        $response->assertRedirect(route('warehouse.index'));

        $product = WarehouseProduct::where('account_id', $user->account_id)
            ->where('article', 'AB1234-100')->get();
        $this->assertCount(1, $product, 'exactly one product should claim this article');
        $canonical = $product->first();

        $items = WarehouseItem::where('account_id', $user->account_id)
            ->where('brand', $canonical->brand)
            ->where('model', $canonical->model)
            ->get()
            ->keyBy('size');

        $this->assertCount(2, $items, 'both size rows (42 and 43) must collapse onto the same brand+model');
        $this->assertEquals(1, $items['42']->quantity);
        $this->assertEquals(1, $items['43']->quantity);

        $secondProduct = WarehouseProduct::where('account_id', $user->account_id)
            ->where('article', 'CD5678-200')->first();
        $this->assertNotNull($secondProduct);
        $secondItem = WarehouseItem::where('account_id', $user->account_id)
            ->where('brand', $secondProduct->brand)
            ->where('model', $secondProduct->model)
            ->where('size', '40')
            ->first();
        $this->assertNotNull($secondItem);
        $this->assertEquals(2, $secondItem->quantity);
    }
}
