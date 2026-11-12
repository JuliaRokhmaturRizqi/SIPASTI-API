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
     * Seed Akun Standar untuk Demo UAT & Pengujian Sistem SIPASTI UNUHA.
     */
    public function run(): void
    {
        $defaultDemoPassword = Hash::make('password');

        // 1. Akun Admin Utama Demo UAT (username: admin_unuha, password: password)
        User::updateOrCreate(
            ['username' => 'admin_unuha'],
            [
                'password'      => $defaultDemoPassword,
                'role'          => 'admin',
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
                'id_fakultas'   => null,
                'id_unit_kerja' => null,
                'is_active'     => true,
            ]
        );

        // 2. Akun Operator Fakultas Sains dan Teknologi (FST)
        $fakultasFst = Fakultas::where('singkatan', 'FST')
            ->orWhere('nama_fakultas', 'Fakultas Sains dan Teknologi')
            ->first();

        if ($fakultasFst) {
            User::updateOrCreate(
                ['username' => 'opr_fst'],
                [
                    'password'      => $defaultDemoPassword,
                    'role'          => 'user',
                    'id_fakultas'   => $fakultasFst->id,
                    'id_unit_kerja' => null,
                    'is_active'     => true,
                ]
            );
        }

        // Akun Operator Fakultas lainnya (FAI, FIP, Pasca) & prodi_ti
        $prodiInformatika = Prodi::where('nama_prodi', 'S1 Informatika')->first() ?? Prodi::first();
        if ($prodiInformatika) {
            User::updateOrCreate(
                ['username' => 'prodi_ti'],
                [
                    'password'      => Hash::make('password123'),
                    'role'          => 'user',
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
                    'id_fakultas'   => $fakultas->id,
                    'id_unit_kerja' => null,
                    'is_active'     => true,
                ]
            );
        }

        // 3. Tiga Akun Operator Unit Utama untuk Demo UAT (Password: password)
        $biroKeuangan = UnitKerja::where('nama_unit', 'Biro Keuangan')->first();
        if ($biroKeuangan) {
            User::updateOrCreate(
                ['username' => 'opr_keuangan'],
                [
                    'password'      => $defaultDemoPassword,
                    'role'          => 'biro',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $biroKeuangan->id,
                    'is_active'     => true,
                ]
            );
        }

        $pusatIt = UnitKerja::where('nama_unit', 'Pusat IT Center')->first();
        if ($pusatIt) {
            User::updateOrCreate(
                ['username' => 'opr_it'],
                [
                    'password'      => $defaultDemoPassword,
                    'role'          => 'pusat',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $pusatIt->id,
                    'is_active'     => true,
                ]
            );
        }

        $labKomputer = UnitKerja::where('nama_unit', 'Laboratorium Komputer')->first();
        if ($labKomputer) {
            User::updateOrCreate(
                ['username' => 'opr_labkom'],
                [
                    'password'      => $defaultDemoPassword,
                    'role'          => 'lab',
                    'id_fakultas'   => null,
                    'id_unit_kerja' => $labKomputer->id,
                    'is_active'     => true,
                ]
            );
        }

        // 4. Akun Tambahan untuk Unit Kerja Lainnya (Opsional agar semua unit siap didemokan)
        $additionalUnitAccounts = [
            ['username' => 'opr_baa',           'role' => 'biro',  'nama_unit' => 'Biro Administrasi Akademik'],
            ['username' => 'opr_kepegawaian',   'role' => 'biro',  'nama_unit' => 'Biro Kepegawaian'],
            ['username' => 'opr_kemahasiswaan', 'role' => 'biro',  'nama_unit' => 'Biro Kemahasiswaan'],
            ['username' => 'opr_alumni',        'role' => 'pusat', 'nama_unit' => 'Pusat Pengembangan Alumni dan Karir'],
            ['username' => 'opr_aswaja',        'role' => 'pusat', 'nama_unit' => 'Pusat Keaswajaan dan Kepesantrenan'],
            ['username' => 'opr_kumoring',      'role' => 'pusat', 'nama_unit' => 'Pusat Kajian Kumoring'],
            ['username' => 'opr_labsains',      'role' => 'lab',   'nama_unit' => 'Laboratorium Sains'],
        ];

        foreach ($additionalUnitAccounts as $acc) {
            $unit = UnitKerja::where('nama_unit', $acc['nama_unit'])->first();
            if ($unit) {
                User::updateOrCreate(
                    ['username' => $acc['username']],
                    [
                        'password'      => $defaultDemoPassword,
                        'role'          => $acc['role'],
                        'id_fakultas'   => null,
                        'id_unit_kerja' => $unit->id,
                        'is_active'     => true,
                    ]
                );
            }
        }
    }
}
