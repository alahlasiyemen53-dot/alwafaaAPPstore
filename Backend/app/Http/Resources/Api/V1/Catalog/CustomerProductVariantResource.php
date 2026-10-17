<?php

declare(strict_types=1);

namespace App\Http\Resources\Api\V1\Catalog;

use App\Enums\ProductVariantFulfillmentMode;
use App\Models\ProductVariant;
use App\Support\ProductSupplierResolver;
use App\Models\VariantPrice;
use App\Support\Decimal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @mixin ProductVariant
 */
final class CustomerProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var VariantPrice|null $priceRecord */
        $priceRecord = $this->relationLoaded('prices')
            ? $this->prices->firstWhere('is_active', true)
            : $this->prices()->where('is_active', true)->first();

        $priceAmount = $priceRecord ? Decimal::format((string) $priceRecord->price, 2) : '0.00';
        $compareAtAmount = ($priceRecord && $priceRecord->compare_at_price)
            ? Decimal::format((string) $priceRecord->compare_at_price, 2)
            : null;

        $hasDiscount = $compareAtAmount !== null && bccomp($compareAtAmount, $priceAmount, 2) === 1;
        $discountPercentage = null;

        if ($hasDiscount && bccomp($compareAtAmount, '0.00', 2) === 1) {
            $diff = bcsub($compareAtAmount, $priceAmount, 2);
            $fraction = bcdiv($diff, $compareAtAmount, 4);
            $discountPercentage = (float) bcmul($fraction, '100', 1);
        }

        // Customer-safe availability
        $mode = $this->fulfillment_mode ?? ProductVariantFulfillmentMode::Stocked;
        $isAvailable = false;
        $status = 'out_of_stock';

        if ($mode === ProductVariantFulfillmentMode::Stocked) {
            $totalStock = 0.0;
            if ($this->relationLoaded('inventories')) {
                foreach ($this->inventories as $inv) {
                    $avail = max(0.0, (float) $inv->quantity - (float) $inv->reserved_quantity);
                    $totalStock += $avail;
                }
            }
            $isAvailable = $totalStock > 0.0;
            $status = $isAvailable ? 'in_stock' : 'out_of_stock';
        } else {
            $supplier = ProductSupplierResolver::forVariant($this->resource);
            $isAvailable = $supplier !== null && (bool) $supplier->is_active;
            $status = $isAvailable ? 'on_demand' : 'out_of_stock';
        }

        // Attribute options
        $attributes = [];
        if ($this->relationLoaded('attributeValues')) {
            foreach ($this->attributeValues as $val) {
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

        // Media images
        $images = [];
        if ($this->relationLoaded('media')) {
            /** @var Media $media */
            foreach ($this->getMedia('variant_images') as $media) {
                $images[] = [
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

        return [
            'id' => $this->id,
            'sku' => $this->sku,
            'is_default' => (bool) $this->is_default,
            'fulfillment_mode' => $mode->value,
            'availability' => [
                'is_available' => $isAvailable,
                'status' => $status,
            ],
            'price' => [
                'amount' => $priceAmount,
                'compare_at' => $compareAtAmount,
                'currency' => ($priceRecord && $priceRecord->relationLoaded('currency') && $priceRecord->currency) ? $priceRecord->currency->code : 'YER',
                'has_discount' => $hasDiscount,
                'discount_percentage' => $discountPercentage,
            ],
            'attributes' => $attributes,
            'images' => $images,
        ];
    }
}
