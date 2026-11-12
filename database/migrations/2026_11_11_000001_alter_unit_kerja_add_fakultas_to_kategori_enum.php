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
        if (Schema::hasTable('unit_kerja')) {
            if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
                DB::statement("ALTER TABLE unit_kerja MODIFY COLUMN kategori_unit ENUM('biro', 'pusat', 'lab', 'fakultas') NOT NULL");
            } else {
                Schema::table('unit_kerja', function (Blueprint $table) {
                    $table->enum('kategori_unit', ['biro', 'pusat', 'lab', 'fakultas'])->change();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('unit_kerja')) {
            if (DB::getDriverName() === 'mysql' || DB::getDriverName() === 'mariadb') {
                DB::statement("UPDATE unit_kerja SET kategori_unit = 'biro' WHERE kategori_unit = 'fakultas'");
                DB::statement("ALTER TABLE unit_kerja MODIFY COLUMN kategori_unit ENUM('biro', 'pusat', 'lab') NOT NULL");
            }
        }
    }
};
