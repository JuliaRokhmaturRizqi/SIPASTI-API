<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KategoriUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class KategoriUnitController extends Controller
{
    /**
     * GET /api/kategori-unit
     * Mengambil seluruh daftar master data kategori unit kerja.
     */
    public function index(): JsonResponse
    {
        try {
            $categories = KategoriUnit::withCount('unitKerja')
                ->orderBy('nama_kategori', 'asc')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar kategori unit kerja berhasil diambil.',
                'data'    => $categories,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar kategori unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/kategori-unit
     * Menambahkan kategori unit kerja baru.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('nama_kategori') && is_string($request->input('nama_kategori'))) {
            $request->merge([
                'nama_kategori' => trim(strip_tags($request->input('nama_kategori'))),
            ]);
        }

        $validated = $request->validate([
            'nama_kategori' => ['required', 'string', 'max:100', 'unique:kategori_unit,nama_kategori'],
        ], [
            'nama_kategori.required' => 'Nama kategori unit kerja wajib diisi.',
            'nama_kategori.max'      => 'Nama kategori unit kerja maksimal 100 karakter.',
            'nama_kategori.unique'   => 'Kategori unit kerja dengan nama ini sudah terdaftar.',
        ]);

        try {
            $category = DB::transaction(function () use ($validated) {
                return KategoriUnit::create([
                    'nama_kategori' => $validated['nama_kategori'],
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Kategori unit kerja berhasil ditambahkan.',
                'data'    => $category,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan kategori unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/kategori-unit/{id}
     * Menampilkan detail 1 kategori unit kerja.
     */
    public function show(int $id): JsonResponse
    {
        $category = KategoriUnit::withCount('unitKerja')->find($id);

        if (! $category) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data kategori unit kerja tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail kategori unit kerja berhasil diambil.',
            'data'    => $category,
        ], 200);
    }

    /**
     * PUT/PATCH /api/kategori-unit/{id}
     * Memperbarui kategori unit kerja.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $category = KategoriUnit::find($id);

        if (! $category) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data kategori unit kerja tidak ditemukan.',
            ], 404);
        }

        if ($request->has('nama_kategori') && is_string($request->input('nama_kategori'))) {
            $request->merge([
                'nama_kategori' => trim(strip_tags($request->input('nama_kategori'))),
            ]);
        }

        $validated = $request->validate([
            'nama_kategori' => [
                'required',
                'string',
                'max:100',
                Rule::unique('kategori_unit', 'nama_kategori')->ignore($category->id),
            ],
        ], [
            'nama_kategori.required' => 'Nama kategori unit kerja wajib diisi.',
            'nama_kategori.max'      => 'Nama kategori unit kerja maksimal 100 karakter.',
            'nama_kategori.unique'   => 'Kategori unit kerja dengan nama ini sudah terdaftar.',
        ]);

        try {
            DB::transaction(function () use ($category, $validated) {
                $category->update([
                    'nama_kategori' => $validated['nama_kategori'],
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Kategori unit kerja berhasil diperbarui.',
                'data'    => $category->fresh(),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui kategori unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/kategori-unit/{id}
     * Menghapus kategori unit kerja.
     */
    public function destroy(int $id): JsonResponse
    {
        $category = KategoriUnit::withCount('unitKerja')->find($id);

        if (! $category) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data kategori unit kerja tidak ditemukan.',
            ], 404);
        }

        if ($category->unit_kerja_count > 0) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Kategori unit tidak dapat dihapus karena masih digunakan oleh unit kerja.',
            ], 422);
        }

        try {
            DB::transaction(function () use ($category) {
                $category->delete();
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Kategori unit kerja berhasil dihapus.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menghapus kategori unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
