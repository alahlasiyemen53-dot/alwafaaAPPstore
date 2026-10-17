<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Governorate;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Governorate
 */
final class CustomerReferenceGovernorateResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'country_id' => $this->country_id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'is_active' => (bool) $this->is_active,
            'is_delivery_active' => (bool) $this->is_delivery_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
