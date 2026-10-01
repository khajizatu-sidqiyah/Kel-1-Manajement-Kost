<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

// Login Pemilik
Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

// Login Penghuni
Route::get('/login/penghuni', [AuthController::class, 'showLoginPenghuni'])
    ->name('login.penghuni');

// Proses login
Route::post('/login', [AuthController::class, 'login'])
    ->name('login.process');

// Logout
Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth');

// Dashboard Pemilik
Route::middleware(['auth', 'pemilik'])->group(function () {
    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');
});