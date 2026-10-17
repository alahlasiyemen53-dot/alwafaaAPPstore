<?php

namespace App\DTOs\Search;

final class ProductSearchDocument
{
    /**
     * @param  array<int, int>  $categoryIds
     * @param  array<int, string>  $skus
     * @param  array<int, string>  $barcodes
     * @param  array<int, string>  $currencies
     * @param  array<string, array<int, string>>  $attributes
     * @param  array<int, string>  $tags
     */
    public function __construct(
        public readonly int $id,
        public readonly string $nameAr,
        public readonly string $nameEn,
        public readonly ?string $descriptionAr,
        public readonly ?string $descriptionEn,
        public readonly ?int $brandId,
        public readonly ?string $brandNameAr,
        public readonly ?string $brandNameEn,
        public readonly ?int $categoryId,
        public readonly ?string $categoryNameAr,
        public readonly ?string $categoryNameEn,
        public readonly array $categoryIds,
        public readonly string $slug,
        public readonly string $status,
        public readonly bool $isActive,
        public readonly bool $hasVariants,
        public readonly array $skus,
        public readonly array $barcodes,
        public readonly bool $hasStock,
        public readonly int $totalStock,
        public readonly int $minPriceCents,
        public readonly int $maxPriceCents,
        public readonly array $currencies,
        public readonly array $attributes,
        public readonly array $tags,
        public readonly int $createdAt,
        public readonly int $updatedAt,
        public readonly int $indexedAt,
        public readonly string $searchableText = '',
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->nameAr,
            'name_en' => $this->nameEn,
            'description_ar' => $this->descriptionAr ?? '',
            'description_en' => $this->descriptionEn ?? '',
            'brand_id' => $this->brandId,
            'brand_name_ar' => $this->brandNameAr ?? '',
            'brand_name_en' => $this->brandNameEn ?? '',
            'category_id' => $this->categoryId,
            'category_name_ar' => $this->categoryNameAr ?? '',
            'category_name_en' => $this->categoryNameEn ?? '',
            'category_ids' => $this->categoryIds,
            'slug' => $this->slug,
            'status' => $this->status,
            'is_active' => $this->isActive,
            'has_variants' => $this->hasVariants,
            'skus' => array_values(array_unique(array_filter($this->skus))),
            'barcodes' => array_values(array_unique(array_filter($this->barcodes))),
            'has_stock' => $this->hasStock,
            'total_stock' => $this->totalStock,
            'min_price_cents' => $this->minPriceCents,
            'max_price_cents' => $this->maxPriceCents,
            'currencies' => array_values(array_unique(array_filter($this->currencies))),
            'attributes' => $this->attributes,
            'tags' => array_values(array_unique(array_filter($this->tags))),
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
            'indexed_at' => $this->indexedAt,
            'searchable_text' => $this->searchableText,
        ];
    }
}
