<?php

namespace Tests\Feature;

use App\Models\KategoriUnit;
use App\Models\LaporanKinerja;
use App\Models\PeriodeAkademik;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class KategoriUnitAndPhase1KinerjaTest extends TestCase
{
    private User $admin;
    private User $userBiro;
    private UnitKerja $unitBiro;
    private PeriodeAkademik $periode;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

        $this->admin = User::where('username', 'admin')->firstOrFail();

        $this->periode = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();
        $this->periode->update([
            'status_aktif'     => true,
            'status_pelaporan' => 'Buka',
        ]);

        $kategoriBiro = KategoriUnit::firstOrCreate(['nama_kategori' => 'Biro']);

        $this->unitBiro = UnitKerja::firstOrCreate(
            ['nama_unit' => 'Biro Testing Phase 1'],
            ['id_kategori' => $kategoriBiro->id]
        );

        $this->userBiro = User::updateOrCreate(
            ['username' => 'op_biro_phase1'],
            [
                'password'      => 'password123',
                'role'          => 'biro',
                'id_unit_kerja' => $this->unitBiro->id,
                'is_active'     => true,
            ]
        );

        LaporanKinerja::where('id_unit_kerja', $this->unitBiro->id)->delete();
    }

    protected function tearDown(): void
    {
        LaporanKinerja::where('id_unit_kerja', $this->unitBiro->id)->delete();
        User::where('username', 'op_biro_phase1')->delete();
        UnitKerja::where('id', $this->unitBiro->id)->delete();
        KategoriUnit::where('nama_kategori', 'Divisi Inovasi Baru')->delete();

        parent::tearDown();
    }

    public function test_kategori_unit_crud_protected_for_admin_only(): void
    {
        // 1. Non-admin ditolak (403 Forbidden)
        Sanctum::actingAs($this->userBiro, ['biro']);
        $this->getJson('/api/kategori-unit')->assertStatus(403);
        $this->postJson('/api/kategori-unit', ['nama_kategori' => 'Divisi Inovasi Baru'])->assertStatus(403);

        // 2. Admin dapat membuat Kategori Unit baru (201 Created)
        Sanctum::actingAs($this->admin, ['admin']);
        $storeRes = $this->postJson('/api/kategori-unit', [
            'nama_kategori' => 'Divisi Inovasi Baru',
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('data.nama_kategori', 'Divisi Inovasi Baru');

        $kategoriId = $storeRes->json('data.id');

        // Validasi Unique
        $this->postJson('/api/kategori-unit', [
            'nama_kategori' => 'Divisi Inovasi Baru',
        ])->assertStatus(422)
          ->assertJsonValidationErrors(['nama_kategori']);

        // 3. Admin dapat melihat daftar Kategori Unit
        $indexRes = $this->getJson('/api/kategori-unit');
        $indexRes->assertStatus(200)
            ->assertJsonStructure(['status', 'data' => [['id', 'nama_kategori']]]);

        // 4. Admin dapat mengupdate Kategori Unit
        $updateRes = $this->putJson("/api/kategori-unit/{$kategoriId}", [
            'nama_kategori' => 'Divisi Inovasi Diperbarui',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.nama_kategori', 'Divisi Inovasi Diperbarui');

        // 5. Admin dapat menghapus Kategori Unit yang belum dipakai
        $deleteRes = $this->deleteJson("/api/kategori-unit/{$kategoriId}");
        $deleteRes->assertStatus(200);
        $this->assertDatabaseMissing('kategori_unit', ['id' => $kategoriId]);

        // 6. Kategori yang sedang digunakan unit kerja TIDAK dapat dihapus (422)
        $kategoriUsed = KategoriUnit::where('nama_kategori', 'Biro')->firstOrFail();
        $this->deleteJson("/api/kategori-unit/{$kategoriUsed->id}")
            ->assertStatus(422)
            ->assertJsonPath('message', 'Kategori unit tidak dapat dihapus karena masih digunakan oleh unit kerja.');
    }

    public function test_laporan_kinerja_validates_one_month_one_report_per_unit_per_period(): void
    {
        Sanctum::actingAs($this->userBiro, ['biro']);

        // 1. Simpan laporan pertama untuk bulan "Januari"
        $res1 = $this->postJson('/api/laporan-kinerja', [
            'id_periode'         => $this->periode->id,
            'bulan_laporan'      => 'Januari',
            'nama_kegiatan'      => 'Kegiatan Pelayanan Januari',
            'uraian'             => 'Pelaksanaan layanan administrasi bulan Januari.',
            'nama_penandatangan' => 'Drs. H. Pimpinan Biro',
        ]);

        $res1->assertStatus(201)
            ->assertJsonPath('data.bulan_laporan', 'Januari');

        // 2. Coba simpan laporan kedua untuk bulan "Januari" pada periode yang sama -> Wajib 422 Duplikat
        $resDuplicate = $this->postJson('/api/laporan-kinerja', [
            'id_periode'         => $this->periode->id,
            'bulan_laporan'      => 'Januari',
            'nama_kegiatan'      => 'Kegiatan Tambahan Januari',
            'uraian'             => 'Percobaan upload kedua di bulan yang sama.',
            'nama_penandatangan' => 'Drs. H. Pimpinan Biro',
        ]);

        $resDuplicate->assertStatus(422)
            ->assertJson([
                'status'  => 'error',
                'message' => 'Unit kerja ini sudah mengunggah laporan untuk bulan Januari pada periode ini.',
            ]);

        // 3. Simpan laporan untuk bulan lain ("Februari") -> Harus Sukses 201
        $res2 = $this->postJson('/api/laporan-kinerja', [
            'id_periode'         => $this->periode->id,
            'bulan_laporan'      => 'Februari',
            'nama_kegiatan'      => 'Kegiatan Pelayanan Februari',
            'uraian'             => 'Pelaksanaan layanan administrasi bulan Februari.',
            'nama_penandatangan' => 'Drs. H. Pimpinan Biro',
        ]);

        $res2->assertStatus(201)
            ->assertJsonPath('data.bulan_laporan', 'Februari');
    }

    public function test_laporan_kinerja_digital_signature_upload_and_pdf_generation(): void
    {
        Sanctum::actingAs($this->userBiro, ['biro']);

        // Buat string dummy data:image/png;base64 dari signature pad canvas
        $canvasBase64 = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNk+M9QDwADhgGAWjR9awAAAABJRU5ErkJggg==';

        $storeRes = $this->postJson('/api/laporan-kinerja', [
            'id_periode'         => $this->periode->id,
            'bulan_laporan'      => 'Maret',
            'nama_kegiatan'      => 'Digital Signature Verification Test',
            'uraian'             => 'Uji coba upload tanda tangan digital berbasis canvas.',
            'nama_penandatangan' => 'Prof. Dr. Kepala Biro, M.Pd.',
            'ttd_image'          => $canvasBase64,
        ]);

        $storeRes->assertStatus(201);
        $laporanId = $storeRes->json('data.id');
        $laporan   = LaporanKinerja::findOrFail($laporanId);

        $this->assertNotNull($laporan->ttd_image_path);
        Storage::disk('public')->assertExists($laporan->ttd_image_path);

        // Cetak PDF dan periksa status 200 & content-type PDF
        $pdfRes = $this->get("/api/laporan-kinerja/{$laporanId}/pdf");
        $pdfRes->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');
    }
}
