<?php

namespace Tests\Unit\Notifications;

use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\PushNotificationMessageData;
use App\Data\Notifications\PushSendResultData;
use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Enums\ProviderFailureCategory;
use Tests\Fakes\Notifications\FakePushNotificationSender;
use Tests\TestCase;

final class PushNotificationSenderFakeTest extends TestCase
{
    public function test_the_container_substitutes_the_test_sender_and_records_a_safe_call(): void
    {
        $sender = app(PushNotificationSender::class);

        self::assertInstanceOf(FakePushNotificationSender::class, $sender);

        $result = $sender->send($this->message(), 'device-token');

        self::assertTrue($result->accepted);
        self::assertSame(1, count($sender->calls));
        self::assertSame(hash('sha256', 'device-token'), $sender->calls[0]['tokenHash']);
    }

    public function test_the_container_fake_returns_a_configured_delivery_failure(): void
    {
        $sender = app(PushNotificationSender::class);
        self::assertInstanceOf(FakePushNotificationSender::class, $sender);
        $sender->respondWith(new PushSendResultData(false, failureCategory: ProviderFailureCategory::TransientProviderFailure));

        $result = $sender->send($this->message(), 'device-token');

        self::assertFalse($result->accepted);
        self::assertSame(ProviderFailureCategory::TransientProviderFailure, $result->failureCategory);
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
