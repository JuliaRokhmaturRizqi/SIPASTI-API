<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class ApiAuthAndRbacTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('admin|127.0.0.1');
        RateLimiter::clear('prodi_ti|127.0.0.1');
    }

    public function test_admin_can_login_and_access_admin_routes_but_blocked_from_user_routes(): void
    {
        $loginResponse = $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('status', 'success')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'user' => ['id', 'username', 'role', 'id_prodi', 'prodi'],
                    'role',
                    'access_token',
                    'token_type',
                ],
            ]);

        $token = $loginResponse->json('data.access_token');

        // Admin mengakses route khusus admin -> 200 OK
        $this->withToken($token)
            ->postJson('/api/admin/periode-akademik')
            ->assertStatus(200);

        // Admin mengakses route khusus user prodi -> 403 Forbidden
        $this->withToken($token)
            ->postJson('/api/prodi/dokumen-pelaporan/upload')
            ->assertStatus(403);

        // Logout menghapus current access token
        $this->withToken($token)
            ->postJson('/api/logout')
            ->assertStatus(200);
    }

    public function test_prodi_user_can_login_and_access_prodi_routes_but_blocked_from_admin_routes(): void
    {
        $loginResponse = $this->postJson('/api/login', [
            'username' => 'prodi_ti',
            'password' => 'password123',
        ]);

        $loginResponse->assertStatus(200)
            ->assertJsonPath('data.role', 'user')
            ->assertJsonPath('data.user.prodi.nama_prodi', 'Teknik Informatika');

        $token = $loginResponse->json('data.access_token');

        // User Prodi mengakses route prodi -> 200 OK
        $this->withToken($token)
            ->postJson('/api/prodi/dokumen-pelaporan/upload')
            ->assertStatus(200);

        // User Prodi mengakses route admin -> 403 Forbidden
        $this->withToken($token)
            ->patchJson('/api/admin/dokumen-pelaporan/1/validasi')
            ->assertStatus(403);
    }

    public function test_brute_force_rate_limiter_blocks_after_five_failed_attempts(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'username' => 'admin',
                'password' => 'wrong_password',
            ])->assertStatus(401);
        }

        // Percobaan ke-6 harus diblokir 429 Too Many Requests
        $this->postJson('/api/login', [
            'username' => 'admin',
            'password' => 'wrong_password',
        ])->assertStatus(429);

        RateLimiter::clear('admin|127.0.0.1');
    }
}
