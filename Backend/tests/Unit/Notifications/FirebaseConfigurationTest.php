<?php

namespace Tests\Unit\Notifications;

use App\Infrastructure\Notifications\NotificationConfigurationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FirebaseConfigurationTest extends TestCase
{
    public function test_disabled_fcm_has_a_safe_configuration_status(): void
    {
        config()->set('services.fcm', [
            'enabled' => false,
            'project_id' => null,
            'credentials_path' => null,
        ]);

        NotificationConfigurationException::assertFcmIsConfigured(config('services.fcm'));

        $this->assertFalse(config('services.fcm.enabled'));
    }

    #[DataProvider('incompleteEnabledConfigurations')]
    public function test_enabled_fcm_with_a_missing_required_setting_does_not_leak_configuration(
        ?string $projectId,
        ?string $credentialsPath,
    ): void {
        $this->expectException(NotificationConfigurationException::class);
        $this->expectExceptionMessage('FCM configuration is incomplete.');

        try {
            NotificationConfigurationException::assertFcmIsConfigured([
                'enabled' => true,
                'project_id' => $projectId,
                'credentials_path' => $credentialsPath,
            ]);
        } catch (NotificationConfigurationException $exception) {
            $this->assertDoesNotMatchRegularExpression('#(?:[A-Za-z]:)?[\\\\/]#', $exception->getMessage());

            throw $exception;
        }
    }

    public static function incompleteEnabledConfigurations(): array
    {
        return [
            'missing project ID' => [null, '/srv/private/firebase/service-account.json'],
            'missing credentials path' => ['lamma-staging', null],
        ];
    }

    #[DataProvider('invalidNumericConfigurations')]
    public function test_enabled_fcm_rejects_unbounded_numeric_configuration(array $overrides): void
    {
        $this->expectException(NotificationConfigurationException::class);
        $this->expectExceptionMessage('FCM configuration is invalid.');

        try {
            NotificationConfigurationException::assertFcmIsConfigured(array_replace([
                'enabled' => true,
                'project_id' => 'lamma-staging',
                'credentials_path' => '/srv/private/firebase/service-account.json',
                'connect_timeout' => 5,
                'request_timeout' => 15,
                'retry_backoff_seconds' => [60, 300, 900],
            ], $overrides));
        } catch (NotificationConfigurationException $exception) {
            $this->assertDoesNotMatchRegularExpression('#(?:[A-Za-z]:)?[\\\\/]#', $exception->getMessage());

            throw $exception;
        }
    }

    public static function invalidNumericConfigurations(): array
    {
        return [
            'zero connect timeout' => [['connect_timeout' => 0]],
            'negative request timeout' => [['request_timeout' => -1]],
            'non-numeric timeout' => [['connect_timeout' => 'five']],
            'connect timeout above upper bound' => [['connect_timeout' => 31]],
            'request timeout above upper bound' => [['request_timeout' => 61]],
            'zero retry backoff' => [['retry_backoff_seconds' => [0]]],
            'negative retry backoff' => [['retry_backoff_seconds' => [-1]]],
            'non-numeric retry backoff' => [['retry_backoff_seconds' => ['sixty']]],
            'retry backoff above upper bound' => [['retry_backoff_seconds' => [3601]]],
        ];
    }
}
