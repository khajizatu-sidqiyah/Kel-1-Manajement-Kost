<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RolePemilik
{
    /**
     * Menangani request yang masuk.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Pastikan user sudah login
        if (!auth()->check()) {
            return redirect('/login');
        }

        // Pastikan role user adalah pemilik
        if (auth()->user()->role !== 'pemilik') {
            abort(403, 'Anda tidak memiliki akses.');
        }

        return $next($request);
    }
}