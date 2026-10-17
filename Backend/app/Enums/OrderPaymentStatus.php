<?php

declare(strict_types=1);

namespace App\Enums;

enum OrderPaymentStatus: string
{
    case Unpaid = 'unpaid';
    case PendingOtp = 'pending_otp';
    case Paid = 'paid';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'غير مدفوع',
            self::PendingOtp => 'بانتظار OTP',
            self::Paid => 'مدفوع',
            self::Failed => 'فشل الدفع',
        };
    }

    public function badgeClass(): string
    {
        return match ($this) {
            self::Paid => 'bg-success-subtle text-success border border-success-subtle',
            self::PendingOtp => 'bg-warning-subtle text-warning border border-warning-subtle',
            self::Failed => 'bg-danger-subtle text-danger border border-danger-subtle',
            self::Unpaid => 'bg-secondary-subtle text-secondary border border-secondary-subtle',
        };
    }
}
