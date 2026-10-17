<?php

namespace App\DTOs;

final readonly class TreasuryTransferData
{
    public function __construct(
        public string $sourceType,
        public int $sourceId,
        public string $destinationType,
        public int $destinationId,
        public float|string $amount,
        public string $description,
        public ?string $referenceNumber = null,
        public float|string|null $exchangeRateSnapshot = null,
    ) {}
}
