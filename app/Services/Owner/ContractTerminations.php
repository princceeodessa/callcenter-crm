<?php

namespace App\Services\Owner;

use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Расторжения договоров (потолки) — готовые итоги CRM БлагоДар по 1С, свой расчёт не делаем (27.11.2026, владелец:
 * «добавим в CRM потолки расторжение по договорам», брать только автоматически из 1С). Расторгнутый — договор, у которого
 * все заказы в 1С «Отменен» с причиной «Расторжение»; отменённые целиком без этой причины — одним числом (unmarked).
 * По дню расторжения, даты включительно. Ответ в кэше 10 минут, сбой — минуту. Ключ — только в .env (BLAGODAR_TERMINATIONS_TOKEN).
 *
 * GET {url}/api/reports/contract-terminations/summary?from=Y-m-d&to=Y-m-d, Authorization: Bearer {token}
 * → {updated:{onec}, stale:[...], period:{from,to}, totals:{contracts, amount, paid, refunded, kept, unmarked},
 *    by_measurer:[{measurer, contracts, amount}], items:[{base, label, number, contractDate, terminatedAt, dateKnown, amount,
 *    paid, refunded, reason, comment, measurer, url}], url}. Деньги — строки с точкой или null. 401 — нет ключа, 403 — выключено.
 * Что просили у CRM БлагоДар — docs/agent/blagodar-contract-terminations.md, их ответ — …-answer.md (27.11.2026): paid/refunded/
 * kept пока null (возвратов в выгрузке 1С нет) — на плитках «нет данных». Второе задание — …-dates.md, ответ — …-dates-answer.md
 * (28.11.2026): день расторжения — последняя отмена заказа договора по истории версий 1С (dateKnown:true), весь 2026 год,
 * «Отказ» по подписанному договору — тоже расторжение (решение владельца), комментарий заказа не приходит (бывают адреса),
 * url договора — «Договоры в 1С» CRM БлагоДар по номеру.
 */
class ContractTerminations
{
    private const DOWN = 'Расторжения договоров считает CRM БлагоДар по 1С';

    private const OFF = self::DOWN.' — здесь они появятся, когда будет подключена их выдача.';

    /** С этого дня CRM БлагоДар считает расторжения по дням отмены в 1С (срез «отмены договоров», 28.11.2026) — весь 2026 год. */
    public const HISTORY_SINCE = '2026-01-01';

    /**
     * Пока у CRM БлагоДар не выкачено второе задание, их выдача видит только договоры с заказами с 01.08.2026 и датирует их
     * последним заказом — узнаём по ссылке договора на «Прибыль договоров». Тогда месяцы — с 01.08, а не нули с января.
     */
    private const LEGACY_SINCE = '2026-08-01';

    private const LEGACY_URL = '/reports/contract-profit';

    /** Ответ без запроса в БлагоДар: из кэша (или «не настроено»); null — в кэше нет, нужен forPeriod(). */
    public static function cached(Carbon $from, Carbon $toExclusive): ?array
    {
        if (! self::configured()) {
            return ['state' => 'off', 'message' => self::OFF];
        }
        $cached = Cache::get(self::key($from, $toExclusive));

        return is_array($cached) ? $cached : null;
    }

    /** @return array{state: string, message?: string, data?: array} state: off | error | ok */
    public static function forPeriod(Carbon $from, Carbon $toExclusive): array
    {
        if (! self::configured()) {
            return ['state' => 'off', 'message' => self::OFF];
        }
        $key = self::key($from, $toExclusive);
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $cfg = (array) config('owner.terminations');
        $result = self::fetch(rtrim((string) $cfg['url'], '/'), (string) $cfg['token'], $from, $toExclusive->copy()->subDay());
        Cache::put($key, $result, $result['state'] === 'ok' ? 600 : 60);

        return $result;
    }

