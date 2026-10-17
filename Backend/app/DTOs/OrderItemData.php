<?php

namespace App\DTOs;

final readonly class OrderItemData
{
    public function __construct(
        public int $productVariantId,
        public float|string $quantity,
        public ?int $unitId = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            productVariantId: (int) $data['product_variant_id'],
            quantity: (float) $data['quantity'],
            unitId: isset($data['unit_id']) && $data['unit_id'] !== '' ? (int) $data['unit_id'] : null,
        );
    }
}
