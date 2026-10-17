<?php

namespace App\Enums;

enum PurchaseInvoiceStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Posted => 'مرحّلة',
            self::Cancelled => 'ملغاة',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Posted => 'bg-success-subtle text-success border border-success-subtle',
            self::Cancelled => 'bg-danger-subtle text-danger border border-danger-subtle',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Draft => in_array($target, [self::Posted, self::Cancelled], true),
            self::Posted => $target === self::Cancelled,
            self::Cancelled => false,
        };
    }
}
