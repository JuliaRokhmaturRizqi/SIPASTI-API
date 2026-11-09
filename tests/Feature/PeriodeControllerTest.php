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
        PeriodeAkademik::where('nama_periode', '2026/2027 Genap')->delete();
        PeriodeAkademik::where('nama_periode', 'Periode Ilegal Oleh User')->delete();
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
                    '*' => ['id', 'nama_periode', 'status_aktif', 'status_pelaporan', 'is_default', 'dokumen_pelaporan_count'],
                ],
            ]);
    }

    public function test_multi_open_allows_multiple_periods_to_be_active_simultaneously_without_overwriting(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        // Periode 1: 2026/2027 Ganjil (Aktif)
        $periode1 = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();
        $periode1->update([
            'status_aktif'     => true,
            'status_pelaporan' => 'Buka',
            'is_default'       => true,
        ]);

        // Periode 2: Tambah periode baru dengan status_aktif = true (Aktif)
        $storeResponse = $this->postJson('/api/periode', [
            'nama_periode' => '2026/2027 Genap',
            'status_aktif' => true,
            'is_default'   => false,
        ]);

        $storeResponse->assertStatus(201)
            ->assertJsonPath('data.status_aktif', true)
            ->assertJsonPath('data.status_pelaporan', 'Buka');

        $periode2Id = $storeResponse->json('data.id');

        // Pastikan Periode 1 TETAP AKTIF (status_aktif = true & status_pelaporan = 'Buka')
        $this->assertTrue($periode1->fresh()->status_aktif);
        $this->assertEquals('Buka', $periode1->fresh()->status_pelaporan);

        // Tambah Periode 3: 2027/2028 Ganjil dengan status_aktif = true
        $storeResponse3 = $this->postJson('/api/periode', [
            'nama_periode' => '2027/2028 Ganjil',
            'status_aktif' => true,
            'is_default'   => false,
        ]);
        $storeResponse3->assertStatus(201);
        $periode3Id = $storeResponse3->json('data.id');

        // KETIGA PERIODE HARUS SAMA-SAMA AKTIF SEKALIGUS (Independent Multi-Period Open)
        $this->assertTrue(PeriodeAkademik::findOrFail($periode1->id)->status_aktif);
        $this->assertTrue(PeriodeAkademik::findOrFail($periode2Id)->status_aktif);
        $this->assertTrue(PeriodeAkademik::findOrFail($periode3Id)->status_aktif);

        $activeCount = PeriodeAkademik::where('status_aktif', true)->count();
        $this->assertGreaterThanOrEqual(3, $activeCount);

        // Test toggle-active pada Periode 3 -> dinonaktifkan (false)
        $toggleRes = $this->patchJson("/api/periode/{$periode3Id}/toggle-active");
        $toggleRes->assertStatus(200)
            ->assertJsonPath('data.status_aktif', false)
            ->assertJsonPath('data.status_pelaporan', 'Tutup');

        // Periode 1 dan 2 TETAP AKTIF tanpa terpengaruh!
        $this->assertTrue(PeriodeAkademik::findOrFail($periode1->id)->status_aktif);
        $this->assertTrue(PeriodeAkademik::findOrFail($periode2Id)->status_aktif);

        // Toggle kembali Periode 3 -> diaktifkan lagi (true)
        $toggleOnRes = $this->patchJson("/api/periode/{$periode3Id}/toggle-active");
        $toggleOnRes->assertStatus(200)
            ->assertJsonPath('data.status_aktif', true);

        // Cleanup periode test
        $this->deleteJson("/api/periode/{$periode2Id}")->assertStatus(200);
        $this->deleteJson("/api/periode/{$periode3Id}")->assertStatus(200);
    }

    public function test_destroy_closes_and_undefaults_periode_when_dokumen_relations_exist(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $periodeDenganRelasi = PeriodeAkademik::where('nama_periode', '2026/2027 Ganjil')->firstOrFail();
        $periodeDenganRelasi->update([
            'status_aktif'     => true,
            'status_pelaporan' => 'Buka',
            'is_default'       => true,
        ]);

        // Pastikan memiliki relasi dokumen_pelaporan
        $this->assertTrue($periodeDenganRelasi->dokumenPelaporan()->exists());

        // Coba hapus periode yang sudah punya dokumen pelaporan -> harus ditolak 400, status_aktif = false, status_pelaporan = 'Tutup'
        $response = $this->deleteJson("/api/periode/{$periodeDenganRelasi->id}");

        $response->assertStatus(400)
            ->assertJsonPath('status', 'error')
            ->assertJsonPath('message', 'Periode tidak dapat dihapus karena menampung dokumen Prodi. Status diubah menjadi Tutup.')
            ->assertJsonPath('data.status_aktif', false)
            ->assertJsonPath('data.status_pelaporan', 'Tutup')
            ->assertJsonPath('data.is_default', false);

        $this->assertFalse($periodeDenganRelasi->fresh()->status_aktif);
        $this->assertEquals('Tutup', $periodeDenganRelasi->fresh()->status_pelaporan);
        $this->assertFalse($periodeDenganRelasi->fresh()->is_default);

        // Kembalikan ke state awal aktif
        $periodeDenganRelasi->update([
            'status_aktif'     => true,
            'status_pelaporan' => 'Buka',
            'is_default'       => true,
        ]);
    }

    public function test_operator_prodi_role_user_dapat_mengakses_daftar_periode_tetapi_tidak_bisa_mengubah_data(): void
    {
        $userProdi = User::where('role', 'user')->first() ?? User::factory()->create([
            'role'      => 'user',
            'is_active' => true,
        ]);

        Sanctum::actingAs($userProdi);

        // Operator Prodi dapat melihat daftar periode (status 200 OK, bukan 403)
        $response = $this->getJson('/api/periode');
        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure(['data' => [['id', 'nama_periode', 'status_aktif', 'status_pelaporan']]]);

        // Operator Prodi TIDAK boleh mengubah/menambah periode (harus 403 Forbidden)
        $storeResponse = $this->postJson('/api/periode', [
            'nama_periode' => 'Periode Ilegal Oleh User',
            'status_aktif' => true,
        ]);
        $storeResponse->assertStatus(403);
    }
}
