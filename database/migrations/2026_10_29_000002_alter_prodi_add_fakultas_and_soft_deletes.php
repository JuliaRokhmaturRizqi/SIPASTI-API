<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->foreignId('id_fakultas')
                  ->nullable()
                  ->after('id')
                  ->constrained('fakultas')
                  ->nullOnDelete()
                  ->cascadeOnUpdate();

            $table->string('periode_mulai_aktif', 50)
                  ->nullable()
                  ->after('nama_prodi')
                  ->index();

            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('prodi', function (Blueprint $table) {
            $table->dropForeign(['id_fakultas']);
            $table->dropIndex(['periode_mulai_aktif']);
            $table->dropColumn(['id_fakultas', 'periode_mulai_aktif', 'deleted_at']);
        });
    }
};
