<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\Conversation;
use App\Models\Deal;
use App\Models\IntegrationConnection;
use App\Services\Chat\ChatIngestService;
use App\Services\Integrations\AvitoConnections;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * «Звонок через Авито» (05.11.2026): звонок приходит через МегаФон на номер кабинета, а Авито в момент окончания
 * звонка пишет в чат appCall — клиент должен остаться одной сделкой (звонок + чат), а не двумя.
 */
class AvitoCallLinkTest extends TestCase
{
    use DatabaseTransactions;

    private int $acc;
    private int $pipeline;
    private int $stage;
    private IntegrationConnection $private;

    protected function setUp(): void
    {
        parent::setUp();
        AvitoConnections::flushCache();
        $this->acc = Account::create(['name' => 'Потолки (тест звонка через Авито)'])->id;
        $this->pipeline = DB::table('pipelines')->insertGetId(['account_id' => $this->acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $this->stage = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'name' => 'Поступил лид', 'sort' => 10, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        IntegrationConnection::create(['account_id' => $this->acc, 'provider' => 'avito', 'status' => 'active', 'settings' => ['user_id' => '45091775']]);
        $this->private = IntegrationConnection::create(['account_id' => $this->acc, 'provider' => 'avito', 'status' => 'active', 'settings' => ['user_id' => '450308372', 'private' => true]]);
        DB::table('phone_sources')->updateOrInsert(['number' => '79225090047'], ['label' => 'авито частник', 'sort_order' => 35, 'created_at' => now(), 'updated_at' => now()]);
    }

    protected function tearDown(): void
    {
        AvitoConnections::flushCache();
        parent::tearDown();
    }

    /** Звонок, как его кладёт МегаФон: начало, а по окончании — COMPLETED и history (start в UTC + длительность). */
    private function phoneCall(string $dialled, string $phone, Carbon $start, ?int $duration, ?Deal $deal = null): Deal
    {
        $deal ??= Deal::findOrFail(DB::table('deals')->insertGetId(['account_id' => $this->acc, 'pipeline_id' => $this->pipeline, 'stage_id' => $this->stage,
            'title' => 'Звонки: '.$phone, 'title_is_custom' => 0, 'contact_id' => DB::table('contacts')->insertGetId(['account_id' => $this->acc, 'name' => 'Клиент '.$phone, 'phone' => $phone, 'created_at' => now(), 'updated_at' => now()]),
            'created_at' => $start, 'updated_at' => $start]));
        $callid = uniqid();
        $row = fn (array $p, Carbon $at) => ['account_id' => $this->acc, 'deal_id' => $deal->id, 'type' => 'call', 'body' => 'Звонок',
            'payload' => json_encode($p + ['callid' => $callid, 'telnum' => $dialled, 'diversion' => $dialled, 'phone' => $phone]), 'created_at' => $at, 'updated_at' => $at];
        DB::table('deal_activities')->insert($row(['cmd' => 'contact'], $start));
        DB::table('deal_activities')->insert($row(['cmd' => 'event', 'type' => 'INCOMING', 'direction' => 'in'], $start));
        if ($duration !== null) {
            $end = $start->copy()->addSeconds($duration);
            DB::table('deal_activities')->insert($row(['cmd' => 'event', 'type' => 'COMPLETED', 'direction' => 'in'], $end->copy()->addSeconds(10)));
            DB::table('deal_activities')->insert($row(['cmd' => 'history', 'type' => 'in', 'status' => 'Success', 'duration' => (string) $duration,
                'start' => $start->copy()->utc()->format('Ymd\THis\Z')], $end->copy()->addSeconds(10)));
        }

        return $deal;
    }

    /** Новый чат, как его приносит опрос Авито: первое сообщение — appCall в момент окончания звонка. */
    private function appCall(Carbon $at, string $name, string $cabinet = '450308372'): Conversation
    {
        $chat = 'u2i-'.uniqid();
        app(ChatIngestService::class)->ingestFromAvito($this->private, [
            'chat_id' => $chat,
            'account_id' => $cabinet,
            'chat' => ['users' => [['id' => $cabinet, 'name' => 'Дмитрий Мастер'], ['id' => '777', 'name' => $name]]],
            'message' => ['id' => uniqid(), 'type' => 'appCall', 'created' => $at->getTimestamp(), 'author_id' => 777, 'content' => []],
        ]);

        return Conversation::where('external_id', $chat)->firstOrFail();
    }

    public function test_avito_call_chat_joins_the_call_deal(): void
    {
        $start = now()->subMinutes(10)->startOfMinute();
        $call = $this->phoneCall('79225090047', '79120204435', $start, 320);
        $other = $this->phoneCall('79225090014', '79991112233', $start->copy()->addSeconds(30), 290);   // другая линия, тот же момент

        $conv = $this->appCall($start->copy()->addSeconds(321), 'Рамиль Бадретдинов');

        $this->assertSame($call->id, (int) $conv->deal_id, 'чат — в сделке звонка, не новой');
        $this->assertSame(2, Deal::where('account_id', $this->acc)->count());
        $deal = $call->fresh();
        $this->assertSame('Рамиль Бадретдинов — Avito', $deal->title);
        $this->assertSame('Рамиль Бадретдинов', $deal->contact->name);
        $this->assertSame('79120204435', $deal->contact->phone);
        $this->assertTrue((bool) $deal->is_unread);
        $this->assertSame(1, DB::table('deal_activities')->where('deal_id', $call->id)->where('type', 'system')->where('body', 'like', 'Клиент звонил через Авито%')->count());
        $this->assertNotSame($other->id, (int) $conv->deal_id);
    }

    public function test_chat_arriving_before_the_call_end_joins_the_running_call(): void
    {
        // опрос Авито успел раньше, чем МегаФон прислал конец звонка
        $start = now()->subMinutes(4)->startOfMinute();
        $call = $this->phoneCall('79225090047', '79120234134', $start, null);

        $this->assertSame($call->id, (int) $this->appCall($start->copy()->addSeconds(200), 'Марина')->deal_id);
    }

    public function test_no_guessing(): void
    {
        $start = now()->subMinutes(20)->startOfMinute();
        // звонок на номер частника закончился давно — это другой клиент
        $this->phoneCall('79225090047', '79124486733', $start, 60);
        $lonely = $this->appCall($start->copy()->addMinutes(12), 'Юлия');
        $this->assertStringContainsString('Юлия', Deal::findOrFail($lonely->deal_id)->title);
        $this->assertSame(0, DB::table('deal_activities')->where('deal_id', $lonely->deal_id)->where('type', 'call')->count(), 'новая сделка');

        // два звонка на номер частника закончились одновременно — не угадываем, кто из них
        $t = now()->subMinutes(8)->startOfMinute();
        $this->phoneCall('79225090047', '79828162665', $t, 100);
        $this->phoneCall('79225150259', '79501633474', $t->copy()->addSeconds(2), 99);
        $both = $this->appCall($t->copy()->addSeconds(101), 'Анна');
        $this->assertSame(0, DB::table('deal_activities')->where('deal_id', $both->deal_id)->where('type', 'call')->count());

        // чат основного кабинета не цепляется к звонку на номер частника
        $s = now()->subMinutes(3)->startOfMinute();
        $this->phoneCall('79225090047', '79630270505', $s, 40);
        $main = $this->appCall($s->copy()->addSeconds(41), 'Олег', '45091775');
        $this->assertSame(0, DB::table('deal_activities')->where('deal_id', $main->deal_id)->where('type', 'call')->count());

        // обычное сообщение (не звонок) — как раньше, своя сделка
        $chat = 'u2i-'.uniqid();
        app(ChatIngestService::class)->ingestFromAvito($this->private, ['chat_id' => $chat, 'account_id' => '450308372',
            'message' => ['id' => uniqid(), 'type' => 'text', 'created' => $s->copy()->addSeconds(42)->getTimestamp(), 'author_id' => 778, 'content' => ['text' => 'Здравствуйте']]]);
        $this->assertSame(0, DB::table('deal_activities')->where('deal_id', Conversation::where('external_id', $chat)->value('deal_id'))->where('type', 'call')->count());
    }
}
