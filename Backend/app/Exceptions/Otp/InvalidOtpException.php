<?php

namespace App\Exceptions\Otp;

final class InvalidOtpException extends OtpException
{
    public function __construct(
        public readonly int $attemptsRemaining,
        string $message = 'رمز التحقق المدخل غير صحيح.',
    ) {
        parent::__construct($message, 422);
    }
}
