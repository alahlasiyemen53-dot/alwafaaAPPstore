<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class IdempotencyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, ?string $required = null): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (empty($idempotencyKey)) {
            if ($required === 'required') {
                return ApiResponse::error(
                    'Idempotency-Key header is required for this operation.',
                    ApiErrorCode::MalformedRequest,
                    400
                );
            }

            return $next($request);
        }

        // Validate Idempotency-Key format (must be valid UUID or alphanumeric string between 16 and 64 chars)
        if (! is_string($idempotencyKey) || strlen($idempotencyKey) > 64 || strlen($idempotencyKey) < 16) {
            return ApiResponse::error(
                'Invalid Idempotency-Key format. Must be between 16 and 64 characters.',
                ApiErrorCode::MalformedRequest,
                400
            );
        }

        $userId = $request->user('customer_jwt')?->getAuthIdentifier() ?? $request->ip();
        $cacheKey = "idempotency:{$userId}:{$idempotencyKey}";

        $cached = Cache::get($cacheKey);

        if ($cached) {
            if (is_array($cached) && ($cached['status'] ?? null) === 'processing') {
                return ApiResponse::error(
                    'A request with this Idempotency-Key is currently being processed.',
                    ApiErrorCode::IdempotencyConflict,
                    409
                );
            }

            if (is_array($cached) && isset($cached['content'], $cached['code'])) {
                $response = response($cached['content'], $cached['code'])
                    ->header('Content-Type', 'application/json')
                    ->header('X-Idempotent-Replay', 'true');

                return $response;
            }
        }

        // Mark as processing (lock for 60 seconds)
        Cache::put($cacheKey, ['status' => 'processing'], 60);

        try {
            $response = $next($request);

            // Only cache successful 2xx responses
            if ($response->isSuccessful() && $response instanceof JsonResponse) {
                Cache::put($cacheKey, [
                    'status' => 'completed',
                    'code' => $response->getStatusCode(),
                    'content' => $response->getContent(),
                ], now()->addHours(24));
            } elseif ($response->isSuccessful()) {
                Cache::put($cacheKey, [
                    'status' => 'completed',
                    'code' => $response->getStatusCode(),
                    'content' => $response->getContent(),
                ], now()->addHours(24));
            } else {
                // If failed with non-2xx, clear key so client can retry
                Cache::forget($cacheKey);
            }

            return $response;
        } catch (\Throwable $e) {
            Cache::forget($cacheKey);
            throw $e;
        }
    }
}
