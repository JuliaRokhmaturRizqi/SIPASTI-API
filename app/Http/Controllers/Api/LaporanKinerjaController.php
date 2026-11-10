<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLaporanKinerjaRequest;
use App\Http\Requests\UpdateLaporanKinerjaRequest;
use App\Models\LaporanKinerja;
use App\Models\PeriodeAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class LaporanKinerjaController extends Controller
{
    /**
     * GET /api/laporan-kinerja
     * Menampilkan daftar laporan kinerja dengan Tenant Isolation (Anti-IDOR):
     * - Role 'admin': dapat melihat seluruh laporan kinerja dari semua unit (mendukung filter).
     * - Role 'biro', 'pusat', 'lab': HANYA melihat laporan milik id_unit_kerja miliknya sendiri.
     */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'id_periode'    => ['nullable', 'integer', 'exists:periode_akademik,id'],
            'id_unit_kerja' => ['nullable', 'integer', 'exists:unit_kerja,id'],
            'kategori_unit' => ['nullable', 'string', Rule::in(['biro', 'pusat', 'lab'])],
            'status'        => ['nullable', 'string', Rule::in(LaporanKinerja::STATUS_LIST)],
        ]);

        try {
            $user = Auth::user();

            $query = LaporanKinerja::query()
                ->with([
                    'unitKerja:id,nama_unit,kategori_unit',
                    'periodeAkademik:id,nama_periode,status_aktif,status_pelaporan,is_default',
                ]);

            // Tenant Isolation (Proteksi IDOR)
            if ($user->role === 'admin') {
                if ($request->filled('id_unit_kerja')) {
                    $query->where('id_unit_kerja', (int) $request->input('id_unit_kerja'));
                }

                if ($request->filled('kategori_unit')) {
                    $kategori = $request->input('kategori_unit');
                    $query->whereHas('unitKerja', function ($q) use ($kategori) {
                        $q->where('kategori_unit', $kategori);
                    });
                }
            } else {
                // Non-admin wajib dibatasi hanya pada id_unit_kerja miliknya sendiri
                if (! $user->id_unit_kerja) {
                    return response()->json([
                        'status'  => 'error',
                        'message' => 'Akun Anda belum terhubung dengan Unit Kerja manapun.',
                    ], 403);
                }

                $query->where('id_unit_kerja', (int) $user->id_unit_kerja);
            }

            if ($request->filled('id_periode')) {
                $query->where('id_periode', (int) $request->input('id_periode'));
            }

            if ($request->filled('status')) {
                $query->where('status', $request->input('status'));
            }

            $laporan = $query
                ->orderByDesc('id_periode')
                ->orderByDesc('updated_at')
                ->orderByDesc('id')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Daftar laporan kinerja berhasil diambil.',
                'data'    => $laporan,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengambil daftar laporan kinerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/laporan-kinerja
     * Membuat laporan kinerja baru untuk unit kerja yang sedang login.
     */
    public function store(StoreLaporanKinerjaRequest $request): JsonResponse
    {
        $user      = Auth::user();
        $validated = $request->validated();

        // Pastikan id_unit_kerja terkunci ke unit milik user yang sedang login (mencegah Parameter Tampering)
        $idUnitKerja = $user->role === 'admin'
            ? (int) $validated['id_unit_kerja']
            : (int) $user->id_unit_kerja;

        if (! $idUnitKerja) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun Anda tidak terhubung ke Unit Kerja yang valid.',
            ], 403);
        }

        // Pastikan periode akademik aktif / dibuka
        $periode = PeriodeAkademik::find((int) $validated['id_periode']);
        if (! $periode || (! $periode->status_aktif && $periode->status_pelaporan !== PeriodeAkademik::STATUS_BUKA)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Periode pelaporan yang dipilih sedang ditutup.',
            ], 403);
        }

        try {
            $laporan = DB::transaction(function () use ($validated, $idUnitKerja, $user) {
                return LaporanKinerja::create([
                    'id_unit_kerja'      => $idUnitKerja,
                    'id_periode'         => (int) $validated['id_periode'],
                    'nama_kegiatan'      => $validated['nama_kegiatan'],
                    'uraian'             => $validated['uraian'],
                    'link_bukti'         => $validated['link_bukti'] ?? null,
                    'kendala'            => $validated['kendala'] ?? null,
                    'dukungan'           => $validated['dukungan'] ?? null,
                    'tindak_lanjut'      => $validated['tindak_lanjut'] ?? null,
                    'nama_penandatangan' => $validated['nama_penandatangan'],
                    'status'             => $user->role === 'admin' && ! empty($validated['status'])
                        ? $validated['status']
                        : LaporanKinerja::STATUS_REVIEW,
                    'catatan_revisi'     => null,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan kinerja berhasil disimpan.',
                'data'    => $laporan->load([
                    'unitKerja:id,nama_unit,kategori_unit',
                    'periodeAkademik:id,nama_periode,status_aktif,status_pelaporan,is_default',
                ]),
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menyimpan laporan kinerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/laporan-kinerja/{id}
     * Menampilkan detail 1 laporan kinerja dengan proteksi Tenant Isolation (Anti-IDOR).
     */
    public function show(int $id): JsonResponse
    {
        $user = Auth::user();

        $laporan = LaporanKinerja::with([
            'unitKerja:id,nama_unit,kategori_unit',
            'periodeAkademik:id,nama_periode,status_aktif,status_pelaporan,is_default',
        ])->find($id);

        if (! $laporan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data laporan kinerja tidak ditemukan.',
            ], 404);
        }

        // Proteksi IDOR: Jika bukan admin, pastikan laporan adalah milik unit kerjanya sendiri
        if ($user->role !== 'admin' && (int) $laporan->id_unit_kerja !== (int) $user->id_unit_kerja) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Anda tidak memiliki hak akses untuk melihat laporan milik unit kerja lain.',
            ], 403);
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Detail laporan kinerja berhasil diambil.',
            'data'    => $laporan,
        ], 200);
    }

    /**
     * PUT/PATCH /api/laporan-kinerja/{id}
     * Memperbarui data laporan kinerja.
     * Proteksi IDOR: Menolak dengan 403 Forbidden jika user mencoba mengedit laporan milik unit lain.
     */
    public function update(UpdateLaporanKinerjaRequest $request, int $id): JsonResponse
    {
        $user    = Auth::user();
        $laporan = LaporanKinerja::find($id);

        if (! $laporan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data laporan kinerja tidak ditemukan.',
            ], 404);
        }

        // Proteksi IDOR Ketat: Pastikan laporan milik unit kerja dari user yang sedang login
        if ($user->role !== 'admin' && (int) $laporan->id_unit_kerja !== (int) $user->id_unit_kerja) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Anda tidak diizinkan mengubah laporan kinerja milik unit kerja lain.',
            ], 403);
        }

        $validated = $request->validated();

        try {
            DB::transaction(function () use ($laporan, $validated, $user) {
                $payload = [
                    'nama_kegiatan'      => $validated['nama_kegiatan'],
                    'uraian'             => $validated['uraian'],
                    'link_bukti'         => $validated['link_bukti'] ?? null,
                    'kendala'            => $validated['kendala'] ?? null,
                    'dukungan'           => $validated['dukungan'] ?? null,
                    'tindak_lanjut'      => $validated['tindak_lanjut'] ?? null,
                    'nama_penandatangan' => $validated['nama_penandatangan'],
                ];

                if (isset($validated['id_periode'])) {
                    $payload['id_periode'] = (int) $validated['id_periode'];
                }

                // Jika diedit oleh operator unit (misal perbaikan dari Revisi), kembalikan status ke Review
                if ($user->role !== 'admin') {
                    $payload['status']         = LaporanKinerja::STATUS_REVIEW;
                    $payload['catatan_revisi'] = null;
                } elseif (! empty($validated['status'])) {
                    $payload['status'] = $validated['status'];
                }

                $laporan->update($payload);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan kinerja berhasil diperbarui.',
                'data'    => $laporan->fresh([
                    'unitKerja:id,nama_unit,kategori_unit',
                    'periodeAkademik:id,nama_periode,status_aktif,status_pelaporan,is_default',
                ]),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memperbarui laporan kinerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * PATCH /api/laporan-kinerja/{id}/validasi
     * Endpoint khusus Admin untuk memvalidasi laporan kinerja (mengubah status & catatan_revisi).
     */
    public function validasi(Request $request, int $id): JsonResponse
    {
        $user = Auth::user();

        if (! $user || $user->role !== 'admin') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Hanya Admin yang dapat memvalidasi laporan kinerja.',
            ], 403);
        }

        $laporan = LaporanKinerja::find($id);

        if (! $laporan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data laporan kinerja tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'status'         => ['required', 'string', Rule::in(LaporanKinerja::STATUS_LIST)],
            'catatan_revisi' => [
                Rule::requiredIf(fn () => $request->input('status') === LaporanKinerja::STATUS_REVISI),
                'nullable',
                'string',
                'max:2000',
            ],
        ], [
            'status.required'         => 'Status validasi wajib dipilih.',
            'status.in'               => 'Status validasi harus salah satu dari: Review, Revisi, atau Selesai.',
            'catatan_revisi.required' => 'Catatan revisi wajib diisi apabila status ditetapkan menjadi Revisi.',
        ]);

        // Sanitasi XSS pada catatan_revisi
        $cleanCatatan = isset($validated['catatan_revisi'])
            ? trim(strip_tags($validated['catatan_revisi']))
            : null;

        if ($validated['status'] === LaporanKinerja::STATUS_REVISI && ($cleanCatatan === '' || $cleanCatatan === null)) {
            return response()->json([
                'message' => 'Catatan revisi tidak boleh kosong atau hanya berisi tag HTML.',
                'errors'  => [
                    'catatan_revisi' => ['Catatan revisi wajib berisi teks instruksi perbaikan yang valid.'],
                ],
            ], 422);
        }

        try {
            $laporan->update([
                'status'         => $validated['status'],
                'catatan_revisi' => $validated['status'] === LaporanKinerja::STATUS_REVISI ? $cleanCatatan : null,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => "Status laporan kinerja berhasil diperbarui menjadi {$laporan->status}.",
                'data'    => $laporan->fresh([
                    'unitKerja:id,nama_unit,kategori_unit',
                    'periodeAkademik:id,nama_periode,status_aktif,status_pelaporan,is_default',
                ]),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memvalidasi laporan kinerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * DELETE /api/laporan-kinerja/{id}
     * Menghapus laporan kinerja (dengan proteksi kepemilikan unit kerja).
     */
    public function destroy(int $id): JsonResponse
    {
        $user    = Auth::user();
        $laporan = LaporanKinerja::find($id);

        if (! $laporan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data laporan kinerja tidak ditemukan.',
            ], 404);
        }

        if ($user->role !== 'admin' && (int) $laporan->id_unit_kerja !== (int) $user->id_unit_kerja) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Anda tidak diizinkan menghapus laporan kinerja milik unit kerja lain.',
            ], 403);
        }

        try {
            $laporan->delete();

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan kinerja berhasil dihapus.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menghapus laporan kinerja.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
