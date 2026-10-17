<?php

namespace App\Services\Notifications;

use App\Data\Notifications\PushSendResultData;
use App\Enums\ProviderFailureCategory;

final class NotificationRetryPolicy
{
    /** @var list<int> */
    public const BACKOFF_SECONDS = [60, 300, 900];

    public function retries(PushSendResultData $sendResult): bool
    {
        return $sendResult->failureCategory === ProviderFailureCategory::TransientProviderFailure;
    }
}
