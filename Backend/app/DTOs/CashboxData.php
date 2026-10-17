<?php

namespace App\DTOs;

final readonly class CashboxData
{
    /**
     * @param  list<int>  $currencyIds
     */
    public function __construct(
        public string $code,
        public string $nameAr,
        public ?string $nameEn,
        public int $currencyId,
        public int $glAccountId,
        public bool $isActive,
        public ?string $notes,
        public array $currencyIds = [],
    ) {}
}
