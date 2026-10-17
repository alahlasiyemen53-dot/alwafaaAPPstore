<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentFlowType: string
{
    case None = 'none';
    case Step1 = 'step1';
    case Step2 = 'step2';

    public function label(): string
    {
        return match ($this) {
            self::None => 'بدون خطوات إضافية',
            self::Step1 => 'خطوة واحدة (مع الطلب)',
            self::Step2 => 'خطوتان (فاتورة معلّقة + OTP)',
        };
    }
}
