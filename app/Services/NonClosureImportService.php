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
        return $this->importFromPath($file->getRealPath(), $accountId, $userId);
    }

    public function importFromPath(string $path, int $accountId, int $userId): array
    {
        $users = User::query()
            ->where('account_id', $accountId)
            ->where('is_active', true)
            ->get(['id', 'name', 'role']);

        $parsed = $this->parseWorkbookDetailed($path);
        $parsedRows = $parsed['rows'];

        $imported = 0;
        $updated = 0;

        foreach ($parsedRows as $row) {
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
                'result_status' => null,
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
                $existing->fill(array_filter($payload, fn ($v) => $v !== null && $v !== ''));
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
            'total' => count($parsedRows),
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

        foreach ($sheetFiles as $sheetName => $sheetPath) {
            $xmlString = $zip->getFromName($sheetPath);
            if ($xmlString === false) {
                $sheetCounts[$sheetName] = 0;
                continue;
            }

            $sheetRows = $this->parseSheetRows($xmlString, $sharedStrings);
            $headerMap = null;
            $importableForSheet = 0;

            foreach ($sheetRows as $cells) {
                if ($headerMap === null) {
                    $headerMap = $this->detectHeaderMap($cells);
                    continue;
                }

                if ($headerMap === null) {
                    continue;
                }

                $record = $this->mapDataRow($cells, $headerMap, trim((string) $sheetName));
                if (!$record) {
                    continue;
                }

                $rows[] = $record;
                $importableForSheet++;
            }

            $sheetCounts[trim((string) $sheetName)] = $importableForSheet;
        }

        $zip->close();

        return [
            'rows' => $rows,
            'sheet_counts' => $sheetCounts,
        ];
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

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $strings = [];

        foreach ($xml->children($ns)->si as $si) {
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
            $relMap[(string) $attrs['Id']] = 'xl/' . ltrim((string) $attrs['Target'], '/');
        }

        $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
        $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');

        $result = [];
        foreach ($workbook->xpath('//a:sheets/a:sheet') ?: [] as $sheet) {
            $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
            $rid = (string) $attrs['id'];
            $name = (string) $sheet['name'];

            if (isset($relMap[$rid])) {
                $result[$name] = $relMap[$rid];
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

        return $this->parseWorksheetRows($xml, $sharedStrings);
    }

    private function parseWorksheetRows(\SimpleXMLElement $xml, array $sharedStrings): array
    {
        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rows = [];
        $sheetData = $xml->children($ns)->sheetData;

        if (!$sheetData) {
            return $rows;
        }

        foreach ($sheetData->row as $row) {
            $cells = [];

            foreach ($row->children($ns)->c as $cell) {
                $ref = (string) $cell['r'];
                $col = preg_replace('/\d+/', '', $ref) ?: $ref;
                $type = (string) $cell['t'];
                $value = null;

                $v = $cell->children($ns)->v;
                $is = $cell->children($ns)->is;

                if ($type === 's') {
                    $idx = (int) ($v ?? 0);
                    $value = $sharedStrings[$idx] ?? null;
                } elseif ($type === 'inlineStr') {
                    $parts = [];

                    if ($is && isset($is->t)) {
                        $parts[] = (string) $is->t;
                    }

                    if ($is) {
                        foreach ($is->r as $run) {
                            if (isset($run->t)) {
                                $parts[] = (string) $run->t;
                            }
                        }
                    }

                    $value = trim(implode('', $parts));
                } else {
                    $value = isset($v) ? (string) $v : null;
                }

                $cells[$col] = $value;
            }

            if (!empty($cells)) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    private function detectHeaderMap(array $cells): ?array
    {
        $normalized = [];
        foreach ($cells as $col => $value) {
            $normalized[$col] = $this->normalize((string) $value);
        }

        $addressCol = null;
        $reasonCol = null;
        $statusCol = null;

        foreach ($normalized as $col => $header) {
            if ($addressCol === null && str_starts_with($header, 'адрес')) {
                $addressCol = $col;
            }

            if ($reasonCol === null && str_contains($header, 'причина незаключения')) {
                $reasonCol = $col;
            }

            if ($statusCol === null && str_contains($header, 'заключен/не заключен')) {
                $statusCol = $col;
            }
        }

        if (!$addressCol || !$reasonCol || !$statusCol) {
            return null;
        }

        $map = [];
        foreach ($normalized as $col => $header) {
            $map[$header] = $col;
        }

        $map['адрес'] = $addressCol;
        $map['причина незаключения'] = $reasonCol;
        $map['заключен/не заключен'] = $statusCol;

        return $map;
    }

    private function mapDataRow(array $cells, array $map, string $sheetName): ?array
    {
        $address = trim((string) $this->cellValue($cells, $map['адрес'] ?? null));
        $reason = trim((string) $this->cellValue($cells, $map['причина незаключения'] ?? null));
        $responsible = trim((string) $this->cellValue($cells, $map['ответственный (кто звонил из менеджеров)'] ?? null));
        $comment = trim((string) $this->cellValue($cells, $map['комментарий'] ?? null));

        $specialCol = $map['спец просчет']
            ?? $map['спецпросчет']
            ?? $map['доп инфа']
            ?? null;
        $special = trim((string) $this->cellValue($cells, $specialCol));

        $statusRaw = trim((string) $this->cellValue($cells, $map['заключен/не заключен'] ?? null));
        $statusNorm = $this->normalize($statusRaw);

        if ($statusNorm !== '' && $statusNorm !== '-') {
            return null;
        }

        if ($address === '' && $reason === '' && $responsible === '' && $comment === '' && $special === '') {
            return null;
        }

        return [
            'entry_date' => $this->parseDateValue($this->cellValue($cells, $map['дата'] ?? null)),
            'address' => $address,
            'reason' => $reason,
            'measurer_name' => trim($sheetName),
            'responsible_name' => $responsible,
            'comment' => $comment,
            'follow_up_date' => $this->parseDateValue($this->cellValue($cells, $map['дата повторной встречи'] ?? null)),
            'special_calculation' => $special,
        ];
    }

    private function cellValue(array $cells, ?string $column): mixed
    {
        if (!$column) {
            return null;
        }

        return $cells[$column] ?? null;
    }

    private function parseDateValue(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }

        if (is_numeric($value) && (float) $value > 20000) {
            $days = (float) $value;
            $ts = (int) round(($days - 25569) * 86400);
            return Carbon::createFromTimestampUTC($ts)->startOfDay();
        }

        $string = trim((string) $value);
        if ($string === '' || $string === '-' || $this->normalize($string) === 'нет') {
            return null;
        }

        try {
            if (preg_match('/(\d{2}\.\d{2}(?:\.\d{2,4})?)/u', $string, $m)) {
                $datePart = $m[1];
                $format = Str::length($datePart) === 5 ? 'd.m' : (Str::length($datePart) === 8 ? 'd.m.y' : 'd.m.Y');
                $dt = Carbon::createFromFormat($format, $datePart);
                if ($format === 'd.m') {
                    $dt->year((int) now()->year);
                }
                return $dt->startOfDay();
            }

            return Carbon::parse($string)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
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
            if ($name === '') {
                continue;
            }

            if ($name === $needle || str_contains($name, $needle) || str_contains($needle, $name)) {
                return (int) $user->id;
            }

            $parts = array_filter(explode(' ', $name));
            foreach ($parts as $part) {
                if ($part === $needle || str_starts_with($part, $needle) || str_starts_with($needle, $part)) {
                    return (int) $user->id;
                }
            }
        }

        return null;
    }

    private function makeHash(int $accountId, array $row): string
    {
        $parts = [
            $accountId,
            $this->normalize((string) ($row['measurer_name'] ?? '')),
            optional($row['entry_date'])->format('Y-m-d'),
            $this->normalize((string) ($row['address'] ?? '')),
            $this->normalize((string) ($row['reason'] ?? '')),
            optional($row['follow_up_date'])->format('Y-m-d'),
        ];

        return sha1(implode('|', $parts));
    }

    private function normalize(string $value): string
    {
        $value = trim(mb_strtolower($value));
        $value = str_replace(['ё', "\n", "\r", "\t"], ['е', ' ', ' ', ' '], $value);
        $value = preg_replace('/\s+/u', ' ', $value) ?: '';

        return $value;
    }
}
