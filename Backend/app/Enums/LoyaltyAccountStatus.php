<?php

namespace App\Enums;

enum LoyaltyAccountStatus: string
{
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'نشط',
            self::Suspended => 'معلق',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Active => 'badge bg-success-subtle text-success border border-success-subtle',
            self::Suspended => 'badge bg-danger-subtle text-danger border border-danger-subtle',
        };
    }

    public function isActive(): bool
    {
        return $this === self::Active;
    }
}
