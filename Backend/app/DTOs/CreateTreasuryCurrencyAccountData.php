<?php

namespace App\DTOs;

final class CreateTreasuryCurrencyAccountData
{
    public function __construct(
        public readonly string $accountableType,
        public readonly int $accountableId,
        public readonly int $currencyId,
        public readonly int $glAccountId,
        public readonly ?string $notes = null,
    ) {}
}
