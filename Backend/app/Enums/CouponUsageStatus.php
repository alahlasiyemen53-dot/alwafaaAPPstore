<?php

declare(strict_types=1);

namespace App\Enums;

enum CouponUsageStatus: string
{
    case Active = 'active';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'مستهلك (نشط)',
            self::Released => 'محرر (ملغي)',
        };
    }
}
