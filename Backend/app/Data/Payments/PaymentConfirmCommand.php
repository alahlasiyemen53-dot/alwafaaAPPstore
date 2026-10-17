<?php

declare(strict_types=1);

namespace App\Data\Payments;

final readonly class PaymentConfirmCommand
{
    public function __construct(
        public string $purchaseId,
        public string $otp,
        public ?string $requestId = null,
    ) {}
}
