<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\CustomerAddress;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerAddress
 */
final class CustomerAddressResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'label' => $this->label,
            'contact_name' => $this->contact_name,
            'contact_phone' => $this->contact_phone,
            'country_id' => $this->country_id,
            'governorate_id' => $this->governorate_id,
            'country' => $this->country ? [
                'id' => $this->country->id,
                'code' => $this->country->code,
                'name_ar' => $this->country->name_ar,
                'name_en' => $this->country->name_en,
                'calling_code' => $this->country->calling_code,
            ] : null,
            'governorate' => $this->governorate ? [
                'id' => $this->governorate->id,
                'name_ar' => $this->governorate->name_ar,
                'name_en' => $this->governorate->name_en,
            ] : null,
            'neighborhood' => $this->neighborhood,
            'street' => $this->street,
            'building' => $this->building,
            'floor' => $this->floor,
            'apartment' => $this->apartment,
            'landmark' => $this->landmark,
            'notes' => $this->notes,
            'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
            'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            'coordinates' => [
                'latitude' => $this->latitude !== null ? (float) $this->latitude : null,
                'longitude' => $this->longitude !== null ? (float) $this->longitude : null,
            ],
            'formatted_address' => $this->formatted_address,
            'is_default' => (bool) $this->is_default,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
