<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Deal;
use App\Models\IntegrationConnection;
use App\Models\Message;
use App\Models\User;
use App\Services\Chat\ChatIngestService;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Regression test for the 2026-07-09 "Регина" incident: two Avito events for the
 * same chat, processed close together, each ran the old check-then-create
 * Conversation lookup before either had committed — producing two Deals for one
 * lead. ChatIngestService::ingestGeneric() now serializes per-conversation via
 * LeadDeduplicator::withLock().
 */
class ChatIngestDuplicateDealTest extends TestCase
{
    use DatabaseTransactions;

    public function test_redelivering_the_same_avito_message_does_not_create_a_second_deal(): void
    {
        $accountId = User::where('role', 'admin')->first()?->account_id
            ?? User::query()->firstOrFail()->account_id;

        $connection = IntegrationConnection::create([
            'account_id' => $accountId,
            'provider' => 'avito',
            'status' => 'active',
            'settings' => [],
        ]);

        $payload = [
            'chat_id' => 'test-chat-'.uniqid(),
            'id' => 'test-msg-'.uniqid(),
            'author_id' => 555111,
            'account_id' => 999222,
            'text' => 'Здравствуйте, хочу заказать потолок',
            'chat' => [
                'users' => [
                    ['id' => '999222', 'name' => 'Наш магазин'],
                    ['id' => '555111', 'name' => 'Тест Регина'],
                ],
            ],
        ];

        $service = new ChatIngestService();

        $first = $service->ingestFromAvito($connection, $payload);
        $second = $service->ingestFromAvito($connection, $payload);

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertEquals($first->id, $second->id, 'redelivery of the same external message must not create a new Message row');

        $conversations = Conversation::where('account_id', $accountId)
            ->where('channel', 'avito')->where('external_id', $payload['chat_id'])->get();
        $this->assertCount(1, $conversations, 'must not create a second Conversation for the same chat_id');

        $deals = Deal::where('account_id', $accountId)->where('id', $conversations->first()->deal_id)->get();
        $this->assertCount(1, $deals);

        $messages = Message::where('conversation_id', $conversations->first()->id)->get();
        $this->assertCount(1, $messages, 'must not create a second Message for the same external_message_id');
    }
}
