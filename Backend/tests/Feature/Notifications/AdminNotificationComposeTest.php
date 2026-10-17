<?php

namespace Tests\Feature\Notifications;

use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Jobs\BroadcastTopicNotificationJob;
use App\Jobs\SendPushNotificationJob;
use App\Models\BusinessNotification;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Customer;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\Notifications\TokenMasker;
use Database\Seeders\AuthorizationSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class AdminNotificationComposeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(AuthorizationSeeder::class);
    }

    private function makeAdmin(): User
    {
        $user = User::create([
            'name' => 'Compose Admin',
            'email' => 'compose_admin@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->syncRoles(['admin']);

        return $user;
    }

    private function makeCustomerWithDevice(PushDevicePlatform $platform = PushDevicePlatform::Android): Customer
    {
        $unique = uniqid();
        $customer = Customer::create([
            'name' => 'عميل إشعار '.$unique,
            'phone' => '+96777'.rand(1000000, 9999999),
            'normalized_phone' => '+96777'.rand(1000000, 9999999),
            'is_active' => true,
        ]);

        $token = 'fcm-compose-token-'.$unique;
        $device = new PushDevice([
            'platform' => $platform,
            'device_identifier' => 'device-compose-'.$unique,
            'app_version' => '1.0.0',
        ]);
        $device->provider = 'fcm';
        $device->status = PushDeviceStatus::Active;
        $device->owner()->associate($customer);
        $device->token = $token;
        $device->token_hash = app(TokenMasker::class)->hash($token);
        $device->last_registered_at = now();
        $device->last_seen_at = now();
        $device->save();

        return $customer;
    }

    public function test_compose_page_requires_send_test_permission(): void
    {
        $user = User::create([
            'name' => 'Viewer',
            'email' => 'viewer_compose@lamma.test',
            'password' => bcrypt('password'),
            'is_active' => true,
        ]);
        $user->syncRoles(['customer_support']);

        $this->actingAs($user)
            ->get(route('admin.notifications.compose'))
            ->assertForbidden();
    }

    public function test_authorized_admin_can_open_compose_page(): void
    {
        $this->actingAs($this->makeAdmin())
            ->get(route('admin.notifications.compose'))
            ->assertOk()
            ->assertSee('إعدادات الإشعار')
            ->assertSee('جميع مستخدمي التطبيق')
            ->assertSee('شريحة مخصصة من العملاء')
            ->assertSee('مستخدم معيّن');
    }

    public function test_admin_can_queue_notification_for_specific_customer(): void
    {
        Queue::fake();
        $customer = $this->makeCustomerWithDevice();

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.notifications.compose.store'), [
                'audience' => 'customer',
                'customer_id' => $customer->id,
                'title' => 'عنوان تجريبي',
                'body' => 'محتوى الإشعار التجريبي من لوحة التحكم',
            ])
            ->assertRedirect(route('admin.notifications.index'));

        Queue::assertPushedOn('notifications', SendPushNotificationJob::class);
        $this->assertDatabaseHas('business_notifications', [
            'title' => 'عنوان تجريبي',
            'body' => 'محتوى الإشعار التجريبي من لوحة التحكم',
        ]);
    }

    public function test_admin_broadcast_to_all_uses_fcm_topic_not_device_fanout(): void
    {
        Queue::fake();
        Storage::fake('public');
        $this->makeCustomerWithDevice();

        /** @var \Tests\Fakes\Notifications\FakePushNotificationSender $sender */
        $sender = app(\App\Contracts\Notifications\PushNotificationSender::class);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.notifications.compose.store'), [
                'audience' => 'all',
                'title' => 'إشعار عام',
                'body' => 'مرحباً بكل مستخدمي التطبيق',
                'image' => UploadedFile::fake()->image('promo.jpg', 600, 400),
            ])
            ->assertRedirect(route('admin.notifications.index'));

        Queue::assertNothingPushed();
        self::assertCount(1, $sender->topicCalls);
        self::assertSame('storeapp_general', $sender->topicCalls[0]['topic']);
        $this->assertDatabaseHas('business_notifications', [
            'title' => 'إشعار عام',
            'status' => 'sent',
        ]);
    }

    public function test_admin_can_schedule_broadcast_to_all(): void
    {
        Queue::fake();
        $scheduledTime = now()->addHours(3);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.notifications.compose.store'), [
                'audience' => 'all',
                'title' => 'عرض الغد المجدول',
                'body' => 'تذكير بالعرض الصباحي',
                'send_type' => 'scheduled',
                'scheduled_at' => $scheduledTime->format('Y-m-d H:i:s'),
            ])
            ->assertRedirect(route('admin.notifications.index'));

        Queue::assertPushedOn('notifications', BroadcastTopicNotificationJob::class);
        $this->assertDatabaseHas('business_notifications', [
            'title' => 'عرض الغد المجدول',
            'status' => 'queued',
        ]);
    }

    public function test_admin_can_send_to_abandoned_cart_segment(): void
    {
        Queue::fake();
        $customerWithCart = $this->makeCustomerWithDevice();

        $category = \App\Models\Category::create(['name_ar' => 'تصنيف', 'slug' => 'cat-seg', 'is_active' => true]);
        $product = \App\Models\Product::create(['name_ar' => 'منتج تجريبي', 'slug' => 'prod-seg', 'category_id' => $category->id, 'status' => 'active']);
        $currency = \App\Models\Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'YR',
            'exchange_rate_to_base' => 1.0,
            'is_default' => true,
            'is_active' => true,
        ]);
        $variant = \App\Models\ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'VAR-SEG-1',
            'price' => 50,
            'currency_id' => $currency->id,
            'fulfillment_mode' => \App\Enums\ProductVariantFulfillmentMode::Stocked,
            'is_active' => true,
        ]);

        // إنشاء سلة وفيها عناصر للعميل
        $cart = Cart::create(['customer_id' => $customerWithCart->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.notifications.compose.store'), [
                'audience' => 'segment',
                'segment' => 'abandoned_cart',
                'title' => 'سلتك في انتظارك!',
                'body' => 'أكمل طلبك الآن واحصل على توصيل سريع',
            ])
            ->assertRedirect(route('admin.notifications.index'));

        Queue::assertPushedOn('notifications', SendPushNotificationJob::class);
        $this->assertDatabaseHas('business_notifications', [
            'title' => 'سلتك في انتظارك!',
            'recipient_type' => Customer::class,
            'recipient_id' => $customerWithCart->id,
        ]);
    }

    public function test_admin_can_compose_notification_with_product_destination(): void
    {
        Queue::fake();
        $customer = $this->makeCustomerWithDevice();

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.notifications.compose.store'), [
                'audience' => 'customer',
                'customer_id' => $customer->id,
                'title' => 'عرض خاص على المنتج',
                'body' => 'تخفيضات 20% لفترة محدودة',
                'destination_type' => 'product',
                'destination_id' => '42',
            ])
            ->assertRedirect(route('admin.notifications.index'));

        Queue::assertPushedOn('notifications', SendPushNotificationJob::class);
        $this->assertDatabaseHas('business_notifications', [
            'title' => 'عرض خاص على المنتج',
            'type' => 'broadcast',
        ]);

        $notification = BusinessNotification::query()
            ->where('title', 'عرض خاص على المنتج')
            ->firstOrFail();

        self::assertSame('product', $notification->payload['destination_type'] ?? null);
        self::assertSame('42', $notification->payload['destination_id'] ?? null);
    }

    public function test_admin_can_target_ios_devices_specifically(): void
    {
        Queue::fake();
        $androidCustomer = $this->makeCustomerWithDevice(PushDevicePlatform::Android);
        $iosCustomer = $this->makeCustomerWithDevice(PushDevicePlatform::Ios);

        $this->actingAs($this->makeAdmin())
            ->post(route('admin.notifications.compose.store'), [
                'audience' => 'all',
                'platform' => 'ios',
                'title' => 'تحديث خاص بتطبيق الآيفون',
                'body' => 'تحديث جديد متاح لمستخدمي iOS',
            ])
            ->assertRedirect(route('admin.notifications.index'));

        Queue::assertPushed(SendPushNotificationJob::class, 1);
        $this->assertDatabaseHas('business_notifications', [
            'title' => 'تحديث خاص بتطبيق الآيفون',
            'recipient_type' => Customer::class,
            'recipient_id' => $iosCustomer->id,
        ]);
        $this->assertDatabaseMissing('business_notifications', [
            'title' => 'تحديث خاص بتطبيق الآيفون',
            'recipient_type' => Customer::class,
            'recipient_id' => $androidCustomer->id,
        ]);
    }

    public function test_admin_can_target_by_governorate_registered_or_address(): void
    {
        Queue::fake();

        $country = \App\Models\Country::firstOrCreate(
            ['code' => 'YE'],
            [
                'name_ar' => 'اليمن',
                'name_en' => 'Yemen',
                'calling_code' => '967',
                'is_active' => true,
            ]
        );

        $sanaa = \App\Models\Governorate::create([
            'country_id' => $country->id,
            'name_ar' => 'صنعاء',
            'name_en' => 'Sanaa',
            'slug' => 'sanaa',
            'is_active' => true,
        ]);

        $aden = \App\Models\Governorate::create([
            'country_id' => $country->id,
            'name_ar' => 'عدن',
            'name_en' => 'Aden',
            'slug' => 'aden',
            'is_active' => true,
        ]);

        // العميل 1: مسجل بمحافظة صنعاء
        $customerSanaa = $this->makeCustomerWithDevice();
        $customerSanaa->update(['governorate_id' => $sanaa->id]);

        // العميل 2: مسجل في عدن ولكن لديه عنوان توصيل في صنعاء
        $customerAdenWithSanaaAddress = $this->makeCustomerWithDevice();
        $customerAdenWithSanaaAddress->update(['governorate_id' => $aden->id]);
        \App\Models\CustomerAddress::create([
            'customer_id' => $customerAdenWithSanaaAddress->id,
            'country_id' => $country->id,
            'governorate_id' => $sanaa->id,
            'label' => 'المنزل',
            'contact_name' => 'مستلم صنعاء',
            'contact_phone' => '+967770000000',
            'street' => 'شارع حدة',
            'is_default' => true,
        ]);

        // استهداف صنعاء بحسب عناوين التوصيل فقط ('address')
        $this->actingAs($this->makeAdmin())
            ->post(route('admin.notifications.compose.store'), [
                'audience' => 'all',
                'governorate_id' => $sanaa->id,
                'governorate_target' => 'address',
                'title' => 'عروض توصيل خاصة بصنعاء',
                'body' => 'خصومات التوصيل لعناوينكم في صنعاء',
            ])
            ->assertRedirect(route('admin.notifications.index'));

        $this->assertDatabaseHas('business_notifications', [
            'title' => 'عروض توصيل خاصة بصنعاء',
            'recipient_type' => Customer::class,
            'recipient_id' => $customerAdenWithSanaaAddress->id,
        ]);
        $this->assertDatabaseMissing('business_notifications', [
            'title' => 'عروض توصيل خاصة بصنعاء',
            'recipient_type' => Customer::class,
            'recipient_id' => $customerSanaa->id,
        ]);
    }
}
