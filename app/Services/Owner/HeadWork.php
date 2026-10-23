<?php

namespace App\Services\Owner;

use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * «Работа главного по замерщикам» (потолки) — готовые итоги CRM БлагоДар, свой расчёт не делаем. По дню звонка,
 * даты включительно. Ответ в кэше 10 минут, сбой — минуту. Токен — только в .env сервера (BLAGODAR_HEAD_WORK_TOKEN).
 * Договор считается только по 1С: «заключили» без договора в 1С — ожидание, не результат.
 *
 * GET {url}/api/reports/head-work/summary?from=Y-m-d&to=Y-m-d, Authorization: Bearer {token}
 * → {updated:{kc_sheet, onec}, stale:[...], period:{from,to}, people:[{user, calls:{total, customers, no_answer, call_back,
 *    agreed, refused, won}, contracts, contracts_sum, outcomes:{contract, claimed, agreed, table, later, call_again, refused,
 *    gone, before}, reasons:[{reason, n}], days:[{day, calls, customers}], onec:{done, pending, failed},
 *    visits:{visits, won, lost, done, cancelled, open}}], url}. 401 — нет ключа или неверный, 403 — выдача выключена.
 */
class HeadWork
{
    /** Где сейчас заказчики, которым он звонил — подписи словами (порядок — как показывать). */
    public const OUTCOMES = [
        'contract' => 'договор в 1С',
        'claimed' => 'отмечено «заключили», в 1С договора ещё нет',
        'agreed' => 'договорились, ждём договор',
        'table' => 'КЦ отметил «заключен»',
        'later' => 'перезвонить позже',
        'call_again' => 'позвонить снова',
        'refused' => 'отказались',
        'gone' => 'ушёл из списка, договора в 1С нет',
        'before' => 'договор был до звонка (в итог не входит)',
    ];

    /** Итоги звонков за срок. */
    public const CALLS = [
        'no_answer' => 'не дозвонился',
        'call_back' => 'перезвонить',
        'agreed' => 'договорились',
        'refused' => 'отказ',
        'won' => 'заключили',
    ];

    private const DOWN = 'Работу главного по замерщикам считает CRM БлагоДар';

    private const OFF = self::DOWN.' — здесь она появится, когда будет подключена их выдача.';

    /** Ответ без запроса в БлагоДар: из кэша (или «не настроено»); null — в кэше нет, нужен forPeriod(). */
    public static function cached(Carbon $from, Carbon $toExclusive): ?array
    {
        $cfg = (array) config('owner.head_work');
        if (empty($cfg['url']) || empty($cfg['token'])) {
            return ['state' => 'off', 'message' => self::OFF];
        }
        $cached = Cache::get(self::key($from, $toExclusive));

        return is_array($cached) ? $cached : null;
    }

    /** @return array{state: string, message?: string, data?: array} state: off | error | ok */
    public static function forPeriod(Carbon $from, Carbon $toExclusive): array
    {
        $cfg = (array) config('owner.head_work');
        if (empty($cfg['url']) || empty($cfg['token'])) {
            return ['state' => 'off', 'message' => self::OFF];
        }
        $key = self::key($from, $toExclusive);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = self::fetch(rtrim((string) $cfg['url'], '/'), (string) $cfg['token'], $from, $toExclusive->copy()->subDay());
        Cache::put($key, $result, $result['state'] === 'ok' ? 600 : 60);

        return $result;
    }

    private static function key(Carbon $from, Carbon $toExclusive): string
    {
        return 'owner.head_work.'.$from->toDateString().'.'.$toExclusive->copy()->subDay()->toDateString();
    }

