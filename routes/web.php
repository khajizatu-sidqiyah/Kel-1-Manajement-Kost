<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('index');
});

// Login Pemilik (default) dan Login Penghuni memakai satu view yang sama
Route::get('/login', function () {
    return view('login', ['role' => 'pemilik']);
})->name('login');

Route::get('/login/penghuni', function () {
    return view('login', ['role' => 'penghuni']);
})->name('login.penghuni');
