<?php

namespace App\Infrastructure\Notifications;

use RuntimeException;

class NotificationConfigurationException extends RuntimeException
{
    private const MAX_CONNECT_TIMEOUT_SECONDS = 30;

    private const MAX_REQUEST_TIMEOUT_SECONDS = 60;

    private const MAX_RETRY_BACKOFF_SECONDS = 3600;

    public static function assertFcmIsConfigured(array $configuration): void
    {
        if (! ($configuration['enabled'] ?? false)) {
            return;
        }

        if (blank($configuration['project_id'] ?? null) || blank($configuration['credentials_path'] ?? null)) {
            throw new self('FCM configuration is incomplete.');
        }

        self::assertBoundedSeconds($configuration['connect_timeout'] ?? null, self::MAX_CONNECT_TIMEOUT_SECONDS);
        self::assertBoundedSeconds($configuration['request_timeout'] ?? null, self::MAX_REQUEST_TIMEOUT_SECONDS);
        self::assertRetryBackoffs($configuration['retry_backoff_seconds'] ?? null);
    }

    private static function assertBoundedSeconds(mixed $seconds, int $maximum): void
    {
        if (! is_int($seconds) || $seconds < 1 || $seconds > $maximum) {
            throw new self('FCM configuration is invalid.');
        }
    }

    private static function assertRetryBackoffs(mixed $backoffs): void
    {
        if (! is_array($backoffs) || $backoffs === []) {
            throw new self('FCM configuration is invalid.');
        }

        foreach ($backoffs as $backoff) {
            self::assertBoundedSeconds($backoff, self::MAX_RETRY_BACKOFF_SECONDS);
        }
    }
}
