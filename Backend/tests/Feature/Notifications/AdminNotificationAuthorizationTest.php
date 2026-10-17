<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\BusinessNotification;
use App\Models\Customer;
use App\Models\NotificationDelivery;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\Notifications\TokenMasker;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class AdminNotificationAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    private function makeUser(string $role): User
    {
        static $counter = 0;
        $counter++;

        $user = User::create([
            'name' => "Admin User {$counter}",
            'email' => "admin_notif_{$counter}@lamma.test",
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);

        $user->syncRoles([$role]);

        return $user;
    }

    private function createCustomer(): Customer
    {
        static $c = 0;
        $c++;

        return Customer::create([
            'name' => "عميل اختبار {$c}",
            'phone' => "+9677700000{$c}",
            'normalized_phone' => "+9677700000{$c}",
            'is_active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeNotification(Customer $customer, array $attributes): BusinessNotification
    {
        $notification = new BusinessNotification($attributes);
        $notification->recipient()->associate($customer);

        foreach (['status', 'payload', 'queued_at', 'sent_at', 'failed_at'] as $guardedAttribute) {
            if (array_key_exists($guardedAttribute, $attributes)) {
                $notification->{$guardedAttribute} = $attributes[$guardedAttribute];
            }
        }

        $notification->save();

        return $notification;
    }

    private function createDevice(Customer $customer, string $rawToken): PushDevice
    {
        $device = new PushDevice([
            'platform' => PushDevicePlatform::Android,
        ]);
        $device->owner()->associate($customer);
        $device->provider = 'fcm';
        $device->token = $rawToken;
        $device->token_hash = hash('sha256', $rawToken);
        $device->status = PushDeviceStatus::Active;
        $device->save();

        return $device;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('admin.notifications.index'))->assertRedirect(route('admin.login'));
    }

    public function test_unauthorized_user_receives_403_on_index(): void
    {
        $user = $this->makeUser('customer_support');

        $this->actingAs($user)
            ->get(route('admin.notifications.index'))
            ->assertForbidden();
    }

    public function test_authorized_operations_manager_can_access_index(): void
    {
        $user = $this->makeUser('operations_manager');

        $this->actingAs($user)
            ->get(route('admin.notifications.index'))
            ->assertOk()
            ->assertSee('عمليات وسجل الإشعارات');
    }

    public function test_authorized_user_can_access_show(): void
    {
        $user = $this->makeUser('admin');
        $customer = $this->createCustomer();

        $notification = $this->makeNotification($customer, [
            'type' => NotificationType::OrderConfirmed,
            'category' => NotificationCategory::Transactional,
            'channel' => NotificationChannel::PushFcm,
            'status' => NotificationStatus::Sent,
            'title' => 'تم تأكيد طلبك #ORD-100',
            'body' => 'جاري تجهيز طلبك في المستودع',
            'payload_version' => 1,
            'payload' => ['order_id' => 100],
            'business_reference' => 'NOTIF-REF-12345',
            'idempotency_key' => 'idemp-12345',
            'queued_at' => now()->subMinute(),
            'sent_at' => now(),
        ]);

        $this->actingAs($user)
            ->get(route('admin.notifications.show', $notification->id))
            ->assertOk()
            ->assertSee('NOTIF-REF-12345')
            ->assertSee('تم تأكيد طلبك #ORD-100');
    }

    public function test_send_test_notification_dispatches_job_and_requires_permission(): void
    {
        Queue::fake();

        $supportUser = $this->makeUser('customer_support');
        $adminUser = $this->makeUser('admin');

        $customer = $this->createCustomer();
        $rawToken = 'fcm_secret_test_token_abcdef1234567890_xyz';
        $device = $this->createDevice($customer, $rawToken);

        $payload = [
            'push_device_id' => $device->id,
            'title' => 'عنوان الاختبار',
            'body' => 'نص الاختبار الفوري',
        ];

        // 1. Forbidden for customer_support
        $this->actingAs($supportUser)
            ->post(route('admin.notifications.send_test'), $payload)
            ->assertForbidden();

        Queue::assertNothingPushed();

        // 2. Allowed for admin
        $this->actingAs($adminUser)
            ->post(route('admin.notifications.send_test'), $payload)
            ->assertRedirect(route('admin.notifications.index'))
            ->assertSessionHas('success');

        Queue::assertPushedOn('notifications', SendPushNotificationJob::class);

        $this->assertDatabaseHas('business_notifications', [
            'recipient_id' => $customer->id,
            'type' => NotificationType::TestNotification->value,
            'title' => 'عنوان الاختبار',
            'status' => NotificationStatus::Queued->value,
        ]);
    }

    public function test_retry_notification_requeues_and_dispatches_job(): void
    {
        Queue::fake();

        $supportUser = $this->makeUser('customer_support');
        $adminUser = $this->makeUser('admin');

        $customer = $this->createCustomer();
        $rawToken = 'fcm_secret_token_to_retry_9999999999999';
        $device = $this->createDevice($customer, $rawToken);

        $notification = $this->makeNotification($customer, [
            'type' => NotificationType::OrderCancelled,
            'category' => NotificationCategory::Transactional,
            'channel' => NotificationChannel::PushFcm,
            'status' => NotificationStatus::Failed,
            'title' => 'إلغاء الطلب',
            'body' => 'تم إلغاء طلبك',
            'payload_version' => 1,
            'payload' => [],
            'business_reference' => 'RETRY-NOTIF-01',
            'idempotency_key' => 'retry-key-01',
            'queued_at' => now()->subMinutes(10),
            'failed_at' => now()->subMinutes(5),
        ]);

        $delivery = NotificationDelivery::create([
            'business_notification_id' => $notification->id,
            'push_device_id' => $device->id,
            'channel' => NotificationChannel::PushFcm,
            'provider' => 'fcm',
            'status' => NotificationStatus::Failed,
            'retryable' => true,
        ]);

        // 1. Forbidden for unauthorized
        $this->actingAs($supportUser)
            ->post(route('admin.notifications.retry', $notification->id))
            ->assertForbidden();

        // 2. Allowed for admin
        $this->actingAs($adminUser)
            ->post(route('admin.notifications.retry', $notification->id))
            ->assertRedirect();

        Queue::assertPushedOn('notifications', SendPushNotificationJob::class);

        $this->assertSame(NotificationStatus::Queued, $notification->fresh()->status);
        $this->assertSame(NotificationStatus::Pending, $delivery->fresh()->status);
    }

    public function test_raw_fcm_tokens_are_never_rendered_in_views(): void
    {
        $adminUser = $this->makeUser('admin');
        $customer = $this->createCustomer();
        $secretToken = 'SUPER_SECRET_RAW_FIREBASE_FCM_TOKEN_1234567890_XYZ';
        $device = $this->createDevice($customer, $secretToken);

        $maskedToken = TokenMasker::mask($secretToken);

        $notification = $this->makeNotification($customer, [
            'type' => NotificationType::OrderDelivered,
            'category' => NotificationCategory::Transactional,
            'channel' => NotificationChannel::PushFcm,
            'status' => NotificationStatus::Sent,
            'title' => 'تم التسليم',
            'body' => 'تم تسليم شحنتك',
            'payload_version' => 1,
            'payload' => [],
            'business_reference' => 'MASK-CHECK-01',
            'idempotency_key' => 'mask-key-01',
            'queued_at' => now(),
            'sent_at' => now(),
        ]);

        NotificationDelivery::create([
            'business_notification_id' => $notification->id,
            'push_device_id' => $device->id,
            'channel' => NotificationChannel::PushFcm,
            'provider' => 'fcm',
            'status' => NotificationStatus::Sent,
            'retryable' => false,
        ]);

        // Index and compose no longer list device tokens; raw token must still never appear.
        $indexResponse = $this->actingAs($adminUser)->get(route('admin.notifications.index'));
        $indexResponse->assertOk();
        $indexResponse->assertDontSee($secretToken);

        $composeResponse = $this->actingAs($adminUser)->get(route('admin.notifications.compose'));
        $composeResponse->assertOk();
        $composeResponse->assertDontSee($secretToken);

        // Show view may display a masked token only.
        $showResponse = $this->actingAs($adminUser)->get(route('admin.notifications.show', $notification->id));
        $showResponse->assertOk();
        $showResponse->assertDontSee($secretToken);
        $showResponse->assertSee($maskedToken);
    }
}
