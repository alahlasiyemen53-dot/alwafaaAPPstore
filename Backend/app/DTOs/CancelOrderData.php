<?php

namespace App\DTOs;

final readonly class CancelOrderData
{
    public function __construct(
        public string $cancellationReason,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            cancellationReason: trim((string) ($data['cancellation_reason'] ?? '')),
        );
    }
}
