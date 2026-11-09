<?php

namespace Database\Seeders;

use App\Models\DokumenPelaporan;
use App\Models\LampiranDokumen;
use App\Models\PeriodeAkademik;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // 1. Jalankan Master Data Fakultas & Prodi UNUHA
        $this->call(FakultasProdiSeeder::class);

        // 2. Akun Admin Universitas
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password'    => 'password123',
                'role'        => 'admin',
                'id_fakultas' => null,
                'is_active'   => true,
            ]
        );

        // 3. Periode Akademik Berjalan (Default & Buka)
        $periode = PeriodeAkademik::updateOrCreate(
            ['nama_periode' => '2026/2027 Ganjil'],
            [
                'status_pelaporan' => PeriodeAkademik::STATUS_BUKA,
                'is_default'       => true,
            ]
        );

        // 4. Pastikan Akun Operator Prodi TI (Informatika - Fakultas Sains dan Teknologi) tersedia untuk testing/login
        $prodiInformatika = Prodi::with('fakultas')->where('nama_prodi', 'S1 Informatika')->first()
            ?? Prodi::with('fakultas')->first();

        if ($prodiInformatika) {
            User::updateOrCreate(
                ['username' => 'prodi_ti'],
                [
                    'password'    => 'password123',
                    'role'        => 'user',
                    'id_fakultas' => $prodiInformatika->id_fakultas,
                    'is_active'   => true,
                ]
            );
        }

        // 5. Buatkan akun operator default untuk seluruh Fakultas UNUHA
        $allFakultas = \App\Models\Fakultas::all();
        foreach ($allFakultas as $fakultas) {
            $existingUser = User::where('id_fakultas', $fakultas->id)->first();
            if (! $existingUser) {
                $cleanSlug = Str::slug($fakultas->singkatan ?: $fakultas->nama_fakultas, '_');
                $username  = 'op_' . Str::lower($cleanSlug);

                User::updateOrCreate(
                    ['username' => $username],
                    [
                        'password'    => 'Unuha2026!',
                        'role'        => 'user',
                        'id_fakultas' => $fakultas->id,
                        'is_active'   => true,
                    ]
                );
            }
        }

        // 6. Bersihkan data dummy lampiran dan file fisik di storage
        LampiranDokumen::truncate();
        if (Storage::disk('local')->exists('dokumen_pelaporan')) {
            Storage::disk('local')->deleteDirectory('dokumen_pelaporan');
        }
        if (File::isDirectory(storage_path('app/temp_zip'))) {
            File::deleteDirectory(storage_path('app/temp_zip'));
        }

        // 7. Inisialisasi 4 Tahap Dokumen Pelaporan untuk SEMUA Program Studi (Semua 'Belum Unggah')
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
