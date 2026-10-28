<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PeriodeAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class PeriodeController extends Controller
{
    /**
     * GET /api/periode
     * Mengambil semua data periode akademik, diurutkan dari yang terbaru.
     */
    public function index(): JsonResponse
    {
        try {
            $periode = PeriodeAkademik::query()
                ->withCount('dokumenPelaporan')
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar periode akademik berhasil diambil.',
                'data'    => $periode,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil data periode akademik.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/periode
     * Menambah periode baru:
     * - Multi-Open: Beberapa periode boleh memiliki status_pelaporan = 'Buka' bersamaan.
     * - Single-Default: Hanya boleh ada SATU periode dengan is_default = true.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_periode'     => ['required', 'string', 'max:50', 'unique:periode_akademik,nama_periode'],
            'status_pelaporan' => ['nullable', Rule::in([PeriodeAkademik::STATUS_BUKA, PeriodeAkademik::STATUS_TUTUP])],
            'is_default'       => ['nullable', 'boolean'],
        ]);

        $statusPelaporan = $validated['status_pelaporan'] ?? PeriodeAkademik::STATUS_TUTUP;
        $isDefault       = (bool) ($validated['is_default'] ?? false);

        try {
            $periode = DB::transaction(function () use ($validated, $statusPelaporan, $isDefault) {
                // Aturan Single-Default: Jika is_default bernilai true, ubah semua periode lain menjadi false
                if ($isDefault) {
                    PeriodeAkademik::where('is_default', true)
                        ->update(['is_default' => false]);
                }

                return PeriodeAkademik::create([
                    'nama_periode'     => $validated['nama_periode'],
                    'status_pelaporan' => $statusPelaporan,
                    'is_default'       => $isDefault,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Periode akademik berhasil ditambahkan.',
                'data'    => $periode,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan periode akademik.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PUT /api/periode/{id}
     * Mengupdate data periode:
     * - Multi-Open: Beberapa periode boleh memiliki status_pelaporan = 'Buka' bersamaan.
     * - Single-Default: Hanya boleh ada SATU periode dengan is_default = true.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $periode = PeriodeAkademik::find($id);

        if (! $periode) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data periode akademik tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'nama_periode' => [
                'required',
                'string',
                'max:50',
                Rule::unique('periode_akademik', 'nama_periode')->ignore($periode->id),
            ],
            'status_pelaporan' => ['required', Rule::in([PeriodeAkademik::STATUS_BUKA, PeriodeAkademik::STATUS_TUTUP])],
            'is_default'       => ['sometimes', 'boolean'],
        ]);

        $isDefault = array_key_exists('is_default', $validated)
            ? (bool) $validated['is_default']
            : $periode->is_default;

        try {
            $updatedPeriode = DB::transaction(function () use ($periode, $validated, $isDefault) {
                // Aturan Single-Default: Jika is_default bernilai true, ubah semua periode lain menjadi false
                if ($isDefault) {
                    PeriodeAkademik::where('id', '!=', $periode->id)
                        ->where('is_default', true)
                        ->update(['is_default' => false]);
                }

                $periode->update([
                    'nama_periode'     => $validated['nama_periode'],
                    'status_pelaporan' => $validated['status_pelaporan'],
                    'is_default'       => $isDefault,
                ]);

                return $periode->fresh();
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Periode akademik berhasil diperbarui.',
                'data'    => $updatedPeriode,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui periode akademik.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/periode/{id}
     * Proteksi Hapus Data:
     * - Jika sudah ada relasi ke dokumen_pelaporan -> tolak hard delete (400),
     *   ubah status_pelaporan menjadi 'Tutup' dan is_default menjadi false.
     * - Jika belum ada relasi -> lakukan hard delete normal.
     */
    public function destroy(int $id): JsonResponse
    {
        $periode = PeriodeAkademik::find($id);

        if (! $periode) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data periode akademik tidak ditemukan.',
            ], 404);
        }

        try {
            return DB::transaction(function () use ($periode) {
                $hasDokumen = $periode->dokumenPelaporan()->exists();

                if ($hasDokumen) {
                    $periode->update([
                        'status_pelaporan' => PeriodeAkademik::STATUS_TUTUP,
                        'is_default'       => false,
                    ]);

                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Periode tidak dapat dihapus karena menampung dokumen Prodi. Status diubah menjadi Tutup.',
                        'data'    => $periode->fresh(),
                    ], 400);
                }

                $periode->delete();

                return response()->json([
                    'status'  => 'success',
                    'message' => 'Periode akademik berhasil dihapus secara permanen.',
                ], 200);
            });
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menghapus periode akademik.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
