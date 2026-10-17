<?php

namespace App\Data;

final readonly class SupplierData
{
    public function __construct(
        public ?string $code,
        public string $name,
        public ?string $contactPerson,
        public string $phone,
        public ?string $secondaryPhone,
        public ?string $email,
        public ?string $address,
        public ?string $taxNumber,
        public ?string $notes,
        public bool $isActive,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            code: isset($data['code']) && trim((string) $data['code']) !== '' ? trim((string) $data['code']) : null,
            name: trim((string) $data['name']),
            contactPerson: isset($data['contact_person']) ? trim((string) $data['contact_person']) : null,
            phone: trim((string) $data['phone']),
            secondaryPhone: isset($data['secondary_phone']) && trim((string) $data['secondary_phone']) !== '' ? trim((string) $data['secondary_phone']) : null,
            email: isset($data['email']) && trim((string) $data['email']) !== '' ? trim((string) $data['email']) : null,
            address: isset($data['address']) ? trim((string) $data['address']) : null,
            taxNumber: isset($data['tax_number']) && trim((string) $data['tax_number']) !== '' ? trim((string) $data['tax_number']) : null,
            notes: isset($data['notes']) ? trim((string) $data['notes']) : null,
            isActive: (bool) ($data['is_active'] ?? true),
        );
    }
}
