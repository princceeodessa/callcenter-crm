<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatHistorySyncService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * История чатов Авито (messenger v3, 03.11.2026): догруженные сообщения встают по времени, а не в конец;
 * имя клиента у уже записанных сообщений не затирается «avito:123».
 */
class ChatHistorySyncTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $conv;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест истории чатов)'])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $stage = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $pipeline, 'name' => 'Поступил лид', 'sort' => 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $deal = DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $pipeline, 'stage_id' => $stage, 'title' => 'Авито', 'created_at' => now(), 'updated_at' => now()]);
        $this->conv = DB::table('conversations')->insertGetId([
            'account_id' => $this->acc, 'deal_id' => $deal, 'channel' => 'avito', 'external_id' => 'u2i-test', 'status' => 'open',
            'unread_count' => 0, 'last_message_at' => now(), 'meta' => json_encode(['lead_name' => 'Мария', 'display_name' => 'Мария']),
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_history_keeps_names_and_backfilled_messages_go_by_time(): void
    {
        // из опроса: последнее сообщение, подписано именем
        $polled = Message::create(['account_id' => $this->acc, 'conversation_id' => $this->conv, 'direction' => 'in', 'author' => 'Мария',
            'body' => 'Цену скажите', 'external_id' => 'm3', 'payload' => ['chat_id' => 'u2i-test'], 'status' => 'ok']);

        $sync = app(ChatHistorySyncService::class);
        $upsert = new \ReflectionMethod($sync, 'upsertAvitoMessage');
        $upsert->setAccessible(true);
        $conv = Conversation::findOrFail($this->conv);
        $base = now()->subHour()->startOfMinute()->getTimestamp();
        foreach ([
            ['id' => 'm3', 'author_id' => 7, 'created' => $base + 180, 'direction' => 'in', 'type' => 'text', 'content' => ['text' => 'Цену скажите']],
            ['id' => 'm1', 'author_id' => 7, 'created' => $base, 'direction' => 'in', 'type' => 'text', 'content' => ['text' => 'Здравствуйте']],
            ['id' => 'm2', 'author_id' => 99, 'created' => $base + 60, 'direction' => 'out', 'type' => 'text', 'content' => ['text' => 'Добрый день!']],
        ] as $m) {
            $upsert->invoke($sync, $conv, $m, '99');
        }

        // удалённое клиентом: история отдаёт «Сообщение удалено» — сохранённый текст остаётся; заглушку — заменяем текстом
        Message::create(['account_id' => $this->acc, 'conversation_id' => $this->conv, 'direction' => 'in', 'author' => 'Мария', 'body' => 'Пушкинская 165', 'external_id' => 'm4', 'status' => 'ok']);
        Message::create(['account_id' => $this->acc, 'conversation_id' => $this->conv, 'direction' => 'in', 'author' => 'Мария',
            'body' => 'Сообщение не поддерживается. Пожалуйста, перейдите в Авито мессенджер', 'external_id' => 'm5', 'status' => 'ok']);
        Message::create(['account_id' => $this->acc, 'conversation_id' => $this->conv, 'direction' => 'out', 'author' => 'user:4', 'body' => 'Записали', 'external_id' => 'm6', 'status' => 'sent']);
        $upsert->invoke($sync, $conv, ['id' => 'm4', 'author_id' => 7, 'created' => $base + 240, 'direction' => 'in', 'type' => 'deleted', 'content' => ['text' => 'Сообщение удалено']], '99');
        $upsert->invoke($sync, $conv, ['id' => 'm5', 'author_id' => 7, 'created' => $base + 300, 'direction' => 'in', 'type' => 'text', 'content' => ['text' => 'Отклик на вакансию']], '99');
        $upsert->invoke($sync, $conv, ['id' => 'm6', 'author_id' => 99, 'created' => $base + 360, 'direction' => 'out', 'type' => 'text', 'content' => ['text' => 'Записали']], '99');
        $this->assertSame('Пушкинская 165', Message::where('external_id', 'm4')->where('conversation_id', $this->conv)->value('body'));
        $this->assertSame('Отклик на вакансию', Message::where('external_id', 'm5')->where('conversation_id', $this->conv)->value('body'));
        $this->assertSame('user:4', Message::where('external_id', 'm6')->where('conversation_id', $this->conv)->value('author'), 'оператор ответа из CRM не затёрт');

        $polled->refresh();
        $this->assertSame('Мария', $polled->author, 'имя из опроса не затёрто');
        $this->assertSame($base + 180, $polled->created_at->getTimestamp(), 'время — из Авито');
        $this->assertSame('Мария', Message::where('conversation_id', $this->conv)->where('external_id', 'm1')->value('author'), 'догруженное входящее — с именем из чата');
        $this->assertSame('out', Message::where('conversation_id', $this->conv)->where('external_id', 'm2')->value('direction'));

        $head = User::create(['account_id' => $this->acc, 'name' => 'Виктория', 'email' => 'chs_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'main_operator', 'is_active' => true]);
        $res = $this->actingAs($head)->get(route('chats.index', ['c' => $this->conv]))->assertOk();
        $this->assertSame(['m1', 'm2', 'm3', 'm4', 'm5', 'm6'], $res->viewData('messages')->pluck('external_id')->all(), 'по времени, хотя m3 записан первым');

        $polledIds = collect($this->actingAs($head)->getJson(route('chats.poll', $this->conv))->assertOk()->json('messages'))->pluck('id')->all();
        $this->assertSame(Message::where('conversation_id', $this->conv)->orderBy('created_at')->pluck('id')->all(), $polledIds);
    }

    /** ВК: вебхук пишет входящие по conversation_message_id, отправка из CRM — по id; синк истории их не задваивает */
    public function test_vk_history_does_not_duplicate_webhook_and_sent_messages(): void
    {
        $deal = DB::table('conversations')->where('id', $this->conv)->value('deal_id');
        $vkConv = DB::table('conversations')->insertGetId(['account_id' => $this->acc, 'deal_id' => $deal, 'channel' => 'vk', 'external_id' => '123456',
            'status' => 'open', 'unread_count' => 0, 'last_message_at' => now(), 'meta' => json_encode(['lead_name' => 'Ольга']), 'created_at' => now(), 'updated_at' => now()]);
        Message::create(['account_id' => $this->acc, 'conversation_id' => $vkConv, 'direction' => 'in', 'author' => 'Ольга', 'body' => 'Сколько стоит?', 'external_id' => '24', 'status' => 'ok']);
        Message::create(['account_id' => $this->acc, 'conversation_id' => $vkConv, 'direction' => 'out', 'author' => 'Виктория', 'body' => 'Добрый день', 'external_id' => '158070', 'status' => 'sent']);

        $sync = app(ChatHistorySyncService::class);
        $upsert = new \ReflectionMethod($sync, 'upsertVkMessage');
        $upsert->setAccessible(true);
        $conv = Conversation::findOrFail($vkConv);
        $t = now()->subMinutes(5)->getTimestamp();
        $upsert->invoke($sync, $conv, ['id' => 158069, 'conversation_message_id' => 24, 'out' => 0, 'from_id' => 77, 'text' => 'Сколько стоит?', 'date' => $t]);
        $upsert->invoke($sync, $conv, ['id' => 158070, 'conversation_message_id' => 25, 'out' => 1, 'from_id' => -1, 'text' => 'Добрый день', 'date' => $t + 60]);
        $upsert->invoke($sync, $conv, ['id' => 158071, 'conversation_message_id' => 26, 'out' => 0, 'from_id' => 77, 'text' => 'Спасибо', 'date' => $t + 120]);

        $this->assertSame(['24', '158070', '26'], Message::where('conversation_id', $vkConv)->orderBy('id')->pluck('external_id')->all());
        $this->assertSame('Виктория', Message::where('conversation_id', $vkConv)->where('external_id', '158070')->value('author'));
    }
}
