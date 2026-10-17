<?php

namespace App\Data;

final readonly class UpdateProductData
{
    public function __construct(
        public int $brandId,
        public int $categoryId,
        public string $nameAr,
        public ?string $nameEn,
        public ?string $slug = null,
        public ?string $descriptionAr = null,
        public ?string $descriptionEn = null,
        public bool $hasVariants = false,
        public bool $isBestSeller = false,
        public bool $isNew = false,
        public bool $isExclusive = false,
        public ?int $supplierId = null,
        public bool $confirmSupplierChange = false,
        public ?string $supplierItemNumber = null,
    ) {}

    public static function fromValidated(array $attributes): self
    {
        return new self(
            (int) $attributes['brand_id'],
            $attributes['category_id'],
            $attributes['name_ar'],
            $attributes['name_en'] ?? null,
            $attributes['slug'] ?? null,
            $attributes['description_ar'] ?? null,
            $attributes['description_en'] ?? null,
            (bool) ($attributes['has_variants'] ?? false),
            (bool) ($attributes['is_best_seller'] ?? false),
            (bool) ($attributes['is_new'] ?? false),
            (bool) ($attributes['is_exclusive'] ?? false),
            array_key_exists('supplier_id', $attributes) && $attributes['supplier_id'] !== '' && $attributes['supplier_id'] !== null
                ? (int) $attributes['supplier_id']
                : null,
            (bool) ($attributes['confirm_supplier_change'] ?? false),
            array_key_exists('supplier_item_number', $attributes)
                ? (trim((string) ($attributes['supplier_item_number'] ?? '')) !== ''
                    ? trim((string) $attributes['supplier_item_number'])
                    : null)
                : null,
        );
    }
}
