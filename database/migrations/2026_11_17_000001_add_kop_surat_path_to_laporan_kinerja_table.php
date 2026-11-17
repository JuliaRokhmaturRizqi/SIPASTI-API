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
        if (Schema::hasTable('laporan_kinerja') && ! Schema::hasColumn('laporan_kinerja', 'kop_surat_path')) {
            Schema::table('laporan_kinerja', function (Blueprint $table) {
                $table->string('kop_surat_path', 255)->nullable()->after('ttd_pimpinan_path');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('laporan_kinerja') && Schema::hasColumn('laporan_kinerja', 'kop_surat_path')) {
            Schema::table('laporan_kinerja', function (Blueprint $table) {
                $table->dropColumn('kop_surat_path');
            });
        }
    }
};
