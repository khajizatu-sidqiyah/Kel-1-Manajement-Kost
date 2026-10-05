<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PemilikMiddleware
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {

        if ($request->user()->role !== 'pemilik') {
            abort(403, 'Akses hanya untuk pemilik.');
        }

        return $next($request);
    }
}