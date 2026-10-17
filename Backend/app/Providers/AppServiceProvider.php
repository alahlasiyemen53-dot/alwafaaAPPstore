<?php

namespace App\Providers;

use App\Contracts\Notifications\PushNotificationSender;
use App\Contracts\SupportChat\SupportChatFirestoreGateway;
use App\Enums\ApiErrorCode;
use App\Infrastructure\Notifications\FirebaseCloudMessagingSender;
use App\Infrastructure\Notifications\NotificationConfigurationException;
use App\Infrastructure\SupportChat\KreaitSupportChatFirestoreGateway;
use App\Infrastructure\SupportChat\NullSupportChatFirestoreGateway;
use App\Services\SupportChat\FirebaseCustomTokenService;
use App\Support\ApiResponse;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PushNotificationSender::class, function (): FirebaseCloudMessagingSender {
            try {
                return FirebaseCloudMessagingSender::fromConfiguration(config('services.fcm'));
            } catch (NotificationConfigurationException|Throwable $exception) {
                report($exception);

                $safeReason = $exception instanceof NotificationConfigurationException
                    ? $exception->getMessage()
                    : 'FCM configuration failed at application boot. Check FCM_ENABLED, FCM_PROJECT_ID, and FCM_CREDENTIALS_PATH.';

                // Keep admin/API bootable when credentials are misconfigured on the host.
                return FirebaseCloudMessagingSender::disabledDueToConfiguration($safeReason);
            }
        });

        $this->app->singleton(\App\Contracts\OtpSenderInterface::class, function () {
            $driver = config('otp.driver', 'fake');

            return match ($driver) {
                'log' => new \App\Services\Otp\LogOtpSender(),
                'whatsapp', 'whapi' => new \App\Services\Otp\WhapiWhatsappOtpSender(
                    url: (string) config('otp.whatsapp.url', 'https://whapi.alwafaaye.com'),
                    apiKey: (string) config('otp.whatsapp.api_key', ''),
                    timeout: (int) config('otp.whatsapp.timeout', 10),
                ),
                default => new \App\Services\Otp\FakeOtpSender(),
            };
        });

        $this->app->singleton(SupportChatFirestoreGateway::class, function (): SupportChatFirestoreGateway {
            if (app()->environment('testing')) {
                return new NullSupportChatFirestoreGateway;
            }

            return KreaitSupportChatFirestoreGateway::fromConfiguration(config('services.fcm'));
        });

        $this->app->singleton(FirebaseCustomTokenService::class, function (): FirebaseCustomTokenService {
            if (app()->environment('testing')) {
                return FirebaseCustomTokenService::fromConfiguration(['enabled' => false]);
            }

            return FirebaseCustomTokenService::fromConfiguration(config('services.fcm'));
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();

        Model::preventLazyLoading(! app()->isProduction());

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute(300)->by(
            $request->user('customer_jwt')?->getAuthIdentifier() ?? $request->user()?->getAuthIdentifier() ?? $request->ip()
        ));

        RateLimiter::for('admin-login', fn (Request $request): Limit => Limit::perMinute(5)->by(strtolower((string) $request->input('identifier')).'|'.$request->ip())
        );

        RateLimiter::for('reports-export', fn (Request $request): Limit => Limit::perMinute(10)->by($request->user()?->getAuthIdentifier() ?? $request->ip())
        );

        RateLimiter::for('media-upload', fn (Request $request): Limit => Limit::perMinute(30)->by($request->user()?->getAuthIdentifier() ?? $request->ip())
        );

        RateLimiter::for('password-change', fn (Request $request): Limit => Limit::perMinute(5)->by($request->user()?->getAuthIdentifier() ?? $request->ip())
        );

        RateLimiter::for('api.customer.public', fn (Request $request): Limit => Limit::perMinute(60)
            ->by($request->ip())
            ->response(fn () => ApiResponse::error(
                'Too many requests. Please slow down.',
                ApiErrorCode::RateLimited,
                429
            ))
        );

        RateLimiter::for('api.customer.auth', fn (Request $request): Limit => Limit::perMinute(120)
            ->by($request->user('customer_jwt')?->getAuthIdentifier() ?? $request->ip())
            ->response(fn () => ApiResponse::error(
                'Too many requests. Please slow down.',
                ApiErrorCode::RateLimited,
                429
            ))
        );

        RateLimiter::for('api.customer.otp-request', fn (Request $request): Limit => Limit::perMinute(5)
            ->by($request->ip().'|'.(string) $request->input('phone'))
            ->response(fn () => ApiResponse::error(
                'تجاوزت الحد المسموح به لطلبات رمز التحقق. يرجى الانتظار والمحاولة لاحقاً.',
                ApiErrorCode::OtpRateLimited,
                429
            ))
        );

        RateLimiter::for('api.customer.otp-verify', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->ip().'|'.(string) $request->input('phone'))
            ->response(fn () => ApiResponse::error(
                'تجاوزت الحد المسموح به لمحاولات التحقق. يرجى الانتظار والمحاولة لاحقاً.',
                ApiErrorCode::OtpRateLimited,
                429
            ))
        );

        RateLimiter::for('api.customer.review-comments', fn (Request $request): Limit => Limit::perMinute(10)
            ->by($request->user('customer_jwt')?->getAuthIdentifier() ?? $request->ip())
            ->response(fn () => ApiResponse::error(
                ApiErrorCode::RateLimited->defaultMessage(),
                ApiErrorCode::RateLimited,
                429
            ))
        );

        \Illuminate\Support\Facades\Auth::extend('customer_jwt', function ($app, $name, array $config) {
            return new \App\Auth\CustomerJwtGuard(
                \Illuminate\Support\Facades\Auth::createUserProvider($config['provider'] ?? 'customers'),
                $app->make('request'),
                $app->make(\App\Services\Auth\CustomerJwtService::class)
            );
        });
    }
}
