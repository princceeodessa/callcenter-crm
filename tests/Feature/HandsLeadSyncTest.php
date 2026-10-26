<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\IntegrationConnection;
use App\Models\User;
use App\Services\Integrations\HandsLeadSync;
use App\Services\Owner\CeilingsKpi;
use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Заказы «Руки» (hands.ru) → лиды потолков: новый заказ — сделка в «Поступил лид» с контактом и текстом заказа,
 * каждый заказ один раз; номер с открытой сделкой — в неё; история до подключения не заводится; ключ не показывается.
 */
class HandsLeadSyncTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private array $stage = [];
    private array $orders = [];
    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 10:00:00');
        $this->acc = Account::create(['name' => 'Потолки (тест Рук)'])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['lead' => ['Поступил лид', 10], 'work' => ['Взяли в работу', 20], 'measure' => ['Замер назначен', 40]] as $k => [$name, $sort]) {
            $this->stage[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $pipeline, 'name' => $name, 'sort' => $sort, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->orders = [$this->order('7001', '2026-10-26 09:12:00', '+7 (912) 000-11-22'), $this->order('6900', '2026-10-20 12:00:00', '+79120003344')];
        Http::$fake = function (string $method, string $url, array $headers) {
            $this->calls[] = $url;
            if (($headers['X-Api-Key'] ?? '') !== 'KEY1') {
                return [403, json_encode(['error' => 'Неверный ключ'])];
            }
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $list = ($q['status'] ?? '') === 'ACTIVE'
                ? array_values(array_filter($this->orders, fn ($o) => in_array($o['status'], ['Назначен', 'Договорились'], true)))
                : array_values(array_filter($this->orders, fn ($o) => substr($o['creation_time'], 0, 10) >= $q['date_from'] && substr($o['creation_time'], 0, 10) <= $q['date_to']));

            return [200, json_encode(['orders' => $list, 'page' => 1, 'per_page' => 500, 'pages' => 1, 'total' => count($list)])];
        };
    }

    protected function tearDown(): void
    {
        Http::$fake = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function order(string $id, string $at, string $phone): array
    {
        return ['id' => $id, 'client_name' => 'Анна Петрова', 'client_phones' => [$phone], 'address' => 'г. Ижевск, ул. Ленина, д. 1, п. 4',
            'directions' => 'кв. 45, эт. 6', 'title' => "ПЗ Натяжной потолок на заказ\nSU Замер потолка", 'price' => '0', 'creation_time' => $at,
            'work_time' => '2026-10-27 10:00:00', 'specialist' => 'Картапов Сергей', 'status' => 'Назначен', 'payment_status' => 'Ожидает оплаты',
            'shop_name' => 'LM_Izhevsk115', 'comment' => 'Позвонить после 18', 'full_comment' => "Услуга: Замер потолка\nКомментарий клиента: Позвонить после 18",
            'delivery_date' => '', 'works' => [['name' => 'Замер потолка', 'description' => '', 'quantity' => '1', 'price' => '0', 'unit' => 'VISIT']], 'files' => [], 'review' => null];
    }

    private function conn(): IntegrationConnection
    {
        return IntegrationConnection::withoutGlobalScopes()->create(['account_id' => $this->acc, 'provider' => 'hands', 'status' => 'active',
            'settings' => ['api_key' => 'KEY1', 'import_since' => '2026-10-23']]);
    }

    public function test_new_order_becomes_a_lead_once(): void
    {
        $conn = $this->conn();
        $this->artisan('integrations:hands-poll')->assertExitCode(0);

        $deals = DB::table('deals')->where('account_id', $this->acc)->get();
        $this->assertCount(1, $deals, 'заказ 20.10 — до подключения, не заводится');
        $deal = $deals->first();
        $this->assertSame([$this->stage['lead'], 'Анна Петрова — Руки (hands.ru)', 1], [(int) $deal->stage_id, $deal->title, (int) $deal->is_unread]);
        $this->assertSame('79120001122', DB::table('contacts')->where('id', $deal->contact_id)->value('phone'));
        $act = DB::table('deal_activities')->where('deal_id', $deal->id)->where('type', 'lead_form')->first();
        $this->assertSame(['hands', '7001'], [json_decode($act->payload, true)['provider'], json_decode($act->payload, true)['external_id']]);
        foreach (['Заказ «Руки» (hands.ru) № 7001 от 26.10.2026 09:12 · магазин LM_Izhevsk115', 'Что: Замер потолка', 'Адрес: г. Ижевск, ул. Ленина, д. 1, п. 4, кв. 45, эт. 6',
            'Время работ в Руках: 27.10.2026 10:00', 'В Руках: Назначен · мастер Картапов Сергей', 'Комментарий: Услуга: Замер потолка'] as $line) {
            $this->assertStringContainsString($line, $act->body);
        }
        $this->assertSame('Руки (hands.ru) · Леруа Мерлен', CeilingsKpi::channelLabel((new CeilingsKpi($this->acc))->channelsFor([(int) $deal->id])[(int) $deal->id]));
        $this->assertNotNull($conn->fresh()->last_synced_at);

        $this->artisan('integrations:hands-poll')->assertExitCode(0);
        $this->assertSame(1, DB::table('deals')->where('account_id', $this->acc)->count(), 'повторный опрос не задваивает');
        $this->assertStringContainsString('date_from=2026-10-24', $this->calls[0], 'окно — последние дни, не раньше подключения');
    }

    /** Мастер в Руках уже договорился — сразу «Замер назначен»; договорился позже — нетронутая сделка переходит сама. */
    public function test_agreed_orders_go_straight_to_measurement(): void
    {
        $this->conn();
        $this->orders[] = ['status' => 'Договорились', 'id' => '7002', 'work_time' => '2026-10-28 15:00:00'] + $this->order('7002', '2026-10-25 11:00:00', '+79120005566');
        $this->orders[] = $this->order('7003', '2026-10-25 12:00:00', '+79120007788');
        $this->artisan('integrations:hands-poll')->assertExitCode(0);

        $deal = fn (string $order) => DB::table('deals')->where('id', DB::table('deal_activities')->where('type', 'lead_form')->where('payload->external_id', $order)->value('deal_id'))->first();
        $this->assertSame($this->stage['measure'], (int) $deal('7002')->stage_id);
        $this->assertSame($this->stage['lead'], (int) $deal('7001')->stage_id, '«Назначен» — колл-центру');
        $this->assertSame(1, DB::table('deal_stage_history')->where('deal_id', $deal('7002')->id)->where('to_stage_id', $this->stage['measure'])->whereNull('changed_by_user_id')->count());
        $this->assertStringContainsString('Руки: мастер договорился с клиентом, замер 28.10.2026 15:00 — этап «Замер назначен»',
            DB::table('deal_activities')->where('deal_id', $deal('7002')->id)->where('type', 'stage_changed')->value('body'));

        // позже: по 7001 договорились — сделка ещё на первом этапе, переходит; 7003 оператор уже взял в работу — не трогаем
        DB::table('deals')->where('id', $deal('7003')->id)->update(['stage_id' => $this->stage['work']]);
        foreach ($this->orders as &$o) {
            if (in_array($o['id'], ['7001', '7003'], true)) {
                $o['status'] = 'Договорились';
            }
        }
        unset($o);
        $this->artisan('integrations:hands-poll')->assertExitCode(0);
        $this->assertSame($this->stage['measure'], (int) $deal('7001')->stage_id);
        $this->assertSame($this->stage['work'], (int) $deal('7003')->stage_id, 'работу оператора не перебиваем');
        $this->artisan('integrations:hands-poll')->assertExitCode(0);
        $this->assertSame(1, DB::table('deal_stage_history')->where('deal_id', $deal('7001')->id)->where('to_stage_id', $this->stage['measure'])->count(), 'один раз');
    }

    public function test_order_for_a_number_with_an_open_deal_goes_into_it(): void
    {
        $this->conn();
        $contact = DB::table('contacts')->insertGetId(['account_id' => $this->acc, 'name' => 'Анна', 'phone' => '79120001122', 'created_at' => now(), 'updated_at' => now()]);
        $open = DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => DB::table('pipelines')->where('account_id', $this->acc)->value('id'),
            'stage_id' => $this->stage['work'], 'title' => 'Звонки: 79120001122', 'contact_id' => $contact, 'created_at' => now()->subDay(), 'updated_at' => now()->subDay()]);

        $this->artisan('integrations:hands-poll')->assertExitCode(0);
        $this->assertSame(1, DB::table('deals')->where('account_id', $this->acc)->count());
        $this->assertSame(1, DB::table('deal_activities')->where('deal_id', $open)->where('type', 'lead_form')->count());
        $this->assertSame(1, (int) DB::table('deals')->where('id', $open)->value('is_unread'));
    }

    public function test_bad_key_is_reported_not_thrown(): void
    {
        $conn = $this->conn();
        $conn->forceFill(['settings' => ['api_key' => 'OLD', 'import_since' => '2026-10-23']])->save();
        $this->artisan('integrations:hands-poll')->assertExitCode(0);
        $this->assertStringContainsString('Руки: HTTP 403 — Неверный ключ', (string) $conn->fresh()->last_error);
        $this->assertSame(0, DB::table('deals')->where('account_id', $this->acc)->count());
    }

    public function test_admin_connects_the_key_and_it_is_never_shown(): void
    {
        $admin = User::create(['account_id' => $this->acc, 'name' => 'Админ (тест Рук)', 'email' => 'hands_admin_'.uniqid(), 'password' => bcrypt('x'), 'role' => 'admin', 'is_active' => true]);
        config(['owner.ceilings_account_id' => $this->acc]);

        $this->actingAs($admin)->post(route('settings.integrations.connect', 'hands'), ['api_key' => 'BAD'])->assertSessionHasErrors('hands');
        $this->actingAs($admin)->post(route('settings.integrations.connect', 'hands'), ['api_key' => ' KEY1 '])->assertRedirect(route('settings.integrations.index'));
        $conn = IntegrationConnection::withoutGlobalScopes()->where('account_id', $this->acc)->where('provider', 'hands')->firstOrFail();
        $this->assertSame(['active', 'KEY1', '2026-10-23'], [$conn->status, $conn->settings['api_key'], $conn->settings['import_since']]);

        $page = $this->actingAs($admin)->get(route('settings.integrations.index'))->assertOk()->assertSee('Руки (hands.ru)')->assertSee('ключ сохранён · заказы с 23.10.2026')->getContent();
        $this->assertStringNotContainsString('KEY1', $page);
    }
}
