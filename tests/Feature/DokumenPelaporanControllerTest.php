<?php

namespace Tests\Feature;

use App\Models\DokumenPelaporan;
use App\Models\PeriodeAkademik;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DokumenPelaporanControllerTest extends TestCase
{
    private User $admin;
    private User $userTi;
    private User $userSi;
    private PeriodeAkademik $periode;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin   = User::where('username', 'admin')->firstOrFail();
        $this->periode = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();
        $this->periode->update(['status_pelaporan' => 'Buka']);

        $prodiTi = Prodi::firstOrCreate(['nama_prodi' => 'S1 Informatika']);
        $this->userTi = User::updateOrCreate(
            ['username' => 'prodi_ti'],
            [
                'password'  => 'password123',
                'role'      => 'user',
                'id_prodi'  => $prodiTi->id,
                'is_active' => true,
            ]
        );

        foreach ([1, 2, 3, 4] as $tahap) {
            DokumenPelaporan::firstOrCreate(
                [
                    'id_prodi'      => $prodiTi->id,
                    'id_periode'    => $this->periode->id,
                    'tahap_dokumen' => $tahap,
                ],
                ['status' => 'Belum Unggah']
            );
        }

        $prodiSi = Prodi::firstOrCreate(['nama_prodi' => 'S1 Sistem Informasi']);
        $this->userSi = User::updateOrCreate(
            ['username' => 'prodi_si_test'],
            [
                'password'  => 'password123',
                'role'      => 'user',
                'id_prodi'  => $prodiSi->id,
                'is_active' => true,
            ]
        );
    }

    public function test_upload_validates_pdf_and_stores_in_private_storage_and_hides_file_path(): void
    {
        Sanctum::actingAs($this->userTi, ['user']);

        // 1. Gagal jika file bukan PDF
        $invalidFile = UploadedFile::fake()->create('malware.exe', 100, 'application/x-msdownload');
        $this->postJson('/api/dokumen/upload', [
            'id_periode'    => $this->periode->id,
            'tahap_dokumen' => 1,
            'file'          => $invalidFile,
        ])->assertStatus(422);

        // 2. Gagal jika ukuran > 2048 KB
        $largePdf = UploadedFile::fake()->create('large.pdf', 2500, 'application/pdf');
        $this->postJson('/api/dokumen/upload', [
            'id_periode'    => $this->periode->id,
            'tahap_dokumen' => 1,
            'file'          => $largePdf,
        ])->assertStatus(422);

        // 3. Sukses upload PDF valid (<= 2048 KB)
        $validPdf = UploadedFile::fake()->create('kurikulum.pdf', 512, 'application/pdf');
        $response = $this->postJson('/api/dokumen/upload', [
            'id_periode'    => $this->periode->id,
            'tahap_dokumen' => 1,
            'file'          => $validPdf,
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.status', 'Review')
            ->assertJsonPath('data.catatan_revisi', null)
            ->assertJsonPath('data.has_file', true)
            ->assertJsonMissingPath('data.file_path'); // Pastikan path direktori asli tidak pernah diekspos

        $expectedPath = "dokumen_pelaporan/{$this->periode->id}/{$this->userTi->id_prodi}/tahap_1.pdf";
        Storage::disk('local')->assertExists($expectedPath);
    }

    public function test_validasi_requires_catatan_revisi_when_revisi_and_sanitizes_xss_and_upload_resets_it(): void
    {
        $dokumen = DokumenPelaporan::where('id_prodi', $this->userTi->id_prodi)
            ->where('id_periode', $this->periode->id)
            ->where('tahap_dokumen', 1)
            ->firstOrFail();

        Sanctum::actingAs($this->admin, ['admin']);

        // 1. Jika status = 'Revisi' tanpa catatan_revisi -> 422 Unprocessable Content
        $this->postJson("/api/dokumen/{$dokumen->id}/validasi", [
            'status' => 'Revisi',
        ])->assertStatus(422);

        // 2. Jika status = 'Revisi' dengan tag script XSS -> tersimpan bersih tanpa tag HTML
        $this->postJson("/api/dokumen/{$dokumen->id}/validasi", [
            'status'         => 'Revisi',
            'catatan_revisi' => '<script>alert("xss")</script>Perbaiki lampiran SK Kurikulum.',
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'Revisi')
          ->assertJsonPath('data.catatan_revisi', 'alert("xss")Perbaiki lampiran SK Kurikulum.');

        // 3. User Prodi mengunggah ulang revisi -> file ditimpa, status kembali 'Review', catatan_revisi menjadi null
        Sanctum::actingAs($this->userTi, ['user']);
        $revisedPdf = UploadedFile::fake()->create('kurikulum_revisi.pdf', 600, 'application/pdf');

        $reupload = $this->postJson('/api/dokumen/upload', [
            'id_periode'    => $this->periode->id,
            'tahap_dokumen' => 1,
            'file'          => $revisedPdf,
        ]);

        $reupload->assertStatus(200)
            ->assertJsonPath('data.status', 'Review')
            ->assertJsonPath('data.catatan_revisi', null);

        // 4. Admin memvalidasi menjadi 'Selesai'
        Sanctum::actingAs($this->admin, ['admin']);
        $this->postJson("/api/dokumen/{$dokumen->id}/validasi", [
            'status' => 'Selesai',
        ])->assertStatus(200)
          ->assertJsonPath('data.status', 'Selesai')
          ->assertJsonPath('data.catatan_revisi', null);
    }

    public function test_secure_download_prevents_idor_between_prodi_and_allows_admin_and_owner(): void
    {
        Sanctum::actingAs($this->userTi, ['user']);
        $pdf = UploadedFile::fake()->create('jadwal.pdf', 300, 'application/pdf');

        $upload = $this->postJson('/api/dokumen/upload', [
            'id_periode'    => $this->periode->id,
            'tahap_dokumen' => 2,
            'file'          => $pdf,
        ]);

        $dokumenId = $upload->json('data.id');

        // 1. Pemilik (User Prodi TI) mengunduh -> 200 OK
        $this->get("/api/dokumen/{$dokumenId}/download")
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'application/pdf');

        // 2. User Prodi lain (Prodi SI) mencoba mengunduh dokumen milik Prodi TI (IDOR Attack) -> 403 Forbidden
        Sanctum::actingAs($this->userSi, ['user']);
        $this->getJson("/api/dokumen/{$dokumenId}/download")
            ->assertStatus(403);

        // 3. Admin mengunduh dokumen Prodi TI -> 200 OK
        Sanctum::actingAs($this->admin, ['admin']);
        $this->get("/api/dokumen/{$dokumenId}/download")
            ->assertStatus(200);
    }

    public function test_get_dokumen_filters_by_prodi_for_user_and_returns_all_for_admin(): void
    {
        // Buat 1 dokumen untuk Prodi SI
        DokumenPelaporan::updateOrCreate(
            [
                'id_prodi'      => $this->userSi->id_prodi,
                'id_periode'    => $this->periode->id,
                'tahap_dokumen' => 1,
            ],
            ['status' => 'Belum Unggah']
        );

        // Jika diakses oleh User Prodi TI -> hanya dokumen milik Prodi TI yang muncul
        Sanctum::actingAs($this->userTi, ['user']);
        $userRes = $this->getJson("/api/dokumen?id_periode={$this->periode->id}");
        $userRes->assertStatus(200);

        $prodiIds = collect($userRes->json('data'))->pluck('id_prodi')->unique()->values()->all();
        $this->assertEquals([$this->userTi->id_prodi], $prodiIds);

        // Jika diakses oleh Admin -> menampilkan dokumen dari kedua Prodi
        Sanctum::actingAs($this->admin, ['admin']);
        $adminRes = $this->getJson("/api/dokumen?id_periode={$this->periode->id}");
        $adminRes->assertStatus(200);

        $allProdiIds = collect($adminRes->json('data'))->pluck('id_prodi')->unique()->values()->all();
        $this->assertGreaterThanOrEqual(2, count($allProdiIds));
    }

    public function test_multiple_attachments_upload_delete_idor_protection_and_zip_download(): void
    {
        $dokumen = DokumenPelaporan::where('id_prodi', $this->userTi->id_prodi)
            ->where('id_periode', $this->periode->id)
            ->where('tahap_dokumen', 4)
            ->firstOrFail();

        // Bersihkan lampiran lama jika ada
        $dokumen->lampiran()->delete();

        Sanctum::actingAs($this->userTi, ['user']);

        // 1. Gagal jika ukuran file lampiran > 5120 KB (5MB)
        $tooLargePdf = UploadedFile::fake()->create('ijazah_besar.pdf', 5500, 'application/pdf');
        $this->postJson("/api/dokumen/{$dokumen->id}/lampiran", [
            'jenis_lampiran' => 'Ijazah',
            'file'           => $tooLargePdf,
        ])->assertStatus(422);

        // 2. Unggah asinkron lampiran ke-1 (Ijazah) & ke-2 (Transkrip)
        $ijazahPdf = UploadedFile::fake()->create('Ijazah_Ahmad_2026.pdf', 1200, 'application/pdf');
        $res1 = $this->postJson("/api/dokumen/{$dokumen->id}/lampiran", [
            'jenis_lampiran' => 'Ijazah',
            'file'           => $ijazahPdf,
        ]);

        $res1->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.jenis_lampiran', 'Ijazah')
            ->assertJsonPath('data.nama_file_asli', 'Ijazah_Ahmad_2026.pdf')
            ->assertJsonMissingPath('data.file_path');

        $idLampiran1 = $res1->json('data.id');

        $transkripPdf = UploadedFile::fake()->create('Transkrip_Ahmad_2026.pdf', 800, 'application/pdf');
        $res2 = $this->postJson("/api/dokumen/{$dokumen->id}/lampiran", [
            'jenis_lampiran' => 'Transkrip',
            'file'           => $transkripPdf,
        ]);
        $res2->assertStatus(201);
        $idLampiran2 = $res2->json('data.id');

        // 3. Proteksi IDOR pada DELETE: Operator Prodi lain (Prodi SI) maupun Admin tidak boleh menghapus lampiran Prodi TI
        Sanctum::actingAs($this->userSi, ['user']);
        $this->deleteJson("/api/dokumen/lampiran/{$idLampiran1}")
            ->assertStatus(403);

        Sanctum::actingAs($this->admin, ['admin']);
        $this->deleteJson("/api/dokumen/lampiran/{$idLampiran1}")
            ->assertStatus(403);

        // 4. Admin mengunduh seluruh lampiran sekaligus dalam format ZIP (GET /api/dokumen/{id}/lampiran/zip)
        $zipResponse = $this->get("/api/dokumen/{$dokumen->id}/lampiran/zip");
        $zipResponse->assertStatus(200)
            ->assertHeader('Content-Type', 'application/zip');

        // Hapus file temp zip pada test environment karena BinaryFileResponse deleteFileAfterSend dieksekusi saat terminate
        $tempZipPath = $zipResponse->baseResponse->getFile()->getPathname();
        if (file_exists($tempZipPath)) {
            @unlink($tempZipPath);
        }

        // 5. Operator Prodi TI (pemilik) menghapus lampiran ke-1 & ke-2 -> 200 OK
        Sanctum::actingAs($this->userTi, ['user']);
        $this->deleteJson("/api/dokumen/lampiran/{$idLampiran1}")
            ->assertStatus(200);
        $this->deleteJson("/api/dokumen/lampiran/{$idLampiran2}")
            ->assertStatus(200);

        $this->assertDatabaseMissing('lampiran_dokumen', ['id' => $idLampiran1]);
        $this->assertDatabaseMissing('lampiran_dokumen', ['id' => $idLampiran2]);
    }
}
