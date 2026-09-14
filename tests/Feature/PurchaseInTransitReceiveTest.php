<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class PurchaseInTransitReceiveTest extends TestCase
{
    use DatabaseTransactions;

    public function test_only_selected_pairs_are_received_the_rest_stay_in_transit(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        [$a, $b, $c] = $this->makeInTransit($user->account_id, 3);

        $this->actingAs($user)
            ->post(route('purchases.receiveBatch'), ['purchase_ids' => [$a->id, $c->id]])
            ->assertRedirect(route('purchases.inTransit'));

        $received = PurchaseStage::where('account_id', $user->account_id)->where('name', 'Получено / На складе')->firstOrFail();

        foreach ([$a, $c] as $picked) {
            $picked->refresh();
            $this->assertNotNull($picked->stocked_at);
            $this->assertEquals($received->id, $picked->purchase_stage_id);
        }

        $b->refresh();
        $this->assertNull($b->stocked_at, 'невыбранная позиция должна остаться в пути');
        $this->assertNotEquals($received->id, $b->purchase_stage_id);

        // На складе появились только выбранные размеры.
        $this->assertNotNull($this->stockFor($user->account_id, $a));
        $this->assertNotNull($this->stockFor($user->account_id, $c));
        $this->assertNull($this->stockFor($user->account_id, $b));
    }

    public function test_receive_all_takes_everything_in_transit(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        $made = $this->makeInTransit($user->account_id, 3);

        $this->actingAs($user)
            ->post(route('purchases.receiveBatch'), ['scope' => 'all'])
            ->assertRedirect(route('purchases.inTransit'));

        foreach ($made as $purchase) {
            $purchase->refresh();
            $this->assertNotNull($purchase->stocked_at);
        }

        $this->assertSame(0, Purchase::where('account_id', $user->account_id)
            ->whereNull('closed_at')->whereNull('stocked_at')->count());
    }

    /** Повторная приёмка уже принятой позиции не должна задваивать остаток. */
    public function test_receiving_twice_does_not_double_the_stock(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        [$purchase] = $this->makeInTransit($user->account_id, 1);

        $this->actingAs($user)->post(route('purchases.receiveBatch'), ['purchase_ids' => [$purchase->id]]);
        $afterFirst = (int) $this->stockFor($user->account_id, $purchase)->quantity;

        $this->actingAs($user)->post(route('purchases.receiveBatch'), ['purchase_ids' => [$purchase->id]]);
        $afterSecond = (int) $this->stockFor($user->account_id, $purchase)->fresh()->quantity;

        $this->assertSame($afterFirst, $afterSecond);
    }

    public function test_in_transit_page_lists_pending_pairs_only(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        [$pending] = $this->makeInTransit($user->account_id, 1);
        [$stocked] = $this->makeInTransit($user->account_id, 1);
        $this->actingAs($user)->post(route('purchases.receiveBatch'), ['purchase_ids' => [$stocked->id]]);

        $response = $this->actingAs($user)->get(route('purchases.inTransit'));

        $response->assertOk();
        $response->assertSee($pending->article);
        $response->assertDontSee($stocked->article);
    }

    public function test_marks_only_the_selected_purchases_as_white_import(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        [$a, $b, $c] = $this->makeInTransit($user->account_id, 3);

        $this->actingAs($user)
            ->post(route('purchases.markWhite'), ['white' => 1, 'purchase_ids' => [$a->id, $c->id]])
            ->assertRedirect(route('purchases.inTransit'));

        $this->assertTrue((bool) $a->fresh()->is_white);
        $this->assertTrue((bool) $c->fresh()->is_white);
        $this->assertFalse((bool) $b->fresh()->is_white, 'невыбранная позиция не должна получить пометку');
    }

    public function test_scope_all_marks_everything_in_transit_and_can_be_undone(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        $made = $this->makeInTransit($user->account_id, 3);

        $this->actingAs($user)->post(route('purchases.markWhite'), ['white' => 1, 'scope' => 'all']);
        foreach ($made as $purchase) {
            $this->assertTrue((bool) $purchase->fresh()->is_white);
        }

        $this->actingAs($user)->post(route('purchases.markWhite'), ['white' => 0, 'scope' => 'all']);
        foreach ($made as $purchase) {
            $this->assertFalse((bool) $purchase->fresh()->is_white);
        }
    }

    /** Пометка не должна трогать то, что уже принято на склад (scope=all = «то, что в пути»). */
    public function test_scope_all_leaves_already_stocked_purchases_alone(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        [$stocked] = $this->makeInTransit($user->account_id, 1);
        [$pending] = $this->makeInTransit($user->account_id, 1);
        $this->actingAs($user)->post(route('purchases.receiveBatch'), ['purchase_ids' => [$stocked->id]]);

        $this->actingAs($user)->post(route('purchases.markWhite'), ['white' => 1, 'scope' => 'all']);

        $this->assertTrue((bool) $pending->fresh()->is_white);
        $this->assertFalse((bool) $stocked->fresh()->is_white);
    }

    public function test_in_transit_page_shows_the_white_badge(): void
    {
        $user = User::where('role', 'sneaker_head')->firstOrFail();
        [$purchase] = $this->makeInTransit($user->account_id, 1);
        $this->actingAs($user)->post(route('purchases.markWhite'), ['white' => 1, 'purchase_ids' => [$purchase->id]]);

        $this->actingAs($user)->get(route('purchases.inTransit'))
            ->assertOk()
            ->assertSee('Ввоз в белую')
            ->assertSee('белая');
    }

    /** @return array<int, Purchase> */
    private function makeInTransit(int $accountId, int $count): array
    {
        $stage = PurchaseStage::where('account_id', $accountId)->where('name', 'В пути')->firstOrFail();
        $marker = strtoupper(substr(md5(uniqid('', true)), 0, 8));

        $made = [];
        for ($i = 0; $i < $count; $i++) {
            $made[] = Purchase::create([
                'account_id' => $accountId,
                'purchase_stage_id' => $stage->id,
                'title' => 'INTRANSIT TEST '.$marker.'-'.$i,
                'brand' => 'INTRANSITTEST',
                'model' => 'MODEL '.$marker,
                'size' => (string) (40 + $i),
                'quantity' => 1,
                'cost' => 1000 + $i,
                'currency' => 'RUB',
                'article' => 'IT-'.$marker.'-'.$i,
            ]);
        }

        return $made;
    }

    private function stockFor(int $accountId, Purchase $purchase): ?WarehouseItem
    {
        return WarehouseItem::where('account_id', $accountId)
            ->where('brand', $purchase->brand)
            ->where('model', $purchase->model)
            ->where('size', $purchase->size)
            ->first();
    }
}
