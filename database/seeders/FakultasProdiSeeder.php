<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\PeriodeAkademik;
use App\Models\Prodi;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FakultasProdiSeeder extends Seeder
{
    /**
     * Seed Master Data Fakultas & Program Studi Universitas Nurul Huda (UNUHA).
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Ambil nama periode berjalan (default/terbaru) untuk Prodi Baru
            $periodeBerjalan = PeriodeAkademik::where('is_default', true)->value('nama_periode')
                ?? PeriodeAkademik::orderByDesc('id')->value('nama_periode')
                ?? '2026/2027 Ganjil';

            $masterData = [
                [
                    'singkatan'     => 'FAI',
                    'nama_fakultas' => 'Fakultas Agama Islam',
                    'prodi'         => [
                        ['nama_prodi' => 'S1 Pendidikan Agama Islam', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Pendidikan Guru Madrasah Ibtidaiyah', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Pendidikan Bahasa Arab', 'periode_mulai_aktif' => $periodeBerjalan], // Prodi Baru!
                    ],
                ],
                [
                    'singkatan'     => 'FIP',
                    'nama_fakultas' => 'Fakultas Ilmu Pendidikan',
                    'prodi'         => [
                        ['nama_prodi' => 'S1 Pendidikan Bahasa dan Sastra Indonesia', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Pendidikan Bahasa Inggris', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Pendidikan Fisika', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Pendidikan Ekonomi', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Pendidikan Teknologi Informasi', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Pelatihan Olahraga', 'periode_mulai_aktif' => $periodeBerjalan], // Prodi Baru!
                    ],
                ],
                [
                    'singkatan'     => 'FST',
                    'nama_fakultas' => 'Fakultas Sains dan Teknologi',
                    'prodi'         => [
                        ['nama_prodi' => 'S1 Informatika', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Matematika', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Sains Pertanian', 'periode_mulai_aktif' => null],
                        ['nama_prodi' => 'S1 Peternakan', 'periode_mulai_aktif' => $periodeBerjalan], // Prodi Baru!
                    ],
                ],
                [
                    'singkatan'     => 'Pasca',
                    'nama_fakultas' => 'Pascasarjana',
                    'prodi'         => [
                        ['nama_prodi' => 'S2 Pendidikan Agama Islam', 'periode_mulai_aktif' => null],
                    ],
                ],
            ];

            foreach ($masterData as $fakData) {
                $fakultas = Fakultas::withTrashed()->updateOrCreate(
                    ['singkatan' => $fakData['singkatan']],
                    [
                        'nama_fakultas' => $fakData['nama_fakultas'],
                        'deleted_at'    => null,
                    ]
                );

                foreach ($fakData['prodi'] as $prodiData) {
                    Prodi::withTrashed()->updateOrCreate(
                        ['nama_prodi' => $prodiData['nama_prodi']],
                        [
                            'id_fakultas'         => $fakultas->id,
                            'periode_mulai_aktif' => $prodiData['periode_mulai_aktif'],
                            'deleted_at'          => null,
                        ]
                    );
                }
            }
        });
    }
}
