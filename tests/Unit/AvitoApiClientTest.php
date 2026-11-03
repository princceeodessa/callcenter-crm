<?php

namespace Tests\Unit;

use App\Services\Integrations\AvitoApiClient;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** История чата Авито — через messenger v3 (v2 — 404, v1 — 405 с 2026 года) */
class AvitoApiClientTest extends TestCase
{
    public function test_messages_come_from_v3(): void
    {
        if (! class_exists(\GuzzleHttp\Psr7\Response::class)) {
            $this->markTestSkipped('нет guzzlehttp/psr7 (в локальном vendor его нет — на проде есть)');
        }
        Http::fake([
            'api.avito.ru/messenger/v3/accounts/42/chats/u2i-abc/messages/*' => Http::response(['messages' => [
                ['id' => 'm2', 'author_id' => 7, 'created' => 1793698200, 'direction' => 'in', 'type' => 'text', 'content' => ['text' => 'Цену скажите']],
                ['id' => 'm1', 'author_id' => 7, 'created' => 1793698100, 'direction' => 'in', 'type' => 'text', 'content' => ['text' => 'Здравствуйте']],
            ], 'meta' => []]),
            'api.avito.ru/messenger/v2/*' => Http::response('404 page not found', 404),
            'api.avito.ru/messenger/v1/*' => Http::response('', 405),
        ]);

        $messages = (new AvitoApiClient('token'))->listMessages(42, 'u2i-abc', 50);

        $this->assertSame(['m2', 'm1'], array_column($messages, 'id'));
        Http::assertSent(fn ($r) => str_contains($r->url(), '/messenger/v3/accounts/42/chats/u2i-abc/messages/') && str_contains($r->url(), 'limit=50'));
    }
}
