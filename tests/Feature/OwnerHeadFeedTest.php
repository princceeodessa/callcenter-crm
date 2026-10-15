<?php

namespace Tests\Feature;

use App\Models\Account;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Шапка страниц владельца: поиск по сделкам и клиентам, карточка сделки и клиента (только просмотр), уведомления.
 * Потолки видит только владелец всех бизнесов; меню «Выйти» — пунктом, как в макете.
 */
class OwnerHeadFeedTest extends TestCase
{
    use DatabaseTransactions;

    private int $ceil;
    private int $shoes;
    private array $stage = [];
    private int $callDeal;
    private int $formDeal;
    private int $sale;
    private int $olga;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-10-15 12:00:00');
        $this->ceil = Account::create(['name' => 'Потолки (тест шапки)'])->id;
        $this->shoes = Account::create(['name' => 'Кроссовки (тест шапки)'])->id;
        config(['owner.ceilings_account_id' => $this->ceil]);
        $p = DB::table('pipelines')->insertGetId(['account_id' => $this->ceil, 'name' => 'Колл-центр', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        foreach (['lead' => ['Поступил лид / Назначить ответственного', 10], 'nt' => ['Нецелевое', 15], 'measure' => ['Замер назначен', 40], 'done' => ['Завершить сделку', 60]] as $k => [$name, $sort]) {
            $this->stage[$k] = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->ceil, 'pipeline_id' => $p, 'name' => $name, 'sort' => $sort, 'is_final' => $k === 'done' ? 1 : 0, 'created_at' => now(), 'updated_at' => now()]);
        }
        $sp = DB::table('pipelines')->insertGetId(['account_id' => $this->shoes, 'name' => 'Продажи', 'is_default' => 1, 'created_at' => now(), 'updated_at' => now()]);
        $sold = DB::table('pipeline_stages')->insertGetId(['account_id' => $this->shoes, 'pipeline_id' => $sp, 'name' => 'Продано', 'sort' => 10, 'is_final' => 1, 'created_at' => now(), 'updated_at' => now()]);

