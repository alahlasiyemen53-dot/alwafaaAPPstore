<?php

namespace App\DTOs\Search;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

final class ProductSearchResult
{
    /**
     * @param  Collection<int, Product>  $items
     * @param  array<int, int>  $candidateIds
     * @param  array<string, mixed>  $facetsDistribution
     * @param  array<string, mixed>  $appliedFilters
     */
    public function __construct(
        public readonly Collection $items,
        public readonly array $candidateIds,
        public readonly int $total,
        public readonly int $page,
        public readonly int $perPage,
        public readonly int $lastPage,
        public readonly float $processingTimeMs,
        public readonly array $facetsDistribution = [],
        public readonly array $appliedFilters = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'page' => $this->page,
            'per_page' => $this->perPage,
            'last_page' => $this->lastPage,
            'processing_time_ms' => $this->processingTimeMs,
            'facets' => $this->facetsDistribution,
            'items_count' => $this->items->count(),
        ];
    }
}
