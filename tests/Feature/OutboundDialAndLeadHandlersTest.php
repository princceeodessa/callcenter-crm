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
        // оператор звонит старому клиенту (номер уже есть в CRM), у которого нет открытых сделок (незаключёнка)
        DB::table('contacts')->insert(['account_id' => $this->acc, 'name' => 'Старый клиент', 'phone' => '79001234567', 'created_at' => '2026-05-01 10:00:00', 'updated_at' => '2026-05-01 10:00:00']);
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

    /** «Отчёты»: отказ засчитывается тому, кто первым взял лид, а не тому, кто закрыл сделку. */
    public function test_refusal_goes_to_whoever_took_the_lead(): void
    {
        $a = $this->user('Анна');
        $b = $this->user('Борис');
        $main = $this->user('Руководитель', 'main_operator');
        $deal = DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage['work'], 'title' => 'Лид',
            'created_at' => '2026-09-02 09:00:00', 'updated_at' => '2026-09-02 09:00:00']);
        DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $deal, 'from_stage_id' => $this->stage['lead'], 'to_stage_id' => $this->stage['work'], 'changed_by_user_id' => $a->id, 'changed_at' => '2026-09-02 09:10:00']);
        // закрыл отказом Борис
        DB::table('deals')->where('id', $deal)->update(['stage_id' => $this->stage['done'], 'closed_at' => '2026-09-05 12:00:00', 'closed_result' => 'lost', 'closed_by_user_id' => $b->id]);

        $rows = collect($this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09']))->assertOk()->viewData('operatorRows'))->keyBy('id');
        $this->assertSame([1, 0], [$rows[$a->id]['closedLost'], $rows[$b->id]['closedLost']]);
        $this->assertSame([$deal], $rows[$a->id]['ids']['closedLost']);
    }

    /** Клиент оставил номер, первыми звоним мы: новый номер — лид в «Поступил лид» и обращение «позвонили мы». */
    public function test_first_outgoing_call_to_a_new_number_is_a_lead(): void
    {
        $this->user('Admin', 'admin');
        $op = $this->user('Оператор');
        $this->vats(['cmd' => 'event', 'type' => 'OUTGOING', 'callid' => 'n1', 'phone' => '79005550011', 'diversion' => '79225174552']);
        $deal = DB::table('deals')->where('account_id', $this->acc)->where('title', 'Звонки: 79005550011')->first();
        $this->assertSame([$this->stage['lead'], 1], [(int) $deal->stage_id, (int) $deal->is_unread]);
        $sys = DB::table('deal_activities')->where('deal_id', $deal->id)->where('type', 'system')->first();
        $this->assertStringContainsString('первыми позвонили мы', $sys->body);
        $this->assertSame(['outbound_lead' => true], json_decode($sys->payload, true));

        // оператор взял лид и записал — в «Операторах» это его лид; в сводке — обращение с источником «звонок от нас»
        DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $deal->id, 'from_stage_id' => $this->stage['lead'], 'to_stage_id' => $this->stage['measure'], 'changed_by_user_id' => $op->id, 'changed_at' => now()->addMinute()]);
        $kpi = new CeilingsKpi($this->acc);
        $s = $kpi->handlerStats(now()->startOfDay(), now()->addDay());
        $this->assertSame([1, 1], [$s[$op->id]['taken'], $s[$op->id]['booked_self']]);
        $list = $kpi->contactList(now()->startOfDay(), now()->addDay());
        $this->assertSame('позвонили мы (клиент оставил номер)', \App\Services\Owner\CeilingsKpi::contactKindLabel('call_ours'));
        $this->assertSame(1, collect($list)->filter(fn ($r) => str_contains(json_encode($r, JSON_UNESCAPED_UNICODE), '005550011') || str_contains(json_encode($r, JSON_UNESCAPED_UNICODE), '(900) 555-00-11'))->count());
        $this->assertSame('Звонок от нас (клиент оставил номер)', \App\Services\Owner\CeilingsKpi::channelLabel($kpi->channelsFor([(int) $deal->id])[(int) $deal->id]));
    }

    /** Клиент написал номер в чате с пробелами и скобками — наш звонок ложится в сделку этого чата, а не в новую. */
    public function test_outgoing_call_finds_the_chat_where_the_client_wrote_the_number(): void
    {
        $this->user('Admin', 'admin');
        $chat = DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage['work'], 'title' => 'Мария — Avito',
            'contact_id' => DB::table('contacts')->insertGetId(['account_id' => $this->acc, 'name' => 'Мария', 'phone' => null, 'created_at' => now(), 'updated_at' => now()]),
            'created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
        $conv = DB::table('conversations')->insertGetId(['account_id' => $this->acc, 'deal_id' => $chat, 'channel' => 'avito', 'created_at' => now()->subHour(), 'updated_at' => now()->subHour()]);
        DB::table('messages')->insert(['account_id' => $this->acc, 'conversation_id' => $conv, 'direction' => 'in', 'body' => 'Перезвоните, пожалуйста: 8 (900) 777-12-34', 'created_at' => now()->subMinutes(30), 'updated_at' => now()->subMinutes(30)]);

        $this->vats(['cmd' => 'event', 'type' => 'OUTGOING', 'callid' => 'm1', 'phone' => '79007771234', 'diversion' => '79225174552']);
        $this->assertSame(1, DB::table('deals')->where('account_id', $this->acc)->count());
        $this->assertSame(1, DB::table('deal_activities')->where('deal_id', $chat)->where('type', 'call')->count());
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
        // записанный другим — «записали другие», не «не записал» (колл-центр 19.10.2026); Борис: l5 ещё в работе
        $this->assertSame(['taken' => 3, 'booked_self' => 2, 'rescued' => 1, 'not_booked' => 0, 'in_work' => 0, 'non_target' => 0, 'booked_others' => 0], array_diff_key($s[$a->id], ['ids' => 0]));
        $this->assertSame(['taken' => 2, 'booked_self' => 0, 'rescued' => 0, 'not_booked' => 0, 'in_work' => 1, 'non_target' => 1, 'booked_others' => 2], array_diff_key($s[$b->id], ['ids' => 0]));
        // за каждым числом — его сделки (для списка по клику в «Отчётах»)
        $this->assertEqualsCanonicalizing([$l1, $l2, $l3], $s[$a->id]['ids']['taken']);
        $this->assertSame([$l1], $s[$a->id]['ids']['rescued']);
        $this->assertSame([$l5], $s[$b->id]['ids']['in_work']);
        $this->assertEqualsCanonicalizing([$l1, $old], $s[$b->id]['ids']['booked_others']);

        // «Отчёты» руководителя КЦ
        $main = $this->user('Руководитель', 'main_operator');
        $rows = collect($this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09']))->assertOk()
            ->assertSee('Взял')->assertSee('Записали другие')->assertSee('Незавершённые')->assertSee('Дожал чужих')->viewData('operatorRows'))->keyBy('id');
        $this->assertSame(3, $rows[$a->id]['leads']['taken']);
        $this->assertSame(2, $rows[$b->id]['leads']['booked_others']);

        // провалиться в число: окно со списком сделок; ссылки только у ненулевых чисел; чужая строка — нет
        $page = $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09']))->getContent();
        $this->assertStringContainsString(e(route('reports.monthly', ['month' => '2026-09', 'drill' => 'leads.rescued', 'user' => $a->id])), $page);
        $this->assertStringNotContainsString(e(route('reports.monthly', ['month' => '2026-09', 'drill' => 'leads.not_booked', 'user' => $a->id])), $page);
        $list = $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'drill' => 'leads.rescued', 'user' => $a->id, 'partial' => 1]))
            ->assertOk()->assertSee('Записали другие: 1')->assertSee('Анна')->assertSee(route('deals.show', $l1), false);
        $this->assertStringNotContainsString('<html', $list->getContent());
        $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'drill' => 'leads.in_work', 'user' => $b->id, 'partial' => 1]))
            ->assertOk()->assertSee('Незавершённые: 1')->assertSee(route('deals.show', $l5), false);
        $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'drill' => 'leads.booked_others', 'user' => $b->id]))
            ->assertOk()->assertSee('Дожал чужих: 2')->assertSee('← к отчёту')->assertSee(route('deals.show', $old), false);
        $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'drill' => 'nope', 'user' => $a->id]))->assertNotFound();
        $this->actingAs($a)->get(route('reports.monthly', ['month' => '2026-09', 'drill' => 'leads.taken', 'user' => $b->id]))->assertNotFound();
        $this->actingAs($a)->get(route('reports.monthly', ['month' => '2026-09', 'drill' => 'leads.taken', 'user' => $a->id, 'partial' => 1]))->assertOk()->assertSee('Взял новых лидов: 3');

        // сводка владельца
        $owner = User::create(['account_id' => User::where('role', 'sneaker_head')->firstOrFail()->account_id, 'name' => 'Владелец (тест)', 'email' => 'dial_owner_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true]);
        $owner->forceFill(['all_businesses' => true])->save();
        $res = $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();
        $ops = collect($res->viewData('kpi')['operators'])->keyBy('user_id');
        $this->assertSame([3, 2, 0, 1, 0], [$ops[$a->id]['taken'], $ops[$a->id]['booked_self'], $ops[$a->id]['not_booked'], $ops[$a->id]['rescued'], $ops[$a->id]['booked_others']]);
        $this->assertSame(1, $ops[$b->id]['in_work']);
        $res->assertSee('Взял новых лидов')->assertSee('Записали другие')->assertSee('Незавершённые');
    }

    /** Отказ — «не записал»; лид, переложенный на «Нецелевое дозвон», — нецелевой, а не «в работе». */
    public function test_refusal_is_not_booked_and_dial_stage_is_non_target(): void
    {
        $a = $this->user('Анна');
        $deal = fn (array $extra = []) => DB::table('deals')->insertGetId($extra + ['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage['lead'], 'title' => 'Лид', 'created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00']);
        $move = fn (int $d, string $from, string $to, string $at) => DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $d, 'from_stage_id' => $this->stage[$from], 'to_stage_id' => $this->stage[$to], 'changed_by_user_id' => $a->id, 'changed_at' => $at]);

        $lost = $deal();
        $move($lost, 'lead', 'work', '2026-09-02 10:00:00');
        DB::table('deals')->where('id', $lost)->update(['stage_id' => $this->stage['done'], 'closed_at' => '2026-09-03 10:00:00', 'closed_result' => 'lost', 'closed_by_user_id' => $a->id]);
        $dial = $deal();
        $move($dial, 'lead', 'dial', '2026-09-04 10:00:00');
        DB::table('deals')->where('id', $dial)->update(['stage_id' => $this->stage['dial']]);

        $s = (new CeilingsKpi($this->acc))->handlerStats(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-01'))[$a->id];
        $this->assertSame([2, 1, 0, 1], [$s['taken'], $s['not_booked'], $s['in_work'], $s['non_target']]);
        $this->assertSame([$lost], $s['ids']['not_booked']);
        $this->assertSame([$dial], $s['ids']['non_target']);
    }

    /** Новые лиды по источникам: звонки, Авито, ВК — отдельно (?src=), числа и списки — только этого источника. */
    public function test_new_leads_split_by_source(): void
    {
        $a = $this->user('Анна');
        $main = $this->user('Руководитель', 'main_operator');
        $lead = fn () => DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage['work'], 'title' => 'Лид', 'created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00']);
        $take = fn (int $d, string $to, string $at) => DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $d, 'from_stage_id' => $this->stage['lead'], 'to_stage_id' => $this->stage[$to], 'changed_by_user_id' => $a->id, 'changed_at' => $at]);

        $call = $lead();                                     // позвонил — Анна записала
        DB::table('deal_activities')->insert(['account_id' => $this->acc, 'deal_id' => $call, 'type' => 'call', 'payload' => json_encode(['type' => 'INCOMING', 'callid' => 'c1']), 'created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00']);
        $take($call, 'measure', '2026-09-02 10:00:00');
        foreach (['2026-09-03 10:00:00', '2026-09-04 10:00:00'] as $at) {   // два чата Авито — молчат
            $chat = $lead();
            DB::table('conversations')->insert(['account_id' => $this->acc, 'deal_id' => $chat, 'channel' => 'avito', 'created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00']);
            $take($chat, 'work', $at);
        }
        $vk = $lead();
        DB::table('conversations')->insert(['account_id' => $this->acc, 'deal_id' => $vk, 'channel' => 'vk', 'created_at' => '2026-09-01 09:00:00', 'updated_at' => '2026-09-01 09:00:00']);
        $take($vk, 'work', '2026-09-05 10:00:00');

        $res = $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09']))->assertOk();
        $this->assertSame(['all' => 4, 'call' => 1, 'avito' => 2, 'vk' => 1, 'other' => 0], $res->viewData('leadSourceTotals'));
        $res->assertSee(e(route('reports.monthly', ['month' => '2026-09', 'src' => 'avito'])), false);

        $avito = $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'src' => 'avito']))->assertOk();
        $row = collect($avito->viewData('operatorRows'))->firstWhere('id', $a->id);
        $this->assertSame([2, 0, 2], [$row['leads']['taken'], $row['leads']['booked_self'], $row['leads']['in_work']]);
        $avito->assertSee(e(route('reports.monthly', ['month' => '2026-09', 'src' => 'avito', 'drill' => 'leads.in_work', 'user' => $a->id])), false);
        $calls = collect($this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'src' => 'call']))->viewData('operatorRows'))->firstWhere('id', $a->id);
        $this->assertSame([1, 1, 0], [$calls['leads']['taken'], $calls['leads']['booked_self'], $calls['leads']['in_work']]);
        $this->actingAs($main)->get(route('reports.monthly', ['month' => '2026-09', 'src' => 'avito', 'drill' => 'leads.taken', 'user' => $a->id, 'partial' => 1]))
            ->assertOk()->assertSee('Взял новых лидов · Авито: 2')->assertDontSee(route('deals.show', $call), false);
    }

    /** «Скрыть из отчётов» в настройках пользователей: строки нет ни в таблице, ни в итогах; учётка работает. */
    public function test_hidden_users_are_not_in_reports(): void
    {
        $admin = $this->user('Admin', 'admin');
        $a = $this->user('Анна');
        $boss = $this->user('Кошевникова', 'main_operator');
        DB::table('deals')->insert(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage['work'], 'title' => 'Сделка Admin',
            'responsible_user_id' => $admin->id, 'created_at' => '2026-09-02 09:00:00', 'updated_at' => '2026-09-02 09:00:00']);

        $names = fn () => collect($this->actingAs($admin)->get(route('reports.monthly', ['month' => '2026-09']))->viewData('operatorRows'))->pluck('name')->all();
        $this->assertContains('Admin', $names());

        foreach ([$admin, $boss] as $u) {
            $this->actingAs($admin)->post(route('settings.users.toggle', $u), ['what' => 'reports'])->assertRedirect();
            $this->assertTrue($u->fresh()->hide_in_reports);
            $this->assertTrue($u->fresh()->is_active, 'учётка не отключается');
        }
        $res = $this->actingAs($admin)->get(route('reports.monthly', ['month' => '2026-09']))->assertOk();
        $this->assertSame(['Анна'], collect($res->viewData('operatorRows'))->pluck('name')->all());
        $this->assertSame(0, $res->viewData('operatorSummary')['created'], 'сделки Admin не в итогах');
        $this->actingAs($admin)->get(route('settings.users.index'))->assertOk()->assertSee('не в отчётах')->assertSee('Показать в отчётах');

        $this->actingAs($admin)->post(route('settings.users.toggle', $boss), ['what' => 'reports']);
        $this->assertFalse($boss->fresh()->hide_in_reports);
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
