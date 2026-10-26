<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Owner\ContractProfit;
use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «Прибыль договоров (потолки)» на сводке владельца — готовые итоги CRM БлагоДар: плитки, маржа словом, куда уходят
 * деньги, замерщики, месяцы — когда период шире месяца; без итогов — слова, а не нули; ответ в кэше 10 минут.
 */
class OwnerContractProfitTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'owner.contract_profit.2026-10-01.2026-10-16';

    protected function setUp(): void
    {
        parent::setUp();
        $acc = Account::create(['name' => 'Потолки (тест прибыли)'])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pipeline_stages')->insert(['account_id' => $acc, 'pipeline_id' => $pipeline, 'name' => 'Замер назначен', 'sort' => 40, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        config(['owner.ceilings_account_id' => $acc, 'owner.nonclosures' => ['url' => null, 'token' => null]]);
        Cache::forget(self::KEY);
    }

    protected function tearDown(): void
    {
        Http::$fake = null;
        Cache::forget(self::KEY);
        parent::tearDown();
    }

    private function owner(): User
    {
        $u = User::create([
            'account_id' => User::where('role', 'sneaker_head')->firstOrFail()->account_id, 'name' => 'Владелец (тест)', 'email' => 'owner_profit_'.uniqid(),
            'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true,
        ]);
        $u->forceFill(['all_businesses' => true])->save();

        return $u;
    }

    /** Блоки CRM БлагоДар — фрагмент ?part=blagodar (страница его догружает, если в кэше нет). */
    private function page(User $u)
    {
        return $this->actingAs($u)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-16', 'part' => 'profit']))->assertOk();
    }

    private function answer(array $over = []): string
    {
        return json_encode(array_replace_recursive([
            'updated' => ['onec' => '2026-10-16T04:45:25.586Z'],
            'stale' => [],
            'period' => ['from' => '2026-10-01', 'to' => '2026-10-16'],
            'totals' => [
                'contracts' => 205, 'incomplete' => 39, 'revenue' => '8406366.00', 'costs' => '4860200.00', 'profit' => '3546166.00', 'margin_pct' => 42.2,
                'costs_by_kind' => ['installers' => '2091065.45', 'materials' => '345248.75', 'canvas' => '454193.10', 'goods' => '252233.76',
                    'departments' => '1361831.55', 'vat' => '139759.92', 'usn' => '199725.14', 'acquiring' => '16142.33'],
                'departments' => [['key' => 'DOCUMENTS', 'title' => 'Отдел документов', 'sum' => '214362.45']],
            ],
            'by_month' => [['month' => '2026-10', 'title' => 'октябрь 2026', 'contracts' => 205, 'incomplete' => 39, 'revenue' => '8406366.00', 'profit' => '3546166.00', 'margin_pct' => 42.2]],
            'by_measurer' => [
                ['measurer' => 'Мокрушин Сергей', 'contracts' => 29, 'incomplete' => 3, 'revenue' => '1644974.00', 'profit' => '682245.72', 'margin_pct' => 41.5],
                ['measurer' => 'Петров Пётр', 'contracts' => 2, 'incomplete' => 0, 'revenue' => '100000.00', 'profit' => '-5000.00', 'margin_pct' => -5.0],
            ],
            'url' => 'https://blagodar.test/reports/contract',
        ], $over));
    }

    public function test_block_from_blagodar_totals(): void
    {
        config(['owner.contract_profit' => ['url' => 'https://blagodar.test/', 'token' => 'secret-token-xyz']]);
        $calls = 0;
        Http::$fake = function (string $method, string $url, array $headers) use (&$calls) {
            $calls++;
            $this->assertSame('https://blagodar.test/api/reports/contract-profit/summary?from=2026-10-01&to=2026-10-16', $url);
            $this->assertSame('Bearer secret-token-xyz', $headers['Authorization']);

            return [200, $this->answer()];
        };
        $owner = $this->owner();
        $res = $this->page($owner);
        $html = $res->getContent();
        $res->assertSee('Прибыль договоров')->assertSee('Договоров')->assertSee('205')->assertSee('Получим')->assertSee('8 406 366 ₽')
            ->assertSee('Потратим')->assertSee('4 860 200 ₽')->assertSee('Заработаем')->assertSee('3 546 166 ₽')
            ->assertSee('маржа 42,2% · хорошо')
            ->assertSee('у 39 договоров в 1С не хватает цены или ставки — на деле прибыль меньше')
            ->assertSee('Куда уходят деньги')->assertSee('монтажники')->assertSee('2 091 065 ₽')->assertSee('Отдел документов 214 362 ₽')
            ->assertSee('Мокрушин Сергей')->assertSee('41,5% · хорошо')->assertSee('-5,0% · убыток')->assertSee('Без цены в 1С')
            ->assertSee('Подробно в CRM БлагоДар')->assertSee('href="https://blagodar.test/reports/contract"', false);
        // на самой странице (ответ уже в кэше) — блок сразу и пункт меню
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-16']))
            ->assertOk()->assertSee('Заработаем')->assertSee('href="#profit"', false);
        $res->assertDontSee('По месяцам');                    // период в пределах месяца
        $this->assertStringNotContainsString('secret-token-xyz', $html);

        // 10 минут ответ — из памяти
        $this->page($owner);
        $this->assertSame(1, $calls);

        // период шире месяца — таблица по месяцам
        Cache::forget(self::KEY);
        Http::$fake = fn () => [200, $this->answer(['by_month' => [1 => ['month' => '2026-09', 'title' => 'сентябрь 2026', 'contracts' => 300, 'incomplete' => 0, 'revenue' => '1.00', 'profit' => '1.00', 'margin_pct' => 12.0]],
            'stale' => ['1С не обновлялась больше суток']])];
        $this->page($owner)->assertSee('По месяцам')->assertSee('Сентябрь 2026')->assertSee('12,0% · мало')
            ->assertSee('Данные могут быть устаревшими: 1С не обновлялась больше суток');
    }

    public function test_net_after_overhead(): void
    {
        // 01–16.10 при «сегодня» 26.10: кассиры 16 × 4 700, месячные — 16/31, рекламы в CRM за период нет
        Carbon::setTestNow('2026-10-26 12:00:00');
        DB::table('owner_marketing_daily')->where('day', '>=', '2026-10-01')->where('day', '<', '2026-10-17')->delete();
        config(['owner.contract_profit' => ['url' => 'https://blagodar.test', 'token' => 't'], 'owner.overhead' => ['cashiers_per_day' => 4700, 'monthly' => [
            'marketing' => ['Маркетинг', 300000], 'radio' => ['Радио', 68000], 'newspapers' => ['Газеты', 20000], 'dgis' => ['2ГИС', 10000],
        ]]]);
        Http::$fake = fn () => [200, $this->answer()];
        try {
            $this->page($this->owner())->assertSee('Чистыми останется')->assertSee('3 265 547 ₽')
                ->assertSee('общие расходы 280 619 ₽')->assertSee('за 16 дней')
                ->assertSee('Кассиры')->assertSee('4 700 ₽ × 16 дней')->assertSee('75 200 ₽')
                ->assertSee('Маркетинг')->assertSee('154 839 ₽')->assertSee('Радио')->assertSee('35 097 ₽')->assertSee('Газеты')->assertSee('10 323 ₽')
                ->assertSee('2ГИС')->assertSee('5 161 ₽')
                ->assertSee('Реклама')->assertSee('Директ, VK, Авито — из CRM');
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_no_totals_means_words_not_zeros(): void
    {
        $owner = $this->owner();
        config(['owner.contract_profit' => ['url' => null, 'token' => null]]);
        $this->page($owner)->assertSee('Итоги по прибыли договоров строятся в CRM БлагоДар')->assertDontSee('Заработаем');

        config(['owner.contract_profit' => ['url' => 'https://blagodar.test', 'token' => 't']]);
        foreach ([401 => 'нужен новый ключ', 403 => 'их выдача сейчас выключена', 500 => 'сейчас они не пришли'] as $status => $text) {
            Cache::forget(self::KEY);
            Http::$fake = fn () => [$status, '{"message":"no"}'];
            $this->page($owner)->assertSee('Итоги по прибыли договоров строятся в CRM БлагоДар')->assertSee($text)->assertDontSee('Заработаем');
        }
        Cache::forget(self::KEY);
        Http::$fake = fn () => throw new \RuntimeException('Сеть: timeout');
        $this->page($owner)->assertSee('сейчас она не ответила')->assertDontSee('Заработаем');
    }

    public function test_page_does_not_wait_for_blagodar(): void
    {
        config(['owner.contract_profit' => ['url' => 'https://blagodar.test', 'token' => 't']]);
        $calls = 0;
        Http::$fake = function () use (&$calls) {
            $calls++;

            return [200, $this->answer()];
        };
        $owner = $this->owner();
        $url = route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-16']);
        // в кэше нет — страница без запроса в БлагоДар, с заглушкой, которая догрузит блоки
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('class="bd-lazy"', false)->assertSee('part=profit', false)->assertDontSee('part=nonclosures', false)   // незаключённые не настроены — их слова сразу, без догрузки
            ->assertSee('Загружаю итоги из CRM БлагоДар')->assertDontSee('Заработаем');
        $this->assertSame(0, $calls);
        // фрагмент сходил в БлагоДар; дальше страница рисует блоки сразу, из кэша
        $this->page($owner)->assertSee('Заработаем')->assertDontSee('<html', false);
        $this->actingAs($owner)->get($url)->assertOk()->assertSee('Заработаем')->assertDontSee('class="bd-lazy"', false);
        $this->assertSame(1, $calls);
    }

    public function test_measurers_sorted_by_contracts(): void
    {
        config(['owner.contract_profit' => ['url' => 'https://blagodar.test', 'token' => 't']]);
        Http::$fake = fn () => [200, $this->answer(['by_measurer' => [0 => ['measurer' => 'Мало договоров', 'contracts' => 3], 1 => ['measurer' => 'Много договоров', 'contracts' => 40]]])];
        $p = ContractProfit::forPeriod(\Carbon\Carbon::parse('2026-10-01'), \Carbon\Carbon::parse('2026-10-17'));
        $this->assertSame(['Много договоров', 'Мало договоров'], array_column($p['data']['by_measurer'], 'measurer'));
    }

    public function test_margin_in_words(): void
    {
        $w = fn ($m) => ContractProfit::verdict($m)[0];
        $this->assertSame(['хорошо', 'хорошо', 'средне', 'средне', 'мало', 'мало', 'убыток', ''], [$w(42.2), $w(30.0), $w(29.9), $w(15.0), $w(14.9), $w(0.0), $w(-0.1), $w(null)]);
    }

    /** Выгрузка строк без цены (scripts/blagodar/cp_uncosted.js): два договора октября и один сентября — вне периода. */
    private function uncosted(): void
    {
        $file = storage_path('framework/testing/cp_uncosted_'.uniqid().'.json');
        @mkdir(dirname($file), 0775, true);
        file_put_contents($file, json_encode(['generated_at' => now()->toIso8601String(), 'shares' => ['work' => 0.2, 'goods' => 0.4, 'since' => '2026-06-01'], 'contracts' => [
            // УСН 15% «доходы минус расходы»: расход +1 000 → налог −150
            ['date' => '2026-10-05', 'measurer' => 'Мокрушин Сергей', 'revenue' => '100000.00', 'income' => '100000.00', 'before_tax' => '50000.00', 'usn' => '7500.00',
                'usn_pct' => 15, 'min_pct' => 1, 'other_missing' => false, 'lines' => [
                    ['kind' => 'work', 'name' => 'Доставка', 'unit' => 'шт', 'qty' => 2, 'sum' => '1000.00'],
                    ['kind' => 'goods', 'name' => 'Карниз ПВХ', 'unit' => 'шт', 'qty' => 1, 'sum' => '2000.00'],
                ]],
            // без налога; не хватает ещё и ставки отдела — неполным остаётся
            ['date' => '2026-10-10', 'measurer' => null, 'revenue' => '50000.00', 'income' => '50000.00', 'before_tax' => '20000.00', 'usn' => '0.00',
                'usn_pct' => 0, 'min_pct' => 0, 'other_missing' => true, 'lines' => [['kind' => 'work', 'name' => 'Доставка', 'unit' => 'шт', 'qty' => 1, 'sum' => '500.00']]],
            ['date' => '2026-09-20', 'measurer' => 'Мокрушин Сергей', 'revenue' => '10000.00', 'income' => '10000.00', 'before_tax' => '1000.00', 'usn' => '1350.00',
                'usn_pct' => 15, 'min_pct' => 1, 'other_missing' => false, 'lines' => [['kind' => 'work', 'name' => 'Демонтаж сложный', 'unit' => 'м2', 'qty' => 10, 'sum' => '3000.00']]],
        ]], JSON_UNESCAPED_UNICODE));
        config(['owner.contract_profit' => ['url' => 'https://blagodar.test', 'token' => 't', 'uncosted_file' => $file]]);
        $this->beforeApplicationDestroyed(fn () => @unlink($file));
        Http::$fake = fn () => [200, $this->answer()];
    }

    public function test_uncosted_lines_filled_by_average_share_with_usn(): void
    {
        $this->uncosted();
        $p = \App\Services\Owner\ContractProfitFill::apply(ContractProfit::forPeriod(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-17')), Carbon::parse('2026-10-01'), Carbon::parse('2026-10-17'));
        $d = $p['data'];
        // 1-й: работы 1 000 × 20% + товар 2 000 × 40% = 1 000, УСН −150 → −850; 2-й: 500 × 20% = 100
        $this->assertEqualsWithDelta(3546166 - 950, $d['totals']['profit'], 0.001);
        $this->assertEqualsWithDelta(4860200 + 950, $d['totals']['costs'], 0.001);
        $this->assertSame(42.2, $d['totals']['margin']);
        $this->assertSame(38, $d['totals']['incomplete']);                 // досчитан целиком только первый
        $this->assertEquals(['contracts' => 2, 'closed' => 1, 'share_lines' => 3, 'rate_lines' => 0], array_intersect_key($d['fill'], array_flip(['contracts', 'closed', 'share_lines', 'rate_lines'])));
        $this->assertEqualsWithDelta(1100, $d['fill']['share_sum'], 0.001);
        $this->assertEqualsWithDelta(-150, $d['fill']['usn'], 0.001);
        $this->assertSame(3546166.0, $d['fill']['blagodar_profit']);
        $m = collect($d['by_measurer'])->firstWhere('measurer', 'Мокрушин Сергей');
        $this->assertEqualsWithDelta(682245.72 - 850, $m['profit'], 0.001);
        $this->assertSame(2, $m['incomplete']);
        $this->assertSame(38, $d['by_month'][0]['incomplete']);
        $kinds = array_column($d['kinds'], 'sum', 'key');
        $this->assertEqualsWithDelta(2091065.45 + 300, $kinds['installers'], 0.001);
        $this->assertEqualsWithDelta(252233.76 + 800, $kinds['goods'], 0.001);
        $this->assertEqualsWithDelta(199725.14 - 150, $kinds['usn'], 0.001);

        // минимальный налог: расход больше, а УСН не меньше 1% дохода
        $this->assertSame(0.0, \App\Services\Owner\ContractProfitFill::usnDelta(['income' => 10000, 'before_tax' => 9950, 'usn' => 100, 'usn_pct' => 15, 'min_pct' => 1], 100));

        // на сводке: прибыль с досчётом и что досчитано
        $this->page($this->owner())->assertSee('3 545 216 ₽')->assertSee('Досчитано у нас: −950 ₽ к прибыли по 2 договорам')
            ->assertSee('в CRM БлагоДар прибыль 3 546 166 ₽')->assertSee('по средней доле (работы 20,0% цены, товары 40,0%) — 3 строки на 1 100 ₽')
            ->assertSee('у 38 договоров в 1С не хватает цены или ставки')->assertSee('Ставки для строк без цены');
    }

    public function test_owner_sets_rates_and_profit_uses_them(): void
    {
        $this->uncosted();
        $owner = $this->owner();
        $q = ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-16'];
        $this->actingAs($owner)->get(route('owner.ceilings', ['rates' => 1] + $q))->assertOk()
            ->assertSee('Строки без цены')->assertSee('Доставка')->assertSee('Карниз ПВХ')->assertSee('Демонтаж сложный')
            ->assertSee('работы — 20,0% цены, товары — 40,0%')->assertSee('2 / 2')->assertSee('0 / 1');

        // ставка за штуку, % от суммы; больше 100% и мусор — не сохраняются
        $items = [['kind' => 'work', 'name' => 'Доставка', 'unit' => 'шт', 'mode' => 'unit', 'value' => '300'],
            ['kind' => 'goods', 'name' => 'Карниз ПВХ', 'unit' => 'шт', 'mode' => 'share', 'value' => '55,5'],
            ['kind' => 'work', 'name' => 'Демонтаж сложный', 'unit' => 'м2', 'mode' => 'share', 'value' => '150'],
            ['kind' => 'work', 'name' => 'Что-то', 'unit' => 'шт', 'mode' => 'unit', 'value' => 'abc']];
        $this->actingAs($owner)->post(route('owner.ceilings.rates'), ['items' => json_encode($items)] + $q)
            ->assertRedirect(route('owner.ceilings', ['rates' => 1] + $q))->assertSessionHas('status', 'Ставки сохранены: 2. Прибыль договоров пересчитана.');
        $this->assertSame(2, DB::table('owner_cost_rates')->count());

        // 1-й: 2 × 300 + 2 000 × 55,5% = 1 710, УСН −256,5 → −1 453,5; 2-й: 1 × 300
        $p = \App\Services\Owner\ContractProfitFill::apply(ContractProfit::forPeriod(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-17')), Carbon::parse('2026-10-01'), Carbon::parse('2026-10-17'));
        $this->assertEqualsWithDelta(3546166 - 1753.5, $p['data']['totals']['profit'], 0.001);
        $this->assertSame(3, $p['data']['fill']['rate_lines']);
        $this->actingAs($owner)->get(route('owner.ceilings', ['rates' => 1] + $q))->assertSee('value="300"', false)->assertSee('value="55,5"', false);

        // пустое значение убирает ставку; чужим — нельзя
        $this->actingAs($owner)->post(route('owner.ceilings.rates'), ['items' => json_encode([['kind' => 'work', 'name' => 'Доставка', 'unit' => 'шт', 'mode' => 'unit', 'value' => '']])] + $q);
        $this->assertSame(1, DB::table('owner_cost_rates')->count());
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->actingAs($head)->post(route('owner.ceilings.rates'), ['items' => json_encode($items)])->assertForbidden();
        $this->actingAs($head)->get(route('owner.ceilings', ['rates' => 1]))->assertForbidden();
    }
}
