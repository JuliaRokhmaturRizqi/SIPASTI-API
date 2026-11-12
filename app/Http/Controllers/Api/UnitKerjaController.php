<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KategoriUnit;
use App\Models\UnitKerja;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class UnitKerjaController extends Controller
{
    /**
     * GET /api/unit-kerja
     * Mengambil daftar seluruh Master Data Unit Kerja beserta jumlah akun dan laporan kinerjanya.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = UnitKerja::query()
                ->with('kategori:id,nama_kategori')
                ->withCount(['users', 'laporanKinerja']);

            if ($request->filled('id_kategori')) {
                $query->where('id_kategori', (int) $request->input('id_kategori'));
            } elseif ($request->filled('kategori_unit')) {
                $kategori = (string) $request->input('kategori_unit');
                $query->whereHas('kategori', function ($q) use ($kategori) {
                    $q->where('nama_kategori', 'like', "%{$kategori}%");
                });
            }

            $units = $query
                ->orderBy('nama_unit')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar master data unit kerja berhasil diambil.',
                'data'    => $units,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar master data unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/unit-kerja
     * Menambahkan Master Data Unit Kerja baru.
     */
    public function store(Request $request): JsonResponse
    {
        if ($request->has('nama_unit') && is_string($request->input('nama_unit'))) {
            $request->merge([
                'nama_unit' => trim(strip_tags($request->input('nama_unit'))),
            ]);
        }

        $validated = $request->validate([
            'nama_unit'     => ['required', 'string', 'max:255', 'unique:unit_kerja,nama_unit'],
            'id_kategori'   => ['nullable', 'integer', 'exists:kategori_unit,id'],
            'kategori_unit' => ['nullable', 'string', 'max:100'],
        ], [
            'nama_unit.required' => 'Nama unit kerja wajib diisi.',
            'nama_unit.unique'   => 'Nama unit kerja sudah terdaftar di dalam sistem.',
            'id_kategori.exists' => 'Kategori unit kerja yang dipilih tidak valid.',
        ]);

        if (empty($validated['id_kategori']) && empty($validated['kategori_unit'])) {
            return response()->json([
                'message' => 'Kategori unit kerja wajib dipilih.',
                'errors'  => [
                    'kategori_unit' => ['Kategori unit kerja wajib dipilih.'],
                ],
            ], 422);
        }

        try {
            $unit = DB::transaction(function () use ($validated) {
                $idKategori = $validated['id_kategori'] ?? null;

                if (! $idKategori && ! empty($validated['kategori_unit'])) {
                    $map = [
                        'biro'         => 'Biro',
                        'pusat'        => 'Pusat',
                        'lab'          => 'Laboratorium',
                        'laboratorium' => 'Laboratorium',
                        'fakultas'     => 'Fakultas',
                    ];

                    $normalized   = strtolower(trim($validated['kategori_unit']));
                    $namaKategori = $map[$normalized] ?? ucfirst($validated['kategori_unit']);
                    $cat = KategoriUnit::firstOrCreate(['nama_kategori' => $namaKategori]);
                    $idKategori = $cat->id;
                }

                return UnitKerja::create([
                    'nama_unit'   => $validated['nama_unit'],
                    'id_kategori' => $idKategori,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Unit kerja berhasil ditambahkan.',
                'data'    => $unit->load('kategori:id,nama_kategori'),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/unit-kerja/{id}
     * Menampilkan detail 1 Unit Kerja.
     */
    public function show(int $id): JsonResponse
    {
        $unit = UnitKerja::with('kategori:id,nama_kategori')
            ->withCount(['users', 'laporanKinerja'])
            ->find($id);

        if (! $unit) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data unit kerja tidak ditemukan.',
            ], 404);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail unit kerja berhasil diambil.',
            'data'    => $unit,
        ], 200);
    }

    /**
     * PUT/PATCH /api/unit-kerja/{id}
     * Memperbarui Master Data Unit Kerja.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $unit = UnitKerja::find($id);

        if (! $unit) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data unit kerja tidak ditemukan.',
            ], 404);
        }

        if ($request->has('nama_unit') && is_string($request->input('nama_unit'))) {
            $request->merge([
                'nama_unit' => trim(strip_tags($request->input('nama_unit'))),
            ]);
        }

        $validated = $request->validate([
            'nama_unit'     => [
                'required',
                'string',
                'max:255',
                Rule::unique('unit_kerja', 'nama_unit')->ignore($unit->id),
            ],
            'id_kategori'   => ['nullable', 'integer', 'exists:kategori_unit,id'],
            'kategori_unit' => ['nullable', 'string', 'max:100'],
        ], [
            'nama_unit.required' => 'Nama unit kerja wajib diisi.',
            'nama_unit.unique'   => 'Nama unit kerja sudah terdaftar di dalam sistem.',
            'id_kategori.exists' => 'Kategori unit kerja yang dipilih tidak valid.',
        ]);

        try {
            DB::transaction(function () use ($unit, $validated) {
                $payload = ['nama_unit' => $validated['nama_unit']];

                if (! empty($validated['id_kategori'])) {
                    $payload['id_kategori'] = (int) $validated['id_kategori'];
                } elseif (! empty($validated['kategori_unit'])) {
                    $map = [
                        'biro'         => 'Biro',
                        'pusat'        => 'Pusat',
                        'lab'          => 'Laboratorium',
                        'laboratorium' => 'Laboratorium',
                        'fakultas'     => 'Fakultas',
                    ];

                    $normalized   = strtolower(trim($validated['kategori_unit']));
                    $namaKategori = $map[$normalized] ?? ucfirst($validated['kategori_unit']);
                    $cat = KategoriUnit::firstOrCreate(['nama_kategori' => $namaKategori]);
                    $payload['id_kategori'] = $cat->id;
                }

                $unit->update($payload);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Unit kerja berhasil diperbarui.',
                'data'    => $unit->fresh(['kategori:id,nama_kategori']),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/unit-kerja/{id}
     * Menghapus Master Data Unit Kerja.
     */
    public function destroy(int $id): JsonResponse
    {
        $unit = UnitKerja::find($id);

        if (! $unit) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data unit kerja tidak ditemukan.',
            ], 404);
        }

        try {
            DB::transaction(function () use ($unit) {
                $unit->delete();
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Unit kerja berhasil dihapus.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menghapus unit kerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
