<?php

namespace Database\Seeders;

use App\Models\Fakultas;
use App\Models\Prodi;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    /**
     * Seed Akun Standar & Pasangan Akun (Staf & Pimpinan) untuk Demo UAT
     * dan Pengujian Alur Persetujuan Bertingkat (Approval Workflow) SIPASTI UNUHA.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        // =====================================================================
        // 1. AKUN ADMIN UTAMA
        // =====================================================================
        User::updateOrCreate(
            ['username' => 'admin_unuha'],
            [
                'password'      => $defaultPassword,
                'role'          => 'admin',
                'jabatan'       => 'pimpinan',
                'id_fakultas'   => null,
                'id_unit_kerja' => null,
                'is_active'     => true,
            ]
        );

        // Akun Admin Sistem (Kompatibilitas Sistem & Automated Test)
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'password'      => Hash::make('password123'),
                'role'          => 'admin',
                'jabatan'       => 'pimpinan',
                'id_fakultas'   => null,
                'id_unit_kerja' => null,
                'is_active'     => true,
            ]
        );

        // =====================================================================
        // 2. PASANGAN AKUN BIRO (Biro Administrasi Keuangan / Biro Keuangan)
        // =====================================================================
        $biroKeuangan = UnitKerja::where('nama_unit', 'Biro Administrasi Keuangan')
            ->orWhere('nama_unit', 'Biro Keuangan')
            ->first();

        if ($biroKeuangan) {
            // Akun Staf Biro Keuangan
            User::updateOrCreate(
                ['username' => 'staf_biro_keu'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'biro',
                    'jabatan'       => 'staf',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $biroKeuangan->id,
                    'is_active'     => true,
                ]
            );

            // Akun Pimpinan (Kepala) Biro Keuangan
            User::updateOrCreate(
                ['username' => 'kepala_biro_keu'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'biro',
                    'jabatan'       => 'pimpinan',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $biroKeuangan->id,
                    'is_active'     => true,
                ]
            );

            // Alias kompatibilitas demo sebelumnya
            User::updateOrCreate(
                ['username' => 'opr_keuangan'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'biro',
                    'jabatan'       => 'staf',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $biroKeuangan->id,
                    'is_active'     => true,
                ]
            );
        }

        // =====================================================================
        // 3. PASANGAN AKUN PUSAT (Pusat IT Center)
        // =====================================================================
        $pusatIt = UnitKerja::where('nama_unit', 'Pusat IT Center')->first();

        if ($pusatIt) {
            // Akun Staf Pusat IT Center
            User::updateOrCreate(
                ['username' => 'staf_pusat_it'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'pusat',
                    'jabatan'       => 'staf',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $pusatIt->id,
                    'is_active'     => true,
                ]
            );

            // Akun Pimpinan (Kepala) Pusat IT Center
            User::updateOrCreate(
                ['username' => 'kepala_pusat_it'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'pusat',
                    'jabatan'       => 'pimpinan',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $pusatIt->id,
                    'is_active'     => true,
                ]
            );

            // Alias kompatibilitas demo sebelumnya
            User::updateOrCreate(
                ['username' => 'opr_it'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'pusat',
                    'jabatan'       => 'staf',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $pusatIt->id,
                    'is_active'     => true,
                ]
            );
        }

        // =====================================================================
        // 4. PASANGAN AKUN LAB (Laboratorium Komputer)
        // =====================================================================
        $labKomputer = UnitKerja::where('nama_unit', 'Laboratorium Komputer')->first();

        if ($labKomputer) {
            // Akun Staf Laboratorium Komputer
            User::updateOrCreate(
                ['username' => 'staf_lab_kom'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'lab',
                    'jabatan'       => 'staf',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $labKomputer->id,
                    'is_active'     => true,
                ]
            );

            // Akun Pimpinan (Kepala) Laboratorium Komputer
            User::updateOrCreate(
                ['username' => 'kepala_lab_kom'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'lab',
                    'jabatan'       => 'pimpinan',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $labKomputer->id,
                    'is_active'     => true,
                ]
            );

            // Alias kompatibilitas demo sebelumnya
            User::updateOrCreate(
                ['username' => 'opr_labkom'],
                [
                    'password'      => $defaultPassword,
                    'role'          => 'lab',
                    'jabatan'       => 'staf',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $labKomputer->id,
                    'is_active'     => true,
                ]
            );
        }

        // =====================================================================
        // 5. AKUN FAKULTAS (Modul Akademik & Unit Fakultas)
        // =====================================================================
        $fakultasAccounts = [
            ['username' => 'opr_fst', 'singkatan' => 'FST', 'nama_fakultas' => 'Fakultas Sains dan Teknologi'],
            ['username' => 'opr_fai', 'singkatan' => 'FAI', 'nama_fakultas' => 'Fakultas Agama Islam'],
            ['username' => 'opr_fip', 'singkatan' => 'FIP', 'nama_fakultas' => 'Fakultas Ilmu Pendidikan'],
        ];

        foreach ($fakultasAccounts as $fakAcc) {
            $fakultas = Fakultas::where('singkatan', $fakAcc['singkatan'])
                ->orWhere('nama_fakultas', $fakAcc['nama_fakultas'])
                ->first();

            if ($fakultas) {
                User::updateOrCreate(
                    ['username' => $fakAcc['username']],
                    [
                        'password'      => $defaultPassword,
                        'role'          => 'user',
                        'jabatan'       => 'staf',
                        'id_fakultas'   => $fakultas->id,
                        'id_unit_kerja' => null,
                        'is_active'     => true,
                    ]
                );
            }
        }

        // Akun Operator Prodi & Fakultas standar untuk kompatibilitas pengujian Modul Akademik
        $prodiInformatika = Prodi::where('nama_prodi', 'S1 Informatika')->first() ?? Prodi::first();
        if ($prodiInformatika) {
            User::updateOrCreate(
                ['username' => 'prodi_ti'],
                [
                    'password'      => Hash::make('password123'),
                    'role'          => 'user',
                    'jabatan'       => 'staf',
                    'id_fakultas'   => $prodiInformatika->id_fakultas,
                    'id_unit_kerja' => null,
                    'is_active'     => true,
                ]
            );
        }

        foreach (Fakultas::all() as $fakultas) {
            $cleanSlug = Str::slug($fakultas->singkatan ?: $fakultas->nama_fakultas, '_');
            $username  = 'op_' . Str::lower($cleanSlug);

            User::updateOrCreate(
                ['username' => $username],
                [
                    'password'      => Hash::make('Unuha2026!'),
                    'role'          => 'user',
                    'jabatan'       => 'staf',
                    'id_fakultas'   => $fakultas->id,
                    'id_unit_kerja' => null,
                    'is_active'     => true,
                ]
            );
        }
    }
}
