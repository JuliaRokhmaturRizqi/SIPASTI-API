<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fakultas;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class FakultasController extends Controller
{
    /**
     * GET /api/fakultas
     * Mengambil daftar Fakultas beserta jumlah Prodi dan daftar Prodi terkait (Eager Loading bebas N+1).
     */
    public function index(): JsonResponse
    {
        try {
            $fakultas = Fakultas::query()
                ->with([
                    'prodi' => function ($query) {
                        $query->select('id', 'id_fakultas', 'nama_prodi', 'periode_mulai_aktif')
                              ->orderBy('nama_prodi');
                    },
                    'users' => function ($query) {
                        $query->select('id', 'username', 'role', 'id_fakultas', 'is_active', 'created_at')
                              ->where('role', 'user')
                              ->orderBy('id');
                    },
                ])
                ->withCount(['prodi', 'users'])
                ->orderBy('id')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar fakultas berhasil diambil.',
                'data'    => $fakultas,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar fakultas.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/fakultas
     * Menambahkan Fakultas baru (Mendukung singkatan opsional dengan auto-generate singkatan unik).
     */
    public function store(Request $request): JsonResponse
    {
        $rawNama      = trim((string) $request->input('nama_fakultas', ''));
        $rawSingkatan = trim((string) $request->input('singkatan', ''));

        // Bersihkan record soft-delete yang mungkin menghalangi index UNIQUE di database
        if ($rawNama !== '') {
            Fakultas::onlyTrashed()
                ->where('nama_fakultas', $rawNama)
                ->whereDoesntHave('prodi')
                ->forceDelete();
        }
        if ($rawSingkatan !== '') {
            Fakultas::onlyTrashed()
                ->where('singkatan', $rawSingkatan)
                ->whereDoesntHave('prodi')
                ->forceDelete();
        }

        $request->merge([
            'nama_fakultas' => $rawNama,
            'singkatan'     => $rawSingkatan !== '' ? $rawSingkatan : null,
        ]);

        $validated = $request->validate([
            'singkatan'     => ['nullable', 'string', 'max:20', Rule::unique('fakultas', 'singkatan')->whereNull('deleted_at')],
            'nama_fakultas' => ['required', 'string', 'max:150', Rule::unique('fakultas', 'nama_fakultas')->whereNull('deleted_at')],
        ], [
            'nama_fakultas.required' => 'Nama Fakultas wajib diisi.',
            'nama_fakultas.unique'   => 'Nama Fakultas tersebut sudah terdaftar di sistem.',
            'singkatan.unique'       => 'Singkatan Fakultas tersebut sudah digunakan oleh fakultas lain.',
            'singkatan.max'          => 'Singkatan maksimal 20 karakter.',
        ]);

        $finalSingkatan = ! empty($validated['singkatan'])
            ? $validated['singkatan']
            : $this->generateUniqueSingkatan($validated['nama_fakultas']);

        try {
            $fakultas = Fakultas::create([
                'nama_fakultas' => $validated['nama_fakultas'],
                'singkatan'     => $finalSingkatan,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Fakultas berhasil ditambahkan.',
                'data'    => $fakultas,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menambahkan fakultas.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PUT /api/fakultas/{id}
     * Memperbarui data Fakultas.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $fakultas = Fakultas::find($id);

        if (! $fakultas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data fakultas tidak ditemukan.',
            ], 404);
        }

        $rawNama      = trim((string) $request->input('nama_fakultas', ''));
        $rawSingkatan = trim((string) $request->input('singkatan', ''));

        $request->merge([
            'nama_fakultas' => $rawNama,
            'singkatan'     => $rawSingkatan !== '' ? $rawSingkatan : null,
        ]);

        $validated = $request->validate([
            'singkatan'     => ['nullable', 'string', 'max:20', Rule::unique('fakultas', 'singkatan')->ignore($fakultas->id)->whereNull('deleted_at')],
            'nama_fakultas' => ['required', 'string', 'max:150', Rule::unique('fakultas', 'nama_fakultas')->ignore($fakultas->id)->whereNull('deleted_at')],
        ], [
            'nama_fakultas.required' => 'Nama Fakultas wajib diisi.',
            'nama_fakultas.unique'   => 'Nama Fakultas tersebut sudah terdaftar di sistem.',
            'singkatan.unique'       => 'Singkatan Fakultas tersebut sudah digunakan oleh fakultas lain.',
            'singkatan.max'          => 'Singkatan maksimal 20 karakter.',
        ]);

        $finalSingkatan = ! empty($validated['singkatan'])
            ? $validated['singkatan']
            : $this->generateUniqueSingkatan($validated['nama_fakultas'], $fakultas->id);

        try {
            $fakultas->update([
                'nama_fakultas' => $validated['nama_fakultas'],
                'singkatan'     => $finalSingkatan,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => 'Fakultas berhasil diperbarui.',
                'data'    => $fakultas->fresh(),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal memperbarui fakultas.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * Helper membuat singkatan otomatis & unik dari nama fakultas apabila dikosongkan.
     */
    private function generateUniqueSingkatan(string $namaFakultas, ?int $ignoreId = null): string
    {
        $words = preg_split('/\s+/', trim($namaFakultas)) ?: [];
        $stopWords = ['dan', 'serta', '&', 'di', 'ke', 'dari', 'untuk'];

        $initials = '';
        foreach ($words as $word) {
            $clean = preg_replace('/[^a-zA-Z0-9]/', '', $word);
            if ($clean === '' || in_array(strtolower($clean), $stopWords, true)) {
                continue;
            }
            $initials .= strtoupper(substr($clean, 0, 1));
        }

        if (strlen($initials) < 2) {
            $alnum = strtoupper(preg_replace('/[^a-zA-Z0-9]/', '', $namaFakultas));
            $initials = substr($alnum !== '' ? $alnum : 'FAK', 0, 8);
        }

        $base = substr($initials, 0, 15);
        $candidate = $base;
        $counter = 2;

        while (
            Fakultas::withTrashed()
                ->where('singkatan', $candidate)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $candidate = substr($base, 0, 15) . $counter;
            $counter++;
        }

        return $candidate;
    }

    /**
     * DELETE /api/fakultas/{id}
     * Menghapus Fakultas secara permanen dari database.
     */
    public function destroy(int $id): JsonResponse
    {
        $fakultas = Fakultas::withTrashed()->find($id);

        if (! $fakultas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data fakultas tidak ditemukan.',
            ], 404);
        }

        // Cek apakah masih ada Program Studi yang terhubung dengan Fakultas ini
        $prodiCount = Prodi::where('id_fakultas', $id)->count();
        if ($prodiCount > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => "Fakultas \"{$fakultas->nama_fakultas}\" tidak dapat dihapus karena masih menaungi {$prodiCount} Program Studi. Silakan hapus atau pindahkan Program Studi terkait terlebih dahulu.",
            ], 422);
        }

        try {
            $fakultas->forceDelete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Fakultas berhasil dihapus permanen dari database.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus fakultas dari database.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/fakultas/{id}/akun
     * Men-generate User (Operator) baru khusus untuk Fakultas tersebut.
     * Satu akun Operator memegang beberapa Prodi di bawah Fakultasnya.
     */
    public function createAkun(Request $request, int $id): JsonResponse
    {
        $fakultas = Fakultas::with('prodi:id,id_fakultas,nama_prodi')->find($id);

        if (! $fakultas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data fakultas tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:50', 'alpha_dash', 'unique:users,username'],
            'password' => ['nullable', 'string', 'min:6', 'max:100'],
        ], [
            'username.unique' => 'Username sudah digunakan oleh akun lain.',
        ]);

        $plainPassword = ! empty($validated['password'])
            ? $validated['password']
            : User::DEFAULT_OPERATOR_PASSWORD;

        try {
            $user = DB::transaction(function () use ($fakultas, $validated, $plainPassword) {
                return User::create([
                    'username'    => $validated['username'],
                    'password'    => $plainPassword,
                    'role'        => 'user',
                    'id_fakultas' => $fakultas->id,
                    'is_active'   => true,
                ]);
            });

            $firstProdi = $fakultas->prodi->first();

            return response()->json([
                'status'  => 'success',
                'message' => "Akun operator untuk fakultas {$fakultas->nama_fakultas} ({$fakultas->singkatan}) berhasil dibuat.",
                'data'    => [
                    'id'          => $user->id,
                    'username'    => $user->username,
                    'role'        => $user->role,
                    'id_fakultas' => $user->id_fakultas,
                    'is_active'   => $user->is_active,
                    'fakultas'    => $fakultas,
                    'id_prodi'    => $user->id_prodi,
                    'prodi'       => $firstProdi ? [
                        'id'         => $firstProdi->id,
                        'nama_prodi' => $firstProdi->nama_prodi,
                    ] : null,
                ],
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat membuat akun operator fakultas.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
