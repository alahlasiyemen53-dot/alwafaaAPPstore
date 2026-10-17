<?php

namespace App\Jobs;

use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\PushNotificationMessageData;
use App\Enums\NotificationStatus;
use App\Models\BusinessNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

final class BroadcastTopicNotificationJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $notificationId)
    {
        $this->onQueue('notifications');
    }

    public function handle(PushNotificationSender $pushSender): void
    {
        $notification = BusinessNotification::query()->find($this->notificationId);

        if ($notification === null || $notification->status === NotificationStatus::Cancelled) {
            return;
        }

        $topic = (string) config('services.fcm.general_topic', 'lamma_general');
        $payload = is_array($notification->payload) ? $notification->payload : [];

        $message = new PushNotificationMessageData(
            $notification->type,
            $notification->category,
            $notification->channel,
            $notification->title,
            $notification->body,
            $payload,
        );

        $result = $pushSender->sendToTopic($message, $topic);

        if ($result->accepted) {
            $notification->status = NotificationStatus::Sent;
            $notification->sent_at = now();
        } else {
            $notification->status = NotificationStatus::Failed;
            $notification->failed_at = now();
            $notification->failure_category = $result->failureCategory;
            $notification->failure_summary = $result->failureSummary;
        }

        $notification->save();
    }
}
