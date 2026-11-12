<?php

namespace Tests\Feature;

use App\Models\LaporanKinerja;
use App\Models\PeriodeAkademik;
use App\Models\UnitKerja;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LaporanKinerjaControllerTest extends TestCase
{
    private User $admin;
    private UnitKerja $unitBiro;
    private UnitKerja $unitLab;
    private User $userBiro;
    private User $userLab;
    private PeriodeAkademik $periode;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::where('username', 'admin')->firstOrFail();

        $this->periode = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();
        $this->periode->update([
            'status_aktif'     => true,
            'status_pelaporan' => 'Buka',
        ]);

        $this->unitBiro = UnitKerja::firstOrCreate(
            ['nama_unit' => 'Biro Administrasi Akademik Testing'],
            ['kategori_unit' => 'biro']
        );

        $this->unitLab = UnitKerja::firstOrCreate(
            ['nama_unit' => 'Laboratorium Komputer Terpadu Testing'],
            ['kategori_unit' => 'lab']
        );

        $this->userBiro = User::updateOrCreate(
            ['username' => 'op_biro_test'],
            [
                'password'      => 'password123',
                'role'          => 'biro',
                'id_unit_kerja' => $this->unitBiro->id,
                'is_active'     => true,
            ]
        );

        $this->userLab = User::updateOrCreate(
            ['username' => 'op_lab_test'],
            [
                'password'      => 'password123',
                'role'          => 'lab',
                'id_unit_kerja' => $this->unitLab->id,
                'is_active'     => true,
            ]
        );

        LaporanKinerja::whereIn('id_unit_kerja', [$this->unitBiro->id, $this->unitLab->id])->delete();
    }

    protected function tearDown(): void
    {
        LaporanKinerja::whereIn('id_unit_kerja', [$this->unitBiro->id, $this->unitLab->id])->delete();
        User::whereIn('username', ['op_biro_test', 'op_lab_test'])->delete();
        UnitKerja::whereIn('id', [$this->unitBiro->id, $this->unitLab->id])->delete();
        parent::tearDown();
    }

    public function test_store_validates_url_sanitizes_xss_and_locks_unit_kerja_to_authenticated_user(): void
    {
        Sanctum::actingAs($this->userBiro, ['biro']);

        // 1. Gagal jika link_bukti bukan URL valid
        $invalidUrlRes = $this->postJson('/api/laporan-kinerja', [
            'id_periode'         => $this->periode->id,
            'nama_kegiatan'      => 'Penyusunan Kalender Akademik',
            'uraian'             => 'Rapat koordinasi penyusunan kalender.',
            'link_bukti'         => 'bukan-format-url-valid',
            'nama_penandatangan' => 'Dr. H. Kepala Biro, M.Pd.',
        ]);
        $invalidUrlRes->assertStatus(422)
            ->assertJsonValidationErrors(['link_bukti']);

        // 2. Sukses menyimpan dengan sanitasi XSS otomatis (strip_tags) dan id_unit_kerja terkunci ke unitBiro
        $storeRes = $this->postJson('/api/laporan-kinerja', [
            'id_unit_kerja'      => $this->unitLab->id, // Percobaan Parameter Tampering ke unit lain -> harus diabaikan!
            'id_periode'         => $this->periode->id,
            'nama_kegiatan'      => '<script>alert("xss")</script>Digitalisasi Layanan Biro',
            'uraian'             => '<b>Implementasi</b> sistem pelayanan terpadu <script>evil()</script>mahasiswa.',
            'link_bukti'         => 'https://drive.google.com/file/d/123456/view',
            'kendala'            => '<img src=x onerror=alert(1)>Jaringan kurang stabil',
            'dukungan'           => 'Penambahan bandwidth server',
            'tindak_lanjut'      => 'Monitoring berkala setiap minggu',
            'nama_penandatangan' => '<h1>Dr. H. Kepala Biro</h1>',
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.id_unit_kerja', $this->unitBiro->id)
            ->assertJsonPath('data.nama_kegiatan', 'alert("xss")Digitalisasi Layanan Biro')
            ->assertJsonPath('data.uraian', 'Implementasi sistem pelayanan terpadu evil()mahasiswa.')
            ->assertJsonPath('data.kendala', 'Jaringan kurang stabil')
            ->assertJsonPath('data.nama_penandatangan', 'Dr. H. Kepala Biro')
            ->assertJsonPath('data.status', 'Review');
    }

    public function test_tenant_isolation_prevents_idor_on_index_show_and_update_and_allows_admin_validation(): void
    {
        // Buat 1 laporan milik Biro dan 1 laporan milik Lab
        $laporanBiro = LaporanKinerja::create([
            'id_unit_kerja'      => $this->unitBiro->id,
            'id_periode'         => $this->periode->id,
            'nama_kegiatan'      => 'Kegiatan Biro Akademik',
            'uraian'             => 'Uraian kegiatan biro.',
            'link_bukti'         => 'https://unuha.ac.id/bukti-biro',
            'nama_penandatangan' => 'Kepala Biro',
            'status'             => 'Review',
        ]);

        $laporanLab = LaporanKinerja::create([
            'id_unit_kerja'      => $this->unitLab->id,
            'id_periode'         => $this->periode->id,
            'nama_kegiatan'      => 'Praktikum Jaringan Komputer',
            'uraian'             => 'Uraian kegiatan laboratorium.',
            'link_bukti'         => 'https://unuha.ac.id/bukti-lab',
            'nama_penandatangan' => 'Kepala Lab',
            'status'             => 'Review',
        ]);

        // 1. Index oleh User Biro -> HANYA melihat laporan milik Biro sendiri
        Sanctum::actingAs($this->userBiro, ['biro']);
        $indexBiro = $this->getJson('/api/laporan-kinerja');
        $indexBiro->assertStatus(200);
        $unitIds = collect($indexBiro->json('data'))->pluck('id_unit_kerja')->unique()->values()->all();
        $this->assertEquals([$this->unitBiro->id], $unitIds);

        // 2. Show IDOR: User Biro mencoba melihat detail laporan milik Lab -> 403 Forbidden
        $this->getJson("/api/laporan-kinerja/{$laporanLab->id}")
            ->assertStatus(403);

        // 3. Update IDOR: User Lab mencoba mengedit laporan milik Biro -> 403 Forbidden
        Sanctum::actingAs($this->userLab, ['lab']);
        $this->putJson("/api/laporan-kinerja/{$laporanBiro->id}", [
            'nama_kegiatan'      => 'Bajak Kegiatan Biro',
            'uraian'             => 'Uraian bajakan.',
            'nama_penandatangan' => 'Hacker Lab',
        ])->assertStatus(403);

        // 4. Non-admin mencoba akses endpoint validasi admin -> 403 Forbidden
        $this->patchJson("/api/laporan-kinerja/{$laporanBiro->id}/validasi", [
            'status' => 'Selesai',
        ])->assertStatus(403);

        // 5. Admin dapat melihat seluruh laporan dari semua unit
        Sanctum::actingAs($this->admin, ['admin']);
        $indexAdmin = $this->getJson('/api/laporan-kinerja');
        $indexAdmin->assertStatus(200);
        $allUnitIds = collect($indexAdmin->json('data'))->pluck('id_unit_kerja')->unique()->values()->all();
        $this->assertContains($this->unitBiro->id, $allUnitIds);
        $this->assertContains($this->unitLab->id, $allUnitIds);

        // 6. Admin melakukan validasi status = 'Revisi' dengan catatan_revisi (XSS dibersihkan)
        $validasiRevisi = $this->patchJson("/api/laporan-kinerja/{$laporanBiro->id}/validasi", [
            'status'         => 'Revisi',
            'catatan_revisi' => '<script>evil()</script>Mohon lengkapi tautan bukti kegiatan.',
        ]);
        $validasiRevisi->assertStatus(200)
            ->assertJsonPath('data.status', 'Revisi')
            ->assertJsonPath('data.catatan_revisi', 'evil()Mohon lengkapi tautan bukti kegiatan.');

        // 7. User Biro mengupdate laporannya -> status otomatis kembali ke 'Review' & catatan_revisi di-reset null
        Sanctum::actingAs($this->userBiro, ['biro']);
        $updateOwn = $this->putJson("/api/laporan-kinerja/{$laporanBiro->id}", [
            'nama_kegiatan'      => 'Kegiatan Biro Akademik (Diperbaiki)',
            'uraian'             => 'Uraian kegiatan biro lengkap.',
            'link_bukti'         => 'https://drive.google.com/bukti-baru',
            'nama_penandatangan' => 'Kepala Biro',
        ]);
        $updateOwn->assertStatus(200)
            ->assertJsonPath('data.status', 'Review')
            ->assertJsonPath('data.catatan_revisi', null);

        // 8. Cetak PDF: Pemilik unit (Biro) & Admin dapat mengunduh PDF (200 OK, application/pdf), sedangkan Lab ditolak (403)
        $pdfOwner = $this->get("/api/laporan-kinerja/{$laporanBiro->id}/pdf");
        $pdfOwner->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        Sanctum::actingAs($this->userLab, ['lab']);
        $this->getJson("/api/laporan-kinerja/{$laporanBiro->id}/pdf")
            ->assertStatus(403);

        Sanctum::actingAs($this->admin, ['admin']);
        $pdfAdmin = $this->get("/api/laporan-kinerja/{$laporanBiro->id}/pdf");
        $pdfAdmin->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }

    public function test_unit_kerja_master_crud_and_admin_bypass_input_on_laporan_kinerja(): void
    {
        // 1. Non-admin ditolak mengakses Master Data Unit Kerja (/api/unit-kerja) -> 403 Forbidden
        Sanctum::actingAs($this->userBiro, ['biro']);
        $this->getJson('/api/unit-kerja')->assertStatus(403);
        $this->postJson('/api/unit-kerja', [
            'nama_unit'     => 'Pusat Bahasa Testing',
            'kategori_unit' => 'pusat',
        ])->assertStatus(403);

        // 2. Admin dapat melakukan CRUD Master Data Unit Kerja (termasuk kategori 'fakultas')
        Sanctum::actingAs($this->admin, ['admin']);
        UnitKerja::whereIn('nama_unit', ['Pusat Penjaminan Mutu Testing', 'Fakultas Ekonomi Testing'])->delete();

        $createUnit = $this->postJson('/api/unit-kerja', [
            'nama_unit'     => 'Pusat Penjaminan Mutu Testing',
            'kategori_unit' => 'pusat',
        ]);
        $createUnit->assertStatus(201)
            ->assertJsonPath('data.nama_unit', 'Pusat Penjaminan Mutu Testing')
            ->assertJsonPath('data.kategori_unit', 'pusat');

        $newUnitId = $createUnit->json('data.id');

        // Validasi unique nama_unit
        $this->postJson('/api/unit-kerja', [
            'nama_unit'     => 'Pusat Penjaminan Mutu Testing',
            'kategori_unit' => 'pusat',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['nama_unit']);

        // Update Unit Kerja ke kategori 'fakultas'
        $updateUnit = $this->putJson("/api/unit-kerja/{$newUnitId}", [
            'nama_unit'     => 'Fakultas Ekonomi Testing',
            'kategori_unit' => 'fakultas',
        ]);
        $updateUnit->assertStatus(200)
            ->assertJsonPath('data.nama_unit', 'Fakultas Ekonomi Testing')
            ->assertJsonPath('data.kategori_unit', 'fakultas');

        // 3. Aturan Ketat: Admin TIDAK diizinkan membuat laporan kinerja baru (403 Forbidden)
        $adminStoreRes = $this->postJson('/api/laporan-kinerja', [
            'id_periode'         => $this->periode->id,
            'nama_kegiatan'      => 'Audit Mutu Internal',
            'uraian'             => 'Pelaksanaan AMI tahunan.',
            'nama_penandatangan' => 'Ketua LPM',
        ]);
        $adminStoreRes->assertStatus(403);

        // 4. Delete Unit Kerja
        $this->deleteJson("/api/unit-kerja/{$newUnitId}")
            ->assertStatus(200);
        $this->assertDatabaseMissing('unit_kerja', ['id' => $newUnitId]);

        // 5. Export Excel: Non-admin ditolak (403 Forbidden), Admin diizinkan (200 OK, .xlsx)
        Sanctum::actingAs($this->userBiro, ['biro']);
        $this->getJson('/api/laporan-kinerja/export/excel')
            ->assertStatus(403);

        Sanctum::actingAs($this->admin, ['admin']);
        $excelRes = $this->get('/api/laporan-kinerja/export/excel');
        $excelRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    }
}
