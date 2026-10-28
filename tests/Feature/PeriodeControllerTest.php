<?php

namespace Tests\Feature;

use App\Models\PeriodeAkademik;
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
                    '*' => ['id', 'nama_periode', 'status_pelaporan', 'is_default', 'dokumen_pelaporan_count'],
                ],
            ]);
    }

    public function test_multi_open_allows_multiple_open_periods_and_single_default_enforces_unique_default(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        // Pastikan periode awal (2026/2027 Ganjil) berstatus Buka dan is_default = true
        $periodeAwal = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();
        $periodeAwal->update([
            'status_pelaporan' => 'Buka',
            'is_default'       => true,
        ]);

        // Tambah periode baru dengan status_pelaporan = 'Buka' dan is_default = true
        $storeResponse = $this->postJson('/api/periode', [
            'nama_periode'     => '2026/2027 Genap',
            'status_pelaporan' => 'Buka',
            'is_default'       => true,
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('data.status_pelaporan', 'Buka')
            ->assertJsonPath('data.is_default', true);

        $periodeBaruId = $storeResponse->json('data.id');

        // Multi-Open: Periode awal TETAP 'Buka' (tidak saling menimpa)
        $this->assertEquals('Buka', $periodeAwal->fresh()->status_pelaporan);

        // Single-Default: Periode awal otomatis berubah menjadi is_default = false
        $this->assertFalse($periodeAwal->fresh()->is_default);
        $this->assertEquals(1, PeriodeAkademik::where('is_default', true)->count());

        // Update kembali periode awal menjadi is_default = true via PUT /api/periode/{id}
        $updateResponse = $this->putJson("/api/periode/{$periodeAwal->id}", [
            'nama_periode'     => '2026/2027 Ganjil',
            'status_pelaporan' => 'Buka',
            'is_default'       => true,
        ]);

        $updateResponse->assertStatus(200)
            ->assertJsonPath('data.status_pelaporan', 'Buka')
            ->assertJsonPath('data.is_default', true);

        // Periode baru harus otomatis berubah menjadi is_default = false, tapi status_pelaporan tetap 'Buka'
        $periodeBaruFresh = PeriodeAkademik::findOrFail($periodeBaruId);
        $this->assertFalse($periodeBaruFresh->is_default);
        $this->assertEquals('Buka', $periodeBaruFresh->status_pelaporan);
        $this->assertEquals(1, PeriodeAkademik::where('is_default', true)->count());

        // Cleanup periode baru (belum punya relasi dokumen_pelaporan -> Hard Delete 200 OK)
        $deleteUnused = $this->deleteJson("/api/periode/{$periodeBaruId}");
        $deleteUnused->assertStatus(200)
            ->assertJsonPath('status', 'success');
        $this->assertDatabaseMissing('periode_akademik', ['id' => $periodeBaruId]);
    }

    public function test_destroy_closes_and_undefaults_periode_when_dokumen_relations_exist(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $periodeDenganRelasi = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();
        $periodeDenganRelasi->update([
            'status_pelaporan' => 'Buka',
            'is_default'       => true,
        ]);

        // Pastikan memiliki relasi dokumen_pelaporan
        $this->assertTrue($periodeDenganRelasi->dokumenPelaporan()->exists());

        // Coba hapus periode yang sudah punya dokumen pelaporan -> harus ditolak 400, status_pelaporan = 'Tutup', is_default = false
        $response = $this->deleteJson("/api/periode/{$periodeDenganRelasi->id}");

        $response->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Periode tidak dapat dihapus karena menampung dokumen Prodi. Status diubah menjadi Tutup.')
            ->assertJsonPath('data.status_pelaporan', 'Tutup')
            ->assertJsonPath('data.is_default', false);

        $this->assertEquals('Tutup', $periodeDenganRelasi->fresh()->status_pelaporan);
        $this->assertFalse($periodeDenganRelasi->fresh()->is_default);

        // Kembalikan ke state awal
        $periodeDenganRelasi->update([
            'status_pelaporan' => 'Buka',
            'is_default'       => true,
        ]);
    }
}
