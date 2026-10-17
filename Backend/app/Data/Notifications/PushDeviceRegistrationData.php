<?php

namespace App\Data\Notifications;

use App\Enums\PushDevicePlatform;

final readonly class PushDeviceRegistrationData
{
    public function __construct(
        public string $token,
        public PushDevicePlatform $platform,
        public ?string $deviceIdentifier = null,
        public ?string $locale = null,
        public ?string $appVersion = null,
    ) {}
}
