<?php

namespace App\DTOs\Search;

final class ProductSearchQuery
{
    /**
     * @param  array<int, int>|null  $categoryIds
     * @param  array<int, int>|null  $brandIds
     * @param  array<string, array<int, string>>  $attributes
     * @param  array<int, string>  $facets
     */
    public function __construct(
        public readonly string $query = '',
        public readonly ?array $categoryIds = null,
        public readonly ?array $brandIds = null,
        public readonly ?int $minPriceCents = null,
        public readonly ?int $maxPriceCents = null,
        public readonly bool $inStockOnly = false,
        public readonly bool $isActiveOnly = true,
        public readonly ?string $currencyCode = null,
        public readonly array $attributes = [],
        public readonly string $sortBy = 'relevance',
        public readonly int $page = 1,
        public readonly int $perPage = 20,
        public readonly array $facets = [],
    ) {}

    /**
     * @param  array<string, mixed>  $params
     */
    public static function fromArray(array $params): self
    {
        return new self(
            query: (string) ($params['q'] ?? $params['query'] ?? ''),
            categoryIds: isset($params['category_ids']) ? array_map('intval', (array) $params['category_ids']) : (isset($params['category_id']) ? [(int) $params['category_id']] : null),
            brandIds: isset($params['brand_ids']) ? array_map('intval', (array) $params['brand_ids']) : (isset($params['brand_id']) ? [(int) $params['brand_id']] : null),
            minPriceCents: isset($params['min_price']) ? (int) ($params['min_price'] * 100) : (isset($params['min_price_cents']) ? (int) $params['min_price_cents'] : null),
            maxPriceCents: isset($params['max_price']) ? (int) ($params['max_price'] * 100) : (isset($params['max_price_cents']) ? (int) $params['max_price_cents'] : null),
            inStockOnly: (bool) ($params['in_stock_only'] ?? false),
            isActiveOnly: (bool) ($params['is_active_only'] ?? true),
            currencyCode: isset($params['currency']) ? (string) $params['currency'] : null,
            attributes: (array) ($params['attributes'] ?? []),
            sortBy: (string) ($params['sort_by'] ?? 'relevance'),
            page: max(1, (int) ($params['page'] ?? 1)),
            perPage: min(100, max(1, (int) ($params['per_page'] ?? 20))),
            facets: (array) ($params['facets'] ?? ['brand_id', 'category_id']),
        );
    }
}
