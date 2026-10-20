<?php

use App\Http\Controllers\Api\AuthController;
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

    // Endpoint umum untuk semua user yang sudah login
    Route::post('/logout', [AuthController::class, 'logout'])->name('api.logout');
    Route::get('/me', function (Request $request) {
        return response()->json([
            'status' => 'success',
            'data'   => $request->user()->load('prodi:id,nama_prodi'),
        ]);
    });

    /*
    |--------------------------------------------------------------------------
    | Admin Routes (Role: admin)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Manajemen Periode Akademik (Dummy)
        Route::post('/periode-akademik', function (Request $request) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Endpoint pembuatan periode akademik (Admin Only).',
            ]);
        });

        // Validasi / Review Dokumen Pelaporan Prodi (Dummy)
        Route::patch('/dokumen-pelaporan/{id}/validasi', function (Request $request, int $id) {
            return response()->json([
                'status'  => 'success',
                'message' => "Endpoint validasi dokumen ID #{$id} (Admin Only).",
            ]);
        });
    });

    /*
    |--------------------------------------------------------------------------
    | User / Prodi Routes (Role: user)
    |--------------------------------------------------------------------------
    */
    Route::middleware('role:user')->prefix('prodi')->group(function () {
        // Melihat daftar dokumen milik prodi user yang sedang login (Dummy)
        Route::get('/dokumen-pelaporan', function (Request $request) {
            return response()->json([
                'status'   => 'success',
                'id_prodi' => $request->user()->id_prodi,
                'message'  => 'Daftar dokumen pelaporan prodi (User Only).',
            ]);
        });

        // Upload Dokumen Pelaporan berdasarkan Tahap 1-4 (Dummy)
        Route::post('/dokumen-pelaporan/upload', function (Request $request) {
            return response()->json([
                'status'  => 'success',
                'message' => 'Endpoint unggah dokumen pelaporan prodi (User Only).',
            ]);
        });
    });
});
