<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\IntegrationConnection;
use App\Models\OnecSaleImport;
use App\Models\Purchase;
use App\Models\PurchaseStage;
use App\Models\StockMark;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Onec\SaleDocExport;
use App\Services\Onec\SaleImport;
use App\Services\Warehouse\WarehouseService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * 1С «Обувь» → CRM (решение 13.10.2026): белые пары продают в 1С чеками, станция привозит чеки, CRM заводит белые
 * продажи и списывает пары; повтор не задваивает; возврат возвращает пару; отменённый в 1С чек только помечается.
 */
class OnecSaleImportTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;
    private string $marker;
    private string $token;

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->marker = 'I'.strtoupper(substr(md5(uniqid('', true)), 0, 7));
        $this->token = str_repeat('t', 20).bin2hex(random_bytes(16));
        IntegrationConnection::withoutGlobalScopes()->where('provider', SaleDocExport::PROVIDER)->delete();
        IntegrationConnection::withoutGlobalScopes()->create([
            'account_id' => $this->head->account_id,
            'provider' => SaleDocExport::PROVIDER,
            'status' => 'active',
            'settings' => ['token' => $this->token, 'import_start_day' => '2026-10-13'],
        ]);
    }

    public function test_api_needs_the_station_token_and_tells_where_to_start(): void
    {
        $this->getJson(route('api.onec.import-since'))->assertUnauthorized();
        $this->postJson(route('api.onec.imports'), ['docs' => []], ['X-Onec-Token' => 'x'.$this->token])->assertUnauthorized();
        $this->getJson(route('api.onec.import-since'), ['X-Onec-Token' => $this->token])->assertOk()->assertJson(['since' => '2026-10-13T00:00:00']);
    }

    public function test_check_becomes_a_white_sale_on_the_pair_by_article_and_size_and_is_not_doubled(): void
    {
        $item42 = $this->item('42', 1, 'IF4229-'.$this->marker);
        $item44 = $this->item('44', 1, 'IF4229-'.$this->marker);
        $doc = $this->check('c1', '2026-10-13T12:30:00', [$this->row(1, 'Кроссовки мужские Adidas Adipuff, арт. IF4229-'.$this->marker.', размер 44, цвет черный', 11390)], 'cash');

        $res = $this->send([$doc])->assertOk()->json('results');
        $this->assertSame('linked', $res[0]['rows'][0]['status']);
        $deal = Deal::findOrFail($res[0]['rows'][0]['deal_id']);
        $this->assertTrue($deal->stock_white);
        $this->assertSame($item44->id, $deal->warehouse_item_id, 'размер из карточки 1С');
        $this->assertSame('cash', $deal->payment_method);
        $this->assertEquals(11390, $deal->amount);
        $this->assertSame('2026-10-13 12:30:00', $deal->stock_deducted_at->format('Y-m-d H:i:s'), 'дата продажи — дата чека');
        $this->assertSame(0, $item44->fresh()->quantity);
        $this->assertSame(1, $item42->fresh()->quantity);

        $this->send([$doc])->assertOk();                                 // станция перечитала те же дни
        $this->assertSame(1, OnecSaleImport::where('onec_uuid', 'c1-'.$this->marker)->count());
        $this->assertSame(0, $item44->fresh()->quantity, 'повтор не списал второй раз');
    }

    public function test_marking_code_finds_the_exact_pair(): void
    {
        $item42 = $this->item('42', 1, 'DV3337-'.$this->marker);
        $item43 = $this->item('43', 1, 'DV3337-'.$this->marker);
        $code = "010460123456789021ABCDEFGH12345\x1D91EE10\x1D92abcdefghij";
        $mark = StockMark::create(['account_id' => $this->head->account_id, 'warehouse_item_id' => $item43->id, 'code' => $code, 'status' => 'in_stock']);

        // в 1С карточка «размер 42» (у «Пойзона» размеры свалены), но код маркировки — пары 43-го
        $res = $this->send([$this->check('c2', '2026-10-13T13:00:00', [$this->row(1, 'Кроссовки, арт. DV3337-'.$this->marker.', размер 42', 9000, '0104601234567890'.'21ABCDEFGH12345'."\x1D".'93dGVz')], 'card')])->json('results');
        $deal = Deal::findOrFail($res[0]['rows'][0]['deal_id']);
        $this->assertSame($item43->id, $deal->warehouse_item_id);
        $this->assertSame(1, $item42->fresh()->quantity);
        $this->assertSame('sold', $mark->fresh()->status);
        $this->assertSame($deal->id, $mark->fresh()->deal_id);
    }

    public function test_unknown_article_waits_in_sales_without_stock(): void
    {
        $res = $this->send([$this->check('c3', '2026-10-13T14:00:00', [$this->row(1, 'Кроссовки, арт. NOPE-'.$this->marker.', размер 41', 5000)])])->json('results');
        $this->assertSame('unlinked', $res[0]['rows'][0]['status']);
        $deal = Deal::findOrFail($res[0]['rows'][0]['deal_id']);
        $this->assertNull($deal->warehouse_item_id);
        $this->assertTrue(Deal::soldWithoutStock()->whereKey($deal->id)->exists(), 'встаёт в «Продажи без списания»');
    }

    public function test_return_check_puts_the_pair_back(): void
    {
        $item = $this->item('40', 1, 'RT-'.$this->marker);
        $this->send([$this->check('c4', '2026-10-13T15:00:00', [$this->row(1, 'Кроссовки, арт. RT-'.$this->marker.', размер 40', 7000)])]);
        $this->assertSame(0, $item->fresh()->quantity);

        $ret = $this->check('r4', '2026-10-13T16:00:00', [$this->row(1, 'Кроссовки, арт. RT-'.$this->marker.', размер 40', 7000)]);
        $ret['type'] = 'return';
        $ret['base_uuid'] = 'c4-'.$this->marker;
        $res = $this->send([$ret])->json('results');
        $this->assertSame('returned', $res[0]['rows'][0]['status']);
        $this->assertSame(1, $item->fresh()->quantity);
        $this->assertNotNull(Deal::findOrFail($res[0]['rows'][0]['deal_id'])->returned_at);
    }

    public function test_check_cancelled_in_1c_after_import_is_only_flagged(): void
    {
        $item = $this->item('39', 1, 'CN-'.$this->marker);
        $doc = $this->check('c5', '2026-10-13T17:00:00', [$this->row(1, 'Кроссовки, арт. CN-'.$this->marker.', размер 39', 6000)]);
        $this->send([$doc]);
        $doc['deleted'] = true;
        $this->send([$doc]);

        $rec = OnecSaleImport::where('onec_uuid', 'c5-'.$this->marker)->firstOrFail();
        $this->assertSame('cancelled_in_1c', $rec->status);
        $this->assertSame(0, $item->fresh()->quantity, 'сделку и склад правят руками');
        $this->actingAs($this->head)->get(route('onec.index'))->assertOk()->assertSee('Продажи из 1С')->assertSee('в 1С документ отменён');
    }

    public function test_check_for_a_sale_already_made_in_crm_is_tied_to_it_not_doubled(): void
    {
        // 10.10 продали в CRM р.37, 15.10 продавцы пробили эту же продажу чеком в 1С — раньше CRM списывала ещё и р.47
        $item37 = $this->item('37', 1, 'IG6813-'.$this->marker);
        $item47 = $this->item('47', 1, 'IG6813-'.$this->marker);
        $crmSale = $this->crmWhiteSale($item37, 9599, '2026-10-10 19:04:00');
        $this->assertSame(0, $item37->fresh()->quantity);

        $res = $this->send([$this->check('d1', '2026-10-15T17:20:50', [$this->row(1, 'Кроссовки мужские Adidas Adipuff, арт. IG6813-'.$this->marker.', размер 37, цвет белый/черный', 9599)])])
            ->assertOk()->json('results');
        $this->assertSame('linked', $res[0]['rows'][0]['status']);
        $this->assertSame($crmSale->id, $res[0]['rows'][0]['deal_id'], 'чек привязан к продаже из CRM');
        $this->assertSame(1, $item47->fresh()->quantity, 'чужой размер не списан');
        $this->assertSame(0, Deal::where('title', 'like', 'Продажа (1С)%')->where('warehouse_item_id', $item47->id)->count());
        $this->assertSame('2026-10-10 19:04:00', $crmSale->fresh()->stock_deducted_at->format('Y-m-d H:i:s'), 'дата продажи — настоящая');

        // возврат по этому чеку возвращает пару той сделки
        $ret = $this->check('dr1', '2026-10-16T12:00:00', [$this->row(1, 'Кроссовки мужские Adidas Adipuff, арт. IG6813-'.$this->marker.', размер 37, цвет белый/черный', 9599)]);
        $ret['type'] = 'return';
        $ret['base_uuid'] = 'd1-'.$this->marker;
        $this->send([$ret])->assertOk();
        $this->assertSame(1, $item37->fresh()->quantity);
        $this->assertNotNull($crmSale->fresh()->returned_at);
    }

    public function test_sold_out_size_is_not_written_off_from_another_size(): void
    {
        $item38 = $this->item('38', 1, 'UW-'.$this->marker);
        $item395 = $this->item('39.5', 1, 'UW-'.$this->marker);
        $this->crmWhiteSale($item395, 12000, '2026-10-10 16:39:00');          // другая сумма — не та же продажа

        $res = $this->send([$this->check('d2', '2026-10-15T18:27:00', [$this->row(1, 'Кроссовки мужские New Balance WRPD Runner, арт. UW-'.$this->marker.', размер 39.5, цвет серый', 13499)])])->json('results');
        $this->assertSame('unlinked', $res[0]['rows'][0]['status']);
        $this->assertStringContainsString('уже продана', OnecSaleImport::where('onec_uuid', 'd2-'.$this->marker)->value('note'));
        $this->assertSame(1, $item38->fresh()->quantity);
    }

    public function test_poizon_whole_size_goes_to_the_nearest_crm_size_only_when_it_is_clear(): void
    {
        $item39 = $this->item('39', 1, 'PZ-'.$this->marker);
        $item405 = $this->item('40.5', 1, 'PZ-'.$this->marker);
        $res = $this->send([$this->check('d3', '2026-10-16T12:00:00', [$this->row(1, 'Кроссовки, арт. PZ-'.$this->marker.', размер 40, цвет черный', 11390)])])->json('results');
        $this->assertSame($item405->id, Deal::findOrFail($res[0]['rows'][0]['deal_id'])->warehouse_item_id, 'в 1С «40», у нас 40.5');
        $this->assertSame(1, $item39->fresh()->quantity);

        $this->item('42', 1, 'PZ-'.$this->marker);
        $this->item('43', 1, 'PZ-'.$this->marker);
        $res = $this->send([$this->check('d4', '2026-10-16T13:00:00', [$this->row(1, 'Кроссовки, арт. PZ-'.$this->marker.', размер 42.5, цвет черный', 11390)])])->json('results');
        $this->assertSame('unlinked', $res[0]['rows'][0]['status'], '42 и 43 одинаково близко — пару выбирают руками');

        $res = $this->send([$this->check('d5', '2026-10-16T14:00:00', [$this->row(1, 'Кроссовки, арт. PZ-'.$this->marker.', размер 44, цвет черный', 11390)])])->json('results');
        $this->assertSame('unlinked', $res[0]['rows'][0]['status'], '43 — не соседний полуразмер для 44');
        $this->assertSame(1, WarehouseItem::where('model', 'Model '.$this->marker)->where('size', '43')->value('quantity'));
    }

    public function test_marking_key_and_card_name_parsing(): void
    {
        $this->assertSame('0104601234567890211234567890123', SaleImport::markingKey("]C1010460123456789021123456789012391EE10"));
        $this->assertSame('010460123456789021ABC', SaleImport::markingKey("010460123456789021ABC\x1D91EE10"));
        $this->assertNull(SaleImport::markingKey('2000000012345'));
        $this->assertSame(['IF4229', 44.0], SaleImport::articleAndSize('Кроссовки мужские Adidas Adipuff, арт. IF4229, размер 44, цвет черный'));
        $this->assertSame(['UWRPDCCE', 42.67], SaleImport::articleAndSize('NEW BALANCE WRPD Runner UWRPDCCE 42 2/3 р-р'));
        $this->assertSame(['UWRPDCCE', 39.5], SaleImport::articleAndSize('Кроссовки мужские New Balance WRPD Runner, арт. UWRPDCCE, размер 39.5, цвет серый'));
        $this->assertSame(['HF0263-401', 42.5], SaleImport::articleAndSize('Кроссовки мужские Nike Cortez, арт. HF0263-401, размер 42,5, цвет синий'));
    }

    /** Белая продажа, внесённая в CRM до 13.10.2026 (тогда белые продавали в CRM). */
    private function crmWhiteSale(WarehouseItem $item, float $amount, string $at): Deal
    {
        $pipeline = \App\Models\Pipeline::where('account_id', $this->head->account_id)->orderByDesc('is_default')->firstOrFail();
        $stage = \App\Models\PipelineStage::where('account_id', $this->head->account_id)->where('pipeline_id', $pipeline->id)
            ->where('is_final', 1)->orderBy('sort')->firstOrFail();
        $deal = Deal::create([
            'account_id' => $this->head->account_id, 'pipeline_id' => $pipeline->id, 'stage_id' => $stage->id,
            'title' => 'Продажа: '.$item->display_name, 'title_is_custom' => 1, 'responsible_user_id' => $this->head->id,
            'amount' => $amount, 'currency' => 'RUB', 'product_category' => 'sneakers',
            'closed_at' => $at, 'closed_result' => 'won', 'closed_by_user_id' => $this->head->id,
            'warehouse_item_id' => $item->id, 'sold_quantity' => 1, 'stock_white' => true,
        ]);
        app(WarehouseService::class)->syncDealStock($deal);
        $deal->refresh()->forceFill(['stock_deducted_at' => $at])->save();

        return $deal->fresh();
    }

    private function send(array $docs)
    {
        return $this->postJson(route('api.onec.imports'), ['docs' => $docs], ['X-Onec-Token' => $this->token]);
    }

    private function check(string $id, string $date, array $rows, ?string $payment = null): array
    {
        return ['uuid' => $id.'-'.$this->marker, 'type' => 'check', 'number' => 'ЗРНФ-'.$id, 'date' => $date,
            'posted' => true, 'deleted' => false, 'payment' => $payment, 'rows' => $rows];
    }

    private function row(int $n, string $name, float $amount, ?string $mark = null): array
    {
        return ['row' => $n, 'code' => 'НФ-'.$n, 'name' => $name, 'qty' => 1, 'price' => $amount, 'amount' => $amount, 'mark' => $mark];
    }

    private function item(string $size, int $qty, string $article): WarehouseItem
    {
        $item = WarehouseItem::create([
            'account_id' => $this->head->account_id, 'brand' => 'IMPTEST', 'model' => 'Model '.$this->marker,
            'size' => $size, 'quantity' => $qty, 'avg_cost' => 3000, 'sale_price' => 9000,
        ]);
        $stage = PurchaseStage::where('account_id', $this->head->account_id)->where('is_stock_in', true)->firstOrFail();
        Purchase::create([
            'account_id' => $this->head->account_id, 'purchase_stage_id' => $stage->id, 'title' => $item->display_name,
            'brand' => $item->brand, 'model' => $item->model, 'size' => $size, 'quantity' => $qty, 'article' => $article,
            'is_white' => true, 'stocked_at' => now()->subDays(10), 'stocked_quantity' => $qty, 'warehouse_item_id' => $item->id,
        ]);

        return $item;
    }
}
