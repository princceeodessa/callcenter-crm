<?php

namespace App\Services;

use App\Models\NonClosure;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class NonClosureImportService
{
    public function importFromXlsx(UploadedFile $file, int $accountId, int $userId): array
    {
        return $this->importFromPath((string) $file->getRealPath(), $accountId, $userId);
    }

    public function importFromPath(string $path, int $accountId, int $userId): array
    {
        $users = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->get(['id', 'name', 'role']);

        $parsed = $this->parseWorkbookDetailed($path);
        $imported = 0;
        $updated = 0;

        foreach ($parsed['rows'] as $row) {
            $hash = $this->makeHash($accountId, $row);

            $payload = [
                'account_id' => $accountId,
                'entry_date' => $row['entry_date'],
                'address' => $row['address'],
                'reason' => $row['reason'],
                'measurer_user_id' => $this->resolveUserId($users, $row['measurer_name'], ['measurer', 'admin', 'main_operator', 'operator']),
                'measurer_name' => $row['measurer_name'],
                'responsible_user_id' => $this->resolveUserId($users, $row['responsible_name'], ['operator', 'main_operator', 'admin']),
                'responsible_name' => $row['responsible_name'],
                'comment' => $row['comment'],
                'follow_up_date' => $row['follow_up_date'],
                'result_status' => $row['result_status'],
                'special_calculation' => $row['special_calculation'],
                'source' => 'xlsx_import',
                'unique_hash' => $hash,
                'updated_by_user_id' => $userId,
            ];

            $existing = NonClosure::query()
                ->where('account_id', $accountId)
                ->where('unique_hash', $hash)
                ->first();

            if ($existing) {
                $existing->fill(array_filter($payload, fn ($value) => $value !== null && $value !== ''));
                $existing->updated_by_user_id = $userId;
                $existing->save();
                $updated++;
                continue;
            }

            $payload['created_by_user_id'] = $userId;
            NonClosure::create($payload);
            $imported++;
        }

        return [
            'imported' => $imported,
            'updated' => $updated,
            'total' => count($parsed['rows']),
            'sheet_counts' => $parsed['sheet_counts'],
        ];
    }

    public function summarizeWorkbook(string $path): array
    {
        $parsed = $this->parseWorkbookDetailed($path);

        return [
            'total' => count($parsed['rows']),
            'sheet_counts' => $parsed['sheet_counts'],
        ];
    }

    private function parseWorkbookDetailed(string $path): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('В PHP не включено расширение zip. Включите extension=zip в php.ini.');
        }

        if (!class_exists(\SimpleXMLElement::class)) {
            throw new \RuntimeException('В PHP не включено XML-расширение. Включите extension=simplexml / extension=xml.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Не удалось открыть XLSX-файл.');
        }

        $sharedStrings = $this->loadSharedStrings($zip);
        $sheetFiles = $this->sheetFiles($zip);
        $rows = [];
        $sheetCounts = [];
        $diagnostics = [];

        foreach ($sheetFiles as $sheetName => $sheetPath) {
            $xmlString = $zip->getFromName($sheetPath);
            if ($xmlString === false) {
                $sheetCounts[$sheetName] = 0;
                $diagnostics[$sheetName] = ['header_found' => false];
                continue;
            }

            $sheetRows = $this->parseSheetRows($xmlString, $sharedStrings);
            $headerMap = null;
            $headerRowIndex = null;
            $importable = 0;

            foreach ($sheetRows as $rowIndex => $cells) {
                if ($headerMap === null) {
                    $headerMap = $this->detectHeaderMap($cells);
                    if ($headerMap !== null) {
                        $headerRowIndex = $rowIndex + 1;
                    }
                    continue;
                }

                $record = $this->mapDataRow($cells, $headerMap, $sheetName);
                if (!$record) {
                    continue;
                }

                $rows[] = $record;
                $importable++;
            }

            $sheetCounts[$sheetName] = $importable;
            $diagnostics[$sheetName] = [
                'header_found' => $headerMap !== null,
                'header_row' => $headerRowIndex,
                'rows_in_sheet' => count($sheetRows),
                'importable_rows' => $importable,
            ];
        }

        $zip->close();

        if (count($rows) === 0) {
            $failedSheets = array_keys(array_filter($diagnostics, fn ($item) => !($item['header_found'] ?? false)));
            $message = 'Не удалось распознать строки для импорта из Excel.';
            if ($failedSheets) {
                $message .= ' Не найдены заголовки в листах: '.implode(', ', $failedSheets).'.';
            }
            $message .= ' Нужны колонки вроде «Адрес», «Причина незаключения», «Заключен/не заключен».';

            throw new \RuntimeException($message);
        }

        return [
            'rows' => $rows,
            'sheet_counts' => $sheetCounts,
            'sheet_diagnostics' => $diagnostics,
        ];
    }

    private function detectHeaderMap(array $cells): ?array
    {
        $normalized = [];
        foreach ($cells as $column => $value) {
            $normalized[$column] = $this->normalize((string) $value);
        }

        $map = [
            'date' => null,
            'address' => null,
            'reason' => null,
            'responsible' => null,
            'comment' => null,
            'follow_up_date' => null,
            'status' => null,
            'special' => null,
        ];

        foreach ($normalized as $column => $header) {
            if ($map['date'] === null && ($header === 'дата' || ($column === 'A' && $header === ''))) {
                $map['date'] = $column;
            }
            if ($map['address'] === null && str_starts_with($header, 'адрес')) {
                $map['address'] = $column;
            }
            if ($map['reason'] === null && (str_contains($header, 'причина незаключения') || str_contains($header, 'причина'))) {
                $map['reason'] = $column;
            }
            if ($map['responsible'] === null && str_contains($header, 'ответствен')) {
                $map['responsible'] = $column;
            }
            if ($map['comment'] === null && str_contains($header, 'комментар')) {
                $map['comment'] = $column;
            }
            if ($map['follow_up_date'] === null && (str_contains($header, 'повторной встречи') || str_contains($header, 'дата повтор'))) {
                $map['follow_up_date'] = $column;
            }
            if ($map['status'] === null && (str_contains($header, 'заключен/не заключен') || str_contains($header, 'заключен') || str_contains($header, 'не заключен'))) {
                $map['status'] = $column;
            }
            if ($map['special'] === null && (str_contains($header, 'спец просчет') || str_contains($header, 'спецпросчет') || str_contains($header, 'доп инф'))) {
                $map['special'] = $column;
            }
        }

        if (!$map['address'] || !$map['reason'] || !$map['status']) {
            return null;
        }

        return $map;
    }

    private function mapDataRow(array $cells, array $map, string $sheetName): ?array
    {
        $address = trim((string) $this->cellValue($cells, $map['address']));
        $reason = trim((string) $this->cellValue($cells, $map['reason']));
        $responsible = trim((string) $this->cellValue($cells, $map['responsible']));
        $comment = trim((string) $this->cellValue($cells, $map['comment']));
        $specialRaw = $this->cellValue($cells, $map['special']);
        $special = trim((string) $specialRaw);

        if (is_numeric($specialRaw)) {
            $special = rtrim(rtrim(number_format((float) $specialRaw, 2, '.', ''), '0'), '.');
        }

        if ($address === '' && $reason === '' && $responsible === '' && $comment === '' && $special === '') {
            return null;
        }

        return [
            'entry_date' => $this->parseDateValue($this->cellValue($cells, $map['date'])),
            'address' => $address,
            'reason' => $reason,
            'measurer_name' => trim($sheetName),
            'responsible_name' => $responsible,
            'comment' => $comment,
            'follow_up_date' => $this->parseDateValue($this->cellValue($cells, $map['follow_up_date'])),
            'result_status' => $this->mapResultStatus((string) $this->cellValue($cells, $map['status'])),
            'special_calculation' => $special,
        ];
    }

    private function parseDateValue(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            $ts = (int) round((((float) $value) - 25569) * 86400);
            return Carbon::createFromTimestampUTC($ts)->startOfDay();
        }

        $string = trim((string) $value);
        if ($string === '' || $string === '-' || $this->normalize($string) === 'нет') {
            return null;
        }

        try {
            if (preg_match('/(\d{2}\.\d{2}(?:\.\d{2,4})?)/u', $string, $matches)) {
                $datePart = $matches[1];
                $format = Str::length($datePart) === 5 ? 'd.m' : (Str::length($datePart) === 8 ? 'd.m.y' : 'd.m.Y');
                $date = Carbon::createFromFormat($format, $datePart);
                if ($format === 'd.m') {
                    $date->year((int) now()->year);
                }
                return $date->startOfDay();
            }

            return Carbon::parse($string)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function mapResultStatus(?string $raw): ?string
    {
        $normalized = $this->normalize((string) $raw);
        if ($normalized === '' || $normalized === '-' || $normalized === 'нет') {
            return null;
        }
        if (str_contains($normalized, 'не заключ')) {
            return 'not_concluded';
        }
        if (str_contains($normalized, 'заключ')) {
            return 'concluded';
        }
        return null;
    }

    private function resolveUserId($users, ?string $rawName, array $roles): ?int
    {
        $needle = $this->normalize((string) $rawName);
        if ($needle === '') {
            return null;
        }

        foreach ($users as $user) {
            if (!in_array($user->role, $roles, true)) {
                continue;
            }

            $name = $this->normalize((string) $user->name);
            if ($name === '' || $name === $needle || str_contains($name, $needle) || str_contains($needle, $name)) {
                return (int) $user->id;
            }

            foreach (array_filter(explode(' ', $name)) as $part) {
                if ($part === $needle || str_starts_with($part, $needle) || str_starts_with($needle, $part)) {
                    return (int) $user->id;
                }
            }
        }

        return null;
    }

    private function makeHash(int $accountId, array $row): string
    {
        return sha1(implode('|', [
            $accountId,
            $this->normalize((string) ($row['measurer_name'] ?? '')),
            optional($row['entry_date'])->format('Y-m-d'),
            $this->normalize((string) ($row['address'] ?? '')),
            $this->normalize((string) ($row['reason'] ?? '')),
            optional($row['follow_up_date'])->format('Y-m-d'),
        ]));
    }

    private function cellValue(array $cells, ?string $column): mixed
    {
        return $column ? ($cells[$column] ?? null) : null;
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = str_replace(['ё', "\n", "\r", "\t"], ['е', ' ', ' ', ' '], $value);
        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }

    private function loadSharedStrings(ZipArchive $zip): array
    {
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlString === false) {
            return [];
        }

        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return [];
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = [];

        foreach ($xml->children($namespace)->si as $si) {
            $parts = [];
            if (isset($si->t)) {
                $parts[] = (string) $si->t;
            }
            foreach ($si->r as $run) {
                if (isset($run->t)) {
                    $parts[] = (string) $run->t;
                }
            }
            $strings[] = trim(implode('', $parts));
        }

        return $strings;
    }

    private function sheetFiles(ZipArchive $zip): array
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if ($workbookXml === false || $relsXml === false) {
            return [];
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);
        if (!$workbook || !$rels) {
            return [];
        }

        $rels->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/package/2006/relationships');
        $relMap = [];
        foreach ($rels->xpath('//r:Relationship') ?: [] as $rel) {
            $attrs = $rel->attributes();
            $relMap[(string) $attrs['Id']] = 'xl/'.ltrim((string) $attrs['Target'], '/');
        }

        $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $result = [];
        foreach ($workbook->xpath('//a:sheets/a:sheet') ?: [] as $sheet) {
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $relationId = (string) $attrs['id'];
            $name = (string) $sheet['name'];
            if (isset($relMap[$relationId])) {
                $result[$name] = $relMap[$relationId];
            }
        }

        return $result;
    }

    private function parseSheetRows(string $xmlString, array $sharedStrings): array
    {
        $xml = simplexml_load_string($xmlString);
        if (!$xml) {
            return [];
        }

        $namespace = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $sheetData = $xml->children($namespace)->sheetData;
        if (!$sheetData) {
            return [];
        }

        $rows = [];
        foreach ($sheetData->row as $row) {
            $cells = [];
            foreach ($row->children($namespace)->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference) ?: $reference;
                $type = (string) $cell['t'];
                $valueNode = $cell->children($namespace)->v;
                $inlineNode = $cell->children($namespace)->is;

                if ($type === 's') {
                    $index = (int) ($valueNode ?? 0);
                    $cells[$column] = $sharedStrings[$index] ?? null;
                } elseif ($type === 'inlineStr') {
                    $parts = [];
                    if ($inlineNode && isset($inlineNode->t)) {
                        $parts[] = (string) $inlineNode->t;
                    }
                    if ($inlineNode) {
                        foreach ($inlineNode->r as $run) {
                            if (isset($run->t)) {
                                $parts[] = (string) $run->t;
                            }
                        }
                    }
                    $cells[$column] = trim(implode('', $parts));
                } else {
                    $cells[$column] = isset($valueNode) ? (string) $valueNode : null;
                }
            }

            if ($cells) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }
}
