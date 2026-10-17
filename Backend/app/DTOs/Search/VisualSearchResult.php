<?php

namespace App\DTOs\Search;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

final class VisualSearchResult
{
    /**
     * @param  Collection<int, Product>  $items
     * @param  array<int, VisualCandidate>  $candidates
     */
    public function __construct(
        public readonly Collection $items,
        public readonly array $candidates,
        public readonly int $totalCandidates,
        public readonly float $processingTimeMs,
        public readonly string $modelVersion,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total_candidates' => $this->totalCandidates,
            'items_count' => $this->items->count(),
            'processing_time_ms' => $this->processingTimeMs,
            'model_version' => $this->modelVersion,
        ];
    }
}
