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

    if (Auth::attempt($credentials, $request->boolean('remember'))) {

        $request->session()->regenerate();

        $user = Auth::user();

        // Pastikan role akun valid
        if (!in_array($user->role, ['pemilik', 'penghuni'])) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Email atau kata sandi tidak sesuai.',
                ]);
        }

        // Login sebagai pemilik
        if ($user->role === 'pemilik') {
            return redirect()->route('dashboard');
        }

        // Login sebagai penghuni
        if ($user->role === 'penghuni') {

            // Cek apakah akun sudah memiliki data penghuni
            if (!$user->penghuni) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return back()
                    ->withInput($request->only('email'))
                    ->withErrors([
                        'email' => 'Akun penghuni belum memiliki data penghuni.',
                    ]);
            }

            return redirect()->route('penghuni.dashboard');
        }
    }

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