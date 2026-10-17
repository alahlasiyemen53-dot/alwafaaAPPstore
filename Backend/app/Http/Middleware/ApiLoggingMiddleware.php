<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

final class ApiLoggingMiddleware
{
    private const SENSITIVE_KEYS = [
        'password',
        'password_confirmation',
        'token',
        'access_token',
        'refresh_token',
        'otp',
        'code',
        'secret',
        'authorization',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = round((microtime(true) - $start) * 1000, 2);

        $this->logRequest($request, $response, $durationMs);

        return $response;
    }

    private function logRequest(Request $request, Response $response, float $durationMs): void
    {
        $sanitizedBody = $this->sanitizePayload($request->all());

        Log::channel('daily')->info('API Request Handled', [
            'request_id' => $request->header('X-Request-Id'),
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'payload' => $sanitizedBody,
        ]);
    }

    private function sanitizePayload(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array(strtolower((string) $key), self::SENSITIVE_KEYS, true)) {
                $data[$key] = '********';
            } elseif (is_array($value)) {
                $data[$key] = $this->sanitizePayload($value);
            }
        }

        return $data;
    }
}
