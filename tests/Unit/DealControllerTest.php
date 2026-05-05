<?php

namespace Tests\Unit;

use App\Http\Controllers\DealController;
use App\Models\PipelineStage;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class DealControllerTest extends TestCase
{
    public function test_closed_deal_is_reopened_only_for_different_non_final_stage(): void
    {
        $workingStage = new PipelineStage();
        $workingStage->forceFill([
            'id' => 11,
            'is_final' => false,
        ]);

        $finalStage = new PipelineStage();
        $finalStage->forceFill([
            'id' => 12,
            'is_final' => true,
        ]);

        $this->assertTrue($this->invokeDealMethod('shouldReopenDealOnStageChange', [true, 10, $workingStage]));
        $this->assertFalse($this->invokeDealMethod('shouldReopenDealOnStageChange', [true, 11, $workingStage]));
        $this->assertFalse($this->invokeDealMethod('shouldReopenDealOnStageChange', [true, 10, $finalStage]));
        $this->assertFalse($this->invokeDealMethod('shouldReopenDealOnStageChange', [false, 10, $workingStage]));
    }

    public function test_reopened_activity_body_mentions_previous_result_and_reason(): void
    {
        $body = $this->invokeDealMethod('dealReopenedActivityBody', ['extra_non_target', 'Нажали случайно']);

        $this->assertStringContainsString('Сделка переоткрыта.', $body);
        $this->assertStringContainsString('Доп.Работы / Не целевой', $body);
        $this->assertStringContainsString('Нажали случайно', $body);
    }

    private function invokeDealMethod(string $methodName, array $arguments): mixed
    {
        $method = new ReflectionMethod(DealController::class, $methodName);
        $method->setAccessible(true);

        return $method->invokeArgs(new DealController(), $arguments);
    }
}
