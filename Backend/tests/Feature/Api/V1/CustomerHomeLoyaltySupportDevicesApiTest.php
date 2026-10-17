<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\BannerActionType;
use App\Enums\LoyaltyEntryType;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Enums\PushDevicePlatform;
use App\Enums\PushDeviceStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerLoyaltyAccount;
use App\Models\CustomerLoyaltyEntry;
use App\Models\CustomerTier;
use App\Models\CustomerTierMembership;
use App\Models\HomeBanner;
use App\Models\Inventory;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\PushDevice;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\Auth\CustomerJwtService;
use App\Services\CustomerSupportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CustomerHomeLoyaltySupportDevicesApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    private Currency $baseCurrency;

    private Category $category;

    private Brand $brand;

    private Warehouse $warehouse;

    private Supplier $supplier;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake([
            '*/health' => Http::response(['status' => 'unavailable'], 503),
        ]);

        $this->jwtService = app(CustomerJwtService::class);

        $this->baseCurrency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'العناية بالبشرة',
            'name_en' => 'Skin Care',
            'slug' => 'skin-care',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name_ar' => 'سيرافي',
            'name_en' => 'CeraVe',
            'slug' => 'cerave',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي',
            'name_en' => 'Main Warehouse',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'المورد المعتمد',
            'code' => 'SUPP-001',
            'phone' => '+967771234567',
            'is_active' => true,
        ]);
    }

    private function createCustomer(string $name = 'محمد الأهدل', string $phone = '771234567'): Customer
    {
        return Customer::create([
            'name' => $name,
            'phone' => '+967'.$phone,
            'normalized_phone' => '+967'.$phone,
            'is_active' => true,
            'phone_verified_at' => now(),
            'phone_verification_method' => 'otp',
        ]);
    }

    private function createProductWithVariant(
        string $nameAr,
        string $slug,
        float $price,
        bool $isBestSeller = false,
        bool $isNew = false,
        bool $isExclusive = false,
        int $stock = 10
    ): Product {
        $product = Product::create([
            'category_id' => $this->category->id,
            'brand_id' => $this->brand->id,
            'name_ar' => $nameAr,
            'name_en' => 'Product '.$slug,
            'slug' => $slug,
            'status' => ProductStatus::Active,
            'is_best_seller' => $isBestSeller,
            'is_new' => $isNew,
            'is_exclusive' => $isExclusive,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.strtoupper($slug),
            'barcode' => 'BAR-'.strtoupper($slug),
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => 'stocked',
            'supplier_id' => $this->supplier->id,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->baseCurrency->id,
            'price' => $price,
            'cost_price' => $price * 0.7,
            'is_active' => true,
        ]);

        Inventory::create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => $stock,
            'reserved_quantity' => 0,
        ]);

        return $product;
    }

    public function test_unauthenticated_requests_to_loyalty_and_devices_are_rejected_with_401(): void
    {
        $this->getJson('/api/v1/loyalty')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->postJson('/api/v1/devices', [
            'platform' => 'android',
            'fcm_token' => 'sample_token_123456789',
        ])
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->deleteJson('/api/v1/devices/1')
            ->assertStatus(401)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);
    }

    public function test_public_endpoints_home_and_support_are_accessible_without_authentication(): void
    {
        $this->getJson('/api/v1/home')
            ->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->getJson('/api/v1/support')
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function test_home_endpoint_returns_active_scheduled_banners_with_safe_destinations(): void
    {
        $product = $this->createProductWithVariant('غسول الوجه المائي', 'water-cleanser', 4500.0);

        // 1. Active and currently scheduled banner linking to Product
        $activeBanner = HomeBanner::create([
            'title_ar' => 'عرض الغسول المميز',
            'title_en' => 'Cleanser Special Offer',
            'is_active' => true,
            'sort_order' => 1,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addDays(5),
            'action_type' => BannerActionType::Product,
            'action_reference' => (string) $product->id,
        ]);

        // 2. Inactive banner
        HomeBanner::create([
            'title_ar' => 'بنر غير مفعل',
            'is_active' => false,
            'sort_order' => 2,
            'action_type' => BannerActionType::None,
        ]);

        // 3. Future scheduled banner (should not show)
        HomeBanner::create([
            'title_ar' => 'بنر مستقبلي',
            'is_active' => true,
            'sort_order' => 3,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(10),
            'action_type' => BannerActionType::None,
        ]);

        // 4. Past scheduled banner (expired)
        HomeBanner::create([
            'title_ar' => 'بنر منتهي الصلاحية',
            'is_active' => true,
            'sort_order' => 0,
            'starts_at' => now()->subDays(10),
            'ends_at' => now()->subDay(),
            'action_type' => BannerActionType::None,
        ]);

        $response = $this->getJson('/api/v1/home');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.banners')
            ->assertJsonPath('data.banners.0.id', $activeBanner->id)
            ->assertJsonPath('data.banners.0.title_ar', 'عرض الغسول المميز')
            ->assertJsonPath('data.banners.0.action_type', 'product')
            ->assertJsonPath('data.banners.0.action_reference', (string) $product->id)
            ->assertJsonPath('data.banners.0.destination.type', 'product')
            ->assertJsonPath('data.banners.0.destination.id', $product->id)
            ->assertJsonPath('data.banners.0.destination.name_ar', 'غسول الوجه المائي');
    }

    public function test_home_endpoint_aggregates_merchandising_sections_bounded_and_prevents_n_plus_one(): void
    {
        $bestSeller = $this->createProductWithVariant('كريم الترطيب الليلي', 'night-cream', 6000.0);
        $newArrival = $this->createProductWithVariant('سيروم فيتامين سي', 'vitamin-c-serum', 8500.0, isNew: true);
        $this->createProductWithVariant('عطر اللافندر الحصري', 'exclusive-lavender', 12000.0, isExclusive: true);

        // Ensure newest-by-created_at ranking prefers the later product.
        $newArrival->forceFill(['created_at' => now()->addMinute()])->save();

        $customer = $this->createCustomer();
        $order = Order::query()->create([
            'order_number' => 'ORD-HOME-TEST-1',
            'customer_id' => $customer->id,
            'warehouse_id' => $this->warehouse->id,
            'currency_id' => $this->baseCurrency->id,
            'status' => OrderStatus::Confirmed,
            'customer_name_snapshot' => $customer->name,
            'customer_phone_snapshot' => $customer->phone,
            'subtotal' => 6000,
            'discount_amount' => 0,
            'delivery_fee' => 0,
            'promotion_discount_total' => 0,
            'coupon_discount_total' => 0,
            'bundle_discount_total' => 0,
            'total' => 6000,
            'exchange_rate_snapshot' => 1,
            'base_currency_total' => 6000,
            'base_currency_delivery_fee' => 0,
        ]);

        $bestVariant = ProductVariant::query()->where('product_id', $bestSeller->id)->firstOrFail();
        OrderItem::query()->create([
            'order_id' => $order->id,
            'product_variant_id' => $bestVariant->id,
            'product_name_snapshot' => $bestSeller->name_ar,
            'variant_name_snapshot' => $bestVariant->sku,
            'sku_snapshot' => $bestVariant->sku,
            'quantity' => 5,
            'base_quantity' => 5,
            'original_unit_price' => 6000,
            'unit_price' => 6000,
            'line_subtotal' => 30000,
            'discount_amount' => 0,
            'item_discount_amount' => 0,
            'allocated_order_discount' => 0,
            'line_total' => 30000,
            'base_currency_unit_price' => 6000,
            'base_currency_line_total' => 30000,
        ]);

        DB::enableQueryLog();

        $response = $this->getJson('/api/v1/home');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.sections.best_sellers')
            ->assertJsonPath('data.sections.best_sellers.0.slug', 'night-cream')
            ->assertJsonPath('data.sections.new_arrivals.0.slug', 'vitamin-c-serum')
            ->assertJsonCount(1, 'data.sections.exclusive')
            ->assertJsonPath('data.sections.exclusive.0.slug', 'exclusive-lavender')
            ->assertJsonPath('data.sections.exclusive.0.badges.is_exclusive', true);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // O(1) query complexity assertion: Home aggregation should execute in bounded constant queries
        $this->assertLessThanOrEqual(35, count($queries), 'Home query count should be bounded and constant, avoiding N+1.');
    }

    public function test_home_endpoint_batches_banner_destinations(): void
    {
        $product = $this->createProductWithVariant('منتج وجهة البانر', 'banner-destination', 5000.0);

        foreach (range(1, 7) as $index) {
            Category::create([
                'name_ar' => "تصنيف {$index}",
                'name_en' => "Category {$index}",
                'slug' => "category-{$index}",
                'is_active' => true,
            ]);

            HomeBanner::create([
                'title_ar' => "بنر {$index}",
                'is_active' => true,
                'sort_order' => $index,
                'action_type' => BannerActionType::Product,
                'action_reference' => (string) $product->id,
            ]);
        }

        DB::enableQueryLog();

        $this->getJson('/api/v1/home')->assertOk()
            ->assertJsonCount(7, 'data.banners')
            ->assertJsonCount(8, 'data.featured_categories');

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        $this->assertLessThanOrEqual(14, count($queries), 'Home serialization must not query once per banner destination.');
    }

    public function test_support_endpoint_returns_configured_channels_and_safe_links(): void
    {
        app(CustomerSupportService::class)->clearCache();

        Setting::updateOrCreate(['key' => 'support_phone_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'support_phone'], ['value' => '771234567']);
        Setting::updateOrCreate(['key' => 'support_whatsapp_enabled'], ['value' => '1']);
        Setting::updateOrCreate(['key' => 'support_whatsapp_number'], ['value' => '771234567']);
        Setting::updateOrCreate(['key' => 'support_whatsapp_default_message'], ['value' => 'مرحباً، أحتاج مساعدة بخصوص طلبي']);
        Setting::updateOrCreate(['key' => 'support_hours_text_ar'], ['value' => 'يومياً من 9 صباحاً حتى 10 مساءً']);

        app(CustomerSupportService::class)->clearCache();

        $response = $this->getJson('/api/v1/support');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.phone.enabled', true)
            ->assertJsonPath('data.phone.number', '771234567')
            ->assertJsonPath('data.phone.tel_link', 'tel:+967771234567')
            ->assertJsonPath('data.whatsapp.enabled', true)
            ->assertJsonPath('data.whatsapp.number', '771234567')
            ->assertJsonPath('data.whatsapp.default_message', 'مرحباً، أحتاج مساعدة بخصوص طلبي')
            ->assertJsonPath('data.hours_text_ar', 'يومياً من 9 صباحاً حتى 10 مساءً');

        $this->assertStringContainsString('https://wa.me/967771234567', (string) $response->json('data.whatsapp.deep_link'));
    }

    public function test_support_endpoint_handles_disabled_channels_safely(): void
    {
        app(CustomerSupportService::class)->clearCache();

        Setting::updateOrCreate(['key' => 'support_phone_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'support_phone'], ['value' => '771234567']);
        Setting::updateOrCreate(['key' => 'support_whatsapp_enabled'], ['value' => '0']);
        Setting::updateOrCreate(['key' => 'support_whatsapp_number'], ['value' => '771234567']);

        app(CustomerSupportService::class)->clearCache();

        $response = $this->getJson('/api/v1/support');

        $response->assertStatus(200)
            ->assertJsonPath('data.phone.enabled', false)
            ->assertJsonPath('data.phone.number', null)
            ->assertJsonPath('data.phone.tel_link', null)
            ->assertJsonPath('data.whatsapp.enabled', false)
            ->assertJsonPath('data.whatsapp.number', null)
            ->assertJsonPath('data.whatsapp.deep_link', null);
    }

    public function test_customer_loyalty_endpoint_returns_ledger_balance_tier_and_bounded_history(): void
    {
        $customer = $this->createCustomer('سارة أحمد', '779988776');
        $token = $this->jwtService->generateAccessToken($customer);

        // Assign VIP Tier to Customer
        $tier = CustomerTier::create([
            'code' => 'GOLD',
            'name_ar' => 'المستوى الذهبي',
            'name_en' => 'Gold Tier',
            'sort_order' => 3,
            'is_active' => true,
        ]);
        $customer->update(['current_tier_id' => $tier->id]);

        CustomerTierMembership::create([
            'customer_id' => $customer->id,
            'customer_tier_id' => $tier->id,
            'starts_at' => now()->subMonth(),
            'ends_at' => null,
            'assignment_source' => 'SYSTEM_DEFAULT',
        ]);

        // Create Loyalty Account and Entries
        $account = CustomerLoyaltyAccount::create([
            'customer_id' => $customer->id,
            'status' => 'active',
        ]);

        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $account->id,
            'entry_number' => 'LE-001',
            'type' => LoyaltyEntryType::Earn,
            'points' => 300,
            'source_type' => 'order',
            'source_id' => 1,
            'source_event' => 'order_completed',
            'description' => 'نقاط مكافأة الطلب الأول',
            'occurred_at' => now()->subDays(5),
        ]);

        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $account->id,
            'entry_number' => 'LE-002',
            'type' => LoyaltyEntryType::Redeem,
            'points' => 100,
            'source_type' => 'order',
            'source_id' => 2,
            'source_event' => 'order_discount',
            'description' => 'استبدال نقاط في خصم طلب',
            'occurred_at' => now()->subDays(2),
        ]);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/loyalty');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available_points', 200)
            ->assertJsonPath('data.lifetime_earned', 300)
            ->assertJsonPath('data.lifetime_redeemed', 100)
            ->assertJsonPath('data.current_tier.code', 'GOLD')
            ->assertJsonPath('data.current_tier.name_ar', 'المستوى الذهبي')
            ->assertJsonCount(2, 'data.recent_entries')
            ->assertJsonPath('data.recent_entries.0.entry_number', 'LE-002')
            ->assertJsonPath('data.recent_entries.0.points', 100);
    }

    public function test_customer_with_no_loyalty_account_returns_zero_points_gracefully(): void
    {
        $customer = $this->createCustomer('عميل جديد', '770011223');
        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/loyalty');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.available_points', 0)
            ->assertJsonPath('data.lifetime_earned', 0)
            ->assertJsonPath('data.lifetime_redeemed', 0)
            ->assertJsonPath('data.current_tier', null)
            ->assertJsonPath('data.recent_entries', []);
    }

    public function test_push_device_registration_lifecycle_is_idempotent_and_masks_token(): void
    {
        $customer = $this->createCustomer('عميل الإشعارات', '773344556');
        $token = $this->jwtService->generateAccessToken($customer);

        $fcmToken = 'sample_fcm_token_super_secret_string_1234567890';

        // 1. Initial registration
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices', [
                'platform' => 'android',
                'fcm_token' => $fcmToken,
                'device_identifier' => 'device-uuid-1111',
                'locale' => 'ar',
                'app_version' => '1.0.0',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.device_identifier', 'device-uuid-1111')
            ->assertJsonPath('data.status', 'active');

        // Security check: raw sensitive token MUST NEVER be in the response payload
        $this->assertArrayNotHasKey('token', $response->json('data'));
        $this->assertArrayNotHasKey('fcm_token', $response->json('data'));

        $this->assertDatabaseHas('push_devices', [
            'owner_type' => Customer::class,
            'owner_id' => $customer->id,
            'platform' => 'android',
            'status' => 'active',
        ]);

        // 2. Idempotent re-registration with updated app version
        $response2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices', [
                'platform' => 'android',
                'fcm_token' => $fcmToken,
                'device_identifier' => 'device-uuid-1111',
                'locale' => 'ar',
                'app_version' => '1.0.1',
            ]);

        $response2->assertStatus(200);

        Http::assertNothingSent();

        // Assert single device record exists (not duplicated)
        $this->assertEquals(1, PushDevice::query()->where('owner_id', $customer->id)->count());
        $this->assertEquals('1.0.1', PushDevice::query()->where('owner_id', $customer->id)->first()->app_version);
    }

    public function test_push_device_registration_validation_rules(): void
    {
        $customer = $this->createCustomer('عميل تجربة الفاليديشن', '774455667');
        $token = $this->jwtService->generateAccessToken($customer);

        // Invalid platform
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices', [
                'platform' => 'windows',
                'fcm_token' => 'fcm_valid_length_token_123',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);

        // Missing token
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/devices', [
                'platform' => 'android',
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);
    }

    public function test_push_device_deregistration_deactivates_device(): void
    {
        $customer = $this->createCustomer('عميل حذف الجهاز', '775566778');
        $token = $this->jwtService->generateAccessToken($customer);

        $device = new PushDevice([
            'platform' => PushDevicePlatform::Android,
            'device_identifier' => 'dummy-dev',
        ]);
        $device->owner()->associate($customer);
        $device->provider = 'fcm';
        $device->token = 'dummy_token';
        $device->token_hash = hash('sha256', 'dummy_token');
        $device->status = PushDeviceStatus::Active;
        $device->save();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/devices/'.$device->id);

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $this->assertEquals(
            PushDeviceStatus::Inactive,
            $device->fresh()->status
        );
    }

    public function test_bola_defense_customer_cannot_delete_another_customers_device(): void
    {
        $customerA = $this->createCustomer('عميل أ', '776677889');
        $customerB = $this->createCustomer('عميل ب', '777788990');
        $tokenB = $this->jwtService->generateAccessToken($customerB);

        $deviceA = new PushDevice([
            'platform' => PushDevicePlatform::Android,
            'device_identifier' => 'device-a-id',
        ]);
        $deviceA->owner()->associate($customerA);
        $deviceA->provider = 'fcm';
        $deviceA->token = 'device_a_token';
        $deviceA->token_hash = hash('sha256', 'device_a_token');
        $deviceA->status = PushDeviceStatus::Active;
        $deviceA->save();

        // Customer B attempts to delete Customer A's device
        $response = $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->deleteJson('/api/v1/devices/'.$deviceA->id);

        $response->assertStatus(404)
            ->assertJsonPath('success', false)
            ->assertJsonPath('data.code', ApiErrorCode::DeviceNotFound->value);

        // Customer A's device remains untouched and active
        $this->assertEquals(
            PushDeviceStatus::Active,
            $deviceA->fresh()->status
        );
    }

    public function test_bola_defense_customer_loyalty_data_isolation(): void
    {
        $customerA = $this->createCustomer('العميل الأول', '778899001');
        $customerB = $this->createCustomer('العميل الثاني', '779900112');

        $tokenA = $this->jwtService->generateAccessToken($customerA);
        $tokenB = $this->jwtService->generateAccessToken($customerB);

        // Account A has 500 points
        $accountA = CustomerLoyaltyAccount::create(['customer_id' => $customerA->id, 'status' => 'active']);
        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $accountA->id,
            'entry_number' => 'LE-A-1',
            'type' => LoyaltyEntryType::Earn,
            'points' => 500,
            'source_type' => 'manual',
            'source_id' => 1,
            'source_event' => 'earn',
            'occurred_at' => now(),
        ]);

        // Account B has 20 points
        $accountB = CustomerLoyaltyAccount::create(['customer_id' => $customerB->id, 'status' => 'active']);
        CustomerLoyaltyEntry::create([
            'customer_loyalty_account_id' => $accountB->id,
            'entry_number' => 'LE-B-1',
            'type' => LoyaltyEntryType::Earn,
            'points' => 20,
            'source_type' => 'manual',
            'source_id' => 2,
            'source_event' => 'earn',
            'occurred_at' => now(),
        ]);

        // Customer A gets 500
        $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->getJson('/api/v1/loyalty')
            ->assertStatus(200)
            ->assertJsonPath('data.available_points', 500);

        // Customer B gets 20
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->getJson('/api/v1/loyalty')
            ->assertStatus(200)
            ->assertJsonPath('data.available_points', 20);
    }
}
