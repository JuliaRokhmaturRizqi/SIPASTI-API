<?php

namespace Tests\Feature;

use App\Models\DokumenPelaporan;
use App\Models\PeriodeAkademik;
use App\Models\Prodi;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PeriodeControllerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('username', 'admin')->firstOrFail();
    }

    public function test_index_returns_all_periode_ordered_by_latest(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $response = $this->getJson('/api/periode');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'nama_periode', 'status', 'dokumen_pelaporan_count'],
                ],
            ]);
    }

    public function test_store_and_update_enforce_single_active_rule(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        // Pastikan periode awal (2026/2027 Ganjil) berstatus Aktif
        $periodeAwal = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();
        $periodeAwal->update(['status' => 'Aktif']);

        // Tambah periode baru dengan status 'Aktif'
        $storeResponse = $this->postJson('/api/periode', [
            'nama_periode' => '2026/2027 Genap',
            'status'       => 'Aktif',
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('data.status', 'Aktif');

        $periodeBaruId = $storeResponse->json('data.id');

        // Periode awal harus otomatis berubah menjadi Nonaktif
        $this->assertEquals('Nonaktif', $periodeAwal->fresh()->status);

        // Update kembali periode awal menjadi Aktif via PUT /api/periode/{id}
        $updateResponse = $this->putJson("/api/periode/{$periodeAwal->id}", [
            'nama_periode' => '2026/2027 Ganjil',
            'status'       => 'Aktif',
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.status', 'Aktif');

        // Periode baru harus otomatis berubah menjadi Nonaktif, dan hanya ada 1 periode Aktif di tabel
        $this->assertEquals('Nonaktif', PeriodeAkademik::findOrFail($periodeBaruId)->status);
        $this->assertEquals(1, PeriodeAkademik::where('status', 'Aktif')->count());

        // Cleanup periode baru (belum punya relasi dokumen_pelaporan -> Hard Delete 200 OK)
        $deleteUnused = $this->deleteJson("/api/periode/{$periodeBaruId}");
        $deleteUnused->assertStatus(200)
            ->assertJsonPath('status', 'success');
        $this->assertDatabaseMissing('periode_akademik', ['id' => $periodeBaruId]);
    }

    public function test_destroy_proteksi_hapus_data_archives_periode_with_relations(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $periodeDenganRelasi = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();

        // Pastikan memiliki relasi dokumen_pelaporan
        $this->assertTrue($periodeDenganRelasi->dokumenPelaporan()->exists());

        // Coba hapus periode yang sudah punya dokumen pelaporan -> harus ditolak 400 & status berubah jadi Arsip
        $response = $this->deleteJson("/api/periode/{$periodeDenganRelasi->id}");

        $response->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Periode tidak dapat dihapus karena sudah memiliki data pelaporan. Status diubah menjadi Arsip.')
            ->assertJsonPath('data.status', 'Arsip');

        $this->assertEquals('Arsip', $periodeDenganRelasi->fresh()->status);

        // Kembalikan ke Aktif untuk menjaga state awal database
        $periodeDenganRelasi->update(['status' => 'Aktif']);
    }
}
