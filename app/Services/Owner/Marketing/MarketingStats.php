<?php

namespace App\Services\Owner\Marketing;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Реклама потолков за период [from, to) из owner_marketing_daily — для сводки владельца.
 * Расход по группам каналов CRM: Директ и VK — из их API (если источник собирается), иначе из таблицы заявок;
 * Авито — только из таблицы заявок (API статистики Авито расходов не даёт).
 */
class MarketingStats
{
    public static function forPeriod(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('owner_marketing_daily')
            ->where('day', '>=', $from->toDateString())
            ->where('day', '<', $to->toDateString())
            ->get(['source', 'day', 'metrics']);

        $sum = ['direct' => [], 'vk' => [], 'avito' => [], 'sheet' => ['total' => 0, 'sources' => [], 'spend' => []]];
        $days = ['direct' => 0, 'vk' => 0, 'avito' => 0, 'sheet' => 0];
        foreach ($rows as $r) {
            $m = json_decode((string) $r->metrics, true) ?: [];
            $days[$r->source] = ($days[$r->source] ?? 0) + 1;
            if ($r->source === 'sheet') {
                $sum['sheet']['total'] += (float) ($m['total'] ?? 0);
                foreach ((array) ($m['sources'] ?? []) as $k => $v) {
                    $sum['sheet']['sources'][$k] = ($sum['sheet']['sources'][$k] ?? 0) + (float) $v;
                }
                foreach ((array) ($m['spend'] ?? []) as $k => $v) {
                    $sum['sheet']['spend'][$k] = ($sum['sheet']['spend'][$k] ?? 0) + (float) $v;
                }
                continue;
            }
            foreach ($m as $k => $v) {
                if (is_numeric($v)) {
                    $sum[$r->source][$k] = ($sum[$r->source][$k] ?? 0) + $v;
                }
            }
        }
        arsort($sum['sheet']['sources']);

        $status = DB::table('owner_marketing_sources')->get()->keyBy('source');
        $sources = [];
        foreach (MarketingCollector::sources() as $key => $src) {
            $s = $status[$key] ?? null;
            $sources[$key] = [
                'label' => MarketingCollector::LABELS[$key],
                'enabled' => $src->enabled(),
                'last_ok_at' => $s?->last_ok_at ? Carbon::parse($s->last_ok_at) : null,
                'last_error' => $s?->last_error,
                'days' => $days[$key] ?? 0,
            ];
        }
        $live = fn (string $k) => $sources[$k]['enabled'] && $sources[$k]['last_ok_at'] !== null;
        $sheetSpend = $sum['sheet']['spend'];

        $spend = [
            'direct' => $live('direct') ? ['value' => (float) ($sum['direct']['cost'] ?? 0), 'from' => 'API Директа']
                : (isset($sheetSpend['Директ']) ? ['value' => (float) $sheetSpend['Директ'], 'from' => 'таблица заявок'] : null),
            'vk' => $live('vk') ? ['value' => (float) ($sum['vk']['spent'] ?? 0), 'from' => 'API VK Рекламы']
                : (isset($sheetSpend['ВК']) ? ['value' => (float) $sheetSpend['ВК'], 'from' => 'таблица заявок'] : null),
            'avito' => isset($sheetSpend['Авито']) ? ['value' => (float) $sheetSpend['Авито'], 'from' => 'таблица заявок'] : null,
        ];

        return [
            'direct' => $sum['direct'],
            'vk' => $sum['vk'],
            'avito' => $sum['avito'],
            'sheet' => $sum['sheet'],
            'spend' => $spend,
            'spend_total' => array_sum(array_map(fn ($s) => $s['value'] ?? 0, array_filter($spend))),
            'sources' => $sources,
            'any' => $rows->isNotEmpty(),
        ];
    }
}
