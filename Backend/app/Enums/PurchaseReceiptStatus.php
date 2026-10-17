<?php

namespace App\Enums;

enum PurchaseReceiptStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Posted => 'مرحّل',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Posted => 'bg-success-subtle text-success border border-success-subtle',
        };
    }
}
