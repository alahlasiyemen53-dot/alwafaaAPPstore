<?php

namespace Tests\Feature\Search;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Services\Search\DatabaseCosineVectorStore;
use App\Services\Search\DeterministicMockEmbeddingGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class DatabaseCosineVectorStoreTest extends TestCase
{
    use RefreshDatabase;

    private DatabaseCosineVectorStore $store;

    private DeterministicMockEmbeddingGenerator $generator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->store = new DatabaseCosineVectorStore;
        $this->generator = new DeterministicMockEmbeddingGenerator(dimension: 128);
    }

    public function test_upsert_query_similar_and_delete_vector(): void
    {
        $brand = Brand::create(['name_ar' => 'سامسونج', 'name_en' => 'Samsung', 'slug' => 'samsung', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'هواتف', 'name_en' => 'Phones', 'slug' => 'phones', 'is_active' => true]);

        $product1 = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'هاتف جالاكسي S24',
            'name_en' => 'Galaxy S24',
            'slug' => 'galaxy-s24',
            'status' => 'active',
        ]);

        $product2 = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'سماعات سامسونج بودز',
            'name_en' => 'Galaxy Buds',
            'slug' => 'galaxy-buds',
            'status' => 'active',
        ]);

        $media1 = Media::create([
            'model_type' => Product::class,
            'model_id' => $product1->id,
            'collection_name' => 'product_images',
            'name' => 's24-main',
            'file_name' => 's24.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 2048,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $media2 = Media::create([
            'model_type' => Product::class,
            'model_id' => $product2->id,
            'collection_name' => 'product_images',
            'name' => 'buds-main',
            'file_name' => 'buds.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        $vec1 = $this->generator->generateVectorFromString('galaxy_s24_front_photo');
        $vec2 = $this->generator->generateVectorFromString('galaxy_buds_case_photo');

        // Upsert both vectors
        $this->store->upsertVector('media_'.$media1->id, $vec1, [
            'media_id' => $media1->id,
            'product_id' => $product1->id,
            'model_version' => $this->generator->getModelVersion(),
        ]);

        $this->store->upsertVector('media_'.$media2->id, $vec2, [
            'media_id' => $media2->id,
            'product_id' => $product2->id,
            'model_version' => $this->generator->getModelVersion(),
        ]);

        $this->assertSame(2, $this->store->count());

        // Query with exact vec1 (similarity should be 1.0 or ~0.999)
        $candidates = $this->store->querySimilar($vec1, limit: 10, minSimilarity: 0.50);

        $this->assertNotEmpty($candidates);
        $top = $candidates[0];
        $this->assertSame($product1->id, $top->productId);
        $this->assertSame($media1->id, $top->mediaId);
        $this->assertEqualsWithDelta(1.0, $top->similarity, 0.01);

        $this->store->upsertVector('media_'.$media2->id, $vec2, [
            'media_id' => $media2->id,
            'product_id' => $product2->id,
            'model_version' => 'legacy-model',
        ]);

        $this->assertSame([], $this->store->querySimilar(
            $vec2,
            limit: 10,
            minSimilarity: 0.50,
            modelVersion: $this->generator->getModelVersion(),
        ));

        // Delete vector
        $this->store->deleteVector('media_'.$media1->id);
        $this->assertSame(1, $this->store->count());
        $this->assertDatabaseMissing('media_embeddings', ['vector_id' => 'media_'.$media1->id]);
    }
}
