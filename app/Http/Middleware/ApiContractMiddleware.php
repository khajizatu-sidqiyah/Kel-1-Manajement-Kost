<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiContractMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (
            !$response instanceof \Illuminate\Http\Response &&
            !$response instanceof \Illuminate\Http\JsonResponse
        ) {
            return $response;
        }

        $content = $response->getContent();

        if (!is_string($content) || $content === '') {
            return $response;
        }

        $body = json_decode($content, true);

        if (!is_array($body)) {
            return $response;
        }

        $status = $response->getStatusCode();

        if ($status >= 400) {
            $message = $body['message'] ?? 'Permintaan gagal.';

            // Ambil detail error dari berbagai kemungkinan struktur respons.
            $details = $body['errors']
                ?? $body['details']
                ?? $body['error']['details']
                ?? (object) [];

            // Ambil kode error dari respons sebelumnya jika integer; jika string fallback ke status HTTP
            $code = is_int($body['error']['code'] ?? null) ? $body['error']['code'] : $status;

            // Pertahankan pesan khusus dari error jika tersedia.
            $errorMessage = $body['error']['message'] ?? $message;

            $body = [
                'message' => $message,
                'error' => [
                    'code' => $code,
                    'message' => $errorMessage,
                    'status' => $this->statusName($status),
                    'details' => $details,
                ],
            ];
        } else {
            if (
                isset($body['sisa_tagihan']) &&
                !isset($body['sisa_pembayaran'])
            ) {
                $body['sisa_pembayaran'] = $body['sisa_tagihan'];
            }

            if (
                isset($body['peringatan']) &&
                !isset($body['warning'])
            ) {
                $body['warning'] = $body['peringatan'] !== null;
            }

            unset($body['success']);

            if (!array_key_exists('message', $body)) {
                $body['message'] = '';
            }

            if (!array_key_exists('data', $body)) {
                $extras = $body;
                unset($extras['message'], $extras['success']);

                $body = [
                    'message' => $body['message'],
                    'data' => empty($extras) ? null : $extras,
                ];
            }
        }

        $response->setContent(
            json_encode(
                $body,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            )
        );

        $response->headers->set(
            'Content-Type',
            'application/json; charset=utf-8'
        );

        return $response;
    }

    private function statusName(int $status): string
    {
        return match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHORIZED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            409 => 'CONFLICT',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            default => $status >= 500 ? 'INTERNAL_SERVER_ERROR' : 'ERROR',
        };
    }
}
