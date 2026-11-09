<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up()
    {
        if (! Schema::hasTable('prodi')) {
            Schema::create('prodi', function (Blueprint $table) {
                $table->id();
                $table->foreignId('id_fakultas')->constrained('fakultas')->cascadeOnDelete();
                $table->string('nama_prodi');
                $table->timestamps();
            });
        }
    }

    public function down()
    {
        Schema::dropIfExists('prodi');
    }
};