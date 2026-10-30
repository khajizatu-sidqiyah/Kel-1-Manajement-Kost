
<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\KosController;
use App\Http\Controllers\KamarController;
use App\Http\Controllers\PenghuniController;
use App\Http\Controllers\PenghunianController;
use App\Http\Controllers\PembayaranController;

Route::middleware('api.contract')->group(function () {

    // =========================
    // AUTENTIKASI
    // =========================
    Route::post('/login', [AuthController::class, 'loginApi']);

    Route::middleware(['auth:sanctum', 'pemilik'])->group(function () {

        Route::post('/logout', [AuthController::class, 'logoutApi']);
        Route::get('/me', [AuthController::class, 'me']);

        // =========================
        // KAMAR
        // =========================
        Route::get('/kamar', [KamarController::class, 'index']);
        Route::post('/kamar', [KamarController::class, 'store']);
        Route::get('/kamar/{id}', [KamarController::class, 'show'])
            ->whereNumber('id');
        Route::put('/kamar/{id}', [KamarController::class, 'update'])
            ->whereNumber('id');
        Route::delete('/kamar/{id}', [KamarController::class, 'destroy'])
            ->whereNumber('id');

        // =========================
        // PENGHUNI
        // =========================
        Route::get('/penghuni', [PenghuniController::class, 'index']);
        Route::post('/penghuni', [PenghuniController::class, 'store']);
        Route::get('/penghuni/{id}', [PenghuniController::class, 'show'])
            ->whereNumber('id');
        Route::put('/penghuni/{id}', [PenghuniController::class, 'update'])
            ->whereNumber('id');
        Route::delete('/penghuni/{id}', [PenghuniController::class, 'destroy'])
            ->whereNumber('id');

        // =========================
        // PENGHUNIAN
        // =========================
        Route::get('/penghunian', [PenghunianController::class, 'index']);
        Route::post('/penghunian', [PenghunianController::class, 'store']);
        Route::post('/penghunian/dengan-penghuni', [PenghunianController::class, 'storeDenganPenghuni']);

        Route::get('/penghunian/{id}', [PenghunianController::class, 'show'])
            ->whereNumber('id');
        Route::put('/penghunian/{id}', [PenghunianController::class, 'update'])
            ->whereNumber('id');

        Route::put('/penghunian/{id}/penyewa-utama', [PenghunianController::class, 'setPenyewaUtama'])
            ->whereNumber('id');

        // Perhitungan DP dan skema sewa
        Route::post('/penghunian/{id}/hitung-dp', [PenghunianController::class, 'hitung'])
            ->whereNumber('id');

        // Pencatatan DP (endpoint lama, dipertahankan sementara)
        Route::post('/penghunian/{id}/dp', [PenghunianController::class, 'catatDp'])
            ->whereNumber('id');

        // Pengaturan tenggat pembayaran
        Route::put('/penghunian/{id}/tenggat', [PenghunianController::class, 'ubahTenggat'])
            ->whereNumber('id');
        Route::post('/penghunian/{id}/tenggat-berikutnya', [PenghunianController::class, 'tenggatBerikutnya'])
            ->whereNumber('id');

        // Menyelesaikan masa penghunian
        Route::put('/penghunian/{id}/selesai', [PenghunianController::class, 'selesai'])
            ->whereNumber('id');

        // =========================
        // PEMBAYARAN
        // =========================

        // Melihat seluruh pembayaran
        Route::get('/pembayaran', [PembayaranController::class, 'index']);

        // Mencatat pembayaran DP, cicilan, atau pelunasan
        Route::post('/pembayaran', [PembayaranController::class, 'store']);

        // Melihat detail pembayaran
        Route::get('/pembayaran/{id}', [PembayaranController::class, 'show'])
            ->whereNumber('id');

        // Mengubah data pembayaran
        Route::put('/pembayaran/{id}', [PembayaranController::class, 'update'])
            ->whereNumber('id');

        // Verifikasi atau penolakan pembayaran
        Route::put('/pembayaran/{id}/status', [PembayaranController::class, 'updateStatus'])
            ->whereNumber('id');
    });
});