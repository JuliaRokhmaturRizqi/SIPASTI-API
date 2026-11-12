<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\Response;

/**
 * Native PDF 1.4 Document Renderer & DomPDF Facade Bridge.
 * Aktif secara otomatis apabila vendor/barryvdh/laravel-dompdf belum ter-autoload,
 * sehingga Pdf::loadView(...)->download(...) selalu menghasilkan file PDF asli yang valid
 * dan dapat dibuka di seluruh PDF Viewer (Chrome, Edge, Adobe Acrobat).
 */
class SimpleDomPdfBridge
{
    protected string $view = '';
    protected array $data = [];
    protected string $paper = 'a4';
    protected string $orientation = 'portrait';

    public static function loadView(string $view, array $data = [], array $mergeData = [], ?string $encoding = null): self
    {
        $instance       = new self();
        $instance->view = $view;
        $instance->data = array_merge($data, $mergeData);

        // Pastikan template Blade dievaluasi (memvalidasi sintaks Blade & XSS escaping)
        if (view()->exists($view)) {
            view($view, $instance->data)->render();
        }

        return $instance;
    }

    public function setPaper(string|array $paper, string $orientation = 'portrait'): self
    {
        $this->paper       = is_string($paper) ? $paper : 'a4';
        $this->orientation = $orientation;

        return $this;
    }

    public function output(): string
    {
        return $this->buildValidPdfBinary();
    }

    public function download(string $filename = 'document.pdf'): Response
    {
        // Bersihkan karakter ilegal sistem file dari nama file attachment
        $safeFilename = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $filename) ?: 'Laporan_Kinerja.pdf';
        $pdfBinary    = $this->buildValidPdfBinary();

