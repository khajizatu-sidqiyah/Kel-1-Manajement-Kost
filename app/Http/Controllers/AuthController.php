<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Menampilkan halaman login Pemilik
     */
    public function showLogin()
    {
        return view('login', [
            'role' => 'pemilik'
        ]);
    }

    /**
     * Memproses login
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        // Verifikasi email dan password terhadap tabel users.
        // Password diverifikasi terhadap hash menggunakan Auth::attempt().
        if (Auth::attempt($credentials, $request->boolean('remember'))) {

            $request->session()->regenerate();

            // Pastikan akun memiliki role pemilik
            if (!in_array(Auth::user()->role, ['pemilik', 'penghuni'])) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => 'Email atau kata sandi tidak sesuai.',
                    ]);
            }

            // Arahkan pengguna sesuai role
            if (Auth::user()->role === 'pemilik') {
                return redirect()->route('dashboard');
            }

            if (Auth::user()->role === 'penghuni') {
                return redirect()->route('penghuni.dashboard');
            }
        }

        // Pesan error dibuat generik agar tidak membocorkan
        // apakah email atau password yang salah.
        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ]);
    }

    /**
     * Logout
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}