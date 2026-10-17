<?php

namespace App\Enums;

enum AccountingPeriodStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'مفتوحة',
            self::Closed => 'مغلقة',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Open => 'bg-success-subtle text-success',
            self::Closed => 'bg-danger-subtle text-danger',
        };
    }
}
