<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class AssignOrderToDriverData
{
    public function __construct(
        public int $driverId,
        public ?string $notes = null,
    ) {}

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            driverId: (int) $data['driver_id'],
            notes: isset($data['notes']) && filled($data['notes']) ? (string) $data['notes'] : null,
        );
    }
}
