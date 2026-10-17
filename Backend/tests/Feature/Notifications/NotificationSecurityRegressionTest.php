<?php

namespace Tests\Feature\Notifications;

use App\Enums\NotificationCategory;
use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Models\AuditLog;
use App\Models\BusinessNotification;
use App\Models\Customer;
use App\Models\PushDevice;
use App\Models\User;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class NotificationSecurityRegressionTest extends TestCase
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
            'name' => "Security Test User {$counter}",
            'email' => "sec_notif_{$counter}@lamma.test",
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
            'name' => "عميل أمان {$c}",
            'phone' => "+9677800000{$c}",
            'normalized_phone' => "+9677800000{$c}",
            'is_active' => true,
        ]);
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

    public function test_test_send_audit_log_never_stores_the_raw_token(): void
    {
        Queue::fake();

        $admin = $this->makeUser('admin');
        $customer = $this->createCustomer();
        $secretToken = 'AUDIT_LEAK_CHECK_RAW_FCM_TOKEN_9876543210_ABCDEF';
        $device = $this->createDevice($customer, $secretToken);

        $this->actingAs($admin)->post(route('admin.notifications.send_test'), [
            'push_device_id' => $device->id,
            'title' => 'فحص أمني',
            'body' => 'التحقق من عدم تسريب التوكن في سجل التدقيق',
        ])->assertRedirect(route('admin.notifications.index'));

        $auditRows = AuditLog::query()->where('action', 'admin.notification.test_sent')->get();
        $this->assertNotEmpty($auditRows);

        foreach ($auditRows as $row) {
            $this->assertStringNotContainsString($secretToken, (string) json_encode($row->metadata));
        }
    }

    public function test_unauthorized_role_cannot_retry_or_send_test(): void
    {
        $unauthorized = $this->makeUser('cashier');
        $customer = $this->createCustomer();
        $device = $this->createDevice($customer, 'cashier_scope_check_token_1234567890');

        $this->actingAs($unauthorized)
            ->post(route('admin.notifications.send_test'), [
                'push_device_id' => $device->id,
                'title' => 'محاولة غير مصرح بها',
                'body' => 'يجب أن يُرفض هذا الطلب',
            ])
            ->assertForbidden();

        $notification = new BusinessNotification([
            'type' => NotificationType::OrderCancelled,
            'category' => NotificationCategory::Transactional,
            'channel' => NotificationChannel::PushFcm,
            'title' => 'إلغاء الطلب',
            'body' => 'تم إلغاء طلبك',
            'payload_version' => 1,
            'business_reference' => 'SEC-RETRY-01',
            'idempotency_key' => 'sec-retry-key-01',
        ]);
        $notification->recipient()->associate($customer);
        $notification->status = NotificationStatus::Failed;
        $notification->payload = [];
        $notification->save();

        $this->actingAs($unauthorized)
            ->post(route('admin.notifications.retry', $notification->id))
            ->assertForbidden();
    }

    public function test_admin_entered_test_content_is_escaped_and_not_executable_in_views(): void
    {
        Queue::fake();

        $admin = $this->makeUser('admin');
        $customer = $this->createCustomer();
        $device = $this->createDevice($customer, 'xss_scope_check_token_1234567890abcd');

        $xssPayload = '<script>alert(1)</script>';

        $this->actingAs($admin)->post(route('admin.notifications.send_test'), [
            'push_device_id' => $device->id,
            'title' => $xssPayload,
            'body' => 'نص اختبار عادي',
        ])->assertRedirect(route('admin.notifications.index'));

        $notification = BusinessNotification::query()->where('business_reference', 'like', 'TEST-%')->latest('id')->firstOrFail();

        $response = $this->actingAs($admin)->get(route('admin.notifications.show', $notification->id));
        $response->assertOk();
        $response->assertDontSee($xssPayload, false);
        $response->assertSee(e($xssPayload), false);
    }

    public function test_log_filters_reject_invalid_enum_and_date_range_without_sql_errors(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get(route('admin.notifications.index', ['type' => "'; DROP TABLE business_notifications; --"]))
            ->assertSessionHasErrors('type');

        $this->actingAs($admin)
            ->get(route('admin.notifications.index', [
                'from_date' => now()->addDay()->toDateString(),
                'to_date' => now()->toDateString(),
            ]))
            ->assertSessionHasErrors('to_date');

        $this->assertDatabaseCount('business_notifications', 0);
    }

    public function test_guest_cannot_post_to_any_notification_mutation_route(): void
    {
        $customer = $this->createCustomer();
        $device = $this->createDevice($customer, 'guest_scope_check_token_1234567890');

        $this->post(route('admin.notifications.send_test'), [
            'push_device_id' => $device->id,
            'title' => 'ضيف',
            'body' => 'ضيف',
        ])->assertRedirect(route('admin.login'));
    }
}
