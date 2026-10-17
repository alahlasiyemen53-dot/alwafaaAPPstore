<?php

namespace Tests\Unit\Otp;

use App\Actions\Otp\RequestCustomerOtpAction;
use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Exceptions\Otp\OtpCooldownException;
use App\Models\CustomerOtpChallenge;
use App\Services\AuditLogger;
use App\Services\Otp\FakeOtpSender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use InvalidArgumentException;
use Tests\TestCase;

final class CustomerOtpPolicyTest extends TestCase
{
    use RefreshDatabase;

    private FakeOtpSender $sender;
    private RequestCustomerOtpAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sender = new FakeOtpSender();
        $this->action = new RequestCustomerOtpAction(
            otpSender: $this->sender,
            auditLogger: app(AuditLogger::class),
        );
    }

    public function test_request_otp_generates_cryptographically_secure_code(): void
    {
        $challenge = $this->action->execute('770123456', OtpPurpose::PhoneVerification);

        $this->assertNotNull($challenge->challenge_id);
        $this->assertSame('+967770123456', $challenge->normalized_phone);
        $this->assertSame(OtpPurpose::PhoneVerification, $challenge->purpose);
        $this->assertSame(OtpChallengeStatus::Pending, $challenge->status);
        $this->assertSame(0, $challenge->attempts);
        $this->assertSame(5, $challenge->max_attempts);
        $this->assertNull($challenge->consumed_at);
        $this->assertTrue($challenge->canAttempt());
        $this->assertFalse($challenge->isExpired());

        // Verify that sender received the generated code
        $this->assertTrue($this->sender->hasSentTo('+967770123456'));
        $code = $this->sender->getLastCodeFor('+967770123456');
        $this->assertNotNull($code);
        $this->assertSame(6, strlen($code));
        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);

        // Code hash must match the sent code
        $this->assertTrue(Hash::check($code, $challenge->code_hash));
    }

    public function test_code_hash_is_hidden_from_model_serialization(): void
    {
        $challenge = $this->action->execute('770123456', OtpPurpose::PhoneVerification);
        $array = $challenge->toArray();

        $this->assertArrayNotHasKey('code_hash', $array);
    }

    public function test_masked_phone_attribute(): void
    {
        $challenge = $this->action->execute('770123456', OtpPurpose::PhoneVerification);

        $this->assertSame('+96777****456', $challenge->masked_phone);
    }

    public function test_invalid_phone_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->action->execute('invalid-phone', OtpPurpose::PhoneVerification);
    }

    public function test_resend_cooldown_is_enforced(): void
    {
        $this->action->execute('770123456', OtpPurpose::PhoneVerification);

        $this->expectException(OtpCooldownException::class);
        $this->action->execute('770123456', OtpPurpose::PhoneVerification);
    }

    public function test_new_otp_request_after_cooldown_supersedes_previous_active_challenge(): void
    {
        $challenge1 = $this->action->execute('770123456', OtpPurpose::PhoneVerification);

        // Fast-forward past cooldown (65 seconds)
        $this->travel(65)->seconds();

        $challenge2 = $this->action->execute('770123456', OtpPurpose::PhoneVerification);

        $challenge1->refresh();
        $this->assertSame(OtpChallengeStatus::Superseded, $challenge1->status);
        $this->assertNotNull($challenge1->consumed_at);
        $this->assertFalse($challenge1->canAttempt());

        $this->assertSame(OtpChallengeStatus::Pending, $challenge2->status);
        $this->assertTrue($challenge2->canAttempt());
    }

    public function test_challenge_expiry_predicate(): void
    {
        $challenge = $this->action->execute('770123456', OtpPurpose::PhoneVerification);
        $this->assertFalse($challenge->isExpired());

        // Travel 301 seconds (past 300s expiry)
        $this->travel(301)->seconds();

        $this->assertTrue($challenge->isExpired());
        $this->assertFalse($challenge->canAttempt());
    }

    public function test_challenge_exhausted_predicate(): void
    {
        $challenge = $this->action->execute('770123456', OtpPurpose::PhoneVerification);
        $this->assertFalse($challenge->isExhausted());

        $challenge->attempts = 5;
        $this->assertTrue($challenge->isExhausted());
        $this->assertFalse($challenge->canAttempt());
    }
}
