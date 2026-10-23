<?php

namespace Tests\Unit;

use App\Console\Commands\PollAvitoChats;
use PHPUnit\Framework\TestCase;

/**
 * Опрос чатов Авито помнит последнее сообщение каждого чата. Порог был 1000: при 2790 чатах (23.10.2026) забытые
 * выкачивались заново каждую минуту, запуск шёл 10–16 минут и держал планировщик.
 */
class AvitoPollSeenTest extends TestCase
{
    public function test_all_chats_of_the_account_are_remembered(): void
    {
        $seen = [];
        for ($i = 0; $i < 2790; $i++) {
            $seen['c'.$i] = 'm'.$i;
        }
        $this->assertSame($seen, PollAvitoChats::rememberSeen($seen));
    }

    public function test_only_a_runaway_list_is_trimmed_keeping_the_newest(): void
    {
        $seen = [];
        for ($i = 0; $i < PollAvitoChats::SEEN_LIMIT + 5; $i++) {
            $seen['c'.$i] = 'm'.$i;
        }
        $kept = PollAvitoChats::rememberSeen($seen);
        $this->assertCount(PollAvitoChats::SEEN_LIMIT, $kept);
        $this->assertArrayNotHasKey('c4', $kept);
        $this->assertSame('m'.(PollAvitoChats::SEEN_LIMIT + 4), $kept['c'.(PollAvitoChats::SEEN_LIMIT + 4)]);
    }
}
