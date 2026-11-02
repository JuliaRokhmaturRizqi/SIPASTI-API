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
        Schema::create('lampiran_dokumen', function (Blueprint $table) {
            $table->id();
            $table->foreignId('dokumen_pelaporan_id')
                  ->constrained('dokumen_pelaporan')
                  ->cascadeOnDelete()
                  ->cascadeOnUpdate();
            $table->string('jenis_lampiran', 100)->index();
            $table->string('nama_file_asli', 255);
            $table->string('file_path', 500);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('lampiran_dokumen');
    }
};
