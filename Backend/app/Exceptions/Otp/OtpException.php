<?php

namespace App\Exceptions\Otp;

use Exception;

class OtpException extends Exception
{
    public function __construct(string $message = 'خطأ في عملية التحقق من الرمز.', int $code = 400)
    {
        parent::__construct($message, $code);
    }
}
