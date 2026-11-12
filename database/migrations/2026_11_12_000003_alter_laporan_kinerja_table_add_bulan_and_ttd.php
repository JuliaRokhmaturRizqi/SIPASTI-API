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
        if (Schema::hasTable('laporan_kinerja')) {
            Schema::table('laporan_kinerja', function (Blueprint $table) {
                if (! Schema::hasColumn('laporan_kinerja', 'bulan_laporan')) {
                    $table->string('bulan_laporan', 50)->nullable()->after('id_periode');
                }
                if (! Schema::hasColumn('laporan_kinerja', 'ttd_image_path')) {
                    $table->string('ttd_image_path', 255)->nullable()->after('nama_penandatangan');
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('laporan_kinerja')) {
            Schema::table('laporan_kinerja', function (Blueprint $table) {
                if (Schema::hasColumn('laporan_kinerja', 'ttd_image_path')) {
                    $table->dropColumn('ttd_image_path');
                }
                if (Schema::hasColumn('laporan_kinerja', 'bulan_laporan')) {
                    $table->dropColumn('bulan_laporan');
                }
            });
        }
    }
};
