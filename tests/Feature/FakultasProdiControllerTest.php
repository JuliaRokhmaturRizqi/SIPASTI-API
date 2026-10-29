<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\Prodi;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FakultasProdiControllerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('username', 'admin')->firstOrFail();
    }

    public function test_get_fakultas_returns_unuha_faculties_with_prodi_relations(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $response = $this->getJson('/api/fakultas');

        $response->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'singkatan', 'nama_fakultas', 'prodi_count', 'prodi'],
                ],
            ]);

        $singkatanList = collect($response->json('data'))->pluck('singkatan')->all();
        $this->assertContains('FAI', $singkatanList);
        $this->assertContains('FIP', $singkatanList);
        $this->assertContains('FST', $singkatanList);
        $this->assertContains('Pasca', $singkatanList);
    }

    public function test_crud_prodi_and_soft_deletes_and_create_akun_operator(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $fst = Fakultas::where('singkatan', 'FST')->firstOrFail();

        // Bersihkan jika ada sisa test sebelumnya
        Prodi::withTrashed()->where('nama_prodi', 'S1 Rekayasa Perangkat Lunak')->forceDelete();
        User::where('username', 'op_rpl_unuha')->delete();

        // 1. Create Prodi Baru (Validasi id_fakultas dan periode_mulai_aktif)
        $storeRes = $this->postJson('/api/prodi', [
            'id_fakultas'         => $fst->id,
            'nama_prodi'          => 'S1 Rekayasa Perangkat Lunak',
            'periode_mulai_aktif' => '2026/2027 Ganjil',
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('data.nama_prodi', 'S1 Rekayasa Perangkat Lunak')
            ->assertJsonPath('data.periode_mulai_aktif', '2026/2027 Ganjil')
            ->assertJsonPath('data.fakultas.singkatan', 'FST');

        $prodiId = $storeRes->json('data.id');

        // 2. Update Prodi
        $updateRes = $this->putJson("/api/prodi/{$prodiId}", [
            'id_fakultas'         => $fst->id,
            'nama_prodi'          => 'S1 Rekayasa Perangkat Lunak',
            'periode_mulai_aktif' => '2026/2027 Genap',
        ]);

        $updateRes->assertStatus(200)
            ->assertJsonPath('data.periode_mulai_aktif', '2026/2027 Genap');

        // 3. Generate Akun Operator untuk Prodi tersebut (POST /api/prodi/{id}/akun)
        $akunRes = $this->postJson("/api/prodi/{$prodiId}/akun", [
            'username' => 'op_rpl_unuha',
            'password' => 'secret12345',
        ]);

        $akunRes->assertStatus(201)
            ->assertJsonPath('data.username', 'op_rpl_unuha')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.id_prodi', $prodiId);

        // Pastikan akun operator baru bisa login via POST /api/login
        $loginRes = $this->postJson('/api/login', [
            'username' => 'op_rpl_unuha',
            'password' => 'secret12345',
        ]);
        $loginRes->assertStatus(200)
            ->assertJsonPath('data.user.id_prodi', $prodiId);

        // 4. Soft Delete Prodi
        Sanctum::actingAs($this->admin, ['admin']);
        $deleteRes = $this->deleteJson("/api/prodi/{$prodiId}");
        $deleteRes->assertStatus(200);

        $this->assertSoftDeleted('prodi', ['id' => $prodiId]);

        // Cleanup test records
        User::where('username', 'op_rpl_unuha')->delete();
        Prodi::withTrashed()->where('id', $prodiId)->forceDelete();
    }
}
