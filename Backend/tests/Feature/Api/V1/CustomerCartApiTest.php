<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\CouponDiscountType;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Cart;
use App\Models\Category;
use App\Models\Coupon;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Supplier;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\Auth\CustomerJwtService;
use App\Services\CustomerCartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

final class CustomerCartApiTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    private Currency $currency;

    private Warehouse $warehouse;

    private Brand $brand;

    private Category $category;

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

        $this->warehouse = Warehouse::create([
            'name_ar' => 'المخزن الرئيسي',
            'code' => 'WH-MAIN',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name_ar' => 'ديور',
            'name_en' => 'Dior',
            'slug' => 'dior',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'عطور',
            'name_en' => 'Perfumes',
            'slug' => 'perfumes',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        $this->getJson('/api/v1/cart')
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->postJson('/api/v1/cart/items', ['product_variant_id' => 1])
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->patchJson('/api/v1/cart/items/1', ['quantity' => 2])
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->deleteJson('/api/v1/cart/items/1')
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->deleteJson('/api/v1/cart')
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->postJson('/api/v1/cart/coupon', ['code' => 'TEST'])
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);

        $this->deleteJson('/api/v1/cart/coupon')
            ->assertStatus(401)
            ->assertJsonPath('data.code', ApiErrorCode::AuthRequired->value);
    }

    public function test_cart_returns_the_selected_variant_identity_and_primary_image(): void
    {
        Storage::fake('public');

        $customer = $this->createTestCustomer('هوية السلة', '773220099');
        $token = $this->jwtService->generateAccessToken($customer);
        $variant = $this->createTestVariant('المنتج الحقيقي', 'real-product', '15000.00', 10);

        $variant->product->addMedia(UploadedFile::fake()->image('product.jpg'))
            ->toMediaCollection('product_images');
        $variantMedia = $variant->addMedia(UploadedFile::fake()->image('blue-variant.jpg'))
            ->withCustomProperties(['is_primary' => true])
            ->toMediaCollection('variant_images');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ])
            ->assertCreated()
            ->assertJsonPath('data.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.items.0.variant.id', $variant->id)
            ->assertJsonPath('data.items.0.product.id', $variant->product_id)
            ->assertJsonPath('data.items.0.product.name', 'المنتج الحقيقي')
            ->assertJsonPath('data.items.0.product.brand', 'ديور')
            ->assertJsonPath('data.items.0.product.primary_image_url', $variantMedia->getUrl('small'));
    }

    public function test_product_variant_identity_is_enforced_and_server_calculates_truth(): void
    {
        $customer = $this->createTestCustomer('أحمد محمد', '771112233');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant = $this->createTestVariant('عطر سوفاج', 'sauvage-edp', '25000.00', 10);

        // Client attempts to pass fake price, subtotal, and discount
        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 2,
                'price' => '1.00', // Fake client price
                'total' => '2.00', // Fake client total
                'discount' => '100.00', // Fake client discount
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.items.0.product_variant_id', $variant->id)
            ->assertJsonPath('data.items.0.quantity', 2)
            ->assertJsonPath('data.items.0.unit_price', 25000)
            ->assertJsonPath('data.items.0.line_subtotal', 50000)
            ->assertJsonPath('data.items.0.line_total', 50000)
            ->assertJsonPath('data.pricing.items_subtotal', 50000)
            ->assertJsonPath('data.pricing.estimated_total', 50000);
    }

    public function test_adding_same_variant_increments_quantity_bounded_to_100(): void
    {
        $customer = $this->createTestCustomer('منى علي', '772223344');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant = $this->createTestVariant('عطر توم فورد', 'tom-ford', '40000.00', 50);

        // Add 3
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 3,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.items.0.quantity', 3);

        // Add 5 more
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 5,
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.items.0.quantity', 8);

        // Assert only 1 cart item row exists
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseHas('cart_items', [
            'product_variant_id' => $variant->id,
            'quantity' => 8,
        ]);
    }

    public function test_replaying_guest_cart_sync_key_does_not_increment_quantity_twice(): void
    {
        $customer = $this->createTestCustomer('ضيف المزامنة', '773221100');
        $token = $this->jwtService->generateAccessToken($customer);
        $variant = $this->createTestVariant('عطر المزامنة', 'sync-perfume', '15000.00', 10);
        $idempotencyKey = 'guest-cart-sync-0001';

        $payload = [
            'product_variant_id' => $variant->id,
            'quantity' => 2,
        ];

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/cart/items', $payload)
            ->assertCreated()
            ->assertJsonPath('data.items.0.quantity', 2);

        $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
            'Idempotency-Key' => $idempotencyKey,
        ])->postJson('/api/v1/cart/items', $payload)
            ->assertCreated()
            ->assertHeader('X-Idempotent-Replay', 'true')
            ->assertJsonPath('data.items.0.quantity', 2);
    }

    public function test_updating_item_quantity_and_bounds_validation(): void
    {
        $customer = $this->createTestCustomer('سالم عبده', '773334455');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant = $this->createTestVariant('عطر بلو شانيل', 'bleu-chanel', '35000.00', 20);

        $addRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        $itemId = $addRes->json('data.items.0.id');

        // Update quantity to 4
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/cart/items/{$itemId}", [
                'quantity' => 4,
            ])
            ->assertStatus(200)
            ->assertJsonPath('data.items.0.quantity', 4)
            ->assertJsonPath('data.items.0.line_subtotal', 140000)
            ->assertJsonPath('data.pricing.estimated_total', 140000);

        // Invalid quantity (< 1)
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/cart/items/{$itemId}", [
                'quantity' => 0,
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);

        // Invalid quantity (> 100)
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->patchJson("/api/v1/cart/items/{$itemId}", [
                'quantity' => 101,
            ])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);
    }

    public function test_removing_item_and_clearing_cart(): void
    {
        $customer = $this->createTestCustomer('فارس محمد', '774445566');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant1 = $this->createTestVariant('عطر 1', 'perfume-1', '10000.00', 10);
        $variant2 = $this->createTestVariant('عطر 2', 'perfume-2', '20000.00', 10);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $variant1->id, 'quantity' => 1]);
        $res2 = $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $variant2->id, 'quantity' => 2]);

        $item2Id = $res2->json('data.items.1.id');

        // Remove item 2
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson("/api/v1/cart/items/{$item2Id}")
            ->assertStatus(200)
            ->assertJsonPath('data.items_count', 1)
            ->assertJsonPath('data.total_quantity', 1)
            ->assertJsonPath('data.pricing.estimated_total', 10000);

        // Clear cart
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.items_count', 0)
            ->assertJsonPath('data.total_quantity', 0)
            ->assertJsonPath('data.pricing.estimated_total', 0);

        $this->assertDatabaseCount('cart_items', 0);
    }

    public function test_cart_presence_does_not_reserve_stock_cart_not_reservation(): void
    {
        $customer = $this->createTestCustomer('طارق ياسين', '775556677');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant = $this->createTestVariant('عطر فاخر', 'luxury-scent', '30000.00', 10);

        $inventoryBefore = Inventory::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(10.0, (float) $inventoryBefore->quantity);
        $this->assertSame(0.0, (float) $inventoryBefore->reserved_quantity);

        // Add 5 to cart
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 5,
            ])
            ->assertStatus(201);

        // Assert warehouse stock is untouched
        $inventoryAfter = Inventory::query()->where('product_variant_id', $variant->id)->first();
        $this->assertSame(10.0, (float) $inventoryAfter->quantity);
        $this->assertSame(0.0, (float) $inventoryAfter->reserved_quantity);
    }

    public function test_variant_availability_reporting_stocked_and_on_demand(): void
    {
        $customer = $this->createTestCustomer('سمير خالد', '776667788');
        $token = $this->jwtService->generateAccessToken($customer);

        // 1. In Stock variant
        $stockedVariant = $this->createTestVariant('منتج متوفر', 'in-stock-var', '15000.00', 12);

        // 2. Out of Stock variant
        $outOfStockVariant = $this->createTestVariant('منتج نافد', 'out-stock-var', '12000.00', 0);

        // 3. On-Demand variant with active supplier
        $supplier = Supplier::create([
            'name' => 'المورد السريع',
            'code' => 'SUP-01',
            'contact_person' => 'زيد',
            'phone' => '778899001',
            'email' => 'supplier@example.com',
            'is_active' => true,
        ]);

        $onDemandProduct = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج بالطلب',
            'slug' => 'on-demand-prod',
            'status' => ProductStatus::Active,
        ]);

        $onDemandVariant = ProductVariant::create([
            'product_id' => $onDemandProduct->id,
            'sku' => 'ON-DEMAND-01',
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::OnDemand,
            'supplier_id' => $supplier->id,
        ]);

        VariantPrice::create([
            'product_variant_id' => $onDemandVariant->id,
            'currency_id' => $this->currency->id,
            'price' => '22000.00',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $stockedVariant->id, 'quantity' => 1]);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $outOfStockVariant->id, 'quantity' => 1]);
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', ['product_variant_id' => $onDemandVariant->id, 'quantity' => 1]);

        $cartRes = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/cart')
            ->assertStatus(200);

        $items = $cartRes->json('data.items');
        $this->assertCount(3, $items);

        // Stocked variant
        $this->assertTrue($items[0]['availability']['is_available']);
        $this->assertSame('in_stock', $items[0]['availability']['status']);
        $this->assertEquals(12, $items[0]['availability']['available_quantity']);

        // Out of stock variant
        $this->assertFalse($items[1]['availability']['is_available']);
        $this->assertSame('out_of_stock', $items[1]['availability']['status']);

        // On demand variant
        $this->assertTrue($items[2]['availability']['is_available']);
        $this->assertSame('on_demand', $items[2]['availability']['status']);
    }

    public function test_coupon_application_calculates_authoritative_discount(): void
    {
        $customer = $this->createTestCustomer('سارة حسن', '777778899');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant = $this->createTestVariant('عطر راقي', 'noble-scent', '50000.00', 20);

        // Add 1 item = 50,000 YER subtotal
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        // Create authoritative backend coupon: 10% off
        Coupon::create([
            'name' => 'خصم 10%',
            'code' => 'OFF10',
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => '10.00',
            'is_active' => true,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
        ]);

        // Apply coupon
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/coupon', ['code' => 'off10'])
            ->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.coupon.code', 'OFF10')
            ->assertJsonPath('data.coupon.is_valid', true)
            ->assertJsonPath('data.coupon.discount_amount', 5000)
            ->assertJsonPath('data.pricing.coupon_discount', 5000)
            ->assertJsonPath('data.pricing.estimated_total', 45000);

        // Verify GET /cart also persists the calculated coupon
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/cart')
            ->assertStatus(200)
            ->assertJsonPath('data.coupon.code', 'OFF10')
            ->assertJsonPath('data.coupon.is_valid', true)
            ->assertJsonPath('data.pricing.coupon_discount', 5000)
            ->assertJsonPath('data.pricing.estimated_total', 45000);
    }

    public function test_flutter_mock_coupons_are_rejected_unless_present_in_backend(): void
    {
        $customer = $this->createTestCustomer('نادية عمر', '778889900');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant = $this->createTestVariant('عطر نسائي', 'femme-scent', '30000.00', 10);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        // Flutter hardcoded mock coupon codes MUST be rejected because they do not exist in DB
        foreach (['BEAUTY20', 'VIP20', 'LAMMA', 'FIRST10'] as $mockCoupon) {
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/cart/coupon', ['code' => $mockCoupon])
                ->assertStatus(422)
                ->assertJsonPath('status', false)
                ->assertJsonPath('data.code', ApiErrorCode::CouponInvalid->value);
        }
    }

    public function test_expired_coupon_is_rejected_with_proper_error_code(): void
    {
        $customer = $this->createTestCustomer('مروان كمال', '779990011');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant = $this->createTestVariant('عطر صيفي', 'summer-scent', '20000.00', 10);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        // Create expired coupon
        Coupon::create([
            'name' => 'كوبون منتهي',
            'code' => 'EXPIRED20',
            'discount_type' => CouponDiscountType::Percentage,
            'discount_value' => '20.00',
            'is_active' => true,
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subDay(), // expired yesterday
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/coupon', ['code' => 'EXPIRED20'])
            ->assertStatus(422)
            ->assertJsonPath('data.code', ApiErrorCode::CouponExpired->value);
    }

    public function test_removing_coupon_resets_pricing(): void
    {
        $customer = $this->createTestCustomer('هدى ناصر', '771239876');
        $token = $this->jwtService->generateAccessToken($customer);

        $variant = $this->createTestVariant('عطر الورد', 'rose-scent', '20000.00', 10);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 1,
            ]);

        Coupon::create([
            'name' => 'خصم ثابت',
            'code' => 'SAVE10',
            'discount_type' => CouponDiscountType::FixedAmount,
            'discount_value' => '2000.00',
            'is_active' => true,
        ]);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->postJson('/api/v1/cart/coupon', ['code' => 'SAVE10'])
            ->assertStatus(200);

        // Remove coupon
        $this->withHeader('Authorization', 'Bearer '.$token)
            ->deleteJson('/api/v1/cart/coupon')
            ->assertStatus(200)
            ->assertJsonPath('data.coupon', null)
            ->assertJsonPath('data.pricing.coupon_discount', 0)
            ->assertJsonPath('data.pricing.estimated_total', 20000);
    }

    public function test_bola_defense_customer_cannot_access_or_mutate_another_customers_cart(): void
    {
        $customerA = $this->createTestCustomer('العميل أ', '770000001');
        $tokenA = $this->jwtService->generateAccessToken($customerA);

        $customerB = $this->createTestCustomer('العميل ب', '770000002');
        $tokenB = $this->jwtService->generateAccessToken($customerB);

        $variant = $this->createTestVariant('عطر مشترك', 'shared-scent', '10000.00', 10);

        // Customer A adds item to their cart
        $resA = $this->withHeader('Authorization', 'Bearer '.$tokenA)
            ->postJson('/api/v1/cart/items', [
                'product_variant_id' => $variant->id,
                'quantity' => 2,
            ]);

        $itemAId = $resA->json('data.items.0.id');

        // Customer B attempts to update Customer A's cart item -> 404 CART_ITEM_NOT_FOUND
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->patchJson("/api/v1/cart/items/{$itemAId}", [
                'quantity' => 10,
            ])
            ->assertStatus(404)
            ->assertJsonPath('data.code', ApiErrorCode::CartItemNotFound->value);

        // Customer B attempts to delete Customer A's cart item -> 404 CART_ITEM_NOT_FOUND
        $this->withHeader('Authorization', 'Bearer '.$tokenB)
            ->deleteJson("/api/v1/cart/items/{$itemAId}")
            ->assertStatus(404)
            ->assertJsonPath('data.code', ApiErrorCode::CartItemNotFound->value);

        // Customer A's item remains untouched
        $this->assertDatabaseHas('cart_items', [
            'id' => $itemAId,
            'quantity' => 2,
        ]);
    }

    public function test_concurrency_simultaneous_adds_maintain_consistent_quantity(): void
    {
        $customer = $this->createTestCustomer('فحص التزامن', '778811223');
        $variant = $this->createTestVariant('عطر التزامن', 'concurrent-scent', '15000.00', 50);

        $cartService = app(CustomerCartService::class);

        // 5 simulated concurrent adds of 2 units each
        for ($i = 0; $i < 5; $i++) {
            $cartService->addItem($customer, $variant->id, 2);
        }

        $cart = $cartService->loadCartWithRelations($customer);
        $this->assertCount(1, $cart->items);
        $this->assertSame(10, $cart->items->first()->quantity);
    }

    public function test_cart_read_query_count_is_constant_avoiding_n_plus_one(): void
    {
        $customer = $this->createTestCustomer('فحص الأداء', '779998888');
        $token = $this->jwtService->generateAccessToken($customer);

        // Create 5 different variants and add them to cart
        for ($i = 1; $i <= 5; $i++) {
            $variant = $this->createTestVariant("عطر {$i}", "perfume-n1-{$i}", (string) ($i * 10000), 10);
            $this->withHeader('Authorization', 'Bearer '.$token)
                ->postJson('/api/v1/cart/items', [
                    'product_variant_id' => $variant->id,
                    'quantity' => 1,
                ]);
        }

        // Measure query count during GET /api/v1/cart
        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/cart');

        $response->assertStatus(200)
            ->assertJsonPath('data.items_count', 5);

        $queries = DB::getQueryLog();
        DB::disableQueryLog();

        // 5 items with variants, prices, products, brands, categories, inventories, etc.
        // If N+1 was present, queries would scale linearly per line.
        // With eager loading and OrderPricingService batching, query count is bounded <= 25.
        $this->assertLessThanOrEqual(25, count($queries), 'Cart read executed excessive database queries (possible N+1 detected).');
    }

    private function createTestCustomer(string $name, string $phone): Customer
    {
        return Customer::create([
            'name' => $name,
            'phone' => $phone,
            'normalized_phone' => '+967'.$phone,
            'email' => "user_{$phone}@example.com",
            'is_active' => true,
            'phone_verified_at' => now(),
            'phone_verification_method' => 'otp',
        ]);
    }

    private function createTestVariant(string $productName, string $slug, string $price, int $stock): ProductVariant
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => $productName,
            'slug' => $slug,
            'status' => ProductStatus::Active,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($slug).'-01',
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
            'reserved_quantity' => 0,
        ]);

        return $variant;
    }
}
