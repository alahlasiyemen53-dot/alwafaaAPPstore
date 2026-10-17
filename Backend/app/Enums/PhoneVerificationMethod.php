<?php

namespace App\Enums;

enum PhoneVerificationMethod: string
{
    case Otp = 'otp';
    case Admin = 'admin';

    public function label(): string
    {
        return match ($this) {
            self::Otp => 'رمز التحقق (OTP)',
            self::Admin => 'يدوي عبر المشرف (ADMIN)',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Otp => 'bg-info-subtle text-info border border-info-subtle',
            self::Admin => 'bg-primary-subtle text-primary border border-primary-subtle',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Otp => 'ri-shield-keyhole-line',
            self::Admin => 'ri-user-star-line',
        };
    }
}
