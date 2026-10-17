<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\HomeSectionType;
use App\Models\HomeSection;
use Illuminate\Database\Seeder;

final class HomeSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            [HomeSectionType::Banners, 'البنرات', 'Banners', 10],
            [HomeSectionType::Categories, 'التصنيفات', 'Categories', 20],
            [HomeSectionType::Brands, 'العلامات التجارية', 'Brands', 30],
            [HomeSectionType::BestSellers, 'الأكثر مبيعاً', 'Best Sellers', 40],
            [HomeSectionType::NewProducts, 'وصل حديثاً', 'New Arrivals', 50],
            [HomeSectionType::ExclusiveProducts, 'حصريات الوفاء استور', 'Exclusive', 60],
            [HomeSectionType::RecentlyViewed, 'شاهدتها مؤخراً', 'Recently Viewed', 70],
        ];

        foreach ($sections as [$type, $titleAr, $titleEn, $sort]) {
            HomeSection::query()->updateOrCreate(
                ['type' => $type->value],
                [
                    'title_ar' => $titleAr,
                    'title_en' => $titleEn,
                    'is_active' => true,
                    'sort_order' => $sort,
                    'configuration' => [],
                ],
            );
        }
    }
}
