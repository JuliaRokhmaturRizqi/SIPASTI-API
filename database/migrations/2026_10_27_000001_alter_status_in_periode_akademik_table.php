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
            $table->dropIndex(['status_aktif']);
            $table->dropColumn('status_aktif');
            $table->enum('status', ['Aktif', 'Nonaktif', 'Arsip'])
                  ->default('Nonaktif')
                  ->after('nama_periode')
                  ->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('periode_akademik', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
            $table->boolean('status_aktif')
                  ->default(false)
                  ->after('nama_periode')
                  ->index();
        });
    }
};
