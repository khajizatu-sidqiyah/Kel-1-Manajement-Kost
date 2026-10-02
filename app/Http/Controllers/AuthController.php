<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * Menampilkan halaman login pemilik.
     */
    public function showLogin()
    {
        return view('login', [
            'role' => 'pemilik',
        ]);
    }

    /**
     * Memproses login pemilik.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        /*
         * Login hanya diperbolehkan untuk user
         * dengan role pemilik.
         */
        $credentials['role'] = 'pemilik';

        if (Auth::attempt(
            $credentials,
            $request->boolean('remember')
        )) {

            // Mencegah session fixation
            $request->session()->regenerate();

            return redirect()->intended(
                route('dashboard')
            );
        }

        return back()
            ->withErrors([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ])
            ->onlyInput('email');
    }

    /**
     * Logout.
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}