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

    /** Login khusus API: mengembalikan token Sanctum, bukan redirect halaman web. */
    public function loginApi(Request $request)
    {
        $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);
        if ($validator->fails()) {
            return response()->json(['message' => 'Parameter permintaan tidak valid.', 'error' => [
                'code' => 422, 'message' => 'Validasi gagal.', 'status' => 'INVALID_ARGUMENT',
                'details' => $validator->errors(),
            ]], 422);
        }
        $user = User::where('email', $request->input('email'))->where('role', 'pemilik')->first();
        if (!$user || !\Illuminate\Support\Facades\Hash::check($request->input('password'), $user->password)) {
            return response()->json(['message' => 'Email atau kata sandi tidak sesuai.', 'error' => [
                'code' => 401, 'message' => 'Kredensial tidak valid.', 'status' => 'UNAUTHENTICATED', 'details' => (object) [],
            ]], 401);
        }
        $token = $user->createToken('postman-api')->plainTextToken;
        return response()->json(['message' => 'Login berhasil.', 'data' => [
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => ['id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role],
        ]], 200);
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

    public function me(Request $request)
    {
        $user = $request->user();
        return response()->json(['message' => '', 'data' => [
            'id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'role' => $user->role,
        ]], 200);
    }

    public function logoutApi(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();
        return response()->json(['message' => 'Logout berhasil.', 'data' => null], 200);
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