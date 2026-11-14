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
        if (Schema::hasTable('laporan_kinerja')) {
            Schema::table('laporan_kinerja', function (Blueprint $table) {
                // 1. Ubah kolom lama menjadi nullable agar pengisian form dinamis fleksibel
                if (Schema::hasColumn('laporan_kinerja', 'nama_kegiatan')) {
                    $table->string('nama_kegiatan')->nullable()->change();
                }
                if (Schema::hasColumn('laporan_kinerja', 'uraian')) {
                    $table->text('uraian')->nullable()->change();
                }
                if (Schema::hasColumn('laporan_kinerja', 'nama_penandatangan')) {
                    $table->string('nama_penandatangan')->nullable()->change();
                }
                if (Schema::hasColumn('laporan_kinerja', 'status')) {
                    $table->string('status')->nullable()->change();
                }

                // 2. Kolom Tanda Tangan: ttd_penyusun_path & ttd_pimpinan_path
                if (Schema::hasColumn('laporan_kinerja', 'ttd_image_path')) {
                    $table->renameColumn('ttd_image_path', 'ttd_penyusun_path');
                } elseif (! Schema::hasColumn('laporan_kinerja', 'ttd_penyusun_path')) {
                    $table->string('ttd_penyusun_path', 255)->nullable();
                }

                if (! Schema::hasColumn('laporan_kinerja', 'ttd_pimpinan_path')) {
                    $table->string('ttd_pimpinan_path', 255)->nullable();
                }

                // 3. Kolom Workflow: status_laporan
                if (! Schema::hasColumn('laporan_kinerja', 'status_laporan')) {
                    $table->enum('status_laporan', [
                        'menunggu_pimpinan',
                        'revisi_pimpinan',
                        'menunggu_admin',
                        'revisi_admin',
                        'selesai',
                    ])->default('menunggu_pimpinan');
                }

                // 4. Kolom JSON Form Dinamis
                if (! Schema::hasColumn('laporan_kinerja', 'identitas_laporan')) {
                    $table->json('identitas_laporan')->nullable();
                }
                if (! Schema::hasColumn('laporan_kinerja', 'pembahasan_json')) {
                    $table->json('pembahasan_json')->nullable();
                }
                if (! Schema::hasColumn('laporan_kinerja', 'kendala_json')) {
                    $table->json('kendala_json')->nullable();
                }
                if (! Schema::hasColumn('laporan_kinerja', 'dukungan_json')) {
                    $table->json('dukungan_json')->nullable();
                }
                if (! Schema::hasColumn('laporan_kinerja', 'tindak_lanjut_json')) {
                    $table->json('tindak_lanjut_json')->nullable();
                }

                // 5. Kolom Teks
                if (! Schema::hasColumn('laporan_kinerja', 'kesimpulan')) {
                    $table->text('kesimpulan')->nullable();
                }
                if (! Schema::hasColumn('laporan_kinerja', 'rekomendasi')) {
                    $table->text('rekomendasi')->nullable();
                }
            });

            // Sinkronisasi data lama status -> status_laporan jika ada
            if (Schema::hasColumn('laporan_kinerja', 'status') && Schema::hasColumn('laporan_kinerja', 'status_laporan')) {
                DB::table('laporan_kinerja')
                    ->where('status', 'Selesai')
                    ->update(['status_laporan' => 'selesai']);

                DB::table('laporan_kinerja')
                    ->where('status', 'Revisi')
                    ->update(['status_laporan' => 'revisi_pimpinan']);

                DB::table('laporan_kinerja')
                    ->where('status', 'Review')
                    ->update(['status_laporan' => 'menunggu_pimpinan']);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('laporan_kinerja')) {
            Schema::table('laporan_kinerja', function (Blueprint $table) {
                $columnsToDrop = [
                    'identitas_laporan',
                    'pembahasan_json',
                    'kendala_json',
                    'dukungan_json',
                    'tindak_lanjut_json',
                    'kesimpulan',
                    'rekomendasi',
                    'status_laporan',
                    'ttd_pimpinan_path',
                ];

                foreach ($columnsToDrop as $col) {
                    if (Schema::hasColumn('laporan_kinerja', $col)) {
                        $table->dropColumn($col);
                    }
                }

                if (Schema::hasColumn('laporan_kinerja', 'ttd_penyusun_path')) {
                    $table->renameColumn('ttd_penyusun_path', 'ttd_image_path');
                }
            });
        }
    }
};
