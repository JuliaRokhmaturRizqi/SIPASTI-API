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
        Schema::create('dokumen_pelaporan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('id_prodi')
                  ->constrained('prodi')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->foreignId('id_periode')
                  ->constrained('periode_akademik')
                  ->restrictOnDelete()
                  ->cascadeOnUpdate();

            // 1: Kurikulum, 2: Jadwal, 3: Nilai, 4: Yudisium
            $table->unsignedTinyInteger('tahap_dokumen');
            $table->string('file_path')->nullable();
            $table->enum('status', ['Belum Unggah', 'Review', 'Revisi', 'Selesai'])
                  ->default('Belum Unggah');
            $table->text('catatan_revisi')->nullable();
            $table->dateTime('tanggal_upload')->nullable();
            $table->timestamps();

            $table->unique(['id_prodi', 'id_periode', 'tahap_dokumen'], 'unik_dokumen_prodi_periode_tahap');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dokumen_pelaporan');
    }
};