        $anna = User::create(['account_id' => $this->ceil, 'name' => 'Анна', 'email' => 'head_anna_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'operator', 'is_active' => true]);
        $contact = fn (string $name, ?string $phone, int $acc) => DB::table('contacts')->insertGetId(['account_id' => $acc, 'name' => $name, 'phone' => $phone, 'created_at' => now(), 'updated_at' => now()]);
        $deal = fn (array $f) => DB::table('deals')->insertGetId($f + ['account_id' => $this->ceil, 'pipeline_id' => $p, 'stage_id' => $this->stage['lead'], 'created_at' => '2026-10-15 09:00:00', 'updated_at' => '2026-10-15 09:00:00']);

        // Ольга позвонила — пропущенный, потом Анна записала её на замер
        $this->olga = $contact('Ольга Смирнова', '79124564421', $this->ceil);
        $this->callDeal = $deal(['title' => 'Звонки: 79124564421', 'contact_id' => $this->olga, 'stage_id' => $this->stage['measure']]);
        DB::table('deal_activities')->insert(['account_id' => $this->ceil, 'deal_id' => $this->callDeal, 'type' => 'call', 'body' => 'Звонок', 'payload' => json_encode(['type' => 'INCOMING', 'callid' => 'c1', 'phone' => '79124564421', 'diversion' => '79000000000']), 'created_at' => '2026-10-15 09:00:00', 'updated_at' => '2026-10-15 09:00:00']);
        DB::table('deal_stage_history')->insert(['account_id' => $this->ceil, 'deal_id' => $this->callDeal, 'from_stage_id' => $this->stage['lead'], 'to_stage_id' => $this->stage['measure'], 'changed_by_user_id' => $anna->id, 'changed_at' => '2026-10-15 11:00:00']);
        DB::table('deal_activities')->insert(['account_id' => $this->ceil, 'deal_id' => $this->callDeal, 'author_user_id' => $anna->id, 'type' => 'stage_changed', 'body' => 'Стадия сделки изменена.', 'payload' => json_encode(['from_stage_id' => $this->stage['lead'], 'to_stage_id' => $this->stage['measure']]), 'created_at' => '2026-10-15 11:00:00', 'updated_at' => '2026-10-15 11:00:00']);

        // форма с сайта
        $this->formDeal = $deal(['title' => 'Иван — сайт', 'contact_id' => $contact('Иван Петров', '79501000808', $this->ceil), 'created_at' => '2026-10-15 11:30:00', 'updated_at' => '2026-10-15 11:30:00']);
        DB::table('deal_activities')->insert(['account_id' => $this->ceil, 'deal_id' => $this->formDeal, 'type' => 'lead_form', 'body' => 'Форма', 'payload' => json_encode(['provider' => 'tilda']), 'created_at' => '2026-10-15 11:30:00', 'updated_at' => '2026-10-15 11:30:00']);

        // продажа кроссовок
        $this->sale = DB::table('deals')->insertGetId(['account_id' => $this->shoes, 'pipeline_id' => $sp, 'stage_id' => $sold, 'title' => 'Продажа: NIKE Air Force 1 · р. 42', 'amount' => 9990, 'sold_quantity' => 1,
            'stock_deducted_at' => '2026-10-15 10:00:00', 'closed_at' => '2026-10-15 10:00:00', 'closed_result' => 'won', 'created_at' => '2026-10-15 10:00:00', 'updated_at' => '2026-10-15 10:00:00']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function owner(bool $all = true): User
    {
        $u = User::create(['account_id' => $this->shoes, 'name' => 'Рустам Тестов', 'email' => 'head_owner_'.uniqid(), 'password' => Hash::make('password'), 'role' => 'sneaker_owner', 'is_active' => true]);
        $u->forceFill(['all_businesses' => $all])->save();

        return $u;
    }

    private function feed(User $u, array $q)
    {
        return $this->actingAs($u)->getJson(route('owner.dashboard', $q));
    }

    public function test_search_finds_deals_and_clients_by_name_and_phone(): void
    {
        $owner = $this->owner();
        $res = $this->feed($owner, ['feed' => 'search', 'q' => 'ольга'])->assertOk();
        $this->assertSame([['id' => $this->olga, 'name' => 'Ольга Смирнова', 'phone' => '+7 912 ••• 44 21', 'deals' => 1, 'biz' => 'Потолки']], $res->json('clients'));
        $this->assertSame('Ольга Смирнова — звонки', $res->json('deals.0.title'));
        $this->assertSame('Потолки', $res->json('deals.0.biz'));
        $this->assertStringStartsWith('Замер назначен · Звонок', $res->json('deals.0.sub'));

        // по номеру — с пробелами и восьмёркой
        $res = $this->feed($owner, ['feed' => 'search', 'q' => '8 912 456'])->assertOk();
        $this->assertSame([$this->olga], array_column($res->json('clients'), 'id'));
        $this->assertSame([$this->callDeal], array_column($res->json('deals'), 'id'));

        // кроссовки — тоже
        $res = $this->feed($owner, ['feed' => 'search', 'q' => 'nike'])->assertOk();
        $this->assertSame([['id' => $this->sale, 'title' => 'Продажа: NIKE Air Force 1 · р. 42', 'sub' => 'Продано', 'amount' => '9 990 ₽', 'date' => '15.10.26', 'biz' => 'Кроссовки']], $res->json('deals'));

        $this->assertSame(['deals' => [], 'clients' => []], $this->feed($owner, ['feed' => 'search', 'q' => 'о'])->json());
    }

    public function test_deal_and_client_cards(): void
    {
        $owner = $this->owner();
        $d = $this->feed($owner, ['feed' => 'deal', 'id' => $this->callDeal])->assertOk()->json();
        $this->assertSame(['id' => $this->olga, 'name' => 'Ольга Смирнова', 'phone' => '+7 (912) 456-44-21', 'tel' => '+79124564421'], $d['contact']);
        $facts = array_column($d['facts'], 'value', 'label');
        $this->assertSame('Замер назначен', $facts['Статус']);
        $this->assertSame('15.10.2026 11:00 · Анна', $facts['Записан на замер']);
        $this->assertSame(['Поступил лид → Замер назначен', 'Входящий звонок — пропущен', 'Сделка создана'], array_column($d['events'], 'text'));
        $this->assertSame('Анна', $d['events'][0]['who']);

        $c = $this->feed($owner, ['feed' => 'client', 'id' => $this->olga])->assertOk()->json();
        $this->assertSame(['Ольга Смирнова', '+7 (912) 456-44-21', [$this->callDeal]], [$c['name'], $c['phone'], array_column($c['deals'], 'id')]);

        $this->feed($owner, ['feed' => 'deal', 'id' => 999999999])->assertNotFound();
        $this->feed($owner, ['feed' => 'nope'])->assertNotFound();
    }

    public function test_sneakers_only_owner_does_not_see_ceilings(): void
    {
        $owner = $this->owner(false);
        $this->feed($owner, ['feed' => 'deal', 'id' => $this->callDeal])->assertNotFound();
        $this->feed($owner, ['feed' => 'client', 'id' => $this->olga])->assertNotFound();
        $this->assertSame(['deals' => [], 'clients' => []], $this->feed($owner, ['feed' => 'search', 'q' => 'Ольга'])->json());
        $this->assertSame(['Продажа: NIKE Air Force 1 · р. 42'], array_column($this->feed($owner, ['feed' => 'search', 'q' => 'nike'])->json('deals'), 'title'));
        $this->assertNull($this->feed($owner, ['feed' => 'search', 'q' => 'nike'])->json('deals.0.biz'));   // один бизнес — без подписи
        $texts = array_column($this->feed($owner, ['feed' => 'notifications'])->json('items'), 'text');
        $this->assertContains('Продажа — NIKE Air Force 1 · р. 42', $texts);
        $this->assertEmpty(array_filter($texts, fn ($t) => str_contains($t, 'замер') || str_contains($t, 'потолк')));
    }

    public function test_notifications(): void
    {
        $items = collect($this->feed($this->owner(), ['feed' => 'notifications'])->assertOk()->json('items'));
        $by = $items->keyBy('id');

        $this->assertStringStartsWith('Новый замер записан — ', $by['book:'.$this->callDeal]['text']);
        $this->assertSame(['measure', 'green', 'Анна · Ольга Смирнова', $this->callDeal], [$by['book:'.$this->callDeal]['icon'], $by['book:'.$this->callDeal]['tone'], $by['book:'.$this->callDeal]['sub'], $by['book:'.$this->callDeal]['deal']]);
        $this->assertStringStartsWith('Пропущенный звонок — ', $by['miss:2026-10-15 09']['text']);
        $this->assertSame(['Новая форма с сайта', 'Иван Петров · Поступил лид'], [$by['form:'.DB::table('deal_activities')->where('deal_id', $this->formDeal)->value('id')]['text'], $items->firstWhere('deal', $this->formDeal)['sub']]);
        $this->assertSame(['Продажа — NIKE Air Force 1 · р. 42', '9 990 ₽'], [$by['sale:'.$this->sale]['text'], $by['sale:'.$this->sale]['sub']]);

        // итоги дня — утром следующего: за 12.10, 13.10 и 14.10 по обоим бизнесам
        $this->assertSame(['Итоги за 14.10 по кроссовкам', 'продаж не было'], [$by['day:s:2026-10-14']['text'], $by['day:s:2026-10-14']['sub']]);
        $this->assertSame('Итоги за 14.10 по потолкам', $by['day:c:2026-10-14']['text']);
        $this->assertStringStartsWith('обратились 0 · записано на замер 0', $by['day:c:2026-10-14']['sub']);
        $this->assertStringContainsString('from=2026-10-14', $by['day:c:2026-10-14']['url']);
        $this->assertTrue($by->has('day:c:2026-10-12') && ! $by->has('day:c:2026-10-11') && ! $by->has('day:c:2026-10-15'));

        // новые сверху
        $this->assertSame($items->pluck('at')->sortDesc()->values()->all(), $items->pluck('at')->all());
        $this->assertSame('form:'.DB::table('deal_activities')->where('deal_id', $this->formDeal)->value('id'), $items->first()['id']);
    }

    public function test_header_has_search_bell_and_menu(): void
    {
        $this->actingAs($this->owner())->get(route('owner.ceilings'))->assertOk()
            ->assertSee('id="bdSearch"', false)->assertSee('id="bdBell"', false)->assertSee('id="bdCard"', false)
            ->assertSee('Владелец · БлагоДар')
            ->assertSee('<form method="POST" action="'.route('logout').'" class="menu-form">', false)
            ->assertSee('js/bd-owner-head.js', false);

        // SMM: подпись бизнеса для плеера — атрибутом (скрипт отчёта в @verbatim, Blade-директивы там не работают)
        $this->actingAs($this->owner())->get(route('owner.smm', ['business' => 'ceilings']))->assertOk()
            ->assertSee('data-biz="БлагоДар · Потолки"', false)->assertDontSee('@json', false);
    }
}
