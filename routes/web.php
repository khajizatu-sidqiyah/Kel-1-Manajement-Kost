<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\PenghuniController;

/*
|--------------------------------------------------------------------------
| Halaman Awal
|--------------------------------------------------------------------------
*/

Route::get('/', function () {
    return view('login');
});


/*
|--------------------------------------------------------------------------
| Halaman Login
|--------------------------------------------------------------------------
*/

Route::get('/login', [AuthController::class, 'showLogin']) 
    ->name('login'); 

Route::get('/login/penghuni', [AuthController::class, 'showLoginPenghuni']) 
    ->name('login.penghuni'); 
    
Route::post('/login', [AuthController::class, 'login']) 
    ->name('login.process'); 
    
Route::post('/login/penghuni', [AuthController::class, 'loginPenghuni']) 
    ->name('login.penghuni.process');


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth');


/*
|--------------------------------------------------------------------------
| Halaman Pemilik
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'pemilik'])->group(function () {

    Route::get('/dashboard', function () {
        return view('dashboard');
    })->name('dashboard');

});

/*
|--------------------------------------------------------------------------
| Halaman Penghuni
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'penghuni'])->group(function () {

    Route::get('/penghuni', [PenghuniController::class, 'index'])
        ->name('penghuni.dashboard');

    Route::get('/penghuni/{id}', [PenghuniController::class, 'show'])
        ->name('penghuni.show');

});