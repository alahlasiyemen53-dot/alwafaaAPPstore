<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class DriverCodSettlementData
{
    /**
     * @param  array<int, DriverCodSettlementItemData>  $items
     */
    public function __construct(
        public int $driverId,
        public int $currencyId,
        public string $treasuryAccountType,
        public int $treasuryAccountId,
        public array $items,
        public ?string $notes = null,
        public bool $postDirectly = false,
    ) {}
}
