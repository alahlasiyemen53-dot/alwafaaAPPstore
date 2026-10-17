<?php

namespace App\DTOs;

use App\Enums\TreasuryTransactionType;

final readonly class TreasuryTransactionData
{
    public function __construct(
        public TreasuryTransactionType $transactionType,
        public string $accountType,
        public int $accountId,
        public int $currencyId,
        public float|string $amount,
        public string $description,
        public ?int $offsetAccountId = null,
        public ?string $counterpartyType = null,
        public ?int $counterpartyId = null,
        public ?string $referenceNumber = null,
        public float|string|null $exchangeRateSnapshot = null,
    ) {}
}
