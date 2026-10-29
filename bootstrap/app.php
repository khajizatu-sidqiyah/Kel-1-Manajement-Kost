<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'pemilik' => \App\Http\Middleware\PemilikMiddleware::class,
            'penghuni' => \App\Http\Middleware\PenghuniMiddleware::class,
            'api.contract' => \App\Http\Middleware\ApiContractMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) return null;
            return response()->json(['message' => 'Parameter permintaan tidak valid.', 'error' => [
                'code' => 422, 'message' => 'Validasi gagal.', 'status' => 'INVALID_ARGUMENT', 'details' => $e->errors(),
            ]], 422);
        });
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) return null;
            return response()->json(['message' => 'Autentikasi diperlukan.', 'error' => [
                'code' => 401, 'message' => 'Token tidak ada atau tidak valid.', 'status' => 'UNAUTHENTICATED', 'details' => (object) [],
            ]], 401);
        });
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpExceptionInterface $e, \Illuminate\Http\Request $request) {
            if (!$request->is('api/*')) return null;
            $status = $e->getStatusCode();
            $name = match ($status) { 403 => 'PERMISSION_DENIED', 404 => 'NOT_FOUND', default => $status >= 500 ? 'INTERNAL' : 'ERROR' };
            $message = $status === 403 ? 'Akses ditolak.' : ($status === 404 ? 'Endpoint atau data tidak ditemukan.' : ($e->getMessage() ?: 'Permintaan gagal.'));
            return response()->json(['message' => $message, 'error' => [
                'code' => $status, 'message' => $message, 'status' => $name, 'details' => (object) [],
            ]], $status);
        });
    })->create();