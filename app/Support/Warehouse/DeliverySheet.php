<?php

namespace App\Support\Warehouse;

/**
 * Разбор таблицы поставки (.xlsx) в строки для карточек закупки.
 *
 * Колонки ищутся ПО ЗАГОЛОВКАМ, а не по позициям: поставщик присылает свою
 * калькуляцию себестоимости (20+ колонок: юани, курс, пошлина, НДС…), и жёсткий
 * порядок столбцов там не соблюдается. Если заголовок не найден — откат на
 * прежний позиционный формат «Название | Размер | Кол-во | Артикул | Сумма».
 */
class DeliverySheet
{
    /** Сколько первых колонок читаем и в скольких первых строках ищем заголовок. */
    private const MAX_COLS = 30;

    private const HEADER_SCAN_ROWS = 25;

    /**
     * Заголовки по полям. Сопоставление ТОЧНОЕ по нормализованной подписи —
     * иначе «Цена, юань» и «Закупочная цена, руб.» перехватили бы цену у
     * «Цена с НДС», а «Товар» (там лежит категория «Кроссовки») — у «Название».
     * Порядок внутри поля = приоритет.
     *
     * @var array<string, array<int, string>>
     */
    private const HEADERS = [
        'name' => ['название', 'наименование', 'название товара', 'модель', 'товар'],
        'article' => ['артикул', 'sku', 'код', 'код товара', 'артикул товара'],
        'size' => ['размер eu', 'размер', 'размер ru', 'size'],
        'qty' => ['количество', 'кол во', 'колво', 'qty', 'шт'],
    ];

    /**
     * Цена: сначала полная себестоимость пары (с доставкой/пошлиной/НДС), затем
     * сумма строки, и только в последнюю очередь «голая» закупка.
     * Формат: подпись => это сумма всей строки (true) или цена за единицу (false).
     *
     * @var array<string, bool>
     */
    private const COST_HEADERS = [
        'цена с ндс' => false,
        'сумма с ндс' => true,
        'цена за пару' => false,
        'цена за единицу' => false,
        'себестоимость' => false,
        'закупочная цена руб' => false,
        'закупочная цена' => false,
        'сумма' => true,
        'итого' => true,
        'цена' => false,
    ];

    /**
     * @return array{rows: array<int, array{brand:string,model:string,size:string,qty:int,article:string,cost:?float}>, mapping: array<string,string>, skipped: int}
     */
    public static function parse(string $path): array
    {
        $raw = SimpleXlsxReader::rows($path, self::MAX_COLS);
        $map = self::detectColumns($raw);

        if ($map === null) {
            // Файл без заголовка — прежний позиционный формат.
            $map = [
                'header_row' => -1, 'name' => 0, 'size' => 1, 'qty' => 2, 'article' => 3,
                'cost' => 4, 'cost_is_total' => true,
                'labels' => ['Название' => 'A', 'Размер' => 'B', 'Кол-во' => 'C', 'Артикул' => 'D', 'Сумма' => 'E'],
            ];
        }

        $rows = [];
        $skipped = 0;
        foreach ($raw as $index => $r) {
            if ($index <= $map['header_row']) {
                continue;
            }

            $name = self::cleanText($r[$map['name']] ?? null);
            $size = self::normalizeSize($r[$map['size']] ?? null);
            if ($name === '' || $size === null) {
                // Пустая строка, строка итогов или примечание под таблицей.
                if (self::rowHasAnything($r)) {
                    $skipped++;
                }

                continue;
            }

            $qty = 1;
            if ($map['qty'] !== null) {
                $parsedQty = self::parseNumber($r[$map['qty']] ?? null);
                if ($parsedQty !== null && $parsedQty >= 1) {
                    $qty = (int) round($parsedQty);
                }
            }

            $cost = null;
            if ($map['cost'] !== null) {
                $value = self::parseNumber($r[$map['cost']] ?? null);
                if ($value !== null) {
                    $cost = $map['cost_is_total'] && $qty > 0 ? round($value / $qty, 2) : round($value, 2);
                }
            }

            $article = $map['article'] !== null
                ? ArticleIdentity::normalizeArticle(self::cleanText($r[$map['article']] ?? null))
                : '';

            [$brand, $model] = ArticleIdentity::splitBrandModel($name);

            $rows[] = [
                'brand' => $brand,
                'model' => $model,
                'size' => $size,
                'qty' => $qty,
                'article' => $article,
                'cost' => $cost,
            ];
        }

        return ['rows' => $rows, 'mapping' => $map['labels'], 'skipped' => $skipped];
    }

