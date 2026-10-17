<?php

namespace App\Services\Search;

use App\Contracts\Search\MeilisearchClientInterface;
use App\Contracts\Search\ProductSearchInterface;
use App\DTOs\Search\ProductSearchQuery;
use App\DTOs\Search\ProductSearchResult;
use App\Enums\ProductStatus;
use App\Models\Product;
use App\Support\ArabicTextNormalizer;
use Illuminate\Database\Eloquent\Builder;

final class MeilisearchProductSearch implements ProductSearchInterface
{
    private string $indexName;

    public function __construct(
        private readonly MeilisearchClientInterface $client,
        private readonly MeilisearchIndexManager $indexManager,
        private readonly SearchResultRehydrationService $rehydrationService,
    ) {
        $this->indexName = $this->indexManager->getCatalogIndexName();
    }

    public function isHealthy(): bool
    {
        return $this->client->isHealthy();
    }

    public function search(ProductSearchQuery $query): ProductSearchResult
    {
        if (! $this->isHealthy()) {
            return $this->searchWithMysql($query);
        }

        $options = [
            'limit' => $query->perPage,
            'offset' => ($query->page - 1) * $query->perPage,
            // Prefer matching remaining words as the user types multi-word Arabic queries
            'matchingStrategy' => 'last',
        ];

        $filters = [];

        if ($query->isActiveOnly) {
            $filters[] = 'is_active = true';
        }

        if ($query->inStockOnly) {
            $filters[] = 'has_stock = true';
        }

        if (! empty($query->categoryIds)) {
            $catFilters = array_map(fn ($id) => "category_ids = {$id}", $query->categoryIds);
            $filters[] = '('.implode(' OR ', $catFilters).')';
        }

        if (! empty($query->brandIds)) {
            $brandFilters = array_map(fn ($id) => "brand_id = {$id}", $query->brandIds);
            $filters[] = '('.implode(' OR ', $brandFilters).')';
        }

        if ($query->minPriceCents !== null) {
            $filters[] = "max_price_cents >= {$query->minPriceCents}";
        }

        if ($query->maxPriceCents !== null) {
            $filters[] = "min_price_cents <= {$query->maxPriceCents}";
        }

        if (! empty($query->currencyCode)) {
            $currency = addslashes($query->currencyCode);
            $filters[] = "currencies = \"{$currency}\"";
        }

        if (! empty($filters)) {
            $options['filter'] = implode(' AND ', $filters);
        }

        // Sorting
        $sortRules = match ($query->sortBy) {
            'price_asc' => ['min_price_cents:asc'],
            'price_desc' => ['max_price_cents:desc'],
            'created_at_desc' => ['created_at:desc'],
            'name_asc' => ['name_ar:asc'],
            default => [],
        };

        if (! empty($sortRules)) {
            $options['sort'] = $sortRules;
        }

        // Facets
        if (! empty($query->facets)) {
            $options['facets'] = $query->facets;
        }

        // Prefer normalized Arabic query so أساس / اساس both hit searchable_text
        $meiliQuery = ArabicTextNormalizer::normalize($query->query);
        if ($meiliQuery === '') {
            $meiliQuery = $query->query;
        }

        $startTime = microtime(true);
        $searchResponse = $this->client->search($this->indexName, $meiliQuery, $options);
        $processingTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        $hits = $searchResponse['hits'] ?? [];
        $candidateIds = array_map(fn ($hit) => (int) $hit['id'], $hits);

        // Rehydrate candidates from MySQL
        $items = $this->rehydrationService->rehydrate($candidateIds, $query->isActiveOnly);

        $total = (int) ($searchResponse['estimatedTotalHits'] ?? $searchResponse['totalHits'] ?? count($candidateIds));
        $lastPage = max(1, (int) ceil($total / max(1, $query->perPage)));
        $facetsDistribution = $searchResponse['facetDistribution'] ?? [];

        return new ProductSearchResult(
            items: $items,
            candidateIds: $candidateIds,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            lastPage: $lastPage,
            processingTimeMs: (float) ($searchResponse['processingTimeMs'] ?? $processingTimeMs),
            facetsDistribution: $facetsDistribution,
            appliedFilters: [
                'raw_filter' => $options['filter'] ?? null,
                'sort' => $sortRules,
            ],
        );
    }

