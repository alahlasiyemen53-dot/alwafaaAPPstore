<?php

namespace App\Services;

use App\Data\MediaItemData;
use App\Models\Product;
use App\Models\ProductVariant;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class CatalogMediaPresenter
{
    /**
     * @return array<int, MediaItemData>
     */
    public function presentProduct(Product $product): array
    {
        return $product->getMedia('product_images')
            ->sortBy('order_column')
            ->values()
            ->map(fn (Media $media) => MediaItemData::fromMedia($media))
            ->all();
    }

    /**
     * @return array<int, MediaItemData>
     */
    public function presentVariant(ProductVariant $variant): array
    {
        return $variant->getMedia('variant_images')
            ->sortBy('order_column')
            ->values()
            ->map(fn (Media $media) => MediaItemData::fromMedia($media))
            ->all();
    }

    public function getPrimaryUrlForProduct(Product $product, string $conversion = ''): string
    {
        return $product->getPrimaryImageUrl($conversion) ?? $this->getDefaultPlaceholderUrl();
    }

    public function getPrimaryUrlForVariant(ProductVariant $variant, string $conversion = ''): string
    {
        return $variant->getPrimaryImageUrl($conversion) ?? $this->getDefaultPlaceholderUrl();
    }

    public function getDefaultPlaceholderUrl(): string
    {
        return asset('assets/images/placeholder.svg');
    }
}
