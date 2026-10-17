<?php

namespace App\DTOs;

final readonly class SalesReturnData
{
    /**
     * @param  array<int, SalesReturnItemData>  $items
     */
    public function __construct(
        public int $orderId,
        public int $warehouseId,
        public string $reasonCode,
        public ?string $notes,
        public array $items,
    ) {}
}
