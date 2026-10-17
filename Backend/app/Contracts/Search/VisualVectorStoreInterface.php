<?php

namespace App\Contracts\Search;

use App\DTOs\Search\VisualCandidate;

interface VisualVectorStoreInterface
{
    /**
     * Upsert a vector embedding with associated metadata.
     *
     * @param  array<int, float>  $vector
     * @param  array<string, mixed>  $metadata
     */
    public function upsertVector(string $vectorId, array $vector, array $metadata = []): bool;

    /**
     * Delete a vector from the store.
     */
    public function deleteVector(string $vectorId): bool;

    /**
     * Query similar vectors by cosine similarity.
     *
     * @param  array<int, float>  $queryVector
     * @return array<int, VisualCandidate>
     */
    public function querySimilar(array $queryVector, int $limit = 50, float $minSimilarity = 0.60, ?string $modelVersion = null): array;

    /**
     * Count indexed vectors.
     */
    public function count(): int;
}
