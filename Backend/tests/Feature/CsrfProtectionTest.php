<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;
use Tests\TestCase;

/**
 * Laravel's VerifyCsrfToken/ValidateCsrfToken middleware intentionally
 * short-circuits (always passes) while `app()->runningUnitTests()` is true,
 * which is always the case inside a PHPUnit process — so a plain
 * `$this->post(...)` feature test can never observe a real 419 rejection.
 * To actually exercise the token-matching logic (and catch an accidental
 * `except()`/route misconfiguration that disables CSRF where it shouldn't
 * be disabled), we subclass the middleware to disable only that testing
 * shortcut and drive it directly.
 */
final class CsrfProtectionTest extends TestCase
{
    public function test_middleware_rejects_a_request_with_no_csrf_token(): void
    {
        $middleware = $this->realCsrfMiddleware();

        $request = Request::create('/admin/login', 'POST', ['identifier' => 'a@b.com', 'password' => 'x']);
        $request->setLaravelSession($this->app['session.store']);
        $request->getSession()->start();

        $this->expectException(TokenMismatchException::class);

        $middleware->handle($request, fn ($req) => response('ok'));
    }

    public function test_middleware_rejects_a_request_with_a_mismatched_csrf_token(): void
    {
        $middleware = $this->realCsrfMiddleware();

        $request = Request::create('/admin/login', 'POST', [
            'identifier' => 'a@b.com',
            'password' => 'x',
            '_token' => 'clearly-not-the-real-token',
        ]);
        $request->setLaravelSession($this->app['session.store']);
        $request->getSession()->start();

        $this->expectException(TokenMismatchException::class);

        $middleware->handle($request, fn ($req) => response('ok'));
    }

    public function test_middleware_accepts_a_request_with_the_matching_csrf_token(): void
    {
        $middleware = $this->realCsrfMiddleware();

        $session = $this->app['session.store'];
        $session->start();

        $request = Request::create('/admin/login', 'POST', [
            'identifier' => 'a@b.com',
            'password' => 'x',
            '_token' => $session->token(),
        ]);
        $request->setLaravelSession($session);

        $response = $middleware->handle($request, fn ($req) => response('ok'));

        $this->assertSame('ok', $response->getContent());
    }

    private function realCsrfMiddleware(): ValidateCsrfToken
    {
        return new class($this->app, $this->app['encrypter']) extends ValidateCsrfToken
        {
            // Force the real token-matching path even though we are running under PHPUnit.
            protected function runningUnitTests()
            {
                return false;
            }
        };
    }
}
