<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\KamarController;

Route::get('/kamar', [KamarController::class, 'index']);
Route::post('/kamar', [KamarController::class, 'store']);
Route::put('/kamar/{id}', [KamarController::class, 'update']);