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
 * Сообщения без текста (04.11.2026): фото, голосовые, ссылки, объявления, стикеры, пересланные — не пустой пузырь;
 * в чате каждое сообщение один раз и по времени (номер последней записи — самый большой, не последний по времени).
 */
class MessageContentTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $avito;
    private int $vk;

    protected function setUp(): void
    {
        parent::setUp();
        $this->acc = Account::create(['name' => 'Потолки (тест вложений)'])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $stage = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $pipeline, 'name' => 'Лид', 'sort' => 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        $deal = DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $pipeline, 'stage_id' => $stage, 'title' => 'Лид', 'created_at' => now(), 'updated_at' => now()]);
        foreach (['avito' => 'u2i-x', 'vk' => '2000000'] as $ch => $ext) {
            $this->{$ch} = DB::table('conversations')->insertGetId(['account_id' => $this->acc, 'deal_id' => $deal, 'channel' => $ch, 'external_id' => $ext, 'status' => 'open', 'unread_count' => 0, 'last_message_at' => now(), 'meta' => json_encode(['lead_name' => 'Ольга']), 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    private function avito(array $msg): Message
    {
        return Message::create(['account_id' => $this->acc, 'conversation_id' => $this->avito, 'direction' => 'in', 'author' => 'Ольга', 'body' => '', 'external_id' => uniqid(), 'payload' => ['history_sync' => true, 'message' => $msg], 'status' => 'ok']);
    }

    public function test_avito_and_vk_non_text_messages_get_content(): void
    {
        $img = $this->avito(['type' => 'image', 'content' => ['image' => ['sizes' => ['140x105' => 'https://60.img.avito.st/s.jpg', '1280x960' => 'https://60.img.avito.st/b.jpg']]]]);
        $this->assertEquals([['type' => 'image', 'url' => 'https://60.img.avito.st/b.jpg']], $img->fresh()->payload['media']);
        $this->assertSame('🎤 Голосовое сообщение — открыть в приложении Авито', $this->avito(['type' => 'voice', 'content' => ['voice' => ['voice_id' => 'v1']]])->fresh()->body);
        $this->assertSame("🏷 Объявление: Натяжной потолок · 10 ₽\nhttps://avito.ru/item", $this->avito(['type' => 'item', 'content' => ['item' => ['title' => 'Натяжной потолок', 'price_string' => '10 ₽', 'item_url' => 'https://avito.ru/item']]])->fresh()->body);
        $this->assertStringStartsWith("📍 Ижевск\nhttps://yandex.ru/maps/?pt=53.15,57.18", $this->avito(['type' => 'location', 'content' => ['location' => ['text' => 'Ижевск', 'lat' => 57.18, 'lon' => 53.15]]])->fresh()->body);
        $this->assertSame('📞 Звонок через Авито', $this->avito(['type' => 'appCall', 'content' => []])->fresh()->body);

        // записанный текст не трогаем
        $kept = Message::create(['account_id' => $this->acc, 'conversation_id' => $this->avito, 'direction' => 'in', 'author' => 'Ольга', 'body' => 'Пушкинская 165', 'external_id' => uniqid(), 'payload' => ['message' => ['type' => 'deleted', 'content' => []]], 'status' => 'ok']);
        $this->assertSame('Пушкинская 165', $kept->fresh()->body);

        $vk = fn (array $object) => Message::create(['account_id' => $this->acc, 'conversation_id' => $this->vk, 'direction' => 'in', 'author' => 'Ольга', 'body' => '', 'external_id' => uniqid(),
            'payload' => ['type' => 'message_new', 'object' => ['message' => $object + ['peer_id' => 2000000, 'conversation_message_id' => 5]]], 'status' => 'ok'])->fresh();
        $sticker = $vk(['text' => '', 'attachments' => [['type' => 'sticker', 'sticker' => ['images' => [['url' => 'https://vk.com/s64.png', 'width' => 64, 'height' => 64], ['url' => 'https://vk.com/s256.png', 'width' => 256, 'height' => 256]]]]]]);
        $this->assertSame('https://vk.com/s256.png', $sticker->payload['media'][0]['url']);
        $voice = $vk(['text' => '', 'attachments' => [['type' => 'audio_message', 'audio_message' => ['link_mp3' => 'https://vk.com/a.mp3']]]]);
        $this->assertEquals(['type' => 'link', 'url' => 'https://vk.com/a.mp3', 'file_name' => '🎤 Голосовое сообщение — прослушать'], $voice->payload['media'][0]);
        $fwd = $vk(['text' => '', 'attachments' => [], 'fwd_messages' => [['text' => 'Сколько стоит потолок?', 'attachments' => []]]]);
        $this->assertSame("↪ Пересланное:\n— Сколько стоит потолок?", $fwd->body);
        $this->assertSame('📖 История', $vk(['text' => '', 'attachments' => [['type' => 'story', 'story' => []]]])->body);
    }

    public function test_chat_page_knows_the_largest_id_and_marks_bubbles(): void
    {
        // догруженное старое сообщение получило номер больше последнего по времени
        $late = Message::create(['account_id' => $this->acc, 'conversation_id' => $this->avito, 'direction' => 'in', 'author' => 'Ольга', 'body' => 'Спасибо!', 'external_id' => 'a', 'status' => 'ok']);
        $old = Message::create(['account_id' => $this->acc, 'conversation_id' => $this->avito, 'direction' => 'out', 'author' => 'user:avito', 'body' => 'Отправьте резюме', 'external_id' => 'b', 'status' => 'sent']);
        $old->timestamps = false;
        $old->forceFill(['created_at' => now()->subHour()])->save();

        $head = User::create(['account_id' => $this->acc, 'name' => 'Виктория', 'email' => 'mc_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'main_operator', 'is_active' => true]);
        $html = $this->actingAs($head)->get(route('chats.index', ['c' => $this->avito]))->assertOk()->getContent();
        $this->assertStringContainsString('let lastId = '.$old->id.';', $html, 'номер последней записи — самый большой');
        $this->assertStringContainsString('data-id="'.$late->id.'"', $html);
        $this->assertLessThan(strpos($html, 'data-id="'.$late->id.'"'), strpos($html, 'data-id="'.$old->id.'"'), 'старое — выше');
        $this->assertStringContainsString('function placeMessage(m)', $html);
    }
}
