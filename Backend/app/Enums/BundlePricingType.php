<?php

declare(strict_types=1);

namespace App\Enums;

enum BundlePricingType: string
{
    case FixedBundlePrice = 'fixed_bundle_price';
    case PercentageDiscount = 'percentage_discount';
    case FixedAmountDiscount = 'fixed_amount_discount';

    public function label(): string
    {
        return match ($this) {
            self::FixedBundlePrice => 'سعر إجمالي محدد للباقة',
            self::PercentageDiscount => 'نسبة خصم على مجموع مكونات الباقة (%)',
            self::FixedAmountDiscount => 'مبلغ خصم ثابت على الباقة',
        };
    }
}
