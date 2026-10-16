<?php

namespace Tests\Feature;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Период на страницах владельца выбирается календарём (как в макете дизайнера): сводка кроссовок — те же периоды,
 * что у потолков, свои даты и старые ссылки ?month=; SMM — 7/30/60 дней или свои даты.
 */
class OwnerPeriodsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 12:00:00');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function owner(): User
    {
        $u = User::create([
            'account_id' => User::where('role', 'sneaker_head')->firstOrFail()->account_id, 'name' => 'Рустам Тестов',
            'email' => 'owner_period_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true,
        ]);
        $u->forceFill(['all_businesses' => true])->save();

        return $u;
    }

    public function test_sneakers_summary_periods(): void
    {
        $owner = $this->owner();
        $res = $this->actingAs($owner)->get(route('owner.dashboard', ['period' => 'custom', 'from' => '2026-09-01', 'to' => '2026-09-10']))->assertOk();
        $this->assertSame(['2026-09-01', '2026-09-10', 'custom'], [$res->viewData('fromValue'), $res->viewData('toValue'), $res->viewData('period')]);
        $this->assertCount(10, $res->viewData('series')['rows']);
        $res->assertSee('data-daterange', false)->assertSee('01.09 — 10.09.2026')->assertSee('Добрый день, <span class="name">Рустам</span>', false);

        // старые ссылки на месяц
        $res = $this->actingAs($owner)->get(route('owner.dashboard', ['month' => '2026-08']))->assertOk();
        $this->assertSame(['2026-08-01', '2026-08-31'], [$res->viewData('fromValue'), $res->viewData('toValue')]);

        // «Этот месяц» по умолчанию — ряд по сегодняшний день
        $res = $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk();
        $this->assertSame('month', $res->viewData('period'));
        $this->assertCount(15, $res->viewData('series')['rows']);
    }

    public function test_smm_custom_dates_and_ceilings_calendar(): void
    {
        $owner = $this->owner();
        $res = $this->actingAs($owner)->get(route('owner.smm', ['business' => 'ceilings', 'from' => '2026-09-01', 'to' => '2026-09-30']))->assertOk();
        $report = $res->viewData('report');
        $this->assertSame(['2026-09-01', '2026-09-30', 30], [$report['date_from'], $report['date_to'], $report['period_days']]);
        $this->assertNull($res->viewData('days'));                     // ни одна из кнопок 7/30/60 не выбрана
        $res->assertSee('data-daterange', false);

        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-14']))
            ->assertOk()->assertSee('data-daterange', false)->assertSee('01.10 — 14.10.2026');
    }

    /** Кроссовки — под логотипом и названием «Шаговый»; потолки — «БлагоДар». «Отчёт по месяцам» — сводка за весь месяц. */
    public function test_shag_brand_and_month_report(): void
    {
        $owner = $this->owner();
        $this->actingAs($owner)->get(route('owner.dashboard'))->assertOk()
            ->assertSee('<title>Кроссовки · Шаговый</title>', false)->assertSee('Шаговый<small>Сводка бизнеса</small>', false)->assertSee('sneaker-touch-icon-180.png', false);
        $this->actingAs($owner)->get(route('owner.smm', ['business' => 'sneakers']))->assertOk()->assertSee('Шаговый<small>', false);
        $this->actingAs($owner)->get(route('owner.smm', ['business' => 'ceilings']))->assertOk()->assertSee('БлагоДар<small>', false);
        $res = $this->actingAs($owner)->get(route('owner.ceilings', ['month' => '2026-09']))->assertOk()
            ->assertSee('БлагоДар<small>Сводка бизнеса</small>', false)->assertSee('отчёт за сентябрь 2026')
            ->assertSee('Отчёт по месяцам')->assertSee('id="bdMonths"', false)->assertSee('Обращения в Excel');
        $this->assertSame(['2026-09-01', '2026-09-30'], [$res->viewData('fromValue'), $res->viewData('toValue')]);
        $this->assertMatchesRegularExpression('~class="month-btn active" href="[^"]*month=2026-09"~', $res->getContent());
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => '7d']))->assertOk()->assertDontSee('отчёт за ');
    }

    /** С телефона боковое меню открывается кнопкой ☰ — и с потолков можно перейти в «Продажи за день». */
    public function test_owner_pages_have_mobile_menu(): void
    {
        $owner = $this->owner();
        foreach ([route('owner.ceilings'), route('owner.dashboard'), route('owner.smm', ['business' => 'sneakers']), route('sale.day')] as $url) {
            $this->actingAs($owner)->get($url)->assertOk()->assertSee('id="bdMenu"', false)->assertSee('id="bdSide"', false);
        }
        $this->actingAs($owner)->get(route('owner.ceilings'))->assertSee(route('sale.day'), false)->assertSee('Продажи за день');
    }
}
