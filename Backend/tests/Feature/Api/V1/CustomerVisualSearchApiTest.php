<?php

declare(strict_types=1);

namespace Tests\Feature\Api\V1;

use App\Contracts\Search\EmbeddingGeneratorInterface;
use App\Contracts\Search\VisualVectorStoreInterface;
use App\DTOs\Search\VisualCandidate;
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
use Illuminate\Http\UploadedFile;
use Mockery;
use RuntimeException;
use Tests\TestCase;

final class CustomerVisualSearchApiTest extends TestCase
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
            'name_ar' => 'شانيل',
            'name_en' => 'Chanel',
            'slug' => 'chanel',
            'is_active' => true,
        ]);

        $this->category = Category::create([
            'name_ar' => 'عطور',
            'name_en' => 'Perfumes',
            'slug' => 'perfumes',
            'is_active' => true,
        ]);
    }

    public function test_successfully_discovers_products_by_uploaded_image(): void
    {
        $product = $this->createTestProduct('عطر بلو دي شانيل', 'Bleu de Chanel', 'bleu-de-chanel', '35000.00');

        $vectorStoreMock = Mockery::mock(VisualVectorStoreInterface::class);
        $vectorStoreMock->shouldReceive('querySimilar')
            ->once()
            ->andReturn([
                new VisualCandidate(
                    productId: $product->id,
                    variantId: null,
                    mediaId: 101,
                    similarity: 0.94,
                    vectorId: 'vec_101'
                ),
            ]);

        $this->app->instance(VisualVectorStoreInterface::class, $vectorStoreMock);

        $image = UploadedFile::fake()->image('perfume_sample.jpg', 300, 300);

        $response = $this->postJson('/api/v1/search/visual', [
            'image' => $image,
            'limit' => 10,
            'min_similarity' => 0.70,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_matches', 1)
            ->assertJsonPath('data.items.0.id', $product->id)
            ->assertJsonPath('data.items.0.name_ar', 'عطر بلو دي شانيل')
            ->assertJsonPath('data.items.0.price_summary.min_price', '35000.00')
            ->assertJsonPath('data.items.0.price_summary.currency', 'YER');
    }

    public function test_validates_image_mime_types_and_dimensions(): void
    {
        // 1. Missing image
        $response1 = $this->postJson('/api/v1/search/visual', []);
        $response1->assertStatus(422)
            ->assertJsonPath('code', 'VISUAL_SEARCH_INVALID_IMAGE')
            ->assertJsonValidationErrors(['image']);

        // 2. Invalid MIME type (text file disguised as image)
        $textDoc = UploadedFile::fake()->create('document.pdf', 500, 'application/pdf');
        $response2 = $this->postJson('/api/v1/search/visual', [
            'image' => $textDoc,
        ]);
        $response2->assertStatus(422)
            ->assertJsonPath('code', 'VISUAL_SEARCH_INVALID_IMAGE');

        // 3. Sub-minimum dimensions (< 50px)
        $tinyImage = UploadedFile::fake()->image('tiny.jpg', 20, 20);
        $response3 = $this->postJson('/api/v1/search/visual', [
            'image' => $tinyImage,
        ]);
        $response3->assertStatus(422)
            ->assertJsonPath('code', 'VISUAL_SEARCH_INVALID_IMAGE');
    }

    public function test_protects_and_shields_inactive_or_deleted_products(): void
    {
        $inactiveProduct = Product::create([
            'brand_id' => $this->brand->id,
            'category_id' => $this->category->id,
            'name_ar' => 'عطر ملغي',
            'name_en' => 'Inactive Perfume',
            'slug' => 'inactive-perfume',
            'status' => ProductStatus::Draft,
        ]);

        $vectorStoreMock = Mockery::mock(VisualVectorStoreInterface::class);
        $vectorStoreMock->shouldReceive('querySimilar')
            ->once()
            ->andReturn([
                new VisualCandidate(
                    productId: $inactiveProduct->id,
                    variantId: null,
                    mediaId: 999,
                    similarity: 0.99,
                    vectorId: 'vec_999'
                ),
            ]);

        $this->app->instance(VisualVectorStoreInterface::class, $vectorStoreMock);

        $image = UploadedFile::fake()->image('query.jpg', 200, 200);

        $response = $this->postJson('/api/v1/search/visual', [
            'image' => $image,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.total_matches', 0)
            ->assertJsonPath('data.items', []);
    }

    public function test_handles_embedding_or_vector_store_failures_gracefully(): void
    {
        $embeddingMock = Mockery::mock(EmbeddingGeneratorInterface::class);
        $embeddingMock->shouldReceive('generateFromImagePath')
            ->once()
            ->andThrow(new RuntimeException('CLIP API service connection refused'));

        $this->app->instance(EmbeddingGeneratorInterface::class, $embeddingMock);

        $image = UploadedFile::fake()->image('perfume.jpg', 200, 200);

        $response = $this->postJson('/api/v1/search/visual', [
            'image' => $image,
        ]);

        $response->assertStatus(503)
            ->assertJsonPath('success', false)
            ->assertJsonPath('code', 'VISUAL_SEARCH_UNAVAILABLE');
    }

    public function test_enforces_bounded_result_limits(): void
    {
        $image = UploadedFile::fake()->image('perfume.jpg', 200, 200);

        // Limit > 50 must fail validation
        $response = $this->postJson('/api/v1/search/visual', [
            'image' => $image,
            'limit' => 100,
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('code', 'VISUAL_SEARCH_INVALID_IMAGE')
            ->assertJsonValidationErrors(['limit']);
    }

    public function test_cleans_up_temporary_uploaded_file_after_search(): void
    {
        $capturedPath = null;
        $embeddingMock = Mockery::mock(EmbeddingGeneratorInterface::class);
        $embeddingMock->shouldReceive('generateFromImagePath')
            ->once()
            ->andReturnUsing(function ($path) use (&$capturedPath) {
                $capturedPath = $path;
                $this->assertFileExists($path);

                return array_fill(0, 512, 0.01);
            });
        $embeddingMock->shouldReceive('getModelVersion')->andReturn('mock-v1.0');

        $this->app->instance(EmbeddingGeneratorInterface::class, $embeddingMock);

        $vectorStoreMock = Mockery::mock(VisualVectorStoreInterface::class);
        $vectorStoreMock->shouldReceive('querySimilar')->andReturn([]);
        $this->app->instance(VisualVectorStoreInterface::class, $vectorStoreMock);

        $image = UploadedFile::fake()->image('temporary_test.jpg', 150, 150);

        $response = $this->postJson('/api/v1/search/visual', [
            'image' => $image,
        ]);

        $response->assertOk();
        $this->assertNotNull($capturedPath);
        $this->assertFileDoesNotExist($capturedPath);
    }

    public function test_products_search_visual_alias_route_works(): void
    {
        $image = UploadedFile::fake()->image('alias_test.jpg', 150, 150);

        $response = $this->postJson('/api/v1/products/search/visual', [
            'image' => $image,
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true);
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
            'compare_at_price' => '40000.00',
            'is_active' => true,
        ]);

        Inventory::create([
            'warehouse_id' => $this->warehouse->id,
            'product_variant_id' => $variant->id,
            'quantity' => 15,
            'reserved_quantity' => 0,
        ]);

        return $product;
    }
}
