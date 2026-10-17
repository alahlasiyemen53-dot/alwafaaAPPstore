<?php

namespace Tests\Feature\Notifications;

use App\Contracts\Notifications\PushNotificationSender;
use App\Data\Notifications\PushSendResultData;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\OrderStatus;
use App\Enums\ProviderFailureCategory;
use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\BusinessNotification;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\PushDevice;
use App\Services\Notifications\NotificationMessageFactory;
use App\Services\Notifications\NotificationScheduler;
use App\Services\Notifications\TransientNotificationDeliveryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\Fakes\Notifications\FakePushNotificationSender;
use Tests\TestCase;

final class NotificationQueueLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private FakePushNotificationSender $sender;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->sender = new FakePushNotificationSender;
        $this->app->instance(PushNotificationSender::class, $this->sender);
    }

    public function test_scheduling_is_idempotent_and_creates_single_logical_record(): void
    {
        Queue::fake();

        $customer = $this->createCustomer();
        $scheduler = $this->app->make(NotificationScheduler::class);
        $factory = $this->app->make(NotificationMessageFactory::class);

        $message = $factory->forOrder(NotificationType::OrderConfirmed, 'ORD-1001', OrderStatus::Confirmed);
        $key = 'order:1:order_confirmed:v1';

        $first = $scheduler->scheduleOrderNotification($customer, $message, $key, 'ORD-1001');
        $second = $scheduler->scheduleOrderNotification($customer, $message, $key, 'ORD-1001');

        self::assertSame($first->id, $second->id);
        self::assertSame(1, BusinessNotification::query()->count());
        self::assertSame(NotificationStatus::Queued, $first->status);
        Queue::assertPushed(SendPushNotificationJob::class, 1);
    }

    public function test_worker_delivers_to_all_eligible_devices_and_marks_sent(): void
    {
        $customer = $this->createCustomer();
        $device1 = $this->createDevice($customer, 'token-device-1');
        $device2 = $this->createDevice($customer, 'token-device-2');

        $scheduler = $this->app->make(NotificationScheduler::class);
        $factory = $this->app->make(NotificationMessageFactory::class);
        $message = $factory->forOrder(NotificationType::OrderConfirmed, 'ORD-1001', OrderStatus::Confirmed);

        $notification = $scheduler->scheduleOrderNotification($customer, $message, 'order:1:order_confirmed:v1', 'ORD-1001');

        // Execute Job
        $job = new SendPushNotificationJob($notification->id);
        $this->app->call([$job, 'handle']);

        $notification->refresh();
        self::assertSame(NotificationStatus::Sent, $notification->status);
        self::assertNotNull($notification->sent_at);
        self::assertCount(2, $this->sender->calls);

        $deliveries = NotificationDelivery::query()->where('business_notification_id', $notification->id)->get();
        self::assertCount(2, $deliveries);
        foreach ($deliveries as $del) {
            self::assertSame(NotificationStatus::Sent, $del->status);
            self::assertSame(1, $del->attempts()->count());
        }
    }

    public function test_inactive_device_is_skipped_by_worker(): void
    {
        $customer = $this->createCustomer();
        $activeDevice = $this->createDevice($customer, 'token-active');
        $inactiveDevice = $this->createDevice($customer, 'token-inactive', PushDeviceStatus::Inactive);

        $scheduler = $this->app->make(NotificationScheduler::class);
        $factory = $this->app->make(NotificationMessageFactory::class);
        $message = $factory->forOrder(NotificationType::OrderConfirmed, 'ORD-1002', OrderStatus::Confirmed);

        $notification = $scheduler->scheduleOrderNotification($customer, $message, 'order:2:order_confirmed:v1', 'ORD-1002');

        $job = new SendPushNotificationJob($notification->id);
        $this->app->call([$job, 'handle']);

        self::assertCount(1, $this->sender->calls);
        self::assertSame(1, NotificationDelivery::query()->where('business_notification_id', $notification->id)->count());
    }

    public function test_worker_marks_failed_when_customer_has_no_eligible_devices(): void
    {
        $customer = $this->createCustomer();

        $scheduler = $this->app->make(NotificationScheduler::class);
        $factory = $this->app->make(NotificationMessageFactory::class);
        $message = $factory->forOrder(NotificationType::OrderConfirmed, 'ORD-1005', OrderStatus::Confirmed);

        $notification = $scheduler->scheduleOrderNotification($customer, $message, 'order:5:order_confirmed:v1', 'ORD-1005');

        $job = new SendPushNotificationJob($notification->id);
        $this->app->call([$job, 'handle']);

        $notification->refresh();
        self::assertSame(NotificationStatus::Failed, $notification->status);
        self::assertNotNull($notification->failed_at);
        self::assertSame(ProviderFailureCategory::UnknownFailure, $notification->failure_category);
        self::assertStringContainsString('لا يوجد جهاز إشعارات نشط', (string) $notification->failure_summary);
        self::assertSame(0, NotificationDelivery::query()->where('business_notification_id', $notification->id)->count());
        self::assertCount(0, $this->sender->calls);
    }

    public function test_permanent_token_failure_invalidates_device_and_records_failure(): void
    {
        $customer = $this->createCustomer();
        $device = $this->createDevice($customer, 'unregistered-token');

        $this->sender->respondWith(new PushSendResultData(
            accepted: false,
            providerReference: null,
            failureCategory: ProviderFailureCategory::PermanentTokenFailure,
            failureSummary: 'Token unregistered'
        ));

        $scheduler = $this->app->make(NotificationScheduler::class);
        $factory = $this->app->make(NotificationMessageFactory::class);
        $message = $factory->forOrder(NotificationType::OrderCancelled, 'ORD-1003', OrderStatus::Cancelled);

        $notification = $scheduler->scheduleOrderNotification($customer, $message, 'order:3:order_cancelled:v1', 'ORD-1003');

        $job = new SendPushNotificationJob($notification->id);
        $this->app->call([$job, 'handle']);

        $device->refresh();
        self::assertSame(PushDeviceStatus::Invalidated, $device->status);
        self::assertSame('provider_rejected_token', $device->invalidation_reason);

        $notification->refresh();
        self::assertSame(NotificationStatus::Failed, $notification->status);
    }

    public function test_transient_failure_throws_exception_for_queue_retry(): void
    {
        $customer = $this->createCustomer();
        $device = $this->createDevice($customer, 'transient-token');

        $this->sender->respondWith(new PushSendResultData(
            accepted: false,
            providerReference: null,
            failureCategory: ProviderFailureCategory::TransientProviderFailure,
            failureSummary: '503 Service Unavailable'
        ));

        $scheduler = $this->app->make(NotificationScheduler::class);
        $factory = $this->app->make(NotificationMessageFactory::class);
        $message = $factory->forOrder(NotificationType::OrderConfirmed, 'ORD-1004', OrderStatus::Confirmed);

        $notification = $scheduler->scheduleOrderNotification($customer, $message, 'order:4:order_confirmed:v1', 'ORD-1004');

        try {
            $job = new SendPushNotificationJob($notification->id);
            $this->app->call([$job, 'handle']);
            $this->fail('Expected TransientNotificationDeliveryException was not thrown.');
        } catch (\Throwable $e) {
            $this->assertInstanceOf(TransientNotificationDeliveryException::class, $e);
            $this->assertSame('Notification delivery will be retried.', $e->getMessage());
        }
    }

    private function createCustomer(): Customer
    {
        $phone = fake()->unique()->numerify('77#######');

        return Customer::create([
            'name' => 'Ahmed Ali',
            'phone' => $phone,
            'normalized_phone' => $phone,
            'is_active' => true,
        ]);
    }

    private function createDevice(Customer $customer, string $token, PushDeviceStatus $status = PushDeviceStatus::Active): PushDevice
    {
        $device = new PushDevice([
            'platform' => PushDevicePlatform::Android,
            'locale' => 'ar',
        ]);
        $device->owner()->associate($customer);
        $device->provider = 'fcm';
        $device->status = $status;
        $device->token = $token;
        $device->token_hash = hash('sha256', $token);
        $device->save();

        return $device;
    }
}
