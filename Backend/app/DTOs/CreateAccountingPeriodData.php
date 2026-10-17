<?php

namespace App\DTOs;

final readonly class CreateAccountingPeriodData
{
    public function __construct(
        public string $name,
        public string $startsAt,
        public string $endsAt,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: trim((string) $data['name']),
            startsAt: trim((string) $data['starts_at']),
            endsAt: trim((string) $data['ends_at']),
        );
    }
}
