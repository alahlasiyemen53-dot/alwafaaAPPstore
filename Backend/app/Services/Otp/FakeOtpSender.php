<?php

namespace App\Services\Otp;

use App\Contracts\OtpSenderInterface;
use App\Enums\OtpPurpose;
use App\ValueObjects\OtpSendResult;

final class FakeOtpSender implements OtpSenderInterface
{
    /** @var array<int, array{phone: string, code: string, purpose: OtpPurpose, sent_at: string}> */
    private array $sent = [];

    private bool $shouldFail = false;
    private string $failureReason = 'Simulated provider failure';

    public function send(string $normalizedPhone, string $code, OtpPurpose $purpose): OtpSendResult
    {
        if ($this->shouldFail) {
            return OtpSendResult::failure('fake', $this->failureReason);
        }

        $this->sent[] = [
            'phone' => $normalizedPhone,
            'code' => $code,
            'purpose' => $purpose,
            'sent_at' => now()->toIso8601String(),
        ];

        return OtpSendResult::success('fake', 'msg_fake_'.uniqid());
    }

    /**
     * @return array<int, array{phone: string, code: string, purpose: OtpPurpose, sent_at: string}>
     */
    public function getSentMessages(): array
    {
        return $this->sent;
    }

    public function getLastCodeFor(string $normalizedPhone): ?string
    {
        for ($i = count($this->sent) - 1; $i >= 0; $i--) {
            if ($this->sent[$i]['phone'] === $normalizedPhone) {
                return $this->sent[$i]['code'];
            }
        }

        return null;
    }

    public function hasSentTo(string $normalizedPhone): bool
    {
        return $this->getLastCodeFor($normalizedPhone) !== null;
    }

    public function simulateFailure(bool $fail = true, string $reason = 'Simulated provider failure'): void
    {
        $this->shouldFail = $fail;
        $this->failureReason = $reason;
    }

    public function clear(): void
    {
        $this->sent = [];
        $this->shouldFail = false;
    }
}
