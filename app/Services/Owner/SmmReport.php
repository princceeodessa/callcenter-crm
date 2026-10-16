<?php

namespace App\Services\Owner;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * SMM-контент для владельца: реклама клипов VK по бизнесу (потолки — кабинет БлагоДар, кроссовки — «Шаговый»).
 * Собирает отчёт в том же виде, что ежедневный отчёт SMM-специалиста (C:\blagodar\vk-target, blagodar-clips):
 * кампании, группы-клипы со статусом модерации и проблемами, итоги за период, динамика CTR первых и последних дней
 * показов, расход по дням. Цифры — из того, что CRM собирает сама (owner:marketing-collect, источники vk и vk_shag):
 * по дням и группам — owner_marketing_daily, названия, модерация и лимиты — owner_marketing_sources.meta.
 */
class SmmReport
{
    public const BUSINESSES = [
        'ceilings' => [
            'source' => 'vk', 'key' => 'potolki', 'title' => 'Потолки', 'brand' => 'БлагоДар', 'owner' => '-82903833',
            'community' => 'https://vk.ru/blagodarvizhevske', 'cabinet_id' => 23767728,
        ],
        'sneakers' => [
            'source' => 'vk_shag', 'key' => 'shag', 'title' => 'Кроссовки', 'brand' => 'Шаговый', 'owner' => '-236144953',
            'community' => 'https://vk.ru/shagovyyy18', 'cabinet_id' => 22322812,
        ],
    ];

    /** Сколько первых и последних дней показа сравнивать для динамики CTR. */
    public const CTR_WINDOW = 7;

