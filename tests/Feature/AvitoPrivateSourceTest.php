<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\IntegrationConnection;
use App\Models\Message;
use App\Models\User;
use App\Services\Integrations\AvitoConnections;
use App\Support\Ui\Nordika;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «Авито частник» — своим цветом, не как Авито (03.11.2026): звонки на номер с подписью «авито частник» и чаты
 * связанного кабинета («Дмитрий Мастер»).
 */
class AvitoPrivateSourceTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $pipeline;
    private int $stage;

    protected function setUp(): void
    {
        parent::setUp();
        AvitoConnections::flushCache();
        $this->acc = Account::create(['name' => 'Потолки (тест частника)'])->id;
        $this->pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->stage = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'name' => 'Поступил лид', 'sort' => 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        IntegrationConnection::create(['account_id' => $this->acc, 'provider' => 'avito', 'status' => 'active', 'settings' => ['user_id' => '45091775']]);
        IntegrationConnection::create(['account_id' => $this->acc, 'provider' => 'avito', 'status' => 'active', 'settings' => ['user_id' => '450308372', 'private' => true]]);
    }

    protected function tearDown(): void
    {
        AvitoConnections::flushCache();
        parent::tearDown();
    }

    private function deal(): Deal
    {
        return Deal::findOrFail(DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage, 'title' => 'Лид', 'created_at' => now(), 'updated_at' => now()]));
    }

    private function avitoChat(string $cabinet): Deal
    {
        $deal = $this->deal();
        $conv = Conversation::create(['account_id' => $this->acc, 'deal_id' => $deal->id, 'channel' => 'avito', 'external_id' => 'u2i-'.uniqid(), 'status' => 'open', 'unread_count' => 1, 'last_message_at' => now(), 'meta' => ['lead_name' => 'Ольга']]);
        // так входящее кладёт опрос: payload.account_id — id кабинета; чат запоминает кабинет сам
        Message::create(['account_id' => $this->acc, 'conversation_id' => $conv->id, 'direction' => 'in', 'author' => 'Ольга', 'body' => 'Здравствуйте', 'external_id' => 'm-'.uniqid(), 'payload' => ['chat_id' => $conv->external_id, 'account_id' => $cabinet], 'status' => 'ok']);
        $this->assertSame($cabinet, $conv->fresh()->meta['avito_user_id']);
        $this->assertSame('Ольга', $conv->fresh()->meta['lead_name']);

        return $deal->fresh();
    }

    private function callTo(string $dialled): Deal
    {
        $deal = $this->deal();
        DB::table('deal_activities')->insert(['account_id' => $this->acc, 'deal_id' => $deal->id, 'type' => 'call', 'body' => 'Входящий',
            'payload' => json_encode(['cmd' => 'event', 'type' => 'INCOMING', 'callid' => uniqid(), 'diversion' => $dialled, 'phone' => '79120000000']), 'created_at' => now(), 'updated_at' => now()]);

        return $deal->fresh();
    }

    public function test_private_chats_and_calls_get_their_own_chip(): void
    {
        $this->assertSame('avito_private', Nordika::sourceKey($this->avitoChat('450308372')));
        $this->assertSame('avito', Nordika::sourceKey($this->avitoChat('45091775')));
        $this->assertSame('avito_private', Nordika::sourceKey($this->callTo('79225150259')), 'номер с подписью «авито частник»');
        $this->assertSame('call', Nordika::sourceKey($this->callTo('79221797710')), 'номер «авито» — обычный звонок');

        $chip = Nordika::sourceChip('avito_private');
        $this->assertStringContainsString('--src:var(--avp)', $chip);
        $this->assertStringContainsString('Частник', $chip);
        $this->assertNotSame(Nordika::source('avito')[1], Nordika::source('avito_private')[1]);
    }

    public function test_chats_page_marks_the_private_chat(): void
    {
        $deal = $this->avitoChat('450308372');
        $conv = Conversation::where('deal_id', $deal->id)->firstOrFail();
        $this->assertSame('avito_private', Nordika::chatSourceKey($conv));

        $head = User::create(['account_id' => $this->acc, 'name' => 'Виктория', 'email' => 'avp_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'main_operator', 'is_active' => true]);
        $html = $this->actingAs($head)->get(route('chats.index', ['c' => $conv->id]))->assertOk()->getContent();
        $this->assertStringContainsString('var(--avp)', $html);
    }
}
