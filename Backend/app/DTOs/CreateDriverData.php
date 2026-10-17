<?php

declare(strict_types=1);

namespace App\DTOs;

final readonly class CreateDriverData
{
    public function __construct(
        public string $name,
        public string $phone,
        public ?string $secondaryPhone = null,
        public ?string $vehicleType = null,
        public ?string $vehiclePlateNumber = null,
        public ?string $notes = null,
        public bool $isActive = true,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            name: (string) $data['name'],
            phone: (string) $data['phone'],
            secondaryPhone: isset($data['secondary_phone']) && filled($data['secondary_phone'])
                ? (string) $data['secondary_phone']
                : (isset($data['email']) && filled($data['email']) ? (string) $data['email'] : null),
            vehicleType: isset($data['vehicle_type']) && filled($data['vehicle_type']) ? (string) $data['vehicle_type'] : null,
            vehiclePlateNumber: isset($data['vehicle_plate_number']) && filled($data['vehicle_plate_number']) ? (string) $data['vehicle_plate_number'] : null,
            notes: isset($data['notes']) && filled($data['notes']) ? (string) $data['notes'] : null,
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
        );
    }
}
