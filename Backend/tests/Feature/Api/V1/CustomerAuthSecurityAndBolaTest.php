<?php

namespace Tests\Feature\Api\V1;

use App\Enums\OtpChallengeStatus;
use App\Enums\OtpPurpose;
use App\Models\Customer;
use App\Models\CustomerOtpChallenge;
use App\Services\Auth\CustomerJwtService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\TestCase;

class CustomerAuthSecurityAndBolaTest extends TestCase
{
    use RefreshDatabase;

    private CustomerJwtService $jwtService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->jwtService = app(CustomerJwtService::class);
    }

    public function test_bola_cannot_verify_phone_a_using_challenge_id_of_phone_b(): void
    {
        // Challenge created for Phone B (772222222)
        $challengeB = CustomerOtpChallenge::create([
            'challenge_id' => (string) Str::uuid(),
            'phone' => '772222222',
            'normalized_phone' => '+967772222222',
            'purpose' => OtpPurpose::Login,
            'code_hash' => Hash::make('123456'),
            'status' => OtpChallengeStatus::Pending,
            'attempts' => 0,
            'max_attempts' => 5,
            'expires_at' => now()->addMinutes(5),
            'last_sent_at' => now(),
        ]);

        Customer::create([
            'name' => 'Phone A Owner',
            'phone' => '771111111',
            'normalized_phone' => '+967771111111',
            'is_active' => true,
        ]);

        // Attacker attempts to verify Phone A (771111111) with Challenge B's ID and code
        $response = $this->postJson('/api/v1/auth/otp/verify', [
            'phone' => '771111111',
            'challenge_id' => $challengeB->challenge_id,
            'code' => '123456',
        ]);

        $response->assertStatus(400)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'OTP_CHALLENGE_INVALID');
    }

    public function test_jwt_via_query_parameter_is_strictly_rejected(): void
    {
        $customer = Customer::create([
            'name' => 'Sara Query Test',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $token = $this->jwtService->generateAccessToken($customer);

        // Attempt authentication via query parameter ?token=
        $response = $this->getJson("/api/v1/me?token={$token}");

        $response->assertStatus(401)
            ->assertJsonPath('status', false)
            ->assertJsonPath('data.code', 'AUTH_REQUIRED');
    }

    public function test_enumeration_safety_returns_identical_structure_for_new_and_existing_phones(): void
    {
        // Existing phone
        Customer::create([
            'name' => 'Existing Customer',
            'phone' => '771234567',
            'normalized_phone' => '+967771234567',
            'is_active' => true,
        ]);

        $resExisting = $this->postJson('/api/v1/auth/otp/request', ['phone' => '771234567']);
        $resExisting->assertOk();

        // Non-existent (new) phone
        $resNew = $this->postJson('/api/v1/auth/otp/request', ['phone' => '779999999']);
        $resNew->assertOk();

        // Structures and messages must be identical
        $this->assertSame($resExisting->json('status'), $resNew->json('status'));
        $this->assertSame($resExisting->json('message'), $resNew->json('message'));
        $this->assertArrayHasKey('challenge_id', $resExisting->json('data'));
        $this->assertArrayHasKey('challenge_id', $resNew->json('data'));
        $this->assertArrayNotHasKey('is_existing', $resExisting->json('data'));
        $this->assertArrayNotHasKey('is_existing', $resNew->json('data'));
    }
}
