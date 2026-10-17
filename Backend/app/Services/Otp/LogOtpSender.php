<?php

namespace App\Services\Otp;

use App\Contracts\OtpSenderInterface;
use App\Enums\OtpPurpose;
use App\ValueObjects\OtpSendResult;
use Illuminate\Support\Facades\Log;

final class LogOtpSender implements OtpSenderInterface
{
    public function send(string $normalizedPhone, string $code, OtpPurpose $purpose): OtpSendResult
    {
        $maskedPhone = strlen($normalizedPhone) > 6
            ? substr($normalizedPhone, 0, 4).'****'.substr($normalizedPhone, -3)
            : '****';

        // Redacted to prevent plaintext OTP leakage in production logs
        Log::info('OTP dispatch requested', [
            'provider' => 'log',
            'masked_phone' => $maskedPhone,
            'purpose' => $purpose->value,
            'code' => app()->isLocal() ? $code : '[REDACTED]',
        ]);

        return OtpSendResult::success('log', 'log_'.uniqid());
    }
}
