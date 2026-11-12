<?php

namespace App\Exports;

use App\Models\LaporanKinerja;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class LaporanKinerjaExport implements FromCollection, WithHeadings, WithMapping, WithStyles, ShouldAutoSize
{
    protected ?int $idPeriode;
    protected ?string $kategoriUnit;
    protected int $rowNumber = 0;

    public function __construct(?int $idPeriode = null, ?string $kategoriUnit = null)
    {
        $this->idPeriode    = $idPeriode;
        $this->kategoriUnit = $kategoriUnit;
    }

    /**
     * Ambil koleksi data LaporanKinerja beserta relasi unitKerja & periodeAkademik.
     */
    public function collection(): Collection
    {
        $query = LaporanKinerja::query()
            ->with([
                'unitKerja:id,nama_unit,id_kategori',
                'unitKerja.kategoriUnit:id,nama_kategori',
                'periodeAkademik:id,nama_periode',
            ]);

        if (! empty($this->idPeriode)) {
            $query->where('id_periode', $this->idPeriode);
        }

        if (! empty($this->kategoriUnit)) {
            $kategori = $this->kategoriUnit;
            $query->whereHas('unitKerja.kategoriUnit', function ($q) use ($kategori) {
                $q->where('nama_kategori', 'like', "%{$kategori}%");
            });
        }

        return $query
            ->orderByDesc('id_periode')
            ->orderBy('id_unit_kerja')
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Baris Pertama (Heading Kolom Excel).
     */
    public function headings(): array
    {
        return [
            'No',
            'Tanggal Dibuat',
            'Nama Unit Kerja',
            'Kategori',
            'Nama Kegiatan',
            'Status',
            'Nama Pimpinan',
        ];
    }

    /**
     * Pemetaan setiap baris data LaporanKinerja ke urutan kolom Heading.
     *
     * @param  \App\Models\LaporanKinerja  $laporan
     */
    public function map($laporan): array
    {
        $this->rowNumber++;

        $kategori = $laporan->unitKerja?->kategoriUnit?->nama_kategori
            ?? $laporan->unitKerja?->kategori_unit
            ?? '-';

        return [
            $this->rowNumber,
            $laporan->created_at ? $laporan->created_at->format('d-m-Y H:i') : '-',
            $laporan->unitKerja->nama_unit ?? '-',
            strtoupper($kategori),
            $laporan->nama_kegiatan,
            $laporan->status,
            $laporan->nama_penandatangan,
        ];
    }

    /**
     * Styling baris pertama (Heading) menjadi Bold dengan latar abu-abu terang (#E5E7EB).
     */
    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => [
                    'bold' => true,
                    'size' => 11,
                ],
                'fill' => [
                    'fillType'   => Fill::FILL_SOLID,
                    'startColor' => ['rgb' => 'E5E7EB'],
                ],
            ],
        ];
    }
}
