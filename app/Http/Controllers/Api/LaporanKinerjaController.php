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
            'id_periode'     => ['nullable', 'integer', 'exists:periode_akademik,id'],
            'id_unit_kerja'  => ['nullable', 'integer', 'exists:unit_kerja,id'],
            'kategori_unit'  => ['nullable', 'string'],
            'bulan_laporan'  => ['nullable', 'string', 'max:50'],
            'status_laporan' => ['nullable', 'string', Rule::in(LaporanKinerja::WORKFLOW_STATUS_LIST)],
            'status'         => ['nullable', 'string', Rule::in(LaporanKinerja::STATUS_LIST)],
        ]);

        try {
            $user = Auth::user();

            $query = LaporanKinerja::query()
                ->with([
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategori:id,nama_kategori',
                    'periodeAkademik:id,nama_periode,status_aktif,status_pelaporan,is_default',
                ]);

            // Tenant Isolation (Proteksi IDOR)
            if ($user->role === 'admin') {
                if ($request->filled('id_unit_kerja')) {
                    $query->where('id_unit_kerja', (int) $request->input('id_unit_kerja'));
                }

                if ($request->filled('kategori_unit')) {
                    $kategori = $request->input('kategori_unit');
                    $query->whereHas('unitKerja.kategori', function ($q) use ($kategori) {
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

            if ($request->filled('status_laporan')) {
                $query->where('status_laporan', $request->input('status_laporan'));
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
     * Membuat laporan kinerja baru (Khusus Staf Unit Kerja).
     * Aturan:
     * - Otorisasi Jabatan: Hanya jabatan 'staf' yang diizinkan membuat laporan.
     * - Set id_unit_kerja otomatis dari Auth::user()->id_unit_kerja.
     * - Set status_laporan otomatis menjadi 'menunggu_pimpinan'.
     * - Simpan file gambar TTD staf ke ttd_penyusun_path.
     * - Validasi duplikat: 1 Bulan = Maksimal 1 Laporan per Unit Kerja per Periode.
     */
    public function store(StoreLaporanKinerjaRequest $request): JsonResponse
    {
        if (Auth::user()->jabatan !== 'staf') {
            abort(403, 'Hanya staf yang dapat membuat laporan.');
        }

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

        // Tangani Upload Tanda Tangan Digital Staf Penyusun (ttd_penyusun_path)
        $ttdPenyusunPath = $this->handleTtdUpload($request, null, 'penyusun');

        try {
            $laporan = DB::transaction(function () use ($validatedData, $idUnitKerja, $bulanLaporan, $ttdPenyusunPath) {
                $payload = [
                    'id_unit_kerja'      => (int) $idUnitKerja,
                    'id_periode'         => (int) $validatedData['id_periode'],
                    'bulan_laporan'      => $bulanLaporan,
                    'status_laporan'     => LaporanKinerja::STATUS_MENUNGGU_PIMPINAN,
                    'status'             => LaporanKinerja::STATUS_REVIEW,

                    // Kolom Dinamis JSON & Teks
                    'identitas_laporan'  => $validatedData['identitas_laporan'] ?? null,
                    'pembahasan_json'    => $validatedData['pembahasan_json'] ?? null,
                    'kendala_json'       => $validatedData['kendala_json'] ?? null,
                    'dukungan_json'      => $validatedData['dukungan_json'] ?? null,
                    'tindak_lanjut_json' => $validatedData['tindak_lanjut_json'] ?? null,
                    'kesimpulan'         => $validatedData['kesimpulan'] ?? null,
                    'rekomendasi'        => $validatedData['rekomendasi'] ?? null,

                    // Kolom Tanda Tangan Penyusun
                    'ttd_penyusun_path'  => $ttdPenyusunPath,

                    // Kolom Legacy / Kompatibilitas
                    'nama_kegiatan'      => $validatedData['nama_kegiatan'] ?? null,
                    'uraian'             => $validatedData['uraian'] ?? null,
                    'link_bukti'         => $validatedData['link_bukti'] ?? null,
                    'kendala'            => $validatedData['kendala'] ?? null,
                    'dukungan'           => $validatedData['dukungan'] ?? null,
                    'tindak_lanjut'      => $validatedData['tindak_lanjut'] ?? null,
                    'nama_penandatangan' => $validatedData['nama_penandatangan']
                        ?? ($validatedData['identitas_laporan']['penanggung_jawab'] ?? null),
                    'catatan_revisi'     => null,
                ];

                return LaporanKinerja::create($payload);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan kinerja berhasil disimpan dan menunggu persetujuan pimpinan.',
                'data'    => $laporan->load([
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategori:id,nama_kategori',
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
     * POST /api/laporan-kinerja/{id}/approve
     * Validasi / Persetujuan Laporan Kinerja oleh Pimpinan Unit Kerja.
     * Keamanan:
     * 1. Otorisasi Jabatan: Hanya jabatan 'pimpinan' yang diizinkan.
     * 2. Anti-IDOR: Hanya boleh memvalidasi laporan milik id_unit_kerja pimpinan itu sendiri.
     * 3. Validasi Status: Laporan wajib berstatus 'menunggu_pimpinan'.
     */
    public function approve(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        // Keamanan 1 (Jabatan)
        if (! $user || $user->jabatan !== 'pimpinan') {
            abort(403, 'Hanya pimpinan yang dapat memvalidasi laporan.');
        }

        // Keamanan 2 (Anti-IDOR)
        $laporan = LaporanKinerja::findOrFail($id);

        if ((int) $laporan->id_unit_kerja !== (int) $user->id_unit_kerja) {
            abort(403, 'Anda tidak berhak memvalidasi laporan unit lain.');
        }

        // Keamanan 3 (Status Workflow)
        if ($laporan->status_laporan !== LaporanKinerja::STATUS_MENUNGGU_PIMPINAN) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Laporan ini tidak dalam status menunggu persetujuan pimpinan.',
            ], 422);
        }

        $validated = $request->validate([
            'aksi'              => ['required', 'string', Rule::in(['setuju', 'tolak'])],
            'catatan_revisi'    => [
                Rule::requiredIf(fn () => $request->input('aksi') === 'tolak'),
                'nullable',
                'string',
                'max:2000',
            ],
            'ttd_pimpinan'      => ['nullable'],
            'ttd_pimpinan_path' => ['nullable', 'string', 'max:255'],
            'ttd_image'         => ['nullable'],
            'ttd'               => ['nullable'],
            'tanda_tangan'      => ['nullable'],
            'ttd_base64'        => ['nullable', 'string'],
        ], [
            'aksi.required'           => 'Aksi persetujuan wajib dipilih (setuju atau tolak).',
            'aksi.in'                 => 'Aksi persetujuan harus bernilai setuju atau tolak.',
            'catatan_revisi.required' => 'Catatan revisi wajib diisi apabila laporan ditolak.',
        ]);

        try {
            DB::transaction(function () use ($request, $laporan, $validated) {
                if ($validated['aksi'] === 'setuju') {
                    $ttdPimpinanPath = $this->handleTtdUpload($request, $laporan->ttd_pimpinan_path, 'pimpinan');
                    if ($ttdPimpinanPath) {
                        $laporan->ttd_pimpinan_path = $ttdPimpinanPath;
                    }

                    $laporan->status_laporan = LaporanKinerja::STATUS_MENUNGGU_ADMIN;
                    $laporan->status         = LaporanKinerja::STATUS_REVIEW;
                    $laporan->catatan_revisi = null;
                } else {
                    $cleanCatatan = isset($validated['catatan_revisi'])
                        ? trim(strip_tags($validated['catatan_revisi']))
                        : null;

                    $laporan->status_laporan = LaporanKinerja::STATUS_REVISI_PIMPINAN;
                    $laporan->status         = LaporanKinerja::STATUS_REVISI;
                    $laporan->catatan_revisi = $cleanCatatan;
                }

                $laporan->save();
            });

            $pesan = $validated['aksi'] === 'setuju'
                ? 'Laporan kinerja telah disetujui pimpinan dan diteruskan ke Admin.'
                : 'Laporan kinerja dikembalikan ke staf untuk direvisi.';

            return response()->json([
                'status'  => 'success',
                'message' => $pesan,
                'data'    => $laporan->fresh([
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategori:id,nama_kategori',
                    'periodeAkademik:id,nama_periode,status_aktif,status_pelaporan,is_default',
                ]),
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Terjadi kesalahan saat memproses persetujuan laporan kinerja.',
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
            'unitKerja.kategori:id,nama_kategori',
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

        $ttdPenyusunPath = $this->handleTtdUpload($request, $laporan->ttd_penyusun_path, 'penyusun');

        try {
            DB::transaction(function () use ($laporan, $validated, $user, $targetBulan, $targetPeriode, $ttdPenyusunPath) {
                $payload = [
                    'id_periode'         => $targetPeriode,
                    'bulan_laporan'      => $targetBulan,
                    'ttd_penyusun_path'  => $ttdPenyusunPath,
                ];

                // Update kolom JSON & teks pendek apabila dikirim
                $dynamicFields = [
                    'identitas_laporan',
                    'pembahasan_json',
                    'kendala_json',
                    'dukungan_json',
                    'tindak_lanjut_json',
                    'kesimpulan',
                    'rekomendasi',
                    'nama_kegiatan',
                    'uraian',
                    'link_bukti',
                    'kendala',
                    'dukungan',
                    'tindak_lanjut',
                    'nama_penandatangan',
                ];

                foreach ($dynamicFields as $field) {
                    if (array_key_exists($field, $validated)) {
                        $payload[$field] = $validated[$field];
                    }
                }

                // Jika diedit oleh operator unit (misal perbaikan dari Revisi), kembalikan status ke menunggu_pimpinan / Review
                if ($user->role !== 'admin') {
                    $payload['status_laporan'] = LaporanKinerja::STATUS_MENUNGGU_PIMPINAN;
                    $payload['status']         = LaporanKinerja::STATUS_REVIEW;
                    $payload['catatan_revisi'] = null;
                    $payload['catatan_admin']  = null;
                } else {
                    if (! empty($validated['status_laporan'])) {
                        $payload['status_laporan'] = $validated['status_laporan'];
                    }
                    if (! empty($validated['status'])) {
                        $payload['status'] = $validated['status'];
                    }
                }

                $laporan->update($payload);
            });

            return response()->json([
                'status'  => 'success',
                'message' => 'Laporan kinerja berhasil diperbarui.',
                'data'    => $laporan->fresh([
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategori:id,nama_kategori',
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
     * POST/PATCH /api/laporan-kinerja/{id}/validasi-admin
     * Endpoint khusus Admin untuk memvalidasi laporan kinerja pada alur persetujuan bertingkat.
     * Keamanan:
     * 1. Hanya role 'admin' yang diizinkan.
     * 2. Laporan wajib berstatus 'menunggu_admin' (telah disetujui Pimpinan Unit).
     */
    public function validasiAdmin(Request $request, $id): JsonResponse
    {
        $user = Auth::user();

        // 1. Keamanan Role Admin
        if (! $user || $user->role !== 'admin') {
            abort(403, 'Hanya Admin yang dapat memvalidasi laporan.');
        }

        $laporan = LaporanKinerja::findOrFail($id);

        // 2. Pastikan laporan berstatus 'menunggu_admin'
        if ($laporan->status_laporan !== LaporanKinerja::STATUS_MENUNGGU_ADMIN) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Laporan ini belum dapat divalidasi oleh Admin karena statusnya bukan menunggu_admin.',
            ], 422);
        }

        $validated = $request->validate([
            'status'         => ['required', 'string', Rule::in(['selesai', 'revisi_admin', 'tolak', 'Selesai', 'Revisi'])],
            'catatan_admin'  => [
                Rule::requiredIf(fn () => in_array(strtolower((string) $request->input('status')), ['revisi_admin', 'tolak', 'revisi'], true) && ! $request->filled('catatan_revisi')),
                'nullable',
                'string',
                'max:2000',
            ],
            'catatan_revisi' => ['nullable', 'string', 'max:2000'],
        ], [
            'status.required'        => 'Status validasi Admin wajib dipilih.',
            'status.in'              => 'Status validasi Admin harus bernilai selesai atau revisi_admin.',
            'catatan_admin.required' => 'Catatan revisi Admin wajib diisi apabila laporan ditolak.',
        ]);

        $statusNormalized = strtolower($validated['status']);

        try {
            DB::transaction(function () use ($laporan, $validated, $statusNormalized) {
                if ($statusNormalized === 'selesai') {
                    $laporan->status_laporan = LaporanKinerja::STATUS_SELESAI;
                    $laporan->status         = LaporanKinerja::STATUS_SELESAI;
                    $laporan->catatan_admin  = null;
                    $laporan->catatan_revisi = null;
                } else {
                    $rawCatatan   = $validated['catatan_admin'] ?? $validated['catatan_revisi'] ?? null;
                    $cleanCatatan = $rawCatatan !== null ? trim(strip_tags($rawCatatan)) : null;

                    $laporan->status_laporan = LaporanKinerja::STATUS_REVISI_ADMIN;
                    $laporan->status         = LaporanKinerja::STATUS_REVISI;
                    $laporan->catatan_admin  = $cleanCatatan;
                    $laporan->catatan_revisi = $cleanCatatan;
                }

                $laporan->save();
            });

            $pesan = $statusNormalized === 'selesai'
                ? 'Laporan kinerja telah disahkan oleh Admin dengan status selesai.'
                : 'Laporan kinerja dikembalikan oleh Admin untuk direvisi.';

            return response()->json([
                'status'  => 'success',
                'message' => $pesan,
                'data'    => $laporan->fresh([
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategori:id,nama_kategori',
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

        $allowedStatuses = array_merge(LaporanKinerja::STATUS_LIST, LaporanKinerja::WORKFLOW_STATUS_LIST);

        $validated = $request->validate([
            'status'         => ['required', 'string', Rule::in($allowedStatuses)],
            'catatan_revisi' => [
                Rule::requiredIf(fn () => in_array($request->input('status'), [
                    LaporanKinerja::STATUS_REVISI,
                    LaporanKinerja::STATUS_REVISI_ADMIN,
                    LaporanKinerja::STATUS_REVISI_PIMPINAN,
                ], true)),
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

        $isRevision = in_array($validated['status'], [
            LaporanKinerja::STATUS_REVISI,
            LaporanKinerja::STATUS_REVISI_ADMIN,
            LaporanKinerja::STATUS_REVISI_PIMPINAN,
        ], true);

        if ($isRevision && ($cleanCatatan === '' || $cleanCatatan === null)) {
            return response()->json([
                'message' => 'Catatan revisi tidak boleh kosong atau hanya berisi tag HTML.',
                'errors'  => [
                    'catatan_revisi' => ['Catatan revisi wajib berisi teks instruksi perbaikan yang valid.'],
                ],
            ], 422);
        }

        try {
            $statusInput = $validated['status'];

            $legacyStatus = match ($statusInput) {
                LaporanKinerja::STATUS_SELESAI, 'Selesai' => 'Selesai',
                LaporanKinerja::STATUS_REVISI_ADMIN, LaporanKinerja::STATUS_REVISI_PIMPINAN, 'Revisi' => 'Revisi',
                default => 'Review',
            };

            $workflowStatus = match ($statusInput) {
                'Selesai', LaporanKinerja::STATUS_SELESAI => LaporanKinerja::STATUS_SELESAI,
                'Revisi', LaporanKinerja::STATUS_REVISI_ADMIN => LaporanKinerja::STATUS_REVISI_ADMIN,
                LaporanKinerja::STATUS_REVISI_PIMPINAN => LaporanKinerja::STATUS_REVISI_PIMPINAN,
                LaporanKinerja::STATUS_MENUNGGU_ADMIN => LaporanKinerja::STATUS_MENUNGGU_ADMIN,
                default => LaporanKinerja::STATUS_MENUNGGU_PIMPINAN,
            };

            $laporan->update([
                'status'         => $legacyStatus,
                'status_laporan' => $workflowStatus,
                'catatan_revisi' => $isRevision ? $cleanCatatan : null,
            ]);

            return response()->json([
                'status'  => 'success',
                'message' => "Status laporan kinerja berhasil diperbarui menjadi {$laporan->status}.",
                'data'    => $laporan->fresh([
                    'unitKerja:id,nama_unit,id_kategori',
                    'unitKerja.kategori:id,nama_kategori',
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
            if ($laporan->ttd_penyusun_path && Storage::disk('public')->exists($laporan->ttd_penyusun_path)) {
                Storage::disk('public')->delete($laporan->ttd_penyusun_path);
            }
            if ($laporan->ttd_pimpinan_path && Storage::disk('public')->exists($laporan->ttd_pimpinan_path)) {
                Storage::disk('public')->delete($laporan->ttd_pimpinan_path);
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
     */
    public function cetakPdf(int $id): Response
    {
        // 1. Fetch data laporan beserta relasi unitKerja dan periodeAkademik
        $laporan = LaporanKinerja::with([
            'unitKerja.kategori',
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

        // Render TTD Penyusun & Pimpinan menjadi Base64 Data URI agar 100% aman di DOMPDF
        $ttdPenyusunBase64 = $this->resolveTtdToBase64($laporan->ttd_penyusun_path);
        $ttdPimpinanBase64 = $this->resolveTtdToBase64($laporan->ttd_pimpinan_path);

        // Bersihkan karakter ilegal sistem file dari nama file attachment
        $namaUnitClean = preg_replace('/[\/\\\\:*?"<>|]+/', '_', $laporan->unitKerja->nama_unit ?? 'Unit_Kerja');

        // 3. Rendering PDF & paksa response attachment download
        return Pdf::loadView('pdf.laporan-kinerja', [
            'laporan'           => $laporan,
            'tanggalCetak'      => $tanggalCetak,
            'ttdBase64'         => $ttdPenyusunBase64,
            'ttdPenyusunBase64' => $ttdPenyusunBase64,
            'ttdPimpinanBase64' => $ttdPimpinanBase64,
        ])
            ->setPaper('a4', 'portrait')
            ->download('Laporan_Kinerja_' . $namaUnitClean . '.pdf');
    }

    /**
     * GET /api/laporan-kinerja/export/excel
     * Mengekspor rekapitulasi seluruh Laporan Kinerja Unit ke dalam file Excel (.xlsx).
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
     * Helper untuk memproses upload tanda tangan digital (Penyusun / Pimpinan).
     * Menerima UploadedFile atau Base64 String dari Canvas HTML5.
     * Menyimpan berkas ke disk 'public' direktori 'ttd' dan mengembalikan relative path-nya.
     */
    protected function handleTtdUpload(Request $request, ?string $currentPath = null, string $prefix = 'ttd'): ?string
    {
        // 1. Upload file via multipart/form-data
        $file = $request->file('ttd_penyusun')
            ?? $request->file('ttd_pimpinan')
            ?? $request->file('ttd_image')
            ?? $request->file('ttd')
            ?? $request->file('tanda_tangan');

        if ($file && $file->isValid()) {
            if ($currentPath && Storage::disk('public')->exists($currentPath)) {
                Storage::disk('public')->delete($currentPath);
            }

            $filename = "ttd_{$prefix}_" . Str::random(24) . '.' . $file->getClientOriginalExtension();
            return $file->storeAs('ttd', $filename, 'public');
        }

        // 2. String Base64 dari frontend canvas
        $base64Data = $request->input('ttd_penyusun')
            ?? $request->input('ttd_pimpinan')
            ?? $request->input('ttd_base64')
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

                    $filename = "ttd_{$prefix}_" . Str::random(24) . '.' . $ext;
                    $relPath  = 'ttd/' . $filename;
                    Storage::disk('public')->put($relPath, $decoded);

                    return $relPath;
                }
            }
        }

        // 3. String path yang sudah ada
        $existingInputPath = $request->input('ttd_penyusun_path')
            ?? $request->input('ttd_pimpinan_path')
            ?? $request->input('ttd_image_path');

        if (! empty($existingInputPath) && is_string($existingInputPath)) {
            return $existingInputPath;
        }

        return $currentPath;
    }

    /**
     * Konversi file tanda tangan di storage ke Base64 Data URI untuk DOMPDF.
     */
    protected function resolveTtdToBase64(?string $relativePath): ?string
    {
        if (empty($relativePath)) {
            return null;
        }

        $storagePath = storage_path('app/public/' . $relativePath);
        $publicPath  = public_path('storage/' . $relativePath);
        $filePath    = file_exists($storagePath) ? $storagePath : (file_exists($publicPath) ? $publicPath : null);

        if ($filePath && is_readable($filePath)) {
            $mimeType = @mime_content_type($filePath) ?: 'image/png';
            $binary   = @file_get_contents($filePath);
            if ($binary !== false) {
                return 'data:' . $mimeType . ';base64,' . base64_encode($binary);
            }
        }

        return null;
    }
}
