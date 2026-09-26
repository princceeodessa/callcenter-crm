<?php

namespace Tests\Feature;

use App\Models\StockMark;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class LabelPrintTest extends TestCase
{
    use DatabaseTransactions;

    private const GS = "\x1D";

    public function test_price_tags_are_printed_per_size_with_chosen_copies(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [$product, $small, $big] = $this->makeProduct($head->account_id);

        $response = $this->actingAs($head)->get(route('print.labels', [
            'product' => $product->id, 'type' => 'price', 'format' => '58x40',
            'c' => [$small->id => 2, $big->id => 0],
        ]));

        $response->assertOk()
            ->assertSee('size: 58mm 40mm', false)      // страница ровно под наклейку
            ->assertSee('12 990 ₽')
            ->assertSee($product->article);
        $this->assertCount(2, $response->viewData('labels'), '2 копии размера 42 и ни одной 43');
    }

    public function test_price_can_be_hidden_and_other_label_sizes_are_used(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [$product] = $this->makeProduct($head->account_id);

        $this->actingAs($head)->get(route('print.labels', [
            'product' => $product->id, 'type' => 'price', 'format' => '43x25', 'show_price' => '0',
        ]))->assertOk()
            ->assertSee('size: 43mm 25mm', false)
            ->assertDontSee('<div class="price">', false);   // на этикетке цены нет (в таблице размеров — есть)
    }

    public function test_marking_codes_from_stock_and_pasted_are_printed_with_gs1_input(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [$product, $small] = $this->makeProduct($head->account_id);
        $stored = $this->code('AAAAAAAAAAAA1');
        StockMark::create(['account_id' => $head->account_id, 'warehouse_item_id' => $small->id, 'code' => $stored, 'status' => 'in_stock']);
        StockMark::create(['account_id' => $head->account_id, 'warehouse_item_id' => $small->id, 'code' => $this->code('SOLDSOLDSOLD1'), 'status' => 'sold']);

        // Вставленный код без GS (как со сканера) + мусорная строка.
        $pasted = str_replace(self::GS, '', $this->code('BBBBBBBBBBBB2'));

        $response = $this->actingAs($head)->post(route('print.labels'), [
            'type' => 'mark', 'products' => (string) $product->id, 'codes' => $pasted."\nне код\n",
        ]);

        $response->assertOk()
            ->assertSee('bwip-js', false)
            ->assertSee('(21) AAAAAAAAAAAA1')
            ->assertSee('(21) BBBBBBBBBBBB2')
            ->assertDontSee('SOLDSOLDSOLD1')                     // проданный код не печатаем
            ->assertSee('Пропущено строк, не похожих на код маркировки: 1');

        $labels = $response->viewData('labels');
        $this->assertCount(2, $labels);
        $this->assertStringStartsWith('^FNC101', $labels[1]['dm']);
        $this->assertStringContainsString('^FNC191', $labels[1]['dm'], 'потерянный GS восстановлен');
    }

    public function test_short_code_without_crypto_tail_is_flagged(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();

        $this->actingAs($head)->post(route('print.labels'), [
            'type' => 'mark', 'codes' => '0104601234567890212222222222222',
        ])->assertOk()->assertSee('нет криптохвоста');
    }

    public function test_receiving_scan_stores_the_code_with_separators_restored(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [, $small] = $this->makeProduct($head->account_id);
        $full = $this->code('CCCCCCCCCCCC3');

        $this->actingAs($head)
            ->withSession(['receive_target_item_id' => $small->id])
            ->post(route('warehouse.receiving.scan'), ['mode' => 'mark', 'code' => str_replace(self::GS, '', $full)]);

        $this->assertTrue(StockMark::where('warehouse_item_id', $small->id)->where('code', $full)->exists());
    }

    public function test_old_label_link_goes_to_the_print_page(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [$product] = $this->makeProduct($head->account_id);

        $this->actingAs($head)->get(route('warehouse.product.label', $product))
            ->assertRedirect(route('print.labels', ['product' => $product->id, 'type' => 'product']));
    }

    public function test_receipt_can_be_printed_on_58mm_tape(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [, $small] = $this->makeProduct($head->account_id);
        $this->actingAs($head)->post(route('sale.quick.store'), ['warehouse_item_id' => $small->id, 'qty' => 1]);
        $deal = \App\Models\Deal::where('warehouse_item_id', $small->id)->latest('id')->firstOrFail();

        $this->actingAs($head)->get(route('deals.receipt', ['deal' => $deal->id, 'w' => 58]))
            ->assertOk()->assertSee('width:48mm', false);
    }

    public function test_driver_is_downloadable_by_sneaker_staff_only(): void
    {
        $path = \App\Http\Controllers\LabelPrintController::driverPath();
        $existed = is_file($path);
        if (! $existed) {
            @mkdir(dirname($path), 0775, true);
            file_put_contents($path, 'fake-zip');
        }

        try {
            $head = User::where('role', 'sneaker_head')->firstOrFail();
            $this->actingAs($head)->get(route('print.labels'))->assertSee('Скачать драйвер XP-365B');
            $this->actingAs($head)->get(route('print.driver'))
                ->assertOk()->assertDownload('Xprinter_XP-365B_driver.zip');

            $this->actingAs(User::where('role', 'admin')->firstOrFail())
                ->get(route('print.driver'))->assertForbidden();
        } finally {
            if (! $existed) {
                @unlink($path);
            }
        }
    }

    public function test_ceiling_users_cannot_open_label_printing(): void
    {
        $this->actingAs(User::where('role', 'admin')->firstOrFail())
            ->get(route('print.labels'))->assertForbidden();
    }

    private function code(string $serial): string
    {
        return '0104601234567890'.'21'.$serial.self::GS.'91EE10'.self::GS.'92'.str_repeat('Q', 43).'=';
    }

    /** @return array{0: WarehouseProduct, 1: WarehouseItem, 2: WarehouseItem} */
    private function makeProduct(int $accountId): array
    {
        $model = 'PRINTTEST '.strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $small = WarehouseItem::create(['account_id' => $accountId, 'brand' => 'NIKE', 'model' => $model, 'size' => '42', 'quantity' => 3, 'sale_price' => 12990, 'avg_cost' => 7000]);
        $big = WarehouseItem::create(['account_id' => $accountId, 'brand' => 'NIKE', 'model' => $model, 'size' => '43', 'quantity' => 1, 'sale_price' => 12990, 'avg_cost' => 7000]);
        $product = WarehouseProduct::firstOrCreate(['account_id' => $accountId, 'brand' => 'NIKE', 'model' => $model]);

        return [$product, $small, $big];
    }
}
