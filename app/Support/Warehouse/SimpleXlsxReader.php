<?php

namespace App\Support\Warehouse;

/**
 * Минимальный читатель .xlsx без внешних зависимостей (ZipArchive + SimpleXML).
 * Возвращает строки первого листа как массивы значений (числа приводятся к int/float,
 * строки берутся из sharedStrings / inline). Достаточно для простых прайс-таблиц.
 */
class SimpleXlsxReader
{
    /** @return array<int, array<int, string|int|float|null>> */
    public static function rows(string $path, int $maxCols = 8): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Не удалось открыть файл .xlsx');
        }

        // Пул общих строк
        $shared = [];
        $ss = $zip->getFromName('xl/sharedStrings.xml');
        if ($ss !== false) {
            $xml = simplexml_load_string($ss);
            if ($xml !== false) {
                foreach ($xml->si as $si) {
                    if (isset($si->t)) {
                        $shared[] = (string) $si->t;
                    } else {
                        $txt = '';
                        foreach ($si->r as $r) {
                            $txt .= (string) $r->t;
                        }
                        $shared[] = $txt;
                    }
                }
            }
        }

        // Путь к первому листу (через rels; фолбэк — sheet1.xml)
        $sheetPath = 'xl/worksheets/sheet1.xml';
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($wb !== false && $rels !== false) {
            $wbx = simplexml_load_string($wb);
            $relx = simplexml_load_string($rels);
            if ($wbx !== false && $relx !== false && isset($wbx->sheets->sheet[0])) {
                $rid = (string) $wbx->sheets->sheet[0]
                    ->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
                foreach ($relx->Relationship as $rel) {
                    if ((string) $rel['Id'] === $rid) {
                        $target = str_replace('..', '', (string) $rel['Target']);
                        $sheetPath = str_starts_with($target, '/') ? ltrim($target, '/') : 'xl/'.ltrim($target, '/');
                        break;
                    }
                }
            }
        }

        $sheet = $zip->getFromName($sheetPath);
        $zip->close();
        if ($sheet === false) {
            throw new \RuntimeException('Лист не найден внутри .xlsx');
        }
        $xml = simplexml_load_string($sheet);
        if ($xml === false) {
            throw new \RuntimeException('Не удалось разобрать лист .xlsx');
        }

        $rows = [];
        foreach ($xml->sheetData->row as $row) {
            $vals = array_fill(0, $maxCols, null);
            foreach ($row->c as $c) {
                $ref = (string) $c['r'];
                $col = self::colIndex(preg_replace('/\d+/', '', $ref));
                if ($col === null || $col >= $maxCols) {
                    continue;
                }
                $t = (string) $c['t'];
                if ($t === 's') {
                    $v = $shared[(int) $c->v] ?? null;
                } elseif ($t === 'inlineStr') {
                    $v = isset($c->is->t) ? (string) $c->is->t : null;
                } elseif (isset($c->v)) {
                    $v = (string) $c->v;
                    if (is_numeric($v)) {
                        $v = $v + 0; // int|float
                    }
                } else {
                    $v = null;
                }
                $vals[$col] = $v;
            }
            $rows[] = $vals;
        }

        return $rows;
    }

    private static function colIndex(string $letters): ?int
    {
        $letters = strtoupper(trim($letters));
        if ($letters === '') {
            return null;
        }
        $n = 0;
        foreach (str_split($letters) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }
}
