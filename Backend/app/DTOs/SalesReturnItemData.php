<?php

namespace App\DTOs;

final readonly class SalesReturnItemData
{
    public function __construct(
        public int $orderItemId,
        public int $productVariantId,
        public float $returnQuantity,
        public ?string $reasonCode = null,
        public ?string $notes = null,
    ) {}
}
