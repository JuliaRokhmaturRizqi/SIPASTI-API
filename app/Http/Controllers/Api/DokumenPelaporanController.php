<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DokumenPelaporan;
use App\Models\LampiranDokumen;
use App\Models\PeriodeAkademik;
use App\Models\Prodi;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use ZipArchive;

class DokumenPelaporanController extends Controller
{
    /**
     * D. GET /api/dokumen (auth:sanctum)
     * Mengambil daftar dokumen pelaporan beserta lampirannya:
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
                    'lampiran:id,dokumen_pelaporan_id,jenis_lampiran,nama_file_asli,created_at',
                ])
                ->withCount('lampiran');

            // Filter otomatis berdasarkan role (mencegah IDOR)
            if ($user->role === 'user') {
                if ($user->id_fakultas) {
                    $prodiIds = Prodi::where('id_fakultas', $user->id_fakultas)->pluck('id')->toArray();
                    if ($request->filled('id_prodi')) {
                        $targetProdi = (int) $request->input('id_prodi');
                        if (in_array($targetProdi, $prodiIds, true)) {
                            $query->where('id_prodi', $targetProdi);
                        } else {
                            $query->whereRaw('1 = 0');
                        }
                    } else {
                        $query->whereIn('id_prodi', $prodiIds);
                    }
                } elseif ($user->id_prodi) {
                    $query->where('id_prodi', $user->id_prodi);
                }
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
        $idProdi = (int) ($request->input('id_prodi') ?: $user->id_prodi);

        if (! $idProdi || ! $this->canUserAccessProdi($user, $idProdi)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun Operator Anda tidak memiliki hak akses untuk mengunggah dokumen pada Program Studi ini.',
            ], 403);
        }

        // Normalisasi agar mendukung field 'file' maupun 'dokumen' serta 'tahap_dokumen' maupun 'tahap'
        if ($request->files->has('dokumen') && ! $request->files->has('file')) {
            $request->files->set('file', $request->files->get('dokumen'));
        }
        if (! $request->filled('tahap_dokumen') && $request->filled('tahap')) {
            $request->merge(['tahap_dokumen' => $request->input('tahap')]);
        }

        $validated = $request->validate([
            'id_periode'    => ['required', 'integer', 'exists:periode_akademik,id'],
            'tahap_dokumen' => ['required', 'integer', 'between:1,4'],
            'file'          => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:2048'],
        ]);

        $idPeriode    = (int) $validated['id_periode'];
        $tahapDokumen = (int) $validated['tahap_dokumen'];

        // Cek apakah periode akademik berstatus aktif / dibuka
        $periode = PeriodeAkademik::findOrFail($idPeriode);
        if (! $periode->status_aktif || $periode->status_pelaporan !== PeriodeAkademik::STATUS_BUKA) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Periode akademik ini sedang dinonaktifkan / ditutup untuk pelaporan dokumen.',
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
                'data'    => $dokumen->load([
                    'prodi:id,nama_prodi',
                    'periodeAkademik:id,nama_periode',
                    'lampiran:id,dokumen_pelaporan_id,jenis_lampiran,nama_file_asli,created_at',
                ]),
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
                'data'    => $dokumen->fresh([
                    'prodi:id,nama_prodi',
                    'periodeAkademik:id,nama_periode',
                    'lampiran:id,dokumen_pelaporan_id,jenis_lampiran,nama_file_asli,created_at',
                ]),
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

        // Proteksi RBAC & Anti-IDOR (Mendukung Arsitektur Akun Operator Berbasis Fakultas)
        if ($user->role === 'user' && ! $this->canUserAccessProdi($user, (int) $dokumen->id_prodi)) {
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

    /**
     * E. POST /api/dokumen/{id}/lampiran (auth:sanctum)
     * - Menerima 1 file PDF (max: 5120 KB / 5MB) secara asinkron (drag-and-drop).
     * - Validasi kepemilikan dokumen (Operator Prodi pemilik dokumen).
     * - Menyimpan file ke storage/app/dokumen_pelaporan/{id_periode}/{id_prodi}/lampiran/
     * - Mencatat metadata ke tabel lampiran_dokumen.
     */
    public function uploadLampiran(Request $request, int $id): JsonResponse
    {
        $dokumen = DokumenPelaporan::find($id);

        if (! $dokumen) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data dokumen pelaporan tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();

        // Hanya Operator yang menaungi Prodi pemilik dokumen yang diizinkan mengunggah lampiran
        if ($user->role !== 'user' || ! $this->canUserAccessProdi($user, (int) $dokumen->id_prodi)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Hanya Operator Prodi pemilik dokumen yang dapat mengunggah lampiran.',
            ], 403);
        }

        // Normalisasi agar mendukung field 'file' maupun 'lampiran' dari FormData frontend
        if ($request->files->has('lampiran') && ! $request->files->has('file')) {
            $request->files->set('file', $request->files->get('lampiran'));
        }

        $validated = $request->validate([
            'jenis_lampiran' => ['nullable', 'string', 'max:100'],
            'file'           => ['required', 'file', 'mimes:pdf', 'mimetypes:application/pdf', 'max:5120'],
        ]);

        $uploadedFile  = $request->file('file');
        $jenisLampiran = trim(strip_tags((string) ($validated['jenis_lampiran'] ?? 'Dokumen Pendukung')));

        // Sanitasi nama file asli untuk mencegah Path Traversal & XSS
        $rawOriginalName = pathinfo($uploadedFile->getClientOriginalName(), PATHINFO_FILENAME);
        $cleanBaseName   = preg_replace('/[^A-Za-z0-9_\-\s\.]/', '', $rawOriginalName) ?: 'lampiran';
        $namaFileAsli    = Str::limit(trim($cleanBaseName), 200, '') . '.pdf';

        $directory    = "dokumen_pelaporan/{$dokumen->id_periode}/{$dokumen->id_prodi}/lampiran";
        $storedName   = Str::uuid()->toString() . '_' . Str::slug(Str::limit($cleanBaseName, 50, '')) . '.pdf';
        $relativePath = "{$directory}/{$storedName}";

        try {
            $lampiran = DB::transaction(function () use ($dokumen, $uploadedFile, $directory, $storedName, $relativePath, $jenisLampiran, $namaFileAsli) {
                Storage::disk('local')->putFileAs(
                    $directory,
                    $uploadedFile,
                    $storedName
                );

                return LampiranDokumen::create([
                    'dokumen_pelaporan_id' => $dokumen->id,
                    'jenis_lampiran'       => $jenisLampiran !== '' ? $jenisLampiran : 'Dokumen Pendukung',
                    'nama_file_asli'       => $namaFileAsli,
                    'file_path'            => $relativePath,
                ]);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Lampiran dokumen berhasil diunggah.',
                'data'    => $lampiran,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat mengunggah lampiran dokumen.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * F. DELETE /api/dokumen/lampiran/{id_lampiran} (auth:sanctum)
     * - Validasi pastikan HANYA Operator Prodi pemilik dokumen yang bisa menghapus (Anti-IDOR).
     * - Menghapus file fisik dari storage lokal dan menghapus record dari database.
     */
    public function destroyLampiran(Request $request, int $id_lampiran): JsonResponse
    {
        $lampiran = LampiranDokumen::with('dokumenPelaporan')->find($id_lampiran);

        if (! $lampiran || ! $lampiran->dokumenPelaporan) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data lampiran dokumen tidak ditemukan.',
            ], 404);
        }

        $user    = $request->user();
        $dokumen = $lampiran->dokumenPelaporan;

        // Validasi ketat: Hanya Operator yang menaungi Prodi pemilik dokumen yang berhak menghapus
        if ($user->role !== 'user' || ! $this->canUserAccessProdi($user, (int) $dokumen->id_prodi)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Hanya Operator Prodi pemilik dokumen yang dapat menghapus lampiran ini.',
            ], 403);
        }

        try {
            DB::transaction(function () use ($lampiran) {
                $filePath = $lampiran->getRawOriginal('file_path');

                if ($filePath && Storage::disk('local')->exists($filePath)) {
                    Storage::disk('local')->delete($filePath);
                }

                $lampiran->delete();
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Lampiran dokumen berhasil dihapus.',
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat menghapus lampiran dokumen.',
                'error'   => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * G. GET /api/dokumen/{id}/lampiran/zip (auth:sanctum)
     * - Membuat file .zip secara dinamis menggunakan ZipArchive PHP yang berisi seluruh file lampiran_dokumen
     *   milik ID dokumen tersebut, lalu mengalirkannya sebagai response download (dan otomatis menghapus file temp).
     * - Dapat diakses oleh Admin maupun Operator Prodi pemilik dokumen.
     */
    public function downloadLampiranZip(Request $request, int $id): BinaryFileResponse|JsonResponse
    {
        $dokumen = DokumenPelaporan::with(['lampiran', 'prodi:id,nama_prodi'])->find($id);

        if (! $dokumen) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Data dokumen pelaporan tidak ditemukan.',
            ], 404);
        }

        $user = $request->user();

        // Otorisasi: Admin diizinkan semua; User hanya boleh mengunduh milik prodinya sendiri (Mendukung Akun Fakultas)
        if ($user->role === 'user' && ! $this->canUserAccessProdi($user, (int) $dokumen->id_prodi)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden. Anda tidak memiliki hak akses untuk mengunduh lampiran prodi lain.',
            ], 403);
        }

        if ($user->role !== 'admin' && $user->role !== 'user') {
            return response()->json([
                'status'  => 'error',
                'message' => 'Forbidden.',
            ], 403);
        }

        if ($dokumen->lampiran->isEmpty()) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Tidak ada file lampiran pada dokumen pelaporan ini.',
            ], 404);
        }

        $tempDir = storage_path('app/temp_zip');
        if (! is_dir($tempDir)) {
            mkdir($tempDir, 0755, true);
        }

        $zipFileName = sprintf(
            'lampiran_prodi_%d_periode_%d_tahap_%d_%s.zip',
            $dokumen->id_prodi,
            $dokumen->id_periode,
            $dokumen->tahap_dokumen,
            Str::random(8)
        );
        $zipFullPath = $tempDir . DIRECTORY_SEPARATOR . $zipFileName;

        $zip = new ZipArchive();
        if ($zip->open($zipFullPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Gagal membuat arsip ZIP di server.',
            ], 500);
        }

        $addedCount = 0;
        $usedNames  = [];

        foreach ($dokumen->lampiran as $index => $item) {
            $relPath = $item->getRawOriginal('file_path');
            if (! $relPath || ! Storage::disk('local')->exists($relPath)) {
                continue;
            }

            $absoluteFilePath = Storage::disk('local')->path($relPath);

            // Hindari bentrok nama file di dalam arsip ZIP & cegah Zip Slip (basename)
            $safeEntryName = basename($item->nama_file_asli ?: "lampiran_" . ($index + 1) . ".pdf");
            $jenisPrefix   = Str::slug($item->jenis_lampiran ?: 'lampiran', '_');
            $candidateName = "{$jenisPrefix}_{$safeEntryName}";

            if (isset($usedNames[$candidateName])) {
                $usedNames[$candidateName]++;
                $candidateName = pathinfo($candidateName, PATHINFO_FILENAME) . "_{$usedNames[$candidateName]}.pdf";
            } else {
                $usedNames[$candidateName] = 1;
            }

            $zip->addFile($absoluteFilePath, $candidateName);
            $addedCount++;
        }

        $zip->close();

        if ($addedCount === 0) {
            if (file_exists($zipFullPath)) {
                @unlink($zipFullPath);
            }

            return response()->json([
                'status'  => 'error',
                'message' => 'File fisik lampiran tidak ditemukan di server.',
            ], 404);
        }

        $downloadZipName = sprintf(
            'Lampiran_Prodi_%d_Periode_%d_Tahap_%d.zip',
            $dokumen->id_prodi,
            $dokumen->id_periode,
            $dokumen->tahap_dokumen
        );

        return response()->download($zipFullPath, $downloadZipName, [
            'Content-Type'           => 'application/zip',
            'X-Content-Type-Options' => 'nosniff',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Memeriksa otorisasi apakah User (Operator) berhak mengakses dokumen suatu Prodi.
     */
    private function canUserAccessProdi(User $user, int $idProdi): bool
    {
        if ($user->role === 'admin') {
            return true;
        }

        if ($user->role !== 'user') {
            return false;
        }

        // Cek jika akun berbasis Fakultas (arsitektur baru)
        if ($user->id_fakultas) {
            return Prodi::where('id', $idProdi)
                ->where('id_fakultas', $user->id_fakultas)
                ->exists();
        }

        // Cek jika akun masih memiliki id_prodi (kompatibilitas transisi)
        if ($user->id_prodi) {
            return (int) $idProdi === (int) $user->id_prodi;
        }

        return false;
    }
}
