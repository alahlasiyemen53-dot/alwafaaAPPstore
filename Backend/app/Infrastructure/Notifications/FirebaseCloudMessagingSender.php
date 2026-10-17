<?php

namespace App\Infrastructure\Notifications;

use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\PushNotificationMessageData;
use App\Data\Notifications\PushSendResultData;
use App\Enums\ProviderFailureCategory;
use Kreait\Firebase\Contract\Messaging;
use Kreait\Firebase\Exception\Messaging\ApiConnectionFailed;
use Kreait\Firebase\Exception\Messaging\AuthenticationError;
use Kreait\Firebase\Exception\Messaging\InvalidArgument;
use Kreait\Firebase\Exception\Messaging\InvalidMessage;
use Kreait\Firebase\Exception\Messaging\NotFound;
use Kreait\Firebase\Exception\Messaging\QuotaExceeded;
use Kreait\Firebase\Exception\Messaging\ServerError;
use Kreait\Firebase\Exception\Messaging\ServerUnavailable;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Kreait\Firebase\Messaging\ApnsConfig;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

final class FirebaseCloudMessagingSender implements PushNotificationSender
{
    public function __construct(
        private readonly ?Messaging $messaging,
        private readonly ?string $disabledReason = null,
    ) {}

    /** @param array<string, mixed> $configuration */
    public static function fromConfiguration(array $configuration): self
    {
        NotificationConfigurationException::assertFcmIsConfigured($configuration);

        if (! ($configuration['enabled'] ?? false)) {
            return new self(null, 'FCM is disabled.');
        }

        $credentialsPath = self::resolveCredentialsPath($configuration['credentials_path'] ?? null);

        if ($credentialsPath === null) {
            throw new NotificationConfigurationException('FCM credentials path is not configured.');
        }

        if (! is_readable($credentialsPath)) {
            throw new NotificationConfigurationException(
                'FCM credentials file is missing or not readable. Check FCM_CREDENTIALS_PATH and that the JSON exists under storage/app/private.'
            );
        }

        try {
            return new self(self::messagingFor($configuration, $credentialsPath));
        } catch (Throwable) {
            throw new NotificationConfigurationException(
                'FCM credentials file could not be loaded. Ensure it is a valid Firebase Admin SDK service-account JSON.'
            );
        }
    }

    public static function disabledDueToConfiguration(string $safeReason): self
    {
        $reason = trim($safeReason);

        return new self(
            null,
            $reason !== '' ? $reason : 'FCM configuration failed at application boot.'
        );
    }

    public function isReady(): bool
    {
        return $this->messaging !== null;
    }

    public function configurationIssue(): ?string
    {
        return $this->messaging === null ? ($this->disabledReason ?? 'FCM is disabled.') : null;
    }

    private static function resolveCredentialsPath(mixed $path): ?string
    {
        if (! is_string($path) || trim($path) === '') {
            return null;
        }

        $path = trim(str_replace('\\', '/', $path));
        $isAbsolute = str_starts_with($path, '/')
            || (strlen($path) > 2 && ctype_alpha($path[0]) && $path[1] === ':' && $path[2] === '/');

        if (! $isAbsolute) {
            $path = base_path($path);
        }

        $resolved = realpath($path);

        return $resolved !== false ? $resolved : $path;
    }

    public function send(PushNotificationMessageData $message, string $deviceToken): PushSendResultData
    {
        return $this->dispatch($this->firebaseMessage($message)->toToken($deviceToken));
    }

    public function sendToTopic(PushNotificationMessageData $message, string $topic): PushSendResultData
    {
        $topic = trim($topic);
        if ($topic === '' || ! preg_match('/^[a-zA-Z0-9\-_.~%]+$/', $topic)) {
            return $this->failure(ProviderFailureCategory::InvalidPayload, 'FCM topic name is invalid.');
        }

        return $this->dispatch($this->firebaseMessage($message)->toTopic($topic));
    }

    private function dispatch(CloudMessage $cloudMessage): PushSendResultData
    {
        if ($this->messaging === null) {
            return $this->failure(
                ProviderFailureCategory::AuthConfigurationFailure,
                $this->disabledReason ?? 'FCM is disabled.'
            );
        }

        try {
            $providerResponse = $this->messaging->send($cloudMessage);

            return new PushSendResultData(true, $providerResponse['name'] ?? null);
        } catch (Throwable $exception) {
            return $this->mapFailure($exception);
        }
    }

    private function firebaseMessage(PushNotificationMessageData $message): CloudMessage
    {
        $notification = Notification::create($message->title, $message->body);
        $imageUrl = $message->payload['image_url'] ?? null;
        if (is_string($imageUrl) && trim($imageUrl) !== '') {
            $notification = $notification->withImageUrl(trim($imageUrl));
        }

        $data = [];
        foreach ($message->payload as $key => $value) {
            $data[$key] = (string) $value;
        }

        $apnsPayload = array_merge($data, [
            'aps' => [
                'sound' => 'default',
                'badge' => 1,
                'content-available' => 1,
            ],
        ]);

        return CloudMessage::new()
            ->withNotification($notification)
            ->withData($data)
            ->withApnsConfig(ApnsConfig::fromArray([
                'headers' => [
                    'apns-priority' => '10',
                ],
                'payload' => $apnsPayload,
            ]));
    }

    /** @param array<string, mixed> $configuration */
    private static function messagingFor(array $configuration, string $credentialsPath): Messaging
    {
        $options = HttpClientOptions::default()
            ->withConnectTimeout((float) $configuration['connect_timeout'])
            ->withTimeout((float) $configuration['request_timeout']);

        return (new Factory)
            ->withProjectId($configuration['project_id'])
            ->withServiceAccount($credentialsPath)
            ->withHttpClientOptions($options)
            ->createMessaging();
    }

    private function mapFailure(Throwable $exception): PushSendResultData
    {
        return match (true) {
            $exception instanceof NotFound => $this->failure(ProviderFailureCategory::PermanentTokenFailure, 'FCM rejected the device token.'),
            $exception instanceof InvalidMessage, $exception instanceof InvalidArgument => $this->failure(ProviderFailureCategory::InvalidPayload, 'FCM rejected the notification payload.'),
            $exception instanceof AuthenticationError => $this->failure(ProviderFailureCategory::AuthConfigurationFailure, 'FCM authentication failed.'),
            $exception instanceof ApiConnectionFailed,
            $exception instanceof ServerUnavailable,
            $exception instanceof ServerError,
            $exception instanceof QuotaExceeded => $this->failure(ProviderFailureCategory::TransientProviderFailure, 'FCM is temporarily unavailable.'),
            default => $this->failure(ProviderFailureCategory::UnknownFailure, 'FCM delivery failed.'),
        };
    }

    private function failure(ProviderFailureCategory $category, string $summary): PushSendResultData
    {
        return new PushSendResultData(false, failureCategory: $category, failureSummary: $summary);
    }
}
