<?php

namespace Tests\Feature\Otp;

use App\Actions\Otp\RequestCustomerOtpAction;
use App\Actions\Otp\VerifyCustomerOtpAction;
use App\Actions\VerifyCustomerPhoneAction;
use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Enums\PhoneVerificationMethod;
use App\Exceptions\Otp\ExhaustedOtpException;
use App\Exceptions\Otp\ExpiredOtpException;
use App\Exceptions\Otp\InvalidOtpException;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Services\AuditLogger;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerOtpLifecycleTest extends TestCase
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

    public function test_successful_otp_verification_marks_customer_phone_verified(): void
    {
        $customer = Customer::create([
            'name' => 'سارة أحمد',
            'phone' => '770123456',
            'normalized_phone' => '+967770123456',
            'is_active' => true,
        ]);

        $this->assertFalse($customer->isPhoneVerified());

        $challenge = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
        $code = $this->sender->getLastCodeFor('+967770123456');
        $this->assertNotNull($code);

        $verifiedChallenge = $this->verifyAction->execute($challenge->challenge_id, $code);

        $this->assertSame(OtpChallengeStatus::Verified, $verifiedChallenge->status);
        $this->assertNotNull($verifiedChallenge->consumed_at);

        // Verify Customer was automatically marked as OTP verified
        $customer->refresh();
        $this->assertTrue($customer->isPhoneVerified());
        $this->assertSame(PhoneVerificationMethod::Otp, $customer->phone_verification_method);
        $this->assertNull($customer->phone_verified_by);
        $this->assertNotNull($customer->phone_verified_at);

        // Verify audit logs
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'otp.challenge.created',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'otp.verification.succeeded',
        ]);
    }

    public function test_wrong_code_increments_attempts_and_reports_remaining(): void
    {
        $challenge = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);

        try {
            $this->verifyAction->execute($challenge->challenge_id, '000000');
            $this->fail('Expected InvalidOtpException was not thrown.');
        } catch (InvalidOtpException $e) {
            $this->assertSame(4, $e->attemptsRemaining);
            $this->assertStringContainsString('4 محاولات', $e->getMessage());
        }

        $challenge->refresh();
        $this->assertSame(1, $challenge->attempts);
        $this->assertSame(OtpChallengeStatus::Pending, $challenge->status);
    }

    public function test_exceeding_max_attempts_exhausts_challenge(): void
    {
        $challenge = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
        $correctCode = $this->sender->getLastCodeFor('+967770123456');

        // Fail 4 times
        for ($i = 0; $i < 4; $i++) {
            try {
                $this->verifyAction->execute($challenge->challenge_id, '000000');
            } catch (InvalidOtpException) {
            }
        }

        $challenge->refresh();
        $this->assertSame(4, $challenge->attempts);

        // 5th failed attempt -> Exhausted
        $this->expectException(ExhaustedOtpException::class);
        $this->verifyAction->execute($challenge->challenge_id, '000000');

        $challenge->refresh();
        $this->assertSame(5, $challenge->attempts);
        $this->assertSame(OtpChallengeStatus::Exhausted, $challenge->status);

        // Even with correct code, subsequent verification is rejected
        $this->expectException(ExhaustedOtpException::class);
        $this->verifyAction->execute($challenge->challenge_id, $correctCode);
    }

    public function test_expired_challenge_is_rejected(): void
    {
        $challenge = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
        $code = $this->sender->getLastCodeFor('+967770123456');

        // Travel 305 seconds
        $this->travel(305)->seconds();

        $this->expectException(ExpiredOtpException::class);
        $this->verifyAction->execute($challenge->challenge_id, $code);

        $challenge->refresh();
        $this->assertSame(OtpChallengeStatus::Expired, $challenge->status);
    }

    public function test_replay_attack_on_already_consumed_challenge_is_rejected(): void
    {
        $challenge = $this->requestAction->execute('770123456', OtpPurpose::PhoneVerification);
        $code = $this->sender->getLastCodeFor('+967770123456');

        // First verification succeeds
        $this->verifyAction->execute($challenge->challenge_id, $code);

        // Replay attempt fails
        $this->expectException(InvalidOtpException::class);
        $this->verifyAction->execute($challenge->challenge_id, $code);
    }

    public function test_login_purpose_does_not_modify_customer_phone_verification_state(): void
    {
        $customer = Customer::create([
            'name' => 'علي صالح',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $this->assertFalse($customer->isPhoneVerified());

        $challenge = $this->requestAction->execute('771234567', OtpPurpose::Login);
        $code = $this->sender->getLastCodeFor('+967771234567');

        $this->verifyAction->execute($challenge->challenge_id, $code);

        $customer->refresh();
        $this->assertFalse($customer->isPhoneVerified());
    }

    public function test_verify_by_phone_convenience_method(): void
    {
        $customer = Customer::create([
            'name' => 'هدى محمد',
            'phone' => '773334444',
            'normalized_phone' => '+967773334444',
            'is_active' => true,
        ]);

        $this->requestAction->execute('773334444', OtpPurpose::PhoneVerification);
        $code = $this->sender->getLastCodeFor('+967773334444');

        $verified = $this->verifyAction->executeByPhone('773334444', $code, OtpPurpose::PhoneVerification);

        $this->assertSame(OtpChallengeStatus::Verified, $verified->status);
        $customer->refresh();
        $this->assertTrue($customer->isPhoneVerified());
    }
}
