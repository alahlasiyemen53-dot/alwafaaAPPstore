<?php

namespace App\Data;

use App\Enums\ProductVariantFulfillmentMode;

final readonly class ProductImportRowData
{
    /** @param array<int> $attributeValueIds */
    public function __construct(
        public int $rowNumber,
        public string $productRef,
        public string $variantRef,
        public string $nameAr,
        public ?string $nameEn,
        public ?string $descriptionAr,
        public ?int $brandId,
        public int $categoryId,
        public array $attributeValueIds,
        public string $price,
        public ProductVariantFulfillmentMode $fulfillmentMode,
        public ?int $supplierId,
        public ?string $supplierItemNumber,
        public ?int $warehouseId,
        public ?string $stockQuantity,
        public bool $isActive,
    ) {}
}
