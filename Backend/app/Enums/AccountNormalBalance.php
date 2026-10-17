<?php

namespace App\Enums;

enum AccountNormalBalance: string
{
    case Debit = 'debit';
    case Credit = 'credit';

    public function label(): string
    {
        return match ($this) {
            self::Debit => 'مدين',
            self::Credit => 'دائن',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Debit => 'bg-primary-subtle text-primary',
            self::Credit => 'bg-secondary-subtle text-secondary',
        };
    }
}