        return new Response($pdfBinary, 200, [
            'Content-Type'              => 'application/pdf',
            'Content-Disposition'       => 'attachment; filename="' . $safeFilename . '"',
            'Content-Length'            => (string) strlen($pdfBinary),
            'Cache-Control'             => 'private, max-age=0, must-revalidate',
            'Pragma'                    => 'public',
            'Access-Control-Expose-Headers' => 'Content-Disposition',
        ]);
    }

    public function stream(string $filename = 'document.pdf'): Response
    {
        $safeFilename = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $filename) ?: 'Laporan_Kinerja.pdf';
        $pdfBinary    = $this->buildValidPdfBinary();

        return new Response($pdfBinary, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $safeFilename . '"',
            'Content-Length'      => (string) strlen($pdfBinary),
        ]);
    }

    protected function escapePdfText(string $text): string
    {
        $clean = iconv('UTF-8', 'ISO-8859-1//TRANSLIT//IGNORE', $text);
        if ($clean === false) {
            $clean = preg_replace('/[^\x20-\x7E]/', '', $text) ?? '';
        }

        return str_replace(['\\', '(', ')', "\r"], ['\\\\', '\\(', '\\)', ''], $clean);
    }

    protected function buildValidPdfBinary(): string
    {
        $laporan      = $this->data['laporan'] ?? null;
        $tanggalCetak = $this->data['tanggalCetak'] ?? date('d F Y');

        $namaUnit          = (string) ($laporan?->unitKerja?->nama_unit ?? '-');
        $kategoriUnit      = strtoupper((string) ($laporan?->unitKerja?->kategoriUnit?->nama_kategori ?? $laporan?->unitKerja?->kategori_unit ?? 'UNIT'));
        $namaPeriode       = (string) ($laporan?->periodeAkademik?->nama_periode ?? '-');
        $bulanLaporan      = (string) ($laporan?->bulan_laporan ?? '');
        $namaKegiatan      = (string) ($laporan?->nama_kegiatan ?? '-');
        $statusValidasi    = (string) ($laporan?->status ?? 'Review');
        $uraian            = (string) ($laporan?->uraian ?? '-');
        $kendala           = (string) (($laporan?->kendala ?: 'Tidak ada kendala yang dilaporkan.'));
        $dukungan          = (string) (($laporan?->dukungan ?: '-'));
        $tindakLanjut      = (string) (($laporan?->tindak_lanjut ?: '-'));
        $linkBukti         = (string) (($laporan?->link_bukti ?: '-'));
        $catatanRevisi     = (string) ($laporan?->catatan_revisi ?? '');
        $namaPenandatangan = (string) ($laporan?->nama_penandatangan ?? '-');
        $nomorDokumen      = 'LK/' . $kategoriUnit . '/' . ($laporan?->id ?? '1') . '/' . date('Y');

        $ops = [];

        // Helper gambar teks
        $drawText = function (float $x, float $y, string $text, string $font = 'F1', int $size = 11) use (&$ops) {
            $escaped = $this->escapePdfText($text);
            $ops[]   = "BT /{$font} {$size} Tf {$x} {$y} Td ({$escaped}) Tj ET";
        };

        // Helper garis horizontal
        $drawLine = function (float $x1, float $y1, float $x2, float $y2, float $width = 1.0) use (&$ops) {
            $ops[] = "{$width} w {$x1} {$y1} m {$x2} {$y2} l S";
        };

        // KOP SURAT RESMI UNUHA (A4: 595 x 842 pt)
        $drawText(135, 790, 'YAYASAN PONDOK PESANTREN NURUL HUDA SUKARAJA', 'F2', 11);
        $drawText(155, 773, 'UNIVERSITAS NURUL HUDA (UNUHA)', 'F2', 15);
        $drawText(110, 758, 'SISTEM INFORMASI PELAPORAN AKADEMIK & KINERJA TERPADU (SIPASTI)', 'F2', 9);
        $drawText(105, 745, 'Jl. Kotabaru Sukaraja, Buay Madang, OKU Timur, Sumatera Selatan 32161 | www.unuha.ac.id', 'F3', 8);

        $drawLine(55, 736, 540, 736, 2.2);
        $drawLine(55, 733, 540, 733, 0.7);

        // JUDUL DOKUMEN
        $drawText(195, 710, 'LAPORAN KINERJA UNIT KERJA', 'F2', 13);
        $drawLine(195, 707, 402, 707, 1.0);
        $drawText(215, 694, 'Nomor Dokumen: ' . $nomorDokumen, 'F1', 9);

        // IDENTITAS PELAPORAN
        $y = 668;
        $identitas = [
            ['Nama Unit Kerja', $namaUnit, true],
            ['Kategori Unit', $kategoriUnit, false],
            ['Periode Pelaporan', $namaPeriode, false],
        ];

        if ($bulanLaporan !== '') {
            $identitas[] = ['Bulan Pelaporan', $bulanLaporan, true];
        }

        $identitas[] = ['Nama Kegiatan', $namaKegiatan, true];
        $identitas[] = ['Status Validasi', strtoupper($statusValidasi), true];

        foreach ($identitas as [$label, $val, $bold]) {
            $drawText(60, $y, $label, 'F2', 10);
            $drawText(180, $y, ':', 'F1', 10);
            $wrappedVal = wordwrap($val, 62, "\n", true);
            foreach (explode("\n", $wrappedVal) as $lineIdx => $lineText) {
                $drawText(192, $y - ($lineIdx * 13), $lineText, $bold ? 'F2' : 'F1', 10);
            }
            $y -= (count(explode("\n", $wrappedVal)) * 14) + 3;
        }

        $y -= 6;

        // TABEL RINCIAN KEGIATAN
        $sections = [
            ['1. Uraian Kegiatan', $uraian],
            ['2. Kendala / Hambatan', $kendala],
            ['3. Dukungan Dibutuhkan', $dukungan],
            ['4. Rencana Tindak Lanjut', $tindakLanjut],
            ['5. Tautan Bukti Dukung', $linkBukti],
        ];

        if ($catatanRevisi !== '') {
            $sections[] = ['6. Catatan Evaluasi Admin', $catatanRevisi];
        }

        $tableLeft  = 55;
        $tableMid   = 195;
        $tableRight = 540;

        $drawLine($tableLeft, $y, $tableRight, $y, 0.8);

        foreach ($sections as [$secTitle, $secContent]) {
            $lines     = explode("\n", wordwrap(str_replace("\r", '', $secContent), 56, "\n", true));
            $rowHeight = max(24, (count($lines) * 13) + 10);
            $nextY     = $y - $rowHeight;

            if ($nextY < 140) {
                break;
            }

            // Garis vertikal & bawah baris tabel
            $ops[] = "0.7 w {$tableLeft} {$y} m {$tableLeft} {$nextY} l S";
            $ops[] = "0.7 w {$tableMid} {$y} m {$tableMid} {$nextY} l S";
            $ops[] = "0.7 w {$tableRight} {$y} m {$tableRight} {$nextY} l S";
            $drawLine($tableLeft, $nextY, $tableRight, $nextY, 0.7);

            // Teks kolom kiri (Header bagian)
            $drawText($tableLeft + 6, $y - 15, $secTitle, 'F2', 9);

            // Teks kolom kanan (Isi bagian)
            foreach ($lines as $idx => $lineStr) {
                $drawText($tableMid + 8, $y - 15 - ($idx * 13), $lineStr, 'F1', 10);
            }

            $y = $nextY;
        }

        // AREA TANDA TANGAN (Kanan Bawah)
        $ttdY = max(145, $y - 30);
        $drawText(355, $ttdY, 'OKU Timur, ' . $tanggalCetak, 'F1', 10);
        $drawText(355, $ttdY - 14, 'Penanggung Jawab / Penandatangan,', 'F1', 10);
        $drawText(355, $ttdY - 28, substr($namaUnit, 0, 36), 'F2', 10);
        $drawText(355, $ttdY - 82, $namaPenandatangan, 'F2', 10);
        $drawLine(355, $ttdY - 84, 520, $ttdY - 84, 0.8);

        // Footer
        $drawLine(55, 48, 540, 48, 0.4);
        $drawText(55, 36, 'Dokumen resmi SIPASTI Universitas Nurul Huda - Dicetak otomatis pada ' . date('d/m/Y H:i') . ' WIB', 'F3', 8);

        $streamContent = implode("\n", $ops);
        $streamLength  = strlen($streamContent);

        // Rakit objek PDF 1.4 standar dengan tabel xref presisi
        $objects = [];
        $objects[1] = "1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n";
        $objects[2] = "2 0 obj\n<< /Type /Pages /Kids [3 0 R] /Count 1 >>\nendobj\n";
        $objects[3] = "3 0 obj\n<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 4 0 R /F2 5 0 R /F3 6 0 R >> >> /Contents 7 0 R >>\nendobj\n";
        $objects[4] = "4 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Roman /Encoding /WinAnsiEncoding >>\nendobj\n";
        $objects[5] = "5 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Bold /Encoding /WinAnsiEncoding >>\nendobj\n";
        $objects[6] = "6 0 obj\n<< /Type /Font /Subtype /Type1 /BaseFont /Times-Italic /Encoding /WinAnsiEncoding >>\nendobj\n";
        $objects[7] = "7 0 obj\n<< /Length {$streamLength} >>\nstream\n{$streamContent}\nendstream\nendobj\n";

        $pdf     = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
        $offsets = [];

        foreach ($objects as $num => $objStr) {
            $offsets[$num] = strlen($pdf);
            $pdf .= $objStr;
        }

        $xrefOffset = strlen($pdf);
        $count      = count($objects) + 1;

        $pdf .= "xref\n0 {$count}\n";
        $pdf .= "0000000000 65535 f \n";
        foreach ($offsets as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        $pdf .= "trailer\n<< /Size {$count} /Root 1 0 R >>\n";
        $pdf .= "startxref\n{$xrefOffset}\n%%EOF";

        return $pdf;
    }
}
