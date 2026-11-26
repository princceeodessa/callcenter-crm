<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Blagodar\ContractSearchClient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * «Договоры 1С» колл-центра (26.11.2026): поиск договоров через CRM «БлагоДар» — учётка сайта, одна пара токенов,
 * 401 → refresh → повтор, тот же запрос — по уже начатому поиску, служебные поля и файлы в браузер не уходят,
 * доступ — колл-центру потолков и документам.
 */
class ContractSearchTest extends TestCase
{
    use DatabaseTransactions;

    private const API = 'https://crm.test/api';

    private string $tokenFile;

    /** @var list<array{method: string, path: string, json: ?array, auth: ?string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->tokenFile = sys_get_temp_dir().'/kc-contracts-'.getmypid().'-'.uniqid().'.json';
        config(['blagodar_crm.api' => self::API, 'blagodar_crm.login' => 'kc-site', 'blagodar_crm.password' => 'secret', 'blagodar_crm.token_file' => $this->tokenFile]);
        Cache::flush();
    }

    protected function tearDown(): void
    {
        ContractSearchClient::$fake = null;
        @unlink($this->tokenFile);
        @unlink($this->tokenFile.'.lock');
        parent::tearDown();
    }

    /** Ответы CRM: «METHOD /путь» => [код, json] или список таких — по очереди. */
    private function fake(array $routes): void
    {
        $this->sent = [];
        ContractSearchClient::$fake = function (string $method, string $url, ?array $json, array $headers) use (&$routes) {
            $path = substr($url, strlen(self::API));
            $this->sent[] = ['method' => $method, 'path' => $path, 'json' => $json, 'auth' => $headers['Authorization'] ?? null];
            $key = $method.' '.$path;
            $this->assertArrayHasKey($key, $routes, 'неожиданный запрос к CRM: '.$key);
            $r = $routes[$key];
            if (is_array($r[0] ?? null)) {   // очередь ответов
                $next = array_shift($routes[$key]);

                return $next;
            }

            return $r;
        };
    }

    private function sentTo(string $path): array
    {
        return array_values(array_filter($this->sent, fn ($s) => $s['path'] === $path));
    }

    private function operator(): User
    {
        $acc = User::whereIn('role', ['admin', 'main_operator', 'operator'])->value('account_id')
            ?? \App\Models\Account::create(['name' => 'Потолки (тест договоров)'])->id;

        return User::create(['account_id' => $acc, 'name' => 'Оператор КЦ (тест)', 'email' => 'kc_op_'.uniqid().'@t.local',
            'password' => bcrypt('x'), 'role' => 'operator', 'is_active' => true]);
    }

    private function crmStatus(string $state = 'done'): array
    {
        return [
            'id' => 's-1', 'query' => '2296', 'state' => $state,
            'bases' => [['key' => 'unf_18', 'label' => 'Закирова 2026'], ['key' => 'unf_4', 'label' => 'Третьяков'], ['key' => 'unf_10', 'label' => 'Закиров новая']],
            'waiting' => $state === 'done' ? [] : [['key' => 'unf_10', 'label' => 'Закиров новая']],
            'results' => [
                ['base' => 'unf_18', 'label' => 'Закирова 2026', 'ok' => true, 'error' => null, 'contracts' => [[
                    'id' => 'ref-1', 'name' => '2296 Пробная 1-1', 'customer' => 'Пробная Анна', 'number' => '2296', 'date' => '2026-09-29',
                    'organization' => 'ИП Закирова', 'amount' => '185000.00', 'note' => 'потолок в зал', 'isOrg' => false,
                    'measurers' => [['name' => 'Костюнин Федор', 'date' => '2026-09-29', 'visits' => 2, 'via' => 'customer']],
                    'fileCount' => 1, 'files' => [['id' => 'f1', 'name' => 'паспорт.pdf']], 'customerFiles' => [],
                    'customerCode' => '000123', 'token' => 'tok', 'dealId' => 77, 'dealNumber' => 'D-77',
                ]]],
                ['base' => 'unf_4', 'label' => 'Третьяков', 'ok' => false, 'error' => 'вход в 1С не удался', 'contracts' => []],
            ],
        ];
    }

