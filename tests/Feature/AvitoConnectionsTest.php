<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Conversation;
use App\Models\IntegrationConnection;
use App\Models\Message;
use App\Services\Integrations\AvitoConnections;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/** Два кабинета Авито в одном аккаунте (связанный «Дмитрий Мастер», 03.11.2026): у каждого чата — свой кабинет */
class AvitoConnectionsTest extends TestCase
{
    use DatabaseTransactions;

    public function test_chat_uses_the_cabinet_it_came_from(): void
    {
        $acc = Account::create(['name' => 'Потолки (тест кабинетов Авито)'])->id;
        $main = IntegrationConnection::create(['account_id' => $acc, 'provider' => 'avito', 'status' => 'active', 'settings' => ['user_id' => '45091775', 'client_id' => 'a']]);
        $linked = IntegrationConnection::create(['account_id' => $acc, 'provider' => 'avito', 'status' => 'active', 'settings' => ['user_id' => '450308372', 'client_id' => 'b']]);

        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $stage = DB::table('pipeline_stages')->insertGetId(['account_id' => $acc, 'pipeline_id' => $pipeline, 'name' => 'Лид', 'sort' => 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $chat = function (string $ext, ?string $cabinet) use ($acc, $pipeline, $stage) {
            $deal = DB::table('deals')->insertGetId(['account_id' => $acc, 'pipeline_id' => $pipeline, 'stage_id' => $stage, 'title' => 'Авито', 'created_at' => now(), 'updated_at' => now()]);
            $c = Conversation::create(['account_id' => $acc, 'deal_id' => $deal, 'channel' => 'avito', 'external_id' => $ext, 'status' => 'open', 'unread_count' => 0, 'meta' => ['lead_name' => 'Ольга']]);
            if ($cabinet !== null) {   // так входящие кладёт опрос: payload.account_id — id кабинета
                Message::create(['account_id' => $acc, 'conversation_id' => $c->id, 'direction' => 'in', 'author' => 'Ольга', 'body' => 'Здравствуйте',
                    'external_id' => 'm-'.$ext, 'payload' => ['chat_id' => $ext, 'account_id' => $cabinet], 'status' => 'ok']);
            }

            return $c;
        };

        $fromLinked = $chat('u2i-linked', '450308372');
        $this->assertSame($linked->id, AvitoConnections::forConversation($fromLinked)->id);
        $this->assertSame('450308372', $fromLinked->fresh()->meta['avito_user_id'], 'кабинет запомнен в чате');
        $this->assertSame('Ольга', $fromLinked->fresh()->meta['lead_name']);

        $this->assertSame($main->id, AvitoConnections::forConversation($chat('u2i-main', '45091775'))->id);
        $this->assertSame($main->id, AvitoConnections::forConversation($chat('u2i-none', null))->id, 'неизвестно чей — основной');

        $linked->update(['status' => 'error']);
        $this->assertSame($main->id, AvitoConnections::forConversation($fromLinked->fresh())->id, 'для чтения истории — только рабочий');
        $this->assertSame($linked->id, AvitoConnections::forConversation($fromLinked->fresh(), ['active', 'error'])->id, 'ответ — из своего кабинета');
    }
}
