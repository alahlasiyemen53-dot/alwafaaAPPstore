<?php

namespace App\Services\Notifications;

use App\Data\Notifications\PushNotificationMessageData;
use App\Enums\NotificationStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\BusinessNotification;
use App\Models\Customer;

final class NotificationScheduler
{
    public function scheduleOrderNotification(
        Customer $recipient,
        PushNotificationMessageData $message,
        string $idempotencyKey,
        string $businessReference,
    ): BusinessNotification {
        $notification = BusinessNotification::query()->where('idempotency_key', $idempotencyKey)->first();

        if ($notification !== null) {
            return $notification;
        }

        $notification = new BusinessNotification([
            'type' => $message->type,
            'category' => $message->category,
            'channel' => $message->channel,
            'title' => $message->title,
            'body' => $message->body,
            'payload_version' => $message->payload['payload_version'],
            'business_reference' => $businessReference,
            'idempotency_key' => $idempotencyKey,
        ]);
        $notification->recipient()->associate($recipient);
        $notification->payload = $message->payload;
        $notification->status = NotificationStatus::Queued;
        $notification->queued_at = now();
        $notification->save();

        SendPushNotificationJob::dispatch($notification->id)
            ->onQueue('notifications')
            ->afterCommit();

        return $notification;
    }
}