    public function test_search_logs_in_once_and_hides_service_fields(): void
    {
        $this->fake([
            'POST /auth/login' => [200, ['accessToken' => 'a1', 'refreshToken' => 'r1', 'expiresIn' => 1800]],
            'POST /onec-contracts/searches' => [201, ['id' => 's-1', 'query' => '2296']],
            'GET /onec-contracts/searches/s-1' => [200, $this->crmStatus()],
        ]);
        $op = $this->operator();
        $this->actingAs($op)->get(route('contracts.index'))->assertOk()->assertSee('Номер договора, фамилия, адрес или телефон')->assertSee('Договоры 1С');

        $this->actingAs($op)->postJson(route('contracts.start'), ['query' => ' 2296 '])->assertOk()->assertJson(['id' => 's-1']);
        // тот же запрос через минуту — по уже начатому поиску, в 1С второй раз не идём
        $this->actingAs($op)->postJson(route('contracts.start'), ['query' => '2296'])->assertOk()->assertJson(['id' => 's-1', 'reused' => true]);

        $res = $this->actingAs($op)->getJson(route('contracts.status', ['id' => 's-1']))->assertOk()->json();
        $this->assertSame('done', $res['state']);
        $c = $res['results'][0]['contracts'][0];
        $this->assertSame(['id', 'name', 'customer', 'number', 'date', 'organization', 'amount', 'note', 'isOrg', 'fileCount', 'measurers'], array_keys($c));
        $this->assertSame([['name' => 'Костюнин Федор', 'date' => '2026-09-29', 'visits' => 2, 'via' => 'customer']], $c['measurers']);
        $this->assertSame(1, $c['fileCount']);
        $this->assertFalse($res['results'][1]['ok']);
        $this->assertSame('вход в 1С не удался', $res['results'][1]['error']);

        $this->assertCount(3, $this->sent);   // вход, начало поиска, «что нашлось»
        $this->assertSame(['phone' => 'kc-site', 'password' => 'secret', 'device' => 'kc-site'], $this->sentTo('/auth/login')[0]['json']);
        $this->assertSame(['query' => '2296'], $this->sentTo('/onec-contracts/searches')[0]['json']);
        $this->assertSame('Bearer a1', $this->sentTo('/onec-contracts/searches/s-1')[0]['auth']);
        $this->assertSame('a1', json_decode((string) file_get_contents($this->tokenFile), true)['accessToken']);
    }

    public function test_expired_session_is_refreshed_once_and_the_request_repeated(): void
    {
        file_put_contents($this->tokenFile, json_encode(['accessToken' => 'old', 'refreshToken' => 'r-old']));
        $this->fake([
            'POST /auth/refresh' => [200, ['accessToken' => 'a2', 'refreshToken' => 'r2']],
            'GET /onec-contracts/searches/s-1' => [[401, ['message' => 'Unauthorized']], [200, $this->crmStatus('searching')]],
        ]);
        $res = $this->actingAs($this->operator())->getJson(route('contracts.status', ['id' => 's-1']))->assertOk()->json();
        $this->assertSame('searching', $res['state']);
        $this->assertSame([['key' => 'unf_10', 'label' => 'Закиров новая']], $res['waiting']);
        $this->assertSame(['refreshToken' => 'r-old'], $this->sentTo('/auth/refresh')[0]['json']);
        $this->assertSame([], $this->sentTo('/auth/login'));
        $this->assertSame(['Bearer old', 'Bearer a2'], array_column($this->sentTo('/onec-contracts/searches/s-1'), 'auth'));
        $saved = json_decode((string) file_get_contents($this->tokenFile), true);
        $this->assertSame(['a2', 'r2'], [$saved['accessToken'], $saved['refreshToken']]);
    }

    public function test_refresh_rejected_falls_back_to_login(): void
    {
        file_put_contents($this->tokenFile, json_encode(['accessToken' => 'old', 'refreshToken' => 'r-old']));
        $this->fake([
            'POST /auth/refresh' => [401, ['message' => 'Unauthorized']],
            'POST /auth/login' => [200, ['accessToken' => 'a3', 'refreshToken' => 'r3']],
            'POST /onec-contracts/searches' => [[401, []], [201, ['id' => 's-9']]],
        ]);
        $this->actingAs($this->operator())->postJson(route('contracts.start'), ['query' => 'Бобров'])->assertOk()->assertJson(['id' => 's-9']);
        $this->assertSame(['Bearer old', 'Bearer a3'], array_column($this->sentTo('/onec-contracts/searches'), 'auth'));
    }

