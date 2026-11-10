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
        if (! Schema::hasTable('laporan_kinerja')) {
            Schema::create('laporan_kinerja', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_unit_kerja')
                    ->constrained('unit_kerja')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
                $table->foreignId('id_periode')
                    ->constrained('periode_akademik')
                    ->cascadeOnDelete()
                    ->cascadeOnUpdate();
                $table->string('nama_kegiatan');
                $table->text('uraian');
                $table->string('link_bukti')->nullable();
                $table->text('kendala')->nullable();
                $table->text('dukungan')->nullable();
                $table->text('tindak_lanjut')->nullable();
                $table->string('nama_penandatangan');
                $table->enum('status', ['Review', 'Revisi', 'Selesai'])->default('Review');
                $table->text('catatan_revisi')->nullable();
                $table->timestamps();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('laporan_kinerja');
    }
};
