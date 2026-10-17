<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\CouponDiscountType;
use App\Enums\OrderStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Enums\ReservationStatus;
use App\Enums\SourcingStatus;
use App\Jobs\SendPushNotificationJob;
use App\Models\Brand;
use App\Models\Country;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Category;
use App\Models\City;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerAddress;
use App\Models\DeliveryMethod;
use App\Models\District;
use App\Models\Governorate;
use App\Models\Inventory;
use App\Models\InventoryReservation;
use App\Models\Order;
use App\Models\OrderAddress;
use App\Models\OrderSourcingRequirement;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\Supplier;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\Auth\CustomerJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CustomerCheckoutOrderApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    private Currency $currency;

    private Warehouse $warehouse;

    private Governorate $governorate;

    private City $city;

    private District $district;

    private Brand $brand;

    private Category $category;

    private Supplier $supplier;

    private Country $country;

    protected function setUp(): void
    {
        parent::setUp();

        Queue::fake();
        Http::fake([
            '*/health' => Http::response(['status' => 'unavailable'], 503),
        ]);

        $this->jwtService = app(CustomerJwtService::class);

        $this->currency = Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        Setting::updateOrCreate(
            ['key' => 'default_currency_id'],
            ['value' => (string) $this->currency->id]
        );

        Setting::updateOrCreate(
            ['key' => 'gift_wrapping_enabled'],
            ['value' => 'true']
        );

        Setting::updateOrCreate(
            ['key' => 'gift_message_enabled'],
            ['value' => 'true']
        );

        $this->country = Country::query()->where('code', 'YE')->firstOrFail();

        DeliveryMethod::query()->updateOrCreate(
            ['code' => 'standard'],
            [
                'name_ar' => 'توصيل قياسي',
                'name_en' => 'Standard Delivery',
                'price' => 1500,
                'estimated_delivery_ar' => 'خلال 24-48 ساعة',
                'estimated_delivery_en' => 'Within 24-48 hours',
                'sort_order' => 1,
                'is_active' => true,
            ]
        );

        $this->governorate = Governorate::create([
            'country_id' => $this->country->id,
            'name_ar' => 'صنعاء',
            'name_en' => 'Sanaa',
            'slug' => 'sanaa-checkout-api',
            'is_active' => true,
        ]);

        $this->ensureStandardDeliveryAssignment($this->governorate->id, 1500.0);

        $this->city = City::create([
            'governorate_id' => $this->governorate->id,
            'name_ar' => 'الأمانة',
            'name_en' => 'Al-Amanah',
            'slug' => 'al-amanah',
            'is_active' => true,
        ]);

        $this->district = District::create([
            'city_id' => $this->city->id,
            'name_ar' => 'السبعين',
            'name_en' => 'Al-Sabeen',
            'slug' => 'al-sabeen',
            'is_active' => true,
        ]);

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المستودع الرئيسي - صنعاء',
            'code' => 'WH-SANAA',
            'governorate_id' => $this->governorate->id,
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name_ar' => 'علامة تجارية',
            'name_en' => 'Brand Test',
            'slug' => 'brand-test',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'قسم تجريبي',
            'name_en' => 'Category Test',
            'slug' => 'category-test',
            'is_active' => true,
        ]);

        $this->supplier = Supplier::create([
            'name' => 'مورد التوفير السريع',
            'code' => 'SUPP-FAST',
            'contact_person' => 'أحمد المورد',
            'phone' => '770000000',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $this->postJson('/api/v1/checkout/preview')
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->postJson('/api/v1/orders', [], ['Idempotency-Key' => (string) Str::uuid()])
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);
    }

    public function test_checkout_preview_with_empty_cart_returns_cannot_checkout(): void
    {
        $customer = $this->createCustomer('771111111');
        $token = $this->jwtService->generateAccessToken($customer);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/checkout/preview');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.can_checkout', false)
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.pricing.total', 0);
    }

    public function test_checkout_preview_calculates_governorate_delivery_fee_and_totals(): void
    {
        $customer = $this->createCustomer('771111112');
        $token = $this->jwtService->generateAccessToken($customer);

        $address = $this->createAddress($customer, true);
        $variant = $this->createStockedVariant(5000.0, 10.0);

        // Add variant to cart
        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/checkout/preview', [
                'customer_address_id' => $address->id,
                'gift_wrapping_requested' => true,
                'gift_message' => 'مع خالص التمنيات بالشفاء العاجل',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.can_checkout', true)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.total_quantity', 2)
            ->assertJsonPath('data.pricing.subtotal', 10000)
            ->assertJsonPath('data.pricing.delivery_fee', 1500)
            ->assertJsonPath('data.pricing.total', 11500)
            ->assertJsonPath('data.payment_methods.0.code', 'cod')
            ->assertJsonPath('data.shipping_methods.0.code', 'standard')
            ->assertJsonPath('data.shipping_methods.0.cost', 1500)
            ->assertJsonPath('data.gift_wrapping.requested', true)
            ->assertJsonPath('data.gift_wrapping.is_free', true);

        // Assert preview does NOT reserve stock (preview ≠ reservation)
        $inventory = Inventory::where('product_variant_id', $variant->id)->first();
        $this->assertSame(0.0, (float) $inventory->reserved_quantity);
    }

    public function test_checkout_preview_uses_assignment_fee_not_delivery_method_price(): void
    {
        DeliveryMethod::query()->where('code', 'standard')->update(['price' => 9999]);

        $customer = $this->createCustomer('771111199');
        $token = $this->jwtService->generateAccessToken($customer);

        $address = $this->createAddress($customer, true);
        $variant = $this->createStockedVariant(5000.0, 10.0);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/checkout/preview', [
                'customer_address_id' => $address->id,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.pricing.delivery_fee', 1500)
            ->assertJsonPath('data.shipping_methods.0.cost', 1500)
            ->assertJsonPath('data.pricing.total', 6500);
    }

    public function test_checkout_preview_with_coupon_calculates_authoritative_discount(): void
    {
        $customer = $this->createCustomer('771111113');
        $token = $this->jwtService->generateAccessToken($customer);

        $address = $this->createAddress($customer, true);
        $variant = $this->createStockedVariant(10000.0, 5.0);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        Coupon::create([
            'code' => 'YEMEN10',
            'name' => 'خصم 10%',
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => 10.0,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(5),
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/checkout/preview', [
                'customer_address_id' => $address->id,
                'coupon_code' => 'YEMEN10',
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.can_checkout', true)
            ->assertJsonPath('data.pricing.subtotal', 10000)
            ->assertJsonPath('data.pricing.coupon_discount', 1000)
            ->assertJsonPath('data.pricing.delivery_fee', 1500)
            ->assertJsonPath('data.pricing.total', 10500);
    }

    public function test_checkout_preview_with_another_customer_address_fails_bola(): void
    {
        $customer1 = $this->createCustomer('771111114');
        $customer2 = $this->createCustomer('771111115');
        $token1 = $this->jwtService->generateAccessToken($customer1);

        $addressOfCustomer2 = $this->createAddress($customer2, true);

        $cart = Cart::create(['customer_id' => $customer1->id]);
        $variant = $this->createStockedVariant(2000.0, 5.0);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token1}")
            ->postJson('/api/v1/checkout/preview', [
                'customer_address_id' => $addressOfCustomer2->id,
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::AddressNotFound->value);
    }

    public function test_checkout_preview_flags_insufficient_stock_warning(): void
    {
        $customer = $this->createCustomer('771111116');
        $token = $this->jwtService->generateAccessToken($customer);

        $address = $this->createAddress($customer, true);
        $variant = $this->createStockedVariant(5000.0, 2.0); // Only 2 in stock

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 5, // Requesting 5
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/checkout/preview', [
                'customer_address_id' => $address->id,
            ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.can_checkout', false)
            ->assertJsonPath('data.items.0.is_available', false);
    }

    public function test_order_creation_requires_idempotency_key(): void
    {
        $customer = $this->createCustomer('771111117');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('data.code', ApiErrorCode::MalformedRequest->value);
    }

    public function test_order_creation_with_empty_cart_fails(): void
    {
        $customer = $this->createCustomer('771111118');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::CartEmpty->value);
    }

    public function test_order_creation_with_unsupported_payment_method_is_rejected(): void
    {
        $customer = $this->createCustomer('771111119');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'apple_pay', // Mock Saudi method
            ]);

        $response->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);
    }

    public function test_successful_order_creation_reserves_stocked_items_and_clears_cart(): void
    {
        $customer = $this->createCustomer('771111120');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);

        $variant = $this->createStockedVariant(4000.0, 10.0);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 3,
        ]);

        $idempotencyKey = (string) Str::uuid();

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
                'shipping_method' => 'standard',
                'gift_wrapping_requested' => true,
                'gift_message' => 'ألف مبروك التخرج والنجاح',
                'notes' => 'يرجى الاتصال قبل الوصول بنصف ساعة',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', OrderStatus::Draft->value)
            ->assertJsonPath('data.payment_method.code', 'cod')
            ->assertJsonPath('data.pricing.subtotal', 12000)
            ->assertJsonPath('data.pricing.delivery_fee', 1500)
            ->assertJsonPath('data.pricing.total', 13500)
            ->assertJsonPath('data.items.0.quantity', 3)
            ->assertJsonPath('data.gift_wrapping.requested', true);

        $orderId = $response->json('data.id');
        $order = Order::find($orderId);
        $this->assertNotNull($order);
        $this->assertEquals(OrderStatus::Draft, $order->status);

        // 1. Assert Address Snapshot was created
        $orderAddress = OrderAddress::where('order_id', $orderId)->first();
        $this->assertNotNull($orderAddress);
        $this->assertEquals('صنعاء', $orderAddress->governorate_name_snapshot);
        $this->assertEquals($this->country->id, $orderAddress->country_id);
        $this->assertEquals('اليمن', $orderAddress->country_name_snapshot);
        $this->assertEquals('YE', $orderAddress->country_code_snapshot);
        $this->assertNull($orderAddress->city_id);
        $this->assertNull($orderAddress->district_id);
        $this->assertEquals($customer->phone, $orderAddress->contact_phone);

        // 2. Under review: inventory is NOT reserved until CS confirms
        $inventory = Inventory::where('product_variant_id', $variant->id)->first();
        $this->assertSame(0.0, (float) $inventory->reserved_quantity);
        $this->assertDatabaseMissing('inventory_reservations', ['order_id' => $orderId]);

        // 3. Assert Cart was cleared atomically
        $this->assertDatabaseMissing('cart_items', ['cart_id' => $cart->id]);
    }

    public function test_order_creation_handles_on_demand_items_without_warehouse_reservation(): void
    {
        $customer = $this->createCustomer('771111121');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);

        // Create ON_DEMAND variant
        $variant = $this->createOnDemandVariant(8000.0);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', OrderStatus::Draft->value)
            ->assertJsonPath('data.items.0.fulfillment_mode', 'on_demand');

        $orderId = $response->json('data.id');

        // No warehouse inventory reservation while under review
        $this->assertDatabaseMissing('inventory_reservations', ['order_id' => $orderId]);

        // Sourcing requirement is created only after CS confirms
        $this->assertDatabaseMissing('order_sourcing_requirements', ['order_id' => $orderId]);
    }

    public function test_customer_can_read_only_own_order_history_detail_and_tracking(): void
    {
        $customer = $this->createCustomer('771111130');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);
        $variant = $this->createStockedVariant(4000.0, 5.0);
        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create(['cart_id' => $cart->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $created = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', ['customer_address_id' => $address->id, 'payment_method' => 'cod', 'shipping_method' => 'standard']);

        $orderId = (int) $created->json('data.id');

        $this->withHeader('Authorization', "Bearer {$token}")->getJson('/api/v1/orders')
            ->assertOk()->assertJsonPath('data.items.0.id', $orderId);
        $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/orders/{$orderId}")
            ->assertOk()->assertJsonPath('data.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.items.0.primary_image_url', null)
            ->assertJsonMissingPath('data.notes');
        $this->withHeader('Authorization', "Bearer {$token}")->getJson("/api/v1/orders/{$orderId}/tracking")
            ->assertOk()->assertJsonPath('data.status', 'under_review');

        $otherCustomer = $this->createCustomer('771111131');
        $otherToken = $this->jwtService->generateAccessToken($otherCustomer);
        $this->withHeader('Authorization', "Bearer {$otherToken}")->getJson("/api/v1/orders/{$orderId}")
            ->assertNotFound()->assertJsonPath('data.code', ApiErrorCode::OrderNotFound->value);
    }

    public function test_order_creation_consumes_coupon_atomically_and_enforces_limit(): void
    {
        $customer = $this->createCustomer('771111122');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);

        $variant = $this->createStockedVariant(6000.0, 10.0);

        $coupon = Coupon::create([
            'code' => 'ONETIME',
            'name' => 'خصم لمرة واحدة فقط',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => 1000.0,
            'usage_limit' => 1,
            'per_customer_limit' => 1,
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'expires_at' => now()->addDays(5),
        ]);

        $cart = Cart::create([
            'customer_id' => $customer->id,
            'coupon_code' => 'ONETIME',
        ]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        // First order succeeds
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
            ]);

        $response1->assertStatus(201)
            ->assertJsonPath('data.pricing.coupon_discount_total', 1000)
            ->assertJsonPath('data.pricing.total', 6500); // 6000 - 1000 + 1500

        $this->assertDatabaseHas('coupon_usages', [
            'coupon_id' => $coupon->id,
            'customer_id' => $customer->id,
        ]);

        // Second order with same coupon must fail
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);
        $cart->update(['coupon_code' => 'ONETIME']);

        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
                'coupon_code' => 'ONETIME',
            ]);

        $response2->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::CouponNotEligible->value);
    }

    public function test_idempotent_order_submission_returns_cached_replay(): void
    {
        $customer = $this->createCustomer('771111123');
        $token = $this->jwtService->generateAccessToken($customer);
        $address = $this->createAddress($customer, true);

        $variant = $this->createStockedVariant(3000.0, 5.0);

        $cart = Cart::create(['customer_id' => $customer->id]);
        CartItem::create([
            'cart_id' => $cart->id,
            'product_variant_id' => $variant->id,
            'quantity' => 1,
        ]);

        $idempotencyKey = (string) Str::uuid();

        // 1. Initial request
        $response1 = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
            ]);

        $response1->assertStatus(201);
        $orderId = $response1->json('data.id');

        // 2. Replay with identical key
        $response2 = $this->withHeader('Authorization', "Bearer {$token}")
            ->withHeader('Idempotency-Key', $idempotencyKey)
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address->id,
                'payment_method' => 'cod',
            ]);

        $response2->assertStatus(201)
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.id', $orderId);

        // Verify only ONE order and ONE reservation exists
        $this->assertEquals(1, Order::where('customer_id', $customer->id)->count());
        $this->assertEquals(1, InventoryReservation::where('product_variant_id', $variant->id)->count());
    }

    public function test_concurrency_race_for_last_stocked_unit(): void
    {
        $customer1 = $this->createCustomer('771111124');
        $customer2 = $this->createCustomer('771111125');
        $token1 = $this->jwtService->generateAccessToken($customer1);
        $token2 = $this->jwtService->generateAccessToken($customer2);

        $address1 = $this->createAddress($customer1, true);
        $address2 = $this->createAddress($customer2, true);

        // Only 1 item in stock
        $variant = $this->createStockedVariant(5000.0, 1.0);

        // Both customers have 1 unit in their cart
        $cart1 = Cart::create(['customer_id' => $customer1->id]);
        CartItem::create(['cart_id' => $cart1->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        $cart2 = Cart::create(['customer_id' => $customer2->id]);
        CartItem::create(['cart_id' => $cart2->id, 'product_variant_id' => $variant->id, 'quantity' => 1]);

        // Customer 1 places order first -> SUCCEEDS
        $response1 = $this->withHeader('Authorization', "Bearer {$token1}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address1->id,
                'payment_method' => 'cod',
            ]);

        $response1->assertStatus(201);

        // Customer 2 attempts to place order for same unit -> FAILS with INSUFFICIENT_STOCK
        $response2 = $this->withHeader('Authorization', "Bearer {$token2}")
            ->withHeader('Idempotency-Key', (string) Str::uuid())
            ->postJson('/api/v1/orders', [
                'customer_address_id' => $address2->id,
                'payment_method' => 'cod',
            ]);

        $response2->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::InsufficientStock->value);

        // Ensure stock was reserved exactly once
        $inventory = Inventory::where('product_variant_id', $variant->id)->first();
        $this->assertSame(1.0, (float) $inventory->reserved_quantity);
    }

    // ==========================================
    // Helper Setup Functions
    // ==========================================

    private function createCustomer(string $phone): Customer
    {
        return Customer::create([
            'phone' => $phone,
            'normalized_phone' => $phone,
            'name' => 'عميل تجريبي '.substr($phone, -4),
            'is_active' => true,
        ]);
    }

    private function createAddress(Customer $customer, bool $isDefault = false): CustomerAddress
    {
        return CustomerAddress::create([
            'customer_id' => $customer->id,
            'country_id' => $this->country->id,
            'label' => 'المنزل',
            'contact_name' => $customer->name,
            'contact_phone' => $customer->phone,
            'governorate_id' => $this->governorate->id,
            'city_id' => $this->city->id,
            'district_id' => $this->district->id,
            'neighborhood' => 'حي السبعين',
            'street' => 'شارع حدة',
            'building' => 'عمارة 10',
            'formatted_address' => 'صنعاء - السبعين - شارع حدة',
            'is_default' => $isDefault,
        ]);
    }

    private function createStockedVariant(float $price, float $stock): ProductVariant
    {
        $product = Product::create([
            'name_ar' => 'منتج مخزني '.Str::random(5),
            'slug' => 'stocked-product-'.Str::random(8),
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'status' => ProductStatus::Active,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.Str::upper(Str::random(6)),
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'price' => $price,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => $stock,
            'reserved_quantity' => 0.0,
        ]);

        return $variant;
    }

    private function createOnDemandVariant(float $price): ProductVariant
    {
        $product = Product::create([
            'name_ar' => 'منتج توفير عند الطلب '.Str::random(5),
            'slug' => 'ondemand-product-'.Str::random(8),
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'status' => ProductStatus::Active,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'supplier_id' => $this->supplier->id,
            'sku' => 'SKU-OD-'.Str::upper(Str::random(6)),
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::OnDemand,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'price' => $price,
            'is_active' => true,
        ]);

        return $variant;
    }
}