    public function test_crm_errors_become_operator_messages_and_failed_searches_are_not_reused(): void
    {
        file_put_contents($this->tokenFile, json_encode(['accessToken' => 'a1', 'refreshToken' => 'r1']));
        $this->fake([
            'POST /onec-contracts/searches' => [
                [400, ['message' => 'В строке нет ни одного слова для поиска']],
                [201, ['id' => 's-2']],
                [201, ['id' => 's-3']],
                [403, ['message' => 'Forbidden']],
            ],
            'GET /onec-contracts/searches/s-2' => [200, ['state' => 'agentSilent', 'bases' => [], 'waiting' => [], 'results' => []]],
        ]);
        $op = $this->operator();
        $this->actingAs($op)->postJson(route('contracts.start'), ['query' => '!!'])->assertStatus(422)->assertJson(['message' => 'В строке нет ни одного слова для поиска']);
        $this->actingAs($op)->postJson(route('contracts.start'), ['query' => 'Карла Маркса 124'])->assertOk()->assertJson(['id' => 's-2']);
        // агент 1С молчит — этот поиск больше не отдаём, следующая попытка спрашивает заново
        $this->actingAs($op)->getJson(route('contracts.status', ['id' => 's-2']))->assertOk()->assertJson(['state' => 'agentSilent']);
        $this->actingAs($op)->postJson(route('contracts.start'), ['query' => 'Карла Маркса 124'])->assertOk()->assertJson(['id' => 's-3']);
        $this->actingAs($op)->postJson(route('contracts.start'), ['query' => 'Иванов'])->assertStatus(502)
            ->assertJson(['message' => 'У учётки сайта нет права на поиск договоров — напишите владельцу CRM «БлагоДар».']);
        $this->actingAs($op)->postJson(route('contracts.start'), ['query' => ''])->assertStatus(422);
        $this->actingAs($op)->getJson('/contracts/searches/'.rawurlencode('x y'))->assertNotFound();
    }

    public function test_crm_down_is_a_plain_message(): void
    {
        $this->fake(['POST /auth/login' => [0, []]]);
        $this->actingAs($this->operator())->postJson(route('contracts.start'), ['query' => '2296'])->assertStatus(502)
            ->assertJson(['message' => 'CRM «БлагоДар» сейчас не отвечает — попробуйте позже.']);
    }

    public function test_access_and_not_configured(): void
    {
        $this->fake([]);
        $sneaker = User::where('role', 'sneaker_head')->firstOrFail();
        $this->actingAs($sneaker)->get(route('contracts.index'))->assertForbidden();
        $this->actingAs($sneaker)->postJson(route('contracts.start'), ['query' => '2296'])->assertForbidden();
        $measurer = User::where('role', 'measurer')->first();
        if ($measurer) {
            $this->actingAs($measurer)->get(route('contracts.index'))->assertForbidden();
        }

        config(['blagodar_crm.password' => null]);
        $op = $this->operator();
        $this->actingAs($op)->get(route('contracts.index'))->assertOk()->assertSee('Поиск договоров ещё не подключён');
        $this->actingAs($op)->postJson(route('contracts.start'), ['query' => '2296'])->assertStatus(503);
        $this->assertSame([], $this->sent);
    }

    public function test_deal_card_has_the_contract_button_for_the_call_center(): void
    {
        $op = $this->operator();
        $contact = \App\Models\Contact::create(['account_id' => $op->account_id, 'name' => 'Клиент (тест)', 'phone' => '+7 (900) 123-45-67']);
        $deal = \App\Models\Deal::withoutGlobalScopes()->where('account_id', $op->account_id)->latest('id')->first();
        if (! $deal) {
            $this->markTestSkipped('нет сделок потолков в базе');
        }
        $deal->forceFill(['contact_id' => $contact->id])->save();
        $html = $this->actingAs($op)->get(route('deals.show', $deal))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('~href="[^"]*/contracts#q=79001234567"[^>]*>.*?Договор в 1С~su', $html);
    }
}
