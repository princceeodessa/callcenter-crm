<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «Расторжения договоров» на сводке владельца «Потолки» (27.11.2026) — готовые итоги CRM БлагоДар по 1С: плитки
 * (расторгнуто, на сумму, вернули, осталось у нас), отменённые без причины «Расторжение» — строкой, замерщики, список
 * договоров свежими первыми; без итогов — слова, а не нули; ответ в кэше 10 минут; ключ нигде не виден.
 */
class OwnerTerminationsTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'owner.terminations.2026-11-01.2026-11-26';

    protected function setUp(): void
    {
        parent::setUp();
        $acc = Account::create(['name' => 'Потолки (тест расторжений)'])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pipeline_stages')->insert(['account_id' => $acc, 'pipeline_id' => $pipeline, 'name' => 'Замер назначен', 'sort' => 40, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        config(['owner.ceilings_account_id' => $acc, 'owner.nonclosures' => ['url' => null, 'token' => null],
            'owner.contract_profit' => ['url' => null, 'token' => null], 'owner.head_work' => ['url' => null, 'token' => null]]);
        // помесячная таблица — с 01.08.2026 до текущего месяца: август … ноябрь
        Carbon::setTestNow('2026-11-26 12:00:00');
        $this->flush();
    }

    protected function tearDown(): void
    {
        Http::$fake = null;
        $this->flush();
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function flush(): void
    {
        foreach ([self::KEY, ...self::MONTH_KEYS, 'owner.terminations.2026-07-01.2026-10-31'] as $k) {
            Cache::forget($k);
        }
    }

    private const MONTH_KEYS = ['owner.terminations.2026-08-01.2026-08-31', 'owner.terminations.2026-09-01.2026-09-30',
        'owner.terminations.2026-10-01.2026-10-31', 'owner.terminations.2026-11-01.2026-11-30'];

    private function owner(): User
    {
        $u = User::create([
            'account_id' => User::where('role', 'sneaker_head')->firstOrFail()->account_id, 'name' => 'Владелец (тест)', 'email' => 'owner_tm_'.uniqid(),
            'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true,
        ]);
        $u->forceFill(['all_businesses' => true])->save();

        return $u;
    }

    private function part(User $u)
    {
        return $this->actingAs($u)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-26', 'part' => 'terminations']))->assertOk();
    }

    private function answer(array $over = []): string
    {
        return json_encode(array_replace([
            'updated' => ['onec' => '2026-11-26T06:20:00Z'],
            'stale' => [],
            'period' => ['from' => '2026-11-01', 'to' => '2026-11-26'],
            'totals' => ['contracts' => 2, 'amount' => '285000.00', 'paid' => '70000.00', 'refunded' => '40000.00', 'kept' => '30000.00', 'unmarked' => 3],
            'by_measurer' => [
                ['measurer' => 'Петров Пётр', 'contracts' => 1, 'amount' => '100000.00'],
                ['measurer' => 'Костюнин Федор', 'contracts' => 1, 'amount' => '185000.00'],
            ],
            'items' => [
                ['base' => 'unf_10', 'label' => 'Закиров новая', 'number' => '1877', 'contractDate' => '2026-08-02', 'terminatedAt' => '2026-11-03', 'dateKnown' => false,
                    'amount' => '100000.00', 'paid' => '20000.00', 'refunded' => '0.00', 'reason' => 'Расторжение', 'comment' => null, 'measurer' => 'Петров Пётр', 'url' => null],
                ['base' => 'unf_18', 'label' => 'Закирова 2026', 'number' => '2296', 'contractDate' => '2026-09-29', 'terminatedAt' => '2026-11-20', 'dateKnown' => true,
                    'amount' => '185000.00', 'paid' => '50000.00', 'refunded' => '40000.00', 'reason' => 'Расторжение', 'comment' => 'переехали, ремонт отменили',
                    'measurer' => 'Костюнин Федор', 'url' => 'https://blagodar.test/deals/abc'],
            ],
            'url' => 'https://blagodar.test/reports/terminations?from=2026-11-01&to=2026-11-26',
        ], $over));
    }

    public function test_block_from_blagodar_totals(): void
    {
        config(['owner.terminations' => ['url' => 'https://blagodar.test/', 'token' => 'secret-term-xyz']]);
        $calls = [];
        $empty = ['totals' => ['contracts' => 0, 'amount' => '0.00', 'unmarked' => 0], 'by_measurer' => [], 'items' => []];
        $months = [
            '2026-08-01&to=2026-08-31' => ['totals' => ['contracts' => 4, 'amount' => '208395.00', 'unmarked' => 5], 'items' => [
                ['number' => '1813', 'terminatedAt' => '2026-08-19', 'dateKnown' => false, 'amount' => '49230.00', 'reason' => 'расторжение']]],
            '2026-09-01&to=2026-09-30' => $empty,
            '2026-10-01&to=2026-10-31' => ['totals' => ['contracts' => 0, 'amount' => '0.00', 'unmarked' => 4], 'by_measurer' => [], 'items' => []],
            '2026-11-01&to=2026-11-30' => ['totals' => ['contracts' => 1, 'amount' => '185000.00', 'unmarked' => 1], 'items' => [
                ['number' => '2296', 'terminatedAt' => '2026-11-20', 'dateKnown' => true, 'amount' => '185000.00', 'reason' => 'Расторжение']]],
        ];
        Http::$fake = function (string $method, string $url, array $headers) use (&$calls, $months) {
            $this->assertSame('Bearer secret-term-xyz', $headers['Authorization']);
            $q = str_replace('https://blagodar.test/api/reports/contract-terminations/summary?from=', '', $url);
            $calls[] = $q;
            if ($q === '2026-11-01&to=2026-11-26') {
                return [200, $this->answer()];
            }
            $this->assertArrayHasKey($q, $months, 'лишний запрос: '.$url);

            return [200, $this->answer($months[$q])];
        };
        $owner = $this->owner();
        $res = $this->part($owner);
        $html = $res->getContent();
        $res->assertSee('Расторжения договоров')->assertSee('Расторгнуто')->assertSee('285 000 ₽')
            ->assertSee('Вернули заказчикам')->assertSee('40 000 ₽')->assertSee('заплатили по ним 70 000 ₽')
            ->assertSee('Осталось у нас')->assertSee('30 000 ₽')
            ->assertSee('Ещё 3 договора в 1С отменены целиком, но без причины «Расторжение» — в расторжения не входят.')
            ->assertSee('Костюнин Федор')->assertSee('Петров Пётр')
            ->assertSee('href="https://blagodar.test/deals/abc"', false)->assertSee('№ 2296')->assertSee('Закирова 2026 · от 29.09.2026')
            ->assertSee('20.11.2026')->assertSee('переехали, ремонт отменили')
            ->assertSee('03.11.2026 <span class="muted" title="точного дня в 1С нет">≈</span>', false)
            ->assertSee('href="https://blagodar.test/reports/terminations?from=2026-11-01&amp;to=2026-11-26"', false)
            ->assertSee('<th class="num">Заплатили</th>', false)->assertDontSee('нет данных')->assertDontSee('расторжения видны с');
        $this->assertLessThan(strpos($html, '№ 1877'), strpos($html, '№ 2296'), 'свежие расторжения — первыми');
        $this->assertStringNotContainsString('secret-term-xyz', $html);
        $this->assertStringNotContainsString('заявк', mb_strtolower($html));

        // по каждому месяцу с августа — новые сверху; месяц — ссылка на свой период; ≈ — где день расторжения примерный
        $res->assertSee('По месяцам')->assertSeeInOrder(['Ноябрь 2026', 'Октябрь 2026', 'Сентябрь 2026', 'Август 2026'])
            ->assertSee('from=2026-08-01&amp;to=2026-08-31#terminations', false)->assertDontSee('class="tm-cur"', false)
            ->assertSee('<b class="tm-neg">4</b> <span class="muted" title="точного дня расторжения нет — месяц последнего заказа договора">≈</span>', false)
            ->assertSee('208 395 ₽')->assertSee('<b class="tm-neg">1</b></td>', false)
            ->assertSee('≈ — точного дня расторжения пока нет: договор стоит в месяце своего последнего заказа. Видны договоры с заказами с 01.08.2026.');
        $this->assertSame(5, count($calls), 'период и четыре месяца: '.implode(', ', $calls));

        // 10 минут — из памяти; на самой странице блок сразу (с месяцами) и пункт меню
        $this->part($owner);
        $this->assertSame(5, count($calls));
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-26']))
            ->assertOk()->assertSee('№ 2296')->assertSee('Август 2026')->assertSee('href="#terminations"', false)->assertDontSee('part=terminations', false);
        // месяца нет в кэше — блок догружается целиком, а не без месяцев
        Cache::forget(self::MONTH_KEYS[0]);
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-26']))
            ->assertOk()->assertSee('part=terminations', false)->assertDontSee('№ 2296');
        $this->assertSame(5, count($calls));

        // за период ничего — словами, и про отменённые без причины
        Cache::forget(self::KEY);
        Http::$fake = fn () => [200, $this->answer(['totals' => ['contracts' => 0, 'amount' => '0.00', 'unmarked' => 1], 'by_measurer' => [], 'items' => []])];
        $this->part($owner)->assertSee('За выбранный период расторжений нет. Но 1 договор в 1С отменён целиком без причины «Расторжение».')
            ->assertDontSee('Вернули заказчикам');
    }

    /** Как отвечает CRM БлагоДар с 27.11.2026: возвратов в выгрузке 1С нет (null), история — «примерно», причина со строчной. */
    public function test_answer_without_refunds(): void
    {
        $key = 'owner.terminations.2026-07-01.2026-10-31';
        Cache::forget($key);
        config(['owner.terminations' => ['url' => 'https://blagodar.test', 'token' => 't']]);
        $item = ['base' => 'unf_10', 'label' => 'Закиров новая', 'number' => '2310', 'contractDate' => '2026-10-02', 'terminatedAt' => '2026-10-02', 'dateKnown' => false,
            'amount' => '38000.00', 'paid' => null, 'refunded' => null, 'reason' => 'расторжение', 'comment' => str_repeat('высота 2,6 м ', 20), 'measurer' => 'Костюнин Федя',
            'url' => 'https://blagodar.test/reports/contract-profit?from=2026-10-02&to=2026-10-02'];
        Http::$fake = fn () => [200, $this->answer([
            'stale' => ['Оплачено и возвращено заказчику пока не считаются: возвратов в выгрузке из 1С ещё нет'],
            'totals' => ['contracts' => 1, 'amount' => '38000.00', 'paid' => null, 'refunded' => null, 'kept' => null, 'unmarked' => 12],
            'by_measurer' => [['measurer' => 'Костюнин Федя', 'contracts' => 1, 'amount' => '38000.00']],
            'items' => [$item], 'url' => null,
        ])];
        $res = $this->actingAs($this->owner())->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-07-01', 'to' => '2026-10-31', 'part' => 'terminations']))->assertOk();
        $html = $res->getContent();
        $res->assertSee('38 000 ₽')->assertSee('нет данных')->assertSee('возвратов в выгрузке 1С пока нет')
            ->assertSee('Оплачено и возвращено заказчику пока не считаются: возвратов в выгрузке из 1С ещё нет')
            ->assertDontSee('Данные могут быть устаревшими')->assertDontSee('<th class="num">Заплатили</th>', false)
            ->assertSee('<td class="wrap tm-reason">Расторжение', false)->assertSee('≈ — точного дня расторжения в 1С нет')
            ->assertSee('расторжения видны с 01.08.2026')->assertDontSee('полный отчёт')
            ->assertSee('Ещё 12 договоров в 1С отменены целиком');
        $this->assertStringNotContainsString(str_repeat('высота 2,6 м ', 20), $html, 'длинный комментарий заказа — обрезан');
        Cache::forget($key);
    }

    public function test_no_totals_means_words_not_zeros(): void
    {
        $owner = $this->owner();
        config(['owner.terminations' => ['url' => 'https://blagodar.test', 'token' => null]]);
        $this->part($owner)->assertSee('Расторжения договоров считает CRM БлагоДар по 1С')->assertSee('когда будет подключена их выдача')->assertDontSee('Расторгнуто');

        config(['owner.terminations' => ['url' => 'https://blagodar.test', 'token' => 'secret-term-xyz']]);
        foreach ([401 => 'нужен новый ключ', 403 => 'их выдача сейчас выключена', 404 => 'сейчас данные не пришли (ответ 404)'] as $status => $text) {
            Cache::forget(self::KEY);
            Http::$fake = fn () => [$status, '{"message":"no"}'];
            $res = $this->part($owner)->assertSee($text)->assertDontSee('Расторгнуто');
            $this->assertStringNotContainsString('secret-term-xyz', $res->getContent());
        }
        Cache::forget(self::KEY);
        Http::$fake = fn () => throw new \RuntimeException('Сеть: timeout');
        $this->part($owner)->assertSee('сейчас она не ответила')->assertDontSee('Расторгнуто');
    }

    public function test_page_does_not_wait_for_blagodar(): void
    {
        config(['owner.terminations' => ['url' => 'https://blagodar.test', 'token' => 't']]);
        $calls = 0;
        Http::$fake = function () use (&$calls) {
            $calls++;

            return [200, $this->answer()];
        };
        $this->actingAs($this->owner())->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-26']))->assertOk()
            ->assertSee('part=terminations', false)->assertSee('Загружаю расторжения из CRM БлагоДар')->assertDontSee('Расторгнуто');
        $this->assertSame(0, $calls);
    }
}
