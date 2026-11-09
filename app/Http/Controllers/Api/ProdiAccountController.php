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
     * Mengambil daftar seluruh akun Operator (role = 'user') beserta relasi data Fakultas dan Prodinya.
     */
    public function index(): JsonResponse
    {
        try {
            $accounts = User::query()
                ->with(['fakultas.prodi:id,id_fakultas,nama_prodi'])
                ->where('role', 'user')
                ->orderBy('id')
                ->get()
                ->map(function ($u) {
                    $firstProdi = $u->fakultas?->prodi?->first();
                    $u->setAttribute('id_prodi', $firstProdi?->id);
                    $u->setRelation('prodi', $firstProdi);
                    return $u;
                });

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
        // Jika id_prodi belum ada di tabel prodi tetapi nama_prodi dikirim dari daftar 14 Prodi UNUHA,
        // buat atau temukan record Prodi secara otomatis agar tidak gagal validasi exists:prodi,id
        if ($request->filled('nama_prodi')) {
            $namaProdiInput = trim(strip_tags((string) $request->input('nama_prodi')));
            $fakultasName   = trim(strip_tags((string) $request->input('fakultas_nama', 'Fakultas Universitas Nurul Huda')));
            $fakultasCode   = trim(strip_tags((string) $request->input('fakultas_singkatan', 'UNUHA')));

            $fakultasRecord = \App\Models\Fakultas::firstOrCreate(
                ['nama_fakultas' => $fakultasName],
                ['singkatan' => $fakultasCode]
            );

            $prodiRecord = \App\Models\Prodi::firstOrCreate(
                ['nama_prodi' => $namaProdiInput],
                [
                    'id_fakultas'         => $fakultasRecord->id,
                    'periode_mulai_aktif' => '2026/2027 Ganjil',
                ]
            );

            $request->merge(['id_prodi' => $prodiRecord->id]);
        }

        $prodi = \App\Models\Prodi::with('fakultas:id,singkatan,nama_fakultas')->find($request->input('id_prodi'));

        $validated = $request->validate([
            'id_prodi' => [
                'required',
                'integer',
                Rule::exists('prodi', 'id')->whereNull('deleted_at'),
            ],
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ], [
            'username.unique' => 'Username sudah digunakan oleh akun lain.',
        ]);

        if ($prodi && User::where('id_fakultas', $prodi->id_fakultas)->exists()) {
            return response()->json([
                'message' => 'Program studi ini sudah memiliki akun operator.',
                'errors'  => [
                    'id_prodi' => ['Program studi ini sudah memiliki akun operator.'],
                ],
            ], 422);
        }

        $plainPassword = ! empty($validated['password'])
            ? $validated['password']
            : User::DEFAULT_PRODI_PASSWORD;

        try {
            $user = DB::transaction(function () use ($validated, $plainPassword, $prodi) {
                return User::create([
                    'id_fakultas' => $prodi?->id_fakultas,
                    'username'    => $validated['username'],
                    'password'    => $plainPassword,
                    'role'        => 'user',
                    'is_active'   => true,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Akun operator program studi berhasil dibuat.',
                'data'    => [
                    'id'        => $user->id,
                    'username'  => $user->username,
                    'role'      => $user->role,
                    'is_active' => $user->is_active,
                    'id_prodi'  => $prodi?->id,
                    'prodi'     => $prodi ? [
                        'id'         => $prodi->id,
                        'nama_prodi' => $prodi->nama_prodi,
                        'fakultas'   => $prodi->fakultas,
                    ] : null,
                ],
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
            'password'       => ['nullable', 'string', 'min:6', 'max:100'],
            'reset_password' => ['nullable', 'boolean'],
        ]);

        try {
            $isPasswordReset = false;

            DB::transaction(function () use ($request, $user, $validated, &$isPasswordReset) {
                $payload = [
                    'username' => $validated['username'],
                ];

                if (! empty($validated['password'])) {
                    $payload['password'] = bcrypt($validated['password']);
                } elseif (
                    $request->boolean('reset_password') ||
                    ($request->exists('password') && $request->input('password') === '')
                ) {
                    $payload['password'] = bcrypt(User::DEFAULT_PRODI_PASSWORD);
                    $isPasswordReset     = true;
                }

                $user->update($payload);
            });

            $refreshed = $user->fresh(['fakultas.prodi:id,id_fakultas,nama_prodi']);
            $firstProdi = $refreshed->fakultas?->prodi?->first();
            $refreshed->id_prodi = $firstProdi?->id;
            $refreshed->prodi = $firstProdi ? (object) [
                'id'         => $firstProdi->id,
                'nama_prodi' => $firstProdi->nama_prodi,
                'fakultas'   => $refreshed->fakultas,
            ] : null;

            return response()->json([
                'status'  => 'success',
                'message' => $isPasswordReset
                    ? 'Sandi akun operator berhasil direset ke sandi default (Unuha2026!).'
                    : 'Akun operator program studi berhasil diperbarui.',
                'data'    => $refreshed,
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

                $refreshed = $user->fresh(['fakultas.prodi:id,id_fakultas,nama_prodi']);
                $firstProdi = $refreshed->fakultas?->prodi?->first();
                $refreshed->id_prodi = $firstProdi?->id;
                $refreshed->prodi = $firstProdi ? (object) [
                    'id'         => $firstProdi->id,
                    'nama_prodi' => $firstProdi->nama_prodi,
                    'fakultas'   => $refreshed->fakultas,
                ] : null;

                return $refreshed;
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

    /**
     * DELETE /api/akun-prodi/{id}
     * Menghapus akun Operator Prodi.
     */
    public function destroy(int $id): JsonResponse
    {
        $user = User::where('role', 'user')->find($id);

        if (! $user) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data akun operator program studi tidak ditemukan.',
            ], 404);
        }

        try {
            DB::transaction(function () use ($user) {
                // Cabut semua token sesi Sanctum
                $user->tokens()->delete();
                // Hapus akun dari database
                $user->delete();
            });

            return response()->json([
                'status'  => 'success',
                'message' => "Akun operator \"{$user->username}\" berhasil dihapus dari database.",
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus akun operator program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
