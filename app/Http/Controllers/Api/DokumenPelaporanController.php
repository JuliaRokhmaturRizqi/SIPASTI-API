<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DokumenPelaporan;
use App\Models\PeriodeAkademik;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DokumenPelaporanController extends Controller
{
    /**
     * D. GET /api/dokumen (auth:sanctum)
     * Mengambil daftar dokumen pelaporan:
     * - Mendukung query parameter ?id_periode=
     * - Jika role = 'user', otomatis difilter hanya milik id_prodi user tersebut (Anti-IDOR).
     * - Jika role = 'admin', tampilkan semua.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id_periode' => ['nullable', 'integer', 'exists:periode_akademik,id'],
        ]);

        try {
            $user  = $request->user();
            $query = DokumenPelaporan::query()
                ->with([
                    'prodi:id,nama_prodi',
                    'periodeAkademik:id,nama_periode,status_pelaporan,is_default',
                ]);

            // Filter otomatis berdasarkan role (mencegah IDOR)
            if ($user->role === 'user') {
                $query->where('id_prodi', $user->id_prodi);
            }

            // Filter berdasarkan query parameter ?id_periode=
            if (! empty($validated['id_periode'])) {
                $query->where('id_periode', (int) $validated['id_periode']);
            }

            $dokumen = $query
                ->orderByDesc('id_periode')
                ->orderBy('id_prodi')
                ->orderBy('tahap_dokumen')
                ->get();

            return response()->json([
                'status'  => 'success',
                'message' => 'Data dokumen pelaporan berhasil diambil.',
                'data'    => $dokumen,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal mengambil daftar dokumen pelaporan.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * A. POST /api/dokumen/upload (role:user)
     * - Validasi Lapis Kedua: mimes:pdf, mimetypes:application/pdf, max:2048 (2MB), id_periode, tahap_dokumen (1-4).
     * - Penyimpanan tertutup (local disk): storage/app/dokumen_pelaporan/{id_periode}/{id_prodi}/tahap_{angka}.pdf
     * - Upsert (updateOrCreate) & timpa file lama jika unggahan revisi.
     * - Set status = 'Review' dan catatan_revisi = null.
     */
    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user->id_prodi) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun Anda tidak terhubung dengan Program Studi manapun.',
            ], 403);
        }

        $validated = $request->validate([
            'id_periode'    => ['required', 'integer', 'exists:periode_akademik,id'],
            'tahap_dokumen' => ['required', 'integer', 'between:1,4'],
            'file'          => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:2048'],
        ]);

        $idPeriode    = (int) $validated['id_periode'];
        $idProdi      = (int) $user->id_prodi;
        $tahapDokumen = (int) $validated['tahap_dokumen'];

        // Cek apakah periode akademik berstatus 'Buka'
        $periode = PeriodeAkademik::findOrFail($idPeriode);
        if ($periode->status_pelaporan !== PeriodeAkademik::STATUS_BUKA) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Periode akademik ini sedang ditutup untuk pelaporan dokumen.',
            ], 403);
        }

        $directory    = "dokumen_pelaporan/{$idPeriode}/{$idProdi}";
        $fileName     = "tahap_{$tahapDokumen}.pdf";
        $relativePath = "{$directory}/{$fileName}";

        try {
            $dokumen = DB::transaction(function () use ($request, $idProdi, $idPeriode, $tahapDokumen, $directory, $fileName, $relativePath) {
                $existing = DokumenPelaporan::where('id_prodi', $idProdi)
                    ->where('id_periode', $idPeriode)
                    ->where('tahap_dokumen', $tahapDokumen)
                    ->lockForUpdate()
                    ->first();

                // Hapus file lama terlebih dahulu jika ada (File Overwrite bersih)
                if ($existing && $existing->getRawOriginal('file_path')) {
                    $oldPath = $existing->getRawOriginal('file_path');
                    if (Storage::disk('local')->exists($oldPath)) {
                        Storage::disk('local')->delete($oldPath);
                    }
                }

                // Simpan file baru ke local/private disk: storage/app/dokumen_pelaporan/{id_periode}/{id_prodi}/tahap_{angka}.pdf
                Storage::disk('local')->putFileAs(
                    $directory,
                    $request->file('file'),
                    $fileName
                );

                return DokumenPelaporan::updateOrCreate(
                    [
                        'id_prodi'      => $idProdi,
                        'id_periode'    => $idPeriode,
                        'tahap_dokumen' => $tahapDokumen,
                    ],
                    [
                        'file_path'      => $relativePath,
                        'status'         => DokumenPelaporan::STATUS_REVIEW,
                        'catatan_revisi' => null,
                        'tanggal_upload' => now(),
                    ]
                );
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Dokumen pelaporan berhasil diunggah dan sedang dalam proses Review.',
                'data'    => $dokumen->load(['prodi:id,nama_prodi', 'periodeAkademik:id,nama_periode']),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengunggah dokumen pelaporan.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * B. POST /api/dokumen/{id}/validasi (role:admin)
     * - Menerima status ('Selesai' atau 'Revisi').
     * - Jika 'Revisi', field catatan_revisi WAJIB diisi dan disanitasi dari tag HTML (Anti-XSS).
     */
    public function validasi(Request $request, int $id): JsonResponse
    {
        $dokumen = DokumenPelaporan::find($id);

        if (! $dokumen) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data dokumen pelaporan tidak ditemukan.',
            ], 404);
        }

        $validated = $request->validate([
            'status'         => ['required', Rule::in([DokumenPelaporan::STATUS_SELESAI, DokumenPelaporan::STATUS_REVISI])],
            'catatan_revisi' => [
                Rule::requiredIf(fn () => $request->input('status') === DokumenPelaporan::STATUS_REVISI),
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        // Sanitasi input catatan_revisi untuk mencegah Stored XSS
        $catatanRevisi = $validated['status'] === DokumenPelaporan::STATUS_REVISI
            ? trim(strip_tags((string) $validated['catatan_revisi']))
            : null;

        try {
            $dokumen->update([
                'status'         => $validated['status'],
                'catatan_revisi' => $catatanRevisi,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => "Status dokumen berhasil diperbarui menjadi {$validated['status']}.",
                'data'    => $dokumen->fresh(['prodi:id,nama_prodi', 'periodeAkademik:id,nama_periode']),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memvalidasi dokumen pelaporan.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * C. GET /api/dokumen/{id}/download (auth:sanctum)
     * - Otorisasi RBAC & Anti-IDOR:
     *   - Jika role = 'admin' -> izinkan unduh.
     *   - Jika role = 'user' -> cek apakah $dokumen->id_prodi === $user->id_prodi, jika tidak -> 403 Forbidden.
     * - Mengembalikan stream file via Storage::disk('local')->download($path) tanpa mengekspos path asli.
     */
    public function download(Request $request, int $id): StreamedResponse|JsonResponse
    {
        $dokumen = DokumenPelaporan::find($id);

        if (! $dokumen) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data dokumen pelaporan tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();

        // Proteksi RBAC & Anti-IDOR
        if ($user->role === 'user' && (int) $dokumen->id_prodi !== (int) $user->id_prodi) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Anda tidak memiliki hak akses untuk mengunduh dokumen prodi lain.',
            ], 403);
        }

        if ($user->role !== 'admin' && $user->role !== 'user') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden.',
            ], 403);
        }

        $filePath = $dokumen->getRawOriginal('file_path');

        if (! $filePath || ! Storage::disk('local')->exists($filePath)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'File dokumen belum diunggah atau tidak ditemukan di server.',
            ], 404);
        }

        $downloadName = sprintf(
            'dokumen_prodi_%d_periode_%d_tahap_%d.pdf',
            $dokumen->id_prodi,
            $dokumen->id_periode,
            $dokumen->tahap_dokumen
        );

        return Storage::disk('local')->download($filePath, $downloadName, [
            'Content-Type'           => 'application/pdf',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
