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
        Schema::table('periode_akademik', function (Blueprint $table) {
            if (! Schema::hasColumn('periode_akademik', 'status_aktif')) {
                $table->boolean('status_aktif')
                      ->default(true)
                      ->after('nama_periode')
                      ->index();
            }
        });

        // Sinkronisasi status_aktif dengan data yang sudah ada
        if (Schema::hasColumn('periode_akademik', 'status_aktif')) {
            DB::table('periode_akademik')
                ->where('status_pelaporan', 'Tutup')
                ->update(['status_aktif' => false]);

            DB::table('periode_akademik')
                ->where('status_pelaporan', 'Buka')
                ->update(['status_aktif' => true]);

            DB::table('periode_akademik')
                ->whereNull('status_aktif')
                ->update(['status_aktif' => true]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periode_akademik', function (Blueprint $table) {
            if (Schema::hasColumn('periode_akademik', 'status_aktif')) {
                $table->dropIndex(['status_aktif']);
                $table->dropColumn('status_aktif');
            }
        });
    }
};