    private function searchWithMysql(ProductSearchQuery $query): ProductSearchResult
    {
        $startTime = microtime(true);
        $sqlQuery = Product::query();

        if (! empty($query->query)) {
            $this->applyMysqlTextSearch($sqlQuery, $query->query);
        }

        if ($query->isActiveOnly) {
            $sqlQuery->where('status', ProductStatus::Active);
        }

        if (! empty($query->categoryIds)) {
            $sqlQuery->whereIn('category_id', $query->categoryIds);
        }

        if (! empty($query->brandIds)) {
            $sqlQuery->whereIn('brand_id', $query->brandIds);
        }

        $total = $sqlQuery->count();
        /** @var array<int, int> $candidateIds */
        $candidateIds = $sqlQuery->offset(($query->page - 1) * $query->perPage)
            ->limit($query->perPage)
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $items = $this->rehydrationService->rehydrate($candidateIds, $query->isActiveOnly);
        $processingTimeMs = round((microtime(true) - $startTime) * 1000, 2);

        return new ProductSearchResult(
            items: $items,
            candidateIds: $candidateIds,
            total: $total,
            page: $query->page,
            perPage: $query->perPage,
            lastPage: max(1, (int) ceil($total / max(1, $query->perPage))),
            processingTimeMs: $processingTimeMs,
            facetsDistribution: [],
            appliedFilters: ['fallback' => 'mysql'],
        );
    }

    private function applyMysqlTextSearch(Builder $sqlQuery, string $rawQuery): void
    {
        $normalized = ArabicTextNormalizer::normalize($rawQuery);
        $likeNormalized = '%'.ArabicTextNormalizer::escapeLike($normalized).'%';
        $likeRaw = '%'.ArabicTextNormalizer::escapeLike(trim($rawQuery)).'%';

        $nameArNorm = ArabicTextNormalizer::sqlNormalizeExpression('name_ar');
        $nameEnNorm = ArabicTextNormalizer::sqlNormalizeExpression('name_en');
        $brandArNorm = ArabicTextNormalizer::sqlNormalizeExpression('name_ar');
        $brandEnNorm = ArabicTextNormalizer::sqlNormalizeExpression('name_en');

        $sqlQuery->where(function (Builder $q) use ($likeRaw, $likeNormalized, $nameArNorm, $nameEnNorm, $brandArNorm, $brandEnNorm) {
            $q->where('name_ar', 'like', $likeRaw)
                ->orWhere('name_en', 'like', $likeRaw)
                ->orWhereRaw("{$nameArNorm} LIKE ?", [$likeNormalized])
                ->orWhereRaw("{$nameEnNorm} LIKE ?", [$likeNormalized])
                ->orWhereHas('brand', function (Builder $bq) use ($likeRaw, $likeNormalized, $brandArNorm, $brandEnNorm) {
                    $bq->where('name_ar', 'like', $likeRaw)
                        ->orWhere('name_en', 'like', $likeRaw)
                        ->orWhereRaw("{$brandArNorm} LIKE ?", [$likeNormalized])
                        ->orWhereRaw("{$brandEnNorm} LIKE ?", [$likeNormalized]);
                })
                ->orWhereHas('variants', function (Builder $vq) use ($likeRaw) {
                    $vq->where('sku', 'like', $likeRaw)
                        ->orWhere('barcode', 'like', $likeRaw)
                        ->orWhere('supplier_item_number', 'like', $likeRaw);
                });
        });
    }
}
