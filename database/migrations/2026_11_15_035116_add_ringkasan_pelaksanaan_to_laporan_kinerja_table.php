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
        Schema::table('laporan_kinerja', function (Blueprint $table) {
            $table->text('ringkasan_pelaksanaan')->nullable()->after('identitas_laporan');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('laporan_kinerja', function (Blueprint $table) {
            $table->dropColumn('ringkasan_pelaksanaan');
        });
    }
};
