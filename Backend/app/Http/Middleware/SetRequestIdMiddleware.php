<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class SetRequestIdMiddleware
{
    public const HEADER_NAME = 'X-Request-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header(self::HEADER_NAME);

        if (! is_string($requestId) || ! Str::isUuid($requestId)) {
            $requestId = (string) Str::uuid();
            $request->headers->set(self::HEADER_NAME, $requestId);
        }

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(self::HEADER_NAME, $requestId);

        return $response;
    }
}
