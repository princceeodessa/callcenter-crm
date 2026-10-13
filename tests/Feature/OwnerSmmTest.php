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
}
