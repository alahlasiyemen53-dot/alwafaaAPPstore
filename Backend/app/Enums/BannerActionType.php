<?php

namespace App\Enums;

enum BannerActionType: string
{
    case None = 'none';
    case Product = 'product';
    case Category = 'category';
    case Promotion = 'promotion';

    public function label(): string
    {
        return match ($this) {
            self::None => 'بدون وجهة (عرض فقط)',
            self::Product => 'منتج محدد',
            self::Category => 'تصنيف منتجات',
            self::Promotion => 'عرض ترويجي',
        };
    }
}
