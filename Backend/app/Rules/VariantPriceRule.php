<?php

namespace App\Rules;

final class VariantPriceRule
{
    public static function passes(int|float $price, int|float|null $compareAtPrice): bool
    {
        return $price >= 0 && ($compareAtPrice === null || $compareAtPrice > $price);
    }
}
