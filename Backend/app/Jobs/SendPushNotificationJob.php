<?php

namespace App\Jobs;

use App\Models\BusinessNotification;
use App\Services\Notifications\NotificationDeliveryService;
use App\Services\Notifications\NotificationRetryPolicy;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class SendPushNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 4;

    /** @var list<int> */
    public array $backoff = NotificationRetryPolicy::BACKOFF_SECONDS;

    public function __construct(public readonly int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(NotificationDeliveryService $delivery): void
    {
        $notification = BusinessNotification::query()->find($this->notificationId);

        if ($notification !== null) {
            $delivery->deliver($notification);
        }
    }
}
