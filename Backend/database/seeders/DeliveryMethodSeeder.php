<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\DeliveryMethod;
use App\Support\GovernorateDeliveryMethodBackfill;
use Illuminate\Database\Seeder;

final class DeliveryMethodSeeder extends Seeder
{
    public function run(): void
    {
        // price is a legacy default only; governorate-specific fees live on governorate_delivery_methods.
        $methods = [
            [
                'code' => 'standard',
                'name_ar' => 'توصيل قياسي',
                'name_en' => 'Standard Delivery',
                'price' => 1500,
                'estimated_delivery_ar' => 'خلال 24-48 ساعة',
                'estimated_delivery_en' => 'Within 24-48 hours',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'code' => 'express',
                'name_ar' => 'توصيل سريع',
                'name_en' => 'Express Delivery',
                'price' => 3000,
                'estimated_delivery_ar' => 'خلال 12-24 ساعة',
                'estimated_delivery_en' => 'Within 12-24 hours',
                'sort_order' => 2,
                'is_active' => true,
            ],
            [
                'code' => 'same_day',
                'name_ar' => 'توصيل في نفس اليوم',
                'name_en' => 'Same Day Delivery',
                'price' => 5000,
                'estimated_delivery_ar' => 'خلال نفس اليوم',
                'estimated_delivery_en' => 'Same day',
                'sort_order' => 3,
                'is_active' => true,
            ],
        ];

        foreach ($methods as $method) {
            DeliveryMethod::query()->updateOrCreate(
                ['code' => $method['code']],
                $method
            );
        }

        GovernorateDeliveryMethodBackfill::run();
    }
}
