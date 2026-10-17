<?php

namespace App\Contracts;

use App\Enums\OtpPurpose;
use App\ValueObjects\OtpSendResult;

interface OtpSenderInterface
{
    /**
     * Send OTP code to the given normalized phone number.
     */
    public function send(string $normalizedPhone, string $code, OtpPurpose $purpose): OtpSendResult;
}
