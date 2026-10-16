<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerCeilingsTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $pipeline;
    private array $stage = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест сводки)'])->id;
        config(['owner.ceilings_account_id' => $this->acc]);
        $this->pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['lead' => ['Поступил лид', 10, 0], 'nt' => ['Нецелевое', 15, 0], 'measure' => ['Замер назначен', 40, 0], 'done' => ['Завершить сделку', 60, 1]] as $k => [$name, $sort, $final]) {
            $this->stage[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'name' => $name, 'sort' => $sort, 'is_final' => $final, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function owner(bool $all = true, string $role = 'sneaker_owner'): User
    {
        $sneakers = User::where('role', 'sneaker_head')->firstOrFail()->account_id;
        $u = User::create([
            'account_id' => $sneakers, 'name' => 'Владелец (тест)', 'email' => 'owner_ceil_'.uniqid(),
            'password' => Hash::make('password'), 'role' => $role, 'is_active' => true,
        ]);
        $u->forceFill(['all_businesses' => $all])->save();

        return $u;
    }

    private function deal(string $created, string $stage = 'lead', ?string $closedAt = null, ?string $result = null, ?int $closedBy = null, ?int $contact = null): int
    {
        return DB::table('deals')->insertGetId([
            'account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage[$stage],
            'title' => 'Тест', 'closed_at' => $closedAt, 'closed_result' => $result, 'closed_by_user_id' => $closedBy,
            'contact_id' => $contact, 'created_at' => $created, 'updated_at' => $created,
        ]);
    }

    private function contact(string $phone): int
    {
        return DB::table('contacts')->insertGetId(['account_id' => $this->acc, 'name' => 'Клиент', 'phone' => $phone, 'created_at' => now(), 'updated_at' => now()]);
    }

    /** Чат сделки и входящее сообщение в нём. */
    private function message(int $deal, string $channel, string $at): void
    {
        $conv = DB::table('conversations')->insertGetId(['account_id' => $this->acc, 'deal_id' => $deal, 'channel' => $channel, 'created_at' => $at, 'updated_at' => $at]);
        DB::table('messages')->insert(['account_id' => $this->acc, 'conversation_id' => $conv, 'direction' => 'in', 'body' => 'Здравствуйте', 'created_at' => $at, 'updated_at' => $at]);
    }

    private function toMeasure(int $deal, string $at, ?int $by = null): void
    {
        DB::table('deal_stage_history')->insert(['account_id' => $this->acc, 'deal_id' => $deal, 'from_stage_id' => $this->stage['lead'], 'to_stage_id' => $this->stage['measure'], 'changed_by_user_id' => $by, 'changed_at' => $at]);
    }

    private function activity(int $deal, string $type, array $payload, string $at): void
    {
        DB::table('deal_activities')->insert(['account_id' => $this->acc, 'deal_id' => $deal, 'type' => $type, 'payload' => json_encode($payload), 'created_at' => $at, 'updated_at' => $at]);
    }

    public function test_owner_of_all_businesses_sees_ceilings_kpis(): void
    {
        $op = $this->owner(false, 'operator');
        $d1 = $this->deal('2026-09-05 10:00:00', 'measure');                               // чат Авито → замер (перевёл оператор)
        $this->toMeasure($d1, '2026-09-06 11:00:00', $op->id);
        $this->message($d1, 'avito', '2026-09-05 09:59:00');
        $d2 = $this->deal('2026-09-07 10:00:00', 'done', '2026-09-08 12:00:00', 'won', $op->id); // звонок на номер «сайт (директ)», успешно без этапа
        $this->activity($d2, 'call', ['type' => 'INCOMING', 'callid' => 'c1', 'diversion' => '79225174552'], '2026-09-07 10:00:00');
        $this->activity($d2, 'call', ['type' => 'ACCEPTED', 'callid' => 'c1', 'diversion' => '79225174552'], '2026-09-07 10:00:05');
        $d3 = $this->deal('2026-09-10 10:00:00', 'done', '2026-09-10 12:00:00', 'extra_non_target', $op->id); // заявка Tilda, нецелевой
        $this->activity($d3, 'lead_form', ['provider' => 'tilda'], '2026-09-10 10:00:00');
        $d4 = $this->deal('2026-09-11 10:00:00', 'done', '2026-09-12 12:00:00', 'lost');    // заведено вручную, отказ
        $this->activity($d4, 'call', ['type' => 'OUTGOING', 'callid' => 'c3'], '2026-09-11 10:05:00');
        $d5 = $this->deal('2026-09-12 10:00:00');                                           // звонок с неизвестного номера, пропущен
        $this->activity($d5, 'call', ['type' => 'INCOMING', 'callid' => 'c2', 'diversion' => '70000000000'], '2026-09-12 10:00:00');
        $old = $this->deal('2026-08-20 10:00:00', 'measure');                               // старый лид, замер назначен в сентябре
        $this->toMeasure($old, '2026-09-03 10:00:00');
        $this->deal('2026-10-02 10:00:00');                                                 // вне периода

        $res = $this->actingAs($this->owner())->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']));

        $res->assertOk();
        $kpi = $res->viewData('kpi');
        $this->assertSame(5, $kpi['leads']);
        $this->assertSame(3, $kpi['bookings']);            // d1, d2 и старый лид
        $this->assertSame(2, $kpi['booked_cohort']);
        $this->assertSame(1, $kpi['non_target']);
        $this->assertSame(1, $kpi['lost']);
        $this->assertSame(1, $kpi['open']);
        $this->assertEqualsWithDelta(40.0, $kpi['conversion'], 0.01);
        $this->assertSame(['incoming' => 2, 'answered' => 1, 'missed' => 1, 'outgoing' => 1], $kpi['calls']);
        // обратились: чат Авито (d1), звонок на номер сайта (d2), форма Tilda (d3), звонок с неизвестного номера (d5);
        // d4 — только исходящий звонок оператора, это не обращение
        $c = $kpi['contacts'];
        $this->assertSame([4, 2, 1, 0, 1], [$c['total'], $c['booked'], $c['non_target'], $c['lost'], $c['open']]);
        $cg = collect($c['groups'])->keyBy('key');
        $this->assertSame(['total' => 2, 'non_target' => 1], ['total' => $cg['direct']['total'], 'non_target' => $cg['direct']['non_target']]);
        $this->assertSame([                                        // d3 закрыта нецелевой до категорий — «без категории»
            'call' => ['total' => 1, 'booked' => 1, 'non_target' => 0, 'cats' => ['booked' => 1]],
            'form_tilda' => ['total' => 1, 'booked' => 0, 'non_target' => 1, 'cats' => ['none' => 1]],
        ], $cg['direct']['kinds']);
        $this->assertSame(['chat_avito' => ['total' => 1, 'booked' => 1, 'non_target' => 0, 'cats' => ['booked' => 1]]], $cg['avito']['kinds']);
        $this->assertSame(['booked' => 2, 'none' => 1, 'open' => 1], $c['cats']);
        $this->assertSame(1, $cg['other']['total']);
        $this->assertSame(0, $kpi['prev']['contacts']);

        $groups = collect($kpi['channels'])->keyBy('key');
        $this->assertSame(2, $groups['direct']['leads']);     // звонок на номер сайта + заявка Tilda
        $this->assertSame(1, $groups['direct']['booked']);
        $this->assertSame(1, $groups['avito']['leads']);
        $this->assertSame(2, $groups['other']['leads']);      // вручную + неизвестный номер

        $opRow = collect($kpi['operators'])->firstWhere('user_id', $op->id);
        $this->assertSame(3, $opRow['handled']);   // перевёл d1, закрыл d2 и d3
        $this->assertSame(2, $opRow['bookings']);  // d1 и d2
        $this->assertSame(1, $opRow['non_target']);
        $this->assertSame(1, collect($kpi['operators'])->firstWhere('user_id', null)['bookings']); // старый лид — «Система»
        $res->assertSee('Потолки');
    }

    /**
     * «Обратились в КЦ» — клиенты, а не звонки: один номер, позвонивший дважды и написавший, — одно обращение (источник —
     * первое обращение); исходящий звонок оператора — не обращение. Записались — по CRM (с таблицей замеров не
     * подгоняется), запись, сделанная до периода, не в счёт.
     */
    public function test_contacts_are_clients_and_bookings_come_from_crm(): void
    {
        $a = $this->contact('8 (912) 000-11-22');
        $a1 = $this->deal('2026-09-02 09:00:00', 'lead', null, null, null, $a);
        $this->activity($a1, 'call', ['type' => 'INCOMING', 'callid' => 'x1', 'diversion' => '79225085574'], '2026-09-02 09:00:00');
        $this->activity($a1, 'call', ['type' => 'in', 'callid' => 'x1', 'diversion' => '79225085574'], '2026-09-02 09:05:00');
        $this->activity($a1, 'call', ['type' => 'INCOMING', 'callid' => 'x2', 'diversion' => '79225085574'], '2026-09-04 09:00:00');
        $this->message($a1, 'vk', '2026-09-05 10:00:00');
        $this->toMeasure($a1, '2026-09-06 12:00:00');
        // другой контакт с тем же номером в другом виде: написал в ВК (нецелевой), потом оставил лид-форму (в работе)
        $b1 = $this->deal('2026-09-03 10:00:00', 'done', '2026-09-03 12:00:00', 'extra_non_target', null, $this->contact('+7 912 000-33-44'));
        $this->message($b1, 'vk', '2026-09-03 10:00:00');
        $b2 = $this->deal('2026-09-06 10:00:00', 'lead', null, null, null, $this->contact('89120003344'));
        $this->activity($b2, 'lead_form', ['provider' => 'vk'], '2026-09-06 10:00:00');
        $c1 = $this->deal('2026-09-04 10:00:00', 'nt');                                     // нецелевой (спам), написал в чат Авито
        DB::table('deals')->where('id', $c1)->update(['non_target_category' => 'spam']);
        $this->message($c1, 'avito', '2026-09-04 10:00:00');
        $e1 = $this->deal('2026-08-15 10:00:00', 'measure');                                // записан в августе, в сентябре написал
        $this->toMeasure($e1, '2026-08-20 10:00:00');
        $this->message($e1, 'vk', '2026-09-08 10:00:00');
        $out = $this->deal('2026-09-07 10:00:00', 'done', '2026-09-07 11:00:00', 'extra_non_target');
        $this->activity($out, 'call', ['type' => 'OUTGOING', 'callid' => 'o1'], '2026-09-07 10:00:00');
        $this->activity($out, 'call', ['type' => 'out', 'callid' => 'o1'], '2026-09-07 10:01:00');
        $this->activity($a1, 'call', ['type' => 'INCOMING', 'callid' => 'x0', 'diversion' => '79225085574'], '2026-08-31 23:00:00'); // вне периода

        // в таблице замеров — свои цифры; в обращения они не подставляются
        DB::table('owner_marketing_daily')->where('source', 'sheet')->where('day', 'like', '2026-09%')->delete();
        DB::table('owner_marketing_daily')->insert(['source' => 'sheet', 'day' => '2026-09-05', 'metrics' => json_encode(['total' => 6, 'sources' => ['ВК' => 2, 'Авито' => 1, 'Офис' => 3]], JSON_UNESCAPED_UNICODE), 'created_at' => now(), 'updated_at' => now()]);

        $res = $this->actingAs($this->owner())->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']));
        $res->assertOk();
        $c = $res->viewData('kpi')['contacts'];
        // клиент А (записан), B (два контакта, в работе), c1 (нецелевой), e1 (записан ещё в августе — в работе)
        $this->assertSame([4, 1, 1, 2, 0, 1], [$c['total'], $c['booked'], $c['non_target'], $c['open'], $c['lost'], $c['booked_earlier']]);
        $cg = collect($c['groups'])->keyBy('key');
        $this->assertSame([3, 1, 0], [$cg['vk']['total'], $cg['vk']['booked'], $cg['vk']['non_target']]);
        $this->assertSame([                                                            // A — первым позвонил, B и e1 — написали
            'chat_vk' => ['total' => 2, 'booked' => 0, 'non_target' => 0, 'cats' => ['open' => 2]],
            'call' => ['total' => 1, 'booked' => 1, 'non_target' => 0, 'cats' => ['booked' => 1]],
        ], $cg['vk']['kinds']);
        $this->assertSame(['booked' => 1, 'open' => 2, 'spam' => 1], $c['cats']);
        $this->assertSame([1, 0, 1], [$cg['avito']['total'], $cg['avito']['booked'], $cg['avito']['non_target']]);
        $this->assertSame(['2026-09-02' => 1, '2026-09-03' => 1, '2026-09-04' => 1, '2026-09-08' => 1], $c['by_day']);
        $this->assertSame(6, $res->viewData('measures')['total']);                   // плитка «Замеры» — по таблице, как была

        $res->assertSee('Обратились в КЦ')->assertSee('Обращения в колл-центр');
        $html = preg_replace('/\s+/u', ' ', strip_tags(str_replace('<', ' <', $res->getContent())));   // ячейки — через пробел
        $this->assertStringContainsString('Написали в соцсети и чаты 3 нецелевые 1 (33%) · ВК 2, чат Авито 1 записались 0 0%', $html);
        $this->assertStringContainsString('Позвонили 1 нецелевые 0 (0%) записались 1 100%', $html);
        // колонки: обратились | целевой записанный | целевой отказ | 6 категорий нецелевых | в работе | % записи
        $this->assertStringContainsString('Обратились Целевой записанный Целевой отказ Дилерский Запись на монтаж Сливы и доп. работы Реклама и спам Запрос на связь с замерщиком Претензии В работе % записи', $html);
        $this->assertStringNotContainsString('Нецелевые без категории', $html);   // все нецелевые периода — с категорией
        $this->assertStringContainsString('Авито написали в чат 1 0 0 0 0 0 1 0 0 0 0%', $html);
        $this->assertStringContainsString('ВКонтакте 3 1 0 0 0 0 0 0 0 2 33% написали в сообщения 2 0 0 0 0 0 0 0 0 2 0% позвонили 1 1 0 0 0 0 0 0 0 0 100%', $html);
        $this->assertStringContainsString('Итого 4 1 0 0 0 0 1 0 0 2 25%', $html);
    }

    public function test_nonclosures_from_blagodar_summary(): void
    {
        config(['owner.nonclosures' => ['url' => null, 'token' => null]]);
        $owner = $this->owner();
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']))
            ->assertOk()->assertSee('появится здесь');

        config(['owner.nonclosures' => ['url' => 'https://blagodar.test', 'token' => 'tkn']]);
        \Illuminate\Support\Facades\Cache::forget('owner.nonclosures.2026-09-01.2026-09-30');
        \App\Services\Owner\Marketing\Http::$fake = function (string $method, string $url, array $headers) {
            $this->assertSame('https://blagodar.test/api/reports/nonclosures/summary?from=2026-09-01&to=2026-09-30', $url);
            $this->assertSame('Bearer tkn', $headers['Authorization']);

            return [200, json_encode([
                'updated' => ['kc_sheet' => '2026-10-09T10:20:00+00:00', 'onec' => '2026-10-09T10:05:00+00:00'],
                'stale' => [],
                'blocks' => [
                    ['key' => 'ceilings', 'title' => 'Потолки', 'rows' => [['measurer' => 'Иванов', 'measurements' => 40, 'not_concluded' => 10], ['measurer' => 'Петров', 'measurements' => 20, 'not_concluded' => 8]], 'total' => ['measurements' => 60, 'not_concluded' => 18]],
                    ['key' => 'conditioners', 'title' => 'Кондиционеры', 'rows' => [], 'total' => ['measurements' => 0, 'not_concluded' => 0]],
                ],
                'discrepancies' => 3,
                'url' => 'https://blagodar.test/reports/nonclosures',
            ])];
        };
        try {
            // блоки CRM БлагоДар догружаются отдельным запросом (страница их не ждёт)
            $res = $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30', 'part' => 'blagodar']));
            $page = $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-30']));
        } finally {
            \App\Services\Owner\Marketing\Http::$fake = null;
            \Illuminate\Support\Facades\Cache::forget('owner.nonclosures.2026-09-01.2026-09-30');
        }
        $res->assertOk();
        $nc = $res->viewData('nonclosures');
        $this->assertSame('ok', $nc['state']);
        $this->assertSame(25.0, $nc['data']['blocks'][0]['rows'][0]['percent']);
        $this->assertSame(30.0, $nc['data']['blocks'][0]['total']['percent']);
        $res->assertSee('Иванов')->assertSee('полный отчёт');
        $page->assertOk()->assertSee('Иванов')->assertDontSee('class="bd-lazy"', false);   // теперь из кэша — сразу на странице
    }

    public function test_switch_only_for_owner_of_all_businesses(): void
    {
        $this->actingAs($this->owner(false))->get(route('owner.ceilings'))->assertForbidden();
        $this->actingAs($this->owner(true, 'sneaker_head'))->get(route('owner.ceilings'))->assertForbidden();

        $this->actingAs($this->owner(false))->get(route('owner.dashboard'))->assertOk()->assertDontSee(route('owner.ceilings'));
        $this->actingAs($this->owner(true))->get(route('owner.dashboard'))->assertOk()->assertSee(route('owner.ceilings'))
            ->assertSee('Продажи за день');                    // у кроссовок кнопка есть, на потолках — нет (тест сводки рекламы)
    }
}