    private static function fetch(string $base, string $token, Carbon $from, Carbon $to): array
    {
        try {
            [$status, $text] = Http::request('GET', $base.'/api/reports/head-work/summary?'.http_build_query([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]), ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'], null, 20);
        } catch (\Throwable $e) {
            Log::warning('owner head work: no answer', ['error' => class_basename($e)]);

            return ['state' => 'error', 'message' => self::DOWN.'; сейчас она не ответила — попробуем ещё раз через минуту.'];
        }
        if ($status === 401 || $status === 403) {
            return ['state' => 'error', 'message' => self::DOWN.'; '.($status === 403 ? 'их выдача сейчас выключена.' : 'доступ к ним не принят — нужен новый ключ.')];
        }
        $data = $status === 200 ? Http::json($text) : [];
        if ($status !== 200 || ! isset($data['people']) || ! is_array($data['people'])) {
            return ['state' => 'error', 'message' => self::DOWN.'; сейчас данные не пришли (ответ '.$status.').'];
        }

        $int = fn (array $a, array $keys) => array_combine($keys, array_map(fn ($k) => (int) ($a[$k] ?? 0), $keys));
        $people = collect($data['people'])->filter(fn ($p) => is_array($p))->map(function (array $p) use ($int) {
            $o = $int((array) ($p['outcomes'] ?? []), array_keys(self::OUTCOMES));

            return [
                'user' => trim((string) ($p['user'] ?? '')) ?: '—',
                'calls' => $int((array) ($p['calls'] ?? []), ['total', 'customers', 'no_answer', 'call_back', 'agreed', 'refused', 'won']),
                'contracts' => (int) ($p['contracts'] ?? 0),
                'contracts_sum' => is_numeric($p['contracts_sum'] ?? null) ? (float) $p['contracts_sum'] : null,
                'outcomes' => $o,
                // три понятные кучки: ждём договор (заключили / договорились / КЦ отметил, а в 1С нет), перезвонить, отказ
                'waiting' => $o['claimed'] + $o['agreed'] + $o['table'],
                'recall' => $o['later'] + $o['call_again'],
                'reasons' => collect((array) ($p['reasons'] ?? []))->filter(fn ($r) => is_array($r) && trim((string) ($r['reason'] ?? '')) !== '')
                    ->map(fn ($r) => ['reason' => trim((string) $r['reason']), 'n' => (int) ($r['n'] ?? 0)])->sortByDesc('n')->values()->all(),
                'days' => collect((array) ($p['days'] ?? []))->filter(fn ($d) => is_array($d) && ! empty($d['day']))
                    ->map(fn ($d) => ['day' => (string) $d['day'], 'calls' => (int) ($d['calls'] ?? 0), 'customers' => (int) ($d['customers'] ?? 0)])
                    ->sortByDesc('day')->values()->all(),
                'onec' => $int((array) ($p['onec'] ?? []), ['done', 'pending', 'failed']),
                'visits' => $int((array) ($p['visits'] ?? []), ['visits', 'won', 'lost', 'done', 'cancelled', 'open']),
            ];
        })
            // больше договоров в 1С — выше, дальше — кто больше обзвонил
            ->sortByDesc(fn ($p) => [$p['contracts'], $p['calls']['customers']])->values()->all();

        $updated = [];
        foreach (['kc_sheet' => 'таблица КЦ', 'onec' => '1С'] as $k => $label) {
            try {
                if (! empty($data['updated'][$k])) {
                    $updated[$label] = Carbon::parse((string) $data['updated'][$k])->setTimezone(config('app.timezone'));
                }
            } catch (\Throwable) {
            }
        }
        $stale = [];
        foreach ((array) ($data['stale'] ?? []) as $s) {
            $line = is_string($s) ? $s : (string) ($s['text'] ?? $s['message'] ?? '');
            if (trim($line) !== '') {
                $stale[] = trim($line);
            }
        }

        return ['state' => 'ok', 'data' => [
            'people' => $people,
            'updated' => $updated,
            'stale' => $stale,
            'url' => is_string($data['url'] ?? null) && str_starts_with($data['url'], 'https://') ? $data['url'] : null,
        ]];
    }
}
