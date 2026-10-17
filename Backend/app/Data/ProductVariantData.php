<?php

namespace App\Data;

use App\Enums\ProductVariantFulfillmentMode;
use App\Enums\ProductVariantStatus;

final readonly class ProductVariantData
{
    /**
     * @param  array<int>  $attributeValueIds
     */
    public function __construct(
        public int $productId,
        public string $sku,
        public ?string $barcode,
        public ProductVariantStatus $status,
        public bool $isDefault,
        public array $attributeValueIds,
        public string $price,
        public ?string $compareAtPrice,
        public ProductVariantFulfillmentMode $fulfillmentMode = ProductVariantFulfillmentMode::Stocked,
        public ?int $supplierId = null,
        public ?string $supplierItemNumber = null,
    ) {}

    public static function fromValidated(int $productId, array $attributes): self
    {
        return new self(
            productId: $productId,
            sku: ! empty($attributes['sku']) ? strtoupper(trim($attributes['sku'])) : '',
            barcode: ! empty($attributes['barcode']) ? trim($attributes['barcode']) : null,
            status: ProductVariantStatus::from($attributes['status'] ?? ProductVariantStatus::Active->value),
            isDefault: (bool) ($attributes['is_default'] ?? false),
            attributeValueIds: array_values(array_filter(
                array_map('intval', (array) ($attributes['attribute_value_ids'] ?? [])),
                fn (int $id) => $id > 0
            )),
            price: (string) $attributes['price'],
            compareAtPrice: isset($attributes['compare_at_price']) && $attributes['compare_at_price'] !== ''
                ? (string) $attributes['compare_at_price']
                : null,
            fulfillmentMode: isset($attributes['fulfillment_mode'])
                ? (is_string($attributes['fulfillment_mode']) ? ProductVariantFulfillmentMode::from($attributes['fulfillment_mode']) : $attributes['fulfillment_mode'])
                : ProductVariantFulfillmentMode::Stocked,
            supplierId: ! empty($attributes['supplier_id']) ? (int) $attributes['supplier_id'] : null,
            supplierItemNumber: isset($attributes['supplier_item_number']) && trim((string) $attributes['supplier_item_number']) !== ''
                ? trim((string) $attributes['supplier_item_number'])
                : null,
        );
    }
}
