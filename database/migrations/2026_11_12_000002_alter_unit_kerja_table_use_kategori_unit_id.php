<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('unit_kerja')) {
            // 1. Pastikan kategori default tersedia di tabel kategori_unit
            $defaults = ['Biro', 'Pusat', 'Laboratorium', 'Fakultas'];
            foreach ($defaults as $cat) {
                if (! DB::table('kategori_unit')->where('nama_kategori', $cat)->exists()) {
                    DB::table('kategori_unit')->insert([
                        'nama_kategori' => $cat,
                        'created_at'    => now(),
                        'updated_at'    => now(),
                    ]);
                }
            }

            // 2. Tambahkan kolom id_kategori jika belum ada
            if (! Schema::hasColumn('unit_kerja', 'id_kategori')) {
                Schema::table('unit_kerja', function (Blueprint $table) {
                    $table->foreignId('id_kategori')
                        ->nullable()
                        ->after('nama_unit')
                        ->constrained('kategori_unit')
                        ->nullOnDelete();
                });
            }

            // 3. Migrasi data lama dari kolom kategori_unit ke id_kategori
            if (Schema::hasColumn('unit_kerja', 'kategori_unit')) {
                $categories = DB::table('kategori_unit')->pluck('id', 'nama_kategori');

                $mapping = [
                    'biro'     => $categories['Biro'] ?? null,
                    'pusat'    => $categories['Pusat'] ?? null,
                    'lab'      => $categories['Laboratorium'] ?? null,
                    'fakultas' => $categories['Fakultas'] ?? null,
                ];

                foreach ($mapping as $oldVal => $newId) {
                    if ($newId) {
                        DB::table('unit_kerja')
                            ->where('kategori_unit', $oldVal)
                            ->update(['id_kategori' => $newId]);
                    }
                }

                // 4. Hapus kolom kategori_unit lama
                Schema::table('unit_kerja', function (Blueprint $table) {
                    $table->dropColumn('kategori_unit');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('unit_kerja')) {
            if (! Schema::hasColumn('unit_kerja', 'kategori_unit')) {
                Schema::table('unit_kerja', function (Blueprint $table) {
                    $table->enum('kategori_unit', ['biro', 'pusat', 'lab', 'fakultas'])
                        ->default('biro')
                        ->after('nama_unit');
                });

                $categories = DB::table('kategori_unit')->pluck('nama_kategori', 'id');
                $reverseMapping = [
                    'Biro'         => 'biro',
                    'Pusat'        => 'pusat',
                    'Laboratorium' => 'lab',
                    'Fakultas'     => 'fakultas',
                ];

                foreach ($categories as $catId => $catName) {
                    $oldEnum = $reverseMapping[$catName] ?? 'biro';
                    DB::table('unit_kerja')
                        ->where('id_kategori', $catId)
                        ->update(['kategori_unit' => $oldEnum]);
                }
            }

            if (Schema::hasColumn('unit_kerja', 'id_kategori')) {
                Schema::table('unit_kerja', function (Blueprint $table) {
                    $table->dropForeign(['id_kategori']);
                    $table->dropColumn('id_kategori');
                });
            }
        }
    }
};
