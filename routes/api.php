<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KamarController;
use App\Http\Controllers\PenghuniController;
use App\Http\Controllers\PenghunianController;

// API Kamar
Route::get('/kamar', [KamarController::class, 'index']);
Route::post('/kamar', [KamarController::class, 'store']);
Route::get('/kamar/{id}', [KamarController::class, 'show']); 
Route::put('/kamar/{id}', [KamarController::class, 'update']);
Route::delete('/kamar/{id}', [KamarController::class, 'destroy']);

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
Route::post('/penghunian/dengan-penghuni',[PenghunianController::class, 'storeDenganPenghuni']);
    
    // 1. Skenario: Menghitung DP Minimum & Tenggat (Bulanan & Tahunan)
    // Serta menangani validasi (Tanggal masuk kosong, Harga kosong, Skema belum dipilih)
    Route::post('/penghunian/{id}/hitung-dp', [PenghunianController::class, 'hitung']);

    // 2. Skenario: Pemilik menyesuaikan tenggat pelunasan
    Route::put('/penghunian/{id}/tenggat', [PenghunianController::class, 'ubahTenggat']);

    // 3. Skenario: Menghitung tenggat bulan berikutnya
    Route::post('/penghunian/tenggat-berikutnya', [PenghunianController::class, 'tenggatBerikutnya']);
// API Utama Pengujian (Dilindungi Middleware Pemilik & Sanctum)
Route::middleware(['auth:sanctum', 'pemilik'])->group(function () {
});
