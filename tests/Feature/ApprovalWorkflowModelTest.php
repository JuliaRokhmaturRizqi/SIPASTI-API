<?php

namespace Tests\Feature;

use App\Models\KategoriUnit;
use App\Models\LaporanKinerja;
use App\Models\PeriodeAkademik;
use App\Models\UnitKerja;
use App\Models\User;
use Tests\TestCase;

class ApprovalWorkflowModelTest extends TestCase
{
    public function test_user_jabatan_migration_and_model_methods(): void
    {
        $user = new User([
            'username' => 'test_user_staf',
            'role'     => 'biro',
            'jabatan'  => 'staf',
        ]);

        $this->assertEquals('staf', $user->jabatan);
        $this->assertTrue($user->isStaf());
        $this->assertFalse($user->isPimpinan());

        $user->jabatan = 'pimpinan';
        $this->assertTrue($user->isPimpinan());
        $this->assertFalse($user->isStaf());
    }

    public function test_laporan_kinerja_json_columns_and_casting(): void
    {
        $kategori = KategoriUnit::firstOrCreate(['nama_kategori' => 'Biro']);
        $unit     = UnitKerja::firstOrCreate(
            ['nama_unit' => 'Biro Pengujian Workflow'],
            ['id_kategori' => $kategori->id]
        );

        $periode = PeriodeAkademik::firstOrCreate(
            ['nama_periode' => '2026/2027 Ganjil'],
            [
                'status_aktif'     => true,
                'status_pelaporan' => 'Buka',
            ]
        );

        $identitasData = [
            'penanggung_jawab' => 'Dr. H. Pimpinan Biro, M.Pd.',
            'penyusun'         => 'Staf Pelaksana Teknis',
            'kontak'           => '081234567890',
        ];

        $pembahasanData = [
            [
                'aspek'     => 'Layanan Administrasi',
                'indikator' => 'Kecepatan Proses Dokumen',
                'target'    => '100%',
                'realisasi' => '98%',
            ],
            [
                'aspek'     => 'Digitalisasi Arsip',
                'indikator' => 'Upload SIPASTI',
                'target'    => '100 Dokumen',
                'realisasi' => '105 Dokumen',
            ],
        ];

        $kendalaData = [
            [
                'kendala'  => 'Gangguan koneksi internet lokal',
                'penyebab' => 'Perbaikan FO provider eksternal',
                'dampak'   => 'Penundaan sinkronisasi 1 jam',
            ],
        ];

        $dukunganData = [
            [
                'jenis_dukungan' => 'Pengadaan UPS Ruang Server',
                'pemberi'        => 'Biro Sarana dan Prasarana',
            ],
        ];

        $tindakLanjutData = [
            [
                'rencana'   => 'Migrasi cadangan ke jaringan sekunder',
                'pic'       => 'Staf IT Unit',
                'target_waktu' => 'November 2026',
            ],
        ];

        $laporan = LaporanKinerja::create([
            'id_unit_kerja'       => $unit->id,
            'id_periode'          => $periode->id,
            'bulan_laporan'       => 'November',
            'status_laporan'      => LaporanKinerja::STATUS_MENUNGGU_PIMPINAN,
            'identitas_laporan'   => $identitasData,
            'pembahasan_json'     => $pembahasanData,
            'kendala_json'        => $kendalaData,
            'dukungan_json'       => $dukunganData,
            'tindak_lanjut_json'  => $tindakLanjutData,
            'kesimpulan'          => 'Pelaksanaan kegiatan secara umum tercapai dengan predikat sangat baik.',
            'rekomendasi'         => 'Disarankan penambahan redundansi link jaringan.',
            'ttd_penyusun_path'   => 'ttd/penyusun_test.png',
            'ttd_pimpinan_path'   => 'ttd/pimpinan_test.png',
        ]);

        $this->assertDatabaseHas('laporan_kinerja', [
            'id'             => $laporan->id,
            'status_laporan' => 'menunggu_pimpinan',
        ]);

        // Verifikasi bahwa Laravel $casts otomatis mengubah string JSON menjadi Array PHP saat di-refresh/diambil
        $fresh = LaporanKinerja::findOrFail($laporan->id);

        $this->assertIsArray($fresh->identitas_laporan);
        $this->assertEquals('Dr. H. Pimpinan Biro, M.Pd.', $fresh->identitas_laporan['penanggung_jawab']);

        $this->assertIsArray($fresh->pembahasan_json);
        $this->assertCount(2, $fresh->pembahasan_json);
        $this->assertEquals('Layanan Administrasi', $fresh->pembahasan_json[0]['aspek']);

        $this->assertIsArray($fresh->kendala_json);
        $this->assertEquals('Gangguan koneksi internet lokal', $fresh->kendala_json[0]['kendala']);

        $this->assertIsArray($fresh->dukungan_json);
        $this->assertEquals('Pengadaan UPS Ruang Server', $fresh->dukungan_json[0]['jenis_dukungan']);

        $this->assertIsArray($fresh->tindak_lanjut_json);
        $this->assertEquals('Staf IT Unit', $fresh->tindak_lanjut_json[0]['pic']);

        $this->assertEquals('ttd/penyusun_test.png', $fresh->ttd_penyusun_path);
        $this->assertEquals('ttd/pimpinan_test.png', $fresh->ttd_pimpinan_path);

        // Backward-compatibility: ttd_image_path mengarah ke ttd_penyusun_path
        $this->assertEquals('ttd/penyusun_test.png', $fresh->ttd_image_path);

        $laporan->delete();
        $unit->delete();
    }
}
