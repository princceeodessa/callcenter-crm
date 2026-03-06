<?php
// Standalone direct importer for non-closures from XLSX into Laravel app DB.
// Usage from project root:
// php /path/to/import_nonclosures_direct.php "C:\\path\\file.xlsx" 1 1 --dry-run

use Illuminate\Contracts\Console\Kernel;

if ($argc < 4) {
    fwrite(STDERR, "Usage: php import_nonclosures_direct.php <xlsx_path> <account_id> <user_id> [--dry-run]\n");
    exit(1);
}

$xlsxPath = $argv[1];
$accountId = (int)$argv[2];
$userId = (int)$argv[3];
$dryRun = in_array('--dry-run', $argv, true);

if (!file_exists($xlsxPath)) {
    fwrite(STDERR, "File not found: {$xlsxPath}\n");
    exit(1);
}
if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "PHP extension zip is not enabled.\n");
    exit(1);
}

require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

/** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\User> $users */
$users = \App\Models\User::query()
    ->where('account_id', $accountId)
    ->where('is_active', true)
    ->get(['id', 'name', 'role']);

$rowsBySheet = parseWorkbook($xlsxPath);
$total = 0;
$imported = 0;
$updated = 0;

fwrite(STDOUT, "Сводка по файлу:\n");
foreach ($rowsBySheet as $sheetName => $rows) {
    fwrite(STDOUT, " - {$sheetName}: " . count($rows) . "\n");
    $total += count($rows);
}
fwrite(STDOUT, "Итого строк к импорту: {$total}\n\n");

if ($dryRun) {
    fwrite(STDOUT, "Dry-run: импорт в БД не выполнялся.\n");
    exit(0);
}

foreach ($rowsBySheet as $sheetName => $rows) {
    foreach ($rows as $row) {
        $hash = makeHash($accountId, $row);

        $payload = [
            'account_id' => $accountId,
            'entry_date' => $row['entry_date'],
            'address' => $row['address'],
            'reason' => $row['reason'],
            'measurer_user_id' => resolveUserId($users, $row['measurer_name'], ['measurer', 'admin', 'main_operator', 'operator']),
            'measurer_name' => $row['measurer_name'],
            'responsible_user_id' => resolveUserId($users, $row['responsible_name'], ['operator', 'main_operator', 'admin']),
            'responsible_name' => $row['responsible_name'],
            'comment' => $row['comment'],
            'follow_up_date' => $row['follow_up_date'],
            'result_status' => null,
            'special_calculation' => $row['special_calculation'],
            'source' => 'xlsx_import',
            'unique_hash' => $hash,
            'updated_by_user_id' => $userId,
        ];

        $existing = \App\Models\NonClosure::query()
            ->where('account_id', $accountId)
            ->where('unique_hash', $hash)
            ->first();

        if ($existing) {
            $existing->fill(array_filter($payload, fn($v) => $v !== null && $v !== ''));
            $existing->updated_by_user_id = $userId;
            $existing->save();
            $updated++;
            continue;
        }

        $payload['created_by_user_id'] = $userId;
        \App\Models\NonClosure::create($payload);
        $imported++;
    }
}

fwrite(STDOUT, "Импорт завершён: добавлено {$imported}, обновлено {$updated}, всего обработано {$total}.\n");
exit(0);

function parseWorkbook(string $path): array
{
    $zip = new ZipArchive();
    if ($zip->open($path) !== true) {
        throw new RuntimeException('Не удалось открыть XLSX-файл.');
    }

    $sharedStrings = loadSharedStrings($zip);
    $sheetFiles = sheetFiles($zip);
    $result = [];

    foreach ($sheetFiles as $sheetName => $sheetPath) {
        $xmlString = $zip->getFromName($sheetPath);
        if ($xmlString === false) {
            continue;
        }
        $sheetRows = parseSheetRows($xmlString, $sharedStrings);
        $headerMap = null;
        $rows = [];
        foreach ($sheetRows as $cells) {
            if ($headerMap === null) {
                $headerMap = detectHeaderMap($cells);
                continue;
            }
            $record = mapDataRow($cells, $headerMap, trim((string)$sheetName));
            if ($record) {
                $rows[] = $record;
            }
        }
        $result[trim((string)$sheetName)] = $rows;
    }

    $zip->close();
    return $result;
}

function loadSharedStrings(ZipArchive $zip): array
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
            $parts[] = (string)$si->t;
        }
        foreach ($si->r as $run) {
            if (isset($run->t)) {
                $parts[] = (string)$run->t;
            }
        }
        $strings[] = trim(implode('', $parts));
    }
    return $strings;
}

