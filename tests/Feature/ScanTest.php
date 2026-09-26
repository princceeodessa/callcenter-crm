<?php

namespace Tests\Feature;

use App\Models\StockMark;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class ScanTest extends TestCase
{
    use DatabaseTransactions;

    private const GS = "\x1D";

    /** Скан штрихкода с ценника = сразу продажа этих кроссовок. */
    public function test_scanning_the_price_tag_opens_quick_sale_of_that_model(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [$product] = $this->makeProduct($head->account_id);

        $this->actingAs($head)->get(route('scan', ['code' => $product->article]))
            ->assertRedirect(route('sale.quick', ['q' => $product->article]));
    }

    public function test_single_size_in_stock_is_preselected(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [$product, $small, $big] = $this->makeProduct($head->account_id);
        $big->update(['quantity' => 0]);

        $this->actingAs($head)->get(route('scan', ['code' => $product->article]))
            ->assertRedirect(route('sale.quick', ['q' => $product->article, 'item' => $small->id]));
    }

    /** Сканер в русской раскладке печатает «ША…» вместо «IF…». */
    public function test_russian_keyboard_layout_is_fixed(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [$product] = $this->makeProduct($head->account_id);
        $typed = strtr($product->article, ['I' => 'Ш', 'F' => 'А']);

        $this->actingAs($head)->get(route('scan', ['code' => $typed]))
            ->assertRedirect(route('sale.quick', ['q' => $product->article]));
    }

    /** Код ЧЗ без GS (сканер их теряет) — продажа сразу с размером этой пары. */
    public function test_marking_code_opens_sale_of_the_exact_pair(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        [$product, , $big] = $this->makeProduct($head->account_id);
        $code = '0104601234567890'.'21'.'SCANPAIR00001'.self::GS.'91EE10'.self::GS.'92'.str_repeat('Q', 43).'=';
        StockMark::create(['account_id' => $head->account_id, 'warehouse_item_id' => $big->id, 'code' => $code, 'status' => 'in_stock']);

        $this->actingAs($head)->get(route('scan', ['code' => str_replace(self::GS, '', $code)]))
            ->assertRedirect(route('sale.quick', ['q' => $product->article, 'item' => $big->id]));

        // «только инфо» — карточка с остатками и подсвеченной парой
        $info = $this->actingAs($head)->get(route('scan', ['code' => str_replace(self::GS, '', $code), 'info' => 1]));
        $info->assertOk()->assertSee($product->display_name)->assertSee('эта пара')->assertSee('на складе');
    }

    public function test_unknown_code_says_not_found(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();

        $this->actingAs($head)->get(route('scan', ['code' => 'NOPE-'.uniqid()]))
            ->assertOk()->assertSee('Товар не найден');
    }

    public function test_scanner_listener_is_only_in_the_sneaker_space(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->actingAs($head)->get(route('sale.day'))->assertSee('SCAN_URL', false);
        $this->actingAs($head)->get(route('print.labels'))->assertSee('SCAN_URL', false);   // и на странице печати ценников

        $admin = User::where('role', 'admin')->firstOrFail();
        $this->actingAs($admin)->get(route('scan', ['code' => 'X']))->assertForbidden();
    }

    /** @return array{0: WarehouseProduct, 1: WarehouseItem, 2: WarehouseItem} */
    private function makeProduct(int $accountId): array
    {
        $model = 'SCANTEST '.strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $article = 'IF'.random_int(100000, 999999);
        $small = WarehouseItem::create(['account_id' => $accountId, 'brand' => 'ADIDAS', 'model' => $model, 'size' => '42', 'quantity' => 3, 'sale_price' => 12990]);
        $big = WarehouseItem::create(['account_id' => $accountId, 'brand' => 'ADIDAS', 'model' => $model, 'size' => '43', 'quantity' => 1, 'sale_price' => 12990]);
        $product = WarehouseProduct::create(['account_id' => $accountId, 'brand' => 'ADIDAS', 'model' => $model, 'article' => $article]);

        return [$product, $small, $big];
    }
}