    /**
     * По каждому месяцу с HISTORY_SINCE до текущего, новые сверху (28.11.2026, «надо отдельно по каждому месяцу») — от выбранного
     * периода не зависит. Каждый месяц — свой запрос (CRM БлагоДар отвечает за ~60 мс) и свой кэш: так видно и «по другой причине»
     * помесячно, и договоры каждого месяца. $fetch=false — только из кэша; null — какого-то месяца в кэше нет (блок тогда
     * догружается отдельно). Последний месяц списка — с него расторжения видны.
     *
     * @return list<array{key: string, from: Carbon, to: Carbon, ok: bool, contracts: ?int, amount: ?float, unmarked: ?int, refusals: int,
     *     approx: bool, items: list<array>, stale: list<string>, updated: ?Carbon, message: ?string}>|null
     */
    public static function months(bool $fetch = true): ?array
    {
        $rows = [];
        $first = Carbon::parse(self::HISTORY_SINCE)->startOfMonth();
        for ($m = now()->startOfMonth(); $m->gte($first); $m = $m->copy()->subMonthNoOverflow()) {
            $end = $m->copy()->addMonthNoOverflow();
            $r = $fetch ? self::forPeriod($m, $end) : self::cached($m, $end);
            if ($r === null) {
                return null;
            }
            $ok = $r['state'] === 'ok';
            $t = $ok ? $r['data']['totals'] : null;
            $items = $ok ? $r['data']['items'] : [];
            $rows[] = [
                'key' => $m->format('Y-m'), 'from' => $m->copy(), 'to' => $end, 'ok' => $ok,
                'contracts' => $t['contracts'] ?? null, 'amount' => $t['amount'] ?? null, 'unmarked' => $t['unmarked'] ?? null,
                'refusals' => collect($items)->where('kind', 'Отказ')->count(),
                // день расторжения примерный (в 1С нет версии заказа — день последнего заказа договора)
                'approx' => collect($items)->contains(fn ($i) => ! $i['date_known']),
                'items' => $items,
                'stale' => $ok ? $r['data']['stale'] : [],
                'updated' => $ok ? $r['data']['updated'] : null,
                'message' => $ok ? null : $r['message'],
            ];
        }
        $legacy = collect($rows)->flatMap(fn ($r) => $r['items'])->contains(fn ($i) => str_contains((string) $i['url'], self::LEGACY_URL));

        return $legacy ? array_values(array_filter($rows, fn ($r) => $r['from']->gte(Carbon::parse(self::LEGACY_SINCE)))) : $rows;
    }

    /**
     * Весь год для страницы «Расторжения подробно» (28.11.2026, «желательно с возможностью прям подробно показать»): месяцы,
     * все договоры (у каждого — его месяц), итоги и разрезы по замерщикам (с месяцами), причинам и базам 1С. null — нет месяца в кэше.
     */
    public static function year(bool $fetch = true): ?array
    {
        $months = self::months($fetch);
        if ($months === null) {
            return null;
        }
        $ok = collect($months)->where('ok', true);
        $items = $ok->flatMap(fn ($m) => array_map(fn ($i) => $i + ['month' => $m['key']], $m['items']))
            ->sortByDesc(fn ($i) => $i['terminated_at']?->timestamp ?? 0)->values();
        $group = fn (string $field) => $items->groupBy(fn ($i) => $i[$field] !== '' ? $i[$field] : '—')
            ->map(fn ($g, $name) => ['name' => (string) $name, 'contracts' => $g->count(), 'amount' => (float) $g->sum('amount'), 'months' => $g->countBy('month')->all()])
            ->sortByDesc(fn ($r) => [$r['contracts'], $r['amount']])->values()->all();
        $days = $items->pluck('days')->filter(fn ($d) => $d !== null)->values();

        return [
            'months' => $months,
            'since' => $months ? end($months)['from'] : Carbon::parse(self::HISTORY_SINCE),
            'items' => $items->all(),
            'totals' => [
                'contracts' => (int) $ok->sum('contracts'), 'amount' => (float) $ok->sum('amount'), 'unmarked' => (int) $ok->sum('unmarked'),
                'refusals' => $items->where('kind', 'Отказ')->count(),
                // обычно через столько дней после договора его расторгают (медиана)
                'days' => $days->isEmpty() ? null : (int) round($days->median()),
            ],
            'by_measurer' => $group('measurer'),
            'by_kind' => $group('kind'),
            'by_base' => $group('label'),
            'failed' => collect($months)->where('ok', false)->count(),
            'message' => collect($months)->firstWhere('ok', false)['message'] ?? null,
            'stale' => $ok->flatMap(fn ($m) => $m['stale'])->unique()->values()->all(),
            'updated' => $ok->pluck('updated')->filter()->sortByDesc(fn ($d) => $d->timestamp)->first(),
        ];
    }

    private static function configured(): bool
    {
        $cfg = (array) config('owner.terminations');

        return ! empty($cfg['url']) && ! empty($cfg['token']);
    }

    private static function key(Carbon $from, Carbon $toExclusive): string
    {
        return 'owner.terminations.'.$from->toDateString().'.'.$toExclusive->copy()->subDay()->toDateString();
    }

