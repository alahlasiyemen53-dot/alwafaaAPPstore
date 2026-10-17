<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Models\BusinessNotification;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\NotificationDeliveryAttempt;
use App\Models\PushDevice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class NotificationSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_device_tokens_are_unique_and_never_serialized(): void
    {
        $customer = $this->customer();
        $token = 'fcm-registration-token';
        $device = $this->device($customer, $token);

        self::assertSame($token, $device->fresh()->token);
        self::assertNotSame($token, DB::table('push_devices')->value('token'));
        self::assertArrayNotHasKey('token', $device->toArray());

        $this->expectException(QueryException::class);
        $this->device($customer, $token);
    }

    public function test_device_ownership_and_notification_recipient_are_required(): void
    {
        $this->expectException(QueryException::class);
        DB::table('push_devices')->insert([
            'provider' => 'fcm',
            'token_hash' => str_repeat('c', 64),
            'platform' => PushDevicePlatform::Android->value,
            'status' => PushDeviceStatus::Active->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_notification_recipient_is_required(): void
    {
        $this->expectException(QueryException::class);
        DB::table('business_notifications')->insert([
            'type' => NotificationType::OrderConfirmed->value,
            'category' => NotificationCategory::Transactional->value,
            'channel' => NotificationChannel::PushFcm->value,
            'status' => NotificationStatus::Pending->value,
            'title' => 'تم تأكيد الطلب',
            'body' => 'تم تأكيد طلبك.',
            'payload' => json_encode(['payload_version' => 1]),
            'payload_version' => 1,
            'idempotency_key' => str_repeat('f', 64),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_logical_notifications_are_idempotent_and_cast_enums(): void
    {
        $customer = $this->customer();
        $notification = $this->notification($customer, str_repeat('a', 64));

        self::assertSame(NotificationType::OrderConfirmed, $notification->type);
        self::assertSame(NotificationCategory::Transactional, $notification->category);
        self::assertSame(NotificationChannel::PushFcm, $notification->channel);
        self::assertSame(NotificationStatus::Pending, $notification->status);

        $this->expectException(QueryException::class);
        $this->notification($customer, str_repeat('a', 64));
    }

    public function test_notification_history_is_retained_when_delete_is_attempted(): void
    {
        $customer = $this->customer();
        $device = $this->device($customer, 'another-fcm-registration-token');
        $notification = $this->notification($customer, str_repeat('b', 64));
        $delivery = NotificationDelivery::query()->create([
            'business_notification_id' => $notification->id,
            'push_device_id' => $device->id,
            'channel' => NotificationChannel::PushFcm,
            'provider' => 'fcm',
            'status' => NotificationStatus::Pending,
        ]);
        $attempt = NotificationDeliveryAttempt::query()->create([
            'notification_delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'status' => NotificationStatus::Pending,
            'attempted_at' => now(),
        ]);

        $deleteFailed = false;

        try {
            $notification->delete();
        } catch (QueryException) {
            $deleteFailed = true;
        }

        self::assertTrue($deleteFailed);
        self::assertDatabaseHas('notification_deliveries', ['id' => $delivery->id]);
        self::assertDatabaseHas('notification_delivery_attempts', ['id' => $attempt->id]);
    }

    public function test_delivery_and_attempt_keys_are_unique(): void
    {
        $customer = $this->customer();
        $device = $this->device($customer, 'unique-fcm-registration-token');
        $notification = $this->notification($customer, str_repeat('d', 64));
        $delivery = $this->delivery($notification, $device);

        $this->expectException(QueryException::class);
        NotificationDelivery::query()->create([
            'business_notification_id' => $notification->id,
            'push_device_id' => $device->id,
            'channel' => NotificationChannel::PushFcm,
            'provider' => 'fcm',
            'status' => NotificationStatus::Pending,
        ]);
    }

    public function test_delivery_attempt_numbers_are_unique_per_delivery(): void
    {
        $customer = $this->customer();
        $device = $this->device($customer, 'attempt-fcm-registration-token');
        $delivery = $this->delivery($this->notification($customer, str_repeat('g', 64)), $device);
        NotificationDeliveryAttempt::query()->create([
            'notification_delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'status' => NotificationStatus::Pending,
            'attempted_at' => now(),
        ]);

        $this->expectException(QueryException::class);
        NotificationDeliveryAttempt::query()->create([
            'notification_delivery_id' => $delivery->id,
            'attempt_number' => 1,
            'status' => NotificationStatus::Pending,
            'attempted_at' => now(),
        ]);
    }

    public function test_lifecycle_and_payload_security_fields_cannot_be_mass_assigned(): void
    {
        $device = new PushDevice([
            'device_identifier' => 'approved-device',
            'provider' => 'untrusted-provider',
            'token' => 'untrusted-token',
            'token_hash' => str_repeat('e', 64),
            'status' => PushDeviceStatus::Invalidated,
            'invalidated_at' => now(),
            'invalidation_reason' => 'untrusted-reason',
        ]);
        $notification = new BusinessNotification([
            'status' => NotificationStatus::Sent,
            'payload' => ['untrusted' => true],
            'queued_at' => now(),
            'failure_summary' => 'untrusted-summary',
        ]);

        self::assertSame('approved-device', $device->device_identifier);
        self::assertNull($device->provider);
        self::assertNull($device->token);
        self::assertNull($device->token_hash);
        self::assertNull($device->status);
        self::assertNull($device->invalidated_at);
        self::assertNull($device->invalidation_reason);
        self::assertNull($notification->status);
        self::assertNull($notification->payload);
        self::assertNull($notification->queued_at);
        self::assertNull($notification->failure_summary);
    }

    private function customer(): Customer
    {
        return Customer::query()->create([
            'name' => 'عميل إشعارات',
            'phone' => fake()->unique()->numerify('77#######'),
            'normalized_phone' => fake()->unique()->numerify('77#######'),
            'is_active' => true,
        ]);
    }

    private function device(Customer $customer, string $token): PushDevice
    {
        $device = new PushDevice([
            'platform' => PushDevicePlatform::Android,
        ]);
        $device->owner()->associate($customer);
        $device->provider = 'fcm';
        $device->status = PushDeviceStatus::Active;
        $device->last_registered_at = now();
        $device->token = $token;
        $device->token_hash = hash('sha256', $token);
        $device->save();

        return $device;
    }

    private function notification(Customer $customer, string $idempotencyKey): BusinessNotification
    {
        $notification = new BusinessNotification([
            'type' => NotificationType::OrderConfirmed,
            'category' => NotificationCategory::Transactional,
            'channel' => NotificationChannel::PushFcm,
            'title' => 'تم تأكيد الطلب',
            'body' => 'تم تأكيد طلبك.',
            'payload_version' => 1,
            'business_reference' => 'ORD-1001',
            'idempotency_key' => $idempotencyKey,
        ]);
        $notification->recipient()->associate($customer);
        $notification->status = NotificationStatus::Pending;
        $notification->payload = ['payload_version' => 1, 'type' => 'ORDER_CONFIRMED'];
        $notification->save();

        return $notification;
    }

    private function delivery(BusinessNotification $notification, PushDevice $device): NotificationDelivery
    {
        return NotificationDelivery::query()->create([
            'business_notification_id' => $notification->id,
            'push_device_id' => $device->id,
            'channel' => NotificationChannel::PushFcm,
            'provider' => 'fcm',
            'status' => NotificationStatus::Pending,
        ]);
    }
}