    /**
     * Найти строку заголовка и колонки. Минимум для распознавания — название и размер.
     *
     * @param  array<int, array<int, string|int|float|null>>  $raw
     * @return array{header_row:int,name:int,size:int,qty:?int,article:?int,cost:?int,cost_is_total:bool,labels:array<string,string>}|null
     */
    private static function detectColumns(array $raw): ?array
    {
        foreach ($raw as $index => $row) {
            if ($index >= self::HEADER_SCAN_ROWS) {
                break;
            }

            $found = ['name' => null, 'article' => null, 'size' => null, 'qty' => null];
            $labels = [];
            foreach (array_keys($found) as $field) {
                foreach (self::HEADERS[$field] as $candidate) {
                    $col = self::findColumn($row, $candidate);
                    if ($col !== null) {
                        $found[$field] = $col;
                        $labels[self::cleanText($row[$col])] = self::columnLetter($col);
                        break;
                    }
                }
            }

            if ($found['name'] === null || $found['size'] === null) {
                continue;
            }

            $costCol = null;
            $costIsTotal = true;
            foreach (self::COST_HEADERS as $candidate => $isTotal) {
                $col = self::findColumn($row, $candidate);
                if ($col !== null) {
                    $costCol = $col;
                    $costIsTotal = $isTotal;
                    $labels[self::cleanText($row[$col])] = self::columnLetter($col);
                    break;
                }
            }

            return [
                'header_row' => $index,
                'name' => $found['name'],
                'size' => $found['size'],
                'qty' => $found['qty'],
                'article' => $found['article'],
                'cost' => $costCol,
                'cost_is_total' => $costIsTotal,
                'labels' => $labels,
            ];
        }

        return null;
    }

    /** @param  array<int, string|int|float|null>  $row */
    private static function findColumn(array $row, string $needle): ?int
    {
        foreach ($row as $col => $value) {
            if (self::normalizeHeader($value) === $needle) {
                return (int) $col;
            }
        }

        return null;
    }

    /** «Размер (EU)» → «размер eu», «Кол-во» → «кол во», «Цена, юань» → «цена юань». */
    private static function normalizeHeader(string|int|float|null $value): string
    {
        $text = mb_strtolower(trim((string) $value));
        $text = str_replace('ё', 'е', $text);
        $text = preg_replace('~[^\p{L}\p{N}]+~u', ' ', $text) ?? '';

        return trim(preg_replace('~\s+~u', ' ', $text) ?? '');
    }

    /**
     * Размер к единому виду, иначе один и тот же размер разъедется на два SKU:
     * 44.5 числом, «44.5» строкой и «44 1/2» — это одно и то же; «43 1/3» —
     * отдельный размер, и обрезать его до «43» нельзя.
     */
    public static function normalizeSize(string|int|float|null $raw): ?string
    {
        $text = str_replace(',', '.', trim((string) $raw));
        if ($text === '') {
            return null;
        }

        if (preg_match('~^(\d+)\s+(\d+)\s*/\s*(\d+)~u', $text, $m) && (int) $m[3] !== 0) {
            return self::trimNumber((int) $m[1] + (int) $m[2] / (int) $m[3]);
        }

        if (preg_match('~\d+(?:\.\d+)?~', $text, $m)) {
            return self::trimNumber((float) $m[0]);
        }

        return null;
    }

    private static function parseNumber(string|int|float|null $raw): ?float
    {
        if (is_int($raw) || is_float($raw)) {
            return (float) $raw;
        }

        $text = str_replace([' ', "\u{a0}", ','], ['', '', '.'], trim((string) $raw));

        return preg_match('~-?\d+(?:\.\d+)?~', $text, $m) ? (float) $m[0] : null;
    }

    private static function cleanText(string|int|float|null $raw): string
    {
        $text = str_replace(['İ', 'ı'], ['I', 'i'], (string) $raw);

        return trim(preg_replace('~\s+~u', ' ', $text) ?? '');
    }

    /** @param  array<int, string|int|float|null>  $row */
    private static function rowHasAnything(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private static function trimNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.');
    }

    private static function columnLetter(int $index): string
    {
        $letter = '';
        for ($i = $index; $i >= 0; $i = intdiv($i, 26) - 1) {
            $letter = chr(65 + $i % 26).$letter;
        }

        return $letter;
    }
}
