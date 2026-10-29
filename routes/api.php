<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DokumenPelaporanController;
use App\Http\Controllers\Api\FakultasController;
use App\Http\Controllers\Api\PeriodeController;
use App\Http\Controllers\Api\ProdiController;
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
            'data'   => $request->user()->load('prodi.fakultas:id,singkatan,nama_fakultas'),
        ]);
    });

    // Get Data Dokumen & Secure Download (Diakses oleh admin maupun user dengan filter RBAC/Anti-IDOR)
    Route::get('/dokumen', [DokumenPelaporanController::class, 'index'])->name('api.dokumen.index');
    Route::get('/dokumen/{id}/download', [DokumenPelaporanController::class, 'download'])->name('api.dokumen.download');

    /*
    |--------------------------------------------------------------------------
    | Admin Routes (Role: admin)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->group(function () {
        // Manajemen Periode Akademik
        Route::get('/periode', [PeriodeController::class, 'index'])->name('api.periode.index');
        Route::post('/periode', [PeriodeController::class, 'store'])->name('api.periode.store');
        Route::put('/periode/{id}', [PeriodeController::class, 'update'])->name('api.periode.update');
        Route::delete('/periode/{id}', [PeriodeController::class, 'destroy'])->name('api.periode.destroy');

        // Manajemen Master Data Fakultas
        Route::get('/fakultas', [FakultasController::class, 'index'])->name('api.fakultas.index');
        Route::post('/fakultas', [FakultasController::class, 'store'])->name('api.fakultas.store');
        Route::put('/fakultas/{id}', [FakultasController::class, 'update'])->name('api.fakultas.update');
        Route::delete('/fakultas/{id}', [FakultasController::class, 'destroy'])->name('api.fakultas.destroy');

        // Manajemen Master Data Program Studi (CRUD + Generate Akun Operator)
        Route::get('/prodi', [ProdiController::class, 'index'])->name('api.prodi.index');
        Route::get('/prodi/{id}', [ProdiController::class, 'show'])->name('api.prodi.show');
        Route::post('/prodi', [ProdiController::class, 'store'])->name('api.prodi.store');
        Route::put('/prodi/{id}', [ProdiController::class, 'update'])->name('api.prodi.update');
        Route::delete('/prodi/{id}', [ProdiController::class, 'destroy'])->name('api.prodi.destroy');
        Route::post('/prodi/{id}/akun', [ProdiController::class, 'createAkun'])->name('api.prodi.createAkun');

        // Validasi Dokumen Pelaporan (Khusus Admin)
        Route::post('/dokumen/{id}/validasi', [DokumenPelaporanController::class, 'validasi'])->name('api.dokumen.validasi');
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
