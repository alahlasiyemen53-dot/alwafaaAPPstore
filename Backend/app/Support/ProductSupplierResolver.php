<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\ProductVariant;
use App\Models\Supplier;

final class ProductSupplierResolver
{
    public static function forVariant(ProductVariant $variant): ?Supplier
    {
        $variant->loadMissing(['product.supplier', 'supplier']);

        $productSupplier = $variant->product?->supplier;
        if ($productSupplier !== null) {
            return $productSupplier;
        }

        return $variant->supplier;
    }

    public static function idForVariant(ProductVariant $variant): ?int
    {
        return self::forVariant($variant)?->id;
    }
}
