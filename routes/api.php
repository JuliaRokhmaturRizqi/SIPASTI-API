<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DokumenPelaporanController;
use App\Http\Controllers\Api\FakultasAccountController;
use App\Http\Controllers\Api\FakultasController;
use App\Http\Controllers\Api\KategoriUnitController;
use App\Http\Controllers\Api\LaporanKinerjaController;
use App\Http\Controllers\Api\PeriodeController;
use App\Http\Controllers\Api\ProdiAccountController;
use App\Http\Controllers\Api\ProdiController;
use App\Http\Controllers\Api\UnitAccountController;
use App\Http\Controllers\Api\UnitKerjaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/
Route::post('/login', [AuthController::class, 'login'])->name('api.login');

/*
|--------------------------------------------------------------------------
| Protected Routes (Sanctum Authenticated)
|--------------------------------------------------------------------------
*/
Route::middleware('auth:sanctum')->group(function () {

    // Endpoint umum untuk semua user yang sudah login (admin & user)
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data'   => $request->user()->load(['fakultas.prodi:id,id_fakultas,nama_prodi,periode_mulai_aktif']),
        ]);
    });

    // Get Data Dokumen & Secure Download (Diakses oleh admin maupun user dengan filter RBAC/Anti-IDOR)
    Route::get('/dokumen', [DokumenPelaporanController::class, 'index'])->name('api.dokumen.index');
    Route::get('/dokumen/{id}/download', [DokumenPelaporanController::class, 'download'])->name('api.dokumen.download');

    // Multiple Attachments (Lampiran Pendukung: Ijazah, Transkrip, dll.)
    Route::post('/dokumen/{id}/lampiran', [DokumenPelaporanController::class, 'uploadLampiran'])->name('api.dokumen.lampiran.upload');
    Route::delete('/dokumen/lampiran/{id_lampiran}', [DokumenPelaporanController::class, 'destroyLampiran'])->name('api.dokumen.lampiran.destroy');
    Route::get('/dokumen/{id}/lampiran/zip', [DokumenPelaporanController::class, 'downloadLampiranZip'])->name('api.dokumen.lampiran.zip');

    // Daftar Periode Akademik, Fakultas, dan Program Studi (Dapat dibaca oleh Admin maupun Operator Fakultas)
    Route::get('/periode', [PeriodeController::class, 'index'])->name('api.periode.index');
    Route::get('/fakultas', [FakultasController::class, 'index'])->name('api.fakultas.index');
    Route::get('/prodi', [ProdiController::class, 'index'])->name('api.prodi.index');
    Route::get('/prodi/{id}', [ProdiController::class, 'show'])->name('api.prodi.show');

    /*
    |--------------------------------------------------------------------------
    | Admin Routes (Role: admin)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        // Manajemen Periode Akademik
        Route::post('/periode', [PeriodeController::class, 'store'])->name('api.periode.store');
        Route::put('/periode/{id}', [PeriodeController::class, 'update'])->name('api.periode.update');
        Route::patch('/periode/{id}/toggle-active', [PeriodeController::class, 'toggleActive'])->name('api.periode.toggle-active');
        Route::patch('/periode/{id}/toggle-status', [PeriodeController::class, 'toggleActive'])->name('api.periode.toggle-status');
        Route::delete('/periode/{id}', [PeriodeController::class, 'destroy'])->name('api.periode.destroy');

        // Manajemen Master Data Fakultas
        Route::post('/fakultas', [FakultasController::class, 'store'])->name('api.fakultas.store');
        Route::put('/fakultas/{id}', [FakultasController::class, 'update'])->name('api.fakultas.update');
        Route::delete('/fakultas/{id}', [FakultasController::class, 'destroy'])->name('api.fakultas.destroy');
        Route::post('/fakultas/{id}/akun', [FakultasController::class, 'createAkun'])->name('api.fakultas.createAkun');

        // Manajemen Akun Operator Fakultas (Berbasis Fakultas)
        Route::get('/fakultas/akun', [FakultasAccountController::class, 'index'])->name('api.fakultas.akun');
        Route::get('/akun-fakultas', [FakultasAccountController::class, 'index'])->name('api.akun-fakultas.index');
        Route::post('/akun-fakultas', [FakultasAccountController::class, 'store'])->name('api.akun-fakultas.store');
        Route::put('/akun-fakultas/{id}', [FakultasAccountController::class, 'update'])->name('api.akun-fakultas.update');
        Route::patch('/akun-fakultas/{id}/toggle-status', [FakultasAccountController::class, 'toggleStatus'])->name('api.akun-fakultas.toggle-status');
        Route::delete('/akun-fakultas/{id}', [FakultasAccountController::class, 'destroy'])->name('api.akun-fakultas.destroy');

        // Manajemen Master Data Program Studi
        Route::post('/prodi', [ProdiController::class, 'store'])->name('api.prodi.store');
        Route::put('/prodi/{id}', [ProdiController::class, 'update'])->name('api.prodi.update');
        Route::delete('/prodi/{id}', [ProdiController::class, 'destroy'])->name('api.prodi.destroy');
        Route::post('/prodi/{id}/akun', [ProdiController::class, 'createAkun'])->name('api.prodi.createAkun');

        // Manajemen Kredensial / Akun Operator Prodi (Hybrid)
        Route::get('/akun-prodi', [ProdiAccountController::class, 'index'])->name('api.akun-prodi.index');
        Route::post('/akun-prodi', [ProdiAccountController::class, 'store'])->name('api.akun-prodi.store');
        Route::put('/akun-prodi/{id}', [ProdiAccountController::class, 'update'])->name('api.akun-prodi.update');
        Route::patch('/akun-prodi/{id}/toggle-status', [ProdiAccountController::class, 'toggleStatus'])->name('api.akun-prodi.toggle-status');
        Route::delete('/akun-prodi/{id}', [ProdiAccountController::class, 'destroy'])->name('api.akun-prodi.destroy');

        // Manajemen Master Data Kategori Unit Kerja (Khusus Admin)
        Route::apiResource('/kategori-unit', KategoriUnitController::class);

        // Manajemen Master Data Unit Kerja (Khusus Admin)
        Route::apiResource('/unit-kerja', UnitKerjaController::class);

        // Manajemen Akun Operator Unit Kerja (Khusus Admin)
        Route::get('/unit/akun', [UnitAccountController::class, 'index'])->name('api.unit.akun');
        Route::get('/akun-unit', [UnitAccountController::class, 'index'])->name('api.akun-unit.index');
        Route::post('/akun-unit', [UnitAccountController::class, 'store'])->name('api.akun-unit.store');
        Route::put('/akun-unit/{id}', [UnitAccountController::class, 'update'])->name('api.akun-unit.update');
        Route::patch('/akun-unit/{id}/toggle-status', [UnitAccountController::class, 'toggleStatus'])->name('api.akun-unit.toggle-status');
        Route::delete('/akun-unit/{id}', [UnitAccountController::class, 'destroy'])->name('api.akun-unit.destroy');

        // Validasi Dokumen Pelaporan (Khusus Admin)
        Route::post('/dokumen/{id}/validasi', [DokumenPelaporanController::class, 'validasi'])->name('api.dokumen.validasi');

        // Validasi & Export Excel Laporan Kinerja Unit (Khusus Admin)
        Route::get('/laporan-kinerja/export/excel', [LaporanKinerjaController::class, 'exportExcel'])->name('api.laporan-kinerja.export.excel');
        Route::match(['post', 'patch'], '/laporan-kinerja/{id}/validasi-admin', [LaporanKinerjaController::class, 'validasiAdmin'])->whereNumber('id')->name('api.laporan-kinerja.validasi-admin');
        Route::match(['post', 'patch'], '/laporan-kinerja/{id}/validasi', [LaporanKinerjaController::class, 'validasi'])->whereNumber('id')->name('api.laporan-kinerja.validasi');
    });

    /*
    |--------------------------------------------------------------------------
    | Modul Kinerja Routes (Role: admin, biro, pusat, lab)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin,biro,pusat,lab')->group(function () {
        Route::get('/laporan-kinerja', [LaporanKinerjaController::class, 'index'])->name('api.laporan-kinerja.index');
        Route::post('/laporan-kinerja', [LaporanKinerjaController::class, 'store'])->name('api.laporan-kinerja.store');
        Route::post('/laporan-kinerja/{id}/approve', [LaporanKinerjaController::class, 'approve'])->whereNumber('id')->name('api.laporan-kinerja.approve');
        Route::get('/laporan-kinerja/{id}', [LaporanKinerjaController::class, 'show'])->whereNumber('id')->name('api.laporan-kinerja.show');
        Route::get('/laporan-kinerja/{id}/pdf', [LaporanKinerjaController::class, 'cetakPdf'])->whereNumber('id')->name('api.laporan-kinerja.pdf');
        Route::match(['put', 'patch', 'post'], '/laporan-kinerja/{id}', [LaporanKinerjaController::class, 'update'])->whereNumber('id')->name('api.laporan-kinerja.update');
        Route::delete('/laporan-kinerja/{id}', [LaporanKinerjaController::class, 'destroy'])->whereNumber('id')->name('api.laporan-kinerja.destroy');
    });

    /*
    |--------------------------------------------------------------------------
    | User / Prodi Routes (Role: user)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:user')->group(function () {
        // Upload Dokumen Pelaporan (Khusus User Prodi)
        Route::post('/dokumen/upload', [DokumenPelaporanController::class, 'upload'])->name('api.dokumen.upload');
    });
});
