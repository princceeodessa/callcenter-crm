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

    public function test_it_resolves_tv_call_source_by_tracked_number(): void
    {
        $payload = [
            'type' => 'in',
            'diversion' => '8-922-509-00-14',
        ];

        $this->assertSame([
            'number' => '79225090014',
            'label' => 'ТВ',
        ], Deal::resolveIncomingPhoneSourceFromPayload($payload));

        $this->assertSame('phone:79225090014', Deal::resolveIncomingPhoneSourceFilterKeyFromPayload($payload));
        $this->assertSame('ТВ', Deal::incomingPhoneSourceOptions()['phone:79225090014']);
    }

    /** Операторы звонят и с рекламных номеров — исходящий звонок не делает номер источником (03.11.2026) */
    public function test_outgoing_calls_from_a_tracked_number_are_not_a_source(): void
    {
        foreach (['OUTGOING', 'ACCEPTED', 'COMPLETED'] as $type) {
            $this->assertNull(Deal::resolveIncomingPhoneSourceFromPayload([
                'cmd' => 'event', 'type' => $type, 'direction' => 'out', 'telnum' => '79225090014', 'diversion' => '79225090014',
            ]), $type);
        }
        $this->assertNull(Deal::resolveIncomingPhoneSourceFromPayload(['cmd' => 'history', 'type' => 'out', 'diversion' => '79225090014']));

        $this->assertSame('phone:79225090014', Deal::resolveIncomingPhoneSourceFilterKeyFromPayload([
            'cmd' => 'event', 'type' => 'ACCEPTED', 'direction' => 'in', 'diversion' => '79225090014',
        ]));
        $this->assertSame('phone:79225090014', Deal::resolveIncomingPhoneSourceFilterKeyFromPayload([
            'cmd' => 'contact', 'telnum' => '79225090014', 'diversion' => '79225090014',
        ]));
    }

    /** telnum — линия, куда пришёл звонок; источник — номер, на который позвонили (diversion) */
    public function test_source_is_the_dialled_number_not_the_answering_line(): void
    {
        $this->assertSame('phone:79221797710', Deal::resolveIncomingPhoneSourceFilterKeyFromPayload([
            'cmd' => 'event', 'type' => 'INCOMING', 'telnum' => '79225090014', 'diversion' => '79221797710',
        ]));
        $this->assertNull(Deal::resolveIncomingPhoneSourceFromPayload([
            'cmd' => 'event', 'type' => 'INCOMING', 'telnum' => '79225090014', 'diversion' => '79650000000',
        ]), 'звонок на неотслеживаемый номер — не источник линии');
        $this->assertSame('phone:79225090014', Deal::resolveIncomingPhoneSourceFilterKeyFromPayload([
            'type' => 'in', 'to' => '+7 922 509-00-14',
        ]), 'без diversion — как раньше, по любому полю');
    }
}
