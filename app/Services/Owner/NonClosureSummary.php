<?php

namespace App\Services\Owner;

use App\Services\Owner\Marketing\Http;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

/**
 * Незаключённые договоры по замерщикам — готовая сводка из CRM БлагоДар (там отчёт сверяет таблицу КЦ с 1С).
 * Только цифры по замерщикам, без адресов и телефонов. Запрос на период сводки, ответ кэшируется на 10 минут.
 *
 * GET {url}/api/reports/nonclosures/summary?from=Y-m-d&to=Y-m-d, Authorization: Bearer {token}
 * → {updated:{kc_sheet, onec}, stale:[...], period:{from,to}, blocks:[{key,title,rows:[{measurer,measurements,
 *    not_concluded}], total:{measurements,not_concluded}}], discrepancies, url}
 */
class NonClosureSummary
{
    /** @return array{state: string, message?: string, data?: array} state: off | error | ok */
    public static function forPeriod(Carbon $from, Carbon $toExclusive): array
    {
        $cfg = (array) config('owner.nonclosures');
        if (empty($cfg['url']) || empty($cfg['token'])) {
            return ['state' => 'off', 'message' => 'Отчёт строится в CRM БлагоДар и появится здесь, когда там будет готова его выдача.'];
        }
        $to = $toExclusive->copy()->subDay();
        $key = 'owner.nonclosures.'.$from->toDateString().'.'.$to->toDateString();

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        try {
            [$status, $text] = Http::request('GET', rtrim($cfg['url'], '/').'/api/reports/nonclosures/summary?'.http_build_query([
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
            ]), ['Authorization' => 'Bearer '.$cfg['token'], 'Accept' => 'application/json'], null, 8);
        } catch (\Throwable $e) {
            return ['state' => 'error', 'message' => 'CRM БлагоДар не ответила: '.$e->getMessage()];
        }
        if ($status !== 200) {
            return ['state' => 'error', 'message' => 'CRM БлагоДар ответила HTTP '.$status];
        }
        $data = Http::json($text);
        if (! isset($data['blocks']) || ! is_array($data['blocks'])) {
            return ['state' => 'error', 'message' => 'CRM БлагоДар прислала сводку не в том виде'];
        }

        $blocks = [];
        foreach ($data['blocks'] as $b) {
            $rows = [];
            foreach ((array) ($b['rows'] ?? []) as $r) {
                $m = (int) ($r['measurements'] ?? 0);
                $nc = (int) ($r['not_concluded'] ?? 0);
                $rows[] = ['measurer' => (string) ($r['measurer'] ?? '—'), 'measurements' => $m, 'not_concluded' => $nc, 'percent' => $m > 0 ? $nc / $m * 100 : null];
            }
            $tm = (int) ($b['total']['measurements'] ?? array_sum(array_column($rows, 'measurements')));
            $tn = (int) ($b['total']['not_concluded'] ?? array_sum(array_column($rows, 'not_concluded')));
            $blocks[] = [
                'key' => (string) ($b['key'] ?? ''),
                'title' => (string) ($b['title'] ?? ''),
                'rows' => $rows,
                'total' => ['measurements' => $tm, 'not_concluded' => $tn, 'percent' => $tm > 0 ? $tn / $tm * 100 : null],
            ];
        }
        $updated = [];
        foreach ((array) ($data['updated'] ?? []) as $k => $v) {
            try {
                $updated[$k] = Carbon::parse((string) $v)->setTimezone(config('app.timezone'));
            } catch (\Throwable) {
            }
        }
        $result = ['state' => 'ok', 'data' => [
            'blocks' => $blocks,
            'updated' => $updated,
            'stale' => array_values(array_map('strval', (array) ($data['stale'] ?? []))),
            'discrepancies' => isset($data['discrepancies']) ? (int) $data['discrepancies'] : null,
            'url' => is_string($data['url'] ?? null) && str_starts_with($data['url'], 'https://') ? $data['url'] : null,
            'period' => $data['period'] ?? null,
        ]];
        Cache::put($key, $result, 600);

        return $result;
    }
}
