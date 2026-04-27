<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportController;
use App\Models\CallRecording;
use App\Models\Deal;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class ReportControllerTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_default_month_is_current_calendar_month(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-23 09:00:00'));

        $user = new User([
            'account_id' => 1,
            'role' => 'main_operator',
        ]);

        $this->assertSame('2026-04', $this->invokeReportMethod('resolveDefaultMonth', [$user]));
    }

    public function test_closed_deal_attribution_prefers_closer_over_responsible(): void
    {
        $deal = new Deal();
        $deal->forceFill([
            'id' => 101,
            'responsible_user_id' => 1,
            'closed_by_user_id' => 2,
            'closed_result' => 'won',
        ]);

        $byUser = $this->invokeReportMethod('closedDealsByReportUser', [
            new Collection([$deal]),
            [1 => true, 2 => true],
            [],
            [],
        ]);

        $this->assertArrayHasKey(2, $byUser);
        $this->assertArrayNotHasKey(1, $byUser);
        $this->assertSame(101, $byUser[2]->first()->id);
    }

    public function test_closed_result_is_normalized_from_legacy_values(): void
    {
        $this->assertSame('won', $this->invokeReportMethod('normalizeClosedResult', ['success']));
        $this->assertSame('won', $this->invokeReportMethod('normalizeClosedResult', ["\u{0443}\u{0441}\u{043F}\u{0435}\u{0448}\u{043D}\u{043E}"]));
        $this->assertSame('lost', $this->invokeReportMethod('normalizeClosedResult', ['refused']));
        $this->assertSame('lost', $this->invokeReportMethod('normalizeClosedResult', ["\u{043E}\u{0442}\u{043A}\u{0430}\u{0437}"]));
        $this->assertSame('extra_non_target', $this->invokeReportMethod('normalizeClosedResult', ['extra_non_target']));
        $this->assertSame('extra_non_target', $this->invokeReportMethod('normalizeClosedResult', ["\u{043D}\u{0435} \u{0446}\u{0435}\u{043B}\u{0435}\u{0432}\u{043E}\u{0439}"]));
    }

    public function test_deal_source_stats_uses_integration_event_payload_for_recording_callid(): void
    {
        $recording = new CallRecording([
            'deal_id' => 101,
            'callid' => 'NFDB8TVEIO00004B',
        ]);

        $stats = $this->invokeReportMethod('dealSourceStats', [
            new Collection([101]),
            new Collection(),
            new Collection([101 => new Collection([$recording])]),
            new Collection([
                'NFDB8TVEIO00004B' => new Collection([[
                    'type' => 'in',
                    'diversion' => '8-922-509-00-14',
                ]]),
            ]),
        ]);

        $this->assertSame(1, $stats['counts']['phone:79225090014']);
        $this->assertSame(0, $stats['uncategorized']);
    }

    private function invokeReportMethod(string $methodName, array $arguments): mixed
    {
        $method = new ReflectionMethod(ReportController::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(new ReportController(), $arguments);
    }
}
