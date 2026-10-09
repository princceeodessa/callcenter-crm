<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Owner\Marketing\DirectSource;
use App\Services\Owner\Marketing\Http;
use App\Services\Owner\Marketing\LeadsSheetSource;
use App\Services\Owner\Marketing\MarketingCollector;
use App\Services\Owner\Marketing\VkAdsSource;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OwnerMarketingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-09 12:00:00');
        Cache::forget('owner_marketing.vk_ads_token');
        Cache::forget('owner_marketing.avito_token');
        config(['owner.marketing' => [
            'vk_ads' => ['client_id' => 'vk-id', 'client_secret' => 'vk-secret', 'agency_client_name' => null],
            'avito' => ['client_id' => 'av-id', 'client_secret' => 'av-secret'],
            'direct' => ['token' => 'di-token', 'client_login' => null, 'sandbox' => false],
            'leads_sheet' => ['id' => 'SHEET'],
        ]]);
    }

    protected function tearDown(): void
    {
        Http::$fake = null;
        Cache::forget('owner_marketing.vk_ads_token');
        Cache::forget('owner_marketing.avito_token');
        Carbon::setTestNow();
        parent::tearDown();
    }

    private const SHEET_CSV = "\"Дата\",\"Тип\",\"Сумма\",\"Директ\",\"Директ затраты\",\"Директ цена лида\",\"Авито\",\"Авито затраты\",\"ВК\",\"ВК затраты\",\"Офис\"\n"
        ."\"08.10.26\",\"Замер\",\"9\",\"4\",\"4 000\",\"1000\",\"3\",\"1 500,5\",\"1\",\"700\",\"1\"\n"
        ."\"09.10.26\",\"Замер\",\"5\",\"2\",\"2000\",\"1000\",\"2\",\"900\",\"0\",\"0\",\"1\"\n"
        ."\"\",\"Итого\",\"14\",\"\",\"\",\"\",\"\",\"\",\"\",\"\",\"\"\n";

    private function fakeAll(bool $avitoFails = false): void
    {
        Http::$fake = function (string $method, string $url, array $headers, ?string $body) use ($avitoFails) {
            if (str_contains($url, 'ads.vk.com/api/v2/oauth2/token.json')) {
                return [200, json_encode(['access_token' => 'vk-token', 'refresh_token' => 'vk-refresh', 'expires_in' => 86400])];
            }
            if (str_contains($url, 'ads.vk.com/api/v2/ad_plans.json')) {
                return [200, json_encode(['count' => 2, 'items' => [['id' => 1, 'status' => 'active'], ['id' => 2, 'status' => 'deleted']]])];
            }
            if (str_contains($url, 'ads.vk.com/api/v2/statistics/ad_plans/day.json')) {
                $this->assertStringContainsString('id=1&', $url);   // удалённая кампания не запрашивается
                return [200, json_encode(['items' => [['id' => 1, 'rows' => [
                    ['date' => '2026-10-08', 'base' => ['spent' => '1200.50', 'shows' => 5000, 'clicks' => 40, 'vk' => ['result' => 3]], 'video' => ['viewed_3_seconds' => 900], 'social_network' => ['result_join' => 7]],
                    ['date' => '2026-10-09', 'base' => ['spent' => '300', 'shows' => 1000, 'clicks' => 10, 'goals' => 1], 'video' => [], 'social_network' => []],
                ]]]])];
            }
            if (str_contains($url, 'api.avito.ru')) {
                if ($avitoFails) {
                    return [403, '{"error":"forbidden"}'];
                }
                if (str_ends_with($url, '/token')) {
                    return [200, json_encode(['access_token' => 'av-token', 'expires_in' => 86400])];
                }
                if (str_contains($url, '/core/v1/accounts/self')) {
                    return [200, json_encode(['id' => 777, 'name' => 'БлагоДар'])];
                }
                if (str_contains($url, '/core/v1/items')) {
                    return [200, json_encode(['resources' => [['id' => 11], ['id' => 12]]])];
                }
                if (str_contains($url, '/stats/v1/accounts/777/items')) {
                    return [200, json_encode(['result' => ['items' => [
                        ['itemId' => 11, 'stats' => [['date' => '2026-10-08', 'uniqViews' => 100, 'uniqContacts' => 5, 'uniqFavorites' => 2]]],
                        ['itemId' => 12, 'stats' => [['date' => '2026-10-08', 'uniqViews' => 50, 'uniqContacts' => 1, 'uniqFavorites' => 0]]],
                    ]]])];
                }
            }
            if (str_contains($url, 'api.direct.yandex.com/json/v5/reports')) {
                $this->assertSame('Bearer di-token', $headers['Authorization']);
                return [200, "Date\tImpressions\tClicks\tCost\n2026-10-08\t3000\t60\t4100.40\n2026-10-09\t1000\t20\t1500\n", 'text/tab-separated-values'];
            }
            if (str_contains($url, 'docs.google.com/spreadsheets/d/SHEET/gviz/tq')) {
                return str_contains(urldecode($url), 'ИЖ Октябрь 2026')
                    ? [200, self::SHEET_CSV, 'text/csv; charset=utf-8']
                    : [404, 'not found', 'text/html'];
            }

            return [404, 'unexpected '.$url];
        };
    }

    public function test_parsers(): void
    {
        $sheet = LeadsSheetSource::parse(array_map('str_getcsv', explode("\n", trim(self::SHEET_CSV))));
        $this->assertSame(['2026-10-08', '2026-10-09'], array_keys($sheet));
        $this->assertSame(9.0, $sheet['2026-10-08']['total']);
        $this->assertSame(['Директ' => 4.0, 'Авито' => 3.0, 'ВК' => 1.0, 'Офис' => 1.0], $sheet['2026-10-08']['sources']);
        $this->assertSame(['Директ' => 4000.0, 'Авито' => 1500.5, 'ВК' => 700.0], $sheet['2026-10-08']['spend']);

        $direct = DirectSource::parseTsv("Date\tImpressions\tClicks\tCost\n2026-10-08\t3000\t60\t4100.40\n");
        $this->assertSame(['2026-10-08' => ['impr' => 3000, 'clicks' => 60, 'cost' => 4100.4]], $direct);

        $vk = VkAdsSource::parse(['base' => ['spent' => '10.5', 'shows' => 3, 'clicks' => 1, 'vk' => ['result' => 2]], 'video' => ['viewed_3_seconds' => 4], 'social_network' => ['result_join' => 1]]);
        $this->assertSame(['spent' => 10.5, 'shows' => 3, 'clicks' => 1, 'views3' => 4, 'joins' => 1, 'goals' => 2], $vk);
    }

    public function test_collector_stores_days_and_keeps_going_when_one_source_fails(): void
    {
        $this->fakeAll(avitoFails: true);
        $result = (new MarketingCollector())->run(14);

        $this->assertStringStartsWith('ok', $result['direct']);
        $this->assertStringStartsWith('ok', $result['vk']);
        $this->assertStringStartsWith('ok', $result['sheet']);
        $this->assertStringStartsWith('ошибка', $result['avito']);

        $vk = json_decode(DB::table('owner_marketing_daily')->where('source', 'vk')->where('day', '2026-10-08')->value('metrics'), true);
        $this->assertEquals(['spent' => 1200.5, 'shows' => 5000, 'clicks' => 40, 'views3' => 900, 'joins' => 7, 'goals' => 3], $vk);
        $this->assertSame(2, DB::table('owner_marketing_daily')->where('source', 'direct')->count());
        $this->assertSame(2, DB::table('owner_marketing_daily')->where('source', 'sheet')->count());
        $this->assertNotNull(DB::table('owner_marketing_sources')->where('source', 'avito')->value('last_error'));
        $this->assertNull(DB::table('owner_marketing_sources')->where('source', 'direct')->value('last_error'));

        // повторный удачный прогон Авито снимает ошибку; токен VK берётся из кэша, а не заново
        $tokenCalls = 0;
        $this->fakeAll();
        $inner = Http::$fake;
        Http::$fake = function (...$args) use ($inner, &$tokenCalls) {
            if (str_contains($args[1], 'oauth2/token.json')) {
                $tokenCalls++;
            }

            return $inner(...$args);
        };
        (new MarketingCollector())->run(14);
        $this->assertSame(0, $tokenCalls);
        $this->assertNull(DB::table('owner_marketing_sources')->where('source', 'avito')->value('last_error'));
        $av = json_decode(DB::table('owner_marketing_daily')->where('source', 'avito')->where('day', '2026-10-08')->value('metrics'), true);
        $this->assertEquals(['views' => 150, 'contacts' => 6, 'favorites' => 2], $av);
    }

    public function test_owner_page_shows_spend_and_cost_per_lead(): void
    {
        $this->fakeAll();
        (new MarketingCollector())->run(14);

        $acc = Account::create(['name' => 'Потолки (тест рекламы)'])->id;
        config(['owner.ceilings_account_id' => $acc]);
        $sneakers = User::where('role', 'sneaker_head')->firstOrFail()->account_id;
        $owner = User::create(['account_id' => $sneakers, 'name' => 'Владелец (тест)', 'email' => 'owner_ads_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true]);
        $owner->forceFill(['all_businesses' => true])->save();

        $res = $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-08', 'to' => '2026-10-09']));
        $res->assertOk();
        $ads = $res->viewData('ads');
        $this->assertEqualsWithDelta(5600.4, $ads['spend']['direct']['value'], 0.001);   // API Директа
        $this->assertSame('API Директа', $ads['spend']['direct']['from']);
        $this->assertEqualsWithDelta(1500.5, $ads['spend']['vk']['value'], 0.001);       // API VK
        $this->assertEqualsWithDelta(2400.5, $ads['spend']['avito']['value'], 0.001);    // таблица заявок
        $this->assertEqualsWithDelta(9501.4, $ads['spend_total'], 0.001);
        $this->assertSame(14.0, $ads['sheet']['total']);
        $m = $res->viewData('measures');
        $this->assertTrue($m['available']);
        $this->assertSame(14, $m['total']);                       // замеры — по таблице: «Сумма» за 08 и 09.10
        $this->assertEquals(['direct' => 6, 'avito' => 5, 'vk' => 1, 'other' => 2], $m['by_group']);
        $this->assertSame(['Офис' => 2], $m['by_source']['other']);
        $res->assertSee('по таблице замеров');
        $res->assertDontSee('заяв');
        $res->assertSee('Расход на рекламу');
        $res->assertSee('Реклама по площадкам');
    }
}
