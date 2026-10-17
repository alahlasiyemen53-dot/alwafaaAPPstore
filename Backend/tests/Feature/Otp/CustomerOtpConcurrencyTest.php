<?php

namespace Tests\Feature\Otp;

use App\Actions\Otp\RequestCustomerOtpAction;
use App\Actions\Otp\VerifyCustomerOtpAction;
use App\Actions\VerifyCustomerPhoneAction;
use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Enums\PhoneVerificationMethod;
use App\Exceptions\Otp\InvalidOtpException;
use App\Exceptions\Otp\OtpCooldownException;
use App\Models\Customer;
use App\Models\CustomerOtpChallenge;
use App\Services\AuditLogger;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerOtpConcurrencyTest extends TestCase
{
    use RefreshDatabase;

    private FakeOtpSender $sender;
    private RequestCustomerOtpAction $requestAction;
    private VerifyCustomerOtpAction $verifyAction;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sender = new FakeOtpSender();
        $this->requestAction = new RequestCustomerOtpAction(
            otpSender: $this->sender,
            auditLogger: app(AuditLogger::class),
        );
        $this->verifyAction = new VerifyCustomerOtpAction(
            verifyCustomerPhoneAction: app(VerifyCustomerPhoneAction::class),
            auditLogger: app(AuditLogger::class),
        );
    }

    public function test_rapid_requests_for_same_phone_enforces_cooldown_and_single_active_challenge(): void
    {
        // 1. Initial request succeeds
        $challenge1 = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
        $this->assertSame(OtpChallengeStatus::Pending, $challenge1->status);

        // 2. Immediate second request must trigger Cooldown
        $this->expectException(OtpCooldownException::class);
        $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
    }

    public function test_resend_after_cooldown_supersedes_previous_challenge_preventing_stale_verification(): void
    {
        $customer = Customer::create([
            'name' => 'نور الهدى',
            'phone' => '770123456',
            'normalized_phone' => '+967770123456',
            'is_active' => true,
        ]);

        // 1. First challenge created
        $challenge1 = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
        $code1 = $this->sender->getLastCodeFor('+967770123456');

        // 2. Fast forward past cooldown
        $this->travel(65)->seconds();

        // 3. Second challenge created
        $challenge2 = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
        $code2 = $this->sender->getLastCodeFor('+967770123456');

        $challenge1->refresh();
        $this->assertSame(OtpChallengeStatus::Superseded, $challenge1->status);
        $this->assertNotNull($challenge1->consumed_at);

        // 4. Attempting to verify the older superseded challenge must fail
        try {
            $this->verifyAction->execute($challenge1->challenge_id, $code1);
            $this->fail('Expected InvalidOtpException for superseded challenge');
        } catch (InvalidOtpException $e) {
            $this->assertStringContainsString('تم استخدام أو إبطال', $e->getMessage());
        }

        // 5. Verifying the latest active challenge succeeds
        $verified = $this->verifyAction->execute($challenge2->challenge_id, $code2);
        $this->assertSame(OtpChallengeStatus::Verified, $verified->status);

        $customer->refresh();
        $this->assertTrue($customer->isPhoneVerified());
        $this->assertSame(PhoneVerificationMethod::Otp, $customer->phone_verification_method);
    }

    public function test_double_verify_attempts_on_same_challenge_enforces_single_use(): void
    {
        $customer = Customer::create([
            'name' => 'أروى صالح',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $challenge = $this->requestAction->execute('771234567', OtpPurpose::PhoneVerification);
        $code = $this->sender->getLastCodeFor('+967771234567');

        // First verification succeeds
        $firstVerify = $this->verifyAction->execute($challenge->challenge_id, $code);
        $this->assertSame(OtpChallengeStatus::Verified, $firstVerify->status);
        $this->assertNotNull($firstVerify->consumed_at);

        // Second verification with identical code must fail
        $this->expectException(InvalidOtpException::class);
        $this->expectExceptionMessage('تم استخدام أو إبطال رمز التحقق مسبقاً.');
        $this->verifyAction->execute($challenge->challenge_id, $code);
    }

    public function test_wrong_attempt_followed_by_correct_attempt_within_limit_succeeds(): void
    {
        $challenge = $this->requestAction->execute('772345678', OtpPurpose::PhoneVerification);
        $code = $this->sender->getLastCodeFor($challenge->normalized_phone);

        // First attempt with wrong code
        try {
            $this->verifyAction->execute($challenge->challenge_id, '000000');
            $this->fail('Expected InvalidOtpException');
        } catch (InvalidOtpException $e) {
            $this->assertSame(4, $e->attemptsRemaining);
        }

        $challenge->refresh();
        $this->assertSame(1, $challenge->attempts);
        $this->assertSame(OtpChallengeStatus::Pending, $challenge->status);

        // Second attempt with correct code
        $verified = $this->verifyAction->execute($challenge->challenge_id, $code);
        $this->assertSame(OtpChallengeStatus::Verified, $verified->status);
        $this->assertSame(1, $verified->attempts);
    }
}
