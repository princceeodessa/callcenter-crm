<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\IntegrationConnection;
use App\Models\OnecRetailDay;
use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Onec\RetailDayExport;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Белые пары → 1С «Обувь»: какая пара белая, оплата, дни для отчёта о розничных продажах и API станции.
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
        IntegrationConnection::withoutGlobalScopes()->where('provider', RetailDayExport::PROVIDER)->delete();
        IntegrationConnection::withoutGlobalScopes()->create([
            'account_id' => $this->head->account_id,
            'provider' => RetailDayExport::PROVIDER,
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

    public function test_station_gets_finished_days_and_reports_back(): void
    {
        $item = $this->item('42', qty: 2);
        $this->whitePurchase($item, 2, article: 'DV3337-010');
        $deal = $this->sell($item, ['payment' => 'card', 'price' => 13000]);
        $deal->forceFill(['stock_deducted_at' => now()->subDay()->setTime(15, 0)])->save();
        $yesterday = now()->subDay()->toDateString();

        $this->getJson(route('api.onec.pending'))->assertUnauthorized();
        $this->getJson(route('api.onec.pending'), ['X-Onec-Token' => 'x'.$this->token])->assertUnauthorized();

        $res = $this->getJson(route('api.onec.pending'), ['X-Onec-Token' => $this->token])->assertOk()->json();
        $day = collect($res['days'])->firstWhere('day', $yesterday);
        $this->assertNotNull($day, 'вчерашний день ждёт выгрузки');
        $row = collect($day['rows'])->firstWhere('deal_id', $deal->id);
        $this->assertSame('sale', $row['kind']);
        $this->assertSame('DV3337-010', $row['article']);
        $this->assertSame(13000, (int) $row['amount']);
        $this->assertSame('card', $row['payment']);

        // Сегодняшняя белая продажа пока не уходит — день не закончен.
        $todayDeal = $this->sell($item, ['payment' => 'cash']);
        $this->assertNull(collect($res['days'])->firstWhere('day', now()->toDateString()));

        $this->postJson(route('api.onec.result', $yesterday), [
            'status' => 'done', 'hash' => $day['hash'], 'onec_uuid' => '11111111-2222-3333-4444-555555555555', 'onec_number' => 'ЗРНФ-000001',
            'pairs' => 1, 'amount' => 13000, 'mapped' => [['deal_id' => $deal->id, 'kind' => 'sale', 'card_code' => 'НФ-00012270']],
        ], ['X-Onec-Token' => $this->token])->assertOk()->assertJson(['ok' => true, 'status' => 'done']);

        $deal->refresh();
        $this->assertSame($yesterday, $deal->onec_sale_day->toDateString());
        $this->assertSame('НФ-00012270', $deal->onec_card_code);
        $this->assertSame('done', OnecRetailDay::withoutGlobalScopes()->where('day', $yesterday)->where('account_id', $this->head->account_id)->value('status'));
        $res = $this->getJson(route('api.onec.pending'), ['X-Onec-Token' => $this->token])->json();
        $this->assertNull(collect($res['days'])->firstWhere('day', $yesterday), 'выгруженный день больше не просится');

        // Возврат вчерашней продажи (вчера же, после выгрузки): продажа остаётся в дне, добавляется строка возврата.
        app(WarehouseService::class)->returnDealStock($deal->fresh());
        $deal->refresh()->forceFill(['returned_at' => now()->subDay()->setTime(18, 0)])->save();
        $res = $this->getJson(route('api.onec.pending'), ['X-Onec-Token' => $this->token])->json();
        $day = collect($res['days'])->firstWhere('day', $yesterday);
        $this->assertNotNull($day, 'день изменился — выгрузить заново');
        $this->assertSame(['return', 'sale'], collect($day['rows'])->where('deal_id', $deal->id)->pluck('kind')->sort()->values()->all());
        $ret = collect($day['rows'])->firstWhere('kind', 'return');
        $this->assertSame('НФ-00012270', $ret['card_code'], 'возврат — на ту же карточку 1С');
        $this->assertNotNull($todayDeal);
    }

    public function test_partial_day_is_retried_later_and_error_keeps_day_pending(): void
    {
        $item = $this->item('40', qty: 1);
        $this->whitePurchase($item, 1, article: 'ZZ-NO-CARD');
        $deal = $this->sell($item, ['payment' => 'cash']);
        $deal->forceFill(['stock_deducted_at' => now()->subDays(2)->setTime(12, 0)])->save();
        $d2 = now()->subDays(2)->toDateString();
        $export = app(RetailDayExport::class);

        $day = collect($export->pending($this->head->account_id, now()->subDays(5)->toDateString()))->firstWhere('day', $d2);
        $export->applyResult($this->head->account_id, $d2, ['status' => 'error', 'error' => 'нет связи с 1С']);
        $this->assertNotNull(collect($export->pending($this->head->account_id, now()->subDays(5)->toDateString()))->firstWhere('day', $d2), 'после ошибки день снова в очереди (хэш не записан)');

        $export->applyResult($this->head->account_id, $d2, ['status' => 'partial', 'hash' => $day['hash'], 'unmapped' => [['deal_id' => $deal->id, 'reason' => 'в 1С нет прихода артикула ZZ-NO-CARD']]]);
        $this->assertNull(collect($export->pending($this->head->account_id, now()->subDays(5)->toDateString()))->firstWhere('day', $d2), 'частичный — повтор не сразу');
        OnecRetailDay::withoutGlobalScopes()->where('day', $d2)->update(['attempted_at' => now()->subHours(RetailDayExport::RETRY_HOURS + 1)]);
        $this->assertNotNull(collect($export->pending($this->head->account_id, now()->subDays(5)->toDateString()))->firstWhere('day', $d2), 'через несколько часов — снова');
    }

    public function test_onec_page_and_flags(): void
    {
        $item = $this->item('39', qty: 2);
        $this->whitePurchase($item, 1);
        $deal = $this->sell($item, ['white' => '1']);   // белая, но без оплаты нельзя
        $this->assertNull($deal);
        $deal = $this->sell($item, ['white' => '1', 'payment' => 'transfer']);

        $this->actingAs($this->head)->get(route('onec.index'))->assertOk()
            ->assertSee('1С: продажи белых пар')->assertSee('Сегодня белых пар');

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
