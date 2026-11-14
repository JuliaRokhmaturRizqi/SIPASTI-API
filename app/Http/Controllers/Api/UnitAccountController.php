<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UnitKerja;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class UnitAccountController extends Controller
{
    /**
     * GET /api/akun-unit
     * Mengambil daftar seluruh akun Operator Unit Kerja beserta relasi data Unit Kerja dan Kategori.
     */
    public function index(): JsonResponse
    {
        try {
            $accounts = User::query()
                ->with(['unitKerja.kategori:id,nama_kategori'])
                ->whereNotNull('id_unit_kerja')
                ->orderBy('id_unit_kerja')
                ->orderBy('id')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar akun operator unit kerja berhasil diambil.',
                'data'    => $accounts,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar akun operator unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/akun-unit
     * Pembuatan akun Operator Unit Kerja:
     * - id_unit_kerja: required, exists di unit_kerja, unique di tabel users (1 unit = 1 akun).
     * - username: required, unique di tabel users.
     * - password: nullable (default 'Unuha2026!').
     * - role: otomatis disesuaikan dengan kategori unit (biro, pusat, lab, dll).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_unit_kerja' => [
                'required',
                'integer',
                Rule::exists('unit_kerja', 'id'),
                Rule::unique('users', 'id_unit_kerja'),
            ],
            'username'  => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password'  => ['nullable', 'string', 'min:6', 'max:100'],
            'is_active' => ['nullable', 'boolean'],
        ], [
            'id_unit_kerja.required' => 'Unit kerja wajib dipilih.',
            'id_unit_kerja.unique'   => 'Unit kerja ini sudah memiliki akun operator.',
            'username.required'      => 'Username wajib diisi.',
            'username.unique'        => 'Username sudah digunakan oleh akun lain.',
        ]);

        $unit = UnitKerja::find($validated['id_unit_kerja']);
        $rawKategori = strtolower(trim($unit->kategori_unit ?? 'biro'));
        $role = in_array($rawKategori, ['biro', 'pusat', 'lab'], true) ? $rawKategori : 'pusat';

        $plainPassword = ! empty($validated['password'])
            ? $validated['password']
            : User::DEFAULT_OPERATOR_PASSWORD;

        try {
            $user = DB::transaction(function () use ($validated, $plainPassword, $role) {
                return User::create([
                    'username'      => $validated['username'],
                    'password'      => $plainPassword,
                    'role'          => $role,
                    'id_unit_kerja' => $validated['id_unit_kerja'],
                    'is_active'     => $validated['is_active'] ?? true,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Akun operator unit kerja berhasil dibuat.',
                'data'    => $user->load('unitKerja.kategori'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat membuat akun operator unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PUT /api/akun-unit/{id}
     * Update akun operator unit kerja.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::whereNotNull('id_unit_kerja')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun operator unit kerja tidak ditemukan.',
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
            'id_unit_kerja' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists('unit_kerja', 'id'),
                Rule::unique('users', 'id_unit_kerja')->ignore($user->id),
            ],
            'password'       => ['nullable', 'string', 'min:6', 'max:100'],
            'is_active'      => ['sometimes', 'boolean'],
            'reset_password' => ['nullable', 'boolean'],
        ], [
            'id_unit_kerja.unique' => 'Unit kerja ini sudah digunakan oleh akun operator lain.',
            'username.unique'      => 'Username sudah digunakan oleh akun lain.',
        ]);

        try {
            $isPasswordReset = false;

            $user = DB::transaction(function () use ($request, $user, $validated, &$isPasswordReset) {
                $payload = [];

                if (array_key_exists('username', $validated)) {
                    $payload['username'] = $validated['username'];
                }

                if (array_key_exists('id_unit_kerja', $validated)) {
                    $payload['id_unit_kerja'] = $validated['id_unit_kerja'];
                    $unit = UnitKerja::find($validated['id_unit_kerja']);
                    if ($unit) {
                        $rawKategori = strtolower(trim($unit->kategori_unit ?? 'biro'));
                        $payload['role'] = in_array($rawKategori, ['biro', 'pusat', 'lab'], true) ? $rawKategori : 'pusat';
                    }
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

                return $user->fresh()->load('unitKerja.kategori');
            });

            return response()->json([
                'status'  => 'success',
                'message' => $isPasswordReset
                    ? 'Sandi akun operator unit kerja berhasil direset ke default (Unuha2026!).'
                    : 'Akun operator unit kerja berhasil diperbarui.',
                'data'    => $user,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui akun operator unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PATCH /api/akun-unit/{id}/toggle-status
     * Toggle status aktif akun (Soft Suspend).
     */
    public function toggleStatus(int $id): JsonResponse
    {
        $user = User::whereNotNull('id_unit_kerja')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun operator unit kerja tidak ditemukan.',
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
                'message' => 'Gagal mengubah status aktif akun operator unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/akun-unit/{id}
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::whereNotNull('id_unit_kerja')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun operator unit kerja tidak ditemukan.',
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
                'message' => 'Gagal menghapus akun operator unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
