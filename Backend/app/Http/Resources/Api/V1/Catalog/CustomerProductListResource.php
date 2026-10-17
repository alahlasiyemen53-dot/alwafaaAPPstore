<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Catalog;

use App\Enums\ProductVariantFulfillmentMode;
use App\Models\Product;
use App\Support\Decimal;
use App\Support\ProductSupplierResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class CustomerProductListResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $minPrice = $this->min_price !== null ? Decimal::format((string) $this->min_price, 2) : '0.00';
        $maxPrice = $this->max_price !== null ? Decimal::format((string) $this->max_price, 2) : $minPrice;
        $hasRange = bccomp($maxPrice, $minPrice, 2) === 1;

        // Calculate aggregate availability across loaded active variants
        $isAvailable = false;
        $hasInStock = false;
        $hasOnDemand = false;
        $defaultVariantId = null;
        $fallbackVariantId = null;

        if ($this->relationLoaded('variants')) {
            foreach ($this->variants as $variant) {
                $fallbackVariantId ??= (int) $variant->id;
                $mode = $variant->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked;
                if ($mode === ProductVariantFulfillmentMode::Stocked) {
                    $availStock = 0.0;
                    if ($variant->relationLoaded('inventories')) {
                        foreach ($variant->inventories as $inv) {
                            $availStock += max(0.0, (float) $inv->quantity - (float) $inv->reserved_quantity);
                        }
                    }
                    if ($availStock > 0.0) {
                        $hasInStock = true;
                        $isAvailable = true;
                        $defaultVariantId ??= (int) $variant->id;
                    }
                } elseif ($mode === ProductVariantFulfillmentMode::OnDemand) {
                    $supplier = ProductSupplierResolver::forVariant($variant);
                    if ($supplier !== null && (bool) $supplier->is_active) {
                        $hasOnDemand = true;
                        $isAvailable = true;
                        $defaultVariantId ??= (int) $variant->id;
                    }
                }
            }
        }

        $defaultVariantId ??= $fallbackVariantId;

        $availabilityStatus = 'out_of_stock';
        if ($hasInStock) {
            $availabilityStatus = 'in_stock';
        } elseif ($hasOnDemand) {
            $availabilityStatus = 'on_demand';
        }

        $primaryImage = null;
        $primaryMedia = $this->getPrimaryMedia();
        if ($primaryMedia) {
            $primaryImage = $primaryMedia->hasGeneratedConversion('small')
                ? $primaryMedia->getUrl('small')
                : $primaryMedia->getUrl();
        }

        return [
            'id' => $this->id,
            'name_ar' => $this->name_ar,
            'name_en' => $this->name_en,
            'slug' => $this->slug,
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
            ] : null),
            'primary_image' => $primaryImage,
            'default_variant_id' => $defaultVariantId,
            'price_summary' => [
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'currency' => 'YER',
                'has_range' => $hasRange,
            ],
            'availability' => [
                'is_available' => $isAvailable,
                'status' => $availabilityStatus,
            ],
            'rating' => [
                'average' => $this->approved_reviews_avg_rating !== null
                    ? round((float) $this->approved_reviews_avg_rating, 1)
                    : 0.0,
                'reviews_count' => (int) ($this->approved_reviews_count ?? 0),
            ],
            'badges' => [
                'is_best_seller' => (bool) $this->is_best_seller,
                'is_new' => (bool) $this->is_new,
                'is_exclusive' => (bool) $this->is_exclusive,
            ],
            'share_url' => app(\App\Services\DeepLinkService::class)->productUrl($this->id),
        ];
    }
}
