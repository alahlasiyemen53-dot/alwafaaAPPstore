<?php

namespace App\Enums;

enum LoyaltyEntryType: string
{
    case Earn = 'earn';
    case Redeem = 'redeem';
    case Clawback = 'clawback';
    case AdjustmentCredit = 'adjustment_credit';
    case AdjustmentDebit = 'adjustment_debit';
    case Reversal = 'reversal';

    public function label(): string
    {
        return match ($this) {
            self::Earn => 'اكتساب نقاط',
            self::Redeem => 'استبدال نقاط',
            self::Clawback => 'استرداد نقاط لمرتجع',
            self::AdjustmentCredit => 'تسوية يدوية (إضافة)',
            self::AdjustmentDebit => 'تسوية يدوية (خصم)',
            self::Reversal => 'عكس حركة',
        };
    }

    public function isCredit(): bool
    {
        return in_array($this, [self::Earn, self::AdjustmentCredit], true);
    }

    public function isDebit(): bool
    {
        return in_array($this, [self::Redeem, self::Clawback, self::AdjustmentDebit], true);
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Earn => 'badge bg-success-subtle text-success border border-success-subtle',
            self::Redeem => 'badge bg-info-subtle text-info border border-info-subtle',
            self::Clawback => 'badge bg-warning-subtle text-warning border border-warning-subtle',
            self::AdjustmentCredit => 'badge bg-primary-subtle text-primary border border-primary-subtle',
            self::AdjustmentDebit => 'badge bg-danger-subtle text-danger border border-danger-subtle',
            self::Reversal => 'badge bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
