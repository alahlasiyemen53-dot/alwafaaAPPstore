<?php

namespace App\Data;

final readonly class CustomerData
{
    public function __construct(
        public string $name,
        public string $phone,
        public ?string $email,
        public ?string $notes,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: trim((string) $data['name']),
            phone: trim((string) $data['phone']),
            email: isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null,
            notes: isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
