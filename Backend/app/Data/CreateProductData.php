<?php

namespace App\Data;

use App\Enums\ProductStatus;

final readonly class CreateProductData
{
    public function __construct(
        public int $brandId,
        public int $categoryId,
        public string $nameAr,
        public ?string $nameEn,
        public string $slug,
        public ?string $descriptionAr,
        public ?string $descriptionEn,
        public ProductStatus $status,
        public bool $hasVariants,
        public bool $isBestSeller = false,
        public bool $isNew = false,
        public bool $isExclusive = false,
        public ?int $supplierId = null,
        public ?string $supplierItemNumber = null,
        public array $variants = [],
    ) {}

    public static function fromValidated(array $attributes): self
    {
        return new self(
            (int) $attributes['brand_id'],
            $attributes['category_id'],
            $attributes['name_ar'],
            $attributes['name_en'] ?? null,
            $attributes['slug'],
            $attributes['description_ar'] ?? null,
            $attributes['description_en'] ?? null,
            ProductStatus::from($attributes['status']),
            (bool) ($attributes['has_variants'] ?? false),
            (bool) ($attributes['is_best_seller'] ?? false),
            (bool) ($attributes['is_new'] ?? false),
            (bool) ($attributes['is_exclusive'] ?? false),
            isset($attributes['supplier_id']) && $attributes['supplier_id'] !== '' && $attributes['supplier_id'] !== null
                ? (int) $attributes['supplier_id']
                : null,
            isset($attributes['supplier_item_number']) && trim((string) $attributes['supplier_item_number']) !== ''
                ? trim((string) $attributes['supplier_item_number'])
                : null,
            array_values(array_filter(
                (array) ($attributes['variants'] ?? []),
                fn ($row) => is_array($row)
            )),
        );
    }
}