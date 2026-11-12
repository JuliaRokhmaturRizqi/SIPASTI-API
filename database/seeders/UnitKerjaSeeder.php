<?php

namespace Database\Seeders;

use App\Models\KategoriUnit;
use App\Models\UnitKerja;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class UnitKerjaSeeder extends Seeder
{
    /**
     * Seed Master Data Unit Kerja Resmi Struktur Organisasi Universitas Nurul Huda (UNUHA).
     */
    public function run(): void
    {
        $kategoriBiro     = KategoriUnit::firstOrCreate(['nama_kategori' => 'Biro']);
        $kategoriPusat    = KategoriUnit::firstOrCreate(['nama_kategori' => 'Pusat']);
        $kategoriLab      = KategoriUnit::firstOrCreate(['nama_kategori' => 'Laboratorium']);
        $kategoriFakultas = KategoriUnit::firstOrCreate(['nama_kategori' => 'Fakultas']);

        $units = [
            // 1. Kategori: Biro
            ['nama_unit' => 'Biro Administrasi Akademik',      'id_kategori' => $kategoriBiro->id],
            ['nama_unit' => 'Biro Administrasi Keuangan',      'id_kategori' => $kategoriBiro->id],
            ['nama_unit' => 'Biro Administrasi Kepegawaian',   'id_kategori' => $kategoriBiro->id],
            ['nama_unit' => 'Biro Sarana dan Prasarana',       'id_kategori' => $kategoriBiro->id],
            ['nama_unit' => 'Biro Administrasi Kemahasiswaan', 'id_kategori' => $kategoriBiro->id],
            ['nama_unit' => 'Biro Kerjasama dan Humas',        'id_kategori' => $kategoriBiro->id],

            // 2. Kategori: Pusat
            ['nama_unit' => 'Pusat Pengembangan Alumni dan Karir',                   'id_kategori' => $kategoriPusat->id],
            ['nama_unit' => 'Pusat Keaswajaan dan Kepesantren',                      'id_kategori' => $kategoriPusat->id],
            ['nama_unit' => 'Pusat IT Center',                                       'id_kategori' => $kategoriPusat->id],
            ['nama_unit' => 'Pusat Kajian Kumoring',                                 'id_kategori' => $kategoriPusat->id],
            ['nama_unit' => 'Pusat Penerimaan Mahasiswa Baru',                       'id_kategori' => $kategoriPusat->id],
            ['nama_unit' => 'Pusat Pengembangan Kurikulum dan Inovasi Pembelajaran', 'id_kategori' => $kategoriPusat->id],
            ['nama_unit' => 'Pusat Ecoenterpreneurship',                             'id_kategori' => $kategoriPusat->id],
            ['nama_unit' => 'Pusat Layanan Bahasa',                                  'id_kategori' => $kategoriPusat->id],
            ['nama_unit' => 'Pusat Data dan Pelaporan',                              'id_kategori' => $kategoriPusat->id],

            // 3. Kategori: Laboratorium
            ['nama_unit' => 'Laboratorium Komputer', 'id_kategori' => $kategoriLab->id],
            ['nama_unit' => 'Laboratorium Sains',    'id_kategori' => $kategoriLab->id],

            // 4. Kategori: Fakultas
            ['nama_unit' => 'Fakultas Sains dan Teknologi (FST)', 'id_kategori' => $kategoriFakultas->id],
            ['nama_unit' => 'Fakultas Agama Islam (FAI)',         'id_kategori' => $kategoriFakultas->id],
            ['nama_unit' => 'Fakultas Ilmu Pendidikan (FIP)',     'id_kategori' => $kategoriFakultas->id],
        ];

        $officialNames = array_column($units, 'nama_unit');

        DB::transaction(function () use ($units, $officialNames) {
            // Bersihkan nama unit lama yang tidak termasuk dalam struktur organisasi resmi
            UnitKerja::whereNotIn('nama_unit', $officialNames)->delete();

            foreach ($units as $unit) {
                UnitKerja::updateOrCreate(
                    ['nama_unit' => $unit['nama_unit']],
                    ['id_kategori' => $unit['id_kategori']]
                );
            }
        });
    }
}
