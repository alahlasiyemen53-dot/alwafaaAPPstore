<?php

namespace App\Exceptions\Otp;

final class ExpiredOtpException extends OtpException
{
    public function __construct(string $message = 'انتهت صلاحية رمز التحقق. يرجى طلب رمز جديد.')
    {
        parent::__construct($message, 410);
    }
}
