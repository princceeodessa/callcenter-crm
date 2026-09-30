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

    /** Владелец (он смотрит только отчёты) видит продажи за день с прибылью, но без кнопок продажи и сделок. */
    public function test_owner_sees_day_sales_with_profit_but_no_actions(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $owner = User::create([
            'account_id' => $head->account_id,
            'name' => 'Test Owner Day',
            'email' => 'owner_day_test_'.uniqid(),
            'password' => \Illuminate\Support\Facades\Hash::make('password'),
            'role' => 'sneaker_owner',
            'is_active' => true,
        ]);
        $item = $this->makeItem($head->account_id, cost: 6000, price: 10000);
        $deal = $this->sell($head, $item, 1);

        $this->actingAs($owner)->get(route('sale.day'))
            ->assertOk()
            ->assertSee($item->model)
            ->assertSee('Прибыль')
            ->assertSee('4 000 ₽')
            ->assertSee('🗓 Продажи за день')                                   // кнопка в меню владельца
            ->assertDontSee('href="'.route('sale.quick').'"', false)            // «Продать» — не для владельца
            ->assertDontSee(route('deals.show', $deal->id), false);             // и ссылок на сделки нет

        // Остальные рабочие страницы владельцу по-прежнему закрыты.
        $this->actingAs($owner)->get(route('sale.quick'))->assertForbidden();
        $this->actingAs($owner)->get(route('warehouse.index'))->assertForbidden();
    }

    /** Смотрим вчера — «сегодня» из полосы дней не пропадает. */
    public function test_strip_keeps_today_when_looking_at_an_earlier_day(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();

        $strip = $this->actingAs($head)->get(route('sale.day', ['date' => now()->subDay()->toDateString()]))
            ->assertOk()->viewData('strip');

        $this->assertSame(now()->toDateString(), $strip->last()['date'], 'полоса заканчивается сегодня');
        $this->assertSame(now()->subDay()->toDateString(), $strip->firstWhere('active', true)['date']);
        $this->assertCount(14, $strip);
    }

    /** Старый день (месяц назад) тоже виден в полосе, с неделей после него. */
    public function test_strip_moves_to_an_old_day(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $old = now()->subDays(30)->toDateString();

        $strip = $this->actingAs($head)->get(route('sale.day', ['date' => $old]))->assertOk()->viewData('strip');

        $this->assertSame($old, $strip->firstWhere('active', true)['date']);
        $this->assertSame(now()->subDays(24)->toDateString(), $strip->last()['date']);
        $active = $strip->firstWhere('active', true);
        $this->assertFalse($active['mobile_hidden'], 'на телефоне выбранный день виден');
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
