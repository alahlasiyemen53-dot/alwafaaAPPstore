<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case PhoneVerification = 'phone_verification';
    case Login = 'login';

    public function label(): string
    {
        return match ($this) {
            self::PhoneVerification => 'التحقق من رقم الهاتف',
            self::Login => 'تسجيل الدخول',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::PhoneVerification => 'bg-info-subtle text-info border border-info-subtle',
            self::Login => 'bg-primary-subtle text-primary border border-primary-subtle',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::PhoneVerification => 'ri-phone-check-line',
            self::Login => 'ri-login-box-line',
        };
    }
}
