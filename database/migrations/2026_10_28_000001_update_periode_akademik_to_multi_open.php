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
        Schema::table('periode_akademik', function (Blueprint $table) {
            if (Schema::hasColumn('periode_akademik', 'status')) {
                $table->dropIndex(['status']);
                $table->dropColumn('status');
            }

            $table->enum('status_pelaporan', ['Buka', 'Tutup'])
                  ->default('Tutup')
                  ->after('nama_periode')
                  ->index();

            $table->boolean('is_default')
                  ->default(false)
                  ->after('status_pelaporan')
                  ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periode_akademik', function (Blueprint $table) {
            $table->dropIndex(['status_pelaporan']);
            $table->dropIndex(['is_default']);
            $table->dropColumn(['status_pelaporan', 'is_default']);

            $table->enum('status', ['Aktif', 'Nonaktif', 'Arsip'])
                  ->default('Nonaktif')
                  ->after('nama_periode')
                  ->index();
        });
    }
};
