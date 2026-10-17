<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponDiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'نسبة مئوية (%)',
            self::FixedAmount => 'مبلغ خصم ثابت',
        };
    }
}
