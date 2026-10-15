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