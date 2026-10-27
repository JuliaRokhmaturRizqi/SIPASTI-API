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
     * Menambah periode baru dengan aturan Single-Active di dalam DB::transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_periode' => ['required', 'string', 'max:50', 'unique:periode_akademik,nama_periode'],
            'status'       => ['nullable', Rule::in(['Aktif', 'Nonaktif', 'Arsip'])],
        ]);

        $status = $validated['status'] ?? PeriodeAkademik::STATUS_NONAKTIF;

        try {
            $periode = DB::transaction(function () use ($validated, $status) {
                // Aturan Single-Active: Nonaktifkan semua periode yang sedang Aktif terlebih dahulu
                if ($status === PeriodeAkademik::STATUS_AKTIF) {
                    PeriodeAkademik::where('status', PeriodeAkademik::STATUS_AKTIF)
                        ->update(['status' => PeriodeAkademik::STATUS_NONAKTIF]);
                }

                return PeriodeAkademik::create([
                    'nama_periode' => $validated['nama_periode'],
                    'status'       => $status,
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
     * Mengupdate data periode dengan aturan Single-Active di dalam DB::transaction.
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
            'status' => ['required', Rule::in(['Aktif', 'Nonaktif', 'Arsip'])],
        ]);

        try {
            $updatedPeriode = DB::transaction(function () use ($periode, $validated) {
                // Aturan Single-Active: Jika diubah menjadi Aktif, ubah periode Aktif lainnya menjadi Nonaktif
                if ($validated['status'] === PeriodeAkademik::STATUS_AKTIF) {
                    PeriodeAkademik::where('id', '!=', $periode->id)
                        ->where('status', PeriodeAkademik::STATUS_AKTIF)
                        ->update(['status' => PeriodeAkademik::STATUS_NONAKTIF]);
                }

                $periode->update([
                    'nama_periode' => $validated['nama_periode'],
                    'status'       => $validated['status'],
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
     * - Jika sudah memiliki relasi di dokumen_pelaporan -> tolak hard delete (400) & ubah status menjadi 'Arsip'.
     * - Jika belum memiliki relasi -> lakukan hard delete normal.
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
                        'status' => PeriodeAkademik::STATUS_ARSIP,
                    ]);

                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Periode tidak dapat dihapus karena sudah memiliki data pelaporan. Status diubah menjadi Arsip.',
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
