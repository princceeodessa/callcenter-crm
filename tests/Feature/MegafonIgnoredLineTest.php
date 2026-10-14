<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Services\Integrations\MegafonVatsDealSync;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Личный номер, подключённый к АТС (settings.ignored_line_numbers): звонки через него не попадают в CRM — ни сделки,
 * ни звонка, хотя звонящий — обычный внешний номер. Звонки клиентов на линии колл-центра идут как раньше.
 */
class MegafonIgnoredLineTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private IntegrationConnection $conn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест АТС)'])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pipeline_stages')->insert(['account_id' => $this->acc, 'pipeline_id' => $pipeline, 'name' => 'Поступил лид', 'sort' => 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->conn = IntegrationConnection::create([
            'account_id' => $this->acc, 'provider' => 'megafon_vats', 'status' => 'active',
            'settings' => ['crm_webhook_token' => 'test', 'ignored_line_numbers' => ['+7 (900) 111-22-33']],
        ]);
    }

    private function vatsEvent(array $payload): void
    {
        $event = IntegrationEvent::create([
            'account_id' => $this->acc, 'provider' => 'megafon_vats', 'direction' => 'in',
            'event_type' => 'event:'.($payload['type'] ?? ''), 'external_id' => $payload['callid'], 'payload' => $payload, 'received_at' => now(),
        ]);
        MegafonVatsDealSync::handle($this->conn, $event);
    }

    public function test_calls_through_a_personal_line_are_not_tracked(): void
    {
        // входящий и исходящий через личный номер: звонящий — внешний номер, но в CRM ничего не появляется
        $this->vatsEvent(['cmd' => 'event', 'type' => 'INCOMING', 'callid' => 'p1', 'phone' => '79005556677', 'diversion' => '79001112233', 'telnum' => '79001112233']);
        $this->vatsEvent(['cmd' => 'history', 'type' => 'in', 'callid' => 'p1', 'phone' => '79005556677', 'diversion' => '79001112233']);
        $this->vatsEvent(['cmd' => 'event', 'type' => 'OUTGOING', 'callid' => 'p2', 'phone' => '78005550000', 'diversion' => '89001112233']);
        $this->assertSame(0, DB::table('deals')->where('account_id', $this->acc)->count());
        $this->assertSame(0, DB::table('deal_activities')->where('account_id', $this->acc)->count());

        // тот же человек звонит на линию колл-центра — это клиент, сделка создаётся как обычно
        $this->vatsEvent(['cmd' => 'event', 'type' => 'INCOMING', 'callid' => 'c1', 'phone' => '79005556677', 'diversion' => '79225174552']);
        $this->assertSame(['Звонки: 79005556677'], DB::table('deals')->where('account_id', $this->acc)->pluck('title')->all());
        $this->assertSame(1, DB::table('deal_activities')->where('account_id', $this->acc)->where('type', 'call')->count());
    }
}
