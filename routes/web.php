<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| Halaman Awal
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return redirect()->route('login');
});


/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {

    // Login Pemilik
    Route::get('/login', [AuthController::class, 'showLogin'])
        ->name('login');

    // Proses Login
    Route::post('/login', [AuthController::class, 'login'])
        ->name('login.process');

    // Login Penghuni - sementara untuk UI
    Route::get('/login/penghuni', function () {
        return view('login', ['role' => 'penghuni']);
    })->name('login.penghuni');
});


/*
|--------------------------------------------------------------------------
| Area Pemilik
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'pemilik'])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

    Route::get('/denah', function () {
        return view('denah');
    })->name('denah');

    Route::post('/logout', [AuthController::class, 'logout'])
        ->name('logout');

});