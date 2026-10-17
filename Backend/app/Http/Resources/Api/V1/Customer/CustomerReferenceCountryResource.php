<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Customer;

use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Country
 */
final class CustomerReferenceCountryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name_ar !== '' ? (string) $this->name_ar : (string) ($this->name_en ?? ''),
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'iso_code' => (string) $this->code,
            // Backward-compatible alias of ISO for existing Flutter clients.
            'code' => (string) $this->code,
            'calling_code' => $this->calling_code,
            'sort_order' => (int) ($this->sort_order ?? 0),
            'is_active' => (bool) $this->is_active,
            'is_delivery_active' => (bool) $this->is_delivery_active,
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
