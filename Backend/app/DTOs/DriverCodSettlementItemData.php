<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class DriverCodSettlementItemData
{
    public function __construct(
        public int $deliveryId,
        public float|string $settlementAmount,
    ) {}
}
