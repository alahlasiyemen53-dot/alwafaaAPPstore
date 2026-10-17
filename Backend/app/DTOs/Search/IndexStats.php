<?php

namespace App\DTOs\Search;

final class IndexStats
{
    /**
     * @param  array<string, int>  $fieldDistribution
     */
    public function __construct(
        public readonly string $indexName,
        public readonly int $numberOfDocuments,
        public readonly bool $isIndexing,
        public readonly array $fieldDistribution,
        public readonly bool $isHealthy,
        public readonly int $totalEmbeddingsCount,
        public readonly int $indexedEmbeddingsCount,
        public readonly int $pendingEmbeddingsCount,
        public readonly int $failedEmbeddingsCount,
        public readonly ?string $lastIndexedAt,
        public readonly string $textDriver,
        public readonly string $vectorDriver,
    ) {}
}
