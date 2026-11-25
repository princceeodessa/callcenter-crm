<?php

namespace App\Services\Owner\Marketing;

use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

/**
 * Google-таблица замеров БлагоДар: лист на месяц «ИЖ <Месяц> <Год>», строка на день:
 * дата | тип | Сумма | <Источник> | <Источник> затраты | <Источник> цена лида | … Открыта по ссылке. Читаем обычной
 * выгрузкой CSV листа (export по gid; gid — по названию листа со страницы таблицы), запасной путь — gviz-CSV по названию.
 * gviz подбирает тип колонки и молча теряет ячейки «чужого» типа: 25.11.2026 из строки дня пропали Авито 3, Повторный 1,
 * «Повторник, пришедший с сайта» 1 (в таблице они были) — сводка показала 5 замеров «без источника».
 * Затраты в таблице вносят вручную; сводка берёт их, только когда API площадки не собирается.
 */
class LeadsSheetSource
{
    public const MONTHS = ['Январь', 'Февраль', 'Март', 'Апрель', 'Май', 'Июнь', 'Июль', 'Август', 'Сентябрь', 'Октябрь', 'Ноябрь', 'Декабрь'];
    public const AD_SOURCES = ['Директ', 'Авито', 'ВК'];

    public function __construct(private readonly array $cfg)
    {
    }

    public function enabled(): bool
    {
        return ! empty($this->cfg['id']);
    }

    public static function sheetName(int $year, int $month): string
    {
        return 'ИЖ '.self::MONTHS[$month - 1].' '.$year;
    }

    /** @return array<string, array{total: float, sources: array<string, float>, spend: array<string, float>}> */
    public function daily(Carbon $from, Carbon $to): array
    {
        $out = [];
        $found = 0;
        $month = $from->copy()->startOfMonth();
        while ($month->lte($to)) {
            $rows = $this->fetch(self::sheetName($month->year, $month->month));
            if ($rows !== null) {
                $found++;
                foreach (self::parse($rows) as $day => $rec) {
                    if ($day >= $from->toDateString() && $day <= $to->toDateString()) {
                        $out[$day] = $rec;
                    }
                }
            }
            $month->addMonth();
        }
        if ($found === 0) {
            throw new RuntimeException('Таблица замеров: листы «'.self::sheetName($to->year, $to->month).'» и соседние не читаются — проверьте доступ по ссылке и названия листов');
        }
        ksort($out);

        return $out;
    }

    private function fetch(string $name): ?array
    {
        $base = 'https://docs.google.com/spreadsheets/d/'.$this->cfg['id'];
        $gid = $this->gid($name);
        if ($gid !== null) {
            $rows = $this->csv($base.'/export?'.http_build_query(['format' => 'csv', 'gid' => $gid]));
            if ($rows !== null) {
                return $rows;
            }
        }

        return $this->csv($base.'/gviz/tq?'.http_build_query(['tqx' => 'out:csv', 'sheet' => $name]));
    }

    /**
     * gid листа по названию — со страницы таблицы (там перечислены все листы). Список кэшируется на 30 мин; листа нет
     * (новый месяц ещё не завели) — страницу перечитываем не чаще раза в 10 мин.
     */
    private function gid(string $name): ?string
    {
        $key = 'owner:leads-sheet-gids:'.md5((string) $this->cfg['id']);
        $map = Cache::get($key);
        if (! is_array($map) || (! array_key_exists($name, $map) && ! Cache::has($key.':fresh'))) {
            $loaded = $this->loadGids();
            if ($loaded !== null) {
                $map = $loaded;
                Cache::put($key, $map, now()->addMinutes(30));
                Cache::put($key.':fresh', 1, now()->addMinutes(10));
            }
        }

        return is_array($map) ? ($map[$name] ?? null) : null;
    }

    /** @return array<string, string>|null название листа => gid */
    private function loadGids(): ?array
    {
        try {
            [$status, $html] = Http::request('GET', 'https://docs.google.com/spreadsheets/d/'.$this->cfg['id'].'/edit', ['User-Agent' => 'Mozilla/5.0'], null, 40);
        } catch (\Throwable) {
            return null;
        }
        if ($status !== 200) {
            return null;
        }

        return self::parseGids($html);
    }

    /** Листы со страницы таблицы: в её данных они идут как [n,0,"gid",[{"1":[[0,0,"Название" (кавычки бывают экранированы). */
    public static function parseGids(string $html): ?array
    {
        if (! preg_match_all('/\[\d+,0,\\\\?"(\d+)\\\\?",\[\{\\\\?"1\\\\?":\[\[0,0,\\\\?"([^"\\\\]+)\\\\?"/u', $html, $mm, PREG_SET_ORDER)) {
            return null;
        }
        $map = [];
        foreach ($mm as $m) {
            $map[$m[2]] ??= $m[1];
        }

        return $map;
    }

    private function csv(string $url): ?array
    {
        try {
            [$status, $text, $type] = Http::withRetry('GET', $url, ['User-Agent' => 'Mozilla/5.0'], null, 40);
        } catch (\Throwable) {
            return null;
        }
        if ($status !== 200 || ! str_contains($type, 'text/csv')) {
            return null;
        }
        $rows = [];
        $fh = fopen('php://temp', 'r+');
        fwrite($fh, $text);
        rewind($fh);
        while (($row = fgetcsv($fh, 0, ',', '"', '')) !== false) {
            $rows[] = $row;
        }
        fclose($fh);

        return count($rows) >= 2 ? $rows : null;
    }

    /** Строки листа → день => всего замеров, по источникам и затраты платных каналов. */
    public static function parse(array $rows): array
    {
        $header = array_map(fn ($h) => trim((string) $h), $rows[0]);
        $sumIdx = array_search('Сумма', $header, true);
        $sources = [];
        foreach ($header as $i => $h) {
            $low = mb_strtolower($h);
            if ($i > 1 && $h !== '' && $h !== 'Сумма' && ! str_contains($low, 'затрат') && ! str_contains($low, 'цена')) {
                $sources[$h] = $i;
            }
        }
        $spendIdx = [];
        foreach (self::AD_SOURCES as $src) {
            $idx = array_search($src.' затраты', $header, true);
            if ($idx !== false) {
                $spendIdx[$src] = $idx;
            }
        }
        $num = function ($v): float {
            $v = str_replace(["\u{00A0}", ' ', ','], ['', '', '.'], trim((string) $v));

            return is_numeric($v) ? (float) $v : 0.0;
        };

        $out = [];
        foreach (array_slice($rows, 1) as $row) {
            $date = trim((string) ($row[0] ?? ''));
            if ($date === '') {
                continue;
            }
            $dt = \DateTime::createFromFormat('!d.m.y', $date) ?: \DateTime::createFromFormat('!d.m.Y', $date);
            if (! $dt) {
                continue;
            }
            $rec = ['total' => $sumIdx !== false ? $num($row[$sumIdx] ?? 0) : 0.0, 'sources' => [], 'spend' => []];
            foreach ($sources as $h => $i) {
                $rec['sources'][$h] = $num($row[$i] ?? 0);
            }
            foreach ($spendIdx as $src => $i) {
                $rec['spend'][$src] = $num($row[$i] ?? 0);
            }
            $out[$dt->format('Y-m-d')] = $rec;
        }

        return $out;
    }
}
