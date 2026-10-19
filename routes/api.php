<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KamarController;
use App\Http\Controllers\PenghuniController;
use App\Http\Controllers\PenghunianController;

// API Kamar
Route::get('/kamar', [KamarController::class, 'index']);
Route::post('/kamar', [KamarController::class, 'store']);
Route::put('/kamar/{id}', [KamarController::class, 'update']);

// API Penghuni
Route::get('/penghuni', [PenghuniController::class, 'index']);
Route::post('/penghuni', [PenghuniController::class, 'store']);
Route::get('/penghuni/{id}', [PenghuniController::class, 'show']);
Route::put('/penghuni/{id}', [PenghuniController::class, 'update']);
Route::delete('/penghuni/{id}', [PenghuniController::class, 'destroy']);

// API Penghunian
Route::get('/penghunian', [PenghunianController::class, 'index']);
Route::post('/penghunian', [PenghunianController::class, 'store']);
Route::get('/penghunian/{id}', [PenghunianController::class, 'show']);

Route::put('/penghunian/{id}', [PenghunianController::class, 'update']);
Route::put('/penghunian/{id}/penyewa-utama', [PenghunianController::class, 'setPenyewaUtama']);
Route::post('/penghunian/{id}/dp', [PenghunianController::class, 'catatDp']);
<<<<<<< HEAD
Route::post('/penghunian/dengan-penghuni',[PenghunianController::class, 'storeDenganPenghuni']);

// API Perhitungan DP dan Tenggat Pelunasan
Route::middleware(['auth:sanctum', 'pemilik'])->group(function () {
    Route::post('/penghunian/{id}/hitung', [
        PenghunianController::class,
        'hitung',
    ]);

    Route::put('/penghunian/{id}/tenggat', [
        PenghunianController::class,
        'ubahTenggat',
    ]);

    Route::put('/penghunian/{id}/tenggat-berikutnya', [
        PenghunianController::class,
        'tenggatBerikutnya',
    ]);
});
=======
Route::post('/penghunian/dengan-penghuni',[PenghunianController::class, 'storeDenganPenghuni']);
>>>>>>> c73f6c62f2ec89ddadc720a849279c26fb069d40