    /** Отчёт по бизнесу за [from, toExclusive) — в виде одного «кабинета» отчёта SMM. */
    public static function cabinet(string $business, Carbon $from, Carbon $toExclusive): array
    {
        $b = self::BUSINESSES[$business];
        $status = DB::table('owner_marketing_sources')->where('source', $b['source'])->first();
        $meta = json_decode((string) ($status->meta ?? ''), true) ?: [];
        $rows = DB::table('owner_marketing_daily')->where('source', $b['source'])
            ->where('day', '>=', $from->toDateString())->where('day', '<', $toExclusive->toDateString())
            ->orderBy('day')->get(['day', 'metrics']);

        $per = [];        // группа => день => цифры
        $dayTotal = [];   // день => расход кабинета
        foreach ($rows as $r) {
            $m = json_decode((string) $r->metrics, true) ?: [];
            $day = substr((string) $r->day, 0, 10);
            $dayTotal[$day] = (float) ($m['spent'] ?? 0);
            foreach ((array) ($m['groups'] ?? []) as $gid => $g) {
                $per[(int) $gid][$day] = (array) $g;
            }
        }

        $groupsMeta = (array) ($meta['groups'] ?? []);
        $fromDay = $from->toDateString();
        $planWithStats = [];
        foreach (array_keys($per) as $gid) {
            if (isset($groupsMeta[$gid]['plan_id'])) {
                $planWithStats[(int) $groupsMeta[$gid]['plan_id']] = true;
            }
        }
        // кампании: действующие, созданные за период и те, что тратили в периоде
        $plans = array_values(array_filter((array) ($meta['plans'] ?? []), fn ($p) => ($p['status'] ?? '') === 'active'
            || substr((string) ($p['created'] ?? ''), 0, 10) >= $fromDay || isset($planWithStats[(int) ($p['id'] ?? 0)])));
        usort($plans, fn ($a, $b2) => [($b2['status'] ?? '') === 'active', (string) ($b2['created'] ?? '')] <=> [($a['status'] ?? '') === 'active', (string) ($a['created'] ?? '')]);
        $planIds = array_flip(array_map(fn ($p) => (int) $p['id'], $plans));

        $groups = [];
        foreach ($groupsMeta as $gid => $g) {
            $gid = (int) $gid;
            if (! isset($planIds[(int) ($g['plan_id'] ?? 0)])) {
                continue;
            }
            $objective = (string) ($g['objective'] ?? '');
            $type = $objective === 'leadads' ? 'lead' : (($objective === 'branding_socialengagement' || ! empty($g['clip'])) ? 'clip' : 'other');
            $days = $per[$gid] ?? [];
            ksort($days);
            $active = array_filter($days, fn ($d) => (int) ($d['shows'] ?? 0) > 0);
            $activeDays = array_keys($active);
            $n = count($activeDays);
            $window = $n >= self::CTR_WINDOW + 1 ? min(self::CTR_WINDOW, intdiv($n, 2)) : 0;
            $sum = fn (string $k) => array_sum(array_map(fn ($d) => (float) ($d[$k] ?? 0), $days));
            $ctr = function (array $ds) {
                $shows = array_sum(array_map(fn ($d) => (int) ($d['shows'] ?? 0), $ds));

                return $shows ? round(100 * array_sum(array_map(fn ($d) => (int) ($d['clicks'] ?? 0), $ds)) / $shows, 2) : null;
            };
            $groups[] = [
                'id' => $gid,
                'name' => (string) ($g['name'] ?? ''),
                'status' => (string) ($g['status'] ?? ''),
                'type' => $type,
                'plan_id' => (int) $g['plan_id'],
                'plan_name' => (string) ($g['plan'] ?? ''),
                'plan_status' => (string) ($g['plan_status'] ?? ''),
                'price' => $g['price'] ?? null,
                'day_limit' => $g['day_limit'] ?? null,
                'created' => $g['created'] ?? null,
                'owner' => $g['owner'] ?? $b['owner'],
                'clip' => $g['clip_id'] ?? null,
                'moderation' => $g['moderation'] ?? null,
                'reasons' => array_values((array) ($g['reasons'] ?? [])),
                'issues' => array_values((array) ($g['issues'] ?? [])),
                'shows' => (int) $sum('shows'),
                'clicks' => (int) $sum('clicks'),
                'spent' => round($sum('spent'), 2),
                'v100' => (int) $sum('views100'),
                'joins' => (int) $sum('joins'),
                'leads' => (int) $sum('goals'),
                'active_days' => $n,
                'first_date' => $activeDays[0] ?? null,
                'last_date' => $n ? $activeDays[$n - 1] : null,
                'ctr_first' => $window ? $ctr(array_slice($active, 0, $window)) : null,
                'ctr_last' => $window ? $ctr(array_slice($active, -$window)) : null,
                'window' => $window,
            ];
        }

        $days = [];
        for ($d = $from->copy(); $d->lt($toExclusive); $d->addDay()) {
            $k = $d->toDateString();
            $clip = $lead = 0.0;
            foreach ($groups as $g) {
                $v = (float) ($per[$g['id']][$k]['spent'] ?? 0);
                if ($g['type'] === 'lead') {
                    $lead += $v;
                } elseif ($g['type'] === 'clip') {
                    $clip += $v;
                }
            }
            $days[] = ['d' => $k, 'clip' => round($clip, 2), 'lead' => round($lead, 2), 'other' => round(max(0, ($dayTotal[$k] ?? 0) - $clip - $lead), 2)];
        }

        $error = null;
        if (! $status) {
            $error = 'кабинет VK Рекламы «'.$b['brand'].'» ещё не подключён к сводке';
        } elseif ($status->last_error) {
            $error = (string) $status->last_error;
        }

        return [
            'key' => $b['key'],
            'title' => $b['title'],
            'brand' => $b['brand'],
            'owner' => $b['owner'],
            'cabinet_id' => $b['cabinet_id'],
            'community' => $b['community'],
            'error' => $error,
            'stale' => $error !== null && $status && $status->last_ok_at !== null,
            'updated' => $status && $status->last_ok_at ? Carbon::parse($status->last_ok_at)->format('Y-m-d H:i') : null,
            'plans' => array_map(fn ($p) => [
                'id' => (int) $p['id'], 'name' => (string) ($p['name'] ?? ''), 'status' => (string) ($p['status'] ?? ''),
                'objective' => (string) ($p['objective'] ?? ''), 'day_limit' => $p['day_limit'] ?? null, 'created' => (string) ($p['created'] ?? ''),
            ], $plans),
            'groups' => $groups,
            'days' => $days,
        ];
    }
}