    private static function fetch(string $base, string $token, Carbon $from, Carbon $to): array
    {
        try {
            [$status, $text] = Http::request('GET', $base.'/api/reports/contract-terminations/summary?'.http_build_query([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]), ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'], null, 20);
        } catch (\Throwable $e) {
            Log::warning('owner contract terminations: no answer', ['error' => class_basename($e)]);

            return ['state' => 'error', 'message' => self::DOWN.'; сейчас она не ответила — попробуем ещё раз через минуту.'];
        }
        if ($status === 401 || $status === 403) {
            return ['state' => 'error', 'message' => self::DOWN.'; '.($status === 403 ? 'их выдача сейчас выключена.' : 'доступ к ним не принят — нужен новый ключ.')];
        }
        $data = $status === 200 ? Http::json($text) : [];
        $t = $data['totals'] ?? null;
        if ($status !== 200 || ! is_array($t) || ! isset($t['contracts']) || ! is_array($data['items'] ?? null)) {
            return ['state' => 'error', 'message' => self::DOWN.'; сейчас данные не пришли (ответ '.$status.').'];
        }

        $money = fn ($v) => is_numeric($v) ? (float) $v : null;
        $str = fn ($v, int $max = 300) => mb_substr(trim(is_scalar($v) ? (string) $v : ''), 0, $max);
        $day = function ($v) {
            try {
                return is_string($v) && $v !== '' ? Carbon::parse($v)->startOfDay() : null;
            } catch (\Throwable) {
                return null;
            }
        };
        $link = fn ($v) => is_string($v) && str_starts_with($v, 'https://') ? $v : null;

        $items = collect($data['items'])->filter(fn ($i) => is_array($i))->map(function (array $i) use ($str, $day, $money, $link) {
            $signed = $day($i['contractDate'] ?? null);
            $at = $day($i['terminatedAt'] ?? null);
            $known = (bool) ($i['dateKnown'] ?? true);
            // как написано в 1С: в «Закиров новая» — «расторжение» со строчной
            $reason = ($r = $str($i['reason'] ?? '', 120)) === '' ? '' : mb_strtoupper(mb_substr($r, 0, 1)).mb_substr($r, 1);

            // комментарий заказа не показываем (в нём бывают адреса) — CRM БлагоДар с 28.11.2026 его и не отдаёт
            return [
                'label' => $str($i['label'] ?? '') ?: $str($i['base'] ?? '', 40),
                'number' => $str($i['number'] ?? '', 40),
                'contract_date' => $signed,
                'terminated_at' => $at,
                'date_known' => $known,
                // через сколько дней после договора расторгли; день примерный (≈ день последнего заказа) — не считаем
                'days' => $known && $signed && $at && $at->gte($signed) ? (int) $signed->diffInDays($at) : null,
                'amount' => $money($i['amount'] ?? null),
                'paid' => $money($i['paid'] ?? null),
                'refunded' => $money($i['refunded'] ?? null),
                'reason' => $reason,
                // «Отказ» по подписанному договору — тоже расторжение (владелец 28.11.2026); остальное — «Расторжение»
                'kind' => preg_match('/отказ/iu', $reason) ? 'Отказ' : 'Расторжение',
                'measurer' => $str($i['measurer'] ?? '', 120),
                'url' => $link($i['url'] ?? null),
            ];
        })
            // свежие расторжения первыми; без дня — в конце
            ->sortByDesc(fn ($i) => $i['terminated_at']?->timestamp ?? 0)->values()->all();

        $updated = null;
        try {
            $updated = ! empty($data['updated']['onec']) ? Carbon::parse((string) $data['updated']['onec'])->setTimezone(config('app.timezone')) : null;
        } catch (\Throwable) {
        }
        $stale = [];
        foreach ((array) ($data['stale'] ?? []) as $s) {
            $line = is_string($s) ? $s : (string) ($s['text'] ?? $s['message'] ?? '');
            if (trim($line) !== '') {
                $stale[] = trim($line);
            }
        }

        return ['state' => 'ok', 'data' => [
            'totals' => [
                'contracts' => (int) $t['contracts'],
                'amount' => $money($t['amount'] ?? null),
                'paid' => $money($t['paid'] ?? null),
                'refunded' => $money($t['refunded'] ?? null),
                'kept' => $money($t['kept'] ?? null),
                'unmarked' => (int) ($t['unmarked'] ?? 0),
            ],
            // замерщики — от большего числа расторжений к меньшему
            'by_measurer' => collect((array) ($data['by_measurer'] ?? []))->filter(fn ($m) => is_array($m))
                ->map(fn (array $m) => ['measurer' => $str($m['measurer'] ?? '', 120) ?: '—', 'contracts' => (int) ($m['contracts'] ?? 0), 'amount' => $money($m['amount'] ?? null)])
                ->filter(fn ($m) => $m['contracts'] > 0)
                ->sortByDesc(fn ($m) => [$m['contracts'], $m['amount'] ?? 0])->values()->all(),
            'items' => $items,
            'updated' => $updated,
            'stale' => $stale,
            'url' => $link($data['url'] ?? null),
        ]];
    }
}
