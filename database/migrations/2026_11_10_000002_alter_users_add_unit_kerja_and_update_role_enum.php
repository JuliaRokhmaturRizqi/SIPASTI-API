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
        // 1. Perbarui enum kolom role untuk mendukung ['admin', 'user', 'biro', 'pusat', 'lab']
        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user', 'biro', 'pusat', 'lab') NOT NULL DEFAULT 'user'");
        } else {
            Schema::table('users', function (Blueprint $table) {
                $table->enum('role', ['admin', 'user', 'biro', 'pusat', 'lab'])->default('user')->change();
            });
        }

        // 2. Tambahkan kolom id_unit_kerja (tanpa mengubah atau menghapus id_fakultas)
        if (! Schema::hasColumn('users', 'id_unit_kerja')) {
            Schema::table('users', function (Blueprint $table) {
                $table->foreignId('id_unit_kerja')
                    ->nullable()
                    ->after('id_fakultas')
                    ->constrained('unit_kerja')
                    ->nullOnDelete()
                    ->cascadeOnUpdate();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('users', 'id_unit_kerja')) {
            Schema::table('users', function (Blueprint $table) {
                try {
                    $table->dropForeign(['id_unit_kerja']);
                } catch (\Throwable $e) {
                    // Abaikan jika constraint sudah tidak ada
                }
                $table->dropColumn('id_unit_kerja');
            });
        }

        if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
            DB::statement("UPDATE users SET role = 'user' WHERE role IN ('biro', 'pusat', 'lab')");
            DB::statement("ALTER TABLE users MODIFY COLUMN role ENUM('admin', 'user') NOT NULL DEFAULT 'user'");
        }
    }
};
