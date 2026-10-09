<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Owner\Marketing\AvitoSource;
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
        AvitoSource::$paceSeconds = 0;
        VkAdsSource::$tokenFile = sys_get_temp_dir().'/owner-test-vk-token-'.getmypid().'.json';
        @unlink(VkAdsSource::$tokenFile);
        // в транзакции теста: чужие строки сбора (например, ручной прогон на этой базе) не мешают подсчётам
        DB::table('owner_marketing_daily')->delete();
        DB::table('owner_marketing_sources')->delete();
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
        AvitoSource::$paceSeconds = 61;
        @unlink((string) VkAdsSource::$tokenFile);
        VkAdsSource::$tokenFile = null;
        Cache::forget('owner_marketing.avito_token');
        Carbon::setTestNow();
        parent::tearDown();
    }

    private const SHEET_CSV = "\"Дата\",\"Тип\",\"Сумма\",\"Директ\",\"Директ затраты\",\"Директ цена лида\",\"Авито\",\"Авито затраты\",\"ВК\",\"ВК затраты\",\"Офис\",\"Радио\"\n"
        ."\"08.10.26\",\"Замер\",\"9\",\"4\",\"4 000\",\"1000\",\"3\",\"1 500,5\",\"1\",\"700\",\"1\",\"0\"\n"
        ."\"09.10.26\",\"Замер\",\"5\",\"2\",\"2000\",\"1000\",\"2\",\"900\",\"0\",\"0\",\"1\",\"0\"\n"
        ."\"\",\"Итого\",\"14\",\"\",\"\",\"\",\"\",\"\",\"\",\"\",\"\",\"\"\n";

    private function fakeAll(bool $avitoFails = false): void
    {
        Http::$fake = function (string $method, string $url, array $headers, ?string $body) use ($avitoFails) {
            if (str_contains($url, 'ads.vk.com/api/v2/oauth2/token.json')) {
                return [200, json_encode(['access_token' => 'vk-token', 'refresh_token' => 'vk-refresh', 'expires_in' => 86400])];
            }
            if (str_contains($url, 'ads.vk.com/api/v2/ad_plans.json')) {
                return [200, json_encode(['count' => 3, 'items' => [
                    ['id' => 1, 'name' => 'Клипы БлагоДар', 'status' => 'active', 'objective' => 'branding_socialengagement'],
                    ['id' => 2, 'name' => 'Старая', 'status' => 'deleted', 'objective' => 'leadads'],
                    ['id' => 3, 'name' => 'Потолки 2+3 (лид-форма)', 'status' => 'blocked', 'objective' => 'leadads'],
                ]])];
            }
            if (str_contains($url, 'ads.vk.com/api/v2/statistics/ad_plans/day.json')) {
                $this->assertStringContainsString('id=1%2C3&', $url);   // удалённая кампания не запрашивается
                return [200, json_encode(['items' => [
                    // охватная кампания: base.vk.result — это показы, не заявки
                    ['id' => 1, 'rows' => [
                        ['date' => '2026-10-08', 'base' => ['spent' => '1200.50', 'shows' => 5000, 'clicks' => 40, 'vk' => ['result' => 5000]], 'video' => ['viewed_3_seconds' => 900, 'viewed_100_percent' => 300], 'social_network' => ['result_join' => 7]],
                        ['date' => '2026-10-09', 'base' => ['spent' => '300', 'shows' => 1000, 'clicks' => 10, 'goals' => 1], 'video' => [], 'social_network' => []],
                    ]],
                    ['id' => 3, 'rows' => [
                        ['date' => '2026-10-08', 'base' => ['spent' => '100', 'shows' => 200, 'clicks' => 5, 'vk' => ['result' => 2]], 'video' => [], 'social_network' => []],
                    ]],
                ]])];
            }
            if (str_contains($url, 'ads.vk.com/api/v2/ad_groups.json')) {
                return [200, json_encode(['count' => 5, 'items' => [
                    ['id' => 10, 'name' => 'Клип 111', 'status' => 'active', 'ad_plan_id' => 1],
                    ['id' => 11, 'name' => 'Клип 222', 'status' => 'blocked', 'ad_plan_id' => 1],
                    ['id' => 12, 'name' => 'Удалённая', 'status' => 'deleted', 'ad_plan_id' => 1],
                    ['id' => 30, 'name' => 'Потолки 2+3 — Ижевск', 'status' => 'active', 'ad_plan_id' => 3],
                    ['id' => 99, 'name' => 'Из удалённой кампании', 'status' => 'active', 'ad_plan_id' => 2],
                ]])];
            }
            if (str_contains($url, 'ads.vk.com/api/v2/banners.json')) {
                return [200, json_encode(['count' => 2, 'items' => [
                    ['id' => 1, 'ad_group_id' => 10, 'urls' => ['vk_clip' => ['url' => 'https://vk.com/clip-1_111']]],
                    ['id' => 3, 'ad_group_id' => 30, 'urls' => ['primary' => ['url' => 'leadads://1/']]],
                ]])];
            }
            if (str_contains($url, 'ads.vk.com/api/v2/statistics/ad_groups/day.json')) {
                $this->assertStringContainsString('id=10%2C11%2C30&', $url);   // без удалённых групп и групп удалённых кампаний
                return [200, json_encode(['items' => [
                    ['id' => 10, 'rows' => [
                        ['date' => '2026-10-08', 'base' => ['spent' => '1000', 'shows' => 4000, 'clicks' => 30, 'vk' => ['result' => 4000]], 'video' => ['viewed_3_seconds' => 800, 'viewed_100_percent' => 250], 'social_network' => ['result_join' => 6]],
                        ['date' => '2026-10-09', 'base' => ['spent' => '300', 'shows' => 1000, 'clicks' => 10], 'video' => [], 'social_network' => []],
                    ]],
                    ['id' => 11, 'rows' => [
                        ['date' => '2026-10-08', 'base' => ['spent' => '200.5', 'shows' => 1000, 'clicks' => 10], 'video' => ['viewed_3_seconds' => 100, 'viewed_100_percent' => 50], 'social_network' => ['result_join' => 1]],
                        ['date' => '2026-10-09', 'base' => ['spent' => '0', 'shows' => 0], 'video' => [], 'social_network' => []],
                    ]],
                    ['id' => 30, 'rows' => [
                        ['date' => '2026-10-08', 'base' => ['spent' => '100', 'shows' => 200, 'clicks' => 5, 'vk' => ['result' => 2]], 'video' => [], 'social_network' => []],
                    ]],
                ]])];
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
                    // потолки и кондиционеры — активные, ремонт и вакансия — в архиве (у архивных тоже бывают расходы)
                    $svc = ['id' => 114, 'name' => 'Предложение услуг'];
                    return [200, json_encode(['resources' => match (true) {
                        str_contains($url, 'status=active') => [
                            ['id' => 11, 'title' => 'Натяжные потолки. Замер в день обращения', 'category' => $svc],
                            ['id' => 12, 'title' => 'Установка кондиционера под ключ', 'category' => $svc],
                        ],
                        str_contains($url, 'status=old') => [
                            ['id' => 13, 'title' => 'Ремонт под ключ', 'category' => $svc],
                            ['id' => 14, 'title' => 'Монтажник натяжных потолков', 'category' => ['id' => 111, 'name' => 'Вакансии']],
                        ],
                        default => [],
                    }])];
                }
                if (str_contains($url, '/stats/v1/accounts/777/items')) {
                    $this->assertSame([11, 12, 13, 14], json_decode((string) $body, true)['itemIds']);
                    return [200, json_encode(['result' => ['items' => [
                        ['itemId' => 11, 'stats' => [['date' => '2026-10-08', 'uniqViews' => 100, 'uniqContacts' => 5, 'uniqFavorites' => 2]]],
                        ['itemId' => 12, 'stats' => [['date' => '2026-10-08', 'uniqViews' => 50, 'uniqContacts' => 1, 'uniqFavorites' => 0]]],
                    ]]])];
                }
                if (str_contains($url, '/stats/v2/accounts/777/spendings')) {
                    $req = json_decode((string) $body, true);
                    $this->assertSame(['all'], $req['spendingTypes']);
                    $this->assertSame('day', $req['grouping']);
                    $day = fn (string $d, array $s) => ['date' => $d, 'type' => 'day', 'spendings' => $s];
                    $presence = fn (float $v) => ['slug' => 'presence', 'value' => $v, 'services' => [['slug' => 'cpa_click_package', 'value' => $v]]];
                    $groupings = match ($req['filter']['itemIDs'] ?? null) {
                        null => [   // весь кабинет: объявления + тариф
                            $day('2026-10-08', [$presence(1600.4), ['slug' => 'rest', 'value' => 200, 'services' => [['slug' => 'tariff_ext', 'value' => 200]]]]),
                            $day('2026-10-09', [$presence(500)]),
                        ],
                        [11] => [$day('2026-10-08', [$presence(1500.4)]), $day('2026-10-09', [$presence(450)])],
                        [12] => [$day('2026-10-08', [$presence(60)]), $day('2026-10-09', [$presence(50)])],
                        [13] => [$day('2026-10-08', [$presence(40)])],
                    };
                    return [200, json_encode(['result' => ['groupings' => $groupings]])];
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
        $this->assertSame(['Директ' => 4.0, 'Авито' => 3.0, 'ВК' => 1.0, 'Офис' => 1.0, 'Радио' => 0.0], $sheet['2026-10-08']['sources']);
        $this->assertSame(['Директ' => 4000.0, 'Авито' => 1500.5, 'ВК' => 700.0], $sheet['2026-10-08']['spend']);

        $direct = DirectSource::parseTsv("Date\tImpressions\tClicks\tCost\n2026-10-08\t3000\t60\t4100.40\n");
        $this->assertSame(['2026-10-08' => ['impr' => 3000, 'clicks' => 60, 'cost' => 4100.4]], $direct);

        $vk = VkAdsSource::parse(['base' => ['spent' => '10.5', 'shows' => 3, 'clicks' => 1, 'vk' => ['result' => 2]], 'video' => ['viewed_3_seconds' => 4], 'social_network' => ['result_join' => 1]]);
        $this->assertSame(['spent' => 10.5, 'shows' => 3, 'clicks' => 1, 'views3' => 4, 'views100' => 0, 'joins' => 1, 'goals' => 2], $vk);
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
        $groups = $vk['groups'];
        unset($vk['groups']);
        // лиды — только у кампании на лид-формы; у охватной base.vk.result — показы
        $this->assertEquals(['spent' => 1300.5, 'shows' => 5200, 'clicks' => 45, 'views3' => 900, 'views100' => 300, 'joins' => 7, 'goals' => 2], $vk);
        $this->assertEquals(['spent' => 1000, 'shows' => 4000, 'clicks' => 30, 'views3' => 800, 'views100' => 250, 'joins' => 6, 'goals' => 0], $groups[10]);
        $this->assertSame(2, $groups[30]['goals']);
        $vk9 = json_decode(DB::table('owner_marketing_daily')->where('source', 'vk')->where('day', '2026-10-09')->value('metrics'), true);
        $this->assertSame([10], array_keys($vk9['groups']));     // группа без показов и расхода в день не пишется
        $meta = json_decode(DB::table('owner_marketing_sources')->where('source', 'vk')->value('meta'), true);
        $this->assertSame('https://vk.com/clip-1_111', $meta['groups'][10]['clip']);
        $this->assertTrue($meta['groups'][30]['leadads']);
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

        // чистка кэша Laravel при выкладке токен VK не стирает — новый из лимита в 5 токенов не берётся
        Cache::flush();
        (new MarketingCollector())->run(14, ['vk']);
        $this->assertSame(0, $tokenCalls);
        $this->assertNull(DB::table('owner_marketing_sources')->where('source', 'vk')->value('last_error'));
        $this->assertNull(DB::table('owner_marketing_sources')->where('source', 'avito')->value('last_error'));
        $av = json_decode(DB::table('owner_marketing_daily')->where('source', 'avito')->where('day', '2026-10-08')->value('metrics'), true);
        $this->assertEquals([
            'views' => 150, 'contacts' => 6, 'favorites' => 2,
            'ceilings_views' => 100, 'ceilings_contacts' => 5, 'ceilings_favorites' => 2,
            'cond_views' => 50, 'cond_contacts' => 1, 'cond_favorites' => 0,
            'spend' => 1800.4, 'spend_presence' => 1600.4, 'spend_promotion' => 0, 'spend_other' => 200,
            'ceilings_spend' => 1500.4, 'cond_spend' => 60, 'repair_spend' => 40, 'shared_spend' => 200,
        ], $av);
        // день, где были только списания, тоже есть
        $av9 = json_decode(DB::table('owner_marketing_daily')->where('source', 'avito')->where('day', '2026-10-09')->value('metrics'), true);
        $this->assertEquals(500, $av9['spend']);
        $this->assertEquals(450, $av9['ceilings_spend']);
        $this->assertEquals(0, $av9['shared_spend']);
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
        $this->assertEqualsWithDelta(1600.5, $ads['spend']['vk']['value'], 0.001);       // API VK
        $this->assertEqualsWithDelta(1950.4, $ads['spend']['avito']['value'], 0.001);    // API Авито: только объявления потолков
        $this->assertStringStartsWith('API Авито', $ads['spend']['avito']['from']);
        $this->assertEqualsWithDelta(9151.3, $ads['spend_total'], 0.001);
        $this->assertEqualsWithDelta(110.0, $ads['avito_dirs']['cond']['spend'], 0.001);
        $this->assertEqualsWithDelta(40.0, $ads['avito_dirs']['repair']['spend'], 0.001);
        $this->assertEqualsWithDelta(200.0, $ads['avito_shared'], 0.001);
        // группы VK за период: сначала работающие, по расходу
        $this->assertSame([10, 11, 30], array_column($ads['vk_groups'], 'id'));   // 30 — в остановленной кампании
        $this->assertEqualsWithDelta(1300.0, $ads['vk_groups'][0]['spent'], 0.001);
        $this->assertSame(14.0, $ads['sheet']['total']);
        $m = $res->viewData('measures');
        $this->assertTrue($m['available']);
        $this->assertSame(14, $m['total']);                       // замеры — по таблице: «Сумма» за 08 и 09.10
        $this->assertEquals(['direct' => 6, 'avito' => 5, 'vk' => 1, 'other' => 2], $m['by_group']);
        $this->assertSame(['Офис' => 2], $m['by_source']['other']);
        $res->assertSee('по таблице замеров');
        $res->assertDontSee('заяв');
        $res->assertDontSee('онверси');                         // конверсию не показываем
        $html = $res->getContent();
        $this->assertSame(4, substr_count($html, 'class="src '));   // карточки: Директ, Авито, Офис, ВК — без «Радио» (0 замеров)
        $this->assertStringNotContainsString('<div class="name">Радио</div>', $html);
        $this->assertStringContainsString('<div class="name">Офис</div>', $html);
        $res->assertSee('Расход на рекламу');
        $res->assertSee('Реклама по площадкам');
        $res->assertSee('весь кабинет 2 300 ₽, кроме потолков: кондиционеры 110 ₽ · ремонт и шумоизоляция 40 ₽ · тариф и прочее 200 ₽');
        $res->assertSee('Авито · потолки');
        $res->assertSee('расход на потолки');
        $res->assertSee('весь кабинет Авито <b>2 300 ₽</b>: потолки 1 950 ₽ · кондиционеры 110 ₽ · ремонт и шумоизоляция 40 ₽ · тариф и прочее 200 ₽', false);
        $res->assertSee('страница обновляется сама каждые 30 с');
        $res->assertSee('setInterval(refresh, 30000)', false);
        $res->assertDontSee('Продажи за день');                 // это продажи кроссовок — на сводке потолков их нет
        $res->assertSee('VK Реклама — группы объявлений');
        $res->assertSee('<a href="https://vk.com/clip-1_111" target="_blank" rel="noopener">Клип 111</a>', false);
        $res->assertSee('Лиды с формы');
        $res->assertSee('1,63 ₽');                              // просмотр клипа 1300 ₽ / 800 — с копейками, не «2 ₽»
        $res->assertDontSee('Новые лиды');                      // плитки лидов на сводке нет (решение владельца 09.10)
        $res->assertSee('По дням');                             // два дня — график есть

        // один день: график не рисуется — один столбик растягивался на всю ширину
        $one = $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-08', 'to' => '2026-10-08']));
        $one->assertOk()->assertDontSee('По дням');
    }

    public function test_avito_spend_falls_back_to_the_sheet_when_api_is_not_collected(): void
    {
        $this->fakeAll(avitoFails: true);
        (new MarketingCollector())->run(14);
        $ads = \App\Services\Owner\Marketing\MarketingStats::forPeriod(Carbon::parse('2026-10-08'), Carbon::parse('2026-10-10'));
        $this->assertEqualsWithDelta(2400.5, $ads['spend']['avito']['value'], 0.001);
        $this->assertSame('таблица замеров', $ads['spend']['avito']['from']);
    }

    public function test_avito_direction_by_title(): void
    {
        $this->assertSame('ceilings', AvitoSource::direction('Натяжные потолки. 2-й и 3-й потолок в подарок'));
        $this->assertSame('ceilings', AvitoSource::direction('Потолки натяжные. Быстрый монтаж'));
        $this->assertSame('cond', AvitoSource::direction('Обслуживание кондиционеров и сплит систем'));
        $this->assertSame('repair', AvitoSource::direction('Надёжный ремонт под ключ — договор и гарантия'));
        $this->assertSame('repair', AvitoSource::direction('Шумоизоляция и звукоизоляция любых помещений'));
        $this->assertSame('repair', AvitoSource::direction('Тихие стены - Ваш надежный уют'));
        $this->assertSame('other', AvitoSource::direction('Монтажник натяжных потолков', 111));   // вакансия
        $this->assertSame('other', AvitoSource::direction('Установка пвх окон под ключ'));
    }

    public function test_the_same_collect_error_is_logged_once(): void
    {
        \Illuminate\Support\Facades\Log::spy();
        Http::$fake = fn () => [500, 'down', 'text/html'];
        (new MarketingCollector())->run(7, ['sheet']);
        (new MarketingCollector())->run(7, ['sheet']);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->once();
    }
}
