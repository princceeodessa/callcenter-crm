<?php

namespace Tests\Unit;

use App\Console\Commands\PollAvitoChats;
use PHPUnit\Framework\TestCase;

/**
 * Опрос чатов Авито: помнит последнее сообщение каждого чата (порог был 1000) и не листает по кругу одну и ту же
 * страницу (Авито не учитывает offset) — 23.10.2026 из-за этого запуск шёл 4–16 минут и держал планировщик.
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

    /** Авито отдаёт ту же первую страницу при любом offset — повтор страницы значит «список кончился». */
    public function test_a_repeated_page_has_no_fresh_chats(): void
    {
        $page = [['id' => 'a'], ['id' => 'b'], ['chat_id' => 'c'], ['id' => '']];
        $fresh = PollAvitoChats::freshChatIds($page, []);
        $this->assertSame(['a', 'b', 'c'], $fresh);
        $this->assertSame([], PollAvitoChats::freshChatIds($page, array_fill_keys($fresh, true)), 'та же страница — листать дальше нечего');
        $this->assertSame(['d'], PollAvitoChats::freshChatIds([['id' => 'b'], ['id' => 'd']], array_fill_keys($fresh, true)));
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
