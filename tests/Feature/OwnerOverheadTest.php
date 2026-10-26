<?php

namespace Tests\Feature;

use App\Services\Owner\Overhead;
use Carbon\Carbon;
use Tests\TestCase;

/**
 * Общие расходы потолков (config owner.overhead): кассиры за день × дни периода, маркетинг / радио / газеты в месяц —
 * за неполный месяц по дням, реклама — из CRM; будущие дни периода не считаются.
 */
class OwnerOverheadTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-26 12:00:00');
        config(['owner.overhead' => ['cashiers_per_day' => 4700, 'monthly' => [
            'marketing' => ['Маркетинг', 300000], 'radio' => ['Радио', 80000], 'newspapers' => ['Газеты', 20000],
        ]]]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function sums(array $o): array
    {
        return array_combine(array_column($o['items'], 'key'), array_column($o['items'], 'sum'));
    }

    public function test_full_month(): void
    {
        $o = Overhead::forPeriod(Carbon::parse('2026-09-01'), Carbon::parse('2026-10-01'), 50000);
        $this->assertSame(30, $o['days']);
        $this->assertEquals(['cashiers' => 141000, 'marketing' => 300000, 'radio' => 80000, 'newspapers' => 20000, 'ads' => 50000], $this->sums($o));
        $this->assertEquals(591000, $o['total']);
        $this->assertSame('4 700 ₽ × 30 дней', $o['items'][0]['note']);
        $this->assertSame('300 000 ₽ в месяц', $o['items'][1]['note']);
    }

    public function test_current_month_counts_only_days_so_far(): void
    {
        // «этот месяц» — до сегодня включительно: 26 дней октября из 31
        $o = Overhead::forPeriod(Carbon::parse('2026-10-01'), Carbon::parse('2026-11-01'), 0);
        $this->assertSame(26, $o['days']);
        $this->assertEquals(['cashiers' => 122200, 'marketing' => 251612.9, 'radio' => 67096.77, 'newspapers' => 16774.19, 'ads' => 0], $this->sums($o));
        $this->assertSame('300 000 ₽ в месяц · за 26 дней', $o['items'][1]['note']);

        // целиком в будущем — расходов нет
        $this->assertSame(0, Overhead::forPeriod(Carbon::parse('2026-11-01'), Carbon::parse('2026-12-01'), 0)['days']);
        $this->assertEquals(0, Overhead::forPeriod(Carbon::parse('2026-11-01'), Carbon::parse('2026-12-01'), 0)['total']);
    }

    public function test_period_across_months_prorated_by_each_month(): void
    {
        // 16–30 сентября (15 из 30) + 1–15 октября (15 из 31)
        $o = Overhead::forPeriod(Carbon::parse('2026-09-16'), Carbon::parse('2026-10-16'), 0);
        $this->assertSame(30, $o['days']);
        $this->assertEquals(round(300000 * (15 / 30 + 15 / 31), 2), $this->sums($o)['marketing']);
        $this->assertSame('4 700 ₽ × 30 дней', $o['items'][0]['note']);
        $this->assertSame('4 700 ₽ × 1 день', Overhead::forPeriod(Carbon::parse('2026-10-05'), Carbon::parse('2026-10-06'), 0)['items'][0]['note']);
        $this->assertSame('4 700 ₽ × 22 дня', Overhead::forPeriod(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-23'), 0)['items'][0]['note']);
    }
}
