<?php

namespace Database\Seeders;

use App\Models\DokumenPelaporan;
use App\Models\PeriodeAkademik;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $prodiTi  = Prodi::firstOrCreate(['nama_prodi' => 'S1 Teknik Informatika']);
        $prodiSi  = Prodi::firstOrCreate(['nama_prodi' => 'S1 Sistem Informasi']);
        $prodiMat = Prodi::firstOrCreate(['nama_prodi' => 'S1 Pendidikan Matematika']);
        $prodiMp  = Prodi::firstOrCreate(['nama_prodi' => 'S1 Manajemen Pendidikan']);

        // Akun Admin Universitas (id_prodi = null)
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password' => 'password123',
                'role'     => 'admin',
                'id_prodi' => null,
            ]
        );

        // Akun Operator Prodi Teknik Informatika
        User::updateOrCreate(
            ['username' => 'prodi_ti'],
            [
                'password' => 'password123',
                'role'     => 'user',
                'id_prodi' => $prodiTi->id,
            ]
        );

        // Periode Akademik Aktif
        $periode = PeriodeAkademik::updateOrCreate(
            ['nama_periode' => '2026/2027 Ganjil'],
            ['status' => PeriodeAkademik::STATUS_AKTIF]
        );

        // Inisialisasi 4 Tahap Dokumen Pelaporan untuk Prodi TI
        $tahapanAwal = [
            1 => [
                'status'         => 'Selesai',
                'file_path'      => 'dokumen-pelaporan/kurikulum-ti-2026-ganjil.pdf',
                'catatan_revisi' => null,
                'tanggal_upload' => now()->subDays(5),
            ],
            2 => [
                'status'         => 'Selesai',
                'file_path'      => 'dokumen-pelaporan/kelas-jadwal-ti-2026-ganjil.pdf',
                'catatan_revisi' => null,
                'tanggal_upload' => now()->subDays(3),
            ],
            3 => [
                'status'         => 'Revisi',
                'file_path'      => 'dokumen-pelaporan/nilai-perkuliahan-ti-2026-ganjil.pdf',
                'catatan_revisi' => 'Rekapitulasi nilai mata kuliah wajib semester 3 dan 5 belum mencantumkan pengesahan Kaprodi serta lampiran berita acara nilai belum lengkap. Mohon perbaiki dan unggah ulang dalam format PDF.',
                'tanggal_upload' => now()->subDay(),
            ],
            4 => [
                'status'         => 'Belum Unggah',
                'file_path'      => null,
                'catatan_revisi' => null,
                'tanggal_upload' => null,
            ],
        ];

        foreach ($tahapanAwal as $tahap => $detail) {
            DokumenPelaporan::updateOrCreate(
                [
                    'id_prodi'      => $prodiTi->id,
                    'id_periode'    => $periode->id,
                    'tahap_dokumen' => $tahap,
                ],
                $detail
            );
        }

        // Data Matriks Pemantauan untuk Prodi lainnya agar Admin dapat langsung menguji Review/Validasi
        $matriksTambahan = [
            $prodiSi->id => [
                1 => ['status' => 'Selesai', 'file_path' => 'dokumen-pelaporan/kurikulum-si-2026.pdf', 'catatan_revisi' => null, 'tanggal_upload' => now()->subDays(6)],
                2 => ['status' => 'Review',  'file_path' => 'dokumen-pelaporan/jadwal-si-2026.pdf',    'catatan_revisi' => null, 'tanggal_upload' => now()->subHours(5)],
                3 => ['status' => 'Review',  'file_path' => 'dokumen-pelaporan/nilai-si-2026.pdf',     'catatan_revisi' => null, 'tanggal_upload' => now()->subHours(2)],
                4 => ['status' => 'Belum Unggah', 'file_path' => null, 'catatan_revisi' => null, 'tanggal_upload' => null],
            ],
            $prodiMat->id => [
                1 => ['status' => 'Selesai', 'file_path' => 'dokumen-pelaporan/kurikulum-mat-2026.pdf', 'catatan_revisi' => null, 'tanggal_upload' => now()->subDays(7)],
                2 => ['status' => 'Selesai', 'file_path' => 'dokumen-pelaporan/jadwal-mat-2026.pdf',    'catatan_revisi' => null, 'tanggal_upload' => now()->subDays(4)],
                3 => ['status' => 'Selesai', 'file_path' => 'dokumen-pelaporan/nilai-mat-2026.pdf',     'catatan_revisi' => null, 'tanggal_upload' => now()->subDays(2)],
                4 => ['status' => 'Review',  'file_path' => 'dokumen-pelaporan/yudisium-mat-2026.pdf',  'catatan_revisi' => null, 'tanggal_upload' => now()->subHour()],
            ],
            $prodiMp->id => [
                1 => ['status' => 'Review',  'file_path' => 'dokumen-pelaporan/kurikulum-mp-2026.pdf', 'catatan_revisi' => null, 'tanggal_upload' => now()->subHours(8)],
                2 => ['status' => 'Revisi',  'file_path' => 'dokumen-pelaporan/jadwal-mp-2026.pdf',    'catatan_revisi' => 'Distribusi SKS dosen tetap pada jadwal semester ganjil belum sesuai SK Dekan.', 'tanggal_upload' => now()->subDays(1)],
                3 => ['status' => 'Belum Unggah', 'file_path' => null, 'catatan_revisi' => null, 'tanggal_upload' => null],
                4 => ['status' => 'Belum Unggah', 'file_path' => null, 'catatan_revisi' => null, 'tanggal_upload' => null],
            ],
        ];

        foreach ($matriksTambahan as $idProdi => $tahapList) {
            foreach ($tahapList as $tahap => $detail) {
                DokumenPelaporan::updateOrCreate(
                    [
                        'id_prodi'      => $idProdi,
                        'id_periode'    => $periode->id,
                        'tahap_dokumen' => $tahap,
                    ],
                    $detail
                );
            }
        }
    }
}
