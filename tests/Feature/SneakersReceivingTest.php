<?php

namespace Tests\Feature;

use App\Models\StockMark;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** «Приёмка» и «Скан» кроссовок в каркасе «Нордика» (этап 4 редизайна, 28.10.2026) */
class SneakersReceivingTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;
    private User $operator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->operator = User::where('role', 'sneaker_operator')->where('account_id', $this->head->account_id)->firstOrFail();
    }

    public function test_receiving_page_by_prototype(): void
    {
        [, $small] = $this->product();
        StockMovement::create(['account_id' => $this->head->account_id, 'warehouse_item_id' => $small->id, 'type' => 'in', 'quantity' => 2,
            'source_type' => 'purchase', 'source_id' => 777, 'note' => 'Приход с закупки #777']);

        $res = $this->actingAs($this->operator)->get(route('warehouse.receiving'))
            ->assertOk()
            ->assertViewIs('warehouse.receiving')
            ->assertSee('Последние приходы')
            ->assertSee('Как принимать')
            ->assertSee('Закупка #777')
            ->assertSee('id="rvIn"', false);
        $this->assertGreaterThanOrEqual(2, $res->viewData('today')['pairs']);
    }

    /** Скан артикула показывает размеры и ничего не меняет; приход — в выбранный размер, а не в первый */
    public function test_scan_shows_sizes_and_receipt_goes_to_the_chosen_size(): void
    {
        [$product, $small, $big] = $this->product();

        $j = $this->actingAs($this->operator)->postJson(route('warehouse.receiving.scan'), ['code' => $product->article, 'quantity' => 2])
            ->assertOk()->json();
        $this->assertSame('found', $j['status']);
        $this->assertSame(['42', '43'], array_column($j['cards'][0]['sizes'], 's'));
        $this->assertSame(3, $small->fresh()->quantity, 'скан не меняет остатки');

        $j = $this->actingAs($this->operator)->postJson(route('warehouse.receiving.scan'), ['item_id' => $big->id, 'quantity' => 2])
            ->assertOk()->json();
        $this->assertSame('received', $j['status']);
        $this->assertSame(3, $big->fresh()->quantity);
        $this->assertSame(3, $small->fresh()->quantity, 'первый размер не тронут');
        $this->assertSame('Сканер', $j['row']['label']);
        $this->assertSame(2, $j['row']['q']);
        $this->assertSame(3, $j['item']['q']);

        // режим «Честный знак» — код к последней принятой паре
        $code = '0104601234567890'.'21'.'RCVPAIR'.random_int(100000, 999999);
        $j = $this->actingAs($this->operator)->postJson(route('warehouse.receiving.scan'), ['code' => $code, 'mode' => 'mark'])->assertOk()->json();
        $this->assertSame('mark_ok', $j['status']);
        $this->assertTrue(StockMark::where('warehouse_item_id', $big->id)->where('code', $code)->exists());
        $this->actingAs($this->operator)->postJson(route('warehouse.receiving.scan'), ['code' => $code, 'mode' => 'mark'])->assertJson(['status' => 'mark_dup']);
    }

    public function test_unknown_code_offers_to_create_the_product(): void
    {
        $j = $this->actingAs($this->operator)->postJson(route('warehouse.receiving.scan'), ['code' => 'ZZ'.uniqid()])->assertOk()->json();

        $this->assertSame('not_found', $j['status']);
        $this->assertStringContainsString('/warehouse?new=', $j['new_url']);

        $this->actingAs($this->operator)->postJson(route('warehouse.receiving.scan'), ['item_id' => 999999999])->assertNotFound();
        $res = $this->actingAs($this->operator)->get(route('warehouse.index', ['new' => 'ZZ0000']))->assertOk();
        $this->assertSame('ZZ0000', $res->viewData('initial')['new']);
    }

    public function test_scan_info_page_by_prototype(): void
    {
        [$product, $small] = $this->product();
        $small->update(['avg_cost' => 7000]);

        $this->actingAs($this->head)->get(route('scan', ['code' => $product->article, 'info' => 1]))
            ->assertOk()->assertViewIs('scan.sneakers')
            ->assertSee('Найдено по коду')->assertSee('Только информация')->assertSee('Склад в ценах продажи')->assertSee('Закуп');
        $this->actingAs($this->operator)->get(route('scan', ['code' => $product->article, 'info' => 1]))
            ->assertOk()->assertDontSee('Закуп')->assertDontSee('7 000 ₽');
        $this->actingAs($this->operator)->get(route('scan'))->assertOk()->assertSee('Курсор уже в поле');
        $this->actingAs($this->operator)->get(route('scan', ['code' => 'NOPE'.uniqid()]))
            ->assertOk()->assertSee('Завести товар')->assertSee('/warehouse?new=NOPE', false);
    }

    /** @return array{0: WarehouseProduct, 1: WarehouseItem, 2: WarehouseItem} */
    private function product(): array
    {
        $acc = $this->head->account_id;
        $model = 'RCVTEST '.strtoupper(substr(md5(uniqid('', true)), 0, 6));
        $small = WarehouseItem::create(['account_id' => $acc, 'brand' => 'NIKE', 'model' => $model, 'size' => '42', 'quantity' => 3, 'sale_price' => 12990]);
        $big = WarehouseItem::create(['account_id' => $acc, 'brand' => 'NIKE', 'model' => $model, 'size' => '43', 'quantity' => 1, 'sale_price' => 12990]);
        $product = WarehouseProduct::create(['account_id' => $acc, 'brand' => 'NIKE', 'model' => $model, 'article' => 'RC'.random_int(100000, 999999)]);

        return [$product, $small, $big];
    }
}
