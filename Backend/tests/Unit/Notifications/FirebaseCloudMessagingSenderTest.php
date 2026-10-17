<?php

namespace Tests\Unit\Notifications;

use App\Data\Notifications\PushNotificationMessageData;
use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\ProviderFailureCategory;
use App\Infrastructure\Notifications\FirebaseCloudMessagingSender;
use App\Infrastructure\Notifications\NotificationConfigurationException;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\AuthenticationError;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Messaging\CloudMessage;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;
use Throwable;

final class FirebaseCloudMessagingSenderTest extends TestCase
{
    public function test_it_builds_a_neutral_firebase_message_and_returns_the_provider_reference(): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')
            ->once()
            ->with(Mockery::on(function (CloudMessage $message): bool {
                self::assertSame([
                    'data' => ['payload_version' => '1', 'type' => 'TEST_NOTIFICATION'],
                    'notification' => ['title' => 'عنوان آمن', 'body' => 'محتوى آمن'],
                    'apns' => [
                        'headers' => [
                            'apns-priority' => '10',
                        ],
                        'payload' => [
                            'payload_version' => '1',
                            'type' => 'TEST_NOTIFICATION',
                            'aps' => [
                                'sound' => 'default',
                                'badge' => 1,
                                'content-available' => 1,
                            ],
                        ],
                    ],
                    'token' => 'device-token',
                ], $message->jsonSerialize());

                return true;
            }))
            ->andReturn(['name' => 'projects/example/messages/123']);

        $result = (new FirebaseCloudMessagingSender($messaging))->send($this->message(), 'device-token');

        self::assertTrue($result->accepted);
        self::assertSame('projects/example/messages/123', $result->providerReference);
        self::assertNull($result->failureCategory);
    }

    #[DataProvider('providerFailures')]
    public function test_it_maps_provider_failures_without_exposing_provider_details(Throwable $exception, ProviderFailureCategory $category): void
    {
        $messaging = Mockery::mock(Messaging::class);
        $messaging->shouldReceive('send')->once()->andThrow($exception);

        $result = (new FirebaseCloudMessagingSender($messaging))->send($this->message(), 'sensitive-device-token');

        self::assertFalse($result->accepted);
        self::assertSame($category, $result->failureCategory);
        self::assertDoesNotMatchRegularExpression('/sensitive-device-token|secret|raw provider detail/i', (string) $result->failureSummary);
    }

    public function test_a_disabled_configuration_returns_a_safe_failure(): void
    {
        $result = FirebaseCloudMessagingSender::fromConfiguration(['enabled' => false])
            ->send($this->message(), 'device-token');

        self::assertFalse($result->accepted);
        self::assertSame(ProviderFailureCategory::AuthConfigurationFailure, $result->failureCategory);
        self::assertSame('FCM is disabled.', $result->failureSummary);
    }

    public function test_boot_configuration_failure_reason_is_surfaced_on_send(): void
    {
        $sender = FirebaseCloudMessagingSender::disabledDueToConfiguration(
            'FCM credentials file is missing or not readable. Check FCM_CREDENTIALS_PATH and that the JSON exists under storage/app/private.'
        );

        self::assertFalse($sender->isReady());
        $result = $sender->send($this->message(), 'device-token');

        self::assertFalse($result->accepted);
        self::assertSame(ProviderFailureCategory::AuthConfigurationFailure, $result->failureCategory);
        self::assertSame(
            'FCM credentials file is missing or not readable. Check FCM_CREDENTIALS_PATH and that the JSON exists under storage/app/private.',
            $result->failureSummary
        );
    }

    public function test_invalid_credentials_do_not_chain_provider_details_into_the_reported_exception(): void
    {
        $credentialsPath = tempnam(sys_get_temp_dir(), 'fcm-secret-');
        self::assertNotFalse($credentialsPath);
        file_put_contents($credentialsPath, 'invalid provider credential content');

        try {
            FirebaseCloudMessagingSender::fromConfiguration([
                'enabled' => true,
                'project_id' => 'test-project',
                'credentials_path' => $credentialsPath,
                'connect_timeout' => 5,
                'request_timeout' => 15,
                'retry_backoff_seconds' => [60],
            ]);

            self::fail('Expected invalid credentials to be rejected.');
        } catch (NotificationConfigurationException $exception) {
            self::assertSame(
                'FCM credentials file could not be loaded. Ensure it is a valid Firebase Admin SDK service-account JSON.',
                $exception->getMessage()
            );
            self::assertNull($exception->getPrevious());
            self::assertStringNotContainsString('invalid provider credential content', $exception->getMessage());
            self::assertStringNotContainsString($credentialsPath, $exception->getMessage());
        } finally {
            unlink($credentialsPath);
        }
    }

    /** @return array<string, array{Throwable, ProviderFailureCategory}> */
    public static function providerFailures(): array
    {
        return [
            'unregistered token' => [new NotFound('sensitive-device-token raw provider detail'), ProviderFailureCategory::PermanentTokenFailure],
            'invalid payload' => [new InvalidMessage('raw provider detail'), ProviderFailureCategory::InvalidPayload],
            'authentication' => [new AuthenticationError('secret'), ProviderFailureCategory::AuthConfigurationFailure],
            'connection timeout' => [new ApiConnectionFailed('raw provider detail'), ProviderFailureCategory::TransientProviderFailure],
            'server unavailable' => [new ServerUnavailable('raw provider detail'), ProviderFailureCategory::TransientProviderFailure],
            'unexpected failure' => [new RuntimeException('raw provider detail'), ProviderFailureCategory::UnknownFailure],
        ];
    }

    private function message(): PushNotificationMessageData
    {
        return new PushNotificationMessageData(
            NotificationType::TestNotification,
            NotificationCategory::Transactional,
            NotificationChannel::PushFcm,
            'عنوان آمن',
            'محتوى آمن',
            ['payload_version' => 1, 'type' => 'TEST_NOTIFICATION'],
        );
    }
}
