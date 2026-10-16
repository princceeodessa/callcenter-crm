<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Owner\ContractProfit;
use App\Services\Owner\Marketing\Http;
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

    public function test_margin_in_words(): void
    {
        $w = fn ($m) => ContractProfit::verdict($m)[0];
        $this->assertSame(['хорошо', 'хорошо', 'средне', 'средне', 'мало', 'мало', 'убыток', ''], [$w(42.2), $w(30.0), $w(29.9), $w(15.0), $w(14.9), $w(0.0), $w(-0.1), $w(null)]);
    }
}
