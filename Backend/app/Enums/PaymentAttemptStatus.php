<?php

declare(strict_types=1);

namespace App\Enums;

enum PaymentAttemptStatus: string
{
    case PendingOtp = 'pending_otp';
    case Paid = 'paid';
    case Failed = 'failed';
    case Expired = 'expired';
}
