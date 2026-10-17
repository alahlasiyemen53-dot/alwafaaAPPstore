<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ApiErrorCode;
use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Customer;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductReview;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class CustomerRelatedProductsApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Warehouse $warehouse;

    private Brand $brand;

    private Category $categoryA;

    private Category $categoryB;

    protected function setUp(): void
    {
        parent::setUp();

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
            'name_ar' => 'ماركة أصيلة',
            'name_en' => 'Authentic Brand',
            'slug' => 'authentic-brand',
            'is_active' => true,
        ]);

        $this->categoryA = Category::create([
            'name_ar' => 'عطور نسائية',
            'name_en' => 'Women Perfumes',
            'slug' => 'women-perfumes',
            'is_active' => true,
        ]);

        $this->categoryB = Category::create([
            'name_ar' => 'عناية بالبشرة',
            'name_en' => 'Skin Care',
            'slug' => 'skin-care',
            'is_active' => true,
        ]);
    }

    private function createProductWithVariant(
        string $nameAr,
        string $slug,
        Category $category,
        float $price = 10000.0,
        ProductStatus $status = ProductStatus::Active,
        bool $isBestSeller = false,
        bool $isNew = false
    ): Product {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $category->id,
            'name_ar' => $nameAr,
            'name_en' => $slug,
            'slug' => $slug,
            'status' => $status,
            'is_best_seller' => $isBestSeller,
            'is_new' => $isNew,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => strtoupper($slug) . '-VAR',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
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
            'quantity' => 10.0,
            'reserved_quantity' => 0.0,
        ]);

        return $product;
    }

    public function test_returns_same_category_products_and_excludes_current_product(): void
    {
        $current = $this->createProductWithVariant('عطر الزهور الحالي', 'current-flower-perfume', $this->categoryA);
        $related1 = $this->createProductWithVariant('عطر الورد', 'rose-perfume', $this->categoryA);
        $related2 = $this->createProductWithVariant('عطر الياسمين', 'jasmine-perfume', $this->categoryA);
        $otherCat = $this->createProductWithVariant('كريم مرطب', 'moisturizer-cream', $this->categoryB);

        $response = $this->getJson("/api/v1/products/{$current->id}/related");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonCount(2, 'data.items');

        $ids = collect($response->json('data.items'))->pluck('id')->all();

        $this->assertContains($related1->id, $ids);
        $this->assertContains($related2->id, $ids);
        $this->assertNotContains($current->id, $ids, 'Current product must be excluded from related products');
        $this->assertNotContains($otherCat->id, $ids, 'Products from different categories must be excluded');
    }

    public function test_excludes_inactive_products_and_inactive_category_products(): void
    {
        $current = $this->createProductWithVariant('عطر رئيسي', 'main-perfume', $this->categoryA);
        $activeRelated = $this->createProductWithVariant('عطر نشط', 'active-perfume', $this->categoryA);
        $inactiveRelated = $this->createProductWithVariant('عطر معطل', 'inactive-perfume', $this->categoryA, 5000, ProductStatus::Inactive);

        $response = $this->getJson("/api/v1/products/{$current->id}/related");

        $response->assertStatus(200);
        $ids = collect($response->json('data.items'))->pluck('id')->all();

        $this->assertContains($activeRelated->id, $ids);
        $this->assertNotContains($inactiveRelated->id, $ids, 'Inactive products must be excluded');
    }

    public function test_target_product_by_slug_resolves_correctly(): void
    {
        $current = $this->createProductWithVariant('عطر العود', 'oud-supreme', $this->categoryA);
        $related = $this->createProductWithVariant('عطر المسك', 'musk-supreme', $this->categoryA);

        $response = $this->getJson('/api/v1/products/oud-supreme/related');

        $response->assertStatus(200)
            ->assertJsonPath('status', true);

        $ids = collect($response->json('data.items'))->pluck('id')->all();
        $this->assertContains($related->id, $ids);
    }

    public function test_product_with_inactive_category_returns_empty_items(): void
    {
        $inactiveCategory = Category::create([
            'name_ar' => 'قسم معطل',
            'name_en' => 'Inactive Category',
            'slug' => 'inactive-category',
            'is_active' => false,
        ]);

        $productInactiveCat = $this->createProductWithVariant('منتج بقسم معطل', 'inactive-cat-prod', $inactiveCategory);

        $response = $this->getJson("/api/v1/products/{$productInactiveCat->id}/related");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.items', []);
    }

    public function test_empty_category_returns_empty_items(): void
    {
        $soleProduct = $this->createProductWithVariant('منتج وحيد في القسم', 'sole-product', $this->categoryB);

        $response = $this->getJson("/api/v1/products/{$soleProduct->id}/related");

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.items', []);
    }

    public function test_returns_404_for_inactive_or_nonexistent_current_product(): void
    {
        $inactiveProduct = $this->createProductWithVariant('منتج مغلق', 'hidden-product', $this->categoryA, 1000, ProductStatus::Inactive);

        $resInactive = $this->getJson("/api/v1/products/{$inactiveProduct->id}/related");
        $resInactive->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::ProductNotFound->value);

        $resMissing = $this->getJson('/api/v1/products/missing-slug-9999/related');
        $resMissing->assertStatus(404)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::ProductNotFound->value);
    }

    public function test_results_are_bounded_by_default_and_limit_param(): void
    {
        $current = $this->createProductWithVariant('منتج قيادي', 'leader-product', $this->categoryA);

        for ($i = 1; $i <= 9; $i++) {
            $this->createProductWithVariant("منتج مشابه {$i}", "similar-item-{$i}", $this->categoryA);
        }

        // Default limit is 8
        $responseDefault = $this->getJson("/api/v1/products/{$current->id}/related");
        $responseDefault->assertStatus(200)
            ->assertJsonCount(8, 'data.items');

        // Custom limit 4
        $responseLimit4 = $this->getJson("/api/v1/products/{$current->id}/related?limit=4");
        $responseLimit4->assertStatus(200)
            ->assertJsonCount(4, 'data.items');

        // Maximum limit clamped
        $responseLimitMax = $this->getJson("/api/v1/products/{$current->id}/related?limit=50");
        $responseLimitMax->assertStatus(200)
            ->assertJsonCount(9, 'data.items');
    }

    public function test_deterministic_ordering_prioritizes_best_seller_and_new(): void
    {
        $current = $this->createProductWithVariant('المنتج المفتوح', 'open-item', $this->categoryA);

        $regular = $this->createProductWithVariant('منتج عادي', 'regular-item', $this->categoryA);
        $new = $this->createProductWithVariant('منتج جديد', 'new-item', $this->categoryA, 10000, ProductStatus::Active, false, true);
        $bestSeller = $this->createProductWithVariant('منتج أكثر مبيعا', 'bestseller-item', $this->categoryA, 10000, ProductStatus::Active, true, false);

        $response = $this->getJson("/api/v1/products/{$current->id}/related");
        $response->assertStatus(200);

        $items = $response->json('data.items');
        $this->assertEquals($bestSeller->id, $items[0]['id'], 'Best seller should be ordered first');
        $this->assertEquals($new->id, $items[1]['id'], 'New product should be ordered next');
        $this->assertEquals($regular->id, $items[2]['id'], 'Regular product should follow');
    }

    public function test_response_payload_structure_matches_customer_product_list_resource(): void
    {
        $current = $this->createProductWithVariant('منتج أصل', 'origin-item', $this->categoryA);
        $related = $this->createProductWithVariant('عطر فاخر', 'luxury-scent', $this->categoryA, 15000.0, ProductStatus::Active, true, true);

        // Add review
        $customer = Customer::create(['name' => 'فاطمة', 'phone' => '773334455', 'normalized_phone' => '+967773334455', 'is_active' => true]);
        ProductReview::create(['customer_id' => $customer->id, 'product_id' => $related->id, 'rating' => 4, 'status' => ProductReviewStatus::Approved]);

        $response = $this->getJson("/api/v1/products/{$current->id}/related");
        $response->assertStatus(200);

        $item = $response->json('data.items.0');
        $this->assertEquals($related->id, $item['id']);
        $this->assertEquals('عطر فاخر', $item['name_ar']);
        $this->assertEquals('luxury-scent', $item['slug']);
        $this->assertEquals('Authentic Brand', $item['brand']['name_en']);
        $this->assertEquals('Women Perfumes', $item['category']['name_en']);
        $this->assertEquals('15000.00', $item['price_summary']['min_price']);
        $this->assertEquals('YER', $item['price_summary']['currency']);
        $this->assertTrue($item['availability']['is_available']);
        $this->assertEquals('in_stock', $item['availability']['status']);
        $this->assertEquals(4.0, $item['rating']['average']);
        $this->assertEquals(1, $item['rating']['reviews_count']);
        $this->assertTrue($item['badges']['is_best_seller']);
        $this->assertTrue($item['badges']['is_new']);
    }

    public function test_query_count_does_not_suffer_from_n_plus_one(): void
    {
        $current = $this->createProductWithVariant('منتج مرجعي', 'ref-item', $this->categoryA);

        for ($i = 1; $i <= 3; $i++) {
            $this->createProductWithVariant("منتج فحص {$i}", "check-item-{$i}", $this->categoryA);
        }

        DB::flushQueryLog();
        DB::enableQueryLog();

        $response = $this->getJson("/api/v1/products/{$current->id}/related");
        $response->assertStatus(200);

        $queryCount = count(DB::getQueryLog());
        // Target query count should be constant and bounded (<= 15 queries total)
        $this->assertLessThanOrEqual(15, $queryCount, "Query count ({$queryCount}) should prevent N+1 queries");
    }

}
