<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PenghuniController;
use App\Http\Controllers\PenghunianController;
use App\Http\Controllers\PengaturanSewaController;

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

// Login Pemilik
Route::get('/login', [AuthController::class, 'showLogin'])
    ->name('login');

Route::post('/login', [AuthController::class, 'login'])
    ->name('login.process');

// Login Penghuni
Route::get('/login/penghuni', [AuthController::class, 'showLoginPenghuni'])
    ->name('login.penghuni');

Route::post('/login/penghuni', [AuthController::class, 'loginPenghuni'])
    ->name('login.penghuni.process');


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

    Route::get('/penyewa', function () {
        return view('penyewa');
    })->name('penyewa');

    Route::get('/pengaturan-sewa', [PengaturanSewaController::class, 'index'])
        ->name('pengaturan-sewa.index');

    Route::put('/pengaturan-sewa', [PengaturanSewaController::class, 'update'])
        ->name('pengaturan-sewa.update');

    // Perhitungan DP dan tenggat pelunasan.
    Route::post('/penghunian/{id}/hitung', [
        PenghunianController::class,
        'hitung',
    ])->name('penghunian.hitung');

    Route::put('/penghunian/{id}/tenggat', [
        PenghunianController::class,
        'ubahTenggat',
    ])->name('penghunian.ubah-tenggat');

    Route::put('/penghunian/{id}/tenggat-berikutnya', [
        PenghunianController::class,
        'tenggatBerikutnya',
    ])->name('penghunian.tenggat-berikutnya');

    Route::get('/penghuni', function () {
        return view('penghuni');
    })->name('penghuni');});



/*
|--------------------------------------------------------------------------
| Area Penghuni
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'penghuni'])->group(function () {

    Route::get('/penghuni/dashboard', function () {
        return view('penghuni.dashboard');
    })->name('penghuni.dashboard');

    Route::get('/penghuni/{id}', [PenghuniController::class, 'show'])
        ->name('penghuni.show');
});


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');
