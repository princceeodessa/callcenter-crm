<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Owner\ContractTerminations;
use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «Расторжения договоров» на сводке владельца «Потолки» (27–28.11.2026) — готовые итоги CRM БлагоДар по 1С: каждый месяц 2026 года
 * (месяц раскрывает свои договоры), плитки выбранного периода, замерщики, договоры свежими первыми; «Подробно за год» — своя страница
 * (месяцы, замерщик × месяц, причины, базы, все договоры с фильтрами). Без итогов — слова, а не нули; кэш 10 минут; ключ нигде не виден.
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
        // помесячная таблица — с 01.01.2026 до текущего месяца: январь … ноябрь
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

    /** Кэш-ключи месяцев 2026 года: январь … ноябрь */
    private static function monthKeys(): array
    {
        return array_map(function (int $m) {
            $d = Carbon::create(2026, $m, 1);

            return 'owner.terminations.'.$d->toDateString().'.'.$d->copy()->endOfMonth()->toDateString();
        }, range(1, 11));
    }

    private function flush(): void
    {
        foreach ([self::KEY, ...self::monthKeys(), 'owner.terminations.2026-11-01.2026-11-30', 'owner.terminations.2025-12-01.2026-02-28'] as $k) {
            Cache::forget($k);
        }
    }

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
                    'measurer' => 'Костюнин Федор', 'url' => 'https://blagodar.test/onec-contracts?q=2296'],
            ],
            'url' => 'https://blagodar.test/reports/terminations?from=2026-11-01&to=2026-11-26',
        ], $over));
    }

    /** Договор так, как его отдаёт CRM БлагоДар с 28.11.2026: день отмены по версиям 1С, без комментария, ссылка — «Договоры в 1С» */
    private static function item(array $over = []): array
    {
        return array_replace(['base' => 'unf_18', 'label' => 'Закирова УНФ', 'number' => '642', 'contractDate' => '2026-05-13', 'terminatedAt' => '2026-11-18',
            'dateKnown' => true, 'amount' => '70000.00', 'paid' => null, 'refunded' => null, 'reason' => 'Расторжение', 'measurer' => 'Костюнин Федя',
            'url' => 'https://blagodar.test/onec-contracts?q='.($over['number'] ?? '642')], $over);
    }

    private static function month(int $contracts, string $amount, int $unmarked, array $items = []): array
    {
        return ['stale' => ['Оплачено и возвращено заказчику пока не считаются: возвратов в выгрузке из 1С ещё нет'],
            'totals' => ['contracts' => $contracts, 'amount' => $amount, 'paid' => null, 'refunded' => null, 'kept' => null, 'unmarked' => $unmarked],
            'by_measurer' => [], 'items' => $items, 'url' => null];
    }

    /** Год как в сверке CRM БлагоДар 28.11.2026 (в уменьшенном виде): ноябрь — «Расторжение» и «Отказ» по старым договорам */
    private static function yearMonths(): array
    {
        return [
            '2026-11-01&to=2026-11-30' => self::month(3, '200800.00', 0, [
                self::item(),
                self::item(['number' => '390', 'contractDate' => '2026-09-22', 'terminatedAt' => '2026-11-19', 'amount' => '92800.00', 'reason' => 'Отказ', 'measurer' => 'Зорин Саша']),
                self::item(['base' => 'unf_10', 'label' => 'Закиров новая', 'number' => '2310', 'contractDate' => '2026-10-01', 'terminatedAt' => '2026-11-04',
                    'amount' => '38000.00', 'reason' => 'расторжение', 'measurer' => null]),
            ]),
            '2026-10-01&to=2026-10-31' => self::month(0, '0.00', 3),
            '2026-08-01&to=2026-08-31' => self::month(1, '49230.00', 0, [
                self::item(['base' => 'unf_10', 'label' => 'Закиров новая', 'number' => '1813', 'contractDate' => '2026-08-01', 'terminatedAt' => '2026-08-19',
                    'amount' => '49230.00', 'reason' => 'расторжение', 'measurer' => 'Зорин Саша']),
            ]),
            '2026-01-01&to=2026-01-31' => self::month(1, '98840.00', 1, [
                self::item(['number' => '77', 'contractDate' => null, 'terminatedAt' => '2026-01-20', 'dateKnown' => false, 'amount' => '98840.00', 'reason' => 'Отказ', 'measurer' => 'Костюнин Федя']),
            ]),
        ];
    }

    /** Подмена CRM БлагоДар: месяцы 2026 — из $months (нет в списке — пустой месяц), остальные запросы — $other */
    private function fake(array $months, callable $other, array &$calls): void
    {
        Http::$fake = function (string $method, string $url, array $headers) use (&$calls, $months, $other) {
            $this->assertSame('GET', $method);
            $q = preg_replace('~^https://blagodar\.test/api/reports/contract-terminations/summary\?from=~', '', $url);
            $calls[] = $q;
            if (preg_match('~^2026-(\d\d)-01&to=2026-\1-(\d\d)$~', $q, $mm) && Carbon::create(2026, (int) $mm[1], 1)->endOfMonth()->day === (int) $mm[2]) {
                return [200, $this->answer($months[$q] ?? self::month(0, '0.00', 0))];
            }

            return $other($q, $headers);
        };
    }

    public function test_block_from_blagodar_totals(): void
    {
        config(['owner.terminations' => ['url' => 'https://blagodar.test/', 'token' => 'secret-term-xyz']]);
        $calls = [];
        $this->fake(self::yearMonths(), function (string $q, array $headers) {
            $this->assertSame('Bearer secret-term-xyz', $headers['Authorization']);
            $this->assertSame('2026-11-01&to=2026-11-26', $q, 'лишний запрос');

            return [200, $this->answer()];
        }, $calls);
        $owner = $this->owner();
        $res = $this->part($owner);
        $html = $res->getContent();
        $res->assertSee('Расторжения договоров')->assertSee('Расторгнуто')->assertSee('285 000 ₽')
            ->assertSee('Вернули заказчикам')->assertSee('40 000 ₽')->assertSee('заплатили по ним 70 000 ₽')
            ->assertSee('Осталось у нас')->assertSee('30 000 ₽')
            ->assertSee('Ещё 3 договора в 1С отменены целиком по другой причине (не «Расторжение» и не «Отказ») — в расторжения не входят.')
            ->assertSee('Костюнин Федор')->assertSee('Петров Пётр')
            ->assertSee('href="https://blagodar.test/onec-contracts?q=2296"', false)->assertSee('№ 2296')->assertSee('Закирова 2026')
            ->assertSee('20.11.2026')->assertSee('29.09.2026')->assertSee('52 дня')
            ->assertSee('<b>03.11.2026</b> <span class="muted" title="точного дня в 1С нет">≈</span>', false)
            ->assertSee('href="https://blagodar.test/reports/terminations?from=2026-11-01&amp;to=2026-11-26"', false)
            ->assertSee('<th class="num">Заплатили</th>', false)->assertDontSee('нет данных')->assertDontSee('расторжения видны с')
            // комментарий заказа не показываем — в нём бывают адреса
            ->assertDontSee('переехали, ремонт отменили');
        $this->assertLessThan(strpos($html, '№ 1877'), strpos($html, '№ 2296'), 'свежие расторжения — первыми');
        $this->assertStringNotContainsString('secret-term-xyz', $html);
        $this->assertStringNotContainsString('заявк', mb_strtolower($html));

        // каждый месяц 2026 года — новые сверху; месяц с договорами раскрывается (договоры — тут же, скрытой строкой)
        $res->assertSee('По месяцам')->assertSeeInOrder(['Ноябрь 2026', 'Октябрь 2026', 'Сентябрь 2026', 'Август 2026', 'Февраль 2026', 'Январь 2026'])
            ->assertSee('data-tm-toggle="2026-11"', false)->assertSee('data-tm-det="2026-11" hidden', false)
            ->assertDontSee('data-tm-toggle="2026-10"', false)->assertDontSee('tm-cur', false)
            ->assertSee('<b class="tm-neg">3</b></td>', false)->assertSee('200 800 ₽')
            ->assertSee('№ 642')->assertSee('189 дней')->assertSee('№ 390')->assertSee('58 дней')->assertSee('№ 2310')->assertSee('34 дня')
            ->assertSee('<span class="tag amber">Отказ</span>', false)->assertSee('<span class="tag red">Расторжение</span>', false)
            ->assertSee('не определён')
            ->assertSee('from=2026-11-01&amp;to=2026-11-30#terminations', false)->assertSee('сводка за этот месяц →')
            ->assertSee('Итого за 2026')->assertSee('<b class="tm-neg">5</b>', false)->assertSee('348 870 ₽')
            ->assertSee('Подробно за год →')->assertSee('report=terminations', false)
            ->assertSee('≈ — у части договоров точного дня отмены в 1С нет')->assertDontSee('пока отдаёт расторжения только');
        $this->assertSame(12, count($calls), 'период и одиннадцать месяцев: '.implode(', ', $calls));

        // 10 минут — из памяти; на самой странице блок сразу (с месяцами) и пункт меню
        $this->part($owner);
        $this->assertSame(12, count($calls));
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-26']))
            ->assertOk()->assertSee('№ 2296')->assertSee('Январь 2026')->assertSee('href="#terminations"', false)->assertDontSee('part=terminations', false)
            ->assertSee('window.bdTmApply', false);
        // месяца нет в кэше — блок догружается целиком, а не без месяцев
        Cache::forget(self::monthKeys()[0]);
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-26']))
            ->assertOk()->assertSee('part=terminations', false)->assertDontSee('№ 2296');
        $this->assertSame(12, count($calls));

        // выбран ноябрь целиком — его строка подсвечена
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-11-01', 'to' => '2026-11-30', 'part' => 'terminations']))
            ->assertOk()->assertSee('tm-cur', false);

        // за период ничего — словами, и про отменённые по другой причине
        Cache::forget(self::KEY);
        $this->fake(self::yearMonths(), fn () => [200, $this->answer(['totals' => ['contracts' => 0, 'amount' => '0.00', 'unmarked' => 1], 'by_measurer' => [], 'items' => []])], $calls);
        $this->part($owner)->assertSee('За выбранный период расторжений нет. Но 1 договор в 1С отменён целиком по другой причине.')
            ->assertDontSee('Вернули заказчикам');
    }

    /** Как отвечает CRM БлагоДар: возвратов в выгрузке 1С нет (null), причина со строчной; до 2026 года расторжений не видно. */
    public function test_answer_without_refunds(): void
    {
        config(['owner.terminations' => ['url' => 'https://blagodar.test', 'token' => 't']]);
        $calls = [];
        $this->fake([], fn () => [200, $this->answer(self::month(1, '38000.00', 12, [
            self::item(['base' => 'unf_10', 'label' => 'Закиров новая', 'number' => '2310', 'contractDate' => '2026-01-02', 'terminatedAt' => '2026-02-02',
                'dateKnown' => false, 'amount' => '38000.00', 'reason' => 'расторжение', 'comment' => 'Трудовая 32', 'measurer' => 'Костюнин Федя']),
        ]))], $calls);
        $res = $this->actingAs($this->owner())->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2025-12-01', 'to' => '2026-02-28', 'part' => 'terminations']))->assertOk();
        $res->assertSee('38 000 ₽')->assertSee('нет данных')->assertSee('возвратов в выгрузке 1С пока нет')
            ->assertSee('Оплачено и возвращено заказчику пока не считаются: возвратов в выгрузке из 1С ещё нет')
            ->assertDontSee('<th class="num">Заплатили</th>', false)
            ->assertSee('<span class="tag red">Расторжение</span>', false)->assertSee('≈ — точного дня отмены в 1С нет')
            ->assertSee('расторжения видны с 01.01.2026')->assertDontSee('полный отчёт')->assertDontSee('Трудовая 32')
            ->assertSee('Ещё 12 договоров в 1С отменены целиком');
    }

    /** CRM БлагоДар ещё не выкатила дни отмены из 1С: договоры — со ссылкой на «Прибыль договоров»; месяцы тогда с 01.08, а не нули с января. */
    public function test_old_blagodar_answer_shows_months_from_august(): void
    {
        config(['owner.terminations' => ['url' => 'https://blagodar.test', 'token' => 't']]);
        $calls = [];
        $old = self::month(1, '49230.00', 5, [self::item(['number' => '1813', 'contractDate' => '2026-08-19', 'terminatedAt' => '2026-08-19', 'dateKnown' => false,
            'amount' => '49230.00', 'reason' => 'расторжение', 'url' => 'https://blagodar.test/reports/contract-profit?from=2026-08-19&to=2026-08-19'])]);
        $this->fake(['2026-08-01&to=2026-08-31' => $old], fn () => [200, $this->answer(self::month(0, '0.00', 1))], $calls);
        $owner = $this->owner();
        $this->part($owner)->assertSee('Август 2026')->assertDontSee('Июль 2026')->assertDontSee('Январь 2026')
            ->assertSee('Итого с 01.08.2026')->assertSee('CRM БлагоДар пока отдаёт расторжения только по договорам с заказами с 01.08.2026');
        $this->actingAs($owner)->get(route('owner.ceilings', ['report' => 'terminations']))->assertOk()
            ->assertSee('Итого с 01.08.2026')->assertSee('CRM БлагоДар пока отдаёт расторжения только по договорам с заказами с 01.08.2026')
            // день примерный (≈ день последнего заказа) — «через сколько дней» не считаем
            ->assertSee('точных дней отмены пока нет')->assertDontSee('Январь 2026');
    }

    /** «Подробно за год»: итоги, график, месяцы, замерщик × месяц, причины, базы, все договоры по месяцам с фильтрами. */
    public function test_year_report_page(): void
    {
        config(['owner.terminations' => ['url' => 'https://blagodar.test', 'token' => 'secret-term-xyz']]);
        $calls = [];
        $this->fake(self::yearMonths(), fn ($q) => $this->fail('лишний запрос: '.$q), $calls);
        $owner = $this->owner();
        $res = $this->actingAs($owner)->get(route('owner.ceilings', ['report' => 'terminations', 'period' => 'month']))->assertOk();
        $html = $res->getContent();
        $this->assertSame(11, count($calls), 'только месяцы года');
        $res->assertSee('Расторжения договоров')->assertSee('расторжения договоров за 2026 год')
            ->assertSee('отменены все заказы с причиной «Расторжение» или «Отказ»')
            // 5 договоров на 348 870 ₽, из них «Отказ» — 2; дни: 189, 58, 34, 18 (у 77 дня договора нет) → медиана 46; по другой причине 3 + 1
            ->assertSee('<div class="val num neg">5</div>', false)->assertSee('348 870 ₽')->assertSee('из них «Отказ» — 2')
            ->assertSee('46 дней')->assertSee('<div class="val num">4</div>', false)
            ->assertSee('data-bars=', false)->assertSee('&quot;«Отказ»&quot;', false)
            ->assertSee('href="#m-2026-11"', false)->assertSee('id="m-2026-11"', false)->assertSee('id="m-2026-01"', false)->assertDontSee('id="m-2026-10"', false)
            ->assertSee('Итого за 2026 год')
            ->assertSee('По замерщикам')->assertSee('data-who-f="Костюнин Федя"', false)->assertSee('data-who-f="—"', false)
            ->assertSee('По причине в 1С')->assertSee('По базам 1С')->assertSee('Закирова УНФ')->assertSee('Закиров новая')
            ->assertSee('Все договоры')->assertSee('data-kind-f="Отказ"', false)->assertSee('<option value="Зорин Саша">Зорин Саша · 2</option>', false)
            ->assertSee('data-month="2026-11"', false)->assertSee('data-kind="Отказ"', false)
            ->assertSee('href="https://blagodar.test/onec-contracts?q=642"', false)
            ->assertSee('Оплачено и возвращено заказчику пока не считаются');
        $this->assertInOrder($html, ['id="m-2026-11"', '№ 642', 'id="m-2026-08"', '№ 1813', 'id="m-2026-01"', '№ 77']);
        // замерщик × месяц: у Зорина — по одному в ноябре и августе
        $year = ContractTerminations::year(false);
        $zorin = collect($year['by_measurer'])->firstWhere('name', 'Зорин Саша');
        $this->assertSame(['2026-11' => 1, '2026-08' => 1], $zorin['months']);
        $this->assertSame(['Расторжение', 'Отказ'], array_column($year['by_kind'], 'name'));
        $this->assertSame(46, $year['totals']['days']);
        $this->assertStringNotContainsString('secret-term-xyz', $html);
        $this->assertStringNotContainsString('заявк', mb_strtolower($html));

        // страница — только владельцу всех бизнесов
        $head = User::where('role', 'sneaker_head')->firstOrFail();
        $this->actingAs($head)->get(route('owner.ceilings', ['report' => 'terminations']))->assertForbidden();
    }

    private function assertInOrder(string $html, array $parts): void
    {
        $pos = -1;
        foreach ($parts as $p) {
            $at = strpos($html, $p, $pos + 1);
            $this->assertNotFalse($at, 'нет или не по порядку: '.$p);
            $pos = $at;
        }
    }

    public function test_no_totals_means_words_not_zeros(): void
    {
        $owner = $this->owner();
        config(['owner.terminations' => ['url' => 'https://blagodar.test', 'token' => null]]);
        $this->part($owner)->assertSee('Расторжения договоров считает CRM БлагоДар по 1С')->assertSee('когда будет подключена их выдача')->assertDontSee('Расторгнуто');
        $this->actingAs($owner)->get(route('owner.ceilings', ['report' => 'terminations']))->assertOk()
            ->assertSee('когда будет подключена их выдача')->assertDontSee('id="tm-list"', false);

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
