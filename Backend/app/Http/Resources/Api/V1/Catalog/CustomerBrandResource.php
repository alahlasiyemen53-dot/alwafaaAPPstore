<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Catalog;

use App\Models\Brand;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Brand */
final class CustomerBrandResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'description' => $this->description,
            'logo' => $this->getFirstMediaUrl('brand_logo', 'small')
                ? ['url' => $this->getFirstMediaUrl('brand_logo', 'small')]
                : null,
        ];
    }
}
