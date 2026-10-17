<?php

namespace App\DTOs;

final readonly class ElectronicWalletAccountData
{
    /**
     * @param  list<int>  $currencyIds
     */
    public function __construct(
        public string $code,
        public string $providerName,
        public string $accountLabel,
        public string $accountIdentifier,
        public int $currencyId,
        public int $glAccountId,
        public bool $isActive,
        public ?string $notes,
        public array $currencyIds = [],
    ) {}
}
