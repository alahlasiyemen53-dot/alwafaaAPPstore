<?php

namespace App\Http\Middleware;

use App\Enums\ApiErrorCode;
use App\Support\ApiResponse;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ContentNegotiationMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Accept Header Check
        $accept = $request->header('Accept');
        if ($accept !== null && $accept !== '') {
            $isAcceptable = str_contains($accept, 'application/json')
                || str_contains($accept, '+json')
                || str_contains($accept, '*/*');

            if (! $isAcceptable) {
                return ApiResponse::error(
                    message: ApiErrorCode::NotAcceptable->defaultMessage(),
                    code: ApiErrorCode::NotAcceptable,
                    status: 406
                );
            }
        }

        // 2. Content-Type Check for requests bearing content
        if (in_array($request->method(), ['POST', 'PUT', 'PATCH'], true)) {
            $content = $request->getContent();
            if ($content !== '' && $content !== false) {
                $contentType = $request->header('Content-Type');
                if ($contentType === null || (! str_contains($contentType, 'application/json') && ! str_contains($contentType, '+json'))) {
                    return ApiResponse::error(
                        message: ApiErrorCode::UnsupportedMediaType->defaultMessage(),
                        code: ApiErrorCode::UnsupportedMediaType,
                        status: 415
                    );
                }
            }
        }

        return $next($request);
    }
}
