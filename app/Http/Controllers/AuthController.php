<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;

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
     * Menampilkan halaman login penghuni.
     */
    public function showLoginPenghuni()
    {
        return view('login', [
            'role' => 'penghuni',
        ]);
    }

    /**
     * Login pemilik.
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['role'] = 'pemilik';

        if (Auth::attempt(
            $credentials,
            $request->boolean('remember')
        )) {
            $request->session()->regenerate();

            return redirect()->route('dashboard');
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors([
                'email' => 'Email atau kata sandi tidak sesuai.',
            ]);
    }

    /**
     * Login penghuni.
     */
    public function loginPenghuni(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        // Cek apakah akun penghuni sudah dibuat
        $user = User::where('email', $credentials['email'])
            ->where('role', 'penghuni')
            ->first();

        if (!$user) {
            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Akun penghuni belum dibuatkan oleh pemilik.',
                ]);
        }

        // Login penghuni
        if (!Auth::attempt([
            'email' => $credentials['email'],
            'password' => $credentials['password'],
            'role' => 'penghuni',
        ], $request->boolean('remember'))) {

            return back()
                ->withInput($request->only('email'))
                ->withErrors([
                    'email' => 'Email atau kata sandi tidak sesuai.',
                ]);
        }

        $request->session()->regenerate();

        // Ambil user yang sedang login
        $user = Auth::user();

        // Pastikan akun mempunyai data penghuni
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

        // Masuk ke data/profil penghuni sendiri
        return redirect()->route(
            'penghuni.show',
            ['id' => $user->penghuni->id_penghuni]
        );
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