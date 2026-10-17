<?php

namespace App\Services\Search;

use App\Contracts\Search\MeilisearchClientInterface;

final class MeilisearchIndexManager
{
    private string $indexPrefix;

    public function __construct(
        private readonly MeilisearchClientInterface $client,
        ?string $indexPrefix = null,
    ) {
        $this->indexPrefix = $indexPrefix ?? (string) config('search.meilisearch.index_prefix', 'lamma_catalog_');
    }

    public function getCatalogIndexName(): string
    {
        return $this->indexPrefix.'products';
    }

    public function setupCatalogIndex(): array
    {
        $indexName = $this->getCatalogIndexName();

        // Ensure index exists
        $createRes = $this->client->createIndexIfNotExists($indexName, 'id');

        // Configure index settings
        $settings = [
            'searchableAttributes' => [
                'searchable_text',
                'name_ar',
                'name_en',
                'skus',
                'barcodes',
                'brand_name_ar',
                'brand_name_en',
                'category_name_ar',
                'category_name_en',
                'tags',
                'description_ar',
                'description_en',
            ],
            'filterableAttributes' => [
                'id',
                'brand_id',
                'category_id',
                'category_ids',
                'status',
                'is_active',
                'has_stock',
                'total_stock',
                'min_price_cents',
                'max_price_cents',
                'currencies',
                'created_at',
            ],
            'sortableAttributes' => [
                'created_at',
                'min_price_cents',
                'max_price_cents',
                'name_ar',
                'name_en',
                'total_stock',
            ],
            'rankingRules' => [
                'words',
                'typo',
                'proximity',
                'attribute',
                'sort',
                'exactness',
            ],
            'typoTolerance' => [
                'enabled' => true,
                'minWordSizeForTypos' => [
                    'oneTypo' => 4,
                    'twoTypos' => 8,
                ],
                'disableOnAttributes' => [
                    'skus',
                    'barcodes',
                ],
            ],
        ];

        $settingsRes = $this->client->updateSettings($indexName, $settings);

        return [
            'index' => $indexName,
            'create_result' => $createRes,
            'settings_result' => $settingsRes,
        ];
    }
}
