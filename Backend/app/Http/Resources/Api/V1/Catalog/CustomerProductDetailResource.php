<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Catalog;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property array<string, mixed> $rating_summary
 *
 * @mixin Product
 */
final class CustomerProductDetailResource extends JsonResource
{
    /**
     * @var array<string, mixed>
     */
    protected array $ratingSummaryData = [];

    /**
     * @param  array<string, mixed>  $ratingSummary
     */
    public function withRatingSummary(array $ratingSummary): self
    {
        $this->ratingSummaryData = $ratingSummary;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Media gallery
        $mediaGallery = [];
        if ($this->relationLoaded('media')) {
            /** @var Media $media */
            foreach ($this->getMedia('product_images') as $media) {
                $mediaGallery[] = [
                    'id' => $media->id,
                    'url' => $media->getUrl(),
                    'thumb' => $media->hasGeneratedConversion('thumb') ? $media->getUrl('thumb') : $media->getUrl(),
                    'small' => $media->hasGeneratedConversion('small') ? $media->getUrl('small') : $media->getUrl(),
                    'medium' => $media->hasGeneratedConversion('medium') ? $media->getUrl('medium') : $media->getUrl(),
                    'large' => $media->hasGeneratedConversion('large') ? $media->getUrl('large') : $media->getUrl(),
                    'is_primary' => (bool) $media->getCustomProperty('is_primary', false),
                ];
            }
        }

        // Product-level aggregated attributes from variants
        $attributes = [];
        if ($this->relationLoaded('variants')) {
            $seen = [];
            foreach ($this->variants as $variant) {
                if ($variant->relationLoaded('attributeValues')) {
                    foreach ($variant->attributeValues as $val) {
                        $key = $val->attribute_id.'_'.$val->id;
                        if (! isset($seen[$key])) {
                            $seen[$key] = true;
                            $attributes[] = [
                                'attribute_id' => $val->attribute_id,
                                'name_ar' => $val->attribute?->name_ar,
                                'name_en' => $val->attribute?->name_en,
                                'code' => $val->attribute?->code,
                                'value_ar' => $val->value_ar,
                                'value_en' => $val->value_en,
                            ];
                        }
                    }
                }
            }
        }

        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
            'description_ar' => $this->description_ar,
            'description_en' => $this->description_en,
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? [
                'id' => $this->brand->id,
                'name_ar' => $this->brand->name_ar,
                'name_en' => $this->brand->name_en,
                'slug' => $this->brand->slug,
                'logo' => $this->brand->getFirstMediaUrl('brand_logo', 'small')
                    ? ['url' => $this->brand->getFirstMediaUrl('brand_logo', 'small')]
                    : null,
            ] : null),
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id' => $this->category->id,
                'name_ar' => $this->category->name_ar,
                'name_en' => $this->category->name_en,
                'slug' => $this->category->slug,
                'parent_id' => $this->category->parent_id,
            ] : null),
            'media_gallery' => $mediaGallery,
            'rating_summary' => ! empty($this->ratingSummaryData) ? $this->ratingSummaryData : [
                'average_rating' => 0.0,
                'reviews_count' => 0,
                'distribution' => [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0],
            ],
            'badges' => [
                'is_best_seller' => (bool) $this->is_best_seller,
                'is_new' => (bool) $this->is_new,
                'is_exclusive' => (bool) $this->is_exclusive,
            ],
            'share_url' => app(\App\Services\DeepLinkService::class)->productUrl($this->id),
            'attributes' => $attributes,
            'variants' => CustomerProductVariantResource::collection($this->whenLoaded('variants')),
        ];
    }
}
