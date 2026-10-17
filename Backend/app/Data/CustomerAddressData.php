<?php

namespace App\Data;

final readonly class CustomerAddressData
{
    public function __construct(
        public string $label,
        public ?string $contactName = null,
        public ?string $contactPhone = null,
        public ?int $countryId = null,
        public ?int $governorateId = null,
        public ?int $cityId = null,
        public ?int $districtId = null,
        public ?string $neighborhood = null,
        public ?string $street = null,
        public ?string $building = null,
        public ?string $floor = null,
        public ?string $apartment = null,
        public ?string $landmark = null,
        public ?string $notes = null,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?string $googlePlaceId = null,
        public ?string $formattedAddress = null,
        public bool $isDefault = false,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            label: trim((string) $data['label']),
            contactName: isset($data['contact_name']) && trim((string) $data['contact_name']) !== '' ? trim((string) $data['contact_name']) : null,
            contactPhone: isset($data['contact_phone']) && trim((string) $data['contact_phone']) !== '' ? trim((string) $data['contact_phone']) : null,
            countryId: ! empty($data['country_id']) ? (int) $data['country_id'] : null,
            governorateId: ! empty($data['governorate_id']) ? (int) $data['governorate_id'] : null,
            cityId: ! empty($data['city_id']) ? (int) $data['city_id'] : null,
            districtId: ! empty($data['district_id']) ? (int) $data['district_id'] : null,
            neighborhood: isset($data['neighborhood']) && trim((string) $data['neighborhood']) !== '' ? trim((string) $data['neighborhood']) : null,
            street: isset($data['street']) && trim((string) $data['street']) !== '' ? trim((string) $data['street']) : null,
            building: isset($data['building']) && trim((string) $data['building']) !== '' ? trim((string) $data['building']) : null,
            floor: isset($data['floor']) && trim((string) $data['floor']) !== '' ? trim((string) $data['floor']) : null,
            apartment: isset($data['apartment']) && trim((string) $data['apartment']) !== '' ? trim((string) $data['apartment']) : null,
            landmark: isset($data['landmark']) && trim((string) $data['landmark']) !== '' ? trim((string) $data['landmark']) : null,
            notes: isset($data['notes']) && trim((string) $data['notes']) !== '' ? trim((string) $data['notes']) : null,
            latitude: isset($data['latitude']) && $data['latitude'] !== '' ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) && $data['longitude'] !== '' ? (float) $data['longitude'] : null,
            googlePlaceId: isset($data['google_place_id']) && trim((string) $data['google_place_id']) !== '' ? trim((string) $data['google_place_id']) : null,
            formattedAddress: isset($data['formatted_address']) && trim((string) $data['formatted_address']) !== '' ? trim((string) $data['formatted_address']) : null,
            isDefault: (bool) ($data['is_default'] ?? false),
        );
    }
}
