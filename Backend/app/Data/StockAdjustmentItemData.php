<?php

namespace App\Data;

final readonly class StockAdjustmentItemData
{
    public function __construct(
        public int $productVariantId,
        public string $direction,
        public float $quantity,
        public ?string $notes = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productVariantId: (int) $data['product_variant_id'],
            direction: (string) $data['direction'],
            quantity: (float) $data['quantity'],
            notes: $data['notes'] ?? null,
        );
    }
}
