<?php

namespace App\Enums;

enum ProductReviewStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار المراجعة',
            self::Approved => 'معتمد',
            self::Rejected => 'مرفوض',
            self::Hidden => 'مخفي',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Approved => 'bg-success-subtle text-success border border-success-subtle',
            self::Rejected => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::Hidden => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::Pending => in_array($target, [self::Approved, self::Rejected], true),
            self::Approved => $target === self::Hidden,
            self::Rejected => $target === self::Pending,
            self::Hidden => $target === self::Approved,
        };
    }
}
