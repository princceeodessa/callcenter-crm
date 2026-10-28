<?php

namespace Tests\Feature;

use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** Закупки кроссовок в каркасе «Нордика» (этап 6 редизайна, 28.10.2026) */
class SneakersPurchasesTest extends TestCase
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

    public function test_kanban_by_prototype_and_quick_create_returns_to_the_board(): void
    {
        $stage = PurchaseStage::where('account_id', $this->head->account_id)->orderBy('sort')->firstOrFail();

        $this->actingAs($this->head)->get(route('purchases.kanban'))->assertOk()
            ->assertSee('id="pboard"', false)->assertSee('В один ряд')->assertSee('Загрузить поставку')->assertSee('id="npuTpl"', false);

        $this->actingAs($this->head)->post(route('purchases.store'), ['title' => 'PURTEST XT-6', 'purchase_stage_id' => $stage->id, 'size' => '43', 'quantity' => 2, 'cost' => 9000, 'back' => 'kanban'])
            ->assertRedirect(route('purchases.kanban'));
        $p = Purchase::where('title', 'PURTEST XT-6')->firstOrFail();
        $this->assertSame(2, (int) $p->quantity);

        $this->actingAs($this->head)->get(route('purchases.show', $p))->assertOk()->assertSee('Закрыть закупку')->assertSee('name="cost"', false);
    }

    /** Закупки с ценами — только руководителю; продавцу «В пути» без цен и приёмка (решение 28.10.2026) */
    public function test_operator_gets_only_in_transit_without_prices(): void
    {
        $stage = PurchaseStage::where('account_id', $this->head->account_id)->where('is_stock_in', false)->orderByDesc('sort')->firstOrFail();
        $p = Purchase::create(['account_id' => $this->head->account_id, 'purchase_stage_id' => $stage->id, 'title' => 'PURTEST op', 'brand' => 'NIKE',
            'model' => 'PURTEST op', 'size' => '42', 'quantity' => 1, 'cost' => 7777, 'currency' => 'RUB', 'article' => 'PT-'.uniqid()]);

        foreach ([route('purchases.kanban'), route('purchases.create'), route('purchases.show', $p), route('purchases.import.form')] as $url) {
            $this->actingAs($this->operator)->get($url)->assertForbidden();
        }
        $this->actingAs($this->operator)->postJson(route('purchases.move', $p), ['to_stage_id' => $stage->id])->assertForbidden();
        $this->actingAs($this->operator)->post(route('purchases.markWhite'), ['white' => 1, 'purchase_ids' => [$p->id]])->assertForbidden();

        $this->actingAs($this->operator)->get(route('purchases.inTransit'))->assertOk()
            ->assertSee($p->article)->assertDontSee('7 777')->assertDontSee('data-cost', false)->assertDontSee('в белую');
        $this->actingAs($this->head)->get(route('purchases.inTransit'))->assertOk()->assertSee('7 777 ₽')->assertSee('в белую');
    }
}
