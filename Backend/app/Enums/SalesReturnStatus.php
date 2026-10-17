<?php

namespace App\Enums;

enum SalesReturnStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Posted => 'معتمد ومستلم',
            self::Cancelled => 'ملغي',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'badge bg-warning-subtle text-warning border border-warning-subtle',
            self::Posted => 'badge bg-success-subtle text-success border border-success-subtle',
            self::Cancelled => 'badge bg-danger-subtle text-danger border border-danger-subtle',
        };
    }
}
