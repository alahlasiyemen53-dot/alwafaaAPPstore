<?php

namespace App\Console\Commands;

use App\Infrastructure\Notifications\NotificationConfigurationException;
use Illuminate\Console\Command;
use Kreait\Firebase\Factory;
use Kreait\Firebase\Http\HttpClientOptions;
use Kreait\Firebase\Messaging\CloudMessage;
use Kreait\Firebase\Messaging\Notification;
use Throwable;

/**
 * Sends a one-off broadcast to the Flutter app general topic (lamma_general).
 * Uses kreait/firebase-php — same stack as FirebaseCloudMessagingSender.
 */
final class SendGeneralTopicNotificationCommand extends Command
{
    protected $signature = 'notifications:send-general
                            {--title=مرحبا بك في لمة :Notification title}
                            {--body=هذا اول اشعار عام من خادم لمة عبر Firebase :Notification body}
                            {--topic=lamma_general :FCM topic name}';

    protected $description = 'Send a general FCM topic notification via Kreait (smoke test).';

    public function handle(): int
    {
        $configuration = config('services.fcm');

        try {
            NotificationConfigurationException::assertFcmIsConfigured($configuration);
        } catch (NotificationConfigurationException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        if (! ($configuration['enabled'] ?? false)) {
            $this->error('FCM is disabled. Set FCM_ENABLED=true in .env');

            return self::FAILURE;
        }

        $credentialsPath = $configuration['credentials_path'] ?? null;
        if (! is_string($credentialsPath) || ! is_readable($credentialsPath)) {
            $this->error('FCM credentials are unavailable.');

            return self::FAILURE;
        }

        $topic = trim((string) $this->option('topic'));
        $title = (string) $this->option('title');
        $body = (string) $this->option('body');

        if ($topic === '' || ! preg_match('/^[a-zA-Z0-9\-_.~%]+$/', $topic)) {
            $this->error("Invalid FCM topic [{$topic}]. Use letters, numbers, - _ . ~ % only.");

            return self::FAILURE;
        }

        try {
            $options = HttpClientOptions::default()
                ->withConnectTimeout((float) $configuration['connect_timeout'])
                ->withTimeout((float) $configuration['request_timeout']);

            $messaging = (new Factory)
                ->withProjectId($configuration['project_id'])
                ->withServiceAccount($credentialsPath)
                ->withHttpClientOptions($options)
                ->createMessaging();

            $message = CloudMessage::new()
                ->toTopic($topic)
                ->withNotification(Notification::create($title, $body))
                ->withData([
                    'type' => 'general',
                    'source' => 'notifications:send-general',
                ]);

            $result = $messaging->send($message);
            $messageName = is_array($result) ? ($result['name'] ?? json_encode($result)) : (string) $result;

            $this->info("Sent general notification to topic [{$topic}].");
            $this->line("Firebase message: {$messageName}");

            return self::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Failed to send general notification: '.$exception->getMessage());

            return self::FAILURE;
        }
    }
}
