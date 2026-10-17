<?php

namespace App\Services\Search;

use App\Contracts\Search\EmbeddingGeneratorInterface;
use App\Contracts\Search\VisualVectorStoreInterface;
use App\Models\MediaEmbedding;
use App\Models\Product;
use App\Models\ProductVariant;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class VisualIndexingService
{
    public function __construct(
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly VisualVectorStoreInterface $vectorStore,
    ) {}

    public function indexMedia(Media $media): bool
    {
        // Determine associated product and variant
        $productId = null;
        $variantId = null;

        if ($media->model_type === Product::class) {
            $productId = (int) $media->model_id;
        } elseif ($media->model_type === ProductVariant::class) {
            $variantId = (int) $media->model_id;
            /** @var ProductVariant|null $variant */
            $variant = ProductVariant::find($variantId);
            if ($variant) {
                $productId = (int) $variant->product_id;
            }
        }

        if (! $productId) {
            Log::warning("Cannot index media #{$media->id}: model_type {$media->model_type} is not Product or ProductVariant");

            return false;
        }

        $vectorId = "media_{$media->id}";

        // Mark as processing
        MediaEmbedding::updateOrCreate(
            ['vector_id' => $vectorId],
            [
                'media_id' => $media->id,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'status' => MediaEmbedding::STATUS_PROCESSING,
                'provider' => (string) config('search.embedding.driver', 'deterministic_mock'),
                'model_version' => $this->embeddingGenerator->getModelVersion(),
                'embedding_version' => 'v1',
                'vector_dimension' => $this->embeddingGenerator->getDimension(),
            ]
        );

        try {
            // Obtain image path or URL
            $path = $media->getPath();
            if (file_exists($path)) {
                $vector = $this->embeddingGenerator->generateFromImagePath($path);
            } else {
                $url = $media->getUrl();
                $vector = $this->embeddingGenerator->generateFromUrl($url);
            }

            // Upsert vector into store
            $this->vectorStore->upsertVector($vectorId, $vector, [
                'media_id' => $media->id,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'provider' => (string) config('search.embedding.driver', 'deterministic_mock'),
                'model_version' => $this->embeddingGenerator->getModelVersion(),
                'embedding_version' => 'v1',
            ]);

            return true;
        } catch (Exception $e) {
            Log::error("Failed to index media embedding for media #{$media->id}: ".$e->getMessage());

            MediaEmbedding::updateOrCreate(
                ['vector_id' => $vectorId],
                [
                    'status' => MediaEmbedding::STATUS_FAILED,
                    'last_error' => $e->getMessage(),
                    'retry_count' => DB::raw('retry_count + 1'),
                ]
            );

            return false;
        }
    }

    public function unindexMedia(int|Media $media): bool
    {
        $id = $media instanceof Media ? $media->id : $media;
        $vectorId = "media_{$id}";

        $this->vectorStore->deleteVector($vectorId);

        return true;
    }

    /**
     * Index all images associated with Products and ProductVariants.
     *
     * @param  (callable(int $processed, int $total): void)|null  $progressCallback
     */
    public function indexAll(int $chunkSize = 50, ?callable $progressCallback = null): int
    {
        $query = Media::query()
            ->whereIn('model_type', [Product::class, ProductVariant::class]);

        $total = $query->count();
        $processed = 0;

        $query->chunk($chunkSize, function (Collection $mediaCollection) use (&$processed, $total, $progressCallback) {
            foreach ($mediaCollection as $media) {
                /** @var Media $media */
                $this->indexMedia($media);
                $processed++;
                if ($progressCallback !== null) {
                    $progressCallback($processed, $total);
                }
            }
        });

        return $processed;
    }
}