function sheetFiles(ZipArchive $zip): array
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
        $relMap[(string)$attrs['Id']] = 'xl/' . ltrim((string)$attrs['Target'], '/');
    }

    $workbook->registerXPathNamespace('a', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');
    $workbook->registerXPathNamespace('r', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships');
    $result = [];
    foreach ($workbook->xpath('//a:sheets/a:sheet') ?: [] as $sheet) {
        $attrs = $sheet->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships');
        $rid = (string)$attrs['id'];
        $name = (string)$sheet['name'];
        if (isset($relMap[$rid])) {
            $result[$name] = $relMap[$rid];
        }
    }
    return $result;
}

function parseSheetRows(string $xmlString, array $sharedStrings): array
{
    $xml = simplexml_load_string($xmlString);
    if (!$xml) {
        return [];
    }
    $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    $rows = [];
    $sheetData = $xml->children($ns)->sheetData;
    if (!$sheetData) {
        return [];
    }
    foreach ($sheetData->row as $row) {
        $cells = [];
        foreach ($row->children($ns)->c as $cell) {
            $ref = (string)$cell['r'];
            $col = preg_replace('/\d+/', '', $ref) ?: $ref;
            $type = (string)$cell['t'];
            $value = null;
            $v = $cell->children($ns)->v;
            $is = $cell->children($ns)->is;

            if ($type === 's') {
                $idx = (int)($v ?? 0);
                $value = $sharedStrings[$idx] ?? null;
            } elseif ($type === 'inlineStr') {
                $parts = [];
                if ($is && isset($is->t)) {
                    $parts[] = (string)$is->t;
                }
                if ($is) {
                    foreach ($is->r as $run) {
                        if (isset($run->t)) {
                            $parts[] = (string)$run->t;
                        }
                    }
                }
                $value = trim(implode('', $parts));
            } else {
                $value = isset($v) ? (string)$v : null;
            }
            $cells[$col] = $value;
        }
        if (!empty($cells)) {
            $rows[] = $cells;
        }
    }
    return $rows;
}

function detectHeaderMap(array $cells): ?array
{
    $normalized = [];
    foreach ($cells as $col => $value) {
        $normalized[$col] = normalize((string)$value);
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

function mapDataRow(array $cells, array $map, string $sheetName): ?array
{
    $address = trim((string)cellValue($cells, $map['адрес'] ?? null));
    $reason = trim((string)cellValue($cells, $map['причина незаключения'] ?? null));
    $responsible = trim((string)cellValue($cells, $map['ответственный (кто звонил из менеджеров)'] ?? null));
    $comment = trim((string)cellValue($cells, $map['комментарий'] ?? null));
    $specialCol = $map['спец просчет'] ?? $map['доп инфа'] ?? null;
    $special = trim((string)cellValue($cells, $specialCol));

    $statusRaw = trim((string)cellValue($cells, $map['заключен/не заключен'] ?? null));
    $statusNorm = normalize($statusRaw);
    if ($statusNorm !== '' && $statusNorm !== '-') {
        return null;
    }

    if ($address === '' && $reason === '' && $responsible === '' && $comment === '' && $special === '') {
        return null;
    }

    return [
        'entry_date' => parseDateValue(cellValue($cells, $map['дата'] ?? null)),
        'address' => $address,
        'reason' => $reason,
        'measurer_name' => trim($sheetName),
        'responsible_name' => $responsible,
        'comment' => $comment,
        'follow_up_date' => parseDateValue(cellValue($cells, $map['дата повторной встречи'] ?? null)),
        'special_calculation' => $special,
    ];
}

function cellValue(array $cells, ?string $column): mixed
{
    if (!$column) {
        return null;
    }
    return $cells[$column] ?? null;
}

function parseDateValue(mixed $value): ?Carbon\Carbon
{
    if ($value === null) {
        return null;
    }
    if (is_numeric($value) && (float)$value > 20000) {
        $days = (float)$value;
        $ts = (int) round(($days - 25569) * 86400);
        return Carbon\Carbon::createFromTimestampUTC($ts)->startOfDay();
    }

    $string = trim((string)$value);
    if ($string === '' || $string === '-') {
        return null;
    }

    try {
        if (preg_match('/^\d{2}\.\d{2}(?:\.\d{2,4})?$/', $string)) {
            $format = mb_strlen($string) === 5 ? 'd.m' : (mb_strlen($string) === 8 ? 'd.m.y' : 'd.m.Y');
            $dt = Carbon\Carbon::createFromFormat($format, $string);
            if ($format === 'd.m') {
                $dt->year((int) date('Y'));
            }
            return $dt->startOfDay();
        }
        return Carbon\Carbon::parse($string)->startOfDay();
    } catch (Throwable $e) {
        return null;
    }
}

function resolveUserId($users, ?string $rawName, array $roles): ?int
{
    $needle = normalize((string)$rawName);
    if ($needle === '') {
        return null;
    }
    foreach ($users as $user) {
        if (!in_array($user->role, $roles, true)) {
            continue;
        }
        $name = normalize((string)$user->name);
        if ($name === '') {
            continue;
        }
        if ($name === $needle || str_contains($name, $needle) || str_contains($needle, $name)) {
            return (int)$user->id;
        }
        $parts = array_filter(explode(' ', $name));
        foreach ($parts as $part) {
            if ($part === $needle || str_starts_with($part, $needle) || str_starts_with($needle, $part)) {
                return (int)$user->id;
            }
        }
    }
    return null;
}

function makeHash(int $accountId, array $row): string
{
    $parts = [
        $accountId,
        normalize((string)($row['measurer_name'] ?? '')),
        $row['entry_date'] ? $row['entry_date']->format('Y-m-d') : '',
        normalize((string)($row['address'] ?? '')),
        normalize((string)($row['reason'] ?? '')),
        $row['follow_up_date'] ? $row['follow_up_date']->format('Y-m-d') : '',
    ];
    return sha1(implode('|', $parts));
}

function normalize(string $value): string
{
    $value = trim(mb_strtolower($value));
    $value = str_replace(['ё', "\n", "\r", "\t"], ['е', ' ', ' ', ' '], $value);
    $value = preg_replace('/\s+/u', ' ', $value) ?: '';
    return $value;
}
