<?php

namespace App\Data;

final readonly class PurchaseInvoiceItemData
{
    public function __construct(
        public int $productVariantId,
        public int $unitId,
        public float $quantity,
        public float $baseQuantity,
        public float $freeQuantity,
        public float $unitCost,
        public float $discountAmount,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $qty = max(0.0001, (float) ($data['quantity'] ?? 1));

        return new self(
            productVariantId: (int) $data['product_variant_id'],
            unitId: (int) $data['unit_id'],
            quantity: $qty,
            baseQuantity: (float) ($data['base_quantity'] ?? $qty),
            freeQuantity: max(0.0, (float) ($data['free_quantity'] ?? 0)),
            unitCost: max(0.0, (float) ($data['unit_cost'] ?? 0)),
            discountAmount: max(0.0, (float) ($data['discount_amount'] ?? 0)),
        );
    }
}
