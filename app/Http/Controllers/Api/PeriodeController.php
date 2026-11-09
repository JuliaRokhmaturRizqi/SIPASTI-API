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
     * Menambah periode baru (Mendukung Pelaporan Multi-Periode Terbuka).
     * Setiap periode bersifat INDEPENDEN. Pengaktifan periode baru TIDAK AKAN
     * menonaktifkan periode lain yang sedang aktif/buka.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nama_periode'     => ['required', 'string', 'max:50', 'unique:periode_akademik,nama_periode'],
            'status_aktif'     => ['nullable', 'boolean'],
            'status_pelaporan' => ['nullable', Rule::in([PeriodeAkademik::STATUS_BUKA, PeriodeAkademik::STATUS_TUTUP, 'Aktif', 'Nonaktif'])],
            'is_default'       => ['nullable', 'boolean'],
        ]);

        // Tentukan nilai status_aktif secara fleksibel (default: true / Buka)
        $statusAktif = true;
        if (array_key_exists('status_aktif', $validated)) {
            $statusAktif = (bool) $validated['status_aktif'];
        } elseif (! empty($validated['status_pelaporan'])) {
            $statusAktif = in_array($validated['status_pelaporan'], [PeriodeAkademik::STATUS_BUKA, 'Aktif'], true);
        }

        $isDefault = (bool) ($validated['is_default'] ?? false);

        try {
            $periode = DB::transaction(function () use ($validated, $statusAktif, $isDefault) {
                // Hanya flag is_default yang saling eksklusif (penanda periode utama dashboard)
                if ($isDefault) {
                    PeriodeAkademik::where('is_default', true)
                        ->update(['is_default' => false]);
                }

                // PENTING: status_aktif bersifat independen, TIDAK mengubah status_aktif periode lain!
                return PeriodeAkademik::create([
                    'nama_periode'     => $validated['nama_periode'],
                    'status_aktif'     => $statusAktif,
                    'status_pelaporan' => $statusAktif ? PeriodeAkademik::STATUS_BUKA : PeriodeAkademik::STATUS_TUTUP,
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
     * Mengupdate data periode akademik.
     * Aturan Independen: Mengubah status_aktif = true pada periode ini TIDAK AKAN
     * mengubah status_aktif = false pada periode lain. Seluruh periode bisa aktif bersamaan.
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
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('periode_akademik', 'nama_periode')->ignore($periode->id),
            ],
            'status_aktif'     => ['sometimes', 'boolean'],
            'status_pelaporan' => ['sometimes', Rule::in([PeriodeAkademik::STATUS_BUKA, PeriodeAkademik::STATUS_TUTUP, 'Aktif', 'Nonaktif'])],
            'is_default'       => ['sometimes', 'boolean'],
        ]);

        try {
            $updatedPeriode = DB::transaction(function () use ($periode, $validated) {
                $payload = [];

                if (array_key_exists('nama_periode', $validated)) {
                    $payload['nama_periode'] = $validated['nama_periode'];
                }

                // Menangani pembaruan status aktif secara independen
                if (array_key_exists('status_aktif', $validated)) {
                    $statusAktif = (bool) $validated['status_aktif'];
                    $payload['status_aktif']     = $statusAktif;
                    $payload['status_pelaporan'] = $statusAktif ? PeriodeAkademik::STATUS_BUKA : PeriodeAkademik::STATUS_TUTUP;
                } elseif (array_key_exists('status_pelaporan', $validated)) {
                    $statusAktif = in_array($validated['status_pelaporan'], [PeriodeAkademik::STATUS_BUKA, 'Aktif'], true);
                    $payload['status_aktif']     = $statusAktif;
                    $payload['status_pelaporan'] = $statusAktif ? PeriodeAkademik::STATUS_BUKA : PeriodeAkademik::STATUS_TUTUP;
                }

                if (array_key_exists('is_default', $validated)) {
                    $isDefault = (bool) $validated['is_default'];
                    if ($isDefault) {
                        PeriodeAkademik::where('id', '!=', $periode->id)
                            ->where('is_default', true)
                            ->update(['is_default' => false]);
                    }
                    $payload['is_default'] = $isDefault;
                }

                // PERINGATAN: TIDAK ADA manipulasi status_aktif pada periode lain!
                // Seluruh periode lain tetap mempertahankan status_aktif aslinya.
                $periode->update($payload);

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
     * PATCH /api/periode/{id}/toggle-active
     * Endpoint khusus untuk mengaktifkan / menonaktifkan status_aktif periode (Soft Toggle).
     * Sama sekali TIDAK MENYENTUH status_aktif periode lainnya.
     */
    public function toggleActive(int $id): JsonResponse
    {
        $periode = PeriodeAkademik::find($id);

        if (! $periode) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data periode akademik tidak ditemukan.',
            ], 404);
        }

        try {
            $updatedPeriode = DB::transaction(function () use ($periode) {
                // Balikkan status periode ini saja secara independen
                $newStatus = ! $periode->status_aktif;

                $periode->update([
                    'status_aktif'     => $newStatus,
                    'status_pelaporan' => $newStatus ? PeriodeAkademik::STATUS_BUKA : PeriodeAkademik::STATUS_TUTUP,
                ]);

                return $periode->fresh();
            });

            $statusText = $updatedPeriode->status_aktif
                ? 'diaktifkan (pelaporan dibuka)'
                : 'dinonaktifkan (pelaporan ditutup)';

            return response()->json([
                'status'  => 'success',
                'message' => "Periode akademik berhasil {$statusText}.",
                'data'    => $updatedPeriode,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengubah status aktif periode akademik.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/periode/{id}
     * Proteksi Hapus Data:
     * - Jika sudah ada relasi ke dokumen_pelaporan -> tolak hard delete (400),
     *   ubah status_aktif menjadi false (Tutup) dan is_default menjadi false.
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
                        'status_aktif'     => false,
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
