<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ProdiAccountController extends Controller
{
    /**
     * GET /api/akun-prodi
     * Mengambil daftar seluruh akun Operator Prodi (role = 'user') beserta relasi data Prodi dan Fakultasnya.
     */
    public function index(): JsonResponse
    {
        try {
            $accounts = User::query()
                ->with(['prodi' => function ($query) {
                    $query->with('fakultas:id,singkatan,nama_fakultas');
                }])
                ->where('role', 'user')
                ->orderBy('id_prodi')
                ->orderBy('id')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar akun operator program studi berhasil diambil.',
                'data'    => $accounts,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar akun operator program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/akun-prodi
     * Pembuatan akun Operator Prodi dengan pendekatan Hybrid:
     * - id_prodi: required, exists di prodi, unique di tabel users (1 prodi = 1 akun).
     * - username: required, unique di tabel users.
     * - password: nullable. Jika kosong/null -> otomatis menggunakan bcrypt('Unuha2026!'). Jika diisi -> bcrypt input tersebut.
     * - role: otomatis 'user', is_active: true.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_prodi' => [
                'required',
                'integer',
                Rule::exists('prodi', 'id')->whereNull('deleted_at'),
                Rule::unique('users', 'id_prodi'),
            ],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ], [
            'id_prodi.unique' => 'Program studi ini sudah memiliki akun operator.',
            'username.unique' => 'Username sudah digunakan oleh akun lain.',
        ]);

        $plainPassword = ! empty($validated['password'])
            ? $validated['password']
            : User::DEFAULT_PRODI_PASSWORD;

        try {
            $user = DB::transaction(function () use ($validated, $plainPassword) {
                return User::create([
                    'id_prodi'  => $validated['id_prodi'],
                    'username'  => $validated['username'],
                    'password'  => bcrypt($plainPassword),
                    'role'      => 'user',
                    'is_active' => true,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Akun operator program studi berhasil dibuat.',
                'data'    => $user->load('prodi.fakultas:id,singkatan,nama_fakultas'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat membuat akun operator program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PUT /api/akun-prodi/{id}
     * Mengubah username (unique kecuali id sendiri) dan password (opsional, hanya di-hash & di-update jika diisi).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::where('role', 'user')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data akun operator program studi tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'username' => [
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ]);

        try {
            DB::transaction(function () use ($user, $validated) {
                $payload = [
                    'username' => $validated['username'],
                ];

                if (! empty($validated['password'])) {
                    $payload['password'] = bcrypt($validated['password']);
                }

                $user->update($payload);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Akun operator program studi berhasil diperbarui.',
                'data'    => $user->fresh(['prodi.fakultas:id,singkatan,nama_fakultas']),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui akun operator program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PATCH /api/akun-prodi/{id}/toggle-status
     * Mengubah nilai is_active dari true ke false atau sebaliknya (Soft Suspend).
     * Jika dinonaktifkan, seluruh token aktif milik akun tersebut juga dicabut (revoke).
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::where('role', 'user')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data akun operator program studi tidak ditemukan.',
            ], 404);
        }

        try {
            $updatedUser = DB::transaction(function () use ($user) {
                $newStatus = ! $user->is_active;

                $user->update([
                    'is_active' => $newStatus,
                ]);

                // Jika akun dinonaktifkan (Soft Suspend), cabut semua token Sanctum yang sedang aktif
                if (! $newStatus) {
                    $user->tokens()->delete();
                }

                return $user->fresh(['prodi.fakultas:id,singkatan,nama_fakultas']);
            });

            $statusText = $updatedUser->is_active ? 'diaktifkan' : 'dinonaktifkan';

            return response()->json([
                'status'  => 'success',
                'message' => "Akun operator program studi berhasil {$statusText}.",
                'data'    => $updatedUser,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengubah status akun operator program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
