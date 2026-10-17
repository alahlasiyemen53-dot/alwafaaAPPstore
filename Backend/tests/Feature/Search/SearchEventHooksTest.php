<?php

namespace Tests\Feature\Search;

use App\Enums\ProductStatus;
use App\Jobs\Search\GenerateImageEmbeddingJob;
use App\Jobs\Search\RemoveImageEmbeddingJob;
use App\Jobs\Search\RemoveProductFromSearchJob;
use App\Jobs\Search\SyncProductToSearchJob;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\TestCase;

final class SearchEventHooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_lifecycle_dispatches_sync_and_remove_jobs(): void
    {
        Queue::fake([
            SyncProductToSearchJob::class,
            RemoveProductFromSearchJob::class,
        ]);

        $brand = Brand::create(['name_ar' => 'هواوي', 'name_en' => 'Huawei', 'slug' => 'huawei', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'أجهزة', 'name_en' => 'Devices', 'slug' => 'devices', 'is_active' => true]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'هواوي بي 60',
            'name_en' => 'Huawei P60',
            'slug' => 'huawei-p60',
            'status' => ProductStatus::Active,
        ]);

        Queue::assertPushedOn('search', SyncProductToSearchJob::class, function ($job) use ($product) {
            return $job->productId === $product->id;
        });

        $productId = $product->id;
        $product->delete();

        Queue::assertPushedOn('search', RemoveProductFromSearchJob::class, function ($job) use ($productId) {
            return $job->productId === $productId;
        });
    }

    public function test_media_lifecycle_dispatches_embedding_jobs(): void
    {
        Queue::fake([
            GenerateImageEmbeddingJob::class,
            RemoveImageEmbeddingJob::class,
        ]);

        $brand = Brand::create(['name_ar' => 'هواوي', 'name_en' => 'Huawei', 'slug' => 'huawei', 'is_active' => true]);
        $category = Category::create(['name_ar' => 'أجهزة', 'name_en' => 'Devices', 'slug' => 'devices', 'is_active' => true]);

        $product = Product::create([
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'name_ar' => 'هواوي ميت 60',
            'name_en' => 'Huawei Mate 60',
            'slug' => 'huawei-mate-60',
            'status' => ProductStatus::Active,
        ]);

        $media = Media::create([
            'model_type' => Product::class,
            'model_id' => $product->id,
            'collection_name' => 'product_images',
            'name' => 'mate60-img',
            'file_name' => 'mate60.jpg',
            'mime_type' => 'image/jpeg',
            'disk' => 'public',
            'size' => 1024,
            'manipulations' => [],
            'custom_properties' => [],
            'generated_conversions' => [],
            'responsive_images' => [],
        ]);

        Queue::assertPushedOn('search', GenerateImageEmbeddingJob::class, function ($job) use ($media) {
            return $job->mediaId === $media->id;
        });

        $mediaId = $media->id;
        $media->delete();

        Queue::assertPushedOn('search', RemoveImageEmbeddingJob::class, function ($job) use ($mediaId) {
            return $job->mediaId === $mediaId;
        });
    }
}
