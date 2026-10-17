<?php

namespace App\Exceptions\Otp;

final class OtpCooldownException extends OtpException
{
    public function __construct(
        public readonly int $secondsRemaining,
        string $message = 'يرجى الانتظار قبل طلب رمز تحقق جديد.',
    ) {
        parent::__construct($message, 429);
    }
}
