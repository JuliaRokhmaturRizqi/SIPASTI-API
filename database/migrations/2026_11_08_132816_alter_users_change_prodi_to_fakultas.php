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
        // 1. Tambahkan kolom id_fakultas jika belum ada
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'id_fakultas')) {
                $table->foreignId('id_fakultas')
                      ->nullable()
                      ->after('role')
                      ->constrained('fakultas')
                      ->nullOnDelete()
                      ->cascadeOnUpdate();
            }
        });

        // 2. Data Migration: Petakan akun operator prodi lama ke fakultasnya masing-masing
        if (Schema::hasColumn('users', 'id_prodi')) {
            DB::statement('
                UPDATE users u
                INNER JOIN prodi p ON u.id_prodi = p.id
                SET u.id_fakultas = p.id_fakultas
                WHERE u.id_fakultas IS NULL AND u.id_prodi IS NOT NULL
            ');

            // 3. Hapus foreign key constraint dan kolom id_prodi
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['id_prodi']);
                $table->dropColumn('id_prodi');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Kembalikan kolom id_prodi
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'id_prodi')) {
                $table->foreignId('id_prodi')
                      ->nullable()
                      ->after('role')
                      ->constrained('prodi')
                      ->nullOnDelete()
                      ->cascadeOnUpdate();
            }
        });

        // Hapus foreign key dan kolom id_fakultas
        if (Schema::hasColumn('users', 'id_fakultas')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropForeign(['id_fakultas']);
                $table->dropColumn('id_fakultas');
            });
        }
    }
};
