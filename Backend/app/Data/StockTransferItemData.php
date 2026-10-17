<?php

namespace App\Data;

final readonly class StockTransferItemData
{
    public function __construct(
        public int $productVariantId,
        public float $quantity,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            productVariantId: (int) $data['product_variant_id'],
            quantity: (float) $data['quantity'],
        );
    }
}
