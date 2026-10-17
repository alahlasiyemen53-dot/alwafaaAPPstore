<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds baseline OWASP-recommended security headers to every response.
 *
 * CSP is scoped to same-origin assets only: the Velzon admin theme is served
 * from local application assets (no external CDN dependency per project rules),
 * so a strict same-origin policy does not break any current functionality.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=()');

        if (! $request->is('api/*')) {
            $response->headers->set(
                'Content-Security-Policy',
                "default-src 'self'; ".
                "script-src 'self' 'unsafe-inline'; ".
                "style-src 'self' 'unsafe-inline'; ".
                "img-src 'self' data:; ".
                "font-src 'self' data:; ".
                "frame-ancestors 'none'; ".
                "base-uri 'self'; ".
                "form-action 'self';"
            );
        }

        if ($request->isSecure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains'
            );
        }

        return $response;
    }
}
