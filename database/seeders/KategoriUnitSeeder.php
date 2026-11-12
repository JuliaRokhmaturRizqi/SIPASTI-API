<?php

namespace Database\Seeders;

use App\Models\KategoriUnit;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class KategoriUnitSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            'Biro',
            'Pusat',
            'Laboratorium',
            'Fakultas',
        ];

        DB::transaction(function () use ($categories) {
            foreach ($categories as $cat) {
                KategoriUnit::firstOrCreate(
                    ['nama_kategori' => $cat]
                );
            }
        });
    }
}
