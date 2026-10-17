<?php

namespace Tests\Feature\Search;

use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Product;
use App\Services\Search\SearchResultRehydrationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SearchResultRehydrationServiceTest extends TestCase
{
    use RefreshDatabase;

    private SearchResultRehydrationService $rehydrationService;

    protected function setUp(): void
    {
        parent::setUp();

        Currency::create([
            'code' => 'YER',
            'name_ar' => 'ريال يمني',
            'name_en' => 'Yemeni Rial',
            'symbol' => 'ر.ي',
            'decimal_places' => 2,
            'is_default' => true,
            'is_active' => true,
        ]);

        $this->rehydrationService = new SearchResultRehydrationService;
    }

    public function test_rehydration_preserves_order_and_filters_inactive_and_nonexistent_products(): void
    {
        $brand = Brand::create(['name_ar' => 'آبل', 'name_en' => 'Apple', 'slug' => 'apple', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'أجهزة', 'name_en' => 'Devices', 'slug' => 'devices', 'is_active' => true]);

        $product1 = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'آيفون 15',
            'name_en' => 'iPhone 15',
            'slug' => 'iphone-15',
            'status' => ProductStatus::Active,
        ]);

        $product2 = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'ماك بوك برو',
            'name_en' => 'MacBook Pro',
            'slug' => 'macbook-pro',
            'status' => ProductStatus::Active,
        ]);

        $productInactive = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'منتج متوقف',
            'name_en' => 'Discontinued',
            'slug' => 'discontinued',
            'status' => ProductStatus::Inactive,
        ]);

        // Candidate IDs in specific relevance ranking order:
        // [Inactive, Product2, 999999, Product1]
        $candidateIds = [
            $productInactive->id,
            $product2->id,
            999999,
            $product1->id,
        ];

        $rehydrated = $this->rehydrationService->rehydrate($candidateIds, activeOnly: true);

        // Should only return active products in the exact order: Product2, then Product1
        $this->assertCount(2, $rehydrated);
        $this->assertSame($product2->id, $rehydrated->get(0)->id);
        $this->assertSame($product1->id, $rehydrated->get(1)->id);

        // Verify eager-loaded relations exist without additional queries
        $this->assertTrue($rehydrated->get(0)->relationLoaded('brand'));
        $this->assertTrue($rehydrated->get(0)->relationLoaded('category'));
        $this->assertTrue($rehydrated->get(0)->relationLoaded('variants'));
        $this->assertTrue($rehydrated->get(0)->relationLoaded('media'));
    }

    public function test_rehydration_with_empty_input_returns_empty_collection(): void
    {
        $result = $this->rehydrationService->rehydrate([]);
        $this->assertTrue($result->isEmpty());
    }
}
