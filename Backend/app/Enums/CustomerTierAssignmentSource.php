<?php

namespace App\Enums;

enum CustomerTierAssignmentSource: string
{
    case SYSTEM_DEFAULT = 'SYSTEM_DEFAULT';
    case ADMIN = 'ADMIN';

    public function label(): string
    {
        return match ($this) {
            self::SYSTEM_DEFAULT => 'افتراضي من النظام',
            self::ADMIN => 'بواسطة الإدارة',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::SYSTEM_DEFAULT => 'bg-secondary-subtle text-secondary',
            self::ADMIN => 'bg-primary-subtle text-primary',
        };
    }
}
