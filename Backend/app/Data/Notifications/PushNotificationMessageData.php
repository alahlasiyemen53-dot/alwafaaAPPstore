<?php

namespace App\Data\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use InvalidArgumentException;

final readonly class PushNotificationMessageData
{
    /** @param array<string, string|int> $payload */
    public function __construct(
        public NotificationType $type,
        public NotificationCategory $category,
        public NotificationChannel $channel,
        public string $title,
        public string $body,
        public array $payload,
    ) {
        if ($category !== $type->category() || ! in_array($channel, $type->allowedChannels(), true)) {
            throw new InvalidArgumentException('Notification type, category, and channel do not match.');
        }

        if (trim($title) === '' || trim($body) === '') {
            throw new InvalidArgumentException('Notification content is required.');
        }

        if (mb_strlen($title) > 120 || mb_strlen($body) > 500) {
            throw new InvalidArgumentException('Notification content exceeds its permitted length.');
        }
    }
}
