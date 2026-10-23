<?php

namespace Tests\Feature;

use App\Models\IntegrationConnection;
use App\Models\User;
use App\Services\Owner\Marketing\Http;
use App\Services\Owner\Marketing\MarketingCollector;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Instagram в «SMM-контенте»: админ потолков вставляет маркеры БлагоДара и «Шагового» в «Интеграции», сборщик раз в
 * 2 часа пишет подписчиков (снимки по дням не теряются) и публикации с просмотрами, страница показывает блок Instagram.
 */
class OwnerInstagramTest extends TestCase
{
    use DatabaseTransactions;

    private array $calls = [];

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-23 12:00:00');
        DB::table('owner_marketing_daily')->whereIn('source', ['ig', 'ig_shag'])->delete();
        DB::table('owner_marketing_sources')->whereIn('source', ['ig', 'ig_shag'])->delete();
        IntegrationConnection::withoutGlobalScopes()->where('provider', 'instagram')->delete();
        Http::$fake = function (string $method, string $url) {
            $this->calls[] = $url;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
            $path = (string) parse_url($url, PHP_URL_PATH);
            if (($q['access_token'] ?? '') === 'BAD') {
                return [400, json_encode(['error' => ['message' => 'Invalid OAuth access token - Cannot parse access token', 'code' => 190]])];
            }

            return match (true) {
                str_ends_with($path, '/refresh_access_token') => [200, json_encode(['access_token' => 'IGNEW', 'token_type' => 'bearer', 'expires_in' => 5184000])],
                str_ends_with($path, '/me') => [200, json_encode(['user_id' => '17841400000000001', 'username' => 'blagodar_izh', 'account_type' => 'BUSINESS', 'followers_count' => 1530, 'media_count' => 2])],
                str_ends_with($path, '/me/insights') => [200, json_encode(['data' => [['name' => 'follower_count', 'period' => 'day', 'values' => [
                    ['value' => 4, 'end_time' => '2026-10-22T07:00:00+0000'], ['value' => 6, 'end_time' => '2026-10-23T07:00:00+0000']]]]])],
                str_ends_with($path, '/me/media') => [200, json_encode(['data' => [
                    ['id' => 'm1', 'caption' => "Натяжной потолок\nза один день", 'media_type' => 'VIDEO', 'media_product_type' => 'REELS', 'permalink' => 'https://www.instagram.com/reel/AAA/', 'timestamp' => '2026-10-20T10:00:00+0000', 'like_count' => 40, 'comments_count' => 3],
                    ['id' => 'm2', 'caption' => 'Старый пост', 'media_type' => 'IMAGE', 'media_product_type' => 'FEED', 'permalink' => 'https://www.instagram.com/p/BBB/', 'timestamp' => '2026-08-01T10:00:00+0000', 'like_count' => 10, 'comments_count' => 0],
                ]])],
                str_ends_with($path, '/m1/insights') => [200, json_encode(['data' => [['name' => 'views', 'values' => [['value' => 5200]]], ['name' => 'reach', 'values' => [['value' => 3100]]], ['name' => 'saved', 'values' => [['value' => 12]]], ['name' => 'shares', 'values' => [['value' => 7]]]]])],
                // у фото Instagram не отдаёт views в общем запросе — берётся вторым
                str_ends_with($path, '/m2/insights') && str_contains((string) ($q['metric'] ?? ''), 'views') && str_contains((string) ($q['metric'] ?? ''), 'reach')
                    => [400, json_encode(['error' => ['message' => 'metric not supported', 'code' => 100]])],
                str_ends_with($path, '/m2/insights') => [200, json_encode(['data' => [['name' => 'reach', 'values' => [['value' => 900]]], ['name' => 'saved', 'values' => [['value' => 1]]], ['name' => 'shares', 'values' => [['value' => 0]]]]])],
                default => [404, '{}'],
            };
        };
    }

    protected function tearDown(): void
    {
        Http::$fake = null;
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function admin(): User
    {
        return User::where('role', 'admin')->where('account_id', (int) config('owner.ceilings_account_id'))->firstOrFail();
    }

    public function test_admin_connects_tokens_and_they_are_never_shown(): void
    {
        $this->actingAs($this->admin())->post(route('settings.integrations.connect', 'instagram'), ['ig_ceilings' => ' IGTOKEN1 ', 'ig_sneakers' => 'BAD'])
            ->assertSessionHasErrors('instagram');
        $s = IntegrationConnection::withoutGlobalScopes()->where('provider', 'instagram')->firstOrFail();
        $this->assertSame('active', $s->status);
        $this->assertSame(['token' => 'IGTOKEN1', 'username' => 'blagodar_izh', 'followers' => 1530], array_intersect_key($s->settings['accounts']['ceilings'], array_flip(['token', 'username', 'followers'])));
        $this->assertArrayNotHasKey('sneakers', $s->settings['accounts'], 'неподошедший маркер не сохраняется');
        $this->assertStringContainsString('Шаговый: токен не подошёл', (string) $s->last_error);

        $page = $this->actingAs($this->admin())->get(route('settings.integrations.index'))->assertOk()
            ->assertSee('Instagram (статистика)')->assertSee('подключён @blagodar_izh')->getContent();
        $this->assertStringNotContainsString('IGTOKEN1', $page);
        $this->actingAs($this->admin())->get(route('settings.integrations.show', 'instagram'))->assertOk()->assertDontSee('IGTOKEN1');
    }

    public function test_collector_keeps_follower_snapshots_and_collects_posts(): void
    {
        $this->connect(['ceilings' => 'IGTOKEN1']);
        DB::table('owner_marketing_daily')->insert(['source' => 'ig', 'day' => '2026-10-10', 'metrics' => json_encode(['followers' => 1500]), 'created_at' => now(), 'updated_at' => now()]);

        $res = (new MarketingCollector())->run(30, ['ig', 'ig_shag']);
        $this->assertStringStartsWith('ok', $res['ig']);
        $this->assertStringStartsWith('не настроен', $res['ig_shag'], '«Шаговый» не подключён — не собирается');
        $day = fn (string $d) => json_decode((string) DB::table('owner_marketing_daily')->where('source', 'ig')->where('day', $d)->value('metrics'), true);
        $this->assertSame(1500, $day('2026-10-10')['followers'], 'снимок прошлого дня не стёрт перезабором окна');
        $this->assertSame(['followers' => 1530], $day('2026-10-23'));
        // значение Instagram помечено концом дня: end_time 23.10 — это подписчики за 22.10
        $this->assertSame([6, 4], [$day('2026-10-22')['new_followers'], $day('2026-10-21')['new_followers']]);

        $meta = json_decode((string) DB::table('owner_marketing_sources')->where('source', 'ig')->value('meta'), true);
        $this->assertSame('blagodar_izh', $meta['profile']['username']);
        $this->assertSame(['REELS', 5200, 3100, 40, 3, 12, 7], [$meta['posts'][0]['type'], $meta['posts'][0]['views'], $meta['posts'][0]['reach'], $meta['posts'][0]['likes'], $meta['posts'][0]['comments'], $meta['posts'][0]['saved'], $meta['posts'][0]['shares']]);
        $this->assertSame('Натяжной потолок за один день', $meta['posts'][0]['caption']);
        $this->assertSame([null, 900], [$meta['posts'][1]['views'], $meta['posts'][1]['reach']]);
        $this->assertStringNotContainsString('IGTOKEN1', (string) json_encode($meta), 'маркер не попадает в собранное');
    }

    public function test_token_is_renewed_before_it_expires(): void
    {
        $this->connect(['ceilings' => 'IGOLD'], now()->addDays(10), now()->subDays(50));
        (new MarketingCollector())->run(30, ['ig']);
        $acc = IntegrationConnection::withoutGlobalScopes()->where('provider', 'instagram')->firstOrFail()->settings['accounts']['ceilings'];
        $this->assertSame('IGNEW', $acc['token']);
        $this->assertSame(now()->addSeconds(5184000)->toDateTimeString(), $acc['expires_at']);
    }

    public function test_smm_page_shows_instagram_block(): void
    {
        $this->connect(['ceilings' => 'IGTOKEN1']);
        DB::table('owner_marketing_daily')->insert(['source' => 'ig', 'day' => '2026-10-10', 'metrics' => json_encode(['followers' => 1500]), 'created_at' => now(), 'updated_at' => now()]);
        (new MarketingCollector())->run(30, ['ig']);
        $owner = User::create(['account_id' => User::where('role', 'sneaker_head')->firstOrFail()->account_id, 'name' => 'Владелец (тест)', 'email' => 'ig_owner_'.uniqid(),
            'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true]);
        $owner->forceFill(['all_businesses' => true])->save();

        $this->actingAs($owner)->get(route('owner.smm', ['business' => 'ceilings', 'days' => 30]))->assertOk()
            ->assertSee('Instagram · @blagodar_izh')->assertSee('1 530')->assertSee('+30 с 10.10')
            ->assertSee('Публикации за период')->assertSee('Натяжной потолок за один день')->assertSee('5 200')
            ->assertDontSee('Старый пост');                                         // август — вне 30 дней
        $this->actingAs($owner)->get(route('owner.smm', ['business' => 'sneakers']))->assertOk()
            ->assertSee('Instagram «Шаговый» ещё не подключён');
    }

    private function connect(array $tokens, ?Carbon $expires = null, ?Carbon $saved = null): void
    {
        $accounts = [];
        foreach ($tokens as $biz => $token) {
            $accounts[$biz] = ['token' => $token, 'username' => 'blagodar_izh', 'followers' => 1500,
                'saved_at' => ($saved ?? now())->toDateTimeString(), 'expires_at' => ($expires ?? now()->addDays(60))->toDateTimeString()];
        }
        IntegrationConnection::withoutGlobalScopes()->create(['account_id' => (int) config('owner.ceilings_account_id'), 'provider' => 'instagram',
            'status' => 'active', 'settings' => ['accounts' => $accounts]]);
    }
}
