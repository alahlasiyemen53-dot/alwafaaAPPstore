<?php

namespace App\Services\Search;

use App\Contracts\Search\MeilisearchClientInterface;
use App\DTOs\Search\IndexStats;
use App\Models\MediaEmbedding;
use App\Models\Product;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class SearchReconciliationService
{
    public function __construct(
        private readonly MeilisearchClientInterface $client,
        private readonly MeilisearchIndexManager $indexManager,
        private readonly ProductIndexingService $productIndexingService,
        private readonly VisualIndexingService $visualIndexingService,
    ) {}

    /**
     * Reconcile text search index with database.
     *
     * @return array{indexed: int, errors: int}
     */
    public function reconcileTextCatalog(): array
    {
        $indexed = 0;
        $errors = 0;

        try {
            $indexed = $this->productIndexingService->indexAll();
        } catch (Exception $e) {
            Log::error('Text catalog reconciliation failed: '.$e->getMessage());
            $errors++;
        }

        return [
            'indexed' => $indexed,
            'errors' => $errors,
        ];
    }

    /**
     * Reconcile visual media embeddings with database.
     *
     * @return array{indexed: int, cleaned: int, errors: int}
     */
    public function reconcileVisualEmbeddings(): array
    {
        $indexed = 0;
        $cleaned = 0;
        $errors = 0;

        // 1. Clean up orphaned embeddings
        $orphans = MediaEmbedding::query()
            ->whereNotExists(function ($query) {
                $query->selectRaw(1)
                    ->from('media')
                    ->whereColumn('media.id', 'media_embeddings.media_id');
            })
            ->get();

        foreach ($orphans as $orphan) {
            $this->visualIndexingService->unindexMedia($orphan->media_id);
            $cleaned++;
        }

        // 2. Index missing or failed media
        $missingMedia = Media::query()
            ->whereIn('model_type', [Product::class, ProductVariant::class])
            ->where(function ($query) {
                $query->whereNotExists(function ($sub) {
                    $sub->selectRaw(1)
                        ->from('media_embeddings')
                        ->whereColumn('media_embeddings.media_id', 'media.id')
                        ->where('status', MediaEmbedding::STATUS_INDEXED);
                });
            })
            ->get();

        foreach ($missingMedia as $media) {
            try {
                if ($this->visualIndexingService->indexMedia($media)) {
                    $indexed++;
                } else {
                    $errors++;
                }
            } catch (Exception $e) {
                Log::error("Reconcile error on media #{$media->id}: ".$e->getMessage());
                $errors++;
            }
        }

        return [
            'indexed' => $indexed,
            'cleaned' => $cleaned,
            'errors' => $errors,
        ];
    }

    public function getIndexStats(): IndexStats
    {
        $indexName = $this->indexManager->getCatalogIndexName();
        $isHealthy = $this->client->isHealthy();
        $meiliStats = $isHealthy ? $this->client->getStats($indexName) : [];

        $totalEmbeddings = MediaEmbedding::count();
        $indexedEmbeddings = MediaEmbedding::where('status', MediaEmbedding::STATUS_INDEXED)->count();
        $pendingEmbeddings = MediaEmbedding::where('status', MediaEmbedding::STATUS_PENDING)->count();
        $failedEmbeddings = MediaEmbedding::where('status', MediaEmbedding::STATUS_FAILED)->count();

        $latestIndexed = MediaEmbedding::whereNotNull('indexed_at')->latest('indexed_at')->first();

        return new IndexStats(
            indexName: $indexName,
            numberOfDocuments: (int) ($meiliStats['numberOfDocuments'] ?? 0),
            isIndexing: (bool) ($meiliStats['isIndexing'] ?? false),
            fieldDistribution: (array) ($meiliStats['fieldDistribution'] ?? []),
            isHealthy: $isHealthy,
            totalEmbeddingsCount: $totalEmbeddings,
            indexedEmbeddingsCount: $indexedEmbeddings,
            pendingEmbeddingsCount: $pendingEmbeddings,
            failedEmbeddingsCount: $failedEmbeddings,
            lastIndexedAt: $latestIndexed?->indexed_at?->toIso8601String(),
            textDriver: (string) config('search.default_text_driver', 'meilisearch'),
            vectorDriver: (string) config('search.vector.driver', 'database_cosine'),
        );
    }
}
