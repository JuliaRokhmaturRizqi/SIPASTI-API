<?php

namespace Database\Seeders;

use App\Models\DokumenPelaporan;
use App\Models\LampiranDokumen;
use App\Models\PeriodeAkademik;
use App\Models\Prodi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Periode Akademik Berjalan (Default & Buka)
        $periode = PeriodeAkademik::updateOrCreate(
            ['nama_periode' => '2026/2027 Ganjil'],
            [
                'status_aktif'     => true,
                'status_pelaporan' => PeriodeAkademik::STATUS_BUKA,
                'is_default'       => true,
            ]
        );

        // 2. Jalankan Master Data Fakultas & Program Studi (Modul Akademik)
        $this->call(FakultasProdiSeeder::class);

        // 3. Jalankan Master Data Kategori Unit, Unit Kerja, Akun Pengguna, & Laporan Kinerja (Berurutan)
        $this->call([
            KategoriUnitSeeder::class,
            UnitKerjaSeeder::class,
            UserSeeder::class,
            LaporanKinerjaSeeder::class,
        ]);

        // 4. Bersihkan sisa file lampiran fisik di storage lokal jika ada
        LampiranDokumen::truncate();
        if (Storage::disk('local')->exists('dokumen_pelaporan')) {
            Storage::disk('local')->deleteDirectory('dokumen_pelaporan');
        }
        if (File::isDirectory(storage_path('app/temp_zip'))) {
            File::deleteDirectory(storage_path('app/temp_zip'));
        }

        // 5. Inisialisasi 4 Tahap Dokumen Pelaporan untuk SEMUA Program Studi
        // Tahap: 1: Kurikulum, 2: Jadwal, 3: Nilai, 4: Yudisium
        $allProdis = Prodi::all();
        foreach ($allProdis as $prodi) {
            foreach ([1, 2, 3, 4] as $tahap) {
                DokumenPelaporan::updateOrCreate(
                    [
                        'id_prodi'      => $prodi->id,
                        'id_periode'    => $periode->id,
                        'tahap_dokumen' => $tahap,
                    ],
                    [
                        'status'         => DokumenPelaporan::STATUS_BELUM_UNGGAH,
                        'file_path'      => null,
                        'catatan_revisi' => null,
                        'tanggal_upload' => null,
                    ]
                );
            }
        }
    }
}
