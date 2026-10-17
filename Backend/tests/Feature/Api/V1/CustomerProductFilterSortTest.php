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
use App\Models\Supplier;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerProductFilterSortTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Warehouse $warehouse;

    private Brand $brandA;

    private Brand $brandB;

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
            'name_ar' => 'مخزن صنعاء',
            'code' => 'WH-SANAA',
            'is_active' => true,
        ]);

        $this->brandA = Brand::create(['name_ar' => 'ماركة أ', 'slug' => 'brand-a', 'is_active' => true]);
        $this->brandB = Brand::create(['name_ar' => 'ماركة ب', 'slug' => 'brand-b', 'is_active' => true]);

        $this->categoryA = Category::create(['name_ar' => 'عطور', 'slug' => 'perfumes', 'is_active' => true]);
        $this->categoryB = Category::create(['name_ar' => 'مكياج', 'slug' => 'makeup', 'is_active' => true]);
    }

    private function createProduct(array $attributes = []): Product
    {
        $product = Product::create(array_merge([
            'brand_id' => $this->brandA->id,
            'category_id' => $this->categoryA->id,
            'name_ar' => 'منتج تجريبي',
            'name_en' => 'Test Product',
            'slug' => 'test-product-'.uniqid(),
            'status' => ProductStatus::Active,
        ], $attributes));

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'SKU-'.uniqid(),
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'price' => 10000.00,
            'is_active' => true,
        ]);

        return $product;
    }

    public function test_filter_by_category_and_brand(): void
    {
        $productA = $this->createProduct(['name_ar' => 'منتج عطور أ', 'category_id' => $this->categoryA->id, 'brand_id' => $this->brandA->id]);
        $productB = $this->createProduct(['name_ar' => 'منتج مكياج ب', 'category_id' => $this->categoryB->id, 'brand_id' => $this->brandB->id]);

        // Filter category
        $respCat = $this->getJson("/api/v1/products?category_id={$this->categoryA->id}");
        $respCat->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $productA->id);

        // Filter brand
        $respBrand = $this->getJson("/api/v1/products?brand_id={$this->brandB->id}");
        $respBrand->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $productB->id);

        // Multi-brand filter (OR within brands)
        $respMulti = $this->getJson('/api/v1/products?'.http_build_query([
            'brand_id' => [$this->brandA->id, $this->brandB->id],
        ]));
        $respMulti->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 2);

        // Multi-category filter (OR within categories)
        $respCats = $this->getJson('/api/v1/products?'.http_build_query([
            'category_id' => [$this->categoryA->id, $this->categoryB->id],
        ]));
        $respCats->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 2);
    }

    public function test_filter_by_merchandising_badge(): void
    {
        $bestSeller = $this->createProduct(['name_ar' => 'الأكثر مبيعاً', 'is_best_seller' => true]);
        $newProd = $this->createProduct(['name_ar' => 'منتج جديد', 'is_new' => true]);
        $exclusiveProd = $this->createProduct(['name_ar' => 'منتج حصري', 'is_exclusive' => true]);

        $respBest = $this->getJson('/api/v1/products?badge=best_seller');
        $respBest->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $bestSeller->id);

        $respNew = $this->getJson('/api/v1/products?badge=new');
        $respNew->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $newProd->id);

        $respExcl = $this->getJson('/api/v1/products?badge=exclusive');
        $respExcl->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $exclusiveProd->id);
    }

    public function test_filter_by_availability_stocked_and_on_demand(): void
    {
        // 1. Stocked product with inventory
        $prodStocked = Product::create([
            'brand_id' => $this->brandA->id,
            'category_id' => $this->categoryA->id,
            'name_ar' => 'متوفر في المخزون',
            'slug' => 'in-stock-prod',
            'status' => ProductStatus::Active,
        ]);
        $varStocked = ProductVariant::create([
            'product_id' => $prodStocked->id,
            'sku' => 'SKU-STOCKED',
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::Stocked,
        ]);
        VariantPrice::create([
            'product_variant_id' => $varStocked->id,
            'currency_id' => $this->currency->id,
            'price' => 5000.00,
            'is_active' => true,
        ]);
        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $varStocked->id,
            'quantity' => 15.0,
            'reserved_quantity' => 0.0,
        ]);

        // 2. On Demand product with active supplier
        $supplier = Supplier::create([
            'name' => 'المورد الذهبي',
            'code' => 'SUP-GOLD',
            'phone' => '770000999',
            'is_active' => true,
        ]);
        $prodOnDemand = Product::create([
            'brand_id' => $this->brandA->id,
            'category_id' => $this->categoryA->id,
            'name_ar' => 'متوفر عند الطلب',
            'slug' => 'on-demand-prod',
            'status' => ProductStatus::Active,
        ]);
        $varOnDemand = ProductVariant::create([
            'product_id' => $prodOnDemand->id,
            'sku' => 'SKU-ONDEMAND',
            'status' => ProductVariantStatus::Active,
            'fulfillment_mode' => ProductVariantFulfillmentMode::OnDemand,
            'supplier_id' => $supplier->id,
        ]);
        VariantPrice::create([
            'product_variant_id' => $varOnDemand->id,
            'currency_id' => $this->currency->id,
            'price' => 8000.00,
            'is_active' => true,
        ]);

        // Filter in_stock
        $respInStock = $this->getJson('/api/v1/products?availability=in_stock');
        $respInStock->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $prodStocked->id);

        // Filter on_demand
        $respOnDemand = $this->getJson('/api/v1/products?availability=on_demand');
        $respOnDemand->assertStatus(200)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $prodOnDemand->id);
    }

    public function test_sorting_by_price_and_rating(): void
    {
        // Cheap product with low rating
        $cheapProd = Product::create([
            'brand_id' => $this->brandA->id,
            'category_id' => $this->categoryA->id,
            'name_ar' => 'منتج رخيص',
            'slug' => 'cheap-prod',
            'status' => ProductStatus::Active,
        ]);
        $cheapVar = ProductVariant::create([
            'product_id' => $cheapProd->id,
            'sku' => 'CHEAP-01',
            'status' => ProductVariantStatus::Active,
        ]);
        VariantPrice::create([
            'product_variant_id' => $cheapVar->id,
            'currency_id' => $this->currency->id,
            'price' => 5000.00,
            'is_active' => true,
        ]);

        // Expensive product with high rating
        $expensiveProd = Product::create([
            'brand_id' => $this->brandA->id,
            'category_id' => $this->categoryA->id,
            'name_ar' => 'منتج فاخر',
            'slug' => 'expensive-prod',
            'status' => ProductStatus::Active,
        ]);
        $expensiveVar = ProductVariant::create([
            'product_id' => $expensiveProd->id,
            'sku' => 'EXP-01',
            'status' => ProductVariantStatus::Active,
        ]);
        VariantPrice::create([
            'product_variant_id' => $expensiveVar->id,
            'currency_id' => $this->currency->id,
            'price' => 30000.00,
            'is_active' => true,
        ]);

        $cust = Customer::create(['name' => 'زبون', 'phone' => '771112233', 'normalized_phone' => '+967771112233', 'is_active' => true]);
        ProductReview::create(['customer_id' => $cust->id, 'product_id' => $expensiveProd->id, 'rating' => 5, 'status' => ProductReviewStatus::Approved]);

        // Sort price_asc
        $respPriceAsc = $this->getJson('/api/v1/products?sort=price_asc');
        $respPriceAsc->assertStatus(200)
            ->assertJsonPath('data.items.0.id', $cheapProd->id)
            ->assertJsonPath('data.items.1.id', $expensiveProd->id);

        // Sort price_desc
        $respPriceDesc = $this->getJson('/api/v1/products?sort=price_desc');
        $respPriceDesc->assertStatus(200)
            ->assertJsonPath('data.items.0.id', $expensiveProd->id)
            ->assertJsonPath('data.items.1.id', $cheapProd->id);

        // Sort rating
        $respRating = $this->getJson('/api/v1/products?sort=rating');
        $respRating->assertStatus(200)
            ->assertJsonPath('data.items.0.id', $expensiveProd->id);
    }

    public function test_invalid_filter_or_sort_returns_422(): void
    {
        $respBadSort = $this->getJson('/api/v1/products?sort=malicious_sql_column');
        $respBadSort->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);

        $respBadBadge = $this->getJson('/api/v1/products?badge=vip_secret');
        $respBadBadge->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', ApiErrorCode::ValidationFailed->value);
    }
}
