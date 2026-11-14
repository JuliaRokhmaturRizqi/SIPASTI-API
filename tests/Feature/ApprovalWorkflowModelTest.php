<?php

namespace Tests\Feature;

use App\Models\KategoriUnit;
use App\Models\LaporanKinerja;
use App\Models\PeriodeAkademik;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
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
                'rencana'      => 'Migrasi cadangan ke jaringan sekunder',
                'pic'          => 'Staf IT Unit',
                'target_waktu' => 'November 2026',
            ],
        ];

        $laporan = LaporanKinerja::create([
            'id_unit_kerja'      => $unit->id,
            'id_periode'         => $periode->id,
            'bulan_laporan'      => 'November',
            'status_laporan'     => LaporanKinerja::STATUS_MENUNGGU_PIMPINAN,
            'identitas_laporan'  => $identitasData,
            'pembahasan_json'    => $pembahasanData,
            'kendala_json'       => $kendalaData,
            'dukungan_json'      => $dukunganData,
            'tindak_lanjut_json' => $tindakLanjutData,
            'kesimpulan'         => 'Pelaksanaan kegiatan secara umum tercapai dengan predikat sangat baik.',
            'rekomendasi'        => 'Disarankan penambahan redundansi link jaringan.',
            'ttd_penyusun_path'  => 'ttd/penyusun_test.png',
            'ttd_pimpinan_path'  => 'ttd/pimpinan_test.png',
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

    public function test_store_for_staf_only_and_approve_for_pimpinan_with_anti_idor(): void
    {
        Storage::fake('public');

        $kategori = KategoriUnit::firstOrCreate(['nama_kategori' => 'Biro']);

        $unitA = UnitKerja::firstOrCreate(
            ['nama_unit' => 'Biro Akademik Approval Test'],
            ['id_kategori' => $kategori->id]
        );
        $unitB = UnitKerja::firstOrCreate(
            ['nama_unit' => 'Biro Keuangan Approval Test'],
            ['id_kategori' => $kategori->id]
        );

        $periode = PeriodeAkademik::firstOrCreate(
            ['nama_periode' => '2026/2027 Ganjil'],
            [
                'status_aktif'     => true,
                'status_pelaporan' => 'Buka',
            ]
        );
        $periode->update(['status_aktif' => true, 'status_pelaporan' => 'Buka']);

        $stafA = User::updateOrCreate(
            ['username' => 'staf_unit_a_test'],
            [
                'password'      => 'password123',
                'role'          => 'biro',
                'jabatan'       => 'staf',
                'id_unit_kerja' => $unitA->id,
                'is_active'     => true,
            ]
        );

        $pimpinanA = User::updateOrCreate(
            ['username' => 'pimpinan_unit_a_test'],
            [
                'password'      => 'password123',
                'role'          => 'biro',
                'jabatan'       => 'pimpinan',
                'id_unit_kerja' => $unitA->id,
                'is_active'     => true,
            ]
        );

        $pimpinanB = User::updateOrCreate(
            ['username' => 'pimpinan_unit_b_test'],
            [
                'password'      => 'password123',
                'role'          => 'biro',
                'jabatan'       => 'pimpinan',
                'id_unit_kerja' => $unitB->id,
                'is_active'     => true,
            ]
        );

        LaporanKinerja::whereIn('id_unit_kerja', [$unitA->id, $unitB->id])->delete();

        $dummyBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        // 1. Pimpinan TIDAK boleh membuat laporan (403 Forbidden)
        Sanctum::actingAs($pimpinanA, ['biro']);
        $forbiddenCreate = $this->postJson('/api/laporan-kinerja', [
            'id_periode'        => $periode->id,
            'identitas_laporan' => ['penyusun' => 'Staf A'],
            'pembahasan_json'   => [['aspek' => 'Akademik']],
        ]);
        $forbiddenCreate->assertStatus(403)
            ->assertJsonPath('message', 'Hanya staf yang dapat membuat laporan.');

        // 2. Staf membuat laporan dengan format JSON dinamis & TTD Penyusun -> 201 Created
        Sanctum::actingAs($stafA, ['biro']);

        // Uji validasi apabila identitas_laporan bukan array -> 422
        $invalidJsonRes = $this->postJson('/api/laporan-kinerja', [
            'id_periode'        => $periode->id,
            'identitas_laporan' => 'bukan-array',
            'pembahasan_json'   => 'bukan-array',
        ]);
        $invalidJsonRes->assertStatus(422)
            ->assertJsonValidationErrors(['identitas_laporan', 'pembahasan_json']);

        $storeRes = $this->postJson('/api/laporan-kinerja', [
            'id_periode'         => $periode->id,
            'bulan_laporan'      => 'Desember',
            'identitas_laporan'  => [
                'penanggung_jawab' => 'Dr. Pimpinan Unit A',
                'penyusun'         => '<script>alert(1)</script>Budi Santoso, S.Kom.',
            ],
            'pembahasan_json'    => [
                [
                    'aspek'     => 'Pelayanan Akademik',
                    'indikator' => 'Ketepatan Jadwal',
                    'realisasi' => '100%',
                ],
            ],
            'kendala_json'       => [
                ['kendala' => 'Server padat', 'penyebab' => 'KRS serentak', 'dampak' => 'Lambat 5 menit'],
            ],
            'dukungan_json'      => [
                ['jenis_dukungan' => 'Upgrade bandwidth'],
            ],
            'tindak_lanjut_json' => [
                ['rencana' => 'Optimasi query'],
            ],
            'kesimpulan'         => 'Kegiatan berjalan efektif.',
            'rekomendasi'        => 'Perlu peningkatan kapasitas server.',
            'ttd_penyusun'       => $dummyBase64,
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('data.id_unit_kerja', $unitA->id)
            ->assertJsonPath('data.status_laporan', 'menunggu_pimpinan')
            ->assertJsonPath('data.identitas_laporan.penyusun', 'alert(1)Budi Santoso, S.Kom.');

        $laporanId = $storeRes->json('data.id');
        $this->assertNotNull($storeRes->json('data.ttd_penyusun_path'));

        // 3. Staf TIDAK boleh memvalidasi laporan di endpoint /approve (403 Forbidden)
        $stafApproveRes = $this->postJson("/api/laporan-kinerja/{$laporanId}/approve", [
            'aksi' => 'setuju',
        ]);
        $stafApproveRes->assertStatus(403)
            ->assertJsonPath('message', 'Hanya pimpinan yang dapat memvalidasi laporan.');

        // 4. Pimpinan Unit B TIDAK boleh memvalidasi laporan milik Unit A (Anti-IDOR -> 403 Forbidden)
        Sanctum::actingAs($pimpinanB, ['biro']);
        $idorApproveRes = $this->postJson("/api/laporan-kinerja/{$laporanId}/approve", [
            'aksi' => 'setuju',
        ]);
        $idorApproveRes->assertStatus(403)
            ->assertJsonPath('message', 'Anda tidak berhak memvalidasi laporan unit lain.');

        // 5. Pimpinan Unit A menolak laporan ('tolak') -> status_laporan menjadi 'revisi_pimpinan'
        Sanctum::actingAs($pimpinanA, ['biro']);
        $rejectRes = $this->postJson("/api/laporan-kinerja/{$laporanId}/approve", [
            'aksi'           => 'tolak',
            'catatan_revisi' => 'Mohon lengkapi tabel realisasi indikator kedua.',
        ]);
        $rejectRes->assertStatus(200)
            ->assertJsonPath('data.status_laporan', 'revisi_pimpinan')
            ->assertJsonPath('data.catatan_revisi', 'Mohon lengkapi tabel realisasi indikator kedua.');

        // 6. Staf Unit A memperbaiki laporan -> status_laporan kembali ke 'menunggu_pimpinan'
        Sanctum::actingAs($stafA, ['biro']);
        $updateRes = $this->putJson("/api/laporan-kinerja/{$laporanId}", [
            'identitas_laporan' => [
                'penanggung_jawab' => 'Dr. Pimpinan Unit A',
                'penyusun'         => 'Budi Santoso, S.Kom.',
            ],
            'pembahasan_json'   => [
                ['aspek' => 'Pelayanan Akademik', 'indikator' => 'Ketepatan Jadwal', 'realisasi' => '100%'],
                ['aspek' => 'Arsip Digital', 'indikator' => 'Kelengkapan Berkas', 'realisasi' => '95%'],
            ],
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.status_laporan', 'menunggu_pimpinan');

        // 7. Pimpinan Unit A menyetujui laporan ('setuju') beserta TTD Pimpinan -> status_laporan menjadi 'menunggu_admin'
        Sanctum::actingAs($pimpinanA, ['biro']);
        $approveRes = $this->postJson("/api/laporan-kinerja/{$laporanId}/approve", [
            'aksi'         => 'setuju',
            'ttd_pimpinan' => $dummyBase64,
        ]);
        $approveRes->assertStatus(200)
            ->assertJsonPath('data.status_laporan', 'menunggu_admin');

        $this->assertNotNull($approveRes->json('data.ttd_pimpinan_path'));
        Storage::disk('public')->assertExists($approveRes->json('data.ttd_pimpinan_path'));

        // Cleanup
        LaporanKinerja::whereIn('id_unit_kerja', [$unitA->id, $unitB->id])->delete();
        User::whereIn('username', ['staf_unit_a_test', 'pimpinan_unit_a_test', 'pimpinan_unit_b_test'])->delete();
        UnitKerja::whereIn('id', [$unitA->id, $unitB->id])->delete();
    }
}
