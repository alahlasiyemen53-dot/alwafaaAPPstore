<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class MarkDeliveryFailedData
{
    public function __construct(
        public string $failureReasonCode,
        public ?string $failureNotes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        $reason = $data['failure_reason'] ?? $data['failure_reason_code'] ?? '';
        if ($reason instanceof \BackedEnum) {
            $reason = (string) $reason->value;
        }

        return new self(
            failureReasonCode: (string) $reason,
            failureNotes: isset($data['notes']) && filled($data['notes'])
                ? (string) $data['notes']
                : (isset($data['failure_notes']) && filled($data['failure_notes']) ? (string) $data['failure_notes'] : null),
        );
    }
}
