<?php

namespace Tests\Feature\Search;

use App\Contracts\Search\ProductSearchInterface;
use App\Contracts\Search\VisualProductSearchInterface;
use App\DTOs\Search\ProductSearchQuery;
use App\DTOs\Search\ProductSearchResult;
use App\DTOs\Search\VisualCandidate;
use App\DTOs\Search\VisualSearchResult;
use App\Enums\ProductStatus;
use App\Models\Brand;
use App\Models\Category;
use App\Models\MediaEmbedding;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class BenchmarkSearchCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_benchmark_command_runs_and_outputs_metrics(): void
    {
        $brand = Brand::create(['name_ar' => 'سامسونج', 'name_en' => 'Samsung', 'slug' => 'samsung', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'هواتف', 'name_en' => 'Phones', 'slug' => 'phones', 'is_active' => true]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'جالاكسي S24 ألترا',
            'name_en' => 'Galaxy S24 Ultra',
            'slug' => 'galaxy-s24-ultra',
            'status' => ProductStatus::Active,
        ]);

        ProductVariant::create([
            'product_id' => $product->id,
            'sku' => 'S24-ULTRA-256',
            'status' => 'active',
            'is_default' => true,
        ]);

        $media = Media::create([
            'model_type' => Product::class,
            'model_id' => $product->id,
            'collection_name' => 'product_images',
            'name' => 's24-ultra',
            'file_name' => 's24.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        MediaEmbedding::updateOrCreate(
            ['vector_id' => 'media_'.$media->id],
            [
                'media_id' => $media->id,
                'product_id' => $product->id,
                'status' => MediaEmbedding::STATUS_INDEXED,
                'embedding_vector' => array_fill(0, 512, 0.04),
                'vector_dimension' => 512,
                'indexed_at' => now(),
            ]
        );

        // Mock Search Interfaces
        $this->app->instance(ProductSearchInterface::class, new class($product) implements ProductSearchInterface
        {
            public function __construct(private Product $prod) {}

            public function isHealthy(): bool
            {
                return true;
            }

            public function search(ProductSearchQuery $query): ProductSearchResult
            {
                return new ProductSearchResult(
                    items: new Collection([$this->prod]),
                    candidateIds: [$this->prod->id],
                    total: 1,
                    page: 1,
                    perPage: 10,
                    lastPage: 1,
                    processingTimeMs: 1.25,
                );
            }
        });

        $this->app->instance(VisualProductSearchInterface::class, new class($product) implements VisualProductSearchInterface
        {
            public function __construct(private Product $prod) {}

            public function searchByImage(string $imagePathOrUrl, int $limit = 20, float $minSimilarity = 0.60): VisualSearchResult
            {
                return $this->searchByVector([], $limit, $minSimilarity);
            }

            public function searchByVector(array $vector, int $limit = 20, float $minSimilarity = 0.60): VisualSearchResult
            {
                return new VisualSearchResult(
                    items: new Collection([$this->prod]),
                    candidates: [new VisualCandidate(productId: $this->prod->id, variantId: null, mediaId: 1, similarity: 0.98, vectorId: 'vec_1')],
                    totalCandidates: 1,
                    processingTimeMs: 2.5,
                    modelVersion: 'mock-v1.0',
                );
            }
        });

        $this->artisan('search:benchmark', ['--samples' => 1])
            ->expectsOutputToContain('تقييم أداء محرك البحث')
            ->expectsOutputToContain('Mean Reciprocal Rank (MRR)')
            ->expectsOutputToContain('Top-1 Accuracy')
            ->assertSuccessful();
    }
}
