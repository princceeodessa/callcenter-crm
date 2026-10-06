<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\PipelineStage;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\UserNotification;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Склад → «Продажи без списания»: продажи из загрузки истории (07.07.2026) отмечены «списано»,
 * но без пары со склада — остаток не уменьшался. Сверка привязывает пару и списывает её.
 */
class UnlinkedSalesTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;
    private string $marker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->marker = 'M'.strtoupper(substr(md5(uniqid('', true)), 0, 7));
    }

    public function test_page_suggests_the_pair_with_the_same_name_and_purchase_cost_first(): void
    {
        $exact = $this->item('42', cost: 7360);
        $sameModel = $this->item('43', cost: 7000);
        $otherBrand = WarehouseItem::create([
            'account_id' => $this->head->account_id, 'brand' => 'OTHERBRAND', 'model' => 'Thing X'.$this->marker,
            'size' => '42', 'quantity' => 1, 'avg_cost' => 7360, 'sale_price' => 12000,
        ]);
        $deal = $this->legacySale(cost: 7360);

        $response = $this->actingAs($this->head)->get(route('warehouse.unlinked'))
            ->assertOk()
            ->assertSee('Продажи без списания со склада')
            ->assertSee('Unltest Air Zeta '.$this->marker)
            ->assertSee('закупочная цена совпала');

        $row = $response->viewData('rows')->firstWhere('deal.id', $deal->id);
        $this->assertNotNull($row, 'продажа без списания в списке');
        $ids = $row['suggestions']->pluck('item.id')->all();
        $this->assertSame($exact->id, $ids[0], 'первой идёт пара с той же закупочной ценой');
        $this->assertContains($sameModel->id, $ids);
        $this->assertNotContains($otherBrand->id, $ids, 'совпавшая цена у другого бренда — не подсказка');

        $this->actingAs($this->head)->get(route('warehouse.index'))
            ->assertOk()->assertSee('Продажи без списания');
    }

    public function test_link_deducts_the_pair_once_and_keeps_sale_date_and_file_cost(): void
    {
        $item = $this->item('42', cost: 7500, qty: 2);
        $deal = $this->legacySale(cost: 7360);

        $this->actingAs($this->head)->post(route('warehouse.unlinked.link', $deal), ['item_id' => $item->id])
            ->assertRedirect()->assertSessionHasNoErrors();
        // Повторный клик (двойное нажатие) ничего не списывает.
        $this->actingAs($this->head)->post(route('warehouse.unlinked.link', $deal), ['item_id' => $item->id]);

        $deal->refresh();
        $this->assertSame(1, $item->fresh()->quantity);
        $this->assertSame($item->id, $deal->warehouse_item_id);
        $this->assertSame('2026-07-07 14:34:08', $deal->stock_deducted_at->format('Y-m-d H:i:s'), 'дата продажи не меняется');
        $this->assertEquals(7360, (float) $deal->sold_unit_cost, 'закупочная цена из файла остаётся');
        $this->assertNotNull($deal->stock_linked_at);
        $this->assertSame(1, StockMovement::where('source_type', 'deal')->where('source_id', $deal->id)->where('type', 'out')->count());
        $this->assertSame(-1, (int) StockMovement::where('source_type', 'deal')->where('source_id', $deal->id)->sum('quantity'));

        $rows = $this->actingAs($this->head)->get(route('warehouse.unlinked'))->assertOk()->viewData('rows');
        $this->assertNull($rows->firstWhere('deal.id', $deal->id), 'разобранная продажа ушла из списка');
        $this->assertTrue(Deal::where('id', $deal->id)->soldWithoutStock()->doesntExist());
    }

    public function test_link_by_typed_choice_from_the_list(): void
    {
        $item = $this->item('44', cost: 7000);
        $deal = $this->legacySale(cost: 7360);

        $this->actingAs($this->head)->post(route('warehouse.unlinked.link', $deal), ['item_ref' => '#'.$item->id.' · '.$item->display_name.' · на складе 1'])
            ->assertSessionHasNoErrors();
        $this->assertSame($item->id, $deal->fresh()->warehouse_item_id);
        $this->assertSame(0, $item->fresh()->quantity);

        // Текст не из списка — понятная ошибка, ничего не списано.
        $other = $this->legacySale(cost: 7360);
        $this->actingAs($this->head)->post(route('warehouse.unlinked.link', $other), ['item_ref' => 'какой-то текст'])
            ->assertSessionHasErrors('item_ref');
        $this->assertNull($other->fresh()->warehouse_item_id);
    }

    public function test_undo_returns_the_pair_and_the_sale_to_the_list(): void
    {
        $item = $this->item('42', cost: 7360);
        $deal = $this->legacySale(cost: 7360);
        $this->actingAs($this->head)->post(route('warehouse.unlinked.link', $deal), ['item_id' => $item->id]);
        $this->assertSame(0, $item->fresh()->quantity);

        $this->actingAs($this->head)->post(route('warehouse.unlinked.undo', $deal))->assertRedirect();

        $deal->refresh();
        $this->assertSame(1, $item->fresh()->quantity, 'пара вернулась на склад');
        $this->assertNull($deal->warehouse_item_id);
        $this->assertNull($deal->stock_linked_at);
        $this->assertSame('2026-07-07 14:34:08', $deal->stock_deducted_at->format('Y-m-d H:i:s'), 'продажа осталась продажей');
        $this->assertTrue(Deal::where('id', $deal->id)->soldWithoutStock()->exists());
    }

    public function test_skip_removes_the_sale_from_the_list_and_can_be_returned(): void
    {
        $deal = $this->legacySale(cost: 5500);

        $this->actingAs($this->head)->post(route('warehouse.unlinked.skip', $deal))->assertRedirect();
        $this->assertNotNull($deal->fresh()->stock_link_skipped_at);
        $this->assertTrue(Deal::where('id', $deal->id)->soldWithoutStock()->doesntExist());
        $this->assertNotNull($deal->fresh()->stock_deducted_at, 'продажа остаётся продажей');

        $this->actingAs($this->head)->post(route('warehouse.unlinked.undo', $deal))->assertRedirect();
        $this->assertNull($deal->fresh()->stock_link_skipped_at);
        $this->assertTrue(Deal::where('id', $deal->id)->soldWithoutStock()->exists());
    }

    /** Карточка сделки: выбор пары у такой продажи списывает её; смена пары возвращает прежнюю; дата и цена из файла не меняются. */
    public function test_choosing_a_pair_on_the_deal_card_deducts_and_switches_correctly(): void
    {
        $first = $this->item('42', cost: 7000);
        $second = $this->item('43', cost: 7100);
        $deal = $this->legacySale(cost: 7360);

        $this->actingAs($this->head)->post(route('deals.sale-item', $deal), ['warehouse_item_id' => $first->id, 'sold_quantity' => 1])
            ->assertSessionHas('status', 'Пара привязана к продаже и списана со склада.');
        $this->assertSame(0, $first->fresh()->quantity);

        $this->actingAs($this->head)->post(route('deals.sale-item', $deal), ['warehouse_item_id' => $second->id, 'sold_quantity' => 1]);
        $this->assertSame(1, $first->fresh()->quantity, 'прежняя пара вернулась');
        $this->assertSame(0, $second->fresh()->quantity, 'новая списана');
        $deal->refresh();
        $this->assertSame($second->id, $deal->warehouse_item_id);
        $this->assertSame('2026-07-07 14:34:08', $deal->stock_deducted_at->format('Y-m-d H:i:s'));
        $this->assertEquals(7360, (float) $deal->sold_unit_cost);

        $this->actingAs($this->head)->post(route('deals.sale-item', $deal), ['warehouse_item_id' => '', 'sold_quantity' => 1]);
        $this->assertSame(1, $second->fresh()->quantity, 'пару убрали — вернулась на склад');
        $this->assertTrue(Deal::where('id', $deal->id)->soldWithoutStock()->exists());

        $this->actingAs($this->head)->get(route('deals.show', $deal))->assertOk()->assertSee('пара со склада НЕ списана');
    }

    /** Обычная продажа (со склада) — прежнее поведение: смена товара в карточке возвращает старую пару и списывает новую. */
    public function test_regular_sale_switching_item_is_unchanged(): void
    {
        $first = $this->item('42', cost: 7000);
        $second = $this->item('43', cost: 7100);
        $this->actingAs($this->head)->post(route('sale.quick.store'), ['warehouse_item_id' => $first->id, 'qty' => 1]);
        $deal = Deal::where('warehouse_item_id', $first->id)->latest('id')->firstOrFail();
        $this->assertSame(0, $first->fresh()->quantity);

        // Раньше тут был 500: повторное уведомление о продаже упиралось в уникальный ключ,
        // старая пара уже вернулась на склад, а новая не списывалась — сделка переставала быть продажей.
        $this->actingAs($this->head)->post(route('deals.sale-item', $deal), ['warehouse_item_id' => $second->id, 'sold_quantity' => 1])
            ->assertRedirect()
            ->assertSessionHas('status', 'Товар для списания со склада обновлён.');

        $this->assertSame(1, $first->fresh()->quantity);
        $this->assertSame(0, $second->fresh()->quantity);
        $this->assertNull($deal->fresh()->stock_linked_at);
        $this->assertNotNull($deal->fresh()->stock_deducted_at, 'сделка осталась продажей');
        $this->assertEquals(7100, (float) $deal->fresh()->sold_unit_cost);
        $this->assertSame(1, UserNotification::where('type', 'sneaker_sale')->where('source_type', 'deal')
            ->where('source_id', $deal->id)->where('user_id', $this->head->id)->count(), 'одно уведомление на сделку');
    }

    public function test_negative_stock_can_be_zeroed(): void
    {
        $item = $this->item('46', cost: 7000, qty: -1);

        $this->actingAs($this->head)->get(route('warehouse.unlinked'))
            ->assertOk()->assertSee('Отрицательные остатки')->assertSee($item->display_name);

        $this->actingAs($this->head)->post(route('warehouse.item.zero', $item))->assertRedirect();

        $this->assertSame(0, $item->fresh()->quantity);
        $this->assertSame(1, (int) StockMovement::where('warehouse_item_id', $item->id)->where('type', 'adjust')->sum('quantity'));
    }

    public function test_operator_can_reconcile_but_does_not_see_costs(): void
    {
        $operator = User::where('role', 'sneaker_operator')->where('account_id', $this->head->account_id)->firstOrFail();
        $item = $this->item('42', cost: 7360);
        $deal = $this->legacySale(cost: 7360);

        $this->actingAs($operator)->get(route('warehouse.unlinked'))
            ->assertOk()
            ->assertSee('закупочная цена совпала')
            ->assertDontSee('закуп по файлу')
            ->assertDontSee('7 360 ₽');

        $this->actingAs($operator)->post(route('warehouse.unlinked.link', $deal), ['item_id' => $item->id])->assertSessionHasNoErrors();
        $this->assertSame(0, $item->fresh()->quantity);
    }

    public function test_ceiling_users_cannot_open_the_page(): void
    {
        $ceiling = User::where('account_id', '!=', $this->head->account_id)->where('is_active', true)->firstOrFail();

        $this->actingAs($ceiling)->get(route('warehouse.unlinked'))->assertForbidden();
    }

    private function item(string $size, float $cost, int $qty = 1): WarehouseItem
    {
        return WarehouseItem::create([
            'account_id' => $this->head->account_id,
            'brand' => 'UNLTEST',
            'model' => 'Air Zeta '.$this->marker,
            'size' => $size,
            'quantity' => $qty,
            'avg_cost' => $cost,
            'sale_price' => 13000,
        ]);
    }

    /** Продажа как из загрузки истории 07.07: «Продано», списано — но без пары со склада. */
    private function legacySale(float $cost): Deal
    {
        $stage = PipelineStage::where('account_id', $this->head->account_id)->where('is_final', 1)->orderBy('sort')->firstOrFail();

        return Deal::create([
            'account_id' => $this->head->account_id,
            'pipeline_id' => $stage->pipeline_id,
            'stage_id' => $stage->id,
            'title' => 'Unltest Air Zeta '.$this->marker,
            'title_is_custom' => 1,
            'responsible_user_id' => $this->head->id,
            'amount' => 13000,
            'currency' => 'RUB',
            'product_category' => 'sneakers',
            'closed_at' => '2026-07-07 14:34:08',
            'closed_result' => 'won',
            'sold_quantity' => 1,
            'sold_unit_cost' => $cost,
            'stock_deducted_at' => '2026-07-07 14:34:08',
        ]);
    }
}
