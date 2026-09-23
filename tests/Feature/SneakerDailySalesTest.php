<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class SneakerDailySalesTest extends TestCase
{
    use DatabaseTransactions;

    public function test_head_sees_todays_sales_with_totals_and_profit(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $item = $this->makeItem($head->account_id, cost: 6000, price: 10000);

        $this->sell($head, $item, 2);

        $this->actingAs($head)->get(route('sale.day'))
            ->assertOk()
            ->assertSee('Продажи за день')
            ->assertSee($item->model)
            ->assertSee('20 000 ₽')      // выручка строки: 2 × 10 000
            ->assertSee('Прибыль')
            ->assertSee('8 000 ₽');      // 20 000 − 2 × 6 000
    }

    /** Продавец видит продажи, но не себестоимость и прибыль — как в «Быстрой продаже». */
    public function test_operator_does_not_see_profit(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $operator = User::where('role', 'sneaker_operator')->where('account_id', $head->account_id)->firstOrFail();
        $item = $this->makeItem($head->account_id, cost: 6000, price: 10000);
        $this->sell($operator, $item, 1);

        $this->actingAs($operator)->get(route('sale.day'))
            ->assertOk()
            ->assertSee($item->model)
            ->assertDontSee('Себест.')
            ->assertDontSee('Прибыль');
    }

    public function test_page_shows_only_the_chosen_day(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $today = $this->makeItem($head->account_id, cost: 1000, price: 3000);
        $old = $this->makeItem($head->account_id, cost: 1000, price: 3000);

        $this->sell($head, $today, 1);
        $oldDeal = $this->sell($head, $old, 1);
        $oldDeal->forceFill(['stock_deducted_at' => now()->subDays(3)->setTime(15, 30)])->save();

        $this->actingAs($head)->get(route('sale.day'))
            ->assertSee($today->model)
            ->assertDontSee($old->model);

        $this->actingAs($head)->get(route('sale.day', ['date' => now()->subDays(3)->toDateString()]))
            ->assertOk()
            ->assertSee($old->model)
            ->assertSee('15:30')
            ->assertDontSee($today->model);
    }

    /** Возврат снимает списание — такая продажа не должна считаться продажей дня. */
    public function test_returned_sale_is_not_counted_as_a_sale(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $item = $this->makeItem($head->account_id, cost: 1000, price: 3000);
        $deal = $this->sell($head, $item, 1);
        $deal->forceFill(['returned_at' => now(), 'stock_deducted_at' => null])->save();

        $response = $this->actingAs($head)->get(route('sale.day'));

        $response->assertOk()->assertSee('Возвраты за день')->assertSee($item->model.' · р. 42');
        $sales = $response->viewData('sales');
        $this->assertFalse($sales->contains('id', $deal->id));
        $this->assertTrue($response->viewData('returns')->contains('id', $deal->id));
    }

    public function test_bad_or_future_date_falls_back_to_today(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();

        foreach (['garbage', now()->addDays(5)->toDateString()] as $date) {
            $response = $this->actingAs($head)->get(route('sale.day', ['date' => $date]));
            $response->assertOk();
            $this->assertTrue($response->viewData('day')->isToday(), $date);
            $this->assertNull($response->viewData('nextDate'));
        }
    }

    public function test_ceiling_users_cannot_open_the_page(): void
    {
        $ceiling = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($ceiling)->get(route('sale.day'))->assertForbidden();
    }

    private function makeItem(int $accountId, float $cost, float $price): WarehouseItem
    {
        $marker = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        return WarehouseItem::create([
            'account_id' => $accountId,
            'brand' => 'DAYTEST',
            'model' => 'DAYMODEL '.$marker,
            'size' => '42',
            'quantity' => 10,
            'sale_price' => $price,
            'avg_cost' => $cost,
        ]);
    }

    private function sell(User $user, WarehouseItem $item, int $qty): Deal
    {
        $this->actingAs($user)
            ->post(route('sale.quick.store'), ['warehouse_item_id' => $item->id, 'qty' => $qty])
            ->assertRedirect(route('sale.quick'));

        return Deal::where('warehouse_item_id', $item->id)->latest('id')->firstOrFail();
    }
}
