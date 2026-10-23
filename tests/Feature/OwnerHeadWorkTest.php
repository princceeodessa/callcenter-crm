<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use App\Services\Owner\HeadWork;
use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * «Работа главного по замерщикам» на сводке владельца — готовые итоги CRM БлагоДар: по каждому человеку обзвонил,
 * договоры в 1С после звонков и сумма, ждём / перезвонить / отказались с причинами, сам съездил; устаревшее — строкой
 * сверху; без итогов — слова, а не нули; ответ в кэше 10 минут; ключ нигде не виден.
 */
class OwnerHeadWorkTest extends TestCase
{
    use DatabaseTransactions;

    private const KEY = 'owner.head_work.2026-10-01.2026-10-16';

    protected function setUp(): void
    {
        parent::setUp();
        $acc = Account::create(['name' => 'Потолки (тест главного)'])->id;
        $pipeline = DB::table('pipelines')->insertGetId(['account_id' => $acc, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        DB::table('pipeline_stages')->insert(['account_id' => $acc, 'pipeline_id' => $pipeline, 'name' => 'Замер назначен', 'sort' => 40, 'is_final' => 0, 'created_at' => now(), 'updated_at' => now()]);
        config(['owner.ceilings_account_id' => $acc, 'owner.nonclosures' => ['url' => null, 'token' => null], 'owner.contract_profit' => ['url' => null, 'token' => null]]);
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
            'account_id' => User::where('role', 'sneaker_head')->firstOrFail()->account_id, 'name' => 'Владелец (тест)', 'email' => 'owner_hw_'.uniqid(),
            'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true,
        ]);
        $u->forceFill(['all_businesses' => true])->save();

        return $u;
    }

    private function part(User $u)
    {
        return $this->actingAs($u)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-16', 'part' => 'headwork']))->assertOk();
    }

    private function answer(array $over = []): string
    {
        return json_encode(array_replace_recursive([
            'updated' => ['kc_sheet' => '2026-10-16T05:10:00Z', 'onec' => '2026-10-16T04:45:00Z'],
            'stale' => [],
            'period' => ['from' => '2026-10-01', 'to' => '2026-10-16'],
            'people' => [
                ['user' => 'Иванов Иван', 'calls' => ['total' => 9, 'customers' => 7, 'no_answer' => 2, 'call_back' => 1, 'agreed' => 1, 'refused' => 3, 'won' => 0],
                    'contracts' => 0, 'contracts_sum' => '0.00', 'outcomes' => ['refused' => 3, 'later' => 1], 'reasons' => [], 'days' => [], 'onec' => [], 'visits' => []],
                ['user' => 'Картапов Сергей', 'calls' => ['total' => 57, 'customers' => 42, 'no_answer' => 10, 'call_back' => 8, 'agreed' => 5, 'refused' => 6, 'won' => 2],
                    'contracts' => 3, 'contracts_sum' => '61400.00',
                    'outcomes' => ['contract' => 3, 'claimed' => 2, 'agreed' => 4, 'table' => 1, 'later' => 5, 'call_again' => 3, 'refused' => 6, 'gone' => 2, 'before' => 1],
                    'reasons' => [['reason' => 'нашли дешевле', 'n' => 1], ['reason' => 'дорого', 'n' => 2]],
                    'days' => [['day' => '2026-10-15', 'calls' => 12, 'customers' => 9], ['day' => '2026-10-16', 'calls' => 10, 'customers' => 8]],
                    'onec' => ['done' => 40, 'pending' => 2, 'failed' => 1],
                    'visits' => ['visits' => 4, 'won' => 1, 'lost' => 1, 'done' => 2, 'cancelled' => 0, 'open' => 2]],
            ],
            'url' => 'https://blagodar.test/reports/head-work?from=2026-10-01&to=2026-10-16',
        ], $over));
    }

