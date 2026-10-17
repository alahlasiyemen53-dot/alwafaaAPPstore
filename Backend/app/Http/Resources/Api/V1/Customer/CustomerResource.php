<?php

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
class CustomerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->loadMissing(['country', 'governorate']);
        $tier = $this->getCurrentTier();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'normalized_phone' => $this->normalized_phone,
            'email' => $this->email,
            'is_active' => (bool) $this->is_active,
            'phone_verified' => $this->isPhoneVerified(),
            'phone_verified_at' => $this->phone_verified_at?->toIso8601String(),
            'tier' => $tier !== null ? [
                'id' => $tier->id,
                'code' => $tier->code,
                'name' => app()->getLocale() === 'en' ? $tier->name_en : $tier->name_ar,
            ] : null,
            'country' => $this->country_id !== null && $this->country !== null
                ? new CustomerReferenceCountryResource($this->country)
                : null,
            'governorate' => $this->governorate_id !== null && $this->governorate !== null
                ? new CustomerReferenceGovernorateResource($this->governorate)
                : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
