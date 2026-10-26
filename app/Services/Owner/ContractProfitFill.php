<?php

namespace App\Services\Owner;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Досчёт прибыли договоров у нас. CRM БлагоДар не считает расход строк без ставки монтажникам и товаров без цены
 * закупки в 1С — их прибыль завышена. Сами строки (без заказчиков и адресов) раз в час выгружает
 * scripts/blagodar/cp_uncosted.js; здесь им ставится расход: ставкой руководства (owner_cost_rates — ₽ за единицу или
 * % от суммы строки), а нет её — средней долей расхода в цене таких же строк у БлагоДар (работы ~21%, товары ~39%).
 * УСН договора («доходы минус расходы», не меньше минимального) пересчитывается: больший расход его уменьшает.
 * В самой БлагоДар ничего не меняется.
 */
class ContractProfitFill
{
    public const TABLE = 'owner_cost_rates';

    /** Строки старше — сводка пишет, на какое время они (выгрузка раз в час) */
    public const STALE_HOURS = 3;

    public const NO_MEASURER = 'Замерщик не определён';

    /** Выгрузка БлагоДар; null — её нет (не настроена или ещё не собрана). */
    public static function export(): ?array
    {
        $file = (string) config('owner.contract_profit.uncosted_file');
        if ($file === '' || ! is_file($file) || ! is_readable($file)) {
            return null;
        }
        clearstatcache(true, $file);
        $mtime = (int) filemtime($file);
        $data = Cache::remember('owner.cp_uncosted.'.md5($file).'.'.$mtime.'.'.filesize($file), 3600, function () use ($file) {
            $j = json_decode((string) @file_get_contents($file), true);

            return is_array($j) && is_array($j['contracts'] ?? null) ? $j : false;
        });

        return $data === false ? null : $data + ['file_time' => Carbon::createFromTimestamp($mtime)];
    }

    public static function key(string $kind, string $name, string $unit): string
    {
        return sha1($kind."\n".trim($name)."\n".trim($unit));
    }

    /** Ставки руководства: key => row */
    public static function rates(): array
    {
        return DB::table(self::TABLE)->get()->mapWithKeys(fn ($r) => [$r->key => (array) $r])->all();
    }

    /** Расход строки: [₽, откуда: rate | share] */
    public static function lineCost(array $line, array $rates, array $shares): array
    {
        $kind = ($line['kind'] ?? '') === 'goods' ? 'goods' : 'work';
        $sum = (float) ($line['sum'] ?? 0);
        $r = $rates[self::key($kind, (string) ($line['name'] ?? ''), (string) ($line['unit'] ?? ''))] ?? null;
        if ($r) {
            $cost = $r['mode'] === 'share' ? $sum * (float) $r['value'] / 100 : (float) $r['value'] * (float) ($line['qty'] ?? 0);

            return [round($cost, 2), 'rate'];
        }

        return [round($sum * (float) ($shares[$kind] ?? 0), 2), 'share'];
    }

    /** УСН «доходы минус расходы»: на сколько он меняется, когда расход договора больше на $extra */
    public static function usnDelta(array $c, float $extra): float
    {
        if (($c['usn_pct'] ?? null) === null || ($c['usn'] ?? null) === null) {
            return 0.0;
        }
        $income = (float) $c['income'];
        $floor = ($c['min_pct'] ?? null) !== null ? round($income * (float) $c['min_pct'] / 100, 2) : 0.0;
        $usn = fn (float $costs) => max(round(max($income - $costs, 0) * (float) $c['usn_pct'] / 100, 2), $floor);

        return round($usn((float) $c['before_tax'] + $extra) - $usn((float) $c['before_tax']), 2);
    }

