<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fakultas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
                ->with(['prodi' => function ($query) {
                    $query->select('id', 'id_fakultas', 'nama_prodi', 'periode_mulai_aktif')
                          ->orderBy('nama_prodi');
                }])
                ->withCount('prodi')
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
     * Menambahkan Fakultas baru.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'singkatan'     => ['required', 'string', 'max:20', Rule::unique('fakultas', 'singkatan')->whereNull('deleted_at')],
            'nama_fakultas' => ['required', 'string', 'max:150', Rule::unique('fakultas', 'nama_fakultas')->whereNull('deleted_at')],
        ]);

        try {
            $fakultas = Fakultas::create($validated);

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

        $validated = $request->validate([
            'singkatan'     => ['required', 'string', 'max:20', Rule::unique('fakultas', 'singkatan')->ignore($fakultas->id)->whereNull('deleted_at')],
            'nama_fakultas' => ['required', 'string', 'max:150', Rule::unique('fakultas', 'nama_fakultas')->ignore($fakultas->id)->whereNull('deleted_at')],
        ]);

        try {
            $fakultas->update($validated);

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
     * DELETE /api/fakultas/{id}
     * Soft delete Fakultas.
     */
    public function destroy(int $id): JsonResponse
    {
        $fakultas = Fakultas::find($id);

        if (! $fakultas) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data fakultas tidak ditemukan.',
            ], 404);
        }

        try {
            $fakultas->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Fakultas berhasil dihapus (Soft Delete).',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal menghapus fakultas.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
