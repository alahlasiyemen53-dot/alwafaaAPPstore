<?php

namespace App\Data\Notifications;

use Carbon\Carbon;
use Illuminate\Http\UploadedFile;

final readonly class ComposeAdminNotificationInput
{
    public function __construct(
        public string $audience,
        public string $title,
        public string $body,
        public ?int $customerId = null,
        public ?UploadedFile $image = null,
        public ?string $destinationType = null,
        public ?string $destinationId = null,
        public ?string $segment = null,
        public ?string $sendType = 'now',
        public ?Carbon $scheduledAt = null,
        public ?string $platform = 'all',
        public ?int $governorateId = null,
        public ?string $governorateTarget = 'any',
    ) {}
}
