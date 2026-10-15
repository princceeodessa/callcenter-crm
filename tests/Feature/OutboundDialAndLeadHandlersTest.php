<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\IntegrationConnection;
use App\Models\IntegrationEvent;
use App\Models\User;
use App\Services\Integrations\MegafonVatsDealSync;
use App\Services\Owner\CeilingsKpi;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Потолки: сделки на исходящий звонок оператора — в «Нецелевое дозвон» (перезвонил клиент сам — в «Поступил лид»);
 * лид закреплён за тем, кто первым его перетащил: «записал сам», «не записал» (даже если потом записал другой),
 * «дожал чужих» — в сводке владельца и в «Отчётах».
 */
class OutboundDialAndLeadHandlersTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $pipeline;
    private array $stage = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест дозвонов)'])->id;
        config(['owner.ceilings_account_id' => $this->acc]);
        $this->pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['lead' => ['Поступил лид', 10, 0], 'nt' => ['Нецелевое', 15, 0], 'dial' => ['Нецелевое дозвон', 16, 0], 'work' => ['Взяли в работу', 20, 0], 'measure' => ['Замер назначен', 40, 0], 'done' => ['Завершить сделку', 60, 1]] as $k => [$name, $sort, $final]) {
            $this->stage[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'name' => $name, 'sort' => $sort, 'is_final' => $final, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function user(string $name, string $role = 'operator'): User
    {
        return User::create(['account_id' => $this->acc, 'name' => $name, 'email' => 'dial_'.uniqid(), 'password' => Hash::make('password'), 'role' => $role, 'is_active' => true]);
    }

    private function vats(array $payload): void
    {
        $conn = IntegrationConnection::firstOrCreate(['account_id' => $this->acc, 'provider' => 'megafon_vats'], ['status' => 'active', 'settings' => ['crm_webhook_token' => 'dial']]);
        $event = IntegrationEvent::create(['account_id' => $this->acc, 'provider' => 'megafon_vats', 'direction' => 'in', 'event_type' => 'event:'.$payload['type'], 'external_id' => $payload['callid'], 'payload' => $payload, 'received_at' => now()]);
        MegafonVatsDealSync::handle($conn, $event);
    }

    public function test_outgoing_call_deals_go_to_the_dial_stage_and_come_back_when_the_client_calls(): void
    {
        $this->user('Admin', 'admin');
        // оператор звонит клиенту, которого нет в открытых сделках (незаключёнка)
        $this->vats(['cmd' => 'event', 'type' => 'OUTGOING', 'callid' => 'o1', 'phone' => '79001234567', 'diversion' => '79225174552']);
        $this->vats(['cmd' => 'event', 'type' => 'ACCEPTED', 'callid' => 'o1', 'phone' => '79001234567', 'diversion' => '79225174552']);
        $deal = DB::table('deals')->where('account_id', $this->acc)->first();
        $this->assertSame([$this->stage['dial'], 0], [(int) $deal->stage_id, (int) $deal->is_unread]);
        $this->assertSame(1, DB::table('deals')->where('account_id', $this->acc)->count());

        // клиент перезвонил сам — это обращение: сделка в «Поступил лид»
        $this->vats(['cmd' => 'event', 'type' => 'INCOMING', 'callid' => 'i1', 'phone' => '79001234567', 'diversion' => '79225174552']);
        $this->assertSame($this->stage['lead'], (int) DB::table('deals')->where('id', $deal->id)->value('stage_id'));
        $this->assertSame(1, DB::table('deal_stage_history')->where('deal_id', $deal->id)->where('to_stage_id', $this->stage['lead'])->count());
        $this->assertStringContainsString('перезвонил', DB::table('deal_activities')->where('deal_id', $deal->id)->where('type', 'system')->orderByDesc('id')->value('body'));

        // новый номер звонит сам — как обычно, в «Поступил лид»
        $this->vats(['cmd' => 'event', 'type' => 'INCOMING', 'callid' => 'i2', 'phone' => '79007654321', 'diversion' => '79225174552']);
        $this->assertSame($this->stage['lead'], (int) DB::table('deals')->where('account_id', $this->acc)->where('title', 'Звонки: 79007654321')->value('stage_id'));

        // на «Нецелевое дозвон» руками — без категории нецелевого
        $op = $this->user('Оператор');
        $id = DB::table('deals')->where('account_id', $this->acc)->where('title', 'Звонки: 79007654321')->value('id');
        $this->actingAs($op)->postJson(route('deals.move', $id), ['to_stage_id' => $this->stage['dial']])->assertOk()->assertJson(['ok' => true]);
    }

    /** Звонок сотруднику (наш номер из ignored_phone_numbers) — внутренний: не прилипает к сделке клиента через общую линию. */
    public function test_calls_with_our_own_numbers_do_not_stick_to_client_deals(): void
    {
        IntegrationConnection::create(['account_id' => $this->acc, 'provider' => 'megafon_vats', 'status' => 'active',
            'settings' => ['crm_webhook_token' => 'dial', 'ignored_phone_numbers' => ['79001110017']]]);
        $this->user('Admin', 'admin');
        $line = ['telnum' => '79658432111', 'diversion' => '79658432111'];
        // клиенту звонят через линию
        $this->vats(['cmd' => 'event', 'type' => 'OUTGOING', 'callid' => 'k1', 'phone' => '79001234546'] + $line);
        $deal = DB::table('deals')->where('account_id', $this->acc)->value('id');
        // оператор звонит замерщику через ту же линию, замерщик перезванивает
        $this->vats(['cmd' => 'event', 'type' => 'OUTGOING', 'callid' => 'k2', 'phone' => '79001110017'] + $line);
        $this->vats(['cmd' => 'event', 'type' => 'ACCEPTED', 'callid' => 'k2', 'phone' => '79001110017'] + $line);
        $this->vats(['cmd' => 'event', 'type' => 'INCOMING', 'callid' => 'k3', 'phone' => '89001110017'] + $line);

        $this->assertSame(1, DB::table('deals')->where('account_id', $this->acc)->count());
        $this->assertSame(1, DB::table('deal_activities')->where('deal_id', $deal)->where('type', 'call')->count());
        $this->assertSame(0, DB::table('contacts')->where('account_id', $this->acc)->where('phone', 'like', '%9658432111')->count());
    }

    public function test_lead_belongs_to_whoever_took_it_first(): void
    {
        $a = $this->user('Анна');
        $b = $this->user('Борис');
        $deal = fn (array $extra = []) => DB::table('deals')->insertGetId($extra + ['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage['lead'], 'title' => 'Лид', 'created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00']);
        $move = fn (int $d, string $from, string $to, ?User $u, string $at) => DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $d, 'from_stage_id' => $this->stage[$from], 'to_stage_id' => $this->stage[$to], 'changed_by_user_id' => $u?->id, 'changed_at' => $at]);

        $l1 = $deal();                                  // Анна взяла, не записала; записал Борис
        $move($l1, 'lead', 'work', $a, '2026-09-02 10:00:00');
        $move($l1, 'work', 'measure', $b, '2026-09-03 10:00:00');
        $l2 = $deal();                                  // Анна записала сразу
        $move($l2, 'lead', 'measure', $a, '2026-09-04 10:00:00');
        $l3 = $deal();                                  // Анна взяла, потом сама записала
        $move($l3, 'lead', 'work', $a, '2026-09-05 10:00:00');
        $move($l3, 'work', 'measure', $a, '2026-09-06 10:00:00');
        $l4 = $deal(['stage_id' => $this->stage['done'], 'closed_at' => '2026-09-07 10:00:00', 'closed_result' => 'extra_non_target', 'closed_by_user_id' => $b->id, 'non_target_category' => 'spam']);
        $l5 = $deal();                                  // Борис взял, в работе
        $move($l5, 'lead', 'work', $b, '2026-09-08 10:00:00');
        $dial = $deal();                                // заведена исходящим звонком — не лид
        DB::table('deal_activities')->insert(['account_id' => $this->acc, 'deal_id' => $dial, 'type' => 'call', 'payload' => json_encode(['type' => 'OUTGOING', 'callid' => 'x']), 'created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00']);
        $move($dial, 'lead', 'nt', $a, '2026-09-09 10:00:00');
        $old = $deal();                                 // взята в августе Анной, записана Борисом в сентябре
        $move($old, 'lead', 'work', $a, '2026-08-20 10:00:00');
        $move($old, 'work', 'measure', $b, '2026-09-10 10:00:00');

        $s = (new CeilingsKpi($this->acc))->handlerStats(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-01'));
        $this->assertSame(['taken' => 3, 'booked_self' => 2, 'not_booked' => 1, 'rescued' => 1, 'non_target' => 0, 'booked_others' => 0], $s[$a->id]);
        $this->assertSame(['taken' => 2, 'booked_self' => 0, 'not_booked' => 1, 'rescued' => 0, 'non_target' => 1, 'booked_others' => 2], $s[$b->id]);

        // «Отчёты» руководителя КЦ
        $main = $this->user('Руководитель', 'main_operator');
        $rows = collect($this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09']))->assertOk()
            ->assertSee('Взял новых лидов')->assertSee('Дожал чужих')->viewData('operatorRows'))->keyBy('id');
        $this->assertSame(3, $rows[$a->id]['leads']['taken']);
        $this->assertSame(2, $rows[$b->id]['leads']['booked_others']);

        // сводка владельца
        $owner = User::create(['account_id' => User::where('role', 'sneaker_head')->firstOrFail()->account_id, 'name' => 'Владелец (тест)', 'email' => 'dial_owner_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true]);
        $owner->forceFill(['all_businesses' => true])->save();
        $res = $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();
        $ops = collect($res->viewData('kpi')['operators'])->keyBy('user_id');
        $this->assertSame([3, 2, 1, 1, 0], [$ops[$a->id]['taken'], $ops[$a->id]['booked_self'], $ops[$a->id]['not_booked'], $ops[$a->id]['rescued'], $ops[$a->id]['booked_others']]);
        $res->assertSee('Взял новых лидов')->assertSee('записали другие: 1');
    }

    /** Выгрузка обращений в Excel: номер, менеджер (кто первым взял), записался; без нецелевых и исходящих дозвонов. */
    public function test_contacts_export_to_excel(): void
    {
        $a = $this->user('Анна');
        $b = $this->user('Борис');
        $lead = function (string $phone, ?string $name = null) {
            $contact = DB::table('contacts')->insertGetId(['account_id' => $this->acc, 'name' => $name ?? 'Клиент', 'phone' => $phone, 'created_at' => now(), 'updated_at' => now()]);

            return DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage['lead'], 'contact_id' => $contact, 'title' => 'Лид', 'created_at' => '2026-09-02 09:00:00', 'updated_at' => '2026-09-02 09:00:00']);
        };
        $call = fn (int $d, string $type, string $at) => DB::table('deal_activities')->insert(['account_id' => $this->acc, 'deal_id' => $d, 'type' => 'call', 'payload' => json_encode(['type' => $type, 'callid' => uniqid(), 'diversion' => '79225174552']), 'created_at' => $at, 'updated_at' => $at]);
        $move = fn (int $d, string $from, string $to, User $u, string $at) => DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $d, 'from_stage_id' => $this->stage[$from], 'to_stage_id' => $this->stage[$to], 'changed_by_user_id' => $u->id, 'changed_at' => $at]);

        $booked = $lead('89001112233');                         // позвонил, Анна записала
        $call($booked, 'INCOMING', '2026-09-02 09:00:00');
        $move($booked, 'lead', 'measure', $a, '2026-09-02 09:10:00');
        $open = $lead('+7 900 444-55-66');                      // позвонил, Борис взял, не записан
        $call($open, 'INCOMING', '2026-09-03 09:00:00');
        $move($open, 'lead', 'work', $b, '2026-09-03 09:10:00');
        $spam = $lead('79007778899');                           // нецелевой — в выгрузку не попадает
        $call($spam, 'INCOMING', '2026-09-04 09:00:00');
        DB::table('deals')->where('id', $spam)->update(['stage_id' => $this->stage['done'], 'closed_at' => '2026-09-04 09:20:00', 'closed_result' => 'extra_non_target', 'closed_by_user_id' => $a->id, 'non_target_category' => 'spam']);
        $dial = $lead('79001230000');                           // исходящий дозвон — не обращение
        $call($dial, 'OUTGOING', '2026-09-05 09:00:00');
        $chat = DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage['lead'], 'title' => 'Альваро — Avito', 'created_at' => '2026-09-06 09:00:00', 'updated_at' => '2026-09-06 09:00:00',
            'contact_id' => DB::table('contacts')->insertGetId(['account_id' => $this->acc, 'name' => 'Альваро', 'phone' => null, 'created_at' => now(), 'updated_at' => now()])]);
        $conv = DB::table('conversations')->insertGetId(['account_id' => $this->acc, 'deal_id' => $chat, 'channel' => 'avito', 'created_at' => '2026-09-06 09:00:00', 'updated_at' => '2026-09-06 09:00:00']);
        DB::table('messages')->insert(['account_id' => $this->acc, 'conversation_id' => $conv, 'direction' => 'in', 'body' => 'Сколько стоит?', 'created_at' => '2026-09-06 09:00:00', 'updated_at' => '2026-09-06 09:00:00']);

        $main = $this->user('Руководитель', 'main_operator');
        $res = $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'export' => 'contacts']));
        $res->assertOk()->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('obrashcheniya-kc-2026-09-01_2026-09-30.xlsx', $res->headers->get('Content-Disposition'));
        $path = tempnam(sys_get_temp_dir(), 'xl');
        file_put_contents($path, $res->getContent());
        $rows = \App\Support\Warehouse\SimpleXlsxReader::rows($path, 3);
        @unlink($path);
        $this->assertSame([
            ['Номер телефона', 'Менеджер (кто принял)', 'Записался'],
            ['+7 (900) 111-22-33', 'Анна', 'да'],
            ['+7 (900) 444-55-66', 'Борис', 'нет'],
            ['без номера — чат Авито (Альваро)', 'не взят в работу', 'нет'],
        ], array_map(fn ($r) => array_map('strval', array_slice($r, 0, 3)), $rows));

        $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09']))->assertOk()->assertSee('Обращения в Excel');

        // свои даты из календаря: отчёт и выгрузка — за выбранные дни
        $res = $this->actingAs($main)->get(route('reports.monthly', ['from' => '2026-09-03', 'to' => '2026-09-04']))->assertOk();
        $this->assertSame(['from' => '2026-09-03', 'to' => '2026-09-04', 'custom' => true], $res->viewData('period'));
        $res->assertSee('data-daterange', false)->assertSee('03.09 — 04.09.2026')
            ->assertSee(e(route('reports.monthly', ['from' => '2026-09-03', 'to' => '2026-09-04', 'export' => 'contacts'])), false);
        $res = $this->actingAs($main)->get(route('reports.monthly', ['from' => '2026-09-03', 'to' => '2026-09-04', 'export' => 'contacts']))->assertOk();
        $this->assertStringContainsString('obrashcheniya-kc-2026-09-03_2026-09-04.xlsx', $res->headers->get('Content-Disposition'));
        $path = tempnam(sys_get_temp_dir(), 'xl');
        file_put_contents($path, $res->getContent());
        $rows = \App\Support\Warehouse\SimpleXlsxReader::rows($path, 3);
        @unlink($path);
        $this->assertSame([['Номер телефона', 'Менеджер (кто принял)', 'Записался'], ['+7 (900) 444-55-66', 'Борис', 'нет']],
            array_map(fn ($r) => array_map('strval', array_slice($r, 0, 3)), $rows));

        // кривые даты — обычный месяц
        $this->assertFalse($this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'from' => '2026-09-10', 'to' => 'x']))->assertOk()->viewData('period')['custom']);
    }
}
