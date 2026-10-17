<?php

namespace App\Data\Notifications;

final readonly class TestNotificationData
{
    public function __construct(
        public string $title,
        public string $body,
        public ?string $imageUrl = null,
    ) {}
}
