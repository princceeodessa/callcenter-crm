<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\WarehouseItem;
use App\Models\WarehouseProduct;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Склад кроссовок в каркасе «Нордика» (этап 3 редизайна, 28.10.2026) */
class SneakersWarehouseTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;
    private User $operator;
    private string $marker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->operator = User::where('role', 'sneaker_operator')->where('account_id', $this->head->account_id)->firstOrFail();
        $this->marker = strtoupper(substr(md5(uniqid('', true)), 0, 8));
    }

    public function test_head_sees_page_with_cost_and_head_chips(): void
    {
        $this->item('42', qty: 3, cost: 6000, price: 11990);
        $this->item('42.5', qty: 1, cost: 6000, price: 11990);

        $res = $this->actingAs($this->head)->get(route('warehouse.index'))
            ->assertOk()
            ->assertViewIs('warehouse.sneakers')
            ->assertSee('class="ns-wchips"', false)
            ->assertSee('href="'.route('warehouse.analytics').'"', false)
            ->assertSee('href="'.route('onec.index').'"', false)
            ->assertSee('id="nsNewProd"', false);

        $p = $res->viewData('products')->firstWhere('m', 'WHMODEL '.$this->marker);
        $this->assertNotNull($p);
        $this->assertSame(['42', '42.5'], array_column($p['sz'], 's'));
        $this->assertSame(6000.0, $p['sz'][0]['c'], 'руководителю — закупка размера');
        $this->assertSame(11990.0, $p['sz'][0]['p']);
        $this->assertSame('Whtest', $p['brand'], 'бренд — по первому слову названия');
    }

    /** Продавцу закупка не уходит даже в данные страницы; аналитика и себестоимость в CSV закрыты (решение 28.10.2026) */
    public function test_operator_gets_no_cost_anywhere(): void
    {
        $this->item('41', qty: 2, cost: 6000, price: 11990);

        $res = $this->actingAs($this->operator)->get(route('warehouse.index'))->assertOk()
            ->assertDontSee('href="'.route('warehouse.analytics').'"', false)
            ->assertDontSee('href="'.route('onec.index').'"', false);
        foreach ($res->viewData('products') as $p) {
            foreach ($p['sz'] as $z) {
                $this->assertArrayNotHasKey('c', $z);
            }
        }
        $this->assertFalse($res->viewData('seesCost'));
        $this->assertStringNotContainsString('"c":', $res->getContent());

        $this->actingAs($this->operator)->get(route('warehouse.analytics'))->assertForbidden();
        $this->actingAs($this->operator)->get(route('warehouse.analytics.export'))->assertForbidden();
        $this->actingAs($this->head)->get(route('warehouse.analytics'))->assertOk();

        $csv = $this->actingAs($this->operator)->get(route('warehouse.export'))->assertOk()->streamedContent();
        $this->assertStringNotContainsString('Себестоимость', $csv);
        $this->assertStringContainsString('Цена продажи', $csv);
        $headCsv = $this->actingAs($this->head)->get(route('warehouse.export'))->streamedContent();
        $this->assertStringContainsString('Себестоимость', $headCsv);
    }

    public function test_size_editor_saves_by_json_and_operator_cannot_change_prices(): void
    {
        $item = $this->item('43', qty: 2, cost: 5000, price: 9990);

        $j = $this->actingAs($this->head)->patchJson(route('warehouse.update', $item), [
            'quantity' => 5, 'low_stock_threshold' => 1, 'avg_cost' => 5500, 'sale_price' => 10990,
        ])->assertOk()->json();
        $this->assertSame('Размер 43 сохранён', $j['msg']);
        $this->assertSame(5, $j['product']['sz'][0]['q']);
        $this->assertEquals(5500, $j['product']['sz'][0]['c']);
        $item->refresh();
        $this->assertSame(5, $item->quantity);
        $this->assertEquals(10990, (float) $item->sale_price);

        $j = $this->actingAs($this->operator)->patchJson(route('warehouse.update', $item), ['quantity' => 4, 'sale_price' => 1, 'avg_cost' => 1])
            ->assertOk()->json();
        $this->assertArrayNotHasKey('c', $j['product']['sz'][0], 'в ответе продавцу закупки нет');
        $item->refresh();
        $this->assertSame(4, $item->quantity);
        $this->assertEquals(10990, (float) $item->sale_price, 'цену продавец не меняет');
        $this->assertEquals(5500, (float) $item->avg_cost);
    }

    /** Сохранение размера больше не стирает заметку позиции */
    public function test_saving_size_keeps_item_notes(): void
    {
        $item = $this->item('44', qty: 1, cost: 5000, price: 9990);
        $item->forceFill(['notes' => 'с дефектом'])->save();

        $this->actingAs($this->head)->patchJson(route('warehouse.update', $item), ['quantity' => 2])->assertOk();

        $this->assertSame('с дефектом', $item->fresh()->notes);
    }

    public function test_add_size_inherits_price_and_cost_of_the_model(): void
    {
        $this->item('40', qty: 1, cost: 5000, price: 9990);

        $j = $this->actingAs($this->head)->postJson(route('warehouse.store'), [
            'brand' => 'WHTEST', 'model' => 'WHMODEL '.$this->marker, 'size' => '41', 'quantity' => 2,
        ])->assertOk()->json();

        $new = WarehouseItem::where('model', 'WHMODEL '.$this->marker)->where('size', '41')->firstOrFail();
        $this->assertSame(2, $new->quantity);
        $this->assertEquals(9990, (float) $new->sale_price);
        $this->assertEquals(5000, (float) $new->avg_cost);
        $this->assertSame(['40', '41'], array_column($j['product']['sz'], 's'));
        $this->assertSame('Размер 41 добавлен: +2', $j['msg']);
    }

    public function test_new_product_with_several_sizes(): void
    {
        $j = $this->actingAs($this->head)->postJson(route('warehouse.store'), [
            'name' => 'NIKE air force '.$this->marker, 'brand' => 'Nike', 'article' => 'AF-'.$this->marker,
            'sizes' => '41:1, 42,5:2, 43(3) 44', 'cost' => 6000, 'price' => 12990,
        ])->assertOk()->json();

        $items = WarehouseItem::where('model', 'air force '.$this->marker)->orderBy('size')->get();
        $this->assertSame(['41', '42.5', '43', '44'], $items->pluck('size')->all());
        $this->assertSame([1, 2, 3, 1], $items->pluck('quantity')->all());
        $this->assertSame('NIKE', $items[0]->brand, 'бренд — как написан в названии');
        $this->assertEquals(6000, (float) $items[0]->avg_cost);
        $product = WarehouseProduct::where('model', 'air force '.$this->marker)->firstOrFail();
        $this->assertSame('AF-'.$this->marker, $product->article);
        $this->assertSame($product->id, $j['id']);
        $this->assertSame('Nike', $j['product']['brand']);

        // продавец создаёт товар без закупки, даже если её прислали
        $this->actingAs($this->operator)->postJson(route('warehouse.store'), ['name' => 'PUMA rs '.$this->marker, 'sizes' => '40', 'cost' => 1, 'price' => 8990])->assertOk();
        $op = WarehouseItem::where('model', 'rs '.$this->marker)->firstOrFail();
        $this->assertSame('PUMA', $op->brand);
        $this->assertNull($op->avg_cost);

        $this->actingAs($this->head)->postJson(route('warehouse.store'), ['name' => 'X '.$this->marker, 'sizes' => '42 / 43'])
            ->assertStatus(422)->assertJsonValidationErrors('sizes');
        $this->actingAs($this->head)->postJson(route('warehouse.store'), ['name' => 'Y '.$this->marker, 'sizes' => '42', 'article' => 'AF-'.$this->marker])
            ->assertStatus(422)->assertJsonValidationErrors('article');
    }

    public function test_deleting_last_size_removes_the_card(): void
    {
        $item = $this->item('45', qty: 1, cost: 5000, price: 9990);
        $product = WarehouseProduct::firstOrCreate(['account_id' => $this->head->account_id, 'brand' => 'WHTEST', 'model' => 'WHMODEL '.$this->marker]);

        $j = $this->actingAs($this->head)->deleteJson(route('warehouse.destroy', $item))->assertOk()->json();

        $this->assertNull($j['product']);
        $this->assertSame($product->id, $j['id']);
        $this->actingAs($this->operator)->deleteJson(route('warehouse.destroy', $this->item('46', qty: 1, cost: 1, price: 1)))->assertForbidden();
    }

    public function test_consignment_and_bulk_answer_json(): void
    {
        $item = $this->item('42', qty: 3, cost: 5000, price: 9990);

        $j = $this->actingAs($this->operator)->postJson(route('warehouse.item.consign', $item), ['consignee' => 'ТЦ Юность', 'quantity' => 2])
            ->assertOk()->json();
        $this->assertSame(1, $j['product']['sz'][0]['a']);
        $this->assertSame('ТЦ Юность', $j['product']['sz'][0]['cons'][0]['who']);
        $this->actingAs($this->operator)->postJson(route('warehouse.item.consign', $item), ['consignee' => 'Ещё', 'quantity' => 5])
            ->assertStatus(422)->assertJsonValidationErrors('quantity');

        $j = $this->actingAs($this->operator)->postJson(route('warehouse.consignment.resolve', $j['product']['sz'][0]['cons'][0]['id']), ['result' => 'returned'])
            ->assertOk()->json();
        $this->assertSame(3, $j['product']['sz'][0]['a']);

        $product = WarehouseProduct::firstOrCreate(['account_id' => $this->head->account_id, 'brand' => 'WHTEST', 'model' => 'WHMODEL '.$this->marker]);
        $j = $this->actingAs($this->head)->postJson(route('warehouse.bulk'), ['product_ids' => [$product->id], 'action' => 'season', 'value' => 'winter'])
            ->assertOk()->json();
        $this->assertSame('winter', $j['products'][0]['sea']);
    }

    private function item(string $size, int $qty, float $cost, float $price): WarehouseItem
    {
        return WarehouseItem::create([
            'account_id' => $this->head->account_id,
            'brand' => 'WHTEST',
            'model' => 'WHMODEL '.$this->marker,
            'size' => $size,
            'quantity' => $qty,
            'sale_price' => $price,
            'avg_cost' => $cost,
        ]);
    }
}
