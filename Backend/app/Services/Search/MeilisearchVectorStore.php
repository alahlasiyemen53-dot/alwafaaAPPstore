<?php

namespace App\Services\Search;

use App\Contracts\Search\MeilisearchClientInterface;
use App\Contracts\Search\VisualVectorStoreInterface;
use App\DTOs\Search\VisualCandidate;

final class MeilisearchVectorStore implements VisualVectorStoreInterface
{
    private string $indexName;

    public function __construct(
        private readonly MeilisearchClientInterface $client,
        private readonly DatabaseCosineVectorStore $fallbackStore,
        ?string $indexPrefix = null,
    ) {
        $prefix = $indexPrefix ?? (string) config('search.meilisearch.index_prefix', 'storeapp_catalog_');
        $this->indexName = $prefix.'vectors';
    }

    public function upsertVector(string $vectorId, array $vector, array $metadata = []): bool
    {
        // First keep database record in sync
        $this->fallbackStore->upsertVector($vectorId, $vector, $metadata);

        // Also push to Meilisearch vector index
        $doc = [
            'id' => $vectorId,
            'media_id' => $metadata['media_id'] ?? null,
            'product_id' => $metadata['product_id'] ?? null,
            'product_variant_id' => $metadata['product_variant_id'] ?? null,
            'model_version' => $metadata['model_version'] ?? null,
            '_vectors' => [
                'default' => $vector,
            ],
        ];

        $res = $this->client->addDocuments($this->indexName, [$doc], 'id');

        return ! isset($res['error']);
    }

    public function deleteVector(string $vectorId): bool
    {
        $this->fallbackStore->deleteVector($vectorId);
        $this->client->deleteDocument($this->indexName, $vectorId);

        return true;
    }

    public function querySimilar(array $queryVector, int $limit = 50, float $minSimilarity = 0.60, ?string $modelVersion = null): array
    {
        // If Meilisearch is reachable and has vector index, we query it
        if ($this->client->isHealthy()) {
            $response = $this->client->search($this->indexName, '', [
                'vector' => $queryVector,
                'limit' => $limit,
            ]);

            $hits = $response['hits'] ?? [];
            if (! empty($hits)) {
                $candidates = [];
                foreach ($hits as $hit) {
                    $score = (float) ($hit['_semanticScore'] ?? $hit['_rankingScore'] ?? 1.0);
                    if ($score >= $minSimilarity && ($modelVersion === null || ($hit['model_version'] ?? null) === $modelVersion)) {
                        $candidates[] = new VisualCandidate(
                            productId: (int) $hit['product_id'],
                            variantId: isset($hit['product_variant_id']) ? (int) $hit['product_variant_id'] : null,
                            mediaId: (int) $hit['media_id'],
                            similarity: $score,
                            vectorId: (string) $hit['id'],
                        );
                    }
                }

                if ($candidates !== []) {
                    return $candidates;
                }
            }
        }

        // Fallback to database cosine vector store
        return $this->fallbackStore->querySimilar($queryVector, $limit, $minSimilarity, $modelVersion);
    }

    public function count(): int
    {
        return $this->fallbackStore->count();
    }
}
