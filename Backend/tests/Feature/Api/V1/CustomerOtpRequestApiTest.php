<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OtpChallengeStatus;
use App\Models\CustomerOtpChallenge;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class CustomerOtpRequestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('127.0.0.1|771234567');
        RateLimiter::clear('127.0.0.1|+967771234567');
    }

    public function test_valid_phone_creates_challenge_and_returns_safe_response(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => '771234567',
        ]);

        $response->assertOk()
            ->assertJsonPath('status', true)
            ->assertJsonPath('data.resend_after', 60)
            ->assertJsonPath('data.is_new_customer', true)
            ->assertJsonStructure([
                'status',
                'message',
                'data' => [
                    'challenge_id',
                    'expires_in',
                    'resend_after',
                    'is_new_customer',
                ],
            ])
            ->assertJsonMissingPath('data.code')
            ->assertJsonMissingPath('data.code_hash');

        $this->assertDatabaseHas('customer_otp_challenges', [
            'normalized_phone' => '+967771234567',
            'status' => OtpChallengeStatus::Pending->value,
        ]);
    }

    public function test_invalid_phone_returns_validation_error(): void
    {
        $response = $this->postJson('/api/v1/auth/otp/request', [
            'phone' => 'invalid-phone-string',
        ]);

        $response->assertStatus(422)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'VALIDATION_FAILED');
    }

    public function test_rapid_resend_within_cooldown_returns_429_otp_rate_limited(): void
    {
        // First request
        $res1 = $this->postJson('/api/v1/auth/otp/request', ['phone' => '771234567']);
        $res1->assertOk();

        // Immediate second request
        $res2 = $this->postJson('/api/v1/auth/otp/request', ['phone' => '771234567']);
        $res2->assertStatus(429)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'OTP_RATE_LIMITED')
            ->assertHeader('Retry-After');
    }

    public function test_request_after_cooldown_supersedes_previous_pending_challenge(): void
    {
        $now = Carbon::parse('2026-09-18 10:00:00');
        Carbon::setTestNow($now);

        $res1 = $this->postJson('/api/v1/auth/otp/request', ['phone' => '771234567']);
        $res1->assertOk();
        $firstChallengeId = $res1->json('data.challenge_id');

        // Advance past cooldown (65 seconds)
        Carbon::setTestNow($now->copy()->addSeconds(65));

        $res2 = $this->postJson('/api/v1/auth/otp/request', ['phone' => '771234567']);
        $res2->assertOk();
        $secondChallengeId = $res2->json('data.challenge_id');

        $this->assertNotSame($firstChallengeId, $secondChallengeId);

        // Previous challenge must be marked superseded
        $firstChallenge = CustomerOtpChallenge::where('challenge_id', $firstChallengeId)->first();
        $this->assertSame(OtpChallengeStatus::Superseded, $firstChallenge->status);
        $this->assertNotNull($firstChallenge->consumed_at);

        Carbon::setTestNow();
    }
}
