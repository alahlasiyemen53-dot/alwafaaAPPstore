<?php

declare(strict_types=1);

namespace App\Enums;

enum DriverCodSettlementStatus: string
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
            self::Draft => 'badge bg-warning-subtle text-warning',
            self::Posted => 'badge bg-success-subtle text-success',
            self::Reversed => 'badge bg-danger-subtle text-danger',
        };
    }

    public function isDraft(): bool
    {
        return $this === self::Draft;
    }

    public function isPosted(): bool
    {
        return $this === self::Posted;
    }

    public function isReversed(): bool
    {
        return $this === self::Reversed;
    }

    public function canBePosted(): bool
    {
        return $this === self::Draft;
    }

    public function canBeReversed(): bool
    {
        return $this === self::Posted;
    }
}
