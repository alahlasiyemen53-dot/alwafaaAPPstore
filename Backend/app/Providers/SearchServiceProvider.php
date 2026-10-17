<?php

namespace App\Providers;

use App\Contracts\Search\EmbeddingGeneratorInterface;
use App\Contracts\Search\MeilisearchClientInterface;
use App\Contracts\Search\ProductSearchInterface;
use App\Contracts\Search\VisualProductSearchInterface;
use App\Contracts\Search\VisualVectorStoreInterface;
use App\Jobs\Search\GenerateImageEmbeddingJob;
use App\Jobs\Search\RemoveImageEmbeddingJob;
use App\Jobs\Search\RemoveProductFromSearchJob;
use App\Jobs\Search\SyncProductToSearchJob;
use App\Models\Inventory;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\VariantPrice;
use App\Services\Search\ClipApiEmbeddingGenerator;
use App\Services\Search\DatabaseCosineVectorStore;
use App\Services\Search\DeterministicMockEmbeddingGenerator;
use App\Services\Search\MeilisearchHttpClient;
use App\Services\Search\MeilisearchIndexManager;
use App\Services\Search\MeilisearchProductSearch;
use App\Services\Search\MeilisearchVectorStore;
use App\Services\Search\ProductIndexingService;
use App\Services\Search\SearchReconciliationService;
use App\Services\Search\SearchResultRehydrationService;
use App\Services\Search\VisualIndexingService;
use App\Services\Search\VisualSearchService;
use Illuminate\Support\ServiceProvider;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SearchServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Meilisearch Client
        $this->app->singleton(MeilisearchClientInterface::class, function () {
            return new MeilisearchHttpClient(
                host: (string) config('search.meilisearch.host', 'http://127.0.0.1:7700'),
                key: config('search.meilisearch.key'),
                timeout: (float) config('search.meilisearch.timeout', 2.0),
            );
        });

        // Index Manager
        $this->app->singleton(MeilisearchIndexManager::class, function ($app) {
            return new MeilisearchIndexManager(
                client: $app->make(MeilisearchClientInterface::class),
                indexPrefix: (string) config('search.meilisearch.index_prefix', 'storeapp_catalog_'),
            );
        });

        // Search Result Rehydration Service
        $this->app->singleton(SearchResultRehydrationService::class);

        // Product Search Interface
        $this->app->singleton(ProductSearchInterface::class, MeilisearchProductSearch::class);

        // Product Indexing Service
        $this->app->singleton(ProductIndexingService::class);

        // Embedding Generator
        $this->app->singleton(EmbeddingGeneratorInterface::class, function () {
            $driver = config('search.embedding.driver', 'mock');
            if ($driver === 'clip_api') {
                return new ClipApiEmbeddingGenerator(
                    apiUrl: config('search.embedding.clip_api_url'),
                    apiKey: config('search.embedding.clip_api_key'),
                    timeout: (float) config('search.embedding.timeout', 5.0),
                    dimension: (int) config('search.vector.dimension', 512),
                    modelVersion: (string) config('search.vector.model_version', 'clip-vit-base-patch32'),
                );
            }

            return new DeterministicMockEmbeddingGenerator(
                dimension: (int) config('search.vector.dimension', 512),
                modelVersion: 'mock-v1.0',
            );
        });

        // Vector Store
        $this->app->singleton(DatabaseCosineVectorStore::class);
        $this->app->singleton(VisualVectorStoreInterface::class, function ($app) {
            $driver = config('search.vector.driver', 'database_cosine');
            if ($driver === 'meilisearch') {
                return new MeilisearchVectorStore(
                    client: $app->make(MeilisearchClientInterface::class),
                    fallbackStore: $app->make(DatabaseCosineVectorStore::class),
                    indexPrefix: (string) config('search.meilisearch.index_prefix', 'storeapp_catalog_'),
                );
            }

            return $app->make(DatabaseCosineVectorStore::class);
        });

        // Visual Indexing & Search
        $this->app->singleton(VisualIndexingService::class);
        $this->app->singleton(VisualProductSearchInterface::class, VisualSearchService::class);

        // Search Reconciliation Service
        $this->app->singleton(SearchReconciliationService::class);
    }

    public function boot(): void
    {
        // Auto-sync product events
        Product::saved(function (Product $product) {
            dispatch(new SyncProductToSearchJob($product->id))->onQueue('search')->afterCommit();
        });

        Product::deleted(function (Product $product) {
            dispatch(new RemoveProductFromSearchJob($product->id))->onQueue('search')->afterCommit();
        });

        // Auto-sync variant events
        ProductVariant::saved(function (ProductVariant $variant) {
            dispatch(new SyncProductToSearchJob($variant->product_id))->onQueue('search')->afterCommit();
        });

        ProductVariant::deleted(function (ProductVariant $variant) {
            dispatch(new SyncProductToSearchJob($variant->product_id))->onQueue('search')->afterCommit();
        });

        // Auto-sync price updates
        VariantPrice::saved(function (VariantPrice $price) {
            $variant = $price->relationLoaded('productVariant') ? $price->productVariant : $price->productVariant()->first();
            if ($variant) {
                dispatch(new SyncProductToSearchJob($variant->product_id))->onQueue('search')->afterCommit();
            }
        });

        // Auto-sync inventory updates
        Inventory::saved(function (Inventory $inventory) {
            $variant = $inventory->relationLoaded('productVariant') ? $inventory->productVariant : $inventory->productVariant()->first();
            if ($variant) {
                dispatch(new SyncProductToSearchJob($variant->product_id))->onQueue('search')->afterCommit();
            }
        });

        // Auto-sync media events
        Media::created(function (Media $media) {
            if (in_array($media->model_type, [Product::class, ProductVariant::class], true)) {
                dispatch(new GenerateImageEmbeddingJob($media->id))->onQueue('search')->afterCommit();
            }
        });

        Media::deleted(function (Media $media) {
            if (in_array($media->model_type, [Product::class, ProductVariant::class], true)) {
                dispatch(new RemoveImageEmbeddingJob($media->id))->onQueue('search')->afterCommit();
            }
        });
    }
}
