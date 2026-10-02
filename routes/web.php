<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;

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
| Halaman Login (Tampilan FE)
|--------------------------------------------------------------------------
*/

// 1. Tampilan Login Pemilik (Default)
Route::get('/login', function () {
    return view('login', ['role' => 'pemilik']);
})->name('login');

// 2. Tampilan Login Penghuni (Untuk cek UI)
Route::get('/login/penghuni', function () {
    return view('login', ['role' => 'penghuni']);
})->name('login.penghuni');

// 3. Simulasi Submit Form Login -> Langsung Direct ke Dashboard
Route::post('/login', function () {
    return redirect()->route('dashboard');
})->name('login.process');


/*
|--------------------------------------------------------------------------
| Logout
|--------------------------------------------------------------------------
*/

Route::post('/logout', function () {
    return redirect()->route('login');
})->name('logout');


/*
|--------------------------------------------------------------------------
| Halaman Pemilik (Bypass Middleware Sementara)
|--------------------------------------------------------------------------
*/

// Middleware 'auth' & 'pemilik' dilepas sementara agar kamu bisa cek UI Dashboard
Route::get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');