<?php

namespace Tests\Feature;

use App\Models\Prodi;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProdiAccountControllerTest extends TestCase
{
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::where('username', 'admin')->firstOrFail();
    }

    public function test_hybrid_account_creation_and_one_account_per_prodi_validation(): void
    {
        Sanctum::actingAs($this->admin, ['admin']);

        $prodiPai = Prodi::where('nama_prodi', 'S1 Pendidikan Agama Islam')->firstOrFail();

        // Bersihkan jika ada akun sebelumnya pada prodi ini
        User::where('id_prodi', $prodiPai->id)->delete();
        User::where('username', 'op_pai_unuha')->delete();

        // 1. Buat akun dengan password kosong/null -> harus otomatis memakai default 'Unuha2026!'
        $storeRes = $this->postJson('/api/akun-prodi', [
            'id_prodi' => $prodiPai->id,
            'username' => 'op_pai_unuha',
            'password' => null,
        ]);

        $storeRes->assertStatus(201)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.username', 'op_pai_unuha')
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.id_prodi', $prodiPai->id)
            ->assertJsonPath('data.prodi.nama_prodi', 'S1 Pendidikan Agama Islam');

        $accountId = $storeRes->json('data.id');

        // 2. Pastikan tidak bisa membuat akun ke-2 untuk Prodi yang sama (1 prodi = 1 akun)
        $duplicateProdiRes = $this->postJson('/api/akun-prodi', [
            'id_prodi' => $prodiPai->id,
            'username' => 'op_pai_kedua',
        ]);
        $duplicateProdiRes->assertStatus(422)
            ->assertJsonValidationErrors(['id_prodi']);

        // 3. Verifikasi login memakai password default 'Unuha2026!'
        $loginDefault = $this->postJson('/api/login', [
            'username' => 'op_pai_unuha',
            'password' => 'Unuha2026!',
        ]);
        $loginDefault->assertStatus(200)
            ->assertJsonPath('data.user.is_active', true);

        // 4. Update username dan kustomisasi password via PUT /api/akun-prodi/{id}
        Sanctum::actingAs($this->admin, ['admin']);
        $updateRes = $this->putJson("/api/akun-prodi/{$accountId}", [
            'username' => 'operator_pai',
            'password' => 'CustomPass2026!',
        ]);
        $updateRes->assertStatus(200)
            ->assertJsonPath('data.username', 'operator_pai');

        // 5. Toggle status (Soft Suspend) menjadi is_active = false via PATCH /api/akun-prodi/{id}/toggle-status
        $toggleOff = $this->patchJson("/api/akun-prodi/{$accountId}/toggle-status");
        $toggleOff->assertStatus(200)
            ->assertJsonPath('data.is_active', false);

        // 6. Coba login saat is_active == false -> harus ditolak dengan 403 Forbidden (Akun dinonaktifkan oleh Admin)
        $blockedLogin = $this->postJson('/api/login', [
            'username' => 'operator_pai',
            'password' => 'CustomPass2026!',
        ]);
        $blockedLogin->assertStatus(403)
            ->assertJsonPath('message', 'Akun dinonaktifkan oleh Admin.');

        // 7. Toggle status kembali menjadi is_active = true
        Sanctum::actingAs($this->admin, ['admin']);
        $toggleOn = $this->patchJson("/api/akun-prodi/{$accountId}/toggle-status");
        $toggleOn->assertStatus(200)
            ->assertJsonPath('data.is_active', true);

        // 8. GET /api/akun-prodi mengembalikan daftar akun prodi beserta relasi prodi
        $listRes = $this->getJson('/api/akun-prodi');
        $listRes->assertStatus(200)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    '*' => ['id', 'username', 'role', 'id_prodi', 'is_active', 'prodi'],
                ],
            ]);

        // Cleanup
        User::where('id', $accountId)->delete();
    }
}
