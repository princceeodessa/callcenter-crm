<?php

namespace App\Services;

use App\Models\NonClosureWorkbook;
use App\Models\NonClosureWorkbookSheet;
use App\Models\NonClosureWorkspace;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use ZipArchive;

class NonClosureWorkbookImportService
{
    private const XLSX_NS = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
    private const REL_NS = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
    private const PKG_REL_NS = 'http://schemas.openxmlformats.org/package/2006/relationships';

    public function inspectWorkbook(string $path, ?string $sourceName = null): array
    {
        return $this->parseWorkbookSnapshot($path, $sourceName);
    }

    public function importUploadedWorkbook(
        UploadedFile $file,
        NonClosureWorkspace $workspace,
        User $user,
        ?string $title = null
    ): NonClosureWorkbook {
        $sourceName = $file->getClientOriginalName() ?: basename((string) $file->getRealPath());

        return $this->importFromPath(
            (string) $file->getRealPath(),
            $workspace,
            $user,
            $title,
            $sourceName
        );
    }

    public function importFromPath(
        string $path,
        NonClosureWorkspace $workspace,
        User $user,
        ?string $title = null,
        ?string $sourceName = null
    ): NonClosureWorkbook {
        $snapshot = $this->parseWorkbookSnapshot($path, $sourceName);
        $resolvedTitle = $this->resolveWorkbookTitle($title, $sourceName, $snapshot);
        $sourceHash = @sha1_file($path) ?: sha1($resolvedTitle.'|'.(string) @filesize($path));

        $workbook = NonClosureWorkbook::query()
            ->where('workspace_id', $workspace->id)
            ->where('title', $resolvedTitle)
            ->first();

        if (!$workbook) {
            $workbook = new NonClosureWorkbook();
        }

        $workbook->fill([
            'account_id' => $workspace->account_id,
            'workspace_id' => $workspace->id,
            'title' => $resolvedTitle,
            'source_name' => $sourceName ?: basename($path),
            'source_hash' => $sourceHash,
            'uploaded_by_user_id' => $user->id,
            'owner_user_id' => $workbook->owner_user_id ?: $user->id,
            'summary' => $snapshot['summary'],
            'imported_at' => now(),
        ]);
        $workbook->save();

        $workbook->sheets()->delete();

        foreach ($snapshot['sheets'] as $sheetData) {
            $sheet = $workbook->sheets()->create([
                'account_id' => $workspace->account_id,
                'name' => $sheetData['name'],
                'slug' => $sheetData['slug'],
                'category' => $sheetData['category'],
                'position' => $sheetData['position'],
                'owner_user_id' => $user->id,
                'header_row_index' => $sheetData['header_row_index'],
                'row_count' => $sheetData['row_count'],
                'column_count' => $sheetData['column_count'],
                'header' => $sheetData['header'],
                'rows' => $sheetData['rows'],
                'notes' => null,
                'preview_text' => $sheetData['preview_text'],
                'meta' => $sheetData['meta'],
            ]);

            $sheet->sharedUsers()->sync([
                $user->id => ['can_edit' => true],
            ]);
        }

        return $workbook->fresh([
            'owner:id,name',
            'uploadedBy:id,name',
            'sheets.owner:id,name',
        ]);
    }

    private function parseWorkbookSnapshot(string $path, ?string $sourceName = null): array
    {
        if (!class_exists(ZipArchive::class)) {
            throw new \RuntimeException('В PHP не включено расширение zip. Включите extension=zip в php.ini.');
        }

        if (!class_exists(\DOMDocument::class) || !class_exists(\SimpleXMLElement::class)) {
            throw new \RuntimeException('В PHP не включены XML-расширения. Включите extension=dom, extension=simplexml и extension=xml.');
        }

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('Не удалось открыть XLSX-файл.');
        }

        $sharedStrings = $this->loadSharedStrings($zip);
        $sheetFiles = $this->sheetFiles($zip);
        $sheets = [];
        $categoryCounts = [];
        $totalRows = 0;

        foreach ($sheetFiles as $position => $sheet) {
            $xmlString = $zip->getFromName($sheet['path']);
            if ($xmlString === false) {
                continue;
            }

            $rows = $this->parseSheetRows($xmlString, $sharedStrings);
            $snapshot = $this->buildSheetSnapshot($sheet['name'], $rows, $position);

            $sheets[] = $snapshot;
            $totalRows += $snapshot['row_count'];
            $categoryCounts[$snapshot['category']] = ($categoryCounts[$snapshot['category']] ?? 0) + 1;
        }

