<?php

declare(strict_types=1);

namespace App\Data\Payments;

final readonly class PaymentCaptureResult
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public bool $success,
        public ?string $providerReference = null,
        public ?string $requestId = null,
        public ?string $message = null,
        public array $raw = [],
    ) {}

    public static function failed(string $message, array $raw = []): self
    {
        return new self(success: false, message: $message, raw: $raw);
    }

    public static function succeeded(string $providerReference, ?string $requestId = null, ?string $message = null, array $raw = []): self
    {
        return new self(
            success: true,
            providerReference: $providerReference,
            requestId: $requestId,
            message: $message,
            raw: $raw,
        );
    }
}
