<?php

namespace App\Enums;

enum JournalEntryStatus: string
{
    case Draft = 'draft';
    case Posted = 'posted';
    case Reversed = 'reversed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'مسودة',
            self::Posted => 'مرحل',
            self::Reversed => 'معكوس',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Draft => 'bg-warning-subtle text-warning',
            self::Posted => 'bg-success-subtle text-success',
            self::Reversed => 'bg-danger-subtle text-danger',
        };
    }
}
