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
        $prodiTi = Prodi::firstOrCreate(['nama_prodi' => 'Teknik Informatika']);
        $prodiSi = Prodi::firstOrCreate(['nama_prodi' => 'Sistem Informasi']);

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
            ['status_aktif' => true]
        );

        // Inisialisasi 4 Tahap Dokumen Pelaporan untuk Prodi TI
        foreach ([1, 2, 3, 4] as $tahap) {
            DokumenPelaporan::firstOrCreate(
                [
                    'id_prodi'      => $prodiTi->id,
                    'id_periode'    => $periode->id,
                    'tahap_dokumen' => $tahap,
                ],
                [
                    'status' => 'Belum Unggah',
                ]
            );
        }
    }
}
