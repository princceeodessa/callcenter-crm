<?php

namespace App\Services\Owner;

use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Прибыль договоров (потолки) — готовые итоги из CRM БлагоДар, свой расчёт не делаем. По дню договора, даты включительно.
 * Ответ держим в кэше 10 минут; сбой — минуту, чтобы автообновление сводки не ждало БлагоДар каждые 30 с.
 * Токен — только в .env сервера (BLAGODAR_PROFIT_TOKEN), в журнал и в ответы не попадает.
 *
 * GET {url}/api/reports/contract-profit/summary?from=Y-m-d&to=Y-m-d, Authorization: Bearer {token}
 * → {updated:{onec}, stale:[...], period:{from,to}, totals:{contracts, incomplete, revenue, costs, profit, margin_pct,
 *    costs_by_kind:{installers, materials, canvas, goods, departments, vat, usn, acquiring}, departments:[{key,title,sum}]},
 *    by_month:[{month,title,contracts,incomplete,revenue,profit,margin_pct}], by_measurer:[{measurer,…}], url}
 *    Деньги — строки с точкой, проценты — числа или null. 401 — нет токена или неверный, 403 — выдача выключена.
 */
class ContractProfit
{
    /** Куда уходят деньги — подписи и цвета статей расходов. */
    public const KINDS = [
        'installers' => ['монтажники', 'var(--accent)'],
        'materials' => ['материалы', 'var(--blue)'],
        'canvas' => ['полотно', 'var(--violet)'],
        'goods' => ['товары', '#f0a26c'],
        'departments' => ['отделы', '#94a3b8'],
        'vat' => ['НДС', '#f0b84c'],
        'usn' => ['УСН', '#e0607a'],
        'acquiring' => ['эквайринг', '#36c3c3'],
    ];

    private const DOWN = 'Итоги по прибыли договоров строятся в CRM БлагоДар';

    /** Ответ без запроса в БлагоДар: из кэша (или «не настроено»); null — в кэше нет, нужен forPeriod(). */
    public static function cached(Carbon $from, Carbon $toExclusive): ?array
    {
        $cfg = (array) config('owner.contract_profit');
        if (empty($cfg['url']) || empty($cfg['token'])) {
            return ['state' => 'off', 'message' => self::DOWN.' — здесь они появятся, когда будет подключена их выдача.'];
        }
        $cached = Cache::get('owner.contract_profit.'.$from->toDateString().'.'.$toExclusive->copy()->subDay()->toDateString());

        return is_array($cached) ? $cached : null;
    }

    /** @return array{state: string, message?: string, data?: array} state: off | error | ok */
    public static function forPeriod(Carbon $from, Carbon $toExclusive): array
    {
        $cfg = (array) config('owner.contract_profit');
        if (empty($cfg['url']) || empty($cfg['token'])) {
            return ['state' => 'off', 'message' => self::DOWN.' — здесь они появятся, когда будет подключена их выдача.'];
        }
        $to = $toExclusive->copy()->subDay();
        $key = 'owner.contract_profit.'.$from->toDateString().'.'.$to->toDateString();
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        $result = self::fetch(rtrim((string) $cfg['url'], '/'), (string) $cfg['token'], $from, $to);
        Cache::put($key, $result, $result['state'] === 'ok' ? 600 : 60);

        return $result;
    }

    /** Маржа словом: от 30% — хорошо, от 15% — средне, ниже — мало, меньше нуля — убыток. @return array{0: string, 1: string} [слово, цвет метки] */
    public static function verdict(?float $margin): array
    {
        return match (true) {
            $margin === null => ['', 'gray'],
            $margin < 0 => ['убыток', 'red'],
            $margin >= 30 => ['хорошо', 'green'],
            $margin >= 15 => ['средне', 'amber'],
            default => ['мало', 'red'],
        };
    }

    private static function fetch(string $base, string $token, Carbon $from, Carbon $to): array
    {
        try {
            [$status, $text] = Http::request('GET', $base.'/api/reports/contract-profit/summary?'.http_build_query([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]), ['Authorization' => 'Bearer '.$token, 'Accept' => 'application/json'], null, 10);
        } catch (\Throwable $e) {
            Log::warning('owner contract profit: no answer', ['error' => class_basename($e)]);

            return ['state' => 'error', 'message' => self::DOWN.'; сейчас она не ответила — попробуем ещё раз через минуту.'];
        }
        if ($status === 401 || $status === 403) {
            return ['state' => 'error', 'message' => self::DOWN.'; '.($status === 403 ? 'их выдача сейчас выключена.' : 'доступ к ним не принят — нужен новый ключ.')];
        }
        $data = $status === 200 ? Http::json($text) : [];
        $t = $data['totals'] ?? null;
        if ($status !== 200 || ! is_array($t) || ! isset($t['revenue'], $t['profit'])) {
            return ['state' => 'error', 'message' => self::DOWN.'; сейчас они не пришли (ответ '.$status.').'];
        }

        $money = fn ($v) => $v === null || $v === '' ? null : (float) $v;
        $pct = fn ($v) => is_numeric($v) ? (float) $v : null;
        $row = fn (array $r) => [
            'contracts' => (int) ($r['contracts'] ?? 0),
            'incomplete' => (int) ($r['incomplete'] ?? 0),
            'revenue' => $money($r['revenue'] ?? null),
            'profit' => $money($r['profit'] ?? null),
            'margin' => $pct($r['margin_pct'] ?? null),
        ];
        $kinds = [];
        foreach (self::KINDS as $k => [$label, $color]) {
            $v = $money($t['costs_by_kind'][$k] ?? null);
            if ($v !== null && abs($v) >= 0.5) {
                $kinds[] = ['key' => $k, 'label' => $label, 'color' => $color, 'sum' => $v];
            }
        }
        $updated = null;
        try {
            $updated = isset($data['updated']['onec']) ? Carbon::parse((string) $data['updated']['onec'])->setTimezone(config('app.timezone')) : null;
        } catch (\Throwable) {
        }
        $stale = [];
        foreach ((array) ($data['stale'] ?? []) as $s) {
            $text = is_string($s) ? $s : (string) ($s['text'] ?? $s['message'] ?? $s['title'] ?? '');
            if (trim($text) !== '') {
                $stale[] = trim($text);
            }
        }

        return ['state' => 'ok', 'data' => [
            'totals' => $row($t) + ['costs' => $money($t['costs'] ?? null)],
            'kinds' => $kinds,
            'departments' => array_values(array_filter(array_map(fn ($d) => ['title' => (string) ($d['title'] ?? ''), 'sum' => $money($d['sum'] ?? null)],
                (array) ($t['departments'] ?? [])), fn ($d) => $d['title'] !== '' && $d['sum'] !== null && abs($d['sum']) >= 0.5)),
            'by_month' => array_map(fn ($m) => ['title' => (string) ($m['title'] ?? $m['month'] ?? '')] + $row((array) $m), (array) ($data['by_month'] ?? [])),
            'by_measurer' => array_map(fn ($m) => ['measurer' => (string) ($m['measurer'] ?? '—')] + $row((array) $m), (array) ($data['by_measurer'] ?? [])),
            'updated' => $updated,
            'stale' => $stale,
            'url' => is_string($data['url'] ?? null) && str_starts_with($data['url'], 'https://') ? $data['url'] : null,
        ]];
    }
}
