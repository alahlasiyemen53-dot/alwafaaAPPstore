<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Actions\AddProductToCustomerWishlistAction;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerWishlistItem;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\Auth\CustomerJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CustomerWishlistApiTest extends TestCase
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
            'name_ar' => 'عطور فاخرة',
            'name_en' => 'Luxury Perfumes',
            'slug' => 'luxury-perfumes',
            'is_active' => true,
        ]);
    }

    public function test_unauthenticated_requests_are_rejected_with_401(): void
    {
        // 1. GET /wishlist
        $this->getJson('/api/v1/wishlist')
            ->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');

        // 2. POST /wishlist
        $this->postJson('/api/v1/wishlist', ['product_id' => 1])
            ->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');

        // 3. DELETE /wishlist/1
        $this->deleteJson('/api/v1/wishlist/1')
            ->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_authenticated_customer_can_list_wishlist_items_with_pagination(): void
    {
        $customer = $this->createTestCustomer('فاطمة علي', '770001111');
        $token = $this->jwtService->generateAccessToken($customer);

        $product1 = $this->createTestProduct('عطر ميس ديور', 'Miss Dior', 'miss-dior', '28000.00');
        $product2 = $this->createTestProduct('عطر جادور ديور', 'Jadore Dior', 'jadore-dior', '32000.00');

        CustomerWishlistItem::create(['customer_id' => $customer->id, 'product_id' => $product1->id]);
        CustomerWishlistItem::create(['customer_id' => $customer->id, 'product_id' => $product2->id]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/wishlist?per_page=10');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.pagination.total', 2)
            ->assertJsonPath('data.pagination.per_page', 10)
            ->assertJsonPath('data.items.0.price_summary.currency', 'YER');

        $itemIds = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertContains($product1->id, $itemIds);
        $this->assertContains($product2->id, $itemIds);
    }

    public function test_authenticated_customer_can_add_product_to_wishlist_idempotently(): void
    {
        $customer = $this->createTestCustomer('أحمد محمد', '770002222');
        $token = $this->jwtService->generateAccessToken($customer);
        $product = $this->createTestProduct('عطر سوفاج ديور', 'Sauvage Dior', 'sauvage-dior', '40000.00');

        // 1. Initial Add
        $response1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/wishlist', [
                'product_id' => $product->id,
            ]);

        $response1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_id', $product->id);

        $this->assertDatabaseHas('customer_wishlist_items', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);
        $this->assertSame(1, CustomerWishlistItem::where('customer_id', $customer->id)->count());

        // 2. Idempotent Second Add (Duplicate request)
        $response2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/wishlist', [
                'product_id' => $product->id,
            ]);

        $response2->assertOk()
            ->assertJsonPath('success', true);

        $this->assertSame(1, CustomerWishlistItem::where('customer_id', $customer->id)->count());
    }

    public function test_authenticated_customer_can_remove_product_from_wishlist_idempotently(): void
    {
        $customer = $this->createTestCustomer('يوسف خالد', '770003333');
        $token = $this->jwtService->generateAccessToken($customer);
        $product = $this->createTestProduct('عطر فهرنهايت', 'Fahrenheit Dior', 'fahrenheit-dior', '30000.00');

        CustomerWishlistItem::create([
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        // 1. First remove
        $response1 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->deleteJson("/api/v1/wishlist/{$product->id}");

        $response1->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.product_id', $product->id);

        $this->assertDatabaseMissing('customer_wishlist_items', [
            'customer_id' => $customer->id,
            'product_id' => $product->id,
        ]);

        // 2. Second remove by slug (idempotent removal)
        $response2 = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->deleteJson("/api/v1/wishlist/{$product->slug}");

        $response2->assertOk()
            ->assertJsonPath('success', true);
    }

    public function test_strictly_enforces_bola_ownership_isolation_between_customers(): void
    {
        $customerA = $this->createTestCustomer('العميل الأول', '770004441');
        $customerB = $this->createTestCustomer('العميل الثاني', '770004442');

        $tokenA = $this->jwtService->generateAccessToken($customerA);
        $tokenB = $this->jwtService->generateAccessToken($customerB);

        $productA = $this->createTestProduct('منتج أ', 'Product A', 'product-a', '10000.00');
        $productB = $this->createTestProduct('منتج ب', 'Product B', 'product-b', '20000.00');

        CustomerWishlistItem::create(['customer_id' => $customerA->id, 'product_id' => $productA->id]);
        CustomerWishlistItem::create(['customer_id' => $customerB->id, 'product_id' => $productB->id]);

        // Customer A listing only sees Product A, NEVER Product B
        $responseA = $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
            ->getJson('/api/v1/wishlist');

        $responseA->assertOk();
        $itemIdsA = collect($responseA->json('data.items'))->pluck('id')->all();
        $this->assertContains($productA->id, $itemIdsA);
        $this->assertNotContains($productB->id, $itemIdsA);

        // Customer A attempts to delete Product B (belongs to Customer B's wishlist)
        $deleteResponse = $this->withHeaders(['Authorization' => "Bearer {$tokenA}"])
            ->deleteJson("/api/v1/wishlist/{$productB->id}");

        $deleteResponse->assertOk();

        // Customer B's wishlist item MUST STILL EXIST
        $this->assertDatabaseHas('customer_wishlist_items', [
            'customer_id' => $customerB->id,
            'product_id' => $productB->id,
        ]);
    }

    public function test_shields_inactive_or_deleted_products_from_wishlist_listing(): void
    {
        $customer = $this->createTestCustomer('مريم سالم', '770005555');
        $token = $this->jwtService->generateAccessToken($customer);

        $activeProduct = $this->createTestProduct('منتج نشط', 'Active Product', 'active-prod', '15000.00');

        $inactiveProduct = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج غير نشط',
            'name_en' => 'Inactive Product',
            'slug' => 'inactive-prod',
            'status' => ProductStatus::Draft,
        ]);

        CustomerWishlistItem::create(['customer_id' => $customer->id, 'product_id' => $activeProduct->id]);
        CustomerWishlistItem::create(['customer_id' => $customer->id, 'product_id' => $inactiveProduct->id]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/wishlist');

        $response->assertOk();
        $itemIds = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertContains($activeProduct->id, $itemIds);
        $this->assertNotContains($inactiveProduct->id, $itemIds);
        $this->assertSame(1, $response->json('data.pagination.total'));
    }

    public function test_returns_appropriate_error_when_adding_or_removing_non_existent_product(): void
    {
        $customer = $this->createTestCustomer('سالم عبده', '770006666');
        $token = $this->jwtService->generateAccessToken($customer);

        // Add non-existent product ID
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/wishlist', ['product_id' => 99999])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['product_id']);

        // Remove non-existent product slug
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->deleteJson('/api/v1/wishlist/non-existent-slug-99999')
            ->assertStatus(404)
            ->assertJsonPath('data.code', 'PRODUCT_NOT_FOUND');
    }

    public function test_handles_concurrency_race_condition_safely(): void
    {
        $customer = $this->createTestCustomer('ناصر صالح', '770007777');
        $product = $this->createTestProduct('عطر التنافس', 'Concurrent Perfume', 'concurrent-perfume', '18000.00');

        $action = new AddProductToCustomerWishlistAction();

        // Simulate concurrent execution
        $item1 = $action->execute($customer, $product);
        $item2 = $action->execute($customer, $product);

        $this->assertSame($item1->id, $item2->id);
        $this->assertSame(1, CustomerWishlistItem::where('customer_id', $customer->id)->count());
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

    private function createTestProduct(string $nameAr, string $nameEn, string $slug, string $price): Product
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => $nameAr,
            'name_en' => $nameEn,
            'slug' => $slug,
            'status' => ProductStatus::Active,
            'is_best_seller' => true,
            'is_new' => false,
            'is_exclusive' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($slug).'-01',
            'is_default' => true,
            'status' => ProductVariantStatus::Active,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'price' => $price,
            'compare_at_price' => '50000.00',
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        return $product;
    }
}