        $zip->close();

        return [
            'title' => $sourceName ? pathinfo($sourceName, PATHINFO_FILENAME) : null,
            'summary' => [
                'sheet_count' => count($sheets),
                'row_count' => $totalRows,
                'category_counts' => $categoryCounts,
                'source_name' => $sourceName ?: basename($path),
            ],
            'sheets' => $sheets,
        ];
    }

    private function buildSheetSnapshot(string $sheetName, array $rows, int $position): array
    {
        $columns = $this->orderedColumns($rows);
        $alignedRows = array_map(fn (array $row) => $this->alignRow($row, $columns), $rows);
        $headerRowIndex = $this->detectHeaderRowIndex($alignedRows);

        if ($headerRowIndex !== null) {
            $header = $this->buildHeader($alignedRows[$headerRowIndex] ?? [], $columns);
            $dataRows = array_slice($alignedRows, $headerRowIndex + 1);
        } else {
            $header = $this->genericHeader($columns);
            $dataRows = $alignedRows;
        }

        $dataRows = array_values(array_filter(array_map(
            fn (array $row) => $this->trimAlignedRow($row),
            $dataRows
        ), fn (array $row) => $this->rowHasContent($row)));

        $category = $this->detectCategory($sheetName, $header, $dataRows);

        return [
            'name' => $sheetName,
            'slug' => $this->sheetSlug($sheetName, $position),
            'category' => $category,
            'position' => $position,
            'header_row_index' => $headerRowIndex !== null ? $headerRowIndex + 1 : null,
            'row_count' => count($dataRows),
            'column_count' => count($header),
            'header' => $header,
            'rows' => $dataRows,
            'preview_text' => $this->buildPreviewText($header, $dataRows),
            'meta' => [
                'detected_header' => $headerRowIndex !== null,
                'source_sheet_name' => $sheetName,
                'column_letters' => $columns,
            ],
        ];
    }

    private function detectHeaderRowIndex(array $rows): ?int
    {
        $bestIndex = null;
        $bestScore = 0;
        $maxRows = min(15, count($rows));

        for ($index = 0; $index < $maxRows; $index++) {
            $row = $rows[$index] ?? [];
            $score = $this->headerScore($row);

            if ($score > $bestScore) {
                $bestScore = $score;
                $bestIndex = $index;
            }
        }

        return $bestScore >= 6 ? $bestIndex : null;
    }

    private function headerScore(array $row): int
    {
        $nonEmpty = array_values(array_filter($row, fn ($value) => trim((string) $value) !== ''));
        if (count($nonEmpty) < 2) {
            return 0;
        }

        $score = count($nonEmpty);
        $headerKeywords = [
            'контраг', 'список', 'телефон', 'адрес', 'город', 'менедж',
            'контакт', 'сумма', 'договор', 'дата', 'статус', 'коммент',
            'товар', 'наименование', 'источник', 'руководитель',
        ];

        foreach ($nonEmpty as $value) {
            $normalized = $this->normalizeText((string) $value);
            $isNumeric = (bool) preg_match('/^\d+(?:[.,]\d+)?$/u', $normalized);
            $hasLetters = (bool) preg_match('/\p{L}/u', $normalized);

            if ($hasLetters) {
                $score += 2;
            }

            if ($isNumeric) {
                $score -= 1;
            }

            foreach ($headerKeywords as $keyword) {
                if (str_contains($normalized, $keyword)) {
                    $score += 4;
                    break;
                }
            }
        }

        return $score;
    }

    private function detectCategory(string $sheetName, array $header, array $rows): string
    {
        $name = $this->normalizeText($sheetName);
        $headerText = $this->normalizeText(implode(' ', $header));

        if (str_contains($name, 'аналит')) {
            return NonClosureWorkbookSheet::CATEGORY_ANALYTICS;
        }

        if (str_contains($name, 'итог') || str_contains($headerText, 'итог')) {
            return NonClosureWorkbookSheet::CATEGORY_SUMMARY;
        }

        if (
            str_contains($name, 'список') ||
            str_contains($name, 'контраг') ||
            str_contains($headerText, 'телефон') ||
            str_contains($headerText, 'контраг') ||
            str_contains($headerText, 'наименование')
        ) {
            return NonClosureWorkbookSheet::CATEGORY_DIRECTORY;
        }

        if (
            str_contains($name, 'товар') ||
            str_contains($name, 'кондиционер') ||
            str_contains($name, 'доп продаж')
        ) {
            return NonClosureWorkbookSheet::CATEGORY_PRODUCTS;
        }

        foreach ([
            'янв', 'фев', 'мар', 'апр', 'май', 'июн', 'июл', 'авг', 'сен', 'сент',
            'окт', 'ноя', 'дек', 'киров', 'удм', 'ижевск', 'тюмень', 'пермь', 'екб', 'роман',
        ] as $needle) {
            if (str_contains($name, $needle)) {
                return NonClosureWorkbookSheet::CATEGORY_SALES;
            }
        }

        if (count($rows) > 0 && count($header) >= 3) {
            return NonClosureWorkbookSheet::CATEGORY_SALES;
        }

        return NonClosureWorkbookSheet::CATEGORY_OTHER;
    }

    private function buildHeader(array $row, array $columns): array
    {
        $labels = [];

        foreach ($columns as $index => $column) {
            $labels[] = $this->normalizeHeaderLabel(
                (string) ($row[$index] ?? ''),
                $this->columnLabelFromIndex($index)
            );
        }

        return $this->uniqueLabels($labels);
    }

    private function genericHeader(array $columns): array
    {
        return array_map(
            fn (int $index) => 'Колонка '.$this->columnLabelFromIndex($index),
            array_keys($columns)
        );
    }

    private function normalizeHeaderLabel(string $value, string $fallback): string
    {
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?: '');

        return $value !== '' ? $value : 'Колонка '.$fallback;
    }

    private function uniqueLabels(array $labels): array
    {
        $seen = [];

        foreach ($labels as $index => $label) {
            $base = $label;
            $suffix = 2;

            while (isset($seen[$label])) {
                $label = $base.' '.$suffix;
                $suffix++;
            }

            $seen[$label] = true;
            $labels[$index] = $label;
        }

        return $labels;
    }

    private function buildPreviewText(array $header, array $rows): string
    {
        $parts = [];

        foreach (array_slice($rows, 0, 5) as $row) {
            $cells = [];

            foreach ($row as $index => $value) {
                $value = trim((string) $value);
                if ($value === '') {
                    continue;
                }

                $cells[] = ($header[$index] ?? ('Колонка '.($index + 1))).': '.$value;
            }

            if (!empty($cells)) {
                $parts[] = implode(' | ', array_slice($cells, 0, 4));
            }
        }

        return implode(' || ', $parts);
    }

    private function orderedColumns(array $rows): array
    {
        $columns = [];

        foreach ($rows as $row) {
            foreach (array_keys($row) as $column) {
                $columns[$column] = $this->columnIndex($column);
            }
        }

        asort($columns);

        return array_keys($columns);
    }

    private function alignRow(array $row, array $columns): array
    {
        $aligned = [];

        foreach ($columns as $column) {
            $value = $row[$column] ?? null;
            $aligned[] = $this->formatCellValue($value);
        }

        return $aligned;
    }

    private function trimAlignedRow(array $row): array
    {
        return array_map(function ($value) {
            $value = trim((string) $value);
            return $value === '' ? '' : $value;
        }, $row);
    }

    private function rowHasContent(array $row): bool
    {
        foreach ($row as $value) {
            if (trim((string) $value) !== '') {
                return true;
            }
        }

        return false;
    }

    private function formatCellValue(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_numeric($value)) {
            $numeric = (string) $value;
            return str_contains($numeric, '.') ? rtrim(rtrim($numeric, '0'), '.') : $numeric;
        }

        return trim((string) preg_replace('/\s+/u', ' ', (string) $value));
    }

    private function resolveWorkbookTitle(?string $title, ?string $sourceName, array $snapshot): string
    {
        $title = trim((string) $title);
        if ($title !== '') {
            return $title;
        }

        $sourceTitle = trim((string) ($snapshot['title'] ?? ''));
        if ($sourceTitle !== '') {
            return $sourceTitle;
        }

        if ($sourceName) {
            return pathinfo($sourceName, PATHINFO_FILENAME);
        }

        return 'Импортированная книга';
    }

    private function sheetSlug(string $sheetName, int $position): string
    {
        $slug = Str::slug($sheetName);

        return $slug !== '' ? $slug : 'sheet-'.($position + 1);
    }

    private function normalizeText(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = str_replace(['ё', "\n", "\r", "\t"], ['е', ' ', ' ', ' '], $value);

        return preg_replace('/\s+/u', ' ', $value) ?: '';
    }

    private function columnIndex(string $label): int
    {
        $label = preg_replace('/[^A-Z]/', '', strtoupper(trim($label))) ?: '';
        if ($label === '') {
            return 0;
        }

        $index = 0;

        foreach (str_split($label) as $char) {
            $index = ($index * 26) + (ord($char) - 64);
        }

        return max(0, $index - 1);
    }

    private function columnLabelFromIndex(int $index): string
    {
        $index++;
        $label = '';

        while ($index > 0) {
            $mod = ($index - 1) % 26;
            $label = chr(65 + $mod).$label;
            $index = intdiv($index - 1, 26);
        }

        return $label;
    }

    private function loadSharedStrings(ZipArchive $zip): array
    {
        $xmlString = $zip->getFromName('xl/sharedStrings.xml');
        if ($xmlString === false) {
            return [];
        }

        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xmlString, LIBXML_NOCDATA | LIBXML_COMPACT)) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('a', self::XLSX_NS);

        $strings = [];
        foreach ($xpath->query('/a:sst/a:si') ?: [] as $itemNode) {
            $parts = [];

            foreach ($xpath->query('.//a:t', $itemNode) ?: [] as $textNode) {
                $parts[] = $textNode->textContent;
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

        $rels->registerXPathNamespace('r', self::PKG_REL_NS);
        $relMap = [];

        foreach ($rels->xpath('//r:Relationship') ?: [] as $rel) {
            $attrs = $rel->attributes();
            $relMap[(string) $attrs['Id']] = 'xl/'.ltrim((string) $attrs['Target'], '/');
        }

        $workbook->registerXPathNamespace('a', self::XLSX_NS);
        $workbook->registerXPathNamespace('r', self::REL_NS);

        $result = [];
        $position = 0;

        foreach ($workbook->xpath('//a:sheets/a:sheet') ?: [] as $sheet) {
            $attrs = $sheet->attributes(self::REL_NS);
            $relationId = (string) $attrs['id'];
            $name = (string) $sheet['name'];

            if (!isset($relMap[$relationId])) {
                continue;
            }

            $result[] = [
                'name' => $name,
                'path' => $relMap[$relationId],
                'position' => $position,
            ];
            $position++;
        }

        return $result;
    }

    private function parseSheetRows(string $xmlString, array $sharedStrings): array
    {
        $dom = new \DOMDocument();
        if (!@$dom->loadXML($xmlString, LIBXML_NOCDATA | LIBXML_COMPACT)) {
            return [];
        }

        $xpath = new \DOMXPath($dom);
        $xpath->registerNamespace('a', self::XLSX_NS);

        $rows = [];

        foreach ($xpath->query('/a:worksheet/a:sheetData/a:row') ?: [] as $rowNode) {
            $cells = [];
            $fallbackColumnIndex = 0;

            foreach ($xpath->query('./a:c', $rowNode) ?: [] as $cellNode) {
                $reference = trim((string) $cellNode->getAttribute('r'));
                $column = $this->extractColumnReference($reference, $fallbackColumnIndex);
                $fallbackColumnIndex = $this->columnIndex($column) + 1;

                $type = trim((string) $cellNode->getAttribute('t'));
                $valueNode = $xpath->query('./a:v', $cellNode)->item(0);
                $inlineStringNode = $xpath->query('./a:is', $cellNode)->item(0);

                if ($type === 's') {
                    $sharedIndex = (int) trim((string) ($valueNode?->textContent ?? '0'));
                    $value = $sharedStrings[$sharedIndex] ?? null;
                } elseif ($type === 'inlineStr') {
                    $value = $this->extractInlineString($xpath, $inlineStringNode);
                } else {
                    $value = $valueNode ? $valueNode->textContent : null;
                }

                $cells[$column] = $value;
            }

            if (!empty($cells)) {
                $rows[] = $cells;
            }
        }

        return $rows;
    }

    private function extractInlineString(\DOMXPath $xpath, ?\DOMNode $inlineStringNode): ?string
    {
        if (!$inlineStringNode) {
            return null;
        }

        $parts = [];
        foreach ($xpath->query('.//a:t', $inlineStringNode) ?: [] as $textNode) {
            $parts[] = $textNode->textContent;
        }

        $value = trim(implode('', $parts));

        return $value !== '' ? $value : null;
    }

    private function extractColumnReference(string $reference, int $fallbackColumnIndex): string
    {
        $column = preg_replace('/[^A-Z]/', '', strtoupper($reference)) ?: '';

        return $column !== '' ? $column : $this->columnLabelFromIndex($fallbackColumnIndex);
    }
}
