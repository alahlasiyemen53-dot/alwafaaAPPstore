<?php

namespace App\DTOs;

final readonly class BankAccountData
{
    /**
     * @param  list<int>  $currencyIds
     */
    public function __construct(
        public string $code,
        public string $bankName,
        public string $accountName,
        public string $accountNumber,
        public ?string $iban,
        public int $currencyId,
        public int $glAccountId,
        public bool $isActive,
        public ?string $notes,
        public array $currencyIds = [],
    ) {}
}
