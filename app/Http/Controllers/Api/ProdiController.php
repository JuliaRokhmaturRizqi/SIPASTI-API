<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class ProdiController extends Controller
{
    /**
     * GET /api/prodi
     * Mengambil daftar Prodi beserta relasi Fakultas dan akun Operator (Eager Loading bebas N+1).
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_fakultas' => ['nullable', 'integer', 'exists:fakultas,id'],
        ]);

        try {
            $query = Prodi::query()
                ->with([
                    'fakultas:id,singkatan,nama_fakultas',
                    'users:id,username,role,id_prodi,created_at',
                ])
                ->withCount(['users', 'dokumenPelaporan']);

            if (! empty($validated['id_fakultas'])) {
                $query->where('id_fakultas', (int) $validated['id_fakultas']);
            }

            $prodi = $query
                ->orderBy('id_fakultas')
                ->orderBy('nama_prodi')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar program studi berhasil diambil.',
                'data'    => $prodi,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/prodi/{id}
     * Mengambil detail satu Prodi beserta Fakultas dan daftar akun Operator-nya.
     */
    public function show(int $id): JsonResponse
    {
        $prodi = Prodi::with([
            'fakultas:id,singkatan,nama_fakultas',
            'users:id,username,role,id_prodi,created_at',
        ])->withCount(['users', 'dokumenPelaporan'])->find($id);

        if (! $prodi) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data program studi tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail program studi berhasil diambil.',
            'data'    => $prodi,
        ], 200);
    }

    /**
     * POST /api/prodi
     * Menambahkan Prodi baru (Wajib memvalidasi id_fakultas dan periode_mulai_aktif).
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_fakultas'         => ['required', 'integer', Rule::exists('fakultas', 'id')->whereNull('deleted_at')],
            'nama_prodi'          => ['required', 'string', 'max:150', Rule::unique('prodi', 'nama_prodi')->whereNull('deleted_at')],
            'periode_mulai_aktif' => ['present', 'nullable', 'string', 'max:50'],
        ]);

        try {
            $prodi = Prodi::create([
                'id_fakultas'         => $validated['id_fakultas'],
                'nama_prodi'          => $validated['nama_prodi'],
                'periode_mulai_aktif' => $validated['periode_mulai_aktif'] ?? null,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Program studi berhasil ditambahkan.',
                'data'    => $prodi->load('fakultas:id,singkatan,nama_fakultas'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PUT /api/prodi/{id}
     * Memperbarui data Prodi (Wajib memvalidasi id_fakultas dan periode_mulai_aktif).
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $prodi = Prodi::find($id);

        if (! $prodi) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data program studi tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'id_fakultas' => ['required', 'integer', Rule::exists('fakultas', 'id')->whereNull('deleted_at')],
            'nama_prodi'  => [
                'required',
                'string',
                'max:150',
                Rule::unique('prodi', 'nama_prodi')->ignore($prodi->id)->whereNull('deleted_at'),
            ],
            'periode_mulai_aktif' => ['present', 'nullable', 'string', 'max:50'],
        ]);

        try {
            $prodi->update([
                'id_fakultas'         => $validated['id_fakultas'],
                'nama_prodi'          => $validated['nama_prodi'],
                'periode_mulai_aktif' => $validated['periode_mulai_aktif'] ?? null,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Program studi berhasil diperbarui.',
                'data'    => $prodi->fresh(['fakultas:id,singkatan,nama_fakultas']),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/prodi/{id}
     * Menghapus Prodi menggunakan SoftDeletes agar riwayat pelaporan lama tetap aman.
     */
    public function destroy(int $id): JsonResponse
    {
        $prodi = Prodi::find($id);

        if (! $prodi) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data program studi tidak ditemukan.',
            ], 404);
        }

        try {
            $prodi->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Program studi berhasil dihapus (Soft Delete). Data historis tetap tersimpan.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menghapus program studi.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/prodi/{id}/akun
     * Men-generate User (Operator) baru khusus untuk Prodi tersebut (Pemisahan Profil Prodi & Kredensial).
     */
    public function createAkun(Request $request, int $id): JsonResponse
    {
        $prodi = Prodi::with('fakultas:id,singkatan,nama_fakultas')->find($id);

        if (! $prodi) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data program studi tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['required', 'string', 'min:8', 'max:100'],
        ]);

        try {
            $user = DB::transaction(function () use ($prodi, $validated) {
                return User::create([
                    'username' => $validated['username'],
                    'password' => $validated['password'], // Otomatis di-hash oleh cast 'hashed' di Model User
                    'role'     => 'user',
                    'id_prodi' => $prodi->id,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => "Akun operator untuk program studi {$prodi->nama_prodi} berhasil dibuat.",
                'data'    => [
                    'id'       => $user->id,
                    'username' => $user->username,
                    'role'     => $user->role,
                    'id_prodi' => $user->id_prodi,
                    'prodi'    => $prodi,
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
}