    /**
     * Итоги БлагоДар (ContractProfit) с досчитанными строками: расход, прибыль и маржа — итогом, по месяцам и
     * по замерщикам, «куда уходят деньги»; число неполных договоров — без досчитанных. Что досчитано — в data.fill.
     */
    public static function apply(?array $profit, Carbon $from, Carbon $toExclusive): ?array
    {
        if (($profit['state'] ?? '') !== 'ok' || ! ($export = self::export())) {
            return $profit;
        }
        $fromDay = $from->toDateString();
        $toDay = $toExclusive->toDateString();
        $rates = self::rates();
        $shares = (array) ($export['shares'] ?? []);

        $fill = ['contracts' => 0, 'closed' => 0, 'works' => 0.0, 'goods' => 0.0, 'usn' => 0.0, 'delta' => 0.0,
            'rate_lines' => 0, 'share_lines' => 0, 'rate_sum' => 0.0, 'share_sum' => 0.0];
        $byMonth = [];
        $byMeasurer = [];
        foreach ($export['contracts'] as $c) {
            $day = (string) ($c['date'] ?? '');
            if ($day < $fromDay || $day >= $toDay || empty($c['lines'])) {
                continue;
            }
            $x = ['work' => 0.0, 'goods' => 0.0];
            foreach ($c['lines'] as $l) {
                [$cost, $how] = self::lineCost((array) $l, $rates, $shares);
                $x[($l['kind'] ?? '') === 'goods' ? 'goods' : 'work'] += $cost;
                $fill[$how.'_lines']++;
                $fill[$how.'_sum'] += $cost;
            }
            $dUsn = self::usnDelta($c, $x['work'] + $x['goods']);
            $delta = $x['work'] + $x['goods'] + $dUsn;      // на столько расход больше, а прибыль меньше
            // не хватало только ставок строк — договор досчитан целиком; другое (полотно, материалы, ставки отделов) — нет
            $closed = empty($c['other_missing']) ? 1 : 0;
            $fill['contracts']++;
            $fill['closed'] += $closed;
            $fill['works'] += $x['work'];
            $fill['goods'] += $x['goods'];
            $fill['usn'] += $dUsn;
            $fill['delta'] += $delta;
            $month = substr($day, 0, 7);
            $byMonth[$month] = ['delta' => ($byMonth[$month]['delta'] ?? 0) + $delta, 'closed' => ($byMonth[$month]['closed'] ?? 0) + $closed];
            $who = (string) ($c['measurer'] ?: self::NO_MEASURER);
            $byMeasurer[$who] = ['delta' => ($byMeasurer[$who]['delta'] ?? 0) + $delta, 'closed' => ($byMeasurer[$who]['closed'] ?? 0) + $closed];
        }
        if ($fill['contracts'] === 0) {
            return $profit;
        }

        $d = $profit['data'];
        $fill['blagodar_profit'] = $d['totals']['profit'];
        $fill['blagodar_incomplete'] = $d['totals']['incomplete'];
        $fill['at'] = $export['file_time'];
        $fill['stale'] = $export['file_time']->lt(now()->subHours(self::STALE_HOURS));
        $fill['shares'] = $shares;

        $d['totals'] = self::shift($d['totals'], ['delta' => $fill['delta'], 'closed' => $fill['closed']]);
        $d['totals']['costs'] = $d['totals']['costs'] !== null ? $d['totals']['costs'] + $fill['delta'] : null;
        $d['by_month'] = array_map(fn ($r) => isset($r['key'], $byMonth[$r['key']]) ? self::shift($r, $byMonth[$r['key']]) : $r, $d['by_month']);
        $d['by_measurer'] = array_map(fn ($r) => isset($byMeasurer[$r['measurer']]) ? self::shift($r, $byMeasurer[$r['measurer']]) : $r, $d['by_measurer']);
        $d['kinds'] = self::kinds($d['kinds'], ['installers' => $fill['works'], 'goods' => $fill['goods'], 'usn' => $fill['usn']]);
        $d['fill'] = $fill;
        $profit['data'] = $d;

        return $profit;
    }

    /** Строка итогов с поправкой: прибыль меньше на delta, маржа заново, неполных — без досчитанных целиком */
    private static function shift(array $row, array $g): array
    {
        $row['profit'] = $row['profit'] !== null ? round($row['profit'] - $g['delta'], 2) : null;
        $row['margin'] = $row['profit'] !== null && ($row['revenue'] ?? 0) > 0 ? round($row['profit'] / $row['revenue'] * 1000) / 10 : $row['margin'];
        $row['incomplete'] = max(0, (int) $row['incomplete'] - (int) $g['closed']);

        return $row;
    }

    /** «Куда уходят деньги» с досчитанным: монтажники, товары, УСН — в порядке и цветах ContractProfit::KINDS */
    private static function kinds(array $kinds, array $add): array
    {
        $sums = array_column($kinds, 'sum', 'key');
        foreach ($add as $k => $v) {
            $sums[$k] = ($sums[$k] ?? 0) + $v;
        }
        $out = [];
        foreach (ContractProfit::KINDS as $k => [$label, $color]) {
            if (isset($sums[$k]) && abs($sums[$k]) >= 0.5) {
                $out[] = ['key' => $k, 'label' => $label, 'color' => $color, 'sum' => round($sums[$k], 2)];
            }
        }

        return $out;
    }

