<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Enums\ProductStatus;
use App\Enums\ProductVariantStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class CustomerProductSearchApiTest extends TestCase
{
    use RefreshDatabase;

    private Currency $currency;

    private Warehouse $warehouse;

    private Brand $brand;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        Http::fake([
            '*/health' => Http::response(['status' => 'unavailable'], 503),
        ]);

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
            'is_active' => true,
            'sort_order' => 1,
        ]);
    }

    public function test_search_requires_q_parameter_and_validates_length(): void
    {
        // Missing q
        $response = $this->getJson('/api/v1/products/search');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        // Empty q
        $response = $this->getJson('/api/v1/products/search?q=');
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);

        // Exceeding max length
        $longQuery = str_repeat('a', 101);
        $response = $this->getJson('/api/v1/products/search?q='.$longQuery);
        $response->assertStatus(422)
            ->assertJsonValidationErrors(['q']);
    }

    public function test_search_returns_matching_products_by_arabic_and_english_name(): void
    {
        $product1 = $this->createTestProduct('عطر خمرة عنبر فاخر', 'Khamrah Amber Luxury', 'khamrah-amber', '15000.00');
        $product2 = $this->createTestProduct('عطر يارا الوردي', 'Yara Pink Perfume', 'yara-pink', '12000.00');

        // Search Arabic keyword
        $response = $this->getJson('/api/v1/products/search?q=عنبر');
        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $product1->id)
            ->assertJsonPath('data.items.0.name_ar', 'عطر خمرة عنبر فاخر')
            ->assertJsonPath('data.items.0.price_summary.min_price', '15000.00')
            ->assertJsonPath('data.meta.query', 'عنبر');

        // Search English keyword
        $responseEn = $this->getJson('/api/v1/products/search?q=Yara');
        $responseEn->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $product2->id);
    }

    public function test_search_tolerates_arabic_alef_hamza_variants(): void
    {
        $product = $this->createTestProduct('كريم أساس مات', 'Matte Foundation', 'matte-foundation', '9000.00');

        foreach (['أساس', 'اساس'] as $term) {
            $response = $this->getJson('/api/v1/products/search?q='.urlencode($term));
            $response->assertOk()
                ->assertJsonCount(1, 'data.items')
                ->assertJsonPath('data.items.0.id', $product->id);
        }
    }

    public function test_search_respects_category_and_brand_filters(): void
    {
        $otherBrand = Brand::create([
            'name_ar' => 'رصاصي',
            'name_en' => 'Rasasi',
            'slug' => 'rasasi',
            'is_active' => true,
        ]);

        $product1 = $this->createTestProduct('عطر عنبر لطافة', 'Amber Lattafa', 'amber-lattafa', '15000.00', $this->brand->id);
        $product2 = $this->createTestProduct('عطر عنبر رصاصي', 'Amber Rasasi', 'amber-rasasi', '18000.00', $otherBrand->id);

        // Filter by brand
        $response = $this->getJson('/api/v1/products/search?q=عنبر&brand_id='.$otherBrand->id);
        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $product2->id);
    }

    public function test_search_shields_inactive_products_and_categories(): void
    {
        $activeProduct = $this->createTestProduct('مسك أبيض فاخر', 'White Musk Luxury', 'white-musk', '10000.00');

        // Inactive product
        $inactiveProduct = $this->createTestProduct('مسك أسود قديم', 'Black Musk Old', 'black-musk', '9000.00');
        $inactiveProduct->update(['status' => ProductStatus::Draft]);

        $response = $this->getJson('/api/v1/products/search?q=مسك');
        $response->assertOk()
            ->assertJsonCount(1, 'data.items')
            ->assertJsonPath('data.items.0.id', $activeProduct->id);
    }

    public function test_search_enforces_bounded_pagination(): void
    {
        $this->createTestProduct('عطر فاخر 1', 'Perfume 1', 'perf-1', '10000.00');
        $this->createTestProduct('عطر فاخر 2', 'Perfume 2', 'perf-2', '11000.00');

        $response = $this->getJson('/api/v1/products/search?q=فاخر&per_page=100');
        $response->assertOk()
            ->assertJsonPath('data.pagination.per_page', 50);
    }

    private function createTestProduct(
        string $nameAr,
        string $nameEn,
        string $slug,
        string $price,
        ?int $brandId = null
    ): Product {
        $product = Product::create([
            'brand_id' => $brandId ?? $this->brand->id,
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
            'compare_at_price' => '20000.00',
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
