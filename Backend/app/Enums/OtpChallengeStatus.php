<?php

namespace App\Enums;

enum OtpChallengeStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Exhausted = 'exhausted';
    case Expired = 'expired';
    case Superseded = 'superseded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'قيد الانتظار',
            self::Verified => 'تم التحقق بنجاح',
            self::Exhausted => 'استنفد المحاولات',
            self::Expired => 'منتهي الصلاحية',
            self::Superseded => 'مستبدل برمز أحدث',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Pending => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Verified => 'bg-success-subtle text-success border border-success-subtle',
            self::Exhausted => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::Expired => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
            self::Superseded => 'bg-light text-muted border',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Pending => 'ri-time-line',
            self::Verified => 'ri-checkbox-circle-line',
            self::Exhausted => 'ri-close-circle-line',
            self::Expired => 'ri-timer-line',
            self::Superseded => 'ri-refresh-line',
        };
    }

    public function isPending(): bool
    {
        return $this === self::Pending;
    }

    public function isVerified(): bool
    {
        return $this === self::Verified;
    }

    public function isTerminal(): bool
    {
        return $this !== self::Pending;
    }
}
