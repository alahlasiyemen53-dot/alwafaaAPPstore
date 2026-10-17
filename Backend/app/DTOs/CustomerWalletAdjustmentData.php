<?php

namespace App\DTOs;

final class CustomerWalletAdjustmentData
{
    public function __construct(
        public readonly int $customerId,
        public readonly int $currencyId,
        public readonly string $amount,
        public readonly string $type, // 'credit' or 'debit'
        public readonly string $reason,
        public readonly ?int $createdBy = null,
    ) {}
}
