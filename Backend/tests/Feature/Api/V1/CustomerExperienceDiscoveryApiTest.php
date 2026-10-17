<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\HomeSectionType;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\CustomerRecentlyViewedProduct;
use App\Models\CustomerSearchHistory;
use App\Models\HomeSection;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductCollection;
use App\Models\ProductCollectionItem;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use App\Services\Auth\CustomerJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

final class CustomerExperienceDiscoveryApiTest extends TestCase
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
            'code' => 'WH-CX',
            'is_active' => true,
        ]);

        $this->brand = Brand::create([
            'name_ar' => 'لمّة',
            'name_en' => 'Lamma',
            'slug' => 'lamma-cx',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'عناية',
            'name_en' => 'Care',
            'slug' => 'care-cx',
            'is_active' => true,
        ]);
    }

    public function test_recently_viewed_upserts_and_bounds(): void
    {
        config(['customer_experience.recently_viewed_limit' => 2]);

        $customer = $this->createTestCustomer('فاطمة', '770100001');
        $token = $this->jwtService->generateAccessToken($customer);

        $p1 = $this->createTestProduct('منتج 1', 'P1', 'p1-cx', '1000.00');
        $p2 = $this->createTestProduct('منتج 2', 'P2', 'p2-cx', '2000.00');
        $p3 = $this->createTestProduct('منتج 3', 'P3', 'p3-cx', '3000.00');

        foreach ([$p1, $p2, $p3] as $product) {
            $this->withHeaders(['Authorization' => "Bearer {$token}"])
                ->postJson('/api/v1/products/'.$product->id.'/view')
                ->assertOk();
        }

        $this->assertSame(2, CustomerRecentlyViewedProduct::query()->where('customer_id', $customer->id)->count());

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->getJson('/api/v1/me/recently-viewed')
            ->assertOk();

        $ids = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertSame([$p3->id, $p2->id], $ids);
    }

    public function test_search_history_dedupes_and_clears(): void
    {
        $customer = $this->createTestCustomer('أحمد', '770100002');
        $token = $this->jwtService->generateAccessToken($customer);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/me/search-history', ['query' => 'سيروم'])
            ->assertOk();
        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/me/search-history', ['query' => '  سيروم  '])
            ->assertOk();

        $this->assertSame(1, CustomerSearchHistory::query()->where('customer_id', $customer->id)->count());

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->deleteJson('/api/v1/me/search-history')
            ->assertOk();

        $this->assertSame(0, CustomerSearchHistory::query()->where('customer_id', $customer->id)->count());
    }

    public function test_guest_search_history_merge_dedupes_and_bounds(): void
    {
        config(['customer_experience.search_history_limit' => 2]);

        $customer = $this->createTestCustomer('ليلى', '770100003');
        $token = $this->jwtService->generateAccessToken($customer);

        $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/me/search-history', ['query' => 'قديم'])
            ->assertOk();

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/me/search-history/merge', [
                'queries' => ['سيروم', 'كريم', 'ماسك', '  سيروم  '],
            ])
            ->assertOk();

        $queries = collect($response->json('data.items'))->pluck('query')->all();
        $this->assertSame(['سيروم', 'كريم'], $queries);
        $this->assertSame(2, CustomerSearchHistory::query()->where('customer_id', $customer->id)->count());
    }

    public function test_guest_recently_viewed_merge_validates_and_bounds(): void
    {
        config(['customer_experience.recently_viewed_limit' => 2]);

        $customer = $this->createTestCustomer('سارة', '770100004');
        $token = $this->jwtService->generateAccessToken($customer);

        $p1 = $this->createTestProduct('منتج أ', 'A', 'a-cx-merge', '1000.00');
        $p2 = $this->createTestProduct('منتج ب', 'B', 'b-cx-merge', '2000.00');
        $p3 = $this->createTestProduct('منتج ج', 'C', 'c-cx-merge', '3000.00');
        $draft = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'مسودة',
            'name_en' => 'Draft',
            'slug' => 'draft-cx-merge',
            'status' => ProductStatus::Draft,
        ]);

        $response = $this->withHeaders(['Authorization' => "Bearer {$token}"])
            ->postJson('/api/v1/me/recently-viewed/merge', [
                'product_ids' => [$p3->id, $draft->id, $p2->id, $p1->id],
            ])
            ->assertOk();

        $ids = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertSame([$p3->id, $p2->id], $ids);
        $this->assertSame(2, CustomerRecentlyViewedProduct::query()->where('customer_id', $customer->id)->count());
    }

    public function test_collection_hides_inactive_products(): void
    {
        $collection = ProductCollection::query()->create([
            'name_ar' => 'مختارات',
            'name_en' => 'Picks',
            'slug' => 'picks-cx',
            'is_active' => true,
            'sort_order' => 1,
        ]);

        $active = $this->createTestProduct('نشط', 'Active', 'active-cx', '1500.00');
        $inactive = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'غير نشط',
            'name_en' => 'Inactive',
            'slug' => 'inactive-cx',
            'status' => ProductStatus::Draft,
        ]);

        ProductCollectionItem::query()->create([
            'collection_id' => $collection->id,
            'product_id' => $active->id,
            'sort_order' => 1,
        ]);
        ProductCollectionItem::query()->create([
            'collection_id' => $collection->id,
            'product_id' => $inactive->id,
            'sort_order' => 2,
        ]);

        $response = $this->getJson('/api/v1/collections/'.$collection->slug)->assertOk();
        $ids = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertContains($active->id, $ids);
        $this->assertNotContains($inactive->id, $ids);
    }

    public function test_home_sections_respect_sort_order(): void
    {
        HomeSection::query()->create([
            'type' => HomeSectionType::BestSellers->value,
            'title_ar' => 'الأكثر مبيعاً',
            'title_en' => 'Best',
            'is_active' => true,
            'sort_order' => 1,
            'configuration' => [],
        ]);
        HomeSection::query()->create([
            'type' => HomeSectionType::Banners->value,
            'title_ar' => 'البنرات',
            'title_en' => 'Banners',
            'is_active' => true,
            'sort_order' => 2,
            'configuration' => [],
        ]);

        $types = collect($this->getJson('/api/v1/home')->assertOk()->json('data.home_sections'))
            ->pluck('type')
            ->all();

        $this->assertSame([
            HomeSectionType::BestSellers->value,
            HomeSectionType::Banners->value,
        ], $types);
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
            'is_exclusive' => false,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.$slug,
            'status' => ProductVariantStatus::Active,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'price' => $price,
            'is_active' => true,
        ]);

        Inventory::create([
            'product_variant_id' => $variant->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity' => 10,
            'reserved_quantity' => 0,
        ]);

        return $product;
    }
}
