<?php

namespace App\Data\Notifications;

use App\Enums\ProviderFailureCategory;

final readonly class PushSendResultData
{
    public function __construct(
        public bool $accepted,
        public ?string $providerReference = null,
        public ?ProviderFailureCategory $failureCategory = null,
        public ?string $failureSummary = null,
    ) {}
}
