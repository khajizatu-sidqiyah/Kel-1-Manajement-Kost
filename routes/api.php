<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KamarController;
use App\Http\Controllers\PenghuniController;

Route::get('/kamar', [KamarController::class, 'index']);
Route::post('/kamar', [KamarController::class, 'store']);
Route::put('/kamar/{id}', [KamarController::class, 'update']);

Route::get('/penghuni', [PenghuniController::class, 'index']);
Route::post('/penghuni', [PenghuniController::class, 'store']);
Route::get('/penghuni/{id}', [PenghuniController::class, 'show']);
Route::put('/penghuni/{id}', [PenghuniController::class, 'update']);
Route::patch('/penghuni/{id}/penyewa-utama', [PenghuniController::class, 'setPenyewaUtama']);
Route::delete('/penghuni/{id}', [PenghuniController::class, 'destroy']);
