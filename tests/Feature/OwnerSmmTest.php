<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Owner\SmmReport;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «SMM-контент» в сводке владельца: реклама клипов VK по бизнесу в виде отчёта SMM-специалиста — кампании за
 * период, клипы с модерацией, итоги, динамика CTR, расход по дням по типам; доступ как у сводок.
 */
class OwnerSmmTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-20 12:00:00');
        DB::table('owner_marketing_daily')->delete();
        DB::table('owner_marketing_sources')->delete();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function owner(bool $all = true): User
    {
        $sneakers = User::where('role', 'sneaker_head')->firstOrFail()->account_id;
        $u = User::create([
            'account_id' => $sneakers, 'name' => 'Владелец (тест)', 'email' => 'owner_smm_'.uniqid(),
            'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true,
        ]);
        $u->forceFill(['all_businesses' => $all])->save();

        return $u;
    }

    /** Кабинет «потолков»: клиповая кампания (2 клипа, один отклонён), лид-форма на паузе и старая кампания весны. */
    private function seedCeilings(): void
    {
        DB::table('owner_marketing_sources')->insert([
            'source' => 'vk', 'last_ok_at' => '2026-10-20 10:00:00', 'created_at' => now(), 'updated_at' => now(),
            'meta' => json_encode([
                'plans' => [
                    ['id' => 1, 'name' => 'Клипы БлагоДар', 'status' => 'active', 'objective' => 'branding_socialengagement', 'day_limit' => 3000, 'created' => '2026-09-25 11:00:00'],
                    ['id' => 2, 'name' => 'Потолки 2+3 (лид-форма)', 'status' => 'blocked', 'objective' => 'leadads', 'day_limit' => 1000, 'created' => '2026-09-25 12:00:00'],
                    ['id' => 3, 'name' => 'Весна', 'status' => 'blocked', 'objective' => 'leadads', 'day_limit' => null, 'created' => '2026-04-01 10:00:00'],
                ],
                'groups' => [
                    10 => ['name' => 'Клип 111', 'status' => 'active', 'plan_id' => 1, 'plan' => 'Клипы БлагоДар', 'plan_status' => 'active', 'objective' => 'branding_socialengagement', 'price' => 150, 'day_limit' => 3000, 'created' => '2026-09-25 11:00:00', 'issues' => [], 'moderation' => 'allowed', 'reasons' => [], 'clip' => 'https://vk.com/clip-82903833_111', 'owner' => '-82903833', 'clip_id' => '111'],
                    11 => ['name' => 'Клип 222', 'status' => 'active', 'plan_id' => 1, 'plan' => 'Клипы БлагоДар', 'plan_status' => 'active', 'objective' => 'branding_socialengagement', 'price' => 150, 'issues' => ['NO_ALLOWED_BANNERS'], 'moderation' => 'banned', 'reasons' => ['NO_ORGN_BANNER'], 'clip' => 'https://vk.com/clip-82903833_222', 'owner' => '-82903833', 'clip_id' => '222'],
                    20 => ['name' => 'Потолки 2+3', 'status' => 'active', 'plan_id' => 2, 'plan' => 'Потолки 2+3 (лид-форма)', 'plan_status' => 'blocked', 'objective' => 'leadads', 'issues' => ['AD_PLAN_STOPPED'], 'moderation' => 'allowed', 'reasons' => []],
                    30 => ['name' => 'Весенняя', 'status' => 'blocked', 'plan_id' => 3, 'plan' => 'Весна', 'plan_status' => 'blocked', 'objective' => 'leadads'],
                ],
            ], JSON_UNESCAPED_UNICODE),
        ]);
        // клип 111: 16 дней показов — CTR первых 7 дней 0,50%, последних 7 — 0,30%; лид-форма — 2 дня с заявками
        for ($i = 0; $i < 16; $i++) {
            $day = Carbon::parse('2026-10-01')->addDays($i)->toDateString();
            $groups = [10 => ['spent' => 150.0, 'shows' => 1000, 'clicks' => $i < 7 ? 5 : 3, 'views3' => 400, 'views100' => 200, 'joins' => 0, 'goals' => 0]];
            $spent = 150.0;
            if ($i < 2) {
                $groups[20] = ['spent' => 500.0, 'shows' => 2000, 'clicks' => 20, 'views3' => 0, 'views100' => 0, 'joins' => 0, 'goals' => 3];
                $spent += 500 + 40;   // ещё 40 ₽ — кампания без групп в отчёте
            }
            DB::table('owner_marketing_daily')->insert(['source' => 'vk', 'day' => $day, 'metrics' => json_encode(['spent' => $spent, 'groups' => $groups]), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function test_report_matches_the_smm_specialist_shape(): void
    {
        $this->seedCeilings();
        $c = SmmReport::cabinet('ceilings', Carbon::parse('2026-09-21'), Carbon::parse('2026-10-21'));

        $this->assertSame('potolki', $c['key']);
        $this->assertSame([1, 2], array_column($c['plans'], 'id'), 'весенняя кампания без расходов в периоде — не показывается');
        $g = collect($c['groups'])->keyBy('id');
        $this->assertSame(['clip', 'clip', 'lead'], [$g[10]['type'], $g[11]['type'], $g[20]['type']]);
        $this->assertSame(16000, $g[10]['shows']);
        $this->assertSame(3200, $g[10]['v100']);
        $this->assertEqualsWithDelta(2400.0, $g[10]['spent'], 0.001);
        $this->assertSame(16, $g[10]['active_days']);
        $this->assertSame(7, $g[10]['window']);
        $this->assertSame(0.5, $g[10]['ctr_first']);
        $this->assertSame(0.3, $g[10]['ctr_last']);
        $this->assertSame('111', $g[10]['clip']);
        $this->assertSame(['NO_ORGN_BANNER'], $g[11]['reasons']);
        $this->assertSame('banned', $g[11]['moderation']);
        $this->assertSame(6, $g[20]['leads']);
        $day1 = collect($c['days'])->firstWhere('d', '2026-10-01');
        $this->assertSame(['d' => '2026-10-01', 'clip' => 150.0, 'lead' => 500.0, 'other' => 40.0], $day1);
        $this->assertCount(30, $c['days']);
        $this->assertNull($c['error']);
    }

    public function test_pages_and_access(): void
    {
        $this->seedCeilings();
        $all = $this->owner(true);
        $one = $this->owner(false);

        $res = $this->actingAs($all)->get(route('owner.smm', ['business' => 'ceilings', 'days' => 30]))->assertOk();
        $res->assertSee('SMM-контент')->assertSee('Каждый клип')->assertSee('Что улучшить')->assertSee('id="report-data"', false);
        $this->assertStringContainsString('"period_days":30', $res->getContent());
        $this->assertStringNotContainsString('Для SMM: предложение', $res->getContent(), 'конструктор предложения — для SMM-специалиста, не для владельца');

        $this->actingAs($one)->get(route('owner.smm', ['business' => 'ceilings']))->assertForbidden();
        $this->actingAs($one)->get(route('owner.smm', ['business' => 'sneakers']))->assertOk()
            ->assertSee('ещё не подключён', false);                                   // кабинет «Шагового» без ключей
        $this->actingAs($all)->get(route('owner.ceilings'))->assertOk()->assertSee(route('owner.smm', ['business' => 'ceilings']));
        $this->actingAs($one)->get(route('owner.dashboard'))->assertOk()->assertSee(route('owner.smm', ['business' => 'sneakers']));
    }

    /** SMM-специалист: аккаунт заводит админ, после входа — «SMM-контент»; CRM, сводки, поиск и уведомления ему закрыты. */
    public function test_smm_specialist_account_sees_only_vk_pages(): void
    {
        $this->seedCeilings();
        $admin = User::where('role', 'admin')->where('account_id', (int) config('owner.ceilings_account_id'))->firstOrFail();
        $login = 'smm_'.uniqid();
        $this->actingAs($admin)->post(route('settings.users.store'), ['name' => 'СММ Тест', 'login' => $login, 'password' => 'secret123', 'role' => 'smm', 'is_active' => 1])
            ->assertRedirect(route('settings.users.index'));
        $smm = User::where('email', $login)->firstOrFail();
        $this->assertSame(['ceilings', 'sneakers'], $smm->smmBusinesses(), 'оба сообщества');
        auth()->logout();

        $this->post(route('login.perform'), ['login' => $login, 'password' => 'secret123'])
            ->assertRedirect(route('owner.smm', ['business' => 'ceilings']));

        $page = $this->actingAs($smm)->get(route('owner.smm', ['business' => 'ceilings', 'days' => 30]))->assertOk()
            ->assertSee('Результат для бизнеса')->assertSee('Клиентов из ВК')->assertSee('Цена замера')->assertSee('Каждый клип')
            ->assertSee('ВК · Шаговый')->assertSee('SMM-специалист')
            ->assertDontSee('id="bdSearchBox"', false)->assertDontSee('id="bdBell"', false)->assertDontSee(route('owner.ceilings'));
        $this->assertStringContainsString('window.bdFeedUrl = null', $page->getContent());
        $this->actingAs($smm)->get(route('owner.smm', ['business' => 'sneakers']))->assertOk()->assertSee('Продаж из ВК');

        // всё остальное — назад на его страницу или закрыто
        foreach ([route('owner.dashboard'), route('owner.ceilings'), route('owner.dashboard', ['feed' => 'search', 'q' => 'ив']), route('deals.kanban'), route('chats.index')] as $url) {
            $this->actingAs($smm)->get($url)->assertRedirect(route('owner.smm', ['business' => 'ceilings']));
        }
        $this->actingAs($smm)->get(route('reports.monthly'))->assertForbidden();
        $this->actingAs($smm)->get(route('settings.users.index'))->assertForbidden();

        // SMM одного пространства (без «оба бизнеса») — только своё сообщество
        $smm->forceFill(['all_businesses' => false])->save();
        $this->actingAs($smm->fresh())->get(route('owner.smm', ['business' => 'sneakers']))->assertForbidden();
    }

    /** «Результат для бизнеса» потолков: лиды из ВК (сообщения, лид-формы) за период, замеры, цена клиента и замера. */
    public function test_ceilings_results_count_vk_leads_and_bookings(): void
    {
        $acc = \App\Models\Account::create(['name' => 'Потолки (тест SMM)'])->id;
        config(['owner.ceilings_account_id' => $acc]);
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $stage = [];
        foreach (['lead' => 'Поступил лид', 'measure' => 'Замер назначен'] as $k => $name) {
            $stage[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $acc, 'pipeline_id' => $pipeline, 'name' => $name, 'sort' => count($stage) * 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $deal = fn (string $at) => DB::table('deals')->insertGetId(['account_id' => $acc, 'pipeline_id' => $pipeline, 'stage_id' => $stage['lead'], 'title' => 'Лид', 'created_at' => $at, 'updated_at' => $at]);
        $chat = function (string $channel, string $at) use ($acc, $deal) {
            $id = $deal($at);
            DB::table('conversations')->insert(['account_id' => $acc, 'deal_id' => $id, 'channel' => $channel, 'created_at' => $at, 'updated_at' => $at]);

            return $id;
        };
        $vk1 = $chat('vk', '2026-10-05 10:00:00');                          // написал в сообщения — записан
        DB::table('deal_stage_history')->insert(['account_id' => $acc, 'deal_id' => $vk1, 'from_stage_id' => $stage['lead'], 'to_stage_id' => $stage['measure'], 'changed_by_user_id' => null, 'changed_at' => '2026-10-05 11:00:00']);
        $form = $deal('2026-10-06 10:00:00');                               // лид-форма ВК — ещё не записан
        DB::table('deal_activities')->insert(['account_id' => $acc, 'deal_id' => $form, 'type' => 'lead_form', 'payload' => json_encode(['provider' => 'vk']), 'created_at' => '2026-10-06 10:00:00', 'updated_at' => '2026-10-06 10:00:00']);
        $chat('avito', '2026-10-07 10:00:00');                              // Авито — не ВК
        $chat('vk', '2026-09-01 10:00:00');                                 // до периода

        $r = \App\Services\Owner\SmmResults::forBusiness('ceilings', Carbon::parse('2026-10-01'), Carbon::parse('2026-10-21'), 3000.0);
        $this->assertSame([2, 1, 0, 50, 1500.0, 3000.0], [$r['leads'], $r['booked'], $r['non_target'], (int) $r['conversion'], (float) $r['per_lead'], (float) $r['per_booked']]);
        $this->assertEqualsCanonicalizing(['ВК · сообщения', 'ВК · лид-форма'], array_column($r['sources'], 'label'));
    }

    /** «Результат для бизнеса» кроссовок: обращения из сообщений ВК, продажи с источником «ВК» или из сообщений ВК. */
    public function test_sneakers_results_count_vk_contacts_and_sales(): void
    {
        $acc = (int) User::where('role', 'sneaker_head')->orderBy('id')->value('account_id');
        $pipeline = DB::table('pipelines')->where('account_id', $acc)->orderByDesc('is_default')->value('id');
        $stageId = DB::table('pipeline_stages')->where('pipeline_id', $pipeline)->orderBy('sort')->value('id');
        $deal = fn (string $created, ?string $sold, ?string $source) => DB::table('deals')->insertGetId(['account_id' => $acc, 'pipeline_id' => $pipeline, 'stage_id' => $stageId,
            'title' => 'Тест SMM', 'created_at' => $created, 'updated_at' => $created, 'stock_deducted_at' => $sold, 'manual_source' => $source, 'sold_quantity' => $sold ? 1 : null]);
        $fromChat = $deal('2020-01-01 10:00:00', '2020-01-01 12:00:00', null);    // написал в ВК и купил
        DB::table('conversations')->insert(['account_id' => $acc, 'deal_id' => $fromChat, 'channel' => 'vk', 'created_at' => '2020-01-01 10:00:00', 'updated_at' => '2020-01-01 10:00:00']);
        $deal('2019-12-01 10:00:00', '2020-01-01 15:00:00', 'ВК');                 // продавец указал источник «ВК»
        $deal('2020-01-01 11:00:00', '2020-01-01 16:00:00', 'Avito');             // не ВК

        $r = \App\Services\Owner\SmmResults::forBusiness('sneakers', Carbon::parse('2020-01-01'), Carbon::parse('2020-01-02'), 1000.0);
        $this->assertSame([1, 2, 1000.0, 500.0], [$r['leads'], $r['sales'], (float) $r['per_lead'], (float) $r['per_sale']]);
    }
}
