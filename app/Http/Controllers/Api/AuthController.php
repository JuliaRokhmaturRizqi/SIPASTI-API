<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthController extends Controller
{
    /**
     * POST /api/login
     */
    public function login(Request $request): JsonResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string', 'max:50'],
            'password' => ['required', 'string'],
        ]);

        // Throttle Key unik berdasarkan kombinasi username + IP Address
        $throttleKey = Str::transliterate(Str::lower($credentials['username']) . '|' . $request->ip());

        // Cek apakah sudah melewati batas 5 kali percobaan gagal
        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return response()->json([
                'status'      => 'error',
                'message'     => "Terlalu banyak percobaan login. Silakan coba lagi dalam {$seconds} detik.",
                'retry_after' => $seconds,
            ], 429);
        }

        // Cari user via Eloquent (aman dari SQL Injection)
        $user = User::with('prodi:id,id_fakultas,nama_prodi,periode_mulai_aktif')
            ->where('username', $credentials['username'])
            ->first();

        // Validasi keberadaan user dan kecocokan hash password
        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            // Catat kegagalan dengan waktu decay 60 detik (1 menit)
            RateLimiter::hit($throttleKey, 60);

            return response()->json([
                'status'  => 'error',
                'message' => 'Kredensial yang Anda masukkan tidak valid.',
            ], 401);
        }

        // Cek status aktif akun (Soft Suspend Check) -> Tolak 403 jika is_active == false
        if (! $user->is_active) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun dinonaktifkan oleh Admin.',
            ], 403);
        }

        // Bersihkan catatan RateLimiter jika login berhasil
        RateLimiter::clear($throttleKey);

        // Buat Sanctum Personal Access Token dengan abilities sesuai role
        $token = $user->createToken('auth_token', [$user->role])->plainTextToken;

        return response()->json([
            'status'  => 'success',
            'message' => 'Login berhasil.',
            'data'    => [
                'user' => [
                    'id'        => $user->id,
                    'username'  => $user->username,
                    'role'      => $user->role,
                    'id_prodi'  => $user->id_prodi,
                    'is_active' => $user->is_active,
                    'prodi'     => $user->prodi,
                ],
                'role'         => $user->role,
                'access_token' => $token,
                'token_type'   => 'Bearer',
            ],
        ], 200);
    }

    /**
     * POST /api/logout
     */
    public function logout(Request $request): JsonResponse
    {
        // Hanya mencabut (revoke) token yang sedang dipakai pada sesi ini
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'status'  => 'success',
            'message' => 'Logout berhasil. Token telah dihapus.',
        ], 200);
    }
}
