<?php

namespace App\Enums;

enum CustomerWalletEntryType: string
{
    case RefundCredit = 'refund_credit';
    case ManualCredit = 'manual_credit';
    case ManualDebit = 'manual_debit';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::RefundCredit => 'استرداد مرتجع مبيعات',
            self::ManualCredit => 'تسوية يدوية (إيداع/شحن)',
            self::ManualDebit => 'تسوية يدوية (خصم)',
            self::Reversal => 'عكس حركة سابقة',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::RefundCredit, self::ManualCredit], true);
    }

    public function isDebit(): bool
    {
        return in_array($this, [self::ManualDebit], true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::RefundCredit => 'badge bg-success-subtle text-success border border-success-subtle',
            self::ManualCredit => 'badge bg-primary-subtle text-primary border border-primary-subtle',
            self::ManualDebit => 'badge bg-danger-subtle text-danger border border-danger-subtle',
            self::Reversal => 'badge bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
