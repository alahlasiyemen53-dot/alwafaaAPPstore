<?php

namespace App\DTOs;

final class LoyaltyAdjustmentData
{
    public function __construct(
        public readonly int $customerId,
        public readonly int $points,
        public readonly string $type, // 'credit' or 'debit'
        public readonly string $reason,
        public readonly ?int $createdBy = null,
    ) {}
}
