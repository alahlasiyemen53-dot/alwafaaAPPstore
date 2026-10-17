<?php

declare(strict_types=1);

namespace App\Data\Payments;

final readonly class PaymentInitiateResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public ?string $purchaseId = null,
        public ?string $requestId = null,
        public ?string $providerReference = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public static function failed(string $message, array $raw = []): self
    {
        return new self(success: false, message: $message, raw: $raw);
    }

    public static function pending(string $purchaseId, string $requestId, ?string $providerReference = null, ?string $message = null, array $raw = []): self
    {
        return new self(
            success: true,
            purchaseId: $purchaseId,
            requestId: $requestId,
            providerReference: $providerReference,
            message: $message,
            raw: $raw,
        );
    }
}
