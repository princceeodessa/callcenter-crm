<?php

namespace App\Support\Export;

use RuntimeException;
use ZipArchive;

/**
 * Минимальный .xlsx из одного листа (пара к SimpleXlsxReader): первая строка — жирная шапка с автофильтром и
 * закреплением, все ячейки — текст, поэтому номера телефонов Excel не превращает в «7,9E+10». Без сторонних
 * библиотек — нужен только zip.
 */
final class SimpleXlsxWriter
{
    /**
     * @param list<list<string>> $rows   первая строка — шапка
     * @param list<int>          $widths ширина колонок в символах
     */
    public static function build(string $sheetName, array $rows, array $widths = []): string
    {
        $tmp = tempnam(sys_get_temp_dir(), 'xlsx');
        $zip = new ZipArchive();
        if ($zip->open($tmp, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Не удалось собрать xlsx');
        }
        $x = fn (string $s) => htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
        $col = function (int $i): string {
            $s = '';
            for ($i++; $i > 0; $i = intdiv($i - 1, 26)) {
                $s = chr(65 + ($i - 1) % 26).$s;
            }

            return $s;
        };
        $ncols = max(1, ...array_map('count', $rows ?: [[]]));
        $last = $col($ncols - 1).max(1, count($rows));
        $sheetName = mb_substr(preg_replace('/[\\\\\/?*\[\]:]/u', ' ', $sheetName), 0, 31);

        $cols = '';
        foreach ($widths as $i => $w) {
            $cols .= '<col min="'.($i + 1).'" max="'.($i + 1).'" width="'.(int) $w.'" customWidth="1"/>';
        }
        $data = '';
        foreach ($rows as $r => $cells) {
            $data .= '<row r="'.($r + 1).'">';
            foreach (array_values($cells) as $c => $value) {
                $data .= '<c r="'.$col($c).($r + 1).'" t="inlineStr"'.($r === 0 ? ' s="1"' : '').'><is><t xml:space="preserve">'.$x((string) $value).'</t></is></c>';
            }
            $data .= '</row>';
        }

        $ns = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $head = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'."\n";
        $zip->addFromString('[Content_Types].xml', $head.'<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            .'<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            .'<Default Extension="xml" ContentType="application/xml"/>'
            .'<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            .'<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            .'<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            .'</Types>');
        $zip->addFromString('_rels/.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="'.$rel.'/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $zip->addFromString('xl/workbook.xml', $head.'<workbook xmlns="'.$ns.'" xmlns:r="'.$rel.'">'
            .'<sheets><sheet name="'.$x($sheetName).'" sheetId="1" r:id="rId1"/></sheets>'
            .'<definedNames><definedName name="_xlnm._FilterDatabase" localSheetId="0" hidden="1">\''.$x(str_replace("'", "''", $sheetName)).'\'!$A$1:$'.preg_replace('/(\d+)$/', '\$$1', $last).'</definedName></definedNames>'
            .'</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', $head.'<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            .'<Relationship Id="rId1" Type="'.$rel.'/worksheet" Target="worksheets/sheet1.xml"/>'
            .'<Relationship Id="rId2" Type="'.$rel.'/styles" Target="styles.xml"/></Relationships>');
        $zip->addFromString('xl/styles.xml', $head.'<styleSheet xmlns="'.$ns.'">'
            .'<fonts count="2"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><name val="Calibri"/></font></fonts>'
            .'<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
            .'<borders count="1"><border><left/><right/><top/><bottom/><diagonal/></border></borders>'
            .'<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            .'<cellXfs count="2"><xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'
            .'<xf numFmtId="49" fontId="1" fillId="0" borderId="0" xfId="0" applyFont="1" applyNumberFormat="1"/></cellXfs>'
            .'<cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles>'
            .'</styleSheet>');
        $zip->addFromString('xl/worksheets/sheet1.xml', $head.'<worksheet xmlns="'.$ns.'">'
            .'<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>'
            .($cols !== '' ? '<cols>'.$cols.'</cols>' : '')
            .'<sheetData>'.$data.'</sheetData>'
            .'<autoFilter ref="A1:'.$last.'"/>'
            .'</worksheet>');
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);

        return $bytes;
    }
}
