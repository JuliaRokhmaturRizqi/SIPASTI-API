<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fakultas;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class FakultasAccountController extends Controller
{
    /**
     * GET /api/akun-fakultas
     * Mengambil daftar seluruh akun Operator Fakultas (role = 'user') beserta relasi data Fakultas dan prodi-prodi di bawahnya.
     */
    public function index(): JsonResponse
    {
        try {
            $accounts = User::query()
                ->with(['fakultas.prodi:id,id_fakultas,nama_prodi,periode_mulai_aktif'])
                ->where('role', 'user')
                ->whereNotNull('id_fakultas')
                ->orderBy('id_fakultas')
                ->orderBy('id')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar akun operator fakultas berhasil diambil.',
                'data'    => $accounts,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar akun operator fakultas.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/akun-fakultas
     * Pembuatan akun Operator Fakultas dengan pendekatan Hybrid:
     * - id_fakultas: required, exists di fakultas, unique di tabel users (1 fakultas = 1 akun utama).
     * - username: required, unique di tabel users.
     * - password: nullable (jika null, default ke 'Unuha2026!').
     * - role: 'user', is_active: true.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_fakultas' => [
                'required',
                'integer',
                Rule::exists('fakultas', 'id')->whereNull('deleted_at'),
                Rule::unique('users', 'id_fakultas'),
            ],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ], [
            'id_fakultas.unique' => 'Fakultas ini sudah memiliki akun operator.',
            'username.unique'    => 'Username sudah digunakan oleh akun lain.',
        ]);

        $plainPassword = ! empty($validated['password'])
            ? $validated['password']
            : User::DEFAULT_OPERATOR_PASSWORD;

        try {
            $user = DB::transaction(function () use ($validated, $plainPassword) {
                return User::create([
                    'username'    => $validated['username'],
                    'password'    => $plainPassword,
                    'role'        => 'user',
                    'id_fakultas' => $validated['id_fakultas'],
                    'is_active'   => true,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Akun operator fakultas berhasil dibuat.',
                'data'    => $user->load('fakultas.prodi'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat membuat akun operator fakultas.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PUT /api/akun-fakultas/{id}
     * Update akun operator fakultas (username, password, id_fakultas).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::where('role', 'user')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun operator tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'username' => [
                'sometimes',
                'required',
                'string',
                'min:3',
                'max:50',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'id_fakultas' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('fakultas', 'id')->whereNull('deleted_at'),
                Rule::unique('users', 'id_fakultas')->ignore($user->id),
            ],
            'password'       => ['nullable', 'string', 'min:6', 'max:100'],
            'is_active'      => ['sometimes', 'boolean'],
            'reset_password' => ['nullable', 'boolean'],
        ], [
            'id_fakultas.unique' => 'Fakultas ini sudah memiliki akun operator lain.',
            'username.unique'    => 'Username sudah digunakan oleh akun lain.',
        ]);

        try {
            $isPasswordReset = false;

            $user = DB::transaction(function () use ($request, $user, $validated, &$isPasswordReset) {
                $payload = [];

                if (array_key_exists('username', $validated)) {
                    $payload['username'] = $validated['username'];
                }

                if (array_key_exists('id_fakultas', $validated)) {
                    $payload['id_fakultas'] = $validated['id_fakultas'];
                }

                if (! empty($validated['password'])) {
                    $payload['password'] = $validated['password'];
                } elseif (
                    $request->boolean('reset_password') ||
                    ($request->exists('password') && $request->input('password') === '')
                ) {
                    $payload['password'] = User::DEFAULT_OPERATOR_PASSWORD;
                    $isPasswordReset     = true;
                }

                if (array_key_exists('is_active', $validated)) {
                    $payload['is_active'] = (bool) $validated['is_active'];
                }

                $user->update($payload);

                return $user->fresh()->load('fakultas.prodi');
            });

            return response()->json([
                'status'  => 'success',
                'message' => $isPasswordReset
                    ? 'Sandi akun operator fakultas berhasil direset ke default (Unuha2026!).'
                    : 'Akun operator fakultas berhasil diperbarui.',
                'data'    => $user,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui akun operator fakultas.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PATCH /api/akun-fakultas/{id}/toggle-status
     * Toggle status aktif akun (Soft Suspend).
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::where('role', 'user')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun operator tidak ditemukan.',
            ], 404);
        }

        try {
            $user->update([
                'is_active' => ! $user->is_active,
            ]);

            $statusText = $user->is_active ? 'diaktifkan' : 'dinonaktifkan (disuspend)';

            return response()->json([
                'status'  => 'success',
                'message' => "Akun operator {$user->username} berhasil {$statusText}.",
                'data'    => [
                    'id'        => $user->id,
                    'username'  => $user->username,
                    'is_active' => $user->is_active,
                ],
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengubah status aktif akun operator.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/akun-fakultas/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::where('role', 'user')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun operator tidak ditemukan.',
            ], 404);
        }

        try {
            $user->delete();

            return response()->json([
                'status'  => 'success',
                'message' => "Akun operator {$user->username} berhasil dihapus.",
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus akun operator.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
