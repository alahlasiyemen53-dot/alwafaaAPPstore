<?php

namespace App\Actions\Otp;

use App\Contracts\OtpSenderInterface;
use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Exceptions\Otp\OtpCooldownException;
use App\Models\CustomerOtpChallenge;
use App\Services\AuditLogger;
use App\Support\CountryCallingCode;
use App\Support\DemoCustomerOtp;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class RequestCustomerOtpAction
{
    public function __construct(
        private readonly OtpSenderInterface $otpSender,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(
        string $phone,
        OtpPurpose $purpose = OtpPurpose::PhoneVerification,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?int $countryId = null,
    ): CustomerOtpChallenge {
        $callingCode = CountryCallingCode::forCountryId($countryId);
        $normalizedPhone = PhoneNormalizer::normalize($phone, $callingCode);
        if ($normalizedPhone === null || ! PhoneNormalizer::isValid($phone, $countryId)) {
            throw new InvalidArgumentException('رقم الهاتف المدخل غير صالح.');
        }

        $cooldownSeconds = (int) config('otp.resend_cooldown_seconds', 60);
        $expirySeconds = (int) config('otp.expiry_seconds', 300);
        $codeLength = (int) config('otp.code_length', 6);
        $maxAttempts = (int) config('otp.max_attempts', 5);

        return DB::transaction(function () use (
            $phone,
            $normalizedPhone,
            $purpose,
            $ipAddress,
            $userAgent,
            $cooldownSeconds,
            $expirySeconds,
            $codeLength,
            $maxAttempts,
        ): CustomerOtpChallenge {
            // Check active pending challenge for cooldown
            /** @var CustomerOtpChallenge|null $latestPending */
            $latestPending = CustomerOtpChallenge::query()
                ->where('normalized_phone', $normalizedPhone)
                ->where('purpose', $purpose->value)
                ->where('status', OtpChallengeStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->whereNull('consumed_at')
                ->lockForUpdate()
                ->latest('id')
                ->first();

            $isDemoPhone = DemoCustomerOtp::matchesNormalizedPhone($normalizedPhone);

            if ($isDemoPhone) {
                DemoCustomerOtp::ensureCustomer();
            }

            if (! $isDemoPhone && $latestPending !== null && $latestPending->last_sent_at !== null) {
                $cooldownExpiresAt = $latestPending->last_sent_at->copy()->addSeconds($cooldownSeconds);
                if ($cooldownExpiresAt->isFuture()) {
                    $remaining = max(1, (int) ceil(now()->diffInSeconds($cooldownExpiresAt)));
                    throw new OtpCooldownException(
                        secondsRemaining: $remaining,
                        message: "يرجى الانتظار {$remaining} ثانية قبل طلب رمز تحقق جديد."
                    );
                }
            }

            // Single active challenge policy: supersede any existing pending challenges
            CustomerOtpChallenge::query()
                ->where('normalized_phone', $normalizedPhone)
                ->where('purpose', $purpose->value)
                ->where('status', OtpChallengeStatus::Pending->value)
                ->lockForUpdate()
                ->update([
                    'status' => OtpChallengeStatus::Superseded->value,
                    'consumed_at' => now(),
                ]);

            // Demo phone always uses the fixed OTP; otherwise cryptographically secure code
            if ($isDemoPhone) {
                $code = DemoCustomerOtp::code();
            } else {
                $minVal = 0;
                $maxVal = (10 ** $codeLength) - 1;
                $randomDigits = random_int($minVal, $maxVal);
                $code = str_pad((string) $randomDigits, $codeLength, '0', STR_PAD_LEFT);
            }

            // Create challenge record with hashed code
            $challenge = CustomerOtpChallenge::query()->create([
                'challenge_id' => (string) Str::uuid(),
                'phone' => $phone,
                'normalized_phone' => $normalizedPhone,
                'purpose' => $purpose,
                'code_hash' => Hash::make($code),
                'status' => OtpChallengeStatus::Pending,
                'attempts' => 0,
                'max_attempts' => $maxAttempts,
                'expires_at' => now()->addSeconds($expirySeconds),
                'consumed_at' => null,
                'last_sent_at' => now(),
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent ? Str::limit($userAgent, 500) : null,
            ]);

            // Demo phone uses a fixed local OTP — skip WhatsApp/provider delivery.
            if (! $isDemoPhone) {
                $this->otpSender->send($normalizedPhone, $code, $purpose);
            }

            // Redacted audit logging (never logs cleartext OTP or hash)
            $this->auditLogger->record(
                action: 'otp.challenge.created',
                subject: $challenge,
                metadata: [
                    'challenge_id' => $challenge->challenge_id,
                    'masked_phone' => $challenge->masked_phone,
                    'purpose' => $purpose->value,
                    'expires_at' => $challenge->expires_at?->toIso8601String(),
                ]
            );

            return $challenge;
        });
    }
}
