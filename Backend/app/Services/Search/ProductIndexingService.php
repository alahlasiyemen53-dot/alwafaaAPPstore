<?php

namespace App\Services\Search;

use App\Contracts\Search\MeilisearchClientInterface;
use App\DTOs\Search\ProductSearchDocument;
use App\Enums\ProductStatus;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Support\ArabicTextNormalizer;
use Illuminate\Database\Eloquent\Collection;

final class ProductIndexingService
{
    private string $indexName;

    public function __construct(
        private readonly MeilisearchClientInterface $client,
        private readonly MeilisearchIndexManager $indexManager,
    ) {
        $this->indexName = $this->indexManager->getCatalogIndexName();
    }

    public function buildDocument(Product $product): ProductSearchDocument
    {
        // Category hierarchy
        $categoryIds = [];
        $category = $product->category;
        $categoryNameAr = $category?->name_ar;
        $categoryNameEn = $category?->name_en;

        if ($category) {
            $categoryIds[] = $category->id;
            $currentParent = $category->parent;
            $safety = 0;
            while ($currentParent && $safety < 10) {
                $categoryIds[] = $currentParent->id;
                $currentParent = $currentParent->parent;
                $safety++;
            }
        }

        // Variants, stock, prices, SKUs, barcodes
        $skus = [];
        $barcodes = [];
        $currencies = [];
        $attributes = [];
        $totalStock = 0;
        $minPriceCents = PHP_INT_MAX;
        $maxPriceCents = 0;

        /** @var Collection<int, ProductVariant> $variants */
        $variants = $product->variants;

        foreach ($variants as $variant) {
            if (! empty($variant->sku)) {
                $skus[] = (string) $variant->sku;
            }
            if (! empty($variant->barcode)) {
                $barcodes[] = (string) $variant->barcode;
            }

            // Inventory
            foreach ($variant->inventories as $inventory) {
                $available = max(0, (float) $inventory->quantity - (float) $inventory->reserved_quantity);
                $totalStock += (int) $available;
            }

            // Prices
            foreach ($variant->prices as $priceRecord) {
                if (! $priceRecord->is_active) {
                    continue;
                }

                $cents = (int) round(((float) $priceRecord->price) * 100);
                if ($cents > 0) {
                    if ($cents < $minPriceCents) {
                        $minPriceCents = $cents;
                    }
                    if ($cents > $maxPriceCents) {
                        $maxPriceCents = $cents;
                    }
                }

                if ($priceRecord->currency && ! empty($priceRecord->currency->code)) {
                    $currencies[] = (string) $priceRecord->currency->code;
                }
            }

            // Attributes
            if ($variant->relationLoaded('attributeValues')) {
                foreach ($variant->attributeValues as $val) {
                    $attrCode = $val->attribute ? (string) $val->attribute->code : 'attr_'.$val->attribute_id;
                    if (! isset($attributes[$attrCode])) {
                        $attributes[$attrCode] = [];
                    }
                    if (! empty($val->value_ar)) {
                        $attributes[$attrCode][] = $val->value_ar;
                    }
                    if (! empty($val->value_en)) {
                        $attributes[$attrCode][] = $val->value_en;
                    }
                    $attributes[$attrCode] = array_values(array_unique($attributes[$attrCode]));
                }
            }
        }

        if ($minPriceCents === PHP_INT_MAX) {
            $minPriceCents = 0;
        }

        $isActive = ($product->status === ProductStatus::Active);
        $hasStock = ($totalStock > 0);

        $searchableText = ArabicTextNormalizer::normalize(implode(' ', array_filter([
            (string) $product->name_ar,
            (string) $product->name_en,
            (string) ($product->description_ar ?? ''),
            (string) ($product->description_en ?? ''),
            (string) ($product->brand?->name_ar ?? ''),
            (string) ($product->brand?->name_en ?? ''),
            (string) ($categoryNameAr ?? ''),
            (string) ($categoryNameEn ?? ''),
            ...$skus,
            ...$barcodes,
        ])));

        return new ProductSearchDocument(
            id: $product->id,
            nameAr: (string) $product->name_ar,
            nameEn: (string) $product->name_en,
            descriptionAr: $product->description_ar,
            descriptionEn: $product->description_en,
            brandId: $product->brand_id,
            brandNameAr: $product->brand?->name_ar,
            brandNameEn: $product->brand?->name_en,
            categoryId: $product->category_id,
            categoryNameAr: $categoryNameAr,
            categoryNameEn: $categoryNameEn,
            categoryIds: array_values(array_unique($categoryIds)),
            slug: (string) $product->slug,
            status: $product->status->value,
            isActive: $isActive,
            hasVariants: (bool) $product->has_variants,
            skus: $skus,
            barcodes: $barcodes,
            hasStock: $hasStock,
            totalStock: $totalStock,
            minPriceCents: $minPriceCents,
            maxPriceCents: $maxPriceCents,
            currencies: $currencies,
            attributes: $attributes,
            tags: [],
            createdAt: $product->created_at ? $product->created_at->getTimestamp() : time(),
            updatedAt: $product->updated_at ? $product->updated_at->getTimestamp() : time(),
            indexedAt: time(),
            searchableText: $searchableText,
        );
    }

    public function indexProduct(Product $product): bool
    {
        // If product is not active or deleted, we can either remove or index with is_active = false
        // Indexing with is_active = false allows admin to search inactive products, while storefront filters is_active = true.
        $this->loadProductRelations($product);
        $doc = $this->buildDocument($product);

        $res = $this->client->addDocuments($this->indexName, [$doc->toArray()]);

        return ! isset($res['error']);
    }

    public function unindexProduct(int|Product $product): bool
    {
        $id = $product instanceof Product ? $product->id : $product;
        $res = $this->client->deleteDocument($this->indexName, $id);

        return ! isset($res['error']);
    }

    /**
     * @param  (callable(int $processed, int $total): void)|null  $progressCallback
     * @return int Total indexed
     */
    public function indexAll(int $chunkSize = 100, ?callable $progressCallback = null): int
    {
        $this->indexManager->setupCatalogIndex();

        $query = Product::query()->with([
            'brand',
            'category.parent',
            'variants.prices.currency',
            'variants.inventories',
            'variants.attributeValues.attribute',
        ]);

        $total = $query->count();
        $processed = 0;

        $query->chunk($chunkSize, function (Collection $products) use (&$processed, $total, $progressCallback) {
            $batch = [];
            foreach ($products as $product) {
                /** @var Product $product */
                $batch[] = $this->buildDocument($product)->toArray();
            }

            if (! empty($batch)) {
                $this->client->addDocuments($this->indexName, $batch);
                $processed += count($batch);
                if ($progressCallback !== null) {
                    $progressCallback($processed, $total);
                }
            }
        });

        return $processed;
    }

    private function loadProductRelations(Product $product): void
    {
        $product->loadMissing([
            'brand',
            'category.parent',
            'variants.prices.currency',
            'variants.inventories',
            'variants.attributeValues.attribute',
        ]);
    }
}
