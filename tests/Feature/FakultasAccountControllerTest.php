<?php

namespace Tests\Feature;

use App\Models\Fakultas;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class FakultasAccountControllerTest extends TestCase
{
    private User $admin;
    private Fakultas $fakultas;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('username', 'admin')->firstOrFail();

        // Siapkan fakultas untuk pengetesan akun
        $this->fakultas = Fakultas::firstOrCreate(
            ['nama_fakultas' => 'Fakultas Percobaan Testing'],
            ['singkatan' => 'FPT']
        );

        // Bersihkan user pengujian jika ada
        User::where('id_fakultas', $this->fakultas->id)->delete();
        User::where('username', 'op_testing_fakultas')->delete();
        User::where('username', 'op_fpt_direct')->delete();
    }

    protected function tearDown(): void
    {
        User::where('id_fakultas', $this->fakultas->id)->delete();
        User::where('username', 'op_testing_fakultas')->delete();
        User::where('username', 'op_fpt_direct')->delete();
        $this->fakultas->forceDelete();
        parent::tearDown();
    }

    public function test_hybrid_fakultas_account_creation_validation_and_lifecycle(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        // 1. Buat akun dengan POST /api/akun-fakultas dengan password null -> default 'Unuha2026!'
        $storeRes = $this->postJson('/api/akun-fakultas', [
            'id_fakultas' => $this->fakultas->id,
            'username'    => 'op_testing_fakultas',
            'password'    => null,
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.username', 'op_testing_fakultas')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.id_fakultas', $this->fakultas->id)
            ->assertJsonPath('data.fakultas.nama_fakultas', 'Fakultas Percobaan Testing');

        $userId = $storeRes->json('data.id');

        // 2. Tidak boleh membuat akun kedua untuk fakultas yang sama (1 fakultas = 1 akun)
        $duplicateRes = $this->postJson('/api/akun-fakultas', [
            'id_fakultas' => $this->fakultas->id,
            'username'    => 'op_testing_fakultas_kedua',
        ]);
        $duplicateRes->assertStatus(422)
            ->assertJsonValidationErrors(['id_fakultas']);

        // 3. User baru bisa login menggunakan default password 'Unuha2026!'
        $loginRes = $this->postJson('/api/login', [
            'username' => 'op_testing_fakultas',
            'password' => 'Unuha2026!',
        ]);
        $loginRes->assertStatus(200)
            ->assertJsonPath('data.user.id_fakultas', $this->fakultas->id)
            ->assertJsonPath('data.user.is_active', true);

        // 4. Update data akun (username & password kustom) via PUT /api/akun-fakultas/{id}
        Sanctum::actingAs($this->admin, ['admin']);
        $updateRes = $this->putJson("/api/akun-fakultas/{$userId}", [
            'username' => 'op_testing_fakultas_renamed',
            'password' => 'PasswordBaru2026!',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.username', 'op_testing_fakultas_renamed');

        // 5. Toggle status (Soft Suspend) menjadi is_active = false
        $toggleOff = $this->patchJson("/api/akun-fakultas/{$userId}/toggle-status");
        $toggleOff->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // 6. Coba login saat non-aktif -> 403 Forbidden
        $blockedLogin = $this->postJson('/api/login', [
            'username' => 'op_testing_fakultas_renamed',
            'password' => 'PasswordBaru2026!',
        ]);
        $blockedLogin->assertStatus(403)
            ->assertJsonPath('message', 'Akun dinonaktifkan oleh Admin.');

        // 7. Toggle kembali menjadi aktif
        Sanctum::actingAs($this->admin, ['admin']);
        $toggleOn = $this->patchJson("/api/akun-fakultas/{$userId}/toggle-status");
        $toggleOn->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        // 8. GET /api/akun-fakultas mengembalikan daftar akun beserta relasi fakultas
        $listRes = $this->getJson('/api/akun-fakultas');
        $listRes->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'username', 'role', 'id_fakultas', 'is_active', 'fakultas'],
                ],
            ]);
    }

    public function test_create_akun_via_fakultas_direct_endpoint(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        // POST /api/fakultas/{id}/akun
        $res = $this->postJson("/api/fakultas/{$this->fakultas->id}/akun", [
            'username' => 'op_fpt_direct',
            'password' => 'PasswordDirect123!',
        ]);

        $res->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.username', 'op_fpt_direct')
            ->assertJsonPath('data.id_fakultas', $this->fakultas->id);

        $login = $this->postJson('/api/login', [
            'username' => 'op_fpt_direct',
            'password' => 'PasswordDirect123!',
        ]);
        $login->assertStatus(200)
            ->assertJsonPath('data.user.id_fakultas', $this->fakultas->id);
    }
}
