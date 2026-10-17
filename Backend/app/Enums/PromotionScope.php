<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionScope: string
{
    case All = 'all';
    case Product = 'product';
    case Variant = 'variant';
    case Category = 'category';
    case Brand = 'brand';

    public function label(): string
    {
        return match ($this) {
            self::All => 'جميع المنتجات',
            self::Product => 'منتجات محددة',
            self::Variant => 'متغيرات منتج محددة',
            self::Category => 'أقسام/فئات محددة',
            self::Brand => 'علامات تجارية محددة',
        };
    }
}
