<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ProductReviewStatus;
use App\Enums\ProductStatus;
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
use Tests\TestCase;

final class CustomerProductListApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Warehouse $warehouse;

    private Brand $brand;

    private Category $category;

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
            'name_ar' => 'لطافة',
            'name_en' => 'Lattafa',
            'slug' => 'lattafa',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'عطور',
            'name_en' => 'Perfumes',
            'slug' => 'perfumes',
            'sort_order' => 1,
            'is_active' => true,
        ]);
    }

    public function test_products_index_returns_paginated_active_products(): void
    {
        $activeProduct = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'عطر مسك الليل',
            'name_en' => 'Night Musk',
            'slug' => 'night-musk',
            'status' => ProductStatus::Active,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $activeProduct->id,
            'sku' => 'MSK-001',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'price' => 15000.00,
            'compare_at_price' => 18000.00,
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 10.0,
            'reserved_quantity' => 0.0,
        ]);

        $inactiveProduct = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'منتج غير نشط',
            'name_en' => 'Inactive Product',
            'slug' => 'inactive-product',
            'status' => ProductStatus::Inactive,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.pagination.total', 1)
            ->assertJsonPath('data.items.0.id', $activeProduct->id)
            ->assertJsonPath('data.items.0.slug', 'night-musk')
            ->assertJsonPath('data.items.0.brand.name_ar', 'لطافة')
            ->assertJsonPath('data.items.0.price_summary.min_price', '15000.00')
            ->assertJsonPath('data.items.0.availability.is_available', true)
            ->assertJsonPath('data.items.0.availability.status', 'in_stock');

        // Verify inactive product is absent
        $items = $response->json('data.items');
        $this->assertCount(1, $items);
        $this->assertNotContains($inactiveProduct->id, array_column($items, 'id'));
    }

    public function test_products_index_returns_merchandising_badges_and_ratings(): void
    {
        $product = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'عطر الورد الحصري',
            'name_en' => 'Exclusive Rose',
            'slug' => 'exclusive-rose',
            'status' => ProductStatus::Active,
            'is_best_seller' => true,
            'is_new' => true,
            'is_exclusive' => true,
        ]);

        $variant = ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'ROSE-001',
            'status' => ProductVariantStatus::Active,
            'is_default' => true,
        ]);

        VariantPrice::create([
            'product_variant_id' => $variant->id,
            'currency_id' => $this->currency->id,
            'price' => 22000.00,
            'is_active' => true,
        ]);

        $customer1 = Customer::create([
            'name' => 'أحمد سعيد',
            'phone' => '770000001',
            'normalized_phone' => '+967770000001',
            'is_active' => true,
        ]);

        $customer2 = Customer::create([
            'name' => 'منى علي',
            'phone' => '770000002',
            'normalized_phone' => '+967770000002',
            'is_active' => true,
        ]);

        $customer3 = Customer::create([
            'name' => 'سالم قاسم',
            'phone' => '770000003',
            'normalized_phone' => '+967770000003',
            'is_active' => true,
        ]);

        // 2 Approved reviews (5 and 4 -> avg 4.5)
        ProductReview::create([
            'customer_id' => $customer1->id,
            'product_id' => $product->id,
            'rating' => 5,
            'status' => ProductReviewStatus::Approved,
        ]);

        ProductReview::create([
            'customer_id' => $customer2->id,
            'product_id' => $product->id,
            'rating' => 4,
            'status' => ProductReviewStatus::Approved,
        ]);

        // 1 Pending review (should NOT count)
        ProductReview::create([
            'customer_id' => $customer3->id,
            'product_id' => $product->id,
            'rating' => 1,
            'status' => ProductReviewStatus::Pending,
        ]);

        $response = $this->getJson('/api/v1/products');

        $response->assertStatus(200)
            ->assertJsonPath('data.items.0.badges.is_best_seller', true)
            ->assertJsonPath('data.items.0.badges.is_new', true)
            ->assertJsonPath('data.items.0.badges.is_exclusive', true)
            ->assertJsonPath('data.items.0.rating.average', 4.5)
            ->assertJsonPath('data.items.0.rating.reviews_count', 2);
    }

    public function test_products_index_enforces_bounded_pagination(): void
    {
        $response = $this->getJson('/api/v1/products?per_page=100');

        $response->assertStatus(200)
            ->assertJsonPath('data.pagination.per_page', 50); // Bounded to max 50
    }
}
