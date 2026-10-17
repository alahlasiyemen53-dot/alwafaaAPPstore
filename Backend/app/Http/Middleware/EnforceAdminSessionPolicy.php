<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final class EnforceAdminSessionPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user() !== null) {
            $startedAt = (int) $request->session()->get((string) config('admin_session.session_started_key'), 0);
            $absoluteMinutes = (int) config('admin_session.absolute_lifetime_minutes', 0);

            if ($absoluteMinutes > 0) {
                if ($startedAt <= 0) {
                    $request->session()->put(
                        (string) config('admin_session.session_started_key'),
                        now()->getTimestamp()
                    );
                } elseif ((now()->getTimestamp() - $startedAt) > ($absoluteMinutes * 60)) {
                    Auth::logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();

                    return redirect()
                        ->route('admin.login')
                        ->withErrors(['identifier' => 'انتهت صلاحية جلستك. يرجى تسجيل الدخول مرة أخرى.']);
                }
            }
        }

        /** @var Response $response */
        $response = $next($request);

        if ($request->user() !== null && ! $request->is('api/*')) {
            $response->headers->set('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
            $response->headers->set('Pragma', 'no-cache');
            $response->headers->set('Expires', '0');
        }

        return $response;
    }
}
