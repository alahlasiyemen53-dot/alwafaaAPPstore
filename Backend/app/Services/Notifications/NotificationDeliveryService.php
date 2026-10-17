<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\PushNotificationMessageData;
use App\Data\Notifications\PushSendResultData;
use App\Enums\NotificationStatus;
use App\Enums\ProviderFailureCategory;
use App\Models\BusinessNotification;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryAttempt;
use App\Models\PushDevice;
use Illuminate\Support\Facades\DB;

final class NotificationDeliveryService
{
    public function __construct(
        private readonly PushDeviceEligibilityService $eligibility,
        private readonly PushNotificationSender $sender,
        private readonly PushDeviceLifecycleService $devices,
        private readonly NotificationRetryPolicy $retryPolicy,
    ) {}

    public function deliver(BusinessNotification $notification): void
    {
        $recipient = $notification->recipient;

        if (! $recipient instanceof Customer) {
            $this->markFailed(
                $notification,
                ProviderFailureCategory::UnknownFailure,
                'لا يمكن إرسال الإشعار: المستلم ليس عميلاً صالحاً.'
            );

            return;
        }

        $eligibleDevices = $this->eligibility->forCustomer($recipient);

        $targetPlatform = $notification->payload['target_platform'] ?? null;
        if (is_string($targetPlatform) && $targetPlatform !== '' && $targetPlatform !== 'all') {
            $eligibleDevices = $eligibleDevices->filter(function (PushDevice $device) use ($targetPlatform) {
                $platform = $device->platform instanceof \App\Enums\PushDevicePlatform
                    ? $device->platform->value
                    : (string) $device->platform;

                return strtolower($platform) === strtolower($targetPlatform);
            });
        }

        if ($eligibleDevices->isEmpty()) {
            $this->markFailed(
                $notification,
                ProviderFailureCategory::UnknownFailure,
                'لا يوجد جهاز إشعارات نشط مسجّل لهذا العميل. افتح التطبيق وسجّل الدخول مع السماح بالإشعارات.'
            );

            return;
        }

        $hasTransientFailure = false;

        foreach ($eligibleDevices as $device) {
            $hasTransientFailure = $this->deliverToDevice($notification, $device) || $hasTransientFailure;
        }

        $this->updateNotificationStatus($notification);

        if ($hasTransientFailure) {
            throw new TransientNotificationDeliveryException('Notification delivery will be retried.');
        }
    }

    private function deliverToDevice(BusinessNotification $notification, PushDevice $device): bool
    {
        $delivery = $this->deliveryFor($notification, $device);

        if (! $this->shouldSend($delivery)) {
            return false;
        }

        $attempt = $this->startAttempt($delivery);

        if ($attempt === null) {
            return false;
        }

        $sendResult = $this->sender->send($this->messageFor($notification), (string) $device->token);
        $this->recordResult($delivery, $attempt, $sendResult);

        return $this->retryPolicy->retries($sendResult);
    }

    private function deliveryFor(BusinessNotification $notification, PushDevice $device): NotificationDelivery
    {
        return NotificationDelivery::query()->firstOrCreate([
            'business_notification_id' => $notification->id,
            'push_device_id' => $device->id,
        ], [
            'channel' => $notification->channel,
            'provider' => 'fcm',
            'status' => NotificationStatus::Pending,
        ]);
    }

    private function shouldSend(NotificationDelivery $delivery): bool
    {
        return $delivery->status !== NotificationStatus::Sent
            && ($delivery->status !== NotificationStatus::Failed || $delivery->retryable);
    }

    private function startAttempt(NotificationDelivery $delivery): ?NotificationDeliveryAttempt
    {
        return DB::transaction(function () use ($delivery): ?NotificationDeliveryAttempt {
            $lockedDelivery = NotificationDelivery::query()->lockForUpdate()->findOrFail($delivery->id);

            if (! $this->shouldSend($lockedDelivery)) {
                return null;
            }

            $lockedDelivery->status = NotificationStatus::Queued;
            $lockedDelivery->save();

            return $lockedDelivery->attempts()->create([
                'attempt_number' => $lockedDelivery->attempts()->count() + 1,
                'status' => NotificationStatus::Queued,
                'attempted_at' => now(),
            ]);
        });
    }

    private function messageFor(BusinessNotification $notification): PushNotificationMessageData
    {
        return new PushNotificationMessageData(
            $notification->type,
            $notification->category,
            $notification->channel,
            $notification->title,
            $notification->body,
            $notification->payload,
        );
    }

    private function recordResult(NotificationDelivery $delivery, NotificationDeliveryAttempt $attempt, PushSendResultData $sendResult): void
    {
        $status = $sendResult->accepted ? NotificationStatus::Sent : NotificationStatus::Failed;
        $retryable = ! $sendResult->accepted && $this->retryPolicy->retries($sendResult);
        $fields = [
            'status' => $status,
            'retryable' => $retryable,
            'provider_reference' => $sendResult->providerReference,
            'failure_category' => $sendResult->failureCategory,
            'failure_summary' => $sendResult->failureSummary,
        ];

        $attempt->update($fields);
        $delivery->fill($fields);
        $delivery->sent_at = $sendResult->accepted ? now() : null;
        $delivery->failed_at = $sendResult->accepted ? null : now();
        $delivery->save();

        if ($sendResult->failureCategory === ProviderFailureCategory::PermanentTokenFailure) {
            $this->devices->invalidate($delivery->pushDevice, 'provider_rejected_token');
        }
    }

    private function updateNotificationStatus(BusinessNotification $notification): void
    {
        $deliveries = $notification->deliveries();

        if ($deliveries->where('status', NotificationStatus::Sent)->exists()) {
            $notification->status = NotificationStatus::Sent;
            $notification->sent_at = now();
            $notification->save();

            return;
        }

        if (! $deliveries->where('retryable', true)->exists()) {
            $failedDelivery = $notification->deliveries()
                ->where('status', NotificationStatus::Failed)
                ->latest('id')
                ->first();

            $this->markFailed(
                $notification,
                $failedDelivery?->failure_category ?? ProviderFailureCategory::UnknownFailure,
                $failedDelivery?->failure_summary ?? 'فشل إرسال الإشعار إلى كل الأجهزة المستهدفة.'
            );
        }
    }

    private function markFailed(
        BusinessNotification $notification,
        ProviderFailureCategory $category,
        string $summary,
    ): void {
        $notification->status = NotificationStatus::Failed;
        $notification->failed_at = now();
        $notification->failure_category = $category;
        $notification->failure_summary = $summary;
        $notification->save();
    }
}
