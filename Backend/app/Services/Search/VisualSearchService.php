<?php

namespace App\Services\Search;

use App\Contracts\Search\EmbeddingGeneratorInterface;
use App\Contracts\Search\VisualProductSearchInterface;
use App\Contracts\Search\VisualVectorStoreInterface;
use App\DTOs\Search\VisualCandidate;
use App\DTOs\Search\VisualSearchResult;
use RuntimeException;

final class VisualSearchService implements VisualProductSearchInterface
{
    public function __construct(
        private readonly EmbeddingGeneratorInterface $embeddingGenerator,
        private readonly VisualVectorStoreInterface $vectorStore,
        private readonly SearchResultRehydrationService $rehydrationService,
    ) {}

    public function searchByImage(string $imagePathOrUrl, int $limit = 20, float $minSimilarity = 0.60): VisualSearchResult
    {
        if (app()->environment('production') && config('search.embedding.driver') === 'mock') {
            throw new RuntimeException('A production embedding provider is not configured.');
        }

        if (filter_var($imagePathOrUrl, FILTER_VALIDATE_URL)) {
            $vector = $this->embeddingGenerator->generateFromUrl($imagePathOrUrl);
        } else {
            $vector = $this->embeddingGenerator->generateFromImagePath($imagePathOrUrl);
        }

        return $this->searchByVector($vector, $limit, $minSimilarity);
    }

    public function searchByVector(array $vector, int $limit = 20, float $minSimilarity = 0.60): VisualSearchResult
    {
        $startTime = microtime(true);

        // Retrieve candidates (fetch up to 3x limit to allow deduplication across multiple media per product)
        $candidateLimit = min(
            (int) config('search.limits.max_visual_candidates', 50),
            max($limit, $limit * 3),
        );
        $rawCandidates = $this->vectorStore->querySimilar(
            queryVector: $vector,
            limit: $candidateLimit,
            minSimilarity: $minSimilarity,
            modelVersion: $this->embeddingGenerator->getModelVersion(),
        );

        // Deduplicate candidates by product_id, keeping the highest similarity per product
        /** @var array<int, VisualCandidate> $bestCandidatesByProduct */
        $bestCandidatesByProduct = [];

        foreach ($rawCandidates as $candidate) {
            $pId = $candidate->productId;
            if (! isset($bestCandidatesByProduct[$pId]) || $candidate->similarity > $bestCandidatesByProduct[$pId]->similarity) {
                $bestCandidatesByProduct[$pId] = $candidate;
            }
        }

        // Sort deduplicated candidates by similarity descending
        uasort($bestCandidatesByProduct, fn ($a, $b) => $b->similarity <=> $a->similarity);

        // Take top $limit candidates
        $topCandidates = array_slice(array_values($bestCandidatesByProduct), 0, $limit);
        $productIds = array_map(fn (VisualCandidate $c) => $c->productId, $topCandidates);

        // Rehydrate models from MySQL in order
        $products = $this->rehydrationService->rehydrate($productIds, activeOnly: true);

        $processingTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return new VisualSearchResult(
            items: $products,
            candidates: $topCandidates,
            totalCandidates: count($rawCandidates),
            processingTimeMs: $processingTimeMs,
            modelVersion: $this->embeddingGenerator->getModelVersion(),
        );
    }
}
