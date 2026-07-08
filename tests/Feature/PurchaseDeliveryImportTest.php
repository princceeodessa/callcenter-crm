<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class PurchaseDeliveryImportTest extends TestCase
{
    use DatabaseTransactions;

    public function test_import_creates_purchases_in_transit_and_receive_button_stocks_the_warehouse(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        $inTransit = PurchaseStage::where('account_id', $user->account_id)->where('name', 'В пути')->firstOrFail();
        $received = PurchaseStage::where('account_id', $user->account_id)->where('name', 'Получено / На складе')->firstOrFail();

        $file = new UploadedFile(
            base_path('tests/Fixtures/purchase-import-sample.xlsx'),
            'purchase-import-sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $beforeMaxId = (int) Purchase::max('id');
        $response = $this->actingAs($user)->post(route('purchases.import.run'), ['xlsx' => $file]);
        $response->assertOk();

        // 3 строки в файле = 3 отдельные карточки закупки (без агрегации, в отличие от склада).
        // Scoped by id > snapshot (not just the notes text) so ambient data in the shared dev DB
        // from other manual runs can't leak into this assertion.
        $purchases = Purchase::where('account_id', $user->account_id)
            ->where('id', '>', $beforeMaxId)
            ->orderBy('id')->get();
        $this->assertCount(3, $purchases);
        $this->assertTrue($purchases->every(fn (Purchase $p) => (int) $p->purchase_stage_id === $inTransit->id));
        $this->assertTrue($purchases->every(fn (Purchase $p) => $p->stocked_at === null), 'nothing should be stocked yet — only "В пути"');

        // Обе строки NIKE TEST MODEL A делят один артикул (AB1234-100) — должны сойтись
        // на один и тот же (brand, model), несмотря на то что qty/сумма разные.
        $nikeRows = $purchases->where('article', 'AB1234-100');
        $this->assertCount(2, $nikeRows);
        $this->assertCount(1, $nikeRows->pluck('model')->unique(), 'same article must canonicalize to one model across rows');

        // Сумма делится на кол-во: 12000 при qty=2 -> цена за пару 6000.
        $qty2Row = $nikeRows->firstWhere('quantity', 2);
        $this->assertNotNull($qty2Row);
        $this->assertEquals(6000, (float) $qty2Row->cost);

        // Артикул уже зарегистрирован за товаром до приёмки (для будущих импортов).
        $product = WarehouseProduct::where('account_id', $user->account_id)->where('article', 'AB1234-100')->first();
        $this->assertNotNull($product);
        $this->assertEquals($nikeRows->first()->model, $product->model);

        // Кнопка "Принять всё на склад".
        $ids = $purchases->pluck('id')->all();
        $receiveResponse = $this->actingAs($user)->post(route('purchases.receiveBatch'), ['purchase_ids' => $ids]);
        $receiveResponse->assertRedirect(route('purchases.kanban'));

        foreach ($purchases as $purchase) {
            $purchase->refresh();
            $this->assertEquals($received->id, $purchase->purchase_stage_id);
            $this->assertNotNull($purchase->stocked_at, 'must be marked stocked after receive');
        }

        // Остатки реально заведены на склад — по каждому размеру своя позиция.
        $item42 = WarehouseItem::where('account_id', $user->account_id)
            ->where('brand', $product->brand)->where('model', $product->model)->where('size', '42')->first();
        $item43 = WarehouseItem::where('account_id', $user->account_id)
            ->where('brand', $product->brand)->where('model', $product->model)->where('size', '43')->first();
        $this->assertNotNull($item42);
        $this->assertNotNull($item43);
        $this->assertEquals(1, $item42->quantity);
        $this->assertEquals(2, $item43->quantity);
    }

    public function test_second_import_with_existing_article_reuses_the_same_product_even_with_different_wording(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();

        // Симулируем, что артикул уже занят существующим товаром с другим написанием модели
        // (как будто его завёл обычный складской импорт или предыдущая поставка).
        $existing = WarehouseProduct::create([
            'account_id' => $user->account_id,
            'brand' => 'NEW BALANCE',
            'model' => 'ALREADY IMPORTED WORDING EF9999-300',
            'article' => 'EF9999-300',
        ]);

        $file = new UploadedFile(
            base_path('tests/Fixtures/purchase-import-sample.xlsx'),
            'purchase-import-sample.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );

        $this->actingAs($user)->post(route('purchases.import.run'), ['xlsx' => $file])->assertOk();

        $purchase = Purchase::where('account_id', $user->account_id)->where('article', 'EF9999-300')
            ->orderByDesc('id')->first();

        $this->assertNotNull($purchase);
        $this->assertEquals($existing->brand, $purchase->brand);
        $this->assertEquals($existing->model, $purchase->model, 'must reuse the pre-existing product wording, not invent a new one');

        // Не должно быть создано второго товара с этим же артикулом.
        $this->assertEquals(1, WarehouseProduct::where('account_id', $user->account_id)->where('article', 'EF9999-300')->count());
    }
}