    /**
     * Позиции без расхода у БлагоДар — для страницы ставок: сколько договоров и на какую сумму (за всё, что есть
     * в выгрузке, и за период), ставка руководства, расход по ней или по средней доле. Сверху — что дороже стоит.
     */
    public static function positions(Carbon $from, Carbon $toExclusive): array
    {
        $export = self::export();
        $rates = self::rates();
        $shares = (array) ($export['shares'] ?? []);
        $fromDay = $from->toDateString();
        $toDay = $toExclusive->toDateString();
        $rows = [];
        foreach ((array) ($export['contracts'] ?? []) as $c) {
            $inPeriod = ($c['date'] ?? '') >= $fromDay && ($c['date'] ?? '') < $toDay;
            $seen = [];
            foreach ((array) ($c['lines'] ?? []) as $l) {
                $kind = ($l['kind'] ?? '') === 'goods' ? 'goods' : 'work';
                $key = self::key($kind, (string) $l['name'], (string) ($l['unit'] ?? ''));
                $r = $rows[$key] ?? ['key' => $key, 'kind' => $kind, 'name' => trim((string) $l['name']), 'unit' => trim((string) ($l['unit'] ?? '')),
                    'contracts' => 0, 'qty' => 0.0, 'sum' => 0.0, 'cost' => 0.0, 'period_contracts' => 0, 'period_sum' => 0.0, 'period_cost' => 0.0, 'last' => ''];
                [$cost] = self::lineCost((array) $l, $rates, $shares);
                if (! isset($seen[$key])) {
                    $r['contracts']++;
                    $r['period_contracts'] += $inPeriod ? 1 : 0;
                    $seen[$key] = true;
                }
                $r['qty'] += (float) ($l['qty'] ?? 0);
                $r['sum'] += (float) ($l['sum'] ?? 0);
                $r['cost'] += $cost;
                if ($inPeriod) {
                    $r['period_sum'] += (float) ($l['sum'] ?? 0);
                    $r['period_cost'] += $cost;
                }
                $r['last'] = max($r['last'], (string) $c['date']);
                $rows[$key] = $r;
            }
        }
        foreach ($rows as $key => &$r) {
            $r['rate'] = $rates[$key] ?? null;
            $r['share'] = (float) ($shares[$r['kind']] ?? 0);
        }
        unset($r);
        // ставки, чьих строк в выгрузке больше нет (в 1С её уже завели) — внизу, чтобы их можно было убрать
        foreach ($rates as $key => $rate) {
            if (! isset($rows[$key])) {
                $rows[$key] = ['key' => $key, 'kind' => $rate['kind'], 'name' => $rate['name'], 'unit' => $rate['unit'], 'contracts' => 0, 'qty' => 0.0,
                    'sum' => 0.0, 'cost' => 0.0, 'period_contracts' => 0, 'period_sum' => 0.0, 'period_cost' => 0.0, 'last' => '', 'rate' => $rate,
                    'share' => (float) ($shares[$rate['kind']] ?? 0)];
            }
        }
        uasort($rows, fn ($a, $b) => [$b['period_sum'], $b['sum'], $b['contracts']] <=> [$a['period_sum'], $a['sum'], $a['contracts']]);

        return ['rows' => array_values($rows), 'shares' => $shares, 'at' => $export['file_time'] ?? null,
            'since' => $export ? collect($export['contracts'])->min('date') : null];
    }

    /**
     * Сохранить ставки со страницы: [{kind, name, unit, mode, value}] — пустое значение убирает ставку.
     * @return int сколько изменено
     */
    public static function save(array $items, ?int $userId): int
    {
        $changed = 0;
        foreach ($items as $it) {
            $kind = ($it['kind'] ?? '') === 'goods' ? 'goods' : 'work';
            $name = trim((string) ($it['name'] ?? ''));
            $unit = trim((string) ($it['unit'] ?? ''));
            if ($name === '' || mb_strlen($name) > 500 || mb_strlen($unit) > 50) {
                continue;
            }
            $key = self::key($kind, $name, $unit);
            $raw = str_replace([' ', "\u{00A0}", "\u{202F}", ','], ['', '', '', '.'], trim((string) ($it['value'] ?? '')));
            if ($raw === '') {
                $changed += DB::table(self::TABLE)->where('key', $key)->delete();
                continue;
            }
            $mode = ($it['mode'] ?? '') === 'share' ? 'share' : 'unit';
            $value = is_numeric($raw) ? round((float) $raw, 2) : null;
            if ($value === null || $value < 0 || $value > 10_000_000 || ($mode === 'share' && $value > 100)) {
                continue;
            }
            $old = DB::table(self::TABLE)->where('key', $key)->first();
            if ($old && $old->mode === $mode && (float) $old->value === $value) {
                continue;
            }
            DB::table(self::TABLE)->updateOrInsert(['key' => $key], ['kind' => $kind, 'name' => $name, 'unit' => $unit, 'mode' => $mode,
                'value' => $value, 'updated_by' => $userId, 'updated_at' => now()] + ($old ? [] : ['created_at' => now()]));
            $changed++;
        }

        return $changed;
    }
}
