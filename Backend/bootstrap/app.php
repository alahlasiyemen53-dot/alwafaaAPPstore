<?php

use App\Http\Middleware\SecurityHeaders;
use App\Support\ApiResponse;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->redirectGuestsTo(fn (): string => route('admin.login'));
        $middleware->append(SecurityHeaders::class);
        $middleware->throttleApi();
        $middleware->alias([
            'request.id' => \App\Http\Middleware\SetRequestIdMiddleware::class,
            'content.negotiation' => \App\Http\Middleware\ContentNegotiationMiddleware::class,
            'api.logging' => \App\Http\Middleware\ApiLoggingMiddleware::class,
            'idempotent' => \App\Http\Middleware\IdempotencyMiddleware::class,
            'admin.active' => \App\Http\Middleware\EnsureAdminAccountIsActive::class,
            'admin.session' => \App\Http\Middleware\EnforceAdminSessionPolicy::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->shouldRenderJsonWhen(fn (Request $request): bool => $request->is('api/*') || $request->expectsJson()
        );

        $exceptions->render(function (Throwable $exception, Request $request) {
            if ($exception instanceof \Illuminate\Http\Exceptions\HttpResponseException) {
                return $exception->getResponse();
            }

            if (! $request->is('api/*') && ! $request->expectsJson()) {
                return null;
            }

            if ($exception instanceof ValidationException) {
                return ApiResponse::validationError(
                    errors: $exception->errors(),
                    message: 'Validation failed.'
                );
            }

            if ($exception instanceof AuthenticationException) {
                $message = ($exception->getMessage() && $exception->getMessage() !== 'Unauthenticated.')
                    ? $exception->getMessage()
                    : \App\Enums\ApiErrorCode::AuthRequired->defaultMessage();

                return ApiResponse::error(
                    message: $message,
                    code: \App\Enums\ApiErrorCode::AuthRequired,
                    status: 401
                );
            }

            if ($exception instanceof \App\Exceptions\Auth\CustomerAccessRestrictedException) {
                return ApiResponse::error(
                    message: $exception->getMessage(),
                    code: \App\Enums\ApiErrorCode::CustomerAccessRestricted,
                    status: 403
                );
            }

            if ($exception instanceof \App\Exceptions\Otp\OtpCooldownException) {
                return ApiResponse::error(
                    message: $exception->getMessage(),
                    code: \App\Enums\ApiErrorCode::OtpRateLimited,
                    status: 429,
                    errors: ['seconds_remaining' => $exception->secondsRemaining],
                    headers: ['Retry-After' => (string) $exception->secondsRemaining]
                );
            }

            if ($exception instanceof \App\Exceptions\Otp\InvalidOtpException) {
                return ApiResponse::error(
                    message: $exception->getMessage(),
                    code: \App\Enums\ApiErrorCode::OtpInvalid,
                    status: 400,
                    errors: $exception->attemptsRemaining !== null ? ['attempts_remaining' => $exception->attemptsRemaining] : []
                );
            }

            if ($exception instanceof \App\Exceptions\Otp\ExpiredOtpException) {
                return ApiResponse::error(
                    message: $exception->getMessage(),
                    code: \App\Enums\ApiErrorCode::OtpExpired,
                    status: 400
                );
            }

            if ($exception instanceof \App\Exceptions\Otp\ExhaustedOtpException) {
                return ApiResponse::error(
                    message: $exception->getMessage(),
                    code: \App\Enums\ApiErrorCode::OtpAttemptsExhausted,
                    status: 429
                );
            }

            if ($exception instanceof AuthorizationException || $exception instanceof \Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException) {
                return ApiResponse::error(
                    message: \App\Enums\ApiErrorCode::Forbidden->defaultMessage(),
                    code: \App\Enums\ApiErrorCode::Forbidden,
                    status: 403
                );
            }

            if ($exception instanceof \Illuminate\Database\Eloquent\ModelNotFoundException || $exception instanceof \Symfony\Component\HttpKernel\Exception\NotFoundHttpException) {
                return ApiResponse::error(
                    message: \App\Enums\ApiErrorCode::ResourceNotFound->defaultMessage(),
                    code: \App\Enums\ApiErrorCode::ResourceNotFound,
                    status: 404
                );
            }

            if ($exception instanceof \Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException) {
                return ApiResponse::error(
                    message: \App\Enums\ApiErrorCode::MethodNotAllowed->defaultMessage(),
                    code: \App\Enums\ApiErrorCode::MethodNotAllowed,
                    status: 405
                );
            }

            if ($exception instanceof \Illuminate\Http\Exceptions\ThrottleRequestsException) {
                return ApiResponse::error(
                    message: \App\Enums\ApiErrorCode::RateLimited->defaultMessage(),
                    code: \App\Enums\ApiErrorCode::RateLimited,
                    status: 429,
                    headers: $exception->getHeaders()
                );
            }

            if ($exception instanceof \JsonException) {
                return ApiResponse::error(
                    message: \App\Enums\ApiErrorCode::MalformedRequest->defaultMessage(),
                    code: \App\Enums\ApiErrorCode::MalformedRequest,
                    status: 400
                );
            }

            $status = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;
            $code = match ($status) {
                400 => \App\Enums\ApiErrorCode::MalformedRequest,
                401 => \App\Enums\ApiErrorCode::AuthRequired,
                403 => \App\Enums\ApiErrorCode::Forbidden,
                404 => \App\Enums\ApiErrorCode::ResourceNotFound,
                405 => \App\Enums\ApiErrorCode::MethodNotAllowed,
                406 => \App\Enums\ApiErrorCode::NotAcceptable,
                409 => \App\Enums\ApiErrorCode::Conflict,
                415 => \App\Enums\ApiErrorCode::UnsupportedMediaType,
                422 => \App\Enums\ApiErrorCode::ValidationFailed,
                429 => \App\Enums\ApiErrorCode::RateLimited,
                503 => \App\Enums\ApiErrorCode::ServiceUnavailable,
                default => \App\Enums\ApiErrorCode::InternalError,
            };

            $message = ($status < 500 || config('app.debug'))
                ? ($exception->getMessage() ?: $code->defaultMessage())
                : \App\Enums\ApiErrorCode::InternalError->defaultMessage();

            $headers = $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : [];

            return ApiResponse::error(
                message: $message,
                code: $code,
                status: $status,
                headers: $headers
            );
        });
    })->create();
