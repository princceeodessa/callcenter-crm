<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\Pipeline;
use App\Models\PipelineStage;
use App\Models\User;
use App\Models\WarehouseItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/** «Продажи · канбан» и карточка сделки кроссовок в каркасе «Нордика» (этап 6 редизайна, 28.10.2026) */
class SneakersDealsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_kanban_card_and_side_pages_in_sneakers_layout(): void
    {
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $operator = User::where('role', 'sneaker_operator')->where('account_id', $head->account_id)->firstOrFail();
        $deal = $this->deal($head);

        $this->actingAs($operator)->get(route('deals.kanban'))->assertOk()->assertViewIs('deals.kanban_sneakers')
            ->assertSee('id="dboard"', false)->assertSee('DLTEST клиент')->assertSee('DLTEST MODEL')->assertSee('Показать нецелевое')
            ->assertSee('class="ns-html"', false);

        $this->actingAs($operator)->get(route('deals.show', $deal))->assertOk()
            ->assertSee('ns-html nd-html', false)->assertSee('nordika-crm-app.css', false)->assertSee('class="nd-panel nd-deal"', false)
            ->assertSee('Склад · что продаём')->assertSee(route('deals.sale-item', $deal), false)->assertSee('Коды маркировки')
            ->assertDontSee('Помещение');

        $this->actingAs($operator)->get(route('deals.create', ['stage_id' => $deal->stage_id]))->assertOk()
            ->assertSee('ns-html nd-html', false)->assertSee('name="contact_phone"', false)
            ->assertSee('value="'.$deal->stage_id.'" selected', false);
        $this->actingAs($operator)->get(route('deals.closed'))->assertOk()->assertSee('ns-html nd-html', false)->assertSee('Завершённые сделки');
    }

    /** У колл-центра карточка и канбан прежние (не каркас кроссовок) */
    public function test_call_center_is_unchanged(): void
    {
        $admin = User::where('role', 'admin')->firstOrFail();

        $this->actingAs($admin)->get(route('deals.kanban'))->assertOk()->assertViewIs('deals.kanban')->assertDontSee('class="ns-html"', false);
        $deal = Deal::where('account_id', $admin->account_id)->latest('id')->first();
        if ($deal) {
            $this->actingAs($admin)->get(route('deals.show', $deal))->assertOk()->assertDontSee('ns-html', false)->assertDontSee('Склад · что продаём');
        }
    }

    private function deal(User $head): Deal
    {
        $pipeline = Pipeline::where('account_id', $head->account_id)->orderByDesc('is_default')->firstOrFail();
        $stage = PipelineStage::where('pipeline_id', $pipeline->id)->orderBy('sort')->firstOrFail();
        $item = WarehouseItem::create(['account_id' => $head->account_id, 'brand' => 'NIKE', 'model' => 'DLTEST MODEL', 'size' => '42', 'quantity' => 2, 'sale_price' => 9990]);

        return Deal::create([
            'account_id' => $head->account_id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id, 'title' => 'DLTEST клиент',
            'title_is_custom' => true, 'responsible_user_id' => $head->id, 'warehouse_item_id' => $item->id, 'manual_source' => 'Avito',
        ]);
    }
}
