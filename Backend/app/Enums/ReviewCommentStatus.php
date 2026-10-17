<?php

namespace App\Enums;

enum ReviewCommentStatus: string
{
    case Approved = 'approved';
    case Hidden = 'hidden';

    public function label(): string
    {
        return match ($this) {
            self::Approved => 'ظاهر',
            self::Hidden => 'مخفي',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Approved => 'bg-success-subtle text-success border border-success-subtle',
            self::Hidden => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Approved;
    }

    public function canTransitionTo(self $target): bool
    {
        if ($this === $target) {
            return false;
        }

        return match ($this) {
            self::Approved => $target === self::Hidden,
            self::Hidden => $target === self::Approved,
        };
    }
}
