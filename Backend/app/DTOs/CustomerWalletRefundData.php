<?php

namespace App\DTOs;

final class CustomerWalletRefundData
{
    public function __construct(
        public readonly int $salesReturnId,
        public readonly ?string $reason = null,
        public readonly ?int $refundedBy = null,
    ) {}
}
