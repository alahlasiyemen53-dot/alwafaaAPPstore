<?php

namespace App\Services\Notifications;

use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\ComposeAdminNotificationInput;
use App\Data\Notifications\PushNotificationMessageData;
use App\Data\Notifications\TestNotificationData;
use App\Enums\NotificationStatus;
use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Jobs\BroadcastTopicNotificationJob;
use App\Jobs\SendPushNotificationJob;
use App\Models\BusinessNotification;
use App\Models\Customer;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AdminNotificationComposer
{
    public function __construct(
        private readonly NotificationMessageFactory $messageFactory,
        private readonly PushNotificationSender $pushSender,
    ) {}

    /**
     * @return array{mode: 'topic'|'devices', queued: int, skipped: int, image_url: ?string, topic: ?string, scheduled: bool}
     */
    public function compose(ComposeAdminNotificationInput $input, ?User $actor = null): array
    {
        $imageUrl = $this->storeOptionalImage($input->image);

        if ($input->audience === 'all' && $input->platform === 'all' && $input->governorateId === null) {
            return $this->broadcastToGeneralTopic(
                $input->title,
                $input->body,
                $imageUrl,
                $actor,
                $input->destinationType,
                $input->destinationId,
                $input->scheduledAt,
            );
        }

        return $this->queueForAudience($input, $imageUrl);
    }

    /**
     * @return array{mode: 'topic', queued: int, skipped: int, image_url: ?string, topic: string, scheduled: bool}
     */
    private function broadcastToGeneralTopic(
        string $title,
        string $body,
        ?string $imageUrl,
        ?User $actor,
        ?string $destinationType = null,
        ?string $destinationId = null,
        ?Carbon $scheduledAt = null,
    ): array {
        if ($actor === null) {
            throw new InvalidArgumentException('Topic broadcast requires an authenticated admin.');
        }

        $topic = (string) config('services.fcm.general_topic', 'lamma_general');
        $message = $this->messageFactory->broadcast(
            $title,
            $body,
            $imageUrl,
            $destinationType,
            $destinationId,
        );
        $message = new PushNotificationMessageData(
            $message->type,
            $message->category,
            $message->channel,
            $message->title,
            $message->body,
            array_merge($message->payload, [
                'delivery_mode' => 'topic',
                'topic' => $topic,
            ]),
        );

        $batch = Str::lower(Str::random(10));
        $notification = new BusinessNotification([
            'type' => $message->type,
            'category' => $message->category,
            'channel' => $message->channel,
            'title' => $message->title,
            'body' => $message->body,
            'payload_version' => (int) ($message->payload['payload_version'] ?? 1),
            'business_reference' => 'TOPIC-'.Str::upper($batch),
            'idempotency_key' => 'admin-topic:'.$batch,
            'scheduled_at' => $scheduledAt,
        ]);

        $notification->recipient()->associate($actor);
        $notification->payload = $message->payload;
        $notification->queued_at = now();

        if ($scheduledAt !== null) {
            $notification->status = NotificationStatus::Queued;
            $notification->save();

            BroadcastTopicNotificationJob::dispatch($notification->id)
                ->delay($scheduledAt)
                ->afterCommit();

            return [
                'mode' => 'topic',
                'queued' => 1,
                'skipped' => 0,
                'image_url' => $imageUrl,
                'topic' => $topic,
                'scheduled' => true,
            ];
        }

        $result = $this->pushSender->sendToTopic($message, $topic);

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

        return [
            'mode' => 'topic',
            'queued' => $result->accepted ? 1 : 0,
            'skipped' => 0,
            'image_url' => $imageUrl,
            'topic' => $topic,
            'scheduled' => false,
        ];
    }

    /**
     * @return array{mode: 'devices', queued: int, skipped: int, image_url: ?string, topic: null, scheduled: bool}
     */
    private function queueForAudience(
        ComposeAdminNotificationInput $input,
        ?string $imageUrl,
    ): array {
        $message = $this->messageFactory->broadcast(
            $input->title,
            $input->body,
            $imageUrl,
            $input->destinationType,
            $input->destinationId,
        );
        $batch = Str::lower(Str::random(10));
        $recipients = $this->resolveRecipients($input);

        $queued = 0;
        foreach ($recipients as $customer) {
            $this->queueForCustomer($customer, $message, $batch, $input->scheduledAt, $input->platform);
            $queued++;
        }

        return [
            'mode' => 'devices',
            'queued' => $queued,
            'skipped' => $queued === 0 ? 1 : 0,
            'image_url' => $imageUrl,
            'topic' => null,
            'scheduled' => $input->scheduledAt !== null,
        ];
    }

    private function storeOptionalImage(?UploadedFile $image): ?string
    {
        if ($image === null) {
            return null;
        }

        $path = $image->store('notifications/compose', 'public');

        return url(Storage::disk('public')->url($path));
    }

    /** @return Collection<int, Customer> */
    public function resolveRecipients(ComposeAdminNotificationInput $input): Collection
    {
        $query = Customer::query()
            ->where('is_active', true);

        if ($input->platform === 'ios' || $input->platform === 'android') {
            $query->whereHas('pushDevices', function ($q) use ($input) {
                $q->where('status', PushDeviceStatus::Active)
                    ->where('platform', $input->platform);
            });
        } else {
            $query->whereHas('pushDevices', fn ($q) => $q->where('status', PushDeviceStatus::Active));
        }

        if ($input->governorateId !== null) {
            $govId = $input->governorateId;
            match ($input->governorateTarget) {
                'registered' => $query->where('governorate_id', $govId),
                'address' => $query->whereHas('addresses', fn ($q) => $q->where('governorate_id', $govId)),
                default => $query->where(function ($q) use ($govId) {
                    $q->where('governorate_id', $govId)
                        ->orWhereHas('addresses', fn ($sub) => $sub->where('governorate_id', $govId));
                }),
            };
        }

        if ($input->audience === 'customer') {
            if ($input->customerId === null) {
                return collect();
            }

            return $query->whereKey($input->customerId)->get();
        }

        if ($input->audience === 'segment') {
            return match ($input->segment) {
                'active_30_days' => $query->whereHas('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))->get(),
                'inactive_30_days' => $query->whereDoesntHave('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))->get(),
                'abandoned_cart' => $query->whereHas('cart.items')->get(),
                'with_orders' => $query->whereHas('orders')->get(),
                default => collect(),
            };
        }

        return $query->get();
    }

    private function queueForCustomer(
        Customer $customer,
        PushNotificationMessageData $message,
        string $batch,
        ?Carbon $scheduledAt = null,
        string $platform = 'all',
    ): void {
        $payload = $message->payload;
        if ($platform !== 'all') {
            $payload['target_platform'] = $platform;
        }

        $notification = new BusinessNotification([
            'type' => $message->type,
            'category' => $message->category,
            'channel' => $message->channel,
            'title' => $message->title,
            'body' => $message->body,
            'payload_version' => (int) ($message->payload['payload_version'] ?? 1),
            'business_reference' => 'ADMIN-'.Str::upper($batch),
            'idempotency_key' => 'admin-compose:'.$batch.':'.$customer->id,
            'scheduled_at' => $scheduledAt,
        ]);
        $notification->recipient()->associate($customer);
        $notification->payload = $payload;
        $notification->status = NotificationStatus::Queued;
        $notification->queued_at = now();
        $notification->save();

        $job = SendPushNotificationJob::dispatch($notification->id)
            ->onQueue('notifications');

        if ($scheduledAt !== null) {
            $job->delay($scheduledAt);
        }

        $job->afterCommit();
    }

    /**
     * @return array<string, int>
     */
    public function getSegmentStatistics(): array
    {
        $baseQuery = Customer::query()
            ->where('is_active', true)
            ->whereHas('pushDevices', fn ($query) => $query->where('status', PushDeviceStatus::Active));

        return [
            'all_active_devices' => (clone $baseQuery)->count(),
            'active_30_days' => (clone $baseQuery)->whereHas('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))->count(),
            'inactive_30_days' => (clone $baseQuery)->whereDoesntHave('orders', fn ($q) => $q->where('created_at', '>=', now()->subDays(30)))->count(),
            'abandoned_cart' => (clone $baseQuery)->whereHas('cart.items')->count(),
            'with_orders' => (clone $baseQuery)->whereHas('orders')->count(),
            'ios_users' => (clone $baseQuery)->whereHas('pushDevices', fn ($q) => $q->where('status', PushDeviceStatus::Active)->where('platform', PushDevicePlatform::Ios))->count(),
            'android_users' => (clone $baseQuery)->whereHas('pushDevices', fn ($q) => $q->where('status', PushDeviceStatus::Active)->where('platform', PushDevicePlatform::Android))->count(),
        ];
    }
}
