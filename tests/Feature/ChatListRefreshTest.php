<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Message;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Автообновление «Чатов» (04.11.2026): список подтягивается сам — новые чаты и сообщения видны без F5;
 * открытый и видимый чат сам становится прочитанным; в превью — последнее по времени сообщение.
 */
class ChatListRefreshTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $deal;
    private User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест списка чатов)'])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $stage = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $pipeline, 'name' => 'Лид', 'sort' => 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $this->deal = DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $pipeline, 'stage_id' => $stage, 'title' => 'Лид', 'is_unread' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->head = User::create(['account_id' => $this->acc, 'name' => 'Виктория', 'email' => 'clr_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'main_operator', 'is_active' => true]);
    }

    private function chat(string $name, int $unread, string $at): int
    {
        return DB::table('conversations')->insertGetId(['account_id' => $this->acc, 'deal_id' => $this->deal, 'channel' => 'telegram', 'external_id' => uniqid(), 'status' => 'open', 'unread_count' => $unread, 'last_message_at' => $at, 'meta' => json_encode(['lead_name' => $name]), 'created_at' => now(), 'updated_at' => now()]);
    }

    private function message(int $conv, string $body, string $at): Message
    {
        $m = Message::create(['account_id' => $this->acc, 'conversation_id' => $conv, 'direction' => 'in', 'author' => 'Клиент', 'body' => $body, 'external_id' => uniqid(), 'status' => 'ok']);
        $m->timestamps = false;
        $m->forceFill(['created_at' => $at])->save();

        return $m;
    }

    public function test_list_refresh_shows_new_chats_without_marking_them_read(): void
    {
        $old = $this->chat('Ольга', 0, now()->subHour()->toDateTimeString());
        $this->message($old, 'Добрый день', now()->subHour()->toDateTimeString());

        $page = $this->actingAs($this->head)->get(route('chats.index', ['c' => $old]))->assertOk()->getContent();
        $this->assertStringContainsString('id="chatList"', $page);
        $this->assertStringContainsString('const listUrl = '.json_encode(route('chats.list')).';', $page);
        $this->assertStringContainsString('data-conv="'.$old.'"', $page);

        // пока страница открыта, написал новый клиент
        $new = $this->chat('Марина', 2, now()->toDateTimeString());
        $this->message($new, 'Сколько стоит потолок?', now()->toDateTimeString());

        $j = $this->actingAs($this->head)->getJson(route('chats.list', ['c' => $old]))->assertOk()->json();
        $this->assertSame(1, $j['unread_conversations']);
        $this->assertSame(2, $j['unread_messages']);
        $this->assertSame('— 2 новых в 1 чатах', $j['sub']);
        $this->assertStringContainsString('data-conv="'.$new.'"', $j['html']);
        $this->assertStringContainsString('Сколько стоит потолок?', $j['html']);
        $this->assertLessThan(strpos($j['html'], 'data-conv="'.$old.'"'), strpos($j['html'], 'data-conv="'.$new.'"'), 'новый чат — сверху');
        $this->assertMatchesRegularExpression('/class="nd-chat-i nd-on"[^>]*data-conv="'.$old.'"/', $j['html'], 'открытый чат выделен');
        $this->assertSame(2, (int) DB::table('conversations')->where('id', $new)->value('unread_count'), 'список не отмечает прочитанным');
    }

    public function test_open_visible_chat_becomes_read_on_poll(): void
    {
        $conv = $this->chat('Ольга', 0, now()->toDateTimeString());
        $this->message($conv, 'Добрый день', now()->toDateTimeString());
        DB::table('conversations')->where('id', $conv)->update(['unread_count' => 1]);
        DB::table('deals')->where('id', $this->deal)->update(['is_unread' => 1]);

        $this->actingAs($this->head)->getJson(route('chats.poll', $conv))->assertOk();   // вкладка свёрнута — не трогаем
        $this->assertSame(1, (int) DB::table('conversations')->where('id', $conv)->value('unread_count'));

        $this->actingAs($this->head)->getJson(route('chats.poll', ['conversation' => $conv, 'read' => 1]))->assertOk();
        $this->assertSame(0, (int) DB::table('conversations')->where('id', $conv)->value('unread_count'));
        $this->assertSame(0, (int) DB::table('deals')->where('id', $this->deal)->value('is_unread'));
    }

    public function test_preview_is_the_latest_message_by_time(): void
    {
        $conv = $this->chat('Ольга', 0, now()->toDateTimeString());
        $this->message($conv, 'Спасибо, ждём замерщика', now()->toDateTimeString());
        $this->message($conv, 'Добрый день', now()->subDay()->toDateTimeString());   // догружено позже, номер больше

        $j = $this->actingAs($this->head)->getJson(route('chats.list'))->assertOk()->json();
        $this->assertStringContainsString('Спасибо, ждём замерщика', $j['html']);
        $this->assertStringNotContainsString('Добрый день', $j['html']);
    }
}
