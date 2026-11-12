<?php

namespace App\Http\Controllers\Api;

use App\Exports\LaporanKinerjaExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreLaporanKinerjaRequest;
use App\Http\Requests\UpdateLaporanKinerjaRequest;
use App\Models\LaporanKinerja;
use App\Models\PeriodeAkademik;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\Response;
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
            'kategori_unit' => ['nullable', 'string'],
            'bulan_laporan' => ['nullable', 'string', 'max:50'],
            'status'        => ['nullable', 'string', Rule::in(LaporanKinerja::STATUS_LIST)],
        ]);

        try {
            $user = Auth::user();

            $query = LaporanKinerja::query()
                ->with([
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategoriUnit:id,nama_kategori',
                    'periodeAkademik:id,nama_periode,status_aktif,status_pelaporan,is_default',
                ]);

            // Tenant Isolation (Proteksi IDOR)
            if ($user->role === 'admin') {
                if ($request->filled('id_unit_kerja')) {
                    $query->where('id_unit_kerja', (int) $request->input('id_unit_kerja'));
                }

                if ($request->filled('kategori_unit')) {
                    $kategori = $request->input('kategori_unit');
                    $query->whereHas('unitKerja.kategoriUnit', function ($q) use ($kategori) {
                        $q->where('nama_kategori', 'like', "%{$kategori}%");
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

            if ($request->filled('bulan_laporan')) {
                $query->where('bulan_laporan', $request->input('bulan_laporan'));
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
     * Membuat laporan kinerja baru khusus untuk unit kerja dari user yang sedang login.
     * Aturan:
     * - Validasi duplikat: 1 Bulan = Maksimal 1 Laporan per Unit Kerja per Periode.
     * - Upload Tanda Tangan Digital (TTD File atau Base64 String dari Canvas).
     */
    public function store(StoreLaporanKinerjaRequest $request): JsonResponse
    {
        $validatedData = $request->validated();
        $idUnitKerja   = Auth::user()->id_unit_kerja;

        if (! $idUnitKerja) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Akun Anda tidak terhubung ke Unit Kerja yang valid.',
            ], 403);
        }

        // Pastikan periode akademik aktif / dibuka
        $periode = PeriodeAkademik::find((int) $validatedData['id_periode']);
        if (! $periode || (! $periode->status_aktif && $periode->status_pelaporan !== PeriodeAkademik::STATUS_BUKA)) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Periode pelaporan yang dipilih sedang ditutup.',
            ], 403);
        }

        // Validasi Aturan Bisnis: 1 Bulan = Maksimal 1 Laporan per Unit Kerja per Periode
        $bulanLaporan = $validatedData['bulan_laporan'] ?? $request->input('bulan_laporan');
        if (! empty($bulanLaporan)) {
            $isDuplicate = LaporanKinerja::where('id_unit_kerja', (int) $idUnitKerja)
                ->where('id_periode', (int) $validatedData['id_periode'])
                ->where('bulan_laporan', $bulanLaporan)
                ->exists();

            if ($isDuplicate) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Unit kerja ini sudah mengunggah laporan untuk bulan {$bulanLaporan} pada periode ini.",
                ], 422);
            }
        }

        // Tangani Upload Tanda Tangan Digital (TTD)
        $ttdImagePath = $this->handleTtdUpload($request);

        try {
            $laporan = DB::transaction(function () use ($validatedData, $idUnitKerja, $bulanLaporan, $ttdImagePath) {
                $payload = [
                    'id_unit_kerja'      => (int) $idUnitKerja,
                    'id_periode'         => (int) $validatedData['id_periode'],
                    'bulan_laporan'      => $bulanLaporan,
                    'nama_kegiatan'      => $validatedData['nama_kegiatan'],
                    'uraian'             => $validatedData['uraian'],
                    'link_bukti'         => $validatedData['link_bukti'] ?? null,
                    'kendala'            => $validatedData['kendala'] ?? null,
                    'dukungan'           => $validatedData['dukungan'] ?? null,
                    'tindak_lanjut'      => $validatedData['tindak_lanjut'] ?? null,
                    'nama_penandatangan' => $validatedData['nama_penandatangan'],
                    'ttd_image_path'     => $ttdImagePath,
                    'status'             => LaporanKinerja::STATUS_REVIEW,
                    'catatan_revisi'     => null,
                ];

                return LaporanKinerja::create($payload);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan kinerja berhasil disimpan.',
                'data'    => $laporan->load([
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategoriUnit:id,nama_kategori',
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
            'unitKerja:id,nama_unit,id_kategori',
            'unitKerja.kategoriUnit:id,nama_kategori',
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

        $targetPeriode = isset($validated['id_periode']) ? (int) $validated['id_periode'] : (int) $laporan->id_periode;
        $targetBulan   = array_key_exists('bulan_laporan', $validated)
            ? $validated['bulan_laporan']
            : ($request->has('bulan_laporan') ? $request->input('bulan_laporan') : $laporan->bulan_laporan);

        // Validasi keunikan bulan jika bulan / periode diubah
        if (! empty($targetBulan) && ($targetBulan !== $laporan->bulan_laporan || $targetPeriode !== (int) $laporan->id_periode)) {
            $isDuplicate = LaporanKinerja::where('id_unit_kerja', (int) $laporan->id_unit_kerja)
                ->where('id_periode', $targetPeriode)
                ->where('bulan_laporan', $targetBulan)
                ->where('id', '!=', $laporan->id)
                ->exists();

            if ($isDuplicate) {
                return response()->json([
                    'status'  => 'error',
                    'message' => "Unit kerja ini sudah mengunggah laporan untuk bulan {$targetBulan} pada periode ini.",
                ], 422);
            }
        }

        $ttdImagePath = $this->handleTtdUpload($request, $laporan->ttd_image_path);

        try {
            DB::transaction(function () use ($laporan, $validated, $user, $targetBulan, $targetPeriode, $ttdImagePath) {
                $payload = [
                    'nama_kegiatan'      => $validated['nama_kegiatan'],
                    'uraian'             => $validated['uraian'],
                    'link_bukti'         => $validated['link_bukti'] ?? null,
                    'kendala'            => $validated['kendala'] ?? null,
                    'dukungan'           => $validated['dukungan'] ?? null,
                    'tindak_lanjut'      => $validated['tindak_lanjut'] ?? null,
                    'nama_penandatangan' => $validated['nama_penandatangan'],
                    'id_periode'         => $targetPeriode,
                    'bulan_laporan'      => $targetBulan,
                    'ttd_image_path'     => $ttdImagePath,
                ];

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
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategoriUnit:id,nama_kategori',
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
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategoriUnit:id,nama_kategori',
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
            if ($laporan->ttd_image_path && Storage::disk('public')->exists($laporan->ttd_image_path)) {
                Storage::disk('public')->delete($laporan->ttd_image_path);
            }

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

    /**
     * GET /api/laporan-kinerja/{id}/pdf
     * Mencetak dan mengunduh dokumen resmi Laporan Kinerja Unit dalam format PDF.
     * Fitur:
     * - Auto-Date Bahasa Indonesia (contoh: "12 November 2026").
     * - Rendering Tanda Tangan Digital (Base64 Data URI) tanpa isu resolusi jaringan.
     * - Proteksi IDOR: Hanya Admin atau Operator pemilik unit kerja terkait yang diizinkan mengunduh.
     */
    public function cetakPdf(int $id): Response
    {
        // 1. Fetch data laporan beserta relasi unitKerja dan periodeAkademik
        $laporan = LaporanKinerja::with([
            'unitKerja.kategoriUnit',
            'periodeAkademik',
        ])->findOrFail($id);

        // 2. Keamanan Otorisasi (IDOR Protection) yang ringan & ketat
        $user = Auth::user();
        if ($user->role !== 'admin' && (int) $laporan->id_unit_kerja !== (int) $user->id_unit_kerja) {
            abort(403, 'Anda tidak memiliki akses untuk mengunduh dokumen ini.');
        }

        // Format tanggal Bahasa Indonesia otomatis (Auto-date)
        $bulanIndo = [
            1  => 'Januari', 2  => 'Februari', 3  => 'Maret', 4  => 'April',
            5  => 'Mei', 6  => 'Juni', 7  => 'Juli', 8  => 'Agustus',
            9  => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember',
        ];
        $dateObj      = $laporan->created_at ?? now();
        $tanggalCetak = $dateObj->format('d') . ' ' . ($bulanIndo[(int) $dateObj->format('n')] ?? $dateObj->format('F')) . ' ' . $dateObj->format('Y');

        // Render TTD jika tersedia menjadi Base64 Data URI agar 100% aman di DOMPDF
        $ttdBase64 = null;
        if (! empty($laporan->ttd_image_path)) {
            $storagePath = storage_path('app/public/' . $laporan->ttd_image_path);
            $publicPath  = public_path('storage/' . $laporan->ttd_image_path);
            $filePath    = file_exists($storagePath) ? $storagePath : (file_exists($publicPath) ? $publicPath : null);

            if ($filePath && is_readable($filePath)) {
                $mimeType = @mime_content_type($filePath) ?: 'image/png';
                $binary   = @file_get_contents($filePath);
                if ($binary !== false) {
                    $ttdBase64 = 'data:' . $mimeType . ';base64,' . base64_encode($binary);
                }
            }
        }

        // Bersihkan karakter ilegal sistem file dari nama file attachment
        $namaUnitClean = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $laporan->unitKerja->nama_unit ?? 'Unit_Kerja');

        // 3. Rendering PDF & paksa response attachment download
        return Pdf::loadView('pdf.laporan-kinerja', [
            'laporan'      => $laporan,
            'tanggalCetak' => $tanggalCetak,
            'ttdBase64'    => $ttdBase64,
        ])
            ->setPaper('a4', 'portrait')
            ->download('Laporan_Kinerja_' . $namaUnitClean . '.pdf');
    }

    /**
     * GET /api/laporan-kinerja/export/excel
     * Mengekspor rekapitulasi seluruh Laporan Kinerja Unit ke dalam file Excel (.xlsx).
     * Keamanan Otorisasi: Hanya role 'admin' yang diizinkan mengakses endpoint ini.
     */
    public function exportExcel(Request $request): Response
    {
        if (Auth::user()?->role !== 'admin') {
            abort(403, 'Forbidden. Hanya Admin yang diizinkan mengunduh rekapitulasi Excel.');
        }

        $idPeriode    = $request->filled('id_periode') ? (int) $request->input('id_periode') : null;
        $kategoriUnit = $request->filled('kategori_unit') ? (string) $request->input('kategori_unit') : null;

        return Excel::download(
            new LaporanKinerjaExport($idPeriode, $kategoriUnit),
            'Rekap_Kinerja_Unit.xlsx'
        );
    }

    /**
     * Helper untuk memproses upload tanda tangan digital.
     * Menerima UploadedFile atau Base64 String dari Canvas HTML5.
     * Menyimpan berkas ke disk 'public' direktori 'ttd' dan mengembalikan relative path-nya.
     */
    protected function handleTtdUpload(Request $request, ?string $currentPath = null): ?string
    {
        // 1. Upload file via multipart/form-data
        $file = $request->file('ttd_image') ?? $request->file('ttd') ?? $request->file('tanda_tangan');
        if ($file && $file->isValid()) {
            if ($currentPath && Storage::disk('public')->exists($currentPath)) {
                Storage::disk('public')->delete($currentPath);
            }

            $filename = 'ttd_' . Str::random(24) . '.' . $file->getClientOriginalExtension();
            return $file->storeAs('ttd', $filename, 'public');
        }

        // 2. String Base64 dari frontend canvas
        $base64Data = $request->input('ttd_base64')
            ?? $request->input('ttd_image')
            ?? $request->input('ttd')
            ?? $request->input('tanda_tangan');

        if (is_string($base64Data) && str_starts_with($base64Data, 'data:image/')) {
            if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
                $raw = substr($base64Data, strpos($base64Data, ',') + 1);
                $ext = strtolower($type[1]);
                if ($ext === 'jpeg') {
                    $ext = 'jpg';
                }

                $decoded = base64_decode($raw);
                if ($decoded !== false) {
                    if ($currentPath && Storage::disk('public')->exists($currentPath)) {
                        Storage::disk('public')->delete($currentPath);
                    }

                    $filename = 'ttd_' . Str::random(24) . '.' . $ext;
                    $relPath  = 'ttd/' . $filename;
                    Storage::disk('public')->put($relPath, $decoded);

                    return $relPath;
                }
            }
        }

        // 3. String path yang sudah ada
        if ($request->filled('ttd_image_path') && is_string($request->input('ttd_image_path'))) {
            return $request->input('ttd_image_path');
        }

        return $currentPath;
    }
}
