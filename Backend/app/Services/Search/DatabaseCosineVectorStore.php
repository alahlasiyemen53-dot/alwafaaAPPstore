<?php

namespace App\Services\Search;

use App\Contracts\Search\VisualVectorStoreInterface;
use App\DTOs\Search\VisualCandidate;
use App\Models\MediaEmbedding;

final class DatabaseCosineVectorStore implements VisualVectorStoreInterface
{
    public function upsertVector(string $vectorId, array $vector, array $metadata = []): bool
    {
        $data = [
            'vector_id' => $vectorId,
            'media_id' => (int) ($metadata['media_id'] ?? 0),
            'product_id' => (int) ($metadata['product_id'] ?? 0),
            'product_variant_id' => isset($metadata['product_variant_id']) ? (int) $metadata['product_variant_id'] : null,
            'status' => MediaEmbedding::STATUS_INDEXED,
            'provider' => (string) ($metadata['provider'] ?? 'deterministic_mock'),
            'model_version' => (string) ($metadata['model_version'] ?? 'v1.0.0'),
            'embedding_version' => (string) ($metadata['embedding_version'] ?? 'v1'),
            'vector_dimension' => count($vector),
            'embedding_vector' => $vector,
            'indexed_at' => now(),
            'last_error' => null,
            'retry_count' => 0,
        ];

        MediaEmbedding::updateOrCreate(
            ['vector_id' => $vectorId],
            $data
        );

        return true;
    }

    public function deleteVector(string $vectorId): bool
    {
        return (bool) MediaEmbedding::where('vector_id', $vectorId)->delete();
    }

    /**
     * @param  array<int, float>  $queryVector
     * @return array<int, VisualCandidate>
     */
    public function querySimilar(array $queryVector, int $limit = 50, float $minSimilarity = 0.60, ?string $modelVersion = null): array
    {
        if (empty($queryVector)) {
            return [];
        }

        $records = MediaEmbedding::query()
            ->where('status', MediaEmbedding::STATUS_INDEXED)
            ->whereNotNull('embedding_vector')
            ->when($modelVersion !== null, fn ($query) => $query->where('model_version', $modelVersion))
            ->select(['id', 'vector_id', 'product_id', 'product_variant_id', 'media_id', 'embedding_vector'])
            ->get();

        $candidates = [];

        foreach ($records as $record) {
            /** @var array<int, float>|null $storedVector */
            $storedVector = $record->embedding_vector;
            if (empty($storedVector) || count($storedVector) !== count($queryVector)) {
                continue;
            }

            // Dot product (since vectors are L2 normalized, dot product == cosine similarity)
            $dotProduct = 0.0;
            $len = count($queryVector);
            for ($i = 0; $i < $len; $i++) {
                $dotProduct += $queryVector[$i] * $storedVector[$i];
            }

            if ($dotProduct >= $minSimilarity) {
                $candidates[] = [
                    'candidate' => new VisualCandidate(
                        productId: (int) $record->product_id,
                        variantId: $record->product_variant_id ? (int) $record->product_variant_id : null,
                        mediaId: (int) $record->media_id,
                        similarity: (float) round($dotProduct, 4),
                        vectorId: (string) $record->vector_id,
                    ),
                    'similarity' => $dotProduct,
                ];
            }
        }

        // Sort descending by similarity
        usort($candidates, fn ($a, $b) => $b['similarity'] <=> $a['similarity']);

        // Return up to limit
        $top = array_slice($candidates, 0, $limit);

        return array_map(fn ($item) => $item['candidate'], $top);
    }

    public function count(): int
    {
        return MediaEmbedding::where('status', MediaEmbedding::STATUS_INDEXED)->count();
    }
}
