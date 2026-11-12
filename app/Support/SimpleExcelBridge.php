<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;
use ZipArchive;

/**
 * Native OpenXML (.xlsx) Spreadsheet Generator & Laravel Excel Bridge.
 * Aktif secara otomatis apabila package maatwebsite/excel belum ter-autoload di vendor,
 * sehingga Excel::download(new LaporanKinerjaExport, 'Rekap_Kinerja_Unit.xlsx')
 * selalu menghasilkan file .xlsx asli (standar Office Open XML) lengkap dengan styling
 * header bold berlatar abu-abu terang dan auto-size kolom yang dapat dibuka di Microsoft Excel.
 */
class SimpleExcelBridge
{
    public static function download(object $export, string $fileName = 'export.xlsx'): Response
    {
        $safeFilename = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $fileName) ?: 'Rekap_Kinerja_Unit.xlsx';

        $headings = method_exists($export, 'headings') ? (array) $export->headings() : [];
        $records  = method_exists($export, 'collection') ? $export->collection() : collect();

        if (method_exists($export, 'styles') && class_exists(\PhpOffice\PhpSpreadsheet\Worksheet\Worksheet::class)) {
            $dummySheet = new \PhpOffice\PhpSpreadsheet\Worksheet\Worksheet();
            $export->styles($dummySheet);
        }

        $rows = [];
        if (! empty($headings)) {
            $rows[] = array_values($headings);
        }

        foreach ($records as $record) {
            if (method_exists($export, 'map')) {
                $rows[] = array_values((array) $export->map($record));
            } elseif (is_array($record)) {
                $rows[] = array_values($record);
            } elseif (is_object($record) && method_exists($record, 'toArray')) {
                $rows[] = array_values($record->toArray());
            }
        }

        $xlsxBinary = self::buildOpenXmlXlsx($rows);

        return new Response($xlsxBinary, 200, [
            'Content-Type'                  => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition'           => 'attachment; filename="' . $safeFilename . '"',
            'Content-Length'                => (string) strlen($xlsxBinary),
            'Cache-Control'                 => 'private, max-age=0, must-revalidate',
            'Pragma'                        => 'public',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    protected static function columnLetter(int $colIndex): string
    {
        $letter = '';
        while ($colIndex >= 0) {
            $letter   = chr(($colIndex % 26) + 65) . $letter;
            $colIndex = intdiv($colIndex, 26) - 1;
        }

        return $letter;
    }

    protected static function escapeXml(string $value): string
    {
        // Hapus karakter kontrol XML ilegal
        $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';

        return htmlspecialchars($cleaned, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    protected static function buildOpenXmlXlsx(array $rows): string
    {
        $colCount  = ! empty($rows) ? max(array_map('count', $rows)) : 7;
        $colWidths = array_fill(0, $colCount, 12);

        foreach ($rows as $row) {
            foreach ($row as $cIdx => $cellVal) {
                $len = mb_strlen((string) $cellVal, 'UTF-8');
                if ($len + 4 > ($colWidths[$cIdx] ?? 12)) {
                    $colWidths[$cIdx] = min(55, $len + 4);
                }
            }
        }

        $colsXml = '<cols>';
        foreach ($colWidths as $cIdx => $width) {
            $colNum   = $cIdx + 1;
            $colsXml .= '<col min="' . $colNum . '" max="' . $colNum . '" width="' . $width . '" customWidth="1"/>';
        }
        $colsXml .= '</cols>';

        $sheetDataXml = '<sheetData>';
        foreach ($rows as $rIdx => $row) {
            $rowNum    = $rIdx + 1;
            $rowHeight = $rIdx === 0 ? ' ht="24" customHeight="1"' : ' ht="19" customHeight="1"';
            $sheetDataXml .= '<row r="' . $rowNum . '"' . $rowHeight . '>';

            foreach ($row as $cIdx => $cellVal) {
                $cellRef = self::columnLetter($cIdx) . $rowNum;
                // Style index: 1 = Header (Bold + Gray Fill #E5E7EB + Border), 2 = Data Cell (Border)
                $styleIdx = $rIdx === 0 ? 1 : 2;

                if (is_int($cellVal) || is_float($cellVal)) {
                    $sheetDataXml .= '<c r="' . $cellRef . '" s="' . $styleIdx . '" t="n"><v>' . $cellVal . '</v></c>';
                } else {
                    $escaped = self::escapeXml((string) ($cellVal ?? ''));
                    $sheetDataXml .= '<c r="' . $cellRef . '" s="' . $styleIdx . '" t="inlineStr"><is><t>' . $escaped . '</t></is></c>';
                }
            }

            $sheetDataXml .= '</row>';
        }
        $sheetDataXml .= '</sheetData>';

        $sheetXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . $colsXml
            . $sheetDataXml
            . '</worksheet>';

        $stylesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<fonts count="2">'
            . '<font><sz val="11"/><color rgb="FF111827"/><name val="Calibri"/></font>'
            . '<font><b/><sz val="11"/><color rgb="FF111827"/><name val="Calibri"/></font>'
            . '</fonts>'
            . '<fills count="3">'
            . '<fill><patternFill patternType="none"/></fill>'
            . '<fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFE5E7EB"/><bgColor indexed="64"/></patternFill></fill>'
            . '</fills>'
            . '<borders count="2">'
            . '<border><left/><right/><top/><bottom/><diagonal/></border>'
            . '<border>'
            . '<left style="thin"><color rgb="FFD1D5DB"/></left>'
            . '<right style="thin"><color rgb="FFD1D5DB"/></right>'
            . '<top style="thin"><color rgb="FFD1D5DB"/></top>'
            . '<bottom style="thin"><color rgb="FFD1D5DB"/></bottom>'
            . '<diagonal/>'
            . '</border>'
            . '</borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="3">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
            . '<xf numFmtId="0" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1">'
            . '<alignment vertical="center"/>'
            . '</xf>'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="1" xfId="0" applyBorder="1" applyAlignment="1">'
            . '<alignment vertical="center" wrapText="1"/>'
            . '</xf>'
            . '</cellXfs>'
            . '</styleSheet>';

        $workbookXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" '
            . 'xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<sheets><sheet name="Rekap Kinerja Unit" sheetId="1" r:id="rId1"/></sheets>'
            . '</workbook>';

        $workbookRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>'
            . '</Relationships>';

        $rootRelsXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '</Relationships>';

        $contentTypesXml = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/>'
            . '<Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '</Types>';

        $tempDir = storage_path('app/temp_excel');
        if (! is_dir($tempDir)) {
            @mkdir($tempDir, 0775, true);
        }

        $tempFile = $tempDir . '/rekap_' . uniqid('', true) . '.xlsx';
        $zip      = new ZipArchive();

        if ($zip->open($tempFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $zip->addFromString('[Content_Types].xml', $contentTypesXml);
            $zip->addFromString('_rels/.rels', $rootRelsXml);
            $zip->addFromString('xl/workbook.xml', $workbookXml);
            $zip->addFromString('xl/_rels/workbook.xml.rels', $workbookRelsXml);
            $zip->addFromString('xl/styles.xml', $stylesXml);
            $zip->addFromString('xl/worksheets/sheet1.xml', $sheetXml);
            $zip->close();
        }

        $binary = is_file($tempFile) ? (string) file_get_contents($tempFile) : '';
        @unlink($tempFile);

        return $binary;
    }
}
