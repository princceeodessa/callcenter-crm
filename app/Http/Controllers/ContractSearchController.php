<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\Blagodar\ContractSearchClient;
use App\Services\Blagodar\CrmApiException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * «Договоры 1С» колл-центра (26.11.2026): одна строка поиска — номер договора, фамилия, адрес или телефон; ищет сразу
 * во всех базах 1С через CRM «БлагоДар». У договора: база (ИП), номер, дата, сумма, заказчик, кто замерял, есть ли скан
 * (без самих сканов — в них паспорта, их открывает отдел документов). Браузер ходит только сюда, к CRM «БлагоДар» —
 * сервер (ContractSearchClient); токенов и пароля браузер не видит. Ответы не храним и в журнал не пишем.
 */
class ContractSearchController extends Controller
{
    /** Поля договора, что нужны экрану: служебное CRM (customerCode, token, dealId…) в браузер не отдаём. */
    private const CONTRACT_FIELDS = ['id', 'name', 'customer', 'number', 'date', 'organization', 'amount', 'note', 'isOrg', 'fileCount'];

    private const FINAL_FAILURES = ['stale', 'agentSilent'];

    /** Колл-центр потолков и документы (как «Документы»): кроссовкам, замерщикам и проектировщикам — нет. */
    public static function allowed(?User $user): bool
    {
        return $user !== null && in_array($user->role, ['admin', 'main_operator', 'operator', 'documents_operator'], true);
    }

    public function index()
    {
        return view('contracts.index', ['enabled' => ContractSearchClient::enabled()]);
    }

    public function start(Request $request, ContractSearchClient $client): JsonResponse
    {
        $data = $request->validate(['query' => ['required', 'string', 'max:200']], [
            'query.required' => 'Введите номер договора, фамилию, адрес или телефон.',
            'query.max' => 'Слишком длинный запрос — не больше 200 знаков.',
        ]);
        if (! ContractSearchClient::enabled()) {
            return response()->json(['message' => 'Поиск договоров ещё не подключён: нет учётки CRM «БлагоДар».'], 503);
        }
        $query = trim($data['query']);

        // тот же запрос несколько минут — по уже начатому поиску: каждый поиск открывает сеансы во всех базах 1С
        $key = 'kc_contract_search:'.sha1(mb_strtolower((string) preg_replace('/\s+/u', ' ', $query)));
        if ($id = Cache::get($key)) {
            return response()->json(['id' => $id, 'reused' => true]);
        }
        try {
            $res = $client->start($query);
        } catch (CrmApiException $e) {
            return $this->fail($e);
        }
        $id = (string) ($res['id'] ?? '');
        if ($id === '') {
            return response()->json(['message' => 'CRM «БлагоДар» не начала поиск — попробуйте ещё раз.'], 502);
        }
        Cache::put($key, $id, (int) config('blagodar_crm.reuse_seconds', 180));
        Cache::put('kc_contract_search_id:'.$id, $key, (int) config('blagodar_crm.reuse_seconds', 180));

        return response()->json(['id' => $id, 'query' => (string) ($res['query'] ?? $query)]);
    }

    public function status(string $id, ContractSearchClient $client): JsonResponse
    {
        abort_unless((bool) preg_match('/^[A-Za-z0-9_-]{1,64}$/', $id), 404);
        if (! ContractSearchClient::enabled()) {
            return response()->json(['message' => 'Поиск договоров ещё не подключён: нет учётки CRM «БлагоДар».'], 503);
        }
        try {
            $res = $client->status($id);
        } catch (CrmApiException $e) {
            return $this->fail($e);
        }
        $state = (string) ($res['state'] ?? '');
        // базы не ответили или агент 1С молчит — такой поиск повторно не отдаём, следующая попытка спросит заново
        if (in_array($state, self::FINAL_FAILURES, true) && ($key = Cache::pull('kc_contract_search_id:'.$id))) {
            Cache::forget($key);
        }
        $base = fn ($b) => ['key' => (string) ($b['key'] ?? ''), 'label' => (string) ($b['label'] ?? $b['key'] ?? '')];

        return response()->json([
            'id' => $id,
            'query' => (string) ($res['query'] ?? ''),
            'state' => $state,
            'bases' => array_map($base, (array) ($res['bases'] ?? [])),
            'waiting' => array_map($base, (array) ($res['waiting'] ?? [])),
            'results' => array_map(fn ($r) => [
                'base' => (string) ($r['base'] ?? ''),
                'label' => (string) ($r['label'] ?? $r['base'] ?? ''),
                'ok' => (bool) ($r['ok'] ?? false),
                'error' => isset($r['error']) && $r['error'] !== '' ? (string) $r['error'] : null,
                'contracts' => array_map(fn ($c) => array_intersect_key((array) $c, array_flip(self::CONTRACT_FIELDS)) + [
                    'measurers' => array_map(fn ($m) => [
                        'name' => (string) ($m['name'] ?? ''),
                        'date' => isset($m['date']) ? (string) $m['date'] : null,
                        'visits' => (int) ($m['visits'] ?? 1),
                        'via' => (string) ($m['via'] ?? 'contract'),
                    ], array_slice((array) ($c['measurers'] ?? []), 0, 3)),
                ], (array) ($r['contracts'] ?? [])),
            ], (array) ($res['results'] ?? [])),
        ]);
    }

    /** Ответ CRM — в текст для оператора. */
    private function fail(CrmApiException $e): JsonResponse
    {
        $crm = trim($e->getMessage());

        return match (true) {
            $e->status === 400 => response()->json(['message' => $crm !== '' ? $crm : 'В запросе нет ни одного слова для поиска.'], 422),
            $e->status === 401 => response()->json(['message' => 'Вход в CRM «БлагоДар» не удался — проверьте логин и пароль учётки сайта.'], 502),
            $e->status === 403 => response()->json(['message' => 'У учётки сайта нет права на поиск договоров — напишите владельцу CRM «БлагоДар».'], 502),
            $e->status === 404 => response()->json(['message' => 'Поиск не найден — начните заново.'], 404),
            $e->status === 429 => response()->json(['message' => 'Слишком много запросов к CRM «БлагоДар» — подождите минуту.'], 429),
            default => response()->json(['message' => 'CRM «БлагоДар» сейчас не отвечает — попробуйте позже.'], 502),
        };
    }
}
