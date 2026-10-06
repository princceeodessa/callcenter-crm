<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\IntegrationConnection;
use App\Models\OnecSaleDoc;
use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Onec\SaleDocExport;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Белые пары → 1С «Обувь»: какая пара белая, оплата, документ на каждую продажу и возврат, API станции.
 */
class WhiteSalesOnecTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;
    private string $marker;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->marker = 'W'.strtoupper(substr(md5(uniqid('', true)), 0, 7));
        $this->token = str_repeat('t', 20).bin2hex(random_bytes(16));
        // Подключение станции: одно на пространство — тестовое заменяет имеющееся только внутри транзакции теста.
        IntegrationConnection::withoutGlobalScopes()->where('provider', SaleDocExport::PROVIDER)->delete();
        IntegrationConnection::withoutGlobalScopes()->create([
            'account_id' => $this->head->account_id,
            'provider' => SaleDocExport::PROVIDER,
            'status' => 'active',
            'settings' => ['token' => $this->token, 'start_day' => now()->subDays(5)->toDateString()],
        ]);
    }

    public function test_white_split_counts_white_purchases_minus_white_sales(): void
    {
        $item = $this->item('42', qty: 3);
        $this->whitePurchase($item, 2);                    // 2 белые + 1 старая (серая) пара
        $ws = app(WarehouseService::class);

        $this->assertSame(['white' => 2, 'other' => 1], $ws->whiteSplit($item));
        $this->assertNull($ws->guessWhite($item), 'есть и те и другие — решает продавец');

        $this->sell($item, ['white' => '1', 'payment' => 'card']);
        $this->assertSame(['white' => 1, 'other' => 1], $ws->whiteSplit($item->fresh()));
    }

    public function test_quick_sale_white_only_size_is_white_and_needs_payment(): void
    {
        $item = $this->item('43', qty: 1);
        $this->whitePurchase($item, 1);

        $this->actingAs($this->head)->post(route('sale.quick.store'), ['warehouse_item_id' => $item->id, 'qty' => 1])
            ->assertRedirect(route('sale.quick', ['item' => $item->id]))
            ->assertSessionHasErrors('payment');
        $this->assertSame(1, $item->fresh()->quantity, 'без оплаты не продали');

        $deal = $this->sell($item, ['payment' => 'cash']);
        $this->assertTrue($deal->stock_white);
        $this->assertSame('cash', $deal->payment_method);
        $this->assertSame(0, $item->fresh()->quantity);
    }

    public function test_quick_sale_grey_size_is_not_white_and_payment_optional(): void
    {
        $item = $this->item('44', qty: 2);

        $deal = $this->sell($item, []);
        $this->assertFalse($deal->stock_white);
        $this->assertNull($deal->payment_method);
    }

    public function test_mixed_size_asks_the_seller(): void
    {
        $item = $this->item('45', qty: 2);
        $this->whitePurchase($item, 1);

        $this->actingAs($this->head)->post(route('sale.quick.store'), ['warehouse_item_id' => $item->id, 'qty' => 1, 'payment' => 'card'])
            ->assertSessionHasErrors('white');

        $deal = $this->sell($item, ['white' => '0']);
        $this->assertFalse($deal->stock_white);
        $this->assertSame(['white' => 1, 'other' => 0], app(WarehouseService::class)->whiteSplit($item->fresh()));
    }

    public function test_quick_sale_page_marks_white_sizes(): void
    {
        $white = $this->item('41', qty: 1);
        $this->whitePurchase($white, 1);

        $html = $this->actingAs($this->head)->get(route('sale.quick'))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-item="'.$white->id.'"[^>]*data-white="1"[^>]*data-other="0"/s', $html);
        $this->assertStringContainsString('Белая пара — уйдёт в 1С', $html);
        $this->assertStringContainsString('name="payment"', $html);
    }

    public function test_station_gets_each_sale_after_the_cooling_window_and_reports_back(): void
    {
        $item = $this->item('42', qty: 3);
        $this->whitePurchase($item, 3, article: 'DV3337-010');
        $deal = $this->sell($item, ['payment' => 'card', 'price' => 13000]);
        $deal->forceFill(['stock_deducted_at' => now()->subMinutes(30)])->save();
        $fresh = $this->sell($item, ['payment' => 'cash']);   // только что — ещё можно поправить

        $this->getJson(route('api.onec.pending'))->assertUnauthorized();
        $this->getJson(route('api.onec.pending'), ['X-Onec-Token' => 'x'.$this->token])->assertUnauthorized();

        $docs = collect($this->getJson(route('api.onec.pending'), ['X-Onec-Token' => $this->token])->assertOk()->json('docs'));
        $doc = $docs->firstWhere('key', 'sale-'.$deal->id);
        $this->assertNotNull($doc, 'продажа 30 минут назад ждёт выгрузки');
        $this->assertNull($docs->firstWhere('key', 'sale-'.$fresh->id), 'свежая продажа ждёт '.SaleDocExport::COOLING_MINUTES.' минут');
        $this->assertSame('sale', $doc['kind']);
        $this->assertSame('DV3337-010', $doc['row']['article']);
        $this->assertSame(13000, (int) $doc['row']['amount']);
        $this->assertSame('card', $doc['row']['payment']);
        $this->assertSame($deal->stock_deducted_at->format('Y-m-d\\TH:i:s'), $doc['date']);

        $this->postJson(route('api.onec.results'), ['results' => [[
            'deal_id' => $deal->id, 'kind' => 'sale', 'status' => 'done', 'hash' => $doc['hash'],
            'onec_uuid' => '11111111-2222-3333-4444-555555555555', 'onec_number' => 'ЗРНФ-000005',
            'onec_date' => $doc['date'], 'card_code' => 'НФ-00012269', 'amount' => 13000,
        ]]], ['X-Onec-Token' => $this->token])->assertOk()->assertJson(['ok' => true, 'saved' => 1]);

        $rec = OnecSaleDoc::withoutGlobalScopes()->where('deal_id', $deal->id)->where('kind', 'sale')->firstOrFail();
        $this->assertSame('done', $rec->status);
        $this->assertSame('ЗРНФ-000005', $rec->onec_number);
        $docs = collect($this->getJson(route('api.onec.pending'), ['X-Onec-Token' => $this->token])->json('docs'));
        $this->assertNull($docs->firstWhere('key', 'sale-'.$deal->id), 'выгруженная продажа больше не просится');

        // Поправили оплату — документ в 1С надо пересобрать.
        $this->actingAs($this->head)->post(route('deals.sale-flags', $deal), ['payment' => 'cash']);
        $this->assertNotNull(collect($this->getJson(route('api.onec.pending'), ['X-Onec-Token' => $this->token])->json('docs'))->firstWhere('key', 'sale-'.$deal->id));
        $rec->forceFill(['rows_hash' => collect($this->getJson(route('api.onec.pending'), ['X-Onec-Token' => $this->token])->json('docs'))->firstWhere('key', 'sale-'.$deal->id)['hash']])->save();

        // Возврат: продажа остаётся как была (с той же датой), возврат — отдельным документом на ту же карточку.
        app(WarehouseService::class)->returnDealStock($deal->fresh());
        $docs = collect($this->getJson(route('api.onec.pending'), ['X-Onec-Token' => $this->token])->json('docs'));
        $this->assertNull($docs->firstWhere('key', 'sale-'.$deal->id), 'продажа после возврата не меняется');
        $ret = $docs->firstWhere('key', 'return-'.$deal->id);
        $this->assertNotNull($ret, 'возврат ждёт выгрузки');
        $this->assertSame('НФ-00012269', $ret['row']['card_code']);
        $this->assertSame('return', $ret['kind']);
    }

    public function test_sale_returned_before_export_never_goes_to_1c(): void
    {
        $item = $this->item('43', qty: 1);
        $this->whitePurchase($item, 1);
        $deal = $this->sell($item, ['payment' => 'cash']);
        $deal->forceFill(['stock_deducted_at' => now()->subHour()])->save();
        app(WarehouseService::class)->returnDealStock($deal->fresh());

        $keys = collect(app(SaleDocExport::class)->pending($this->head->account_id, now()->subDays(5)->toDateString()))->pluck('key');
        $this->assertNotContains('sale-'.$deal->id, $keys);
        $this->assertNotContains('return-'.$deal->id, $keys);
    }

    public function test_waiting_or_failed_document_is_retried_later_or_when_it_changes(): void
    {
        $item = $this->item('40', qty: 1);
        $this->whitePurchase($item, 1, article: 'ZZ-NO-CARD');
        $deal = $this->sell($item, ['payment' => 'cash']);
        $deal->forceFill(['stock_deducted_at' => now()->subHours(2)])->save();
        $export = app(SaleDocExport::class);
        $start = now()->subDays(5)->toDateString();
        $doc = collect($export->pending($this->head->account_id, $start))->firstWhere('key', 'sale-'.$deal->id);

        $export->applyResults($this->head->account_id, [['deal_id' => $deal->id, 'kind' => 'sale', 'status' => 'waiting', 'hash' => $doc['hash'], 'reason' => 'в 1С нет прихода артикула ZZ-NO-CARD']]);
        $this->assertNull(collect($export->pending($this->head->account_id, $start))->firstWhere('key', 'sale-'.$deal->id), 'ждёт прихода — повтор не сразу');
        OnecSaleDoc::withoutGlobalScopes()->where('deal_id', $deal->id)->update(['attempted_at' => now()->subHours(SaleDocExport::RETRY_HOURS + 1)]);
        $this->assertNotNull(collect($export->pending($this->head->account_id, $start))->firstWhere('key', 'sale-'.$deal->id), 'через несколько часов — снова');

        $export->applyResults($this->head->account_id, [['deal_id' => $deal->id, 'kind' => 'sale', 'status' => 'error', 'hash' => $doc['hash'], 'error' => 'нет связи']]);
        $deal->forceFill(['amount' => 9999])->save();
        $this->assertNotNull(collect($export->pending($this->head->account_id, $start))->firstWhere('key', 'sale-'.$deal->id), 'поменяли продажу — сразу');
    }

    public function test_exported_sale_made_grey_shows_up_for_manual_cleanup(): void
    {
        $item = $this->item('41', qty: 1);
        $this->whitePurchase($item, 1);
        $deal = $this->sell($item, ['payment' => 'cash']);
        OnecSaleDoc::withoutGlobalScopes()->create([
            'account_id' => $this->head->account_id, 'deal_id' => $deal->id, 'kind' => 'sale', 'status' => 'done',
            'rows_hash' => 'x', 'onec_number' => 'ЗРНФ-000007', 'onec_date' => now()->subHour(),
        ]);
        $this->actingAs($this->head)->post(route('deals.sale-flags', $deal), ['white' => '0']);

        $cancelled = app(SaleDocExport::class)->cancelledInCrm($this->head->account_id);
        $this->assertTrue($cancelled->contains('deal_id', $deal->id));
        $this->actingAs($this->head)->get(route('onec.index'))->assertOk()->assertSee('пометьте на удаление вручную')->assertSee('ЗРНФ-000007');
    }

    public function test_onec_page_and_flags(): void
    {
        $item = $this->item('39', qty: 2);
        $this->whitePurchase($item, 1);
        $deal = $this->sell($item, ['white' => '1']);   // белая, но без оплаты нельзя
        $this->assertNull($deal);
        $deal = $this->sell($item, ['white' => '1', 'payment' => 'transfer']);

        $this->actingAs($this->head)->get(route('onec.index'))->assertOk()
            ->assertSee('1С: продажи белых пар')->assertSee('Документы в 1С')->assertSee('можно поправить');

        $operator = User::where('role', 'sneaker_operator')->where('account_id', $this->head->account_id)->firstOrFail();
        $this->actingAs($operator)->get(route('onec.index'))->assertForbidden();

        $this->actingAs($operator)->post(route('deals.sale-flags', $deal), ['white' => '0', 'payment' => 'card'])->assertRedirect();
        $deal->refresh();
        $this->assertFalse($deal->stock_white);
        $this->assertSame('card', $deal->payment_method);
        $this->actingAs($this->head)->get(route('deals.show', $deal))->assertOk()->assertSee('Пара')->assertSee('серая');
    }

    private function item(string $size, int $qty): WarehouseItem
    {
        return WarehouseItem::create([
            'account_id' => $this->head->account_id,
            'brand' => 'WHITETEST',
            'model' => 'Model '.$this->marker,
            'size' => $size,
            'quantity' => $qty,
            'avg_cost' => 7000,
            'sale_price' => 12000,
        ]);
    }

    private function whitePurchase(WarehouseItem $item, int $qty, string $article = 'WT-ART'): Purchase
    {
        $stage = PurchaseStage::where('account_id', $this->head->account_id)->where('is_stock_in', true)->firstOrFail();

        return Purchase::create([
            'account_id' => $this->head->account_id,
            'purchase_stage_id' => $stage->id,
            'title' => $item->display_name,
            'brand' => $item->brand,
            'model' => $item->model,
            'size' => $item->size,
            'quantity' => $qty,
            'article' => $article,
            'is_white' => true,
            'stocked_at' => now()->subDays(10),
            'stocked_quantity' => $qty,
            'warehouse_item_id' => $item->id,
        ]);
    }

    /** Быстрая продажа; null — если не продалось (ошибка формы). */
    private function sell(WarehouseItem $item, array $extra): ?Deal
    {
        $before = Deal::where('warehouse_item_id', $item->id)->max('id');
        $this->actingAs($this->head)->post(route('sale.quick.store'), ['warehouse_item_id' => $item->id, 'qty' => 1] + $extra);
        $deal = Deal::where('warehouse_item_id', $item->id)->latest('id')->first();

        return $deal && $deal->id !== $before ? $deal : null;
    }
}
