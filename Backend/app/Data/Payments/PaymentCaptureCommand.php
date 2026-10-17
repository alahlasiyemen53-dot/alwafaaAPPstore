<?php

declare(strict_types=1);

namespace App\Data\Payments;

final readonly class PaymentCaptureCommand
{
    public function __construct(
        public string $requestId,
        public string $customerPhone,
        public string $paymentCode,
        public string $amount,
        public string $currencyCode,
        public ?string $notes = null,
    ) {}
}
