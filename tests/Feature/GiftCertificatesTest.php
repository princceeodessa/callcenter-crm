<?php

namespace Tests\Feature;

use App\Models\Deal;
use App\Models\GiftCertificate;
use App\Models\GiftCertificateSale;
use App\Models\IntegrationConnection;
use App\Models\User;
use App\Models\WarehouseItem;
use App\Services\Onec\SaleDocExport;
use App\Support\Sales\GiftCertificates;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Подарочные сертификаты кроссовок (10.11.2026): продажа сертификата — не выручка, остаток номинала уменьшается;
 * оплата пары сертификатом: дороже — доплата, дешевле — сдача (26.11.2026) или остаток сгорает; обналичивание
 * сертификата; выручка и маржа — от цены пары; из 1С — сумма сертификата и строка самого сертификата не становится парой.
 */
class GiftCertificatesTest extends TestCase
{
    use DatabaseTransactions;

    private User $head;
    private User $op;
    private int $acc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->acc = (int) $this->head->account_id;
        $this->op = User::where('role', 'sneaker_operator')->where('account_id', $this->acc)->firstOrFail();
        GiftCertificate::withoutGlobalScopes()->where('account_id', $this->acc)->delete();
        foreach ([10000, 15000, 20000] as $nominal) {
            GiftCertificate::withoutGlobalScopes()->create(['account_id' => $this->acc, 'nominal' => $nominal, 'stock' => 100]);
        }
    }

    private function stock(int $nominal): int
    {
        return (int) GiftCertificate::withoutGlobalScopes()->where('account_id', $this->acc)->where('nominal', $nominal)->value('stock');
    }

    private function grey(float $price): WarehouseItem
    {
        return WarehouseItem::create(['account_id' => $this->acc, 'brand' => 'CERTTEST', 'model' => 'Model '.uniqid(), 'size' => '42', 'quantity' => 3, 'avg_cost' => 5000, 'sale_price' => $price]);
    }

    public function test_selling_a_certificate_lowers_stock_and_is_not_revenue(): void
    {
        $page = $this->actingAs($this->op)->get(route('certificates.index'))->assertOk()->getContent();
        $this->assertStringContainsString('10 000 ₽', $page);
        $this->assertStringContainsString('в наличии <b>100 шт</b>', $page);
        $this->assertStringNotContainsString('Остаток и номиналы', $page, 'остаток правит только руководитель');

        $deals = Deal::count();
        $this->actingAs($this->op)->post(route('certificates.sell'), ['nominal' => 15000, 'qty' => 2, 'payment' => 'card', 'client_name' => 'Анна'])
            ->assertRedirect();
        $this->assertSame(98, $this->stock(15000));
        $this->assertSame($deals, Deal::count(), 'продажа сертификата — не сделка, не выручка');
        $sale = GiftCertificateSale::where('account_id', $this->acc)->latest('id')->firstOrFail();
        $this->assertSame(30000.0, (float) $sale->amount);
        $this->actingAs($this->op)->get(route('certificates.index', ['sold' => $sale->id]))->assertOk()->assertSee('Продан сертификат 15 000 ₽ × 2 — 30 000 ₽ · карта');

        // больше, чем осталось, — нельзя; без способа оплаты — нельзя
        GiftCertificate::withoutGlobalScopes()->where('account_id', $this->acc)->where('nominal', 20000)->update(['stock' => 1]);
        $this->actingAs($this->op)->post(route('certificates.sell'), ['nominal' => 20000, 'qty' => 2, 'payment' => 'cash'])->assertSessionHasErrors('qty');
        $this->actingAs($this->op)->post(route('certificates.sell'), ['nominal' => 20000, 'qty' => 1])->assertSessionHasErrors('payment');
        $this->assertSame(1, $this->stock(20000));

        // ошибка — отмена возвращает в остаток; чужую отменить продавец не может
        $this->actingAs($this->op)->post(route('certificates.cancel', $sale))->assertRedirect();
        $this->assertSame(100, $this->stock(15000));
        $other = GiftCertificateSale::create(['account_id' => $this->acc, 'nominal' => 10000, 'qty' => 1, 'amount' => 10000, 'payment_method' => 'cash', 'user_id' => $this->head->id, 'sold_at' => now()]);
        $this->actingAs($this->op)->post(route('certificates.cancel', $other))->assertForbidden();

        // руководитель: остаток и новый номинал
        $this->actingAs($this->head)->post(route('certificates.stock'), ['nominal' => 10000, 'stock' => 150])->assertRedirect();
        $this->assertSame(150, $this->stock(10000));
        $this->actingAs($this->head)->post(route('certificates.stock'), ['nominal' => 5000, 'stock' => 20])->assertRedirect();
        $this->assertSame(20, $this->stock(5000));
        $this->actingAs($this->op)->post(route('certificates.stock'), ['nominal' => 10000, 'stock' => 1])->assertForbidden();
    }

    public function test_paying_for_a_pair_with_a_certificate(): void
    {
        $s0 = GiftCertificates::summary($this->acc, now()->startOfDay(), now()->endOfDay());
        $page = $this->actingAs($this->op)->get(route('sale.quick'))->assertOk()->getContent();
        $this->assertStringContainsString('data-v="certificate"', $page);
        $this->assertStringContainsString('name="cert[10000]"', $page);

        // дороже сертификата — без доплаты нельзя, с доплатой картой — продажа на полную цену
        $item = $this->grey(13000);
        $this->actingAs($this->op)->post(route('sale.quick.store'), ['warehouse_item_id' => $item->id, 'qty' => 1, 'price' => 13000, 'payment' => 'certificate', 'cert' => [10000 => 1]])
            ->assertSessionHasErrors('pay_extra');
        $this->actingAs($this->op)->post(route('sale.quick.store'), ['warehouse_item_id' => $item->id, 'qty' => 1, 'price' => 13000, 'payment' => 'certificate'])
            ->assertSessionHasErrors('cert');
        $this->actingAs($this->op)->post(route('sale.quick.store'), ['warehouse_item_id' => $item->id, 'qty' => 1, 'price' => 13000, 'payment' => 'certificate', 'cert' => [10000 => 1], 'pay_extra' => 'card'])
            ->assertRedirect();
        $deal = Deal::where('warehouse_item_id', $item->id)->latest('id')->firstOrFail();
        $this->assertSame('certificate', $deal->payment_method);
        $this->assertSame(10000.0, (float) $deal->cert_value);
        $this->assertSame('card', $deal->pay_extra_method);
        $this->assertSame(13000.0, (float) $deal->amount, 'выручка — полная цена пары');
        $this->assertSame('Сертификат 10 000 ₽ + карта 3 000 ₽', GiftCertificates::dealLabel($deal));

        // дешевле сертификата — сдача клиенту (26.11.2026): выручка и маржа — от цены пары, сдача в них не входит
        $cheap = $this->grey(8000);
        $this->actingAs($this->op)->post(route('sale.quick.store'), ['warehouse_item_id' => $cheap->id, 'qty' => 1, 'price' => 8000, 'payment' => 'certificate', 'cert' => [10000 => 1], 'pay_extra' => 'cash'])
            ->assertRedirect();
        $d2 = Deal::where('warehouse_item_id', $cheap->id)->latest('id')->firstOrFail();
        $this->assertSame('cash', $d2->pay_extra_method);
        $this->assertSame('Сертификат 10 000 ₽ · сдача наличными 2 000 ₽', GiftCertificates::dealLabel($d2));
        $this->assertSame(8000.0, (float) $d2->amount);
        $this->assertSame(3000.0, $d2->sale_profit, 'маржа — цена пары 8 000 − закуп 5 000');
        $this->assertSame(2000.0, GiftCertificates::change($d2));
        $this->assertSame(0.0, GiftCertificates::burned($d2));
        $this->actingAs($this->op)->get(route('sale.quick', ['sold' => $d2->id]))->assertOk()
            ->assertSee('Выдайте клиенту сдачу с сертификата: <b>2 000 ₽</b> наличными', false);

        // способ сдачи не выбрали («Сгорает») — остаток сгорает
        $burn = $this->grey(8000);
        $this->actingAs($this->op)->post(route('sale.quick.store'), ['warehouse_item_id' => $burn->id, 'qty' => 1, 'price' => 8000, 'payment' => 'certificate', 'cert' => [10000 => 1], 'pay_extra' => ''])
            ->assertRedirect();
        $dBurn = Deal::where('warehouse_item_id', $burn->id)->latest('id')->firstOrFail();
        $this->assertNull($dBurn->pay_extra_method);
        $this->assertSame('Сертификат 10 000 ₽ (сгорело 2 000 ₽)', GiftCertificates::dealLabel($dBurn));
        $this->assertSame(2000.0, GiftCertificates::burned($dBurn));

        $before = GiftCertificates::summary($this->acc, now()->startOfDay(), now()->endOfDay());
        GiftCertificateSale::create(['account_id' => $this->acc, 'nominal' => 10000, 'qty' => 3, 'amount' => 30000, 'payment_method' => 'cash', 'user_id' => $this->op->id, 'sold_at' => now()]);
        $sum = GiftCertificates::summary($this->acc, now()->startOfDay(), now()->endOfDay());
        $this->assertSame(3, $sum['sold_qty'] - $before['sold_qty']);
        $this->assertSame(30000.0, $sum['sold_amount'] - $before['sold_amount']);
        $this->assertSame(30000.0, $sum['outstanding'] - $before['outstanding']);
        // три пары за сертификаты: номиналы 30 000, выручка 13 000 + 8 000 + 8 000, маржа 8 000 + 3 000 + 3 000
        $this->assertSame(3, $sum['redeemed_count'] - $s0['redeemed_count']);
        $this->assertEqualsWithDelta(30000.0, $sum['redeemed'] - $s0['redeemed'], 0.001);
        $this->assertEqualsWithDelta(29000.0, $sum['revenue'] - $s0['revenue'], 0.001);
        $this->assertEqualsWithDelta(14000.0, $sum['profit'] - $s0['profit'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $sum['change'] - $s0['change'], 0.001);
        $this->assertEqualsWithDelta(2000.0, $sum['burned'] - $s0['burned'], 0.001);

        // «Продажи за день»: сертификаты — отдельным блоком, у пары — как оплатили
        $day = $this->actingAs($this->head)->get(route('sale.day'))->assertOk()->getContent();
        $this->assertStringContainsString('Подарочные сертификаты', $day);
        $this->assertStringContainsString('Сертификат 10 000 ₽ + карта 3 000 ₽', $day);
        $this->assertStringContainsString('сдача с сертификатов −', $day);
        // руководителю на «Сертификатах» — выручка и маржа пар, оплаченных сертификатами; продавцу маржи нет
        $this->actingAs($this->head)->get(route('certificates.index'))->assertOk()->assertSee('Пары за сертификаты за месяц');
        $this->actingAs($this->op)->get(route('certificates.index'))->assertOk()->assertDontSee('Пары за сертификаты за месяц');

        // карточка сделки: сменить оплату на сертификат с доплатой переводом
        $d3 = $this->grey(15000);
        $this->actingAs($this->op)->post(route('sale.quick.store'), ['warehouse_item_id' => $d3->id, 'qty' => 1, 'price' => 15000, 'payment' => 'cash'])->assertRedirect();
        $deal3 = Deal::where('warehouse_item_id', $d3->id)->latest('id')->firstOrFail();
        $this->actingAs($this->op)->post(route('deals.sale-flags', $deal3), ['payment' => 'certificate', 'cert_value' => 10000, 'pay_extra' => 'transfer'])->assertRedirect();
        $this->assertSame('Сертификат 10 000 ₽ + перевод 5 000 ₽', GiftCertificates::dealLabel($deal3->fresh()));
        $this->actingAs($this->op)->post(route('deals.sale-flags', $deal3), ['payment' => 'cash'])->assertRedirect();
        $this->assertNull($deal3->fresh()->cert_value);
    }

    public function test_cashing_out_a_certificate(): void
    {
        // в транзакции теста: на руках у клиентов — только то, что продадим здесь
        GiftCertificateSale::withoutGlobalScopes()->where('account_id', $this->acc)->delete();
        Deal::withoutGlobalScopes()->where('account_id', $this->acc)->where('payment_method', 'certificate')->update(['payment_method' => 'cash']);
        $deals = Deal::withoutGlobalScopes()->count();

        // сертификаты клиентам не продавали — обналичить нечего
        $this->actingAs($this->op)->post(route('certificates.cashout'), ['nominal' => 10000, 'qty' => 1, 'payment' => 'cash'])->assertSessionHasErrors('cashout');
        $this->actingAs($this->op)->post(route('certificates.cashout'), ['nominal' => 10000, 'qty' => 1])->assertSessionHasErrors('payment');

        $this->actingAs($this->op)->post(route('certificates.sell'), ['nominal' => 15000, 'qty' => 1, 'payment' => 'card'])->assertRedirect();
        $this->assertSame(99, $this->stock(15000));
        $this->assertSame(15000.0, GiftCertificates::outstanding($this->acc));
        // два по 15 000 — больше, чем на руках
        $this->actingAs($this->op)->post(route('certificates.cashout'), ['nominal' => 15000, 'qty' => 2, 'payment' => 'cash'])->assertSessionHasErrors('cashout');

        $r = $this->actingAs($this->op)->post(route('certificates.cashout'), ['nominal' => 15000, 'qty' => 1, 'payment' => 'cash', 'client_name' => 'Олег']);
        $row = GiftCertificateSale::withoutGlobalScopes()->where('account_id', $this->acc)->latest('id')->firstOrFail();
        $r->assertRedirect(route('certificates.index', ['sold' => $row->id]));
        $this->assertTrue($row->isCashout());
        $this->assertSame(100, $this->stock(15000), 'сданный сертификат — снова в остатке');
        $this->assertSame(0.0, GiftCertificates::outstanding($this->acc));
        $this->assertSame($deals, Deal::withoutGlobalScopes()->count(), 'обналичивание — не продажа и не выручка');
        $sum = GiftCertificates::summary($this->acc, now()->startOfDay(), now()->endOfDay());
        $this->assertSame([15000.0, 1, 15000.0, 1], [$sum['sold_amount'], $sum['sold_qty'], $sum['cashed_amount'], $sum['cashed_qty']]);
        // руководителю — уведомление со ссылкой на «Сертификаты»
        $n = \App\Models\UserNotification::withoutGlobalScopes()->where('user_id', $this->head->id)->where('type', 'certificate_cashout')->where('source_id', $row->id)->firstOrFail();
        $this->assertStringContainsString('выдано наличными 15 000 ₽ · Олег', $n->body);
        $this->assertSame(route('certificates.index'), $n->payload['context_url']);

        $page = $this->actingAs($this->op)->get(route('certificates.index', ['sold' => $row->id]))->assertOk()->getContent();
        $this->assertStringContainsString('Обналичен сертификат 15 000 ₽ — выдано наличными 15 000 ₽', $page);
        $this->assertStringContainsString('Обналичили', $page);
        $this->assertStringContainsString('−15 000 ₽', $page);
        $day = $this->actingAs($this->head)->get(route('sale.day'))->assertOk()->getContent();
        $this->assertStringContainsString('обналичили 1 шт · −15 000 ₽', $day);

        // ошибка — отмена: сертификат снова у клиента, из остатка уходит, уведомление убирается
        $this->actingAs($this->op)->post(route('certificates.cancel', $row))->assertRedirect();
        $this->assertSame(99, $this->stock(15000));
        $this->assertSame(15000.0, GiftCertificates::outstanding($this->acc));
        $this->assertFalse(\App\Models\UserNotification::withoutGlobalScopes()->where('type', 'certificate_cashout')->where('source_id', $row->id)->exists());
    }

    public function test_certificate_payment_and_certificate_rows_from_1c(): void
    {
        $token = str_repeat('t', 20).bin2hex(random_bytes(16));
        IntegrationConnection::withoutGlobalScopes()->where('provider', SaleDocExport::PROVIDER)->delete();
        IntegrationConnection::withoutGlobalScopes()->create(['account_id' => $this->acc, 'provider' => SaleDocExport::PROVIDER, 'status' => 'active',
            'settings' => ['token' => $token, 'import_start_day' => '2026-10-13']]);
        $m = 'C'.strtoupper(substr(md5(uniqid('', true)), 0, 7));
        $doc = ['uuid' => 'cert-'.$m, 'type' => 'check', 'number' => 'ЗРНФ-'.$m, 'date' => now()->format('Y-m-d\TH:i:s'), 'posted' => true, 'deleted' => false,
            'payment' => 'card', 'certificate' => 15000, 'payment_extra' => 'card', 'rows' => [
                ['row' => 1, 'code' => 'НФ-1', 'name' => 'Кроссовки Nike, арт. ZZ-'.$m.', размер 42', 'qty' => 1, 'price' => 12000, 'amount' => 12000],
                ['row' => 2, 'code' => 'НФ-2', 'name' => 'Кроссовки Adidas, арт. YY-'.$m.', размер 43', 'qty' => 1, 'price' => 9000, 'amount' => 9000],
                ['row' => 3, 'code' => 'НФ-3', 'name' => 'Подарочный сертификат 10 000', 'qty' => 1, 'price' => 10000, 'amount' => 10000],
            ]];
        $res = $this->postJson(route('api.onec.imports'), ['docs' => [$doc]], ['X-Onec-Token' => $token])->assertOk()->json('results.0.rows');

        $first = Deal::findOrFail($res[0]['deal_id']);
        $this->assertSame('certificate', $first->payment_method);
        $this->assertSame(12000.0, (float) $first->cert_value);
        $second = Deal::findOrFail($res[1]['deal_id']);
        $this->assertSame('certificate', $second->payment_method);
        $this->assertSame(3000.0, (float) $second->cert_value);
        $this->assertSame('Сертификат 3 000 ₽ + карта 6 000 ₽', GiftCertificates::dealLabel($second));
        $this->assertSame('certificate', $res[2]['status'], 'строка сертификата — не пара');
        $this->assertNull($res[2]['deal_id']);
    }
}
