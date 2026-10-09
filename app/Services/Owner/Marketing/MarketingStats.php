<?php

namespace App\Services\Owner\Marketing;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Реклама потолков за период [from, to) из owner_marketing_daily — для сводки владельца.
 * Расход по группам каналов CRM: Директ, VK и Авито — из их API (если источник собирается), иначе из таблицы замеров.
 * Авито — только объявления потолков (в кабинете ещё кондиционеры и ремонт, их и тариф сводка показывает отдельно).
 * Число замеров в сводке — по таблице замеров (решение владельца 09.10.2026), а не по этапам CRM.
 */
class MarketingStats
{
    /** Источник из таблицы замеров → группа каналов CRM (CeilingsKpi::GROUPS). */
    public static function sheetGroup(string $name): string
    {
        return match (mb_strtolower(self::cleanName($name))) {
            'директ' => 'direct',
            'авито', 'авито частник' => 'avito',
            'вк' => 'vk',
            'радио' => 'radio',
            'тв' => 'tv',
            default => 'other',
        };
    }

    public static function cleanName(string $name): string
    {
        return trim(preg_replace('/\s+/u', ' ', $name));
    }

    /**
     * Замеры по таблице за период [from, to): всего, по дням, по группам и источникам, прошлый период той же длины
     * и сколько дней периода в таблице есть (листа за месяц может не быть).
     */
    public static function measurements(Carbon $from, Carbon $to): array
    {
        $read = function (Carbon $a, Carbon $b) {
            return DB::table('owner_marketing_daily')->where('source', 'sheet')
                ->where('day', '>=', $a->toDateString())->where('day', '<', $b->toDateString())
                ->orderBy('day')->get(['day', 'metrics']);
        };
        $total = 0;
        $byDay = [];
        $byGroup = [];
        $bySource = [];
        foreach ($read($from, $to) as $r) {
            $m = json_decode((string) $r->metrics, true) ?: [];
            $day = substr((string) $r->day, 0, 10);
            $byDay[$day] = (int) round((float) ($m['total'] ?? 0));
            $total += $byDay[$day];
            foreach ((array) ($m['sources'] ?? []) as $name => $v) {
                $v = (int) round((float) $v);
                if ($v <= 0) {
                    continue;
                }
                $clean = self::cleanName((string) $name);
                $g = self::sheetGroup($clean);
                $byGroup[$g] = ($byGroup[$g] ?? 0) + $v;
                $bySource[$g][$clean] = ($bySource[$g][$clean] ?? 0) + $v;
            }
        }
        foreach ($bySource as &$list) {
            arsort($list);
        }
        unset($list);

        $prevFrom = $from->copy()->subSeconds($to->getTimestamp() - $from->getTimestamp());
        $prevRows = $read($prevFrom, $from);
        $prevTotal = (int) round($prevRows->sum(fn ($r) => (float) ((json_decode((string) $r->metrics, true) ?: [])['total'] ?? 0)));

        $lastDay = Carbon::today()->lt($to) ? Carbon::today()->addDay() : $to;
        $periodDays = max(0, (int) $from->diffInDays($lastDay));

        return [
            'available' => count($byDay) > 0,
            'total' => $total,
            'by_day' => $byDay,
            'by_group' => $byGroup,
            'by_source' => $bySource,
            'days' => count($byDay),
            'period_days' => $periodDays,
            // сравнение честно только если прошлый период в таблице есть целиком (листа за месяц может не быть)
            'prev_total' => $prevRows->count() >= (int) $prevFrom->diffInDays($from) ? $prevTotal : null,
        ];
    }

    public static function forPeriod(Carbon $from, Carbon $to): array
    {
        $rows = DB::table('owner_marketing_daily')
            ->where('day', '>=', $from->toDateString())
            ->where('day', '<', $to->toDateString())
            ->get(['source', 'day', 'metrics']);

        $sum = ['direct' => [], 'vk' => [], 'avito' => [], 'sheet' => ['total' => 0, 'sources' => [], 'spend' => []]];
        $vkGroups = [];
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
            if ($r->source === 'vk') {
                foreach ((array) ($m['groups'] ?? []) as $gid => $g) {
                    foreach ((array) $g as $k => $v) {
                        if (is_numeric($v)) {
                            $vkGroups[$gid][$k] = ($vkGroups[$gid][$k] ?? 0) + $v;
                        }
                    }
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
                : (isset($sheetSpend['Директ']) ? ['value' => (float) $sheetSpend['Директ'], 'from' => 'таблица замеров'] : null),
            'vk' => $live('vk') ? ['value' => (float) ($sum['vk']['spent'] ?? 0), 'from' => 'API VK Рекламы']
                : (isset($sheetSpend['ВК']) ? ['value' => (float) $sheetSpend['ВК'], 'from' => 'таблица замеров'] : null),
            'avito' => $live('avito') && isset($sum['avito']['ceilings_spend']) ? ['value' => (float) $sum['avito']['ceilings_spend'], 'from' => 'API Авито, объявления потолков']
                : (isset($sheetSpend['Авито']) ? ['value' => (float) $sheetSpend['Авито'], 'from' => 'таблица замеров'] : null),
        ];

        // кабинет Авито по направлениям: потолки, кондиционеры, ремонт и общие списания (тариф и прочее)
        $avitoDirs = [];
        foreach (AvitoSource::DIRS as $key => $d) {
            $avitoDirs[$key] = [
                'label' => $d['label'],
                'spend' => (float) ($sum['avito'][$key.'_spend'] ?? 0),
                'contacts' => (int) ($sum['avito'][$key.'_contacts'] ?? 0),
                'views' => (int) ($sum['avito'][$key.'_views'] ?? 0),
            ];
        }

        $vkMeta = json_decode((string) ($status['vk']->meta ?? ''), true) ?: [];
        $groups = [];
        foreach ($vkGroups as $gid => $g) {
            $info = (array) ($vkMeta['groups'][$gid] ?? []);
            $groups[] = $g + [
                'id' => (int) $gid,
                'name' => (string) ($info['name'] ?? ('группа '.$gid)),
                'plan' => (string) ($info['plan'] ?? ''),
                'active' => ($info['status'] ?? '') === 'active' && ($info['plan_status'] ?? '') === 'active',
                'leadads' => (bool) ($info['leadads'] ?? false),
                'clip' => is_string($info['clip'] ?? null) && str_starts_with($info['clip'], 'https://vk.com/') ? $info['clip'] : null,
            ];
        }
        usort($groups, fn ($a, $b) => [$b['active'], $b['spent'] ?? 0] <=> [$a['active'], $a['spent'] ?? 0]);

        return [
            'direct' => $sum['direct'],
            'vk' => $sum['vk'],
            'avito' => $sum['avito'],
            'sheet' => $sum['sheet'],
            'spend' => $spend,
            'avito_dirs' => $avitoDirs,
            'avito_shared' => (float) ($sum['avito']['shared_spend'] ?? 0),
            'vk_groups' => $groups,
            'spend_total' => array_sum(array_map(fn ($s) => $s['value'] ?? 0, array_filter($spend))),
            'sources' => $sources,
            'any' => $rows->isNotEmpty(),
        ];
    }
}
