<?php

declare(strict_types=1);

namespace App\Enums;

enum HomeSectionType: string
{
    case Banners = 'banners';
    case Categories = 'categories';
    case Brands = 'brands';
    case BestSellers = 'best_sellers';
    case NewProducts = 'new_products';
    case ExclusiveProducts = 'exclusive_products';
    case Collection = 'collection';
    case RecentlyViewed = 'recently_viewed';

    public function labelAr(): string
    {
        return match ($this) {
            self::Banners => 'البنرات',
            self::Categories => 'التصنيفات',
            self::Brands => 'العلامات التجارية',
            self::BestSellers => 'الأكثر مبيعاً',
            self::NewProducts => 'وصل حديثاً',
            self::ExclusiveProducts => 'حصريات الوفاء استور',
            self::Collection => 'مجموعة منتجات',
            self::RecentlyViewed => 'شاهدتها مؤخراً',
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
