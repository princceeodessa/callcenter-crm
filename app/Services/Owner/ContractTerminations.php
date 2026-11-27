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
 * Что просили у CRM БлагоДар — docs/agent/blagodar-contract-terminations.md.
 */
class ContractTerminations
{
    private const DOWN = 'Расторжения договоров считает CRM БлагоДар по 1С';

    private const OFF = self::DOWN.' — здесь они появятся, когда будет подключена их выдача.';

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

        $items = collect($data['items'])->filter(fn ($i) => is_array($i))->map(fn (array $i) => [
            'label' => $str($i['label'] ?? '') ?: $str($i['base'] ?? '', 40),
            'number' => $str($i['number'] ?? '', 40),
            'contract_date' => $day($i['contractDate'] ?? null),
            'terminated_at' => $day($i['terminatedAt'] ?? null),
            'date_known' => (bool) ($i['dateKnown'] ?? true),
            'amount' => $money($i['amount'] ?? null),
            'paid' => $money($i['paid'] ?? null),
            'refunded' => $money($i['refunded'] ?? null),
            'reason' => $str($i['reason'] ?? '', 120),
            'comment' => $str($i['comment'] ?? '', 200),
            'measurer' => $str($i['measurer'] ?? '', 120),
            'url' => $link($i['url'] ?? null),
        ])
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