    public function test_block_from_blagodar_totals(): void
    {
        config(['owner.head_work' => ['url' => 'https://blagodar.test/', 'token' => 'secret-head-xyz']]);
        $calls = 0;
        Http::$fake = function (string $method, string $url, array $headers) use (&$calls) {
            $calls++;
            $this->assertSame('https://blagodar.test/api/reports/head-work/summary?from=2026-10-01&to=2026-10-16', $url);
            $this->assertSame('Bearer secret-head-xyz', $headers['Authorization']);

            return [200, $this->answer()];
        };
        $owner = $this->owner();
        $res = $this->part($owner);
        $html = $res->getContent();
        $res->assertSee('Работа главного по замерщикам')->assertSee('Картапов Сергей')
            ->assertSee('Обзвонил <b>42</b> заказчиков · звонков 57', false)
            ->assertSee('После звонков договор в 1С: <b>3</b> на <b>61 400 ₽</b>', false)
            ->assertSee('Ждём договор')->assertSee('Перезвонить')->assertSee('Отказались')->assertSee('Сам съездил')
            ->assertSee('дорого — 2 · нашли дешевле — 1')
            ->assertSee('Съездил сам:</b> 4, из них договор 1', false)
            ->assertSee('отмечено «заключили», в 1С договора ещё нет — 2')->assertSee('договор был до звонка (в итог не входит) — 1')
            ->assertSee('в 1С комментарием: ушло 40')->assertSee('16.10 — 8 зак., 10 зв. · 15.10 — 9 зак., 12 зв.')
            ->assertSee('После звонков договоров в 1С пока нет')
            ->assertSee('href="https://blagodar.test/reports/head-work?from=2026-10-01&amp;to=2026-10-16"', false);
        $this->assertLessThan(strpos($html, 'Иванов Иван'), strpos($html, 'Картапов Сергей'), 'у кого больше договоров — выше');
        $this->assertStringNotContainsString('secret-head-xyz', $html);
        $this->assertStringNotContainsString('заявк', mb_strtolower($html));
        // ждём договор = заключили без 1С + договорились + КЦ отметил; перезвонить = позже + снова
        $p = HeadWork::forPeriod(Carbon::parse('2026-10-01'), Carbon::parse('2026-10-17'))['data']['people'][0];
        $this->assertSame([7, 8, 6], [$p['waiting'], $p['recall'], $p['outcomes']['refused']]);

        // 10 минут — из памяти; на самой странице блок сразу и пункт меню
        $this->part($owner);
        $this->assertSame(1, $calls);
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-16']))
            ->assertOk()->assertSee('Картапов Сергей')->assertSee('href="#headwork"', false)->assertDontSee('part=headwork', false);

        // устаревшее — строкой сверху
        Cache::forget(self::KEY);
        Http::$fake = fn () => [200, $this->answer(['stale' => ['таблица КЦ не обновлялась больше суток']])];
        $this->part($owner)->assertSee('⚠ таблица КЦ не обновлялась больше суток')->assertSee('hw-stale', false);
    }

    public function test_no_totals_means_words_not_zeros(): void
    {
        $owner = $this->owner();
        config(['owner.head_work' => ['url' => null, 'token' => null]]);
        $this->part($owner)->assertSee('Работу главного по замерщикам считает CRM БлагоДар')->assertSee('когда будет подключена их выдача')->assertDontSee('Обзвонил');

        config(['owner.head_work' => ['url' => 'https://blagodar.test', 'token' => 'secret-head-xyz']]);
        foreach ([401 => 'нужен новый ключ', 403 => 'их выдача сейчас выключена', 500 => 'сейчас данные не пришли'] as $status => $text) {
            Cache::forget(self::KEY);
            Http::$fake = fn () => [$status, '{"message":"no"}'];
            $res = $this->part($owner)->assertSee('Работу главного по замерщикам считает CRM БлагоДар')->assertSee($text)->assertDontSee('Обзвонил');
            $this->assertStringNotContainsString('secret-head-xyz', $res->getContent());
        }
        Cache::forget(self::KEY);
        Http::$fake = fn () => throw new \RuntimeException('Сеть: timeout');
        $this->part($owner)->assertSee('сейчас она не ответила')->assertDontSee('Обзвонил');
    }

    public function test_page_does_not_wait_for_blagodar(): void
    {
        config(['owner.head_work' => ['url' => 'https://blagodar.test', 'token' => 't']]);
        $calls = 0;
        Http::$fake = function () use (&$calls) {
            $calls++;

            return [200, $this->answer()];
        };
        $owner = $this->owner();
        $this->actingAs($owner)->get(route('owner.ceilings', ['period' => 'custom', 'from' => '2026-10-01', 'to' => '2026-10-16']))->assertOk()
            ->assertSee('part=headwork', false)->assertSee('Загружаю итоги из CRM БлагоДар')->assertDontSee('Обзвонил');
        $this->assertSame(0, $calls);
    }
}
