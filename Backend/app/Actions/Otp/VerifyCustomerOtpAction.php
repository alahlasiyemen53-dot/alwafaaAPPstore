<?php

namespace App\Actions\Otp;

use App\Actions\VerifyCustomerPhoneAction;
use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Enums\PhoneVerificationMethod;
use App\Exceptions\Otp\ExhaustedOtpException;
use App\Exceptions\Otp\ExpiredOtpException;
use App\Exceptions\Otp\InvalidOtpException;
use App\Models\Customer;
use App\Models\CustomerOtpChallenge;
use App\Services\AuditLogger;
use App\Support\DemoCustomerOtp;
use App\Support\PhoneNormalizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class VerifyCustomerOtpAction
{
    public function __construct(
        private readonly VerifyCustomerPhoneAction $verifyCustomerPhoneAction,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function execute(string $challengeId, string $code): CustomerOtpChallenge
    {
        return $this->processVerification(function () use ($challengeId): ?CustomerOtpChallenge {
            /** @var CustomerOtpChallenge|null $challenge */
            return CustomerOtpChallenge::query()
                ->where('challenge_id', $challengeId)
                ->lockForUpdate()
                ->first();
        }, $code, 'رمز أو طلب التحقق غير موجود.');
    }

    public function executeByPhone(string $phone, string $code, OtpPurpose $purpose = OtpPurpose::PhoneVerification): CustomerOtpChallenge
    {
        $normalizedPhone = PhoneNormalizer::normalize($phone);

        return $this->processVerification(function () use ($normalizedPhone, $purpose): ?CustomerOtpChallenge {
            /** @var CustomerOtpChallenge|null $challenge */
            return CustomerOtpChallenge::query()
                ->where('normalized_phone', $normalizedPhone)
                ->where('purpose', $purpose->value)
                ->where('status', OtpChallengeStatus::Pending->value)
                ->lockForUpdate()
                ->latest('id')
                ->first();
        }, $code, 'لا يوجد رمز تحقق نشط لهذا الرقم.');
    }

    /**
     * @param  callable(): (?CustomerOtpChallenge)  $findChallenge
     */
    private function processVerification(callable $findChallenge, string $code, string $notFoundMessage): CustomerOtpChallenge
    {
        $challenge = null;
        $failureException = null;

        DB::transaction(function () use ($findChallenge, $code, $notFoundMessage, &$challenge, &$failureException): void {
            $loadedChallenge = $findChallenge();

            if ($loadedChallenge === null) {
                $failureException = new InvalidOtpException(0, $notFoundMessage);
                return;
            }

            $challenge = $loadedChallenge;

            // 1. Expiry Check
            if ($challenge->isExpired()) {
                $challenge->status = OtpChallengeStatus::Expired;
                $challenge->save();

                $this->auditLogger->record(
                    action: 'otp.verification.expired',
                    subject: $challenge,
                    metadata: ['challenge_id' => $challenge->challenge_id, 'masked_phone' => $challenge->masked_phone]
                );

                $failureException = new ExpiredOtpException();
                return;
            }

            // 2. Consumption / Single-use Check
            if ($challenge->consumed_at !== null || $challenge->status !== OtpChallengeStatus::Pending) {
                $failureException = new InvalidOtpException(0, 'تم استخدام أو إبطال رمز التحقق مسبقاً.');
                return;
            }

            // 3. Exhaustion Check
            if ($challenge->isExhausted()) {
                $challenge->status = OtpChallengeStatus::Exhausted;
                $challenge->save();
                $failureException = new ExhaustedOtpException();
                return;
            }

            // 4. Code Hash Comparison (demo phone also accepts the fixed OTP)
            $isMatch = Hash::check($code, $challenge->code_hash)
                || (
                    DemoCustomerOtp::matchesNormalizedPhone($challenge->normalized_phone)
                    && DemoCustomerOtp::matchesCode($code)
                );

            if (! $isMatch) {
                $challenge->attempts++;

                if ($challenge->attempts >= $challenge->max_attempts) {
                    $challenge->status = OtpChallengeStatus::Exhausted;
                    $challenge->save();

                    $this->auditLogger->record(
                        action: 'otp.challenge.exhausted',
                        subject: $challenge,
                        metadata: [
                            'challenge_id' => $challenge->challenge_id,
                            'masked_phone' => $challenge->masked_phone,
                            'total_attempts' => $challenge->attempts,
                        ]
                    );

                    $failureException = new ExhaustedOtpException();
                    return;
                }

                $challenge->save();
                $remaining = $challenge->max_attempts - $challenge->attempts;

                $this->auditLogger->record(
                    action: 'otp.verification.failed',
                    subject: $challenge,
                    metadata: [
                        'challenge_id' => $challenge->challenge_id,
                        'masked_phone' => $challenge->masked_phone,
                        'attempts' => $challenge->attempts,
                        'attempts_remaining' => $remaining,
                    ]
                );

                $failureException = new InvalidOtpException(
                    attemptsRemaining: $remaining,
                    message: "رمز التحقق غير صحيح. متبقي لديك {$remaining} محاولات."
                );
                return;
            }

            // 5. Successful Verification
            $challenge->status = OtpChallengeStatus::Verified;
            $challenge->consumed_at = now();
            $challenge->save();

            // 6. Integration: If PhoneVerification, mark Customer verified via authoritative domain action
            if ($challenge->purpose === OtpPurpose::PhoneVerification) {
                /** @var Customer|null $customer */
                $customer = Customer::query()
                    ->where('normalized_phone', $challenge->normalized_phone)
                    ->lockForUpdate()
                    ->first();

                if ($customer !== null && ! $customer->isPhoneVerified()) {
                    $this->verifyCustomerPhoneAction->execute(
                        customer: $customer,
                        method: PhoneVerificationMethod::Otp,
                        verifiedBy: null
                    );
                }
            }

            $this->auditLogger->record(
                action: 'otp.verification.succeeded',
                subject: $challenge,
                metadata: [
                    'challenge_id' => $challenge->challenge_id,
                    'masked_phone' => $challenge->masked_phone,
                    'purpose' => $challenge->purpose->value,
                ]
            );
        });

        if ($failureException !== null) {
            throw $failureException;
        }

        return $challenge;
    }
}
