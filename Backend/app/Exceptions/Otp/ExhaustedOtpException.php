<?php

namespace App\Exceptions\Otp;

final class ExhaustedOtpException extends OtpException
{
    public function __construct(string $message = 'تم تجاوز الحد الأقصى للمحاولات الخاطئة. تم إيقاف هذا الرمز، يرجى طلب رمز جديد.')
    {
        parent::__construct($message, 429);
    }
}
