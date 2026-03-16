<?php

namespace Tests\Unit;

use App\Models\Deal;
use App\Models\DealActivity;
use PHPUnit\Framework\TestCase;

class DealCallEmployeeTest extends TestCase
{
    public function test_it_resolves_the_answering_employee_for_incoming_calls(): void
    {
        $payload = [
            'type' => 'in',
            'status' => 'answered',
            'user' => 'ivan_petrov',
        ];

        $this->assertSame('Ivan Petrov', Deal::resolveCallEmployeeFromPayload($payload));
    }

    public function test_it_skips_missed_and_outgoing_calls(): void
    {
        $this->assertNull(Deal::resolveCallEmployeeFromPayload([
            'type' => 'missed',
            'user' => 'ivan_petrov',
        ]));

        $this->assertNull(Deal::resolveCallEmployeeFromPayload([
            'type' => 'out',
            'user' => 'ivan_petrov',
        ]));
    }

    public function test_it_reads_nested_employee_data_and_accessor_uses_loaded_relation(): void
    {
        $payload = [
            'type' => 'in',
            'status' => 'connected',
            'call' => [
                'answered_by' => [
                    'name' => 'Иван Петров',
                ],
            ],
        ];

        $deal = new Deal();
        $deal->setRelation('latestCallActivity', new DealActivity([
            'type' => 'call',
            'payload' => $payload,
        ]));

        $this->assertSame('Иван Петров', $deal->latest_call_answered_by_label);
    }
}
