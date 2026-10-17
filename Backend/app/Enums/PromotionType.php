<?php

declare(strict_types=1);

namespace App\Enums;

enum PromotionType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';
    case FixedPrice = 'fixed_price';

    public function label(): string
    {
        return match ($this) {
            self::Percentage => 'نسبة مئوية (%)',
            self::FixedAmount => 'مبلغ خصم ثابت',
            self::FixedPrice => 'سعر بيع مخفض ثابت',
        };
    }
}
