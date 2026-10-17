<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Home;

use App\Enums\BannerActionType;
use App\Models\HomeBanner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin HomeBanner
 */
final class CustomerHomeBannerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $destination = null;
        if ($this->action_type !== BannerActionType::None && $this->action_reference !== null) {
            $entity = $this->relationLoaded('customerDestination')
                ? $this->getRelation('customerDestination')
                : $this->getDestinationEntity();
            if ($entity !== null) {
                $destination = match ($this->action_type) {
                    BannerActionType::Product => [
                        'type' => 'product',
                        'id' => $entity->id,
                        'name_ar' => $entity->name_ar,
                        'slug' => $entity->slug,
                    ],
                    BannerActionType::Category => [
                        'type' => 'category',
                        'id' => $entity->id,
                        'name_ar' => $entity->name_ar,
                        'slug' => $entity->slug,
                    ],
                    BannerActionType::Promotion => [
                        'type' => 'promotion',
                        'id' => $entity->id,
                        'name' => $entity->name,
                    ],
                    default => null,
                };
            }
        }

        return [
            'id' => $this->id,
            'title_ar' => $this->title_ar,
            'title_en' => $this->title_en,
            'subtitle_ar' => $this->subtitle_ar,
            'subtitle_en' => $this->subtitle_en,
            'image_url' => $this->getBannerImageUrl('medium'),
            'sort_order' => (int) $this->sort_order,
            'action_type' => $this->action_type instanceof BannerActionType ? $this->action_type->value : (string) $this->action_type,
            'action_reference' => $this->action_reference,
            'destination' => $destination,
        ];
    }
}
