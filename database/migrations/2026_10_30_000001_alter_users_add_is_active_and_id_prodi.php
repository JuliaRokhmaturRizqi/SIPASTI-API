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
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'id_prodi')) {
                $table->foreignId('id_prodi')
                      ->nullable()
                      ->after('role')
                      ->constrained('prodi')
                      ->nullOnDelete()
                      ->cascadeOnUpdate();
            }

            if (! Schema::hasColumn('users', 'is_active')) {
                $table->boolean('is_active')
                      ->default(true)
                      ->after('id_prodi')
                      ->index();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'is_active')) {
                $table->dropIndex(['is_active']);
                $table->dropColumn('is_active');
            }
        });
    }
};
